<?php

namespace App\Services;

use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Cronbach's alpha: how consistently a set of items measures the same thing (internal consistency).
 *
 *   α = (k / (k − 1)) × (1 − Σσᵢ² / σₜ²)
 *     k   = number of items
 *     σᵢ² = variance of each item
 *     σₜ² = variance of the respondents' total scores
 *
 * Only respondents who answered ALL selected items are used ("listwise deletion"),
 * because a missing answer would make that person's total score smaller.
 */
class ReliabilityService
{
    /**
     * George & Mallery (2003) rule of thumb, highest first.
     */
    public const INTERPRETATION = [
        [0.9, 'Excellent'],
        [0.8, 'Good'],
        [0.7, 'Acceptable'],
        [0.6, 'Questionable'],
        [0.5, 'Poor'],
        [-INF, 'Unacceptable'],
    ];

    /** Items whose corrected item-total correlation is below this are flagged. */
    public const WEAK_ITEM_CORRELATION = 0.30;

    /**
     * @param  Collection  $columns  the Likert items to test (at least 2)
     */
    public function analyze(ResponseMatrix $matrix, Collection $columns): array
    {
        if ($columns->count() < 2) {
            throw new InvalidArgumentException('Choose at least 2 items to compute Cronbach\'s alpha.');
        }

        // Matrix of complete respondents: rows = respondents, columns = items.
        $scoresPerItem = $columns->map(fn ($column) => $matrix->scores($column))->values()->all();
        $rows = [];
        foreach (array_keys($scoresPerItem[0]) as $i) {
            $row = array_column($scoresPerItem, $i);
            if (! in_array(null, $row, true)) {
                $rows[] = $row;
            }
        }

        $n = count($rows);
        if ($n < 3) {
            throw new InvalidArgumentException("Only {$n} respondent(s) answered all selected items. At least 3 are needed.");
        }

        $alpha = $this->alpha($rows);
        if ($alpha === null) {
            throw new InvalidArgumentException('Everyone gave the same total score, so reliability cannot be computed (the total variance is 0).');
        }

        // Item analysis
        $items = [];
        foreach ($columns->values() as $j => $column) {
            $itemScores = array_column($rows, $j);
            $restScores = array_map(fn ($row) => array_sum($row) - $row[$j], $rows); // total WITHOUT this item
            $withoutItem = array_map(function ($row) use ($j) {
                unset($row[$j]);

                return array_values($row);
            }, $rows);

            $items[] = [
                'column' => $column,
                'mean' => round(array_sum($itemScores) / $n, 2),
                'variance' => $this->variance($itemScores),
                'item_total_correlation' => $this->correlation($itemScores, $restScores),
                'alpha_if_deleted' => $columns->count() > 2 ? $this->alpha($withoutItem) : null,
            ];
        }

        foreach ($items as &$item) {
            $item['flag'] = $this->flag($item, $alpha);
        }

        return [
            'alpha' => $alpha,
            'interpretation' => $this->interpret($alpha),
            'k' => $columns->count(),
            'n' => $n,
            'excluded' => $matrix->respondentCount() - $n,
            'sum_item_variances' => array_sum(array_column($items, 'variance')),
            'total_variance' => $this->variance(array_map('array_sum', $rows)),
            'items' => $items,
        ];
    }

    public function interpret(float $alpha): string
    {
        foreach (self::INTERPRETATION as [$minimum, $label]) {
            if ($alpha >= $minimum) {
                return $label;
            }
        }

        return 'Unacceptable';
    }

    /**
     * @param  array<int, array<int, int>>  $rows
     */
    private function alpha(array $rows): ?float
    {
        $k = count($rows[0]);
        $itemVariances = 0;
        for ($j = 0; $j < $k; $j++) {
            $itemVariances += $this->variance(array_column($rows, $j));
        }

        $totalVariance = $this->variance(array_map('array_sum', $rows));
        if ($totalVariance == 0.0) {
            return null;
        }

        return ($k / ($k - 1)) * (1 - $itemVariances / $totalVariance);
    }

    /**
     * Sample variance: Σ(x − mean)² / (n − 1)
     */
    private function variance(array $values): float
    {
        $n = count($values);
        $mean = array_sum($values) / $n;

        return array_sum(array_map(fn ($x) => ($x - $mean) ** 2, $values)) / ($n - 1);
    }

    /**
     * Pearson correlation between two lists, or null if one of them never changes.
     */
    private function correlation(array $x, array $y): ?float
    {
        $mx = array_sum($x) / count($x);
        $my = array_sum($y) / count($y);
        $sxy = $sxx = $syy = 0;
        foreach ($x as $i => $xi) {
            $sxy += ($xi - $mx) * ($y[$i] - $my);
            $sxx += ($xi - $mx) ** 2;
            $syy += ($y[$i] - $my) ** 2;
        }

        return ($sxx == 0 || $syy == 0) ? null : $sxy / sqrt($sxx * $syy);
    }

    /**
     * Why an item may need revision, or null if it is fine.
     */
    private function flag(array $item, float $alpha): ?string
    {
        if ($item['alpha_if_deleted'] !== null && $item['alpha_if_deleted'] > $alpha + 0.001) {
            return 'Removing this item would raise alpha to '.number_format($item['alpha_if_deleted'], 3).'.';
        }

        if ($item['item_total_correlation'] === null) {
            return 'Everyone gave this item the same answer.';
        }

        if ($item['item_total_correlation'] < self::WEAK_ITEM_CORRELATION) {
            return 'Weak link to the other items (r < '.self::WEAK_ITEM_CORRELATION.'). Consider revising it.';
        }

        return null;
    }
}
