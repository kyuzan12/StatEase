<?php

namespace App\Http\Requests;

use App\Models\LikertScale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Used for both creating and editing a Likert scale.
 * The form sends levels[1][label], levels[1][interpretation], levels[2][label], ...
 */
class LikertScaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var LikertScale|null $scale  null when creating */
        $scale = $this->route('scale');
        $points = (int) $this->input('points');

        return [
            // Unique among the scales this user can see (the built-in ones and their own)
            'name' => ['required', 'string', 'max:100', Rule::unique('likert_scales', 'name')
                ->where(fn ($query) => $query->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $this->user()->id)))
                ->ignore($scale?->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'points' => ['required', 'integer', 'between:'.LikertScale::MIN_POINTS.','.LikertScale::MAX_POINTS],
            'levels' => ['required', 'array', 'size:'.$points],
            'levels.*.label' => ['required', 'string', 'max:100', 'distinct:ignore_case', 'not_regex:/^\s*\d+\s*$/'],
            'levels.*.interpretation' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'points' => 'number of points',
            'levels.*.label' => 'label',
            'levels.*.interpretation' => 'interpretation',
        ];
    }

    public function messages(): array
    {
        return [
            'levels.size' => 'Please give a label for every point of the scale.',
            'levels.*.label.required' => 'Every point needs a label.',
            'levels.*.label.distinct' => 'Each label must be different.',
            'levels.*.label.not_regex' => 'A label cannot be only a number, because numbers are already read as scale values.',
        ];
    }

    /**
     * A scale already used by surveys cannot change its number of points:
     * those surveys were checked against the old number.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $scale = $this->route('scale');

                if ($scale && $scale->points !== (int) $this->input('points') && $scale->surveys()->exists()) {
                    $validator->errors()->add(
                        'points',
                        "This scale is used by {$scale->surveys()->count()} survey(s), so its number of points cannot change. Create a new scale instead."
                    );
                }
            },
        ];
    }

    /**
     * The levels as a clean list: [['value' => 1, 'label' => ..., 'interpretation' => ...], ...]
     */
    public function levels(): array
    {
        $levels = [];

        foreach (range(1, (int) $this->validated('points')) as $value) {
            $level = $this->validated('levels')[$value] ?? [];
            $levels[] = [
                'value' => $value,
                'label' => trim($level['label'] ?? ''),
                'interpretation' => trim($level['interpretation'] ?? '') ?: null,
            ];
        }

        return $levels;
    }
}
