<?php

namespace App\Services;

use App\Exceptions\SurveyImportException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Reads the first worksheet of an Excel .xlsx file, using only PHP's built-in ZipArchive and SimpleXML.
 *
 * An .xlsx file is a ZIP archive of XML files:
 *   xl/workbook.xml          list of sheets
 *   xl/_rels/...rels         which XML file belongs to which sheet
 *   xl/worksheets/sheet1.xml the cells
 *   xl/sharedStrings.xml     text values (cells only store a number pointing into this list)
 *   xl/styles.xml            number formats (needed to recognize dates)
 */
class XlsxReader
{
    private const NS_RELATIONSHIPS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /** Refuse XML parts larger than this when unzipped, so a tiny "zip bomb" cannot use up the server's memory. */
    private const MAX_PART_BYTES = 50 * 1024 * 1024;

    /** Excel's built-in number formats that display dates and times. */
    private const BUILT_IN_DATE_FORMATS = [14, 15, 16, 17, 18, 19, 20, 21, 22, 45, 46, 47];

    /**
     * @return array<int, array<int, string>>  Excel row number => cell values (column A = index 0)
     */
    public function rows(string $path): array
    {
        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new SurveyImportException(
                'This is not a valid Excel (.xlsx) file. If it is an old .xls file, open it in Excel and save it as .xlsx or .csv.'
            );
        }

