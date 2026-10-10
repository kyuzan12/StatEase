<?php

namespace App\Services;

use App\Exceptions\SurveyImportException;
use App\Models\LikertScale;
use App\Models\SurveyColumn;

/**
 * Reads an uploaded CSV or Excel (.xlsx) file and guesses what kind of data each column holds.
 *
 * Reading happens in two steps:
 *   1. readCsv() / XlsxReader turn the file into plain rows of text, keyed by row number.
 *   2. buildTable() does the same cleaning for both formats: header row, blank rows, limits.
 */
class SurveyImportService
{
    public const MAX_COLUMNS = 100;
    public const MAX_ROWS = 5000;

    public function __construct(private XlsxReader $xlsxReader)
    {
    }

    /**
     * Read an uploaded file into headers and rows.
     *
     * @param  string  $extension  "csv" or "xlsx"
     * @return array{headers: string[], rows: array<int, array{row_number: int, values: string[]}>}
     */
    public function parse(string $path, string $extension): array
    {
        $rows = strtolower($extension) === 'xlsx'
            ? $this->xlsxReader->rows($path)
            : $this->readCsv($path);

        return $this->buildTable($rows);
    }

    /**
     * Turn raw rows (row number => cell values) into a clean header row and data rows.
     *
     * @param  iterable<int, array<int, string|null>>  $rows
     */
    private function buildTable(iterable $rows): array
    {
        $headers = null;
        $data = [];

        foreach ($rows as $rowNumber => $values) {
            if ($this->isEmptyRow($values)) {
                continue; // skip blank lines (also blank lines before the header)
            }

            // 1. The first non-empty row is the header row
            if ($headers === null) {
                $headers = $this->cleanHeaders($values);

                if (count($headers) > self::MAX_COLUMNS) {
                    throw new SurveyImportException('The file has too many columns (maximum is '.self::MAX_COLUMNS.').');
                }

                continue;
            }

            // 2. Every row after it is one respondent
            if (count($data) >= self::MAX_ROWS) {
                throw new SurveyImportException('The file has too many rows (maximum is '.self::MAX_ROWS.' respondents).');
            }

            $data[] = [
                'row_number' => $rowNumber,
                'values' => $this->fitToHeaders($values, count($headers)),
            ];
        }

        if ($headers === null) {
            throw new SurveyImportException('The file is empty. The first row must contain the question headers.');
        }

        if (count($data) === 0) {
            throw new SurveyImportException('The file has a header row but no responses.');
        }

        return ['headers' => $headers, 'rows' => $data];
    }

    /**
     * Read a CSV file row by row. Row numbers start at 1, like in Excel.
     *
     * @return \Generator<int, array<int, string|null>>
     */
    private function readCsv(string $path): \Generator
    {
        $handle = @fopen($path, 'r');

        if ($handle === false) {
            throw new SurveyImportException('The uploaded file could not be opened.');
        }

        try {
            $delimiter = $this->detectDelimiter($handle);
            $rowNumber = 0;

            while (($values = fgetcsv($handle, null, $delimiter)) !== false) {
                yield ++$rowNumber => $values;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Guess a column's type from its header and answers, so the user only has to correct mistakes.
     *
     * @param  string[]  $values  all answers in this column
     */
    public function guessColumnType(string $header, array $values, LikertScale $scale): string
    {
        // 1. Identity columns ("Timestamp", "Email Address", "Name") are never analyzed.
        if (preg_match('/\b(timestamp|e-?mail|name)\b/i', $header)) {
            return SurveyColumn::TYPE_IGNORE;
        }

        $answers = array_values(array_filter($values, fn ($value) => $value !== ''));

        if (count($answers) === 0) {
            return SurveyColumn::TYPE_IGNORE;
        }

        // 2. Every answer is on the scale (a number like "4" or a label like "Agree") -> Likert item.
        //    This is checked before the comment keywords below, because the answers are stronger
        //    evidence than the words in a question (e.g. "I receive feedback promptly." is a Likert item).
        $allOnScale = true;
        foreach ($answers as $answer) {
            if ($scale->valueFor($answer) === null) {
                $allOnScale = false;
                break;
            }
        }

        if ($allOnScale) {
            return SurveyColumn::TYPE_LIKERT;
        }

        // 3. Open-ended questions ("Comments", "Suggestions") are not analyzed.
        if (preg_match('/\b(comments?|suggestions?|remarks?|feedback)\b/i', $header)) {
            return SurveyColumn::TYPE_IGNORE;
        }

        // 4. If more than 30% left it blank, it is most likely an optional text box.
        if (count($answers) < count($values) * 0.7) {
            return SurveyColumn::TYPE_IGNORE;
        }

        // 5. Only a few different answers (e.g. Male/Female) -> profile question.
        // Many different answers usually means free text (comments), which we ignore.
        $distinct = count(array_unique(array_map('mb_strtolower', $answers)));

        return $distinct <= 10 ? SurveyColumn::TYPE_PROFILE : SurveyColumn::TYPE_IGNORE;
    }

    /**
     * Excel in some countries saves CSVs with ";" instead of ",". Check the first line to find out.
     *
     * @param  resource  $handle
     */
    private function detectDelimiter($handle): string
    {
        $firstLine = (string) fgets($handle);
        rewind($handle);

        $counts = [
            ',' => substr_count($firstLine, ','),
            ';' => substr_count($firstLine, ';'),
            "\t" => substr_count($firstLine, "\t"),
        ];

        arsort($counts);

        return array_key_first($counts);
    }

    /**
     * @param  array<int, string|null>  $headers
     * @return string[]
     */
    private function cleanHeaders(array $headers): array
    {
        // Remove the invisible "byte order mark" that Excel and Google Sheets add to the first cell.
        $headers = array_values($headers);
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);

        // Drop empty cells at the end of the header row (common in files saved from Excel).
        while (count($headers) > 1 && trim((string) end($headers)) === '') {
            array_pop($headers);
        }

        foreach ($headers as $index => $header) {
            $header = trim($this->toUtf8((string) $header));
            $headers[$index] = $header !== '' ? $header : 'Column '.($index + 1);
        }

        return $headers;
    }

    /**
     * Make every row exactly as long as the header row, and clean each value.
     *
     * @param  array<int, string|null>  $values
     * @return string[]
     */
    private function fitToHeaders(array $values, int $columnCount): array
    {
        $values = array_slice(array_pad($values, $columnCount, ''), 0, $columnCount);

        return array_map(fn ($value) => trim($this->toUtf8((string) $value)), $values);
    }

    /**
     * @param  array<int, string|null>  $values
     */
    private function isEmptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * CSVs saved by older Excel versions are not UTF-8, which breaks letters like "ñ".
     */
    private function toUtf8(string $value): string
    {
        return mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }
}
