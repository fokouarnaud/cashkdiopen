<?php

namespace Cashkdiopen\Laravel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPaymentsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->apiKey && $this->apiKey->hasScope('payments:read');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'status' => [
                'sometimes',
                'string',
                Rule::in(['pending', 'processing', 'success', 'failed', 'canceled', 'expired']),
            ],
            'method' => [
                'sometimes',
                'string',
                Rule::in(['orange_money', 'mtn_momo', 'cards']),
            ],
            'currency' => [
                'sometimes',
                'string',
                'size:3',
                Rule::in(['XOF', 'XAF', 'USD', 'EUR', 'GHS', 'UGX', 'ZMW']),
            ],
            'amount_min' => [
                'sometimes',
                'integer',
                'min:100',
            ],
            'amount_max' => [
                'sometimes',
                'integer',
                'min:100',
                'gte:amount_min',
            ],
            'date_from' => [
                'sometimes',
                'date',
                'before_or_equal:date_to',
            ],
            'date_to' => [
                'sometimes',
                'date',
                'after_or_equal:date_from',
                'before_or_equal:now',
            ],
            'page' => [
                'sometimes',
                'integer',
                'min:1',
            ],
            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],
            'sort' => [
                'sometimes',
                'string',
                Rule::in(['created_at', 'amount', 'status', 'completed_at']),
            ],
            'direction' => [
                'sometimes',
                'string',
                Rule::in(['asc', 'desc']),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Invalid status. Must be one of: pending, processing, success, failed, canceled, expired.',
            'method.in' => 'Invalid payment method. Must be one of: orange_money, mtn_momo, cards.',
            'currency.in' => 'Invalid currency code.',
            'amount_min.min' => 'Minimum amount must be at least 1.00.',
            'amount_max.min' => 'Maximum amount must be at least 1.00.',
            'amount_max.gte' => 'Maximum amount must be greater than or equal to minimum amount.',
            'date_from.before_or_equal' => 'Start date must be before or equal to end date.',
            'date_to.after_or_equal' => 'End date must be after or equal to start date.',
            'date_to.before_or_equal' => 'End date cannot be in the future.',
            'per_page.max' => 'Maximum 100 items per page allowed.',
            'sort.in' => 'Invalid sort field. Must be one of: created_at, amount, status, completed_at.',
            'direction.in' => 'Sort direction must be asc or desc.',
        ];
    }

    /**
     * Get default values for optional parameters.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        if ($key !== null) {
            return $validated;
        }

        // Set default values
        $validated['page'] = $validated['page'] ?? 1;
        $validated['per_page'] = $validated['per_page'] ?? 20;
        $validated['sort'] = $validated['sort'] ?? 'created_at';
        $validated['direction'] = $validated['direction'] ?? 'desc';

        // Convert amount filters from major currency units to cents
        if (isset($validated['amount_min'])) {
            $validated['amount_min'] *= 100;
        }
        if (isset($validated['amount_max'])) {
            $validated['amount_max'] *= 100;
        }

        return $validated;
    }
}