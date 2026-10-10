<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Emails are compared in lowercase, so "Ana@Example.com" and "ana@example.com" are one account.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            // 8 to 72 characters with letters and numbers; "confirmed" = must match password_confirmation.
            // 72 is the most that bcrypt uses: longer passwords would have their extra characters ignored.
            'password' => ['required', 'confirmed', 'max:72', Password::min(8)->letters()->numbers()],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'An account with this email already exists. Please log in instead.',
            'password.confirmed' => 'The two passwords do not match.',
            'password.max' => 'The password must not be longer than 72 characters.',
        ];
    }
}
