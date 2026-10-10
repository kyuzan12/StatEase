<?php

namespace App\Services;

use App\Models\LikertScale;
use App\Models\Survey;
use Illuminate\Support\Collection;

/**
 * All of StatEase's computations. Controllers call these methods; no math is done in the views.
 */
class StatisticsService
{
    /**
     * The mean ranges used to interpret a weighted mean, for any number of points.
     *
     * Interval width = (highest - lowest) / number of points.
     *   4-point: (4 - 1) / 4 = 0.75 -> 1.00-1.75, 1.76-2.50, 2.51-3.25, 3.26-4.00
     *   5-point: (5 - 1) / 5 = 0.80 -> 1.00-1.80, 1.81-2.60, 2.61-3.40, 3.41-4.20, 4.21-5.00
     *   7-point: (7 - 1) / 7 = 0.857 -> 1.00-1.86, 1.87-2.71, ... , 6.15-7.00
     *
     * @return array<int, array{value: int, min: float, max: float, label: string, interpretation: string, background: string, text: string}>
     */
    public function interpretationScale(LikertScale $scale): array
    {
        $points = $scale->points;
        $interval = ($points - 1) / $points;
        $colors = $scale->colors();
        $ranges = [];

        foreach ($scale->levels as $index => $level) {
            $ranges[] = [
                'value' => $level->value,
                'min' => $index === 0 ? 1.00 : round(round(1 + $index * $interval, 2) + 0.01, 2),
                'max' => round(1 + ($index + 1) * $interval, 2),
                'label' => $level->label,
                'interpretation' => $level->displayInterpretation(),
                'background' => $colors[$level->value]['background'],
                'text' => $colors[$level->value]['text'],
            ];
        }

        return $ranges;
    }

    /**
     * Find the range a weighted mean falls into, e.g. 3.41 on a 4-point scale -> the "Strongly Agree" range.
     */
    public function interpret(float $mean, array $ranges): array
    {
        $mean = round($mean, 2); // interpret the same value that is displayed

        foreach ($ranges as $range) {
            if ($mean <= $range['max']) {
                return $range;
            }
        }

        return end($ranges);
    }

    /**
     * Everything shown on the results page and in the Word report.
     * Profile tables come first (Table 1, 2, ...), then the weighted mean table.
     */
    public function fullResults(Survey $survey): array
    {
        $profiles = $this->profileSummary($survey);

        return [
            'profiles' => $profiles,
            'likert' => $this->likertSummary($survey),
            'likert_table_number' => count($profiles) + 1,
        ];
    }

    /**
     * Weighted mean, interpretation and rank of every Likert item, plus the overall mean.
     *
     * Weighted mean = Σ(f × w) / N
     *   f = number of respondents who chose a scale point
     *   w = the scale point's value (1 to the number of points)
     *   N = number of respondents who answered the item (blanks are excluded)
     */
    public function likertSummary(Survey $survey): array
    {
        $scale = $survey->scale;
        $ranges = $this->interpretationScale($scale);
        $respondents = $survey->respondents()->get(['answers']);
        $items = [];

        foreach ($survey->likertColumns as $column) {
            // Frequency of each scale point, highest first, e.g. [4 => 10, 3 => 15, 2 => 5, 1 => 0]
            $frequencies = array_fill_keys(range($scale->points, 1), 0);

            foreach ($respondents as $respondent) {
                $answer = $respondent->answerFor($column);
                $value = ($answer === null || $answer === '') ? null : $scale->valueFor($answer);

                if ($value !== null) {
                    $frequencies[$value]++;
                }
            }

            $n = array_sum($frequencies);
            $sumOfProducts = 0;
            foreach ($frequencies as $weight => $frequency) {
                $sumOfProducts += $frequency * $weight;
            }

            $mean = $n > 0 ? $sumOfProducts / $n : null;

            $items[] = [
                'column' => $column,
                'frequencies' => $frequencies,
                'percentages' => array_map(fn ($f) => $n > 0 ? round($f / $n * 100, 2) : 0, $frequencies),
                'n' => $n,
                'sum_of_products' => $sumOfProducts,
                'mean' => $mean !== null ? round($mean, 2) : null,
                'level' => $mean !== null ? $this->interpret($mean, $ranges) : null,
            ];
        }

        $items = $this->rank($items);

        // Overall (composite) mean = average of the items' weighted means.
        $means = array_filter(array_column($items, 'mean'), fn ($mean) => $mean !== null);
        $overall = count($means) > 0 ? round(array_sum($means) / count($means), 2) : null;

        $answered = array_values(array_filter($items, fn ($item) => $item['mean'] !== null));

        return [
            'items' => $items,
            'overall_mean' => $overall,
            'overall_level' => $overall !== null ? $this->interpret($overall, $ranges) : null,
            'highest' => $answered ? collect($answered)->sortByDesc('mean')->first() : null,
            'lowest' => $answered ? collect($answered)->sortBy('mean')->first() : null,
            'ranges' => $ranges,
        ];
    }

