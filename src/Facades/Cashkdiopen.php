<?php

namespace Cashkdiopen\Payments\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Cashkdiopen\Payments\Models\Payment createPayment(array $data)
 * @method static \Illuminate\Pagination\LengthAwarePaginator listPayments(array $filters = [])
 * @method static array cancelPayment(\Cashkdiopen\Payments\Models\Payment $payment)
 * @method static array getAvailableProviders()
 * @method static array getProviderInfo(string $providerName)
 * @method static bool validatePhoneNumber(string $phone, string $providerName)
 * @method static array getSupportedCurrencies(string $providerName = null)
 * 
 * @see \Cashkdiopen\Payments\Services\PaymentService
 */
class Cashkdiopen extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'cashkdiopen';
    }
}