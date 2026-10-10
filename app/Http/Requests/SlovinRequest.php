<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SlovinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'population' => ['required', 'integer', 'min:1', 'max:100000000'],
            'margin_of_error' => ['required', 'numeric', 'min:0.1', 'max:50'], // in percent
        ];
    }

    public function attributes(): array
    {
        return [
            'population' => 'population size (N)',
            'margin_of_error' => 'margin of error (e)',
        ];
    }

    public function messages(): array
    {
        return [
            'population.integer' => 'The population size must be a whole number of people.',
            'margin_of_error.min' => 'The margin of error must be at least 0.1%.',
            'margin_of_error.max' => 'The margin of error must not be more than 50%.',
        ];
    }
}
