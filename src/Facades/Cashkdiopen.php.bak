<?php

namespace Cashkdiopen\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Cashkdiopen\Laravel\Models\Transaction createPayment(array $data)
 * @method static \Cashkdiopen\Laravel\Models\Transaction getPayment(string $id)
 * @method static \Cashkdiopen\Laravel\DataObjects\StatusResponse getPaymentStatus(string $id)
 * @method static \Illuminate\Contracts\Pagination\LengthAwarePaginator listPayments(array $filters = [])
 * @method static bool cancelPayment(string $id)
 * @method static array getProviders()
 * @method static array getProviderInfo(string $provider)
 * @method static array getSupportedCurrencies(string $provider = null)
 * @method static bool validatePhoneNumber(string $phone, string $provider)
 * @method static array getHealthStatus()
 *
 * @see \Cashkdiopen\Laravel\Services\PaymentService
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