    /**
     * Frequency and percentage of each answer to one profile question (e.g. Sex: Male 12, Female 18).
     *
     * Percentage = (f / N) × 100, where N is everyone who answered the question.
     */
    public function profileSummary(Survey $survey): array
    {
        $respondents = $survey->respondents()->get(['answers']);
        $tables = [];

        foreach ($survey->profileColumns as $column) {
            $counts = [];
            $blank = 0;

            foreach ($respondents as $respondent) {
                $answer = $respondent->answerFor($column);

                if ($answer === null || $answer === '') {
                    $blank++;
                    continue;
                }

                // "female", "Female " and "FEMALE" count as the same answer.
                $key = mb_strtolower($answer);
                $counts[$key] ??= ['label' => $answer, 'frequency' => 0];
                $counts[$key]['frequency']++;
            }

            // Natural order: "1st Year, 2nd Year, 3rd Year" instead of by count.
            uasort($counts, fn ($a, $b) => strnatcasecmp($a['label'], $b['label']));

            $answered = array_sum(array_column($counts, 'frequency'));

            $rows = array_values(array_map(fn ($row) => $row + [
                'percentage' => $answered > 0 ? round($row['frequency'] / $answered * 100, 2) : 0,
            ], $counts));

            $tables[] = [
                'column' => $column,
                'rows' => $rows,
                'answered' => $answered,
                'blank' => $blank,
                'mode' => $rows ? collect($rows)->sortByDesc('frequency')->first() : null, // most common answer
            ];
        }

        return $tables;
    }

    /**
     * Slovin's formula: n = N / (1 + N × e²)
     *   N = population size, e = margin of error (0.05 for 5%)
     * The result is rounded UP, because you cannot survey part of a person
     * and rounding down would make the margin of error bigger than intended.
     */
    public function slovin(int $population, float $marginOfError): array
    {
        $eSquared = $marginOfError ** 2;
        $denominator = 1 + $population * $eSquared;
        $exact = $population / $denominator;

        return [
            'population' => $population,
            'margin_of_error' => $marginOfError,
            'e_squared' => $eSquared,
            'n_times_e_squared' => $population * $eSquared,
            'denominator' => $denominator,
            'exact' => $exact,
            'sample_size' => (int) ceil(round($exact, 6)), // round first so 100.0000001 does not become 101
        ];
    }

    /**
     * Rank items from highest mean (rank 1) to lowest.
     * Tied items share the average of their positions, e.g. two items tied for 2nd both get rank 2.5.
     */
    private function rank(array $items): array
    {
        $means = Collection::make($items)
            ->pluck('mean')
            ->filter(fn ($mean) => $mean !== null)
            ->sortDesc()
            ->values();

        foreach ($items as &$item) {
            if ($item['mean'] === null) {
                $item['rank'] = null;
                continue;
            }

            // Positions (1-based) of every item with this same mean.
            $positions = $means->keys()->filter(fn ($i) => $means[$i] == $item['mean'])->map(fn ($i) => $i + 1);
            $item['rank'] = $positions->avg();
        }

        return $items;
    }
}
