<?php

namespace Cashkdiopen\Laravel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->apiKey && $this->apiKey->hasScope('payments:create');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'amount' => [
                'required',
                'integer',
                'min:100', // Minimum 1.00 in cents
                'max:1000000000', // Maximum 10,000,000.00 in cents
            ],
            'currency' => [
                'required',
                'string',
                'size:3',
                Rule::in($this->getSupportedCurrencies()),
            ],
            'method' => [
                'required',
                'string',
                Rule::in($this->getSupportedMethods()),
            ],
            'customer_phone' => [
                'required_if:method,orange_money,mtn_momo',
                'nullable',
                'string',
                'regex:/^\+[1-9]\d{1,14}$/', // E.164 format
                function ($attribute, $value, $fail) {
                    if ($value && !$this->isPhoneValidForProvider($value, $this->input('method'))) {
                        $fail('The phone number is not valid for the selected payment method.');
                    }
                },
            ],
            'description' => [
                'required',
                'string',
                'max:500',
                'min:1',
            ],
            'callback_url' => [
                'required',
                'url',
                'max:500',
                function ($attribute, $value, $fail) {
                    if (!$this->isValidCallbackUrl($value)) {
                        $fail('The callback URL must be accessible via HTTPS in production.');
                    }
                },
            ],
            'return_url' => [
                'required',
                'url',
                'max:500',
            ],
            'metadata' => [
                'sometimes',
                'array',
                'max:10', // Maximum 10 metadata keys
            ],
            'metadata.*' => [
                'string',
                'max:255',
            ],
            'expires_in' => [
                'sometimes',
                'integer',
                'min:300', // Minimum 5 minutes
                'max:3600', // Maximum 1 hour
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'amount.min' => 'The minimum amount is 1.00 :currency.',
            'amount.max' => 'The maximum amount is 10,000,000.00 :currency.',
            'currency.in' => 'The selected currency is not supported.',
            'method.in' => 'The selected payment method is not supported.',
            'customer_phone.required_if' => 'Phone number is required for mobile money payments.',
            'customer_phone.regex' => 'Phone number must be in international format (e.g., +22607123456).',
            'callback_url.url' => 'Callback URL must be a valid URL.',
            'return_url.url' => 'Return URL must be a valid URL.',
            'description.max' => 'Description cannot exceed 500 characters.',
            'description.min' => 'Description is required.',
            'metadata.max' => 'Maximum 10 metadata entries allowed.',
            'metadata.*.max' => 'Metadata values cannot exceed 255 characters.',
            'expires_in.min' => 'Payment must be valid for at least 5 minutes.',
            'expires_in.max' => 'Payment cannot be valid for more than 1 hour.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'customer_phone' => 'phone number',
            'callback_url' => 'callback URL',
            'return_url' => 'return URL',
            'expires_in' => 'expiration time',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate amount limits per provider and currency
            $this->validateAmountLimits($validator);
            
            // Validate phone number format for specific providers
            $this->validatePhoneForProvider($validator);
            
            // Validate currency support for provider
            $this->validateCurrencyForProvider($validator);
        });
    }

    /**
     * Get supported currencies.
     */
    protected function getSupportedCurrencies(): array
    {
        return ['XOF', 'XAF', 'USD', 'EUR', 'GHS', 'UGX', 'ZMW'];
    }

    /**
     * Get supported payment methods.
     */
    protected function getSupportedMethods(): array
    {
        return ['orange_money', 'mtn_momo', 'cards'];
    }

    /**
     * Validate amount limits per provider.
     */
    protected function validateAmountLimits($validator): void
    {
        $method = $this->input('method');
        $amount = $this->input('amount');
        $currency = $this->input('currency');

        if (!$method || !$amount || !$currency) {
            return;
        }

        $limits = $this->getProviderAmountLimits($method, $currency);
        
        if ($amount < $limits['min']) {
            $validator->errors()->add(
                'amount',
                "Minimum amount for {$method} is " . ($limits['min'] / 100) . " {$currency}."
            );
        }

        if ($amount > $limits['max']) {
            $validator->errors()->add(
                'amount',
                "Maximum amount for {$method} is " . ($limits['max'] / 100) . " {$currency}."
            );
        }
    }

    /**
     * Validate phone number for specific provider.
     */
    protected function validatePhoneForProvider($validator): void
    {
        $method = $this->input('method');
        $phone = $this->input('customer_phone');

        if (!in_array($method, ['orange_money', 'mtn_momo']) || !$phone) {
            return;
        }

        if (!$this->isPhoneValidForProvider($phone, $method)) {
            $validator->errors()->add(
                'customer_phone',
                "Phone number format is not valid for {$method}."
            );
        }
    }

    /**
     * Validate currency support for provider.
     */
    protected function validateCurrencyForProvider($validator): void
    {
        $method = $this->input('method');
        $currency = $this->input('currency');

        if (!$method || !$currency) {
            return;
        }

        $supportedCurrencies = $this->getProviderSupportedCurrencies($method);
        
        if (!in_array($currency, $supportedCurrencies)) {
            $validator->errors()->add(
                'currency',
                "Currency {$currency} is not supported by {$method}."
            );
        }
    }

    /**
     * Get amount limits for provider and currency.
     */
    protected function getProviderAmountLimits(string $method, string $currency): array
    {
        $config = config("cashkdiopen.providers.{$method}", []);
        
        return [
            'min' => $config['min_amount'] ?? 100,
            'max' => $config['max_amount'] ?? 500000000,
        ];
    }

    /**
     * Get supported currencies for provider.
     */
    protected function getProviderSupportedCurrencies(string $method): array
    {
        return config("cashkdiopen.providers.{$method}.currencies", ['XOF']);
    }

    /**
     * Check if phone number is valid for provider.
     */
    protected function isPhoneValidForProvider(string $phone, string $method): bool
    {
        // Remove the + prefix for validation
        $phoneNumber = ltrim($phone, '+');

        return match($method) {
            'orange_money' => $this->isValidOrangeMoneyPhone($phoneNumber),
            'mtn_momo' => $this->isValidMtnMoMoPhone($phoneNumber),
            default => true,
        };
    }

    /**
     * Validate Orange Money phone number.
     */
    protected function isValidOrangeMoneyPhone(string $phone): bool
    {
        // Orange Money countries and prefixes
        $validPrefixes = [
            '225', // Côte d'Ivoire
            '221', // Sénégal
            '223', // Mali
            '226', // Burkina Faso
            '227', // Niger
            '224', // Guinée
            '237', // Cameroun
        ];

        foreach ($validPrefixes as $prefix) {
            if (str_starts_with($phone, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate MTN MoMo phone number.
     */
    protected function isValidMtnMoMoPhone(string $phone): bool
    {
        // MTN Mobile Money countries and prefixes
        $validPrefixes = [
            '225', // Côte d'Ivoire
            '237', // Cameroun
            '233', // Ghana
            '256', // Uganda
            '260', // Zambia
        ];

        foreach ($validPrefixes as $prefix) {
            if (str_starts_with($phone, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate callback URL.
     */
    protected function isValidCallbackUrl(string $url): bool
    {
        // In production, require HTTPS
        if (app()->environment('production')) {
            return str_starts_with($url, 'https://');
        }

        // In non-production, allow HTTP
        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
    }

    /**
     * Get validated data with processed values.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        if ($key !== null) {
            return $validated;
        }

        // Convert expires_in to expires_at timestamp
        if (isset($validated['expires_in'])) {
            $validated['expires_at'] = now()->addSeconds($validated['expires_in']);
            unset($validated['expires_in']);
        }

        // Add API key information
        $validated['api_key_id'] = $this->apiKey->id;

        // Set provider based on method
        $validated['provider'] = $validated['method'];

        return $validated;
    }
}