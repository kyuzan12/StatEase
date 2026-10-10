<?php

namespace App\Services;

use App\Models\SurveyColumn;
use App\Support\Distributions;
use InvalidArgumentException;

/**
 * Hypothesis tests: chi-square test of independence, Pearson r, and one-way ANOVA.
 *
 * Each test answers "Is the pattern in our sample strong enough that it is probably not due to chance?"
 * If p < α (usually 0.05), we reject the null hypothesis (H₀) and call the result significant.
 */
class InferentialService
{
    public const TEST_CHI_SQUARE = 'chi_square';
    public const TEST_PEARSON = 'pearson';
    public const TEST_ANOVA = 'anova';

    public const OVERALL = 'overall';

    /**
     * Chi-square test of independence: are two categorical variables related?
     *   Expected count E = (row total × column total) / N
     *   χ² = Σ (O − E)² / E,   df = (rows − 1)(columns − 1)
     *   Cramér's V = √(χ² / (N × (min(rows, columns) − 1)))  -> strength of the relationship
     */
    public function chiSquare(ResponseMatrix $matrix, SurveyColumn $rowVariable, SurveyColumn $columnVariable): array
    {
        $rowsData = $matrix->categories($rowVariable);
        $colsData = $matrix->categories($columnVariable);

        // Count every pair of answers (respondents who answered both)
        $observed = [];
        foreach ($rowsData as $i => $rowValue) {
            $colValue = $colsData[$i];
            if ($rowValue !== null && $colValue !== null) {
                $observed[$rowValue][$colValue] = ($observed[$rowValue][$colValue] ?? 0) + 1;
            }
        }

        $rowLabels = $this->sortLabels(array_keys($observed), $rowVariable, $matrix);
        $colLabels = $this->sortLabels(array_unique(array_merge(...array_map('array_keys', array_values($observed)) ?: [[]])), $columnVariable, $matrix);

        if (count($rowLabels) < 2 || count($colLabels) < 2) {
            throw new InvalidArgumentException('Each variable needs at least 2 different answers for a chi-square test.');
        }

        $rowTotals = $colTotals = [];
        foreach ($rowLabels as $r) {
            foreach ($colLabels as $c) {
                $observed[$r][$c] ??= 0;
                $rowTotals[$r] = ($rowTotals[$r] ?? 0) + $observed[$r][$c];
                $colTotals[$c] = ($colTotals[$c] ?? 0) + $observed[$r][$c];
            }
        }
        $n = array_sum($rowTotals);

        $chiSquare = 0;
        $expected = [];
        $smallCells = 0;
        foreach ($rowLabels as $r) {
            foreach ($colLabels as $c) {
                $e = $rowTotals[$r] * $colTotals[$c] / $n;
                $expected[$r][$c] = $e;
                $chiSquare += ($observed[$r][$c] - $e) ** 2 / $e;
                if ($e < 5) {
                    $smallCells++;
                }
            }
        }

        $df = (count($rowLabels) - 1) * (count($colLabels) - 1);
        $cells = count($rowLabels) * count($colLabels);
        $cramersV = sqrt($chiSquare / ($n * (min(count($rowLabels), count($colLabels)) - 1)));

        $warnings = [];
        if ($smallCells / $cells > 0.2) {
            $warnings[] = sprintf(
                '%d of %d cells (%.0f%%) have an expected count below 5. Chi-square may be unreliable; consider combining small groups.',
                $smallCells, $cells, $smallCells / $cells * 100
            );
        }

        return [
            'test' => self::TEST_CHI_SQUARE,
            'row_labels' => $rowLabels,
            'col_labels' => $colLabels,
            'observed' => $observed,
            'expected' => $expected,
            'row_totals' => $rowTotals,
            'col_totals' => $colTotals,
            'n' => $n,
            'statistic' => $chiSquare,
            'df' => $df,
            'p' => Distributions::chiSquarePValue($chiSquare, $df),
            'effect_size' => $cramersV,
            'effect_label' => $this->cramersVStrength($cramersV, min(count($rowLabels), count($colLabels)) - 1),
            'warnings' => $warnings,
        ];
    }

    /**
     * Pearson product-moment correlation: are two sets of scores related, and how strongly?
     *   r = Σ(x − x̄)(y − ȳ) / √(Σ(x − x̄)² × Σ(y − ȳ)²)
     *   t = r √((n − 2) / (1 − r²)),   df = n − 2
     *
     * @param  array<int, float|int|null>  $x
     * @param  array<int, float|int|null>  $y
     */
    public function pearson(array $x, array $y): array
    {
        $pairs = [];
        foreach ($x as $i => $xi) {
            if ($xi !== null && ($y[$i] ?? null) !== null) {
                $pairs[] = [$xi, $y[$i]];
            }
        }

        $n = count($pairs);
        if ($n < 3) {
            throw new InvalidArgumentException('At least 3 respondents must answer both variables.');
        }

        $mx = array_sum(array_column($pairs, 0)) / $n;
        $my = array_sum(array_column($pairs, 1)) / $n;
        $sxy = $sxx = $syy = 0;
        foreach ($pairs as [$xi, $yi]) {
            $sxy += ($xi - $mx) * ($yi - $my);
            $sxx += ($xi - $mx) ** 2;
            $syy += ($yi - $my) ** 2;
        }

        if ($sxx == 0 || $syy == 0) {
            throw new InvalidArgumentException('One of the variables has the same value for everyone, so a correlation cannot be computed.');
        }

        $r = $sxy / sqrt($sxx * $syy);
        $df = $n - 2;
        $t = abs($r) >= 1 ? INF : $r * sqrt($df / (1 - $r * $r));

        return [
            'test' => self::TEST_PEARSON,
            'n' => $n,
            'mean_x' => $mx,
            'mean_y' => $my,
            'statistic' => $r,
            't' => $t,
            'df' => $df,
            'p' => is_infinite($t) ? 0.0 : Distributions::tTwoTailedPValue($t, $df),
            'effect_size' => $r * $r, // r² = share of variation explained
            'effect_label' => $this->correlationStrength($r),
            'direction' => $r > 0 ? 'positive' : ($r < 0 ? 'negative' : 'no'),
            'pairs' => $pairs,
            'warnings' => [],
        ];
    }

