<?php

namespace App\Services;

/**
 * Writes thesis-style interpretation paragraphs from computed results.
 * The sentences are templates filled with the numbers, so they are always consistent with the tables.
 * Users should still review and edit them before putting them in their paper.
 */
class NarrativeService
{
    /**
     * Profile table, e.g. "Table 1 presents ... the majority, 18 or 60.00%, fall under Male, while ..."
     */
    public function profile(int $tableNumber, array $profile): string
    {
        $name = $profile['column']->name;
        $rows = collect($profile['rows'])->sortByDesc('frequency')->values();
        $answered = $profile['answered'];
        $top = $rows->first();

        $text = "Table {$tableNumber} presents the distribution of the respondents according to {$name}. ";
        // "the majority ... fall under" (plural) vs. "the largest group ... falls under" (singular)
        $share = $top['percentage'] > 50 ? 'the majority' : 'the largest group';
        $verb = $top['percentage'] > 50 ? 'fall' : 'falls';
        $text .= "Of the {$answered} respondents, {$share}, {$this->count($top)}, {$verb} under {$this->quote($top['label'])}";

        $others = $rows->slice(1);
        if ($others->count() === 1) {
            $only = $others->first();
            $text .= ", while {$this->count($only)} fall under {$this->quote($only['label'])}.";
        } elseif ($others->count() > 1) {
            $listed = $others->take(4);
            $parts = $listed->map(fn ($row) => "{$this->quote($row['label'])} with {$this->count($row)}")->all();
            $text .= ', followed by '.$this->join($parts).'.';

            if ($others->count() > 4) {
                $rest = $others->count() - 4;
                $text .= " The remaining {$this->words($rest)} groups each account for {$this->pct($others->last()['percentage'])} or less.";
            } else {
                $last = $others->last();
                $text .= " {$this->quote($last['label'])} has the fewest respondents.";
            }
        } else {
            $text .= '.';
        }

        if ($profile['blank'] > 0) {
            $text .= " {$this->wordsAndDigits($profile['blank'])} respondent(s) did not answer this question.";
        }

        return $text;
    }

    /**
     * Weighted mean table: overall result, highest and lowest items, and how the items are spread.
     */
    public function likert(int $tableNumber, array $likert): string
    {
        $items = collect($likert['items'])->filter(fn ($item) => $item['mean'] !== null)->values();
        $k = $items->count();

        if ($k === 0) {
            return "Table {$tableNumber} has no answered items to interpret.";
        }

        $text = "Table {$tableNumber} presents the weighted mean and verbal interpretation of the {$this->words($k)} ({$k}) items. ";
        $text .= "The overall weighted mean is {$this->num($likert['overall_mean'])}, which is interpreted as {$this->quote($likert['overall_level']['interpretation'])}. ";

        if ($k === 1) {
            return trim($text);
        }

        // Highest and lowest (items tied for first or last are mentioned together)
        $highest = $items->where('mean', $items->max('mean'));
        $lowest = $items->where('mean', $items->min('mean'));

        $text .= 'Among the items, '.$this->itemNames($highest).' obtained the highest weighted mean of '
            ."{$this->num($highest->first()['mean'])} ({$this->quote($highest->first()['level']['interpretation'])}), while "
            .$this->itemNames($lowest).' obtained the lowest weighted mean of '
            ."{$this->num($lowest->first()['mean'])} ({$this->quote($lowest->first()['level']['interpretation'])}). ";

        // How many items fall under each interpretation, from the most favorable down.
        // Grouped by the interpretation words, because several levels may share one interpretation
        // (e.g. on a 10-point scale, 7 and 8 can both be "Satisfied").
        $byLevel = $items->groupBy(fn ($item) => $item['level']['interpretation'])
            ->sortByDesc(fn ($group) => $group->max(fn ($item) => $item['level']['value']));
        if ($byLevel->count() === 1) {
            $text .= "All {$this->words($k)} ({$k}) items are interpreted as {$this->quote($items->first()['level']['interpretation'])}. ";
        } else {
            // "eleven (11) are interpreted as "Agree" and four (4) as "Neutral""
            $parts = $byLevel->values()->map(function ($group, $i) {
                $verb = $i === 0 ? 'are interpreted as' : 'as';

                return "{$this->words($group->count())} ({$group->count()}) {$verb} {$this->quote($group->first()['level']['interpretation'])}";
            })->all();
            $text .= "Of the {$k} items, ".$this->join($parts).'. ';
        }

        // What it implies: items whose interpretation is lower than the overall interpretation.
        // (Same words = same interpretation, even if the level value differs.)
        $overall = $likert['overall_level'];
        $below = $items->filter(fn ($item) => $item['level']['value'] < $overall['value']
            && $item['level']['interpretation'] !== $overall['interpretation']);
        if ($below->isNotEmpty()) {
            $text .= $below->count() === 1
                ? 'This suggests that '.$this->itemNames($below).' is the area that needs the most attention, as it is the only item rated below the overall assessment.'
                : "This suggests that the {$this->words($below->count())} ({$below->count()}) items rated below the overall assessment, especially ".$this->itemNames($lowest).', are the areas that need the most attention.';
        } else {
            $text .= 'The ratings are consistent across the items, since even the lowest-rated item falls under the same or a higher interpretation than the overall assessment.';
        }

        return trim($text);
    }

