<?php

namespace Cashkdiopen\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPaymentsRequest extends FormRequest
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
            'status' => [
                'nullable',
                'string',
                Rule::in(['pending', 'completed', 'failed', 'cancelled']),
            ],
            'provider' => [
                'nullable',
                'string',
                Rule::in(['orange-money', 'mtn-momo', 'cards']),
            ],
            'currency' => [
                'nullable',
                'string',
                'size:3',
                Rule::in(['XAF', 'EUR', 'USD']),
            ],
            'from_date' => [
                'nullable',
                'date',
                'before_or_equal:to_date',
            ],
            'to_date' => [
                'nullable',
                'date',
                'after_or_equal:from_date',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Invalid payment status. Allowed values: pending, completed, failed, cancelled.',
            'provider.in' => 'Invalid provider. Allowed values: orange-money, mtn-momo, cards.',
            'currency.in' => 'Invalid currency. Allowed values: XAF, EUR, USD.',
            'from_date.before_or_equal' => 'From date must be before or equal to the to date.',
            'to_date.after_or_equal' => 'To date must be after or equal to the from date.',
            'per_page.max' => 'Maximum 100 results per page allowed.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default per_page if not provided
        if (!$this->has('per_page')) {
            $this->merge(['per_page' => 15]);
        }

        // Normalize currency to uppercase
        if ($this->has('currency')) {
            $this->merge(['currency' => strtoupper($this->currency)]);
        }
    }
}