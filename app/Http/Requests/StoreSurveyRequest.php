<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Rules checked before the controller runs. If one fails, Laravel sends the user
     * back to the form with the error messages and their previous input.
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            // Only a built-in scale or one of the user's own scales
            'likert_scale_id' => ['required', 'integer', Rule::exists('likert_scales', 'id')->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $this->user()->id))],
            // .csv or .xlsx, max 5 MB. The file's contents are checked again when it is read.
            'survey_file' => ['required', 'file', 'extensions:csv,xlsx', 'max:5120'],
        ];
    }

    public function attributes(): array
    {
        return [
            'likert_scale_id' => 'Likert scale',
            'survey_file' => 'file',
        ];
    }

    public function messages(): array
    {
        return [
            'survey_file.required' => 'Please choose a CSV or Excel file to upload.',
            'survey_file.extensions' => 'The file must be a .csv or .xlsx file. Old .xls files: open them in Excel and save as .xlsx.',
            'survey_file.max' => 'The file must not be larger than 5 MB.',
        ];
    }
}
