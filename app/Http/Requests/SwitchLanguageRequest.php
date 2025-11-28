<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SwitchLanguageRequest extends FormRequest
{
    /**
     * Authorize all users to switch language.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for switching language.
     */
    public function rules(): array
    {
        return [
            'language' => ['required_without:locale', 'string', 'exists:languages,code'],
            'locale' => ['nullable', 'string', 'exists:languages,code'],
        ];
    }

    /**
     * Custom error messages for validation failures.
     */
    public function messages(): array
    {
        return [
            'language.required_without' => __('Please select a language.'),
            'language.exists' => __('The selected language is not available.'),
            'locale.exists' => __('The selected language is not available.'),
        ];
    }

    /**
     * Handle a failed validation attempt - log for debugging.
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        \Log::warning('Language switch validation failed', [
            'errors' => $validator->errors()->toArray(),
            'input' => $this->all(),
            'ip' => $this->ip(),
        ]);

        parent::failedValidation($validator);
    }
}
