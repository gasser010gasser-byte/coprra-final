<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SwitchCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currency' => ['required', 'string', 'exists:currencies,code'],
        ];
    }

    /**
     * Custom error messages for validation failures.
     */
    public function messages(): array
    {
        return [
            'currency.required' => __('Please select a currency.'),
            'currency.exists' => __('The selected currency is not available.'),
        ];
    }

    /**
     * Handle a failed validation attempt - log for debugging.
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        \Log::warning('Currency switch validation failed', [
            'errors' => $validator->errors()->toArray(),
            'input' => $this->all(),
            'ip' => $this->ip(),
        ]);

        parent::failedValidation($validator);
    }
}
