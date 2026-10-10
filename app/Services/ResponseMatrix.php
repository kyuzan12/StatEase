<?php

namespace App\Services;

use App\Models\Survey;
use App\Models\SurveyColumn;

/**
 * A survey's answers loaded once and converted for analysis:
 *   - Likert items -> numbers (1 to the number of points), using the survey's scale
 *   - profile questions -> group labels ("Female", "2nd Year", ...)
 * Every array is indexed by respondent (0, 1, 2, ...), with null for a blank or invalid answer.
 * Used by the reliability and statistical test features.
 */
class ResponseMatrix
{
    /** @var array<int, array<int, string>> */
    private array $answers;

    private function __construct(public readonly Survey $survey)
    {
        $survey->loadMissing(['scale.levels', 'columns']);
        $this->answers = $survey->respondents()->orderBy('row_number')->pluck('answers')->all();
    }

    public static function for(Survey $survey): self
    {
        return new self($survey);
    }

    public function respondentCount(): int
    {
        return count($this->answers);
    }

    /**
     * Numeric answers to one Likert item.
     *
     * @return array<int, int|null>
     */
    public function scores(SurveyColumn $column): array
    {
        return array_map(function ($answers) use ($column) {
            $answer = $answers[$column->position] ?? '';

            return $answer === '' ? null : $this->survey->scale->valueFor($answer);
        }, $this->answers);
    }

    /**
     * Each respondent's overall score = the average of the Likert items they answered.
     *
     * @return array<int, float|null>
     */
    public function overallScores(): array
    {
        $columns = $this->survey->columns->where('type', SurveyColumn::TYPE_LIKERT);
        $perItem = $columns->map(fn ($column) => $this->scores($column))->values()->all();
        $overall = [];

        foreach (array_keys($this->answers) as $i) {
            $values = array_filter(array_column($perItem, $i), fn ($value) => $value !== null);
            $overall[$i] = count($values) > 0 ? array_sum($values) / count($values) : null;
        }

        return $overall;
    }

    /**
     * Answers as group labels. Letter case and extra spaces are ignored, so "female" and "Female " are one group.
     * For a Likert item the group is the level label (e.g. "Agree").
     *
     * @return array<int, string|null>
     */
    public function categories(SurveyColumn $column): array
    {
        if ($column->type === SurveyColumn::TYPE_LIKERT) {
            $labels = $this->survey->scale->levels->pluck('label', 'value');

            return array_map(fn ($value) => $value === null ? null : $labels[$value], $this->scores($column));
        }

        $display = []; // normalized key -> the first spelling we saw

        return array_map(function ($answers) use ($column, &$display) {
            $answer = trim($answers[$column->position] ?? '');
            if ($answer === '') {
                return null;
            }

            $key = mb_strtolower(preg_replace('/\s+/', ' ', $answer));

            return $display[$key] ??= $answer;
        }, $this->answers);
    }
}
