# Cashkdiopen Payments Package

[![Latest Version](https://img.shields.io/packagist/v/cashkdiopen/payments.svg?style=flat-square)](https://packagist.org/packages/cashkdiopen/payments)
[![Total Downloads](https://img.shields.io/packagist/dt/cashkdiopen/payments.svg?style=flat-square)](https://packagist.org/packages/cashkdiopen/payments)
[![Tests](https://img.shields.io/github/actions/workflow/status/cashkdiopen/payments/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/cashkdiopen/payments/actions)
[![License](https://img.shields.io/packagist/l/cashkdiopen/payments.svg?style=flat-square)](LICENSE.md)

A modern Laravel package for unified payment integration with African mobile money providers and bank cards. Supports Orange Money, MTN Mobile Money, and major card networks with a single, elegant API.

## 🚀 Features

- **Unified API** for multiple payment providers
- **Laravel Native** with ServiceProvider, Artisan commands, and migrations
- **Secure Webhooks** with HMAC signature validation
- **Comprehensive Testing** with PHPUnit and Pest
- **Type Safety** with full PHP 8.2+ support
- **Production Ready** with monitoring and error handling

## 📋 Supported Providers

| Provider | Status | Methods |
|----------|--------|---------|
| **Orange Money** | ✅ | Mobile Money |
| **MTN Mobile Money** | ✅ | Mobile Money |
| **Bank Cards** | ✅ | Visa, Mastercard |
| **Moov Money** | 🔄 | Coming Soon |

## 📦 Installation

Install the package via Composer:

```bash
composer require cashkdiopen/payments
```

Publish the configuration and run migrations:

```bash
php artisan vendor:publish --provider="Cashkdiopen\\Payments\\CashkdiopenServiceProvider"
php artisan migrate
```

## ⚙️ Configuration

Configure your environment variables in `.env`:

```env
# API Configuration
CASHKDIOPEN_API_KEY=ck_live_your_api_key_here
CASHKDIOPEN_WEBHOOK_SECRET=whk_your_webhook_secret_here
CASHKDIOPEN_ENVIRONMENT=production

# Provider Settings
ORANGE_MONEY_CLIENT_ID=your_client_id
ORANGE_MONEY_CLIENT_SECRET=your_client_secret
ORANGE_MONEY_WEBHOOK_SECRET=your_webhook_secret

MTN_MOMO_API_USER=your_api_user
MTN_MOMO_API_KEY=your_api_key
MTN_MOMO_SUBSCRIPTION_KEY=your_subscription_key
```

## 🎯 Quick Start

### Create a Payment

```php
use Cashkdiopen\\Payments\\Facades\\Cashkdiopen;

$payment = Cashkdiopen::createPayment([
    'amount' => 10000, // Amount in cents (100.00 XOF)
    'currency' => 'XOF',
    'method' => 'orange_money',
    'customer_phone' => '+22607123456',
    'description' => 'Order #12345',
    'callback_url' => route('webhooks.cashkdiopen'),
    'return_url' => route('payment.success'),
    'metadata' => [
        'order_id' => '12345',
        'customer_id' => '67890',
    ]
]);

// Redirect user to payment page
return redirect($payment->redirect_url);
```

### Handle Webhooks

```php
// routes/web.php
Route::post('/webhooks/cashkdiopen', [WebhookController::class, 'handle'])
    ->name('webhooks.cashkdiopen')
    ->withoutMiddleware(['web', 'csrf']);

// app/Http/Controllers/WebhookController.php
use Cashkdiopen\\Payments\\Http\\Middleware\\ValidateWebhookSignature;

class WebhookController extends Controller
{
    public function __construct()
    {
        $this->middleware(ValidateWebhookSignature::class);
    }
    
    public function handle(Request $request)
    {
        $payload = $request->all();
        
        switch ($payload['event']) {
            case 'payment.succeeded':
                $this->handlePaymentSuccess($payload['data']);
                break;
                
            case 'payment.failed':
                $this->handlePaymentFailure($payload['data']);
                break;
        }
        
        return response('OK', 200);
    }
    
    private function handlePaymentSuccess($paymentData)
    {
        $orderId = $paymentData['metadata']['order_id'];
        
        // Update your order status
        Order::where('id', $orderId)->update([
            'status' => 'paid',
            'payment_reference' => $paymentData['provider_reference'],
            'paid_at' => now(),
        ]);
        
        // Send confirmation email, etc.
    }
}
```

### Check Payment Status

```php
$payment = Cashkdiopen::getPayment('txn_01J5XYZABC123');

if ($payment->status === 'success') {
    // Payment confirmed
    $this->fulfillOrder($payment->metadata['order_id']);
}
```

## 🛠️ Advanced Usage

### Custom Provider Configuration

```php
// config/cashkdiopen.php
return [
    'default_provider' => 'orange_money',
    
    'providers' => [
        'orange_money' => [
            'base_url' => env('ORANGE_MONEY_BASE_URL'),
            'client_id' => env('ORANGE_MONEY_CLIENT_ID'),
            'client_secret' => env('ORANGE_MONEY_CLIENT_SECRET'),
            'timeout' => 30,
        ],
        
        'mtn_momo' => [
            'base_url' => env('MTN_MOMO_BASE_URL'),
            'api_user' => env('MTN_MOMO_API_USER'),
            'api_key' => env('MTN_MOMO_API_KEY'),
            'subscription_key' => env('MTN_MOMO_SUBSCRIPTION_KEY'),
            'timeout' => 30,
        ],
    ],
];
```

### Event Listeners

```php
// app/Providers/EventServiceProvider.php
use Cashkdiopen\\Payments\\Events\\PaymentSucceeded;
use App\\Listeners\\SendPaymentConfirmation;

protected $listen = [
    PaymentSucceeded::class => [
        SendPaymentConfirmation::class,
    ],
];
```

### Artisan Commands

```bash
# Generate API key for a user
php artisan cashkdiopen:generate-key user@example.com --name="Production API" --environment=production

# Clean up expired transactions
php artisan cashkdiopen:cleanup --days=30

# Sync payment statuses
php artisan cashkdiopen:sync-status --provider=orange_money
```

## 🧪 Testing

The package includes comprehensive tests. Run them with:

```bash
# Run all tests
composer test

# Run with coverage
composer test-coverage

# Run specific test suite
vendor/bin/pest --filter=PaymentCreationTest
```

### Mock Payments in Tests

```php
use Cashkdiopen\\Payments\\Testing\\CashkdiopenFake;

public function test_payment_creation()
{
    CashkdiopenFake::fake();
    
    $payment = Cashkdiopen::createPayment([
        'amount' => 1000,
        'currency' => 'XOF',
        'method' => 'orange_money',
        // ... other parameters
    ]);
    
    CashkdiopenFake::assertPaymentCreated($payment->id);
}
```

## 🚀 Production Deployment

### Security Checklist

- [ ] Store API keys in environment variables
- [ ] Use HTTPS for all webhooks
- [ ] Validate webhook signatures
- [ ] Implement proper error logging
- [ ] Set up monitoring and alerts

### Performance Optimization

```php
// Cache payment status for 5 minutes
$status = Cache::remember("payment_status_{$paymentId}", 300, function () use ($paymentId) {
    return Cashkdiopen::getPaymentStatus($paymentId);
});

// Queue webhook processing
dispatch(new ProcessPaymentWebhookJob($webhookPayload));
```

## 📚 Documentation

- [Installation Guide](https://docs.cashkdiopen.com/installation)
- [API Reference](https://docs.cashkdiopen.com/api)
- [Webhook Guide](https://docs.cashkdiopen.com/webhooks)
- [Testing Guide](https://docs.cashkdiopen.com/testing)
- [Migration Guide](https://docs.cashkdiopen.com/migration)

## 🤝 Contributing

We welcome contributions! Please read our [Contributing Guide](CONTRIBUTING.md) for details.

### Development Setup

```bash
git clone https://github.com/cashkdiopen/payments.git
cd payments
composer install
cp .env.example .env
vendor/bin/pest
```

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## 💬 Support

- **Documentation**: [docs.cashkdiopen.com](https://docs.cashkdiopen.com)
- **Issues**: [GitHub Issues](https://github.com/cashkdiopen/payments/issues)
- **Discussions**: [GitHub Discussions](https://github.com/cashkdiopen/payments/discussions)
- **Email**: support@cashkdiopen.com

## 🙏 Credits

- **Cashkdi Team** - *Initial work* - [Cashkdi](https://github.com/cashkdi)
- **All Contributors** - [Contributors](https://github.com/cashkdiopen/payments/contributors)

---

Made with ❤️ by [Cashkdi](https://cashkdi.com) for African developers