    /**
     * One-way ANOVA: do the groups (e.g. year levels) differ in their mean score?
     *   SSB = Σ nⱼ(x̄ⱼ − x̄)²            (between groups),  df = k − 1
     *   SSW = Σ Σ (x − x̄ⱼ)²            (within groups),   df = N − k
     *   F = (SSB / dfB) / (SSW / dfW)
     *   η² = SSB / (SSB + SSW)         -> share of the variation explained by the groups
     *
     * @param  array<int, string|null>  $groups
     * @param  array<int, float|int|null>  $scores
     */
    public function anova(array $groups, array $scores): array
    {
        $byGroup = [];
        foreach ($groups as $i => $group) {
            if ($group !== null && ($scores[$i] ?? null) !== null) {
                $byGroup[$group][] = $scores[$i];
            }
        }

        uksort($byGroup, 'strnatcasecmp');
        $k = count($byGroup);
        $n = array_sum(array_map('count', $byGroup));

        if ($k < 2) {
            throw new InvalidArgumentException('At least 2 groups are needed to compare means.');
        }
        if ($n - $k < 1) {
            throw new InvalidArgumentException('There are not enough respondents in the groups to compare them.');
        }

        $grandMean = array_sum(array_map('array_sum', $byGroup)) / $n;
        $ssBetween = $ssWithin = 0;
        $groupStats = [];

        foreach ($byGroup as $label => $values) {
            $count = count($values);
            $mean = array_sum($values) / $count;
            $ss = array_sum(array_map(fn ($x) => ($x - $mean) ** 2, $values));
            $ssBetween += $count * ($mean - $grandMean) ** 2;
            $ssWithin += $ss;
            $groupStats[] = [
                'label' => $label,
                'n' => $count,
                'mean' => $mean,
                'sd' => $count > 1 ? sqrt($ss / ($count - 1)) : null,
            ];
        }

        $dfBetween = $k - 1;
        $dfWithin = $n - $k;
        $msBetween = $ssBetween / $dfBetween;
        $msWithin = $ssWithin / $dfWithin;

        if ($msWithin == 0) {
            throw new InvalidArgumentException('Everyone within each group gave the same score, so ANOVA cannot be computed.');
        }

        $f = $msBetween / $msWithin;
        $etaSquared = $ssBetween / ($ssBetween + $ssWithin);

        $warnings = [];
        $small = array_filter($groupStats, fn ($g) => $g['n'] < 5);
        if ($small) {
            $warnings[] = 'Some groups have fewer than 5 respondents ('.implode(', ', array_column($small, 'label')).'). Their means are less stable; consider combining small groups.';
        }

        return [
            'test' => self::TEST_ANOVA,
            'groups' => $groupStats,
            'n' => $n,
            'grand_mean' => $grandMean,
            'ss_between' => $ssBetween,
            'ss_within' => $ssWithin,
            'df_between' => $dfBetween,
            'df_within' => $dfWithin,
            'ms_between' => $msBetween,
            'ms_within' => $msWithin,
            'statistic' => $f,
            'df' => "{$dfBetween}, {$dfWithin}",
            'p' => Distributions::fPValue($f, $dfBetween, $dfWithin),
            'effect_size' => $etaSquared,
            'effect_label' => $etaSquared >= 0.14 ? 'large' : ($etaSquared >= 0.06 ? 'medium' : ($etaSquared >= 0.01 ? 'small' : 'negligible')),
            'highest_group' => collect($groupStats)->sortByDesc('mean')->first(),
            'lowest_group' => collect($groupStats)->sortBy('mean')->first(),
            'warnings' => $warnings,
        ];
    }

    /**
     * Commonly used verbal scale for |r| (e.g. Evans, 1996).
     */
    public function correlationStrength(float $r): string
    {
        $abs = abs($r);

        return match (true) {
            $abs >= 0.80 => 'very strong',
            $abs >= 0.60 => 'strong',
            $abs >= 0.40 => 'moderate',
            $abs >= 0.20 => 'weak',
            default => 'very weak',
        };
    }

    /**
     * Cohen's guidelines for Cramér's V, which depend on the table size.
     */
    private function cramersVStrength(float $v, int $minDimensionMinusOne): string
    {
        $small = [1 => 0.10, 2 => 0.07, 3 => 0.06][$minDimensionMinusOne] ?? 0.05;

        return match (true) {
            $v >= $small * 5 => 'large',
            $v >= $small * 3 => 'medium',
            $v >= $small => 'small',
            default => 'negligible',
        };
    }

    /**
     * Likert levels in scale order (1, 2, 3...); other answers in natural order (1st Year, 2nd Year...).
     */
    private function sortLabels(array $labels, SurveyColumn $column, ResponseMatrix $matrix): array
    {
        if ($column->type === SurveyColumn::TYPE_LIKERT) {
            $order = $matrix->survey->scale->levels->pluck('label')->all();

            return array_values(array_filter($order, fn ($label) => in_array($label, $labels, true)));
        }

        natcasesort($labels);

        return array_values($labels);
    }
}