    /**
     * Cronbach's alpha result.
     */
    public function reliability(array $result): string
    {
        $text = "The Cronbach's alpha of the {$this->words($result['k'])} ({$result['k']}) items is "
            .number_format($result['alpha'], 3).", which indicates {$this->quote($result['interpretation'])} internal consistency "
            ."(George & Mallery, 2003), based on {$result['n']} respondents who answered all of the items. ";

        $text .= $result['alpha'] >= 0.7
            ? 'Therefore, the instrument is considered reliable. '
            : 'Therefore, the instrument should be revised before it is used. ';

        $flagged = collect($result['items'])->filter(fn ($item) => $item['flag'] !== null);
        $raising = $flagged->filter(fn ($item) => $item['alpha_if_deleted'] !== null && $item['alpha_if_deleted'] > $result['alpha'] + 0.001)
            ->sortByDesc('alpha_if_deleted');

        if ($raising->isNotEmpty()) {
            $best = $raising->first();
            $text .= "Removing {$this->quote($best['column']->name)} would increase the alpha to ".number_format($best['alpha_if_deleted'], 3).', so this item may be revised or removed.';
        } elseif ($flagged->isNotEmpty()) {
            $text .= $this->words($flagged->count(), true).' item(s) have a weak correlation with the rest of the scale and may be reviewed.';
        } else {
            $text .= 'All items contribute positively to the reliability of the scale.';
        }

        return trim($text);
    }

    /**
     * Null and alternative hypotheses of a test.
     *
     * @return array{null: string, alternative: string}
     */
    public function hypotheses(string $test, string $a, string $b): array
    {
        return match ($test) {
            InferentialService::TEST_CHI_SQUARE => [
                'null' => "There is no significant relationship between {$a} and {$b}.",
                'alternative' => "There is a significant relationship between {$a} and {$b}.",
            ],
            InferentialService::TEST_PEARSON => [
                'null' => "There is no significant correlation between {$a} and {$b}.",
                'alternative' => "There is a significant correlation between {$a} and {$b}.",
            ],
            InferentialService::TEST_ANOVA => [
                'null' => "There is no significant difference in {$b} when the respondents are grouped according to {$a}.",
                'alternative' => "There is a significant difference in {$b} when the respondents are grouped according to {$a}.",
            ],
        };
    }

