<?php

namespace App\Http\Requests;

use App\Models\LikertScale;
use App\Models\Survey;
use App\Models\SurveyColumn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateColumnMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The form sends columns[<column id>] = likert | profile | ignore.
     */
    public function rules(): array
    {
        return [
            // Only a built-in scale or one of the user's own scales
            'likert_scale_id' => ['required', 'integer', Rule::exists('likert_scales', 'id')->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $this->user()->id))],
            'columns' => ['required', 'array'],
            'columns.*' => ['required', Rule::in(array_keys(SurveyColumn::TYPES))],
        ];
    }

    public function attributes(): array
    {
        return [
            'likert_scale_id' => 'Likert scale',
        ];
    }

    public function messages(): array
    {
        return [
            'columns.*.in' => 'Each column must be a Likert item, a profile question, or ignored.',
        ];
    }

    /**
     * Extra checks that need the database. They only run if the basic rules above passed.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                // Skip these checks if the basic rules already failed (e.g. an unknown scale was sent).
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var Survey $survey */
                $survey = $this->route('survey');
                $types = $this->input('columns', []);
                $columns = $survey->columns->keyBy('id');

                // 1. The form must contain exactly this survey's columns (no missing or fake IDs).
                $sentIds = array_map('intval', array_keys($types));
                $realIds = $columns->keys()->all();
                sort($sentIds);
                sort($realIds);

                if ($sentIds !== $realIds) {
                    $validator->errors()->add('columns', 'The column list is out of date. Please reload the page and try again.');

                    return;
                }

                // 2. At least one Likert item, or there is nothing to compute.
                if (! in_array(SurveyColumn::TYPE_LIKERT, $types, true)) {
                    $validator->errors()->add('columns', 'Choose at least one column as a Likert item.');

                    return;
                }

                // 3. Every Likert column may only contain answers on the chosen scale (a number or a label).
                $scale = LikertScale::with('levels')->findOrFail($this->input('likert_scale_id'));
                $respondents = $survey->respondents()->orderBy('row_number')->get(['row_number', 'answers']);

                foreach ($types as $columnId => $type) {
                    if ($type !== SurveyColumn::TYPE_LIKERT) {
                        continue;
                    }

                    $column = $columns[(int) $columnId];
                    $badRows = [];

                    foreach ($respondents as $respondent) {
                        $answer = $respondent->answerFor($column) ?? '';

                        // Blank answers are allowed: they are skipped in the computation.
                        if ($answer !== '' && $scale->valueFor($answer) === null) {
                            $badRows[] = "row {$respondent->row_number} (\"{$answer}\")";
                        }
                    }

                    if ($badRows) {
                        $examples = implode(', ', array_slice($badRows, 0, 3));
                        $more = count($badRows) > 3 ? ' and '.(count($badRows) - 3).' more' : '';

                        $validator->errors()->add(
                            "columns.{$columnId}",
                            "\"{$column->name}\" cannot be a Likert item on the {$scale->name} scale: answers must be 1 to {$scale->points} or one of its labels. Found {$examples}{$more}."
                        );
                    }
                }
            },
        ];
    }
}