        try {
            $workbook = $this->xml($zip, 'xl/workbook.xml')
                ?? throw new SurveyImportException('This Excel file has no workbook. Please save it again in Excel.');

            $sheet = $this->xml($zip, $this->firstSheetPath($zip, $workbook))
                ?? throw new SurveyImportException('The first worksheet of this Excel file could not be read.');

            $sharedStrings = $this->sharedStrings($zip);
            $dateStyles = $this->dateStyleIndexes($zip);
            $uses1904Dates = in_array(strtolower((string) ($workbook->workbookPr['date1904'] ?? '')), ['1', 'true'], true);

            $rows = [];
            $previousRow = 0;

            foreach ($sheet->sheetData->row ?? [] as $row) {
                $rowNumber = isset($row['r']) ? (int) $row['r'] : $previousRow + 1;
                $previousRow = $rowNumber;

                // Empty cells are often left out of the file, so place each cell by its reference (e.g. "C5").
                $cells = [];
                $nextColumn = 0;
                foreach ($row->c as $cell) {
                    $column = isset($cell['r']) ? $this->columnIndex((string) $cell['r']) : $nextColumn;
                    $cells[$column] = $this->cellValue($cell, $sharedStrings, $dateStyles, $uses1904Dates);
                    $nextColumn = $column + 1;
                }

                $values = $cells ? array_fill(0, max(array_keys($cells)) + 1, '') : [];
                foreach ($cells as $column => $value) {
                    $values[$column] = $value;
                }

                $rows[$rowNumber] = $values;
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    /**
     * Find the XML file of the first sheet through the workbook's relationships.
     */
    private function firstSheetPath(ZipArchive $zip, SimpleXMLElement $workbook): string
    {
        $firstSheet = $workbook->sheets->sheet[0] ?? null;
        $relationshipId = $firstSheet ? (string) $firstSheet->attributes(self::NS_RELATIONSHIPS)['id'] : '';
        $relationships = $this->xml($zip, 'xl/_rels/workbook.xml.rels');

        foreach ($relationships->Relationship ?? [] as $relationship) {
            if ((string) $relationship['Id'] === $relationshipId) {
                $target = (string) $relationship['Target'];

                // Targets are relative to the "xl" folder, or absolute when they start with "/".
                return str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /**
     * @return string[]  index => text
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $this->xml($zip, 'xl/sharedStrings.xml');
        $strings = [];

        foreach ($xml->si ?? [] as $item) {
            $strings[] = $this->text($item);
        }

        return $strings;
    }

    /**
     * Which cell styles display a date, so their numbers can be shown as dates instead of e.g. 46296.54.
     *
     * @return array<int, true>  style index => true
     */
    private function dateStyleIndexes(ZipArchive $zip): array
    {
        $styles = $this->xml($zip, 'xl/styles.xml');
        if ($styles === null) {
            return [];
        }

        $dateFormats = array_fill_keys(self::BUILT_IN_DATE_FORMATS, true);
        foreach ($styles->numFmts->numFmt ?? [] as $format) {
            // Remove quoted text and [color]/[locale] parts, then look for day, month, year or hour codes.
            $code = preg_replace(['/"[^"]*"/', '/\[[^\]]*\]/'], '', (string) $format['formatCode']);
            if (preg_match('/[dmyh]/i', $code)) {
                $dateFormats[(int) $format['numFmtId']] = true;
            }
        }

        // A cell's "s" attribute is its position in this list. SimpleXML's foreach has no numeric keys,
        // so we count the position ourselves.
        $indexes = [];
        $position = 0;
        foreach ($styles->cellXfs->xf ?? [] as $xf) {
            if (isset($dateFormats[(int) $xf['numFmtId']])) {
                $indexes[$position] = true;
            }
            $position++;
        }

        return $indexes;
    }

    private function cellValue(SimpleXMLElement $cell, array $sharedStrings, array $dateStyles, bool $uses1904Dates): string
    {
        $type = (string) ($cell['t'] ?? 'n');
        $raw = (string) ($cell->v ?? '');

        return match ($type) {
            's' => $sharedStrings[(int) $raw] ?? '',                 // shared text
            'inlineStr' => $this->text($cell->is),                 // text stored in the cell
            'str', 'd' => $raw,                                    // formula result text / ISO date
            'b' => $raw === '1' ? 'TRUE' : 'FALSE',
            'e' => '',                                             // errors such as #N/A count as blank
            default => $this->number($raw, isset($dateStyles[(int) ($cell['s'] ?? 0)]), $uses1904Dates),
        };
    }

    private function number(string $raw, bool $isDate, bool $uses1904Dates): string
    {
        if ($raw === '' || ! is_numeric($raw)) {
            return $raw;
        }

        $value = (float) $raw;

        if ($isDate) {
            // Excel stores dates as days since 1899-12-30 (or 1904-01-01): 25569 days before 1970-01-01.
            $days = $value - ($uses1904Dates ? 24107 : 25569);
            $timestamp = (int) round($days * 86400);

            return floor($value) == $value ? gmdate('Y-m-d', $timestamp) : gmdate('Y-m-d H:i:s', $timestamp);
        }

        // 4 -> "4", 3.5 -> "3.5" (and remove tiny floating point noise such as 3.5000000000000004)
        return floor($value) == $value && abs($value) < 1e15
            ? (string) (int) $value
            : (string) round($value, 10);
    }

    /**
     * Text of a shared or inline string. Formatted ("rich") text is split into runs (<r>); join them.
     */
    private function text(?SimpleXMLElement $item): string
    {
        if ($item === null) {
            return '';
        }

        if (isset($item->t)) {
            return (string) $item->t;
        }

        $text = '';
        foreach ($item->r as $run) {
            $text .= (string) $run->t;
        }

        return $text;
    }

    /**
     * "A1" -> 0, "B7" -> 1, "AA3" -> 26
     */
    private function columnIndex(string $reference): int
    {
        $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', $reference));
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }

    /**
     * Read one XML part of the archive, or null if it does not exist.
     */
    private function xml(ZipArchive $zip, string $name): ?SimpleXMLElement
    {
        $info = $zip->statName($name);
        if ($info === false) {
            return null;
        }

        if ($info['size'] > self::MAX_PART_BYTES) {
            throw new SurveyImportException('This Excel file is too large to read.');
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($zip->getFromName($name), SimpleXMLElement::class, LIBXML_NONET);
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            throw new SurveyImportException('This Excel file is damaged and could not be read.');
        }

        return $xml;
    }
}