    /**
     * The conclusion paragraph of a statistical test.
     */
    public function testConclusion(array $result, string $a, string $b, float $alpha): string
    {
        $significant = $result['p'] < $alpha;
        $decision = $significant ? 'Hence, the null hypothesis is rejected.' : 'Hence, the null hypothesis is not rejected.';
        $level = rtrim(rtrim(number_format($alpha, 2), '0'), '.');

        switch ($result['test']) {
            case InferentialService::TEST_CHI_SQUARE:
                $text = "A chi-square test of independence was conducted to determine whether {$a} is related to {$b}. "
                    ."The result, χ²({$result['df']}, N = {$result['n']}) = ".number_format($result['statistic'], 2).", {$this->p($result['p'])}, shows that there is "
                    .($significant ? 'a significant' : 'no significant')." relationship between {$a} and {$b} at the {$level} level of significance. {$decision}";
                if ($significant) {
                    $text .= " The strength of the relationship is {$result['effect_label']} (Cramér's V = ".number_format($result['effect_size'], 2).').';
                }

                return $text;

            case InferentialService::TEST_PEARSON:
                $text = "Pearson's r was computed to determine the relationship between {$a} and {$b}. "
                    .'The result, r('.$result['df'].') = '.$this->apa($result['statistic']).", {$this->p($result['p'])}, indicates a {$result['effect_label']} {$result['direction']} correlation that is "
                    .($significant ? 'statistically significant' : 'not statistically significant')." at the {$level} level. {$decision}";
                if ($significant && $result['direction'] !== 'no') {
                    $text .= $result['direction'] === 'positive'
                        ? " This means that the higher {$a}, the higher {$b} tends to be."
                        : " This means that the higher {$a}, the lower {$b} tends to be.";
                }

                return $text;

            case InferentialService::TEST_ANOVA:
                $high = $result['highest_group'];
                $low = $result['lowest_group'];
                $text = "A one-way analysis of variance (ANOVA) was conducted to determine whether there is a significant difference in {$b} "
                    ."when the respondents are grouped according to {$a}. The result, F({$result['df_between']}, {$result['df_within']}) = "
                    .number_format($result['statistic'], 2).", {$this->p($result['p'])}, shows that there is "
                    .($significant ? 'a significant' : 'no significant')." difference at the {$level} level of significance. {$decision} "
                    ."The {$this->quote($high['label'])} group obtained the highest mean ({$this->num($high['mean'])}), while the "
                    ."{$this->quote($low['label'])} group obtained the lowest ({$this->num($low['mean'])}).";
                if ($significant) {
                    $text .= ' A post hoc test (e.g. Tukey HSD) is recommended to find out which specific groups differ.';
                }

                return $text;
        }

        return '';
    }

    // ----------------------------------------------------------------- formatting helpers

    /**
     * "p < .001" or "p = .034" (APA style: no leading zero).
     */
    public function p(float $p): string
    {
        return $p < 0.001 ? 'p < .001' : 'p = '.$this->apa($p, 3);
    }

    /**
     * 0.4567 -> ".46", -0.3 -> "-.30" (APA style for values that cannot exceed 1).
     */
    public function apa(float $value, int $decimals = 2): string
    {
        return preg_replace('/^(-?)0\./', '$1.', number_format($value, $decimals));
    }

    /**
     * 0 to 999 in words, e.g. 12 -> "twelve". Capitalized if $capitalize is true.
     */
    public function words(int $n, bool $capitalize = false): string
    {
        $ones = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve',
            'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
        $tens = [2 => 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

        $word = match (true) {
            $n < 20 => $ones[$n],
            $n < 100 => $tens[intdiv($n, 10)].($n % 10 ? '-'.$ones[$n % 10] : ''),
            $n < 1000 => $ones[intdiv($n, 100)].' hundred'.($n % 100 ? ' '.$this->words($n % 100) : ''),
            default => (string) $n,
        };

        return $capitalize ? ucfirst($word) : $word;
    }

    private function wordsAndDigits(int $n): string
    {
        return $this->words($n, true)." ({$n})";
    }

    private function count(array $row): string
    {
        return "{$row['frequency']} or {$this->pct($row['percentage'])}";
    }

    private function pct(float $percentage): string
    {
        return number_format($percentage, 2).'%';
    }

    private function num(float $value): string
    {
        return number_format($value, 2);
    }

    private function quote(string $text): string
    {
        return '"'.$text.'"';
    }

    private function itemNames($items): string
    {
        $names = $items->map(fn ($item) => $this->quote(rtrim($item['column']->name, '.')))->values()->all();

        return count($names) === 1 ? 'the item '.$names[0] : 'the items '.$this->join($names);
    }

    /**
     * ["a", "b", "c"] -> "a, b, and c"
     */
    private function join(array $parts): string
    {
        if (count($parts) <= 2) {
            return implode(' and ', $parts);
        }

        $last = array_pop($parts);

        return implode(', ', $parts).', and '.$last;
    }
}
