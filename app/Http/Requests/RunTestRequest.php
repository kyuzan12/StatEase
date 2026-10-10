<?php

namespace App\Http\Requests;

use App\Models\Survey;
use App\Models\SurveyColumn;
use App\Services\InferentialService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Checks the test chosen in the advisor and its two variables.
 * Variables are sent as "col:<column id>" or "overall" (each respondent's average of all Likert items).
 */
class RunTestRequest extends FormRequest
{
    /**
     * Which kinds of variables each test accepts, for the first (a) and second (b) variable.
     */
    public const ALLOWED = [
        InferentialService::TEST_CHI_SQUARE => ['a' => ['profile'], 'b' => ['profile', 'likert']],
        InferentialService::TEST_ANOVA => ['a' => ['profile'], 'b' => ['likert', 'overall']],
        InferentialService::TEST_PEARSON => ['a' => ['likert', 'overall'], 'b' => ['likert', 'overall']],
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'test' => ['required', Rule::in(array_keys(self::ALLOWED))],
            'a' => ['required', 'string'],
            'b' => ['required', 'string', 'different:a'],
            'alpha' => ['required', Rule::in(['0.01', '0.05', '0.10'])],
        ];
    }

    public function messages(): array
    {
        return [
            'a.required' => 'Please choose the first variable.',
            'b.required' => 'Please choose the second variable.',
            'b.different' => 'Please choose two different variables.',
            'alpha.in' => 'The level of significance must be 0.01, 0.05 or 0.10.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                // Skip these checks if the basic rules already failed (e.g. no test or variable chosen).
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $allowed = self::ALLOWED[$this->input('test')];

                foreach (['a', 'b'] as $field) {
                    $variable = $this->variable($field);

                    if ($variable === null) {
                        $validator->errors()->add($field, 'This variable does not belong to the survey.');
                    } elseif (! in_array($variable['kind'], $allowed[$field], true)) {
                        $validator->errors()->add($field, 'This kind of variable cannot be used for the chosen test.');
                    }
                }
            },
        ];
    }

    /**
     * When validation fails, go back to the advisor page (not "back", which could be this same URL).
     */
    protected function getRedirectUrl(): string
    {
        return route('surveys.tests.index', $this->route('survey'));
    }

    /**
     * Turn "col:12" or "overall" into a description of the variable, or null if it is not valid.
     *
     * @return array{key: string, kind: string, label: string, column: SurveyColumn|null}|null
     */
    public function variable(string $field): ?array
    {
        /** @var Survey $survey */
        $survey = $this->route('survey');
        $key = (string) $this->input($field);

        if ($key === InferentialService::OVERALL) {
            return ['key' => $key, 'kind' => 'overall', 'label' => 'the overall assessment', 'column' => null];
        }

        if (preg_match('/^col:(\d+)$/', $key, $match)) {
            $column = $survey->columns->firstWhere('id', (int) $match[1]);

            if ($column && $column->type !== SurveyColumn::TYPE_IGNORE) {
                // Labels are written to read well inside a sentence.
                $label = $column->type === SurveyColumn::TYPE_LIKERT
                    ? 'the rating on "'.rtrim($column->name, '.').'"'
                    : $column->name;

                return ['key' => $key, 'kind' => $column->type, 'label' => $label, 'column' => $column];
            }
        }

        return null;
    }
}
