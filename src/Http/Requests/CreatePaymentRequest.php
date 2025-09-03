<?php

namespace Cashkdiopen\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'provider' => [
                'required',
                'string',
                Rule::in(['orange-money', 'mtn-momo', 'cards']),
            ],
            'amount' => [
                'required',
                'numeric',
                'min:100', // Minimum 100 XAF
                'max:10000000', // Maximum 10M XAF
            ],
            'currency' => [
                'required',
                'string',
                'size:3',
                Rule::in(['XAF', 'EUR', 'USD']),
            ],
            'phone' => [
                'required_if:provider,orange-money,mtn-momo',
                'nullable',
                'string',
                'regex:/^\+237[67]\d{8}$/',
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
                'max:255',
            ],
            'callback_url' => [
                'nullable',
                'url',
                'max:255',
            ],
            'return_url' => [
                'nullable',
                'url',
                'max:255',
            ],
            'metadata' => [
                'nullable',
                'array',
            ],
            'metadata.*' => [
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'provider.in' => 'The selected provider is invalid. Supported providers are: orange-money, mtn-momo, cards.',
            'amount.min' => 'The minimum payment amount is 100 XAF.',
            'amount.max' => 'The maximum payment amount is 10,000,000 XAF.',
            'currency.in' => 'The selected currency is invalid. Supported currencies are: XAF, EUR, USD.',
            'phone.required_if' => 'Phone number is required for mobile money payments.',
            'phone.regex' => 'Phone number must be a valid Cameroon mobile number (+237XXXXXXXXX).',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Normalize phone number
        if ($this->has('phone') && $this->phone) {
            $phone = preg_replace('/[^0-9+]/', '', $this->phone);
            
            // Add country code if missing
            if (!str_starts_with($phone, '+237') && str_starts_with($phone, '6')) {
                $phone = '+237' . $phone;
            }
            
            $this->merge(['phone' => $phone]);
        }

        // Normalize currency to uppercase
        if ($this->has('currency')) {
            $this->merge(['currency' => strtoupper($this->currency)]);
        }

        // Convert amount to float
        if ($this->has('amount')) {
            $this->merge(['amount' => floatval($this->amount)]);
        }
    }
}