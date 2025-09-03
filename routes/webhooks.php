<?php

use Illuminate\Support\Facades\Route;
use Cashkdiopen\Payments\Http\Controllers\WebhookController;

/*
|--------------------------------------------------------------------------
| Cashkdiopen Webhook Routes
|--------------------------------------------------------------------------
|
| These routes handle incoming webhooks from payment providers. They are
| automatically prefixed with 'webhooks' and include signature validation
| middleware to ensure the webhooks are authentic.
|
*/

Route::middleware([
    'cashkdiopen.webhook.signature',
    'cashkdiopen.webhook.log'
])->group(function () {
    
    /*
    |--------------------------------------------------------------------------
    | Provider Webhook Routes
    |--------------------------------------------------------------------------
    */
    
    // Orange Money webhooks
    Route::post('/orange-money', [WebhookController::class, 'handleOrangeMoney'])
        ->name('cashkdiopen.webhooks.orange-money');
    
    // MTN Mobile Money webhooks
    Route::post('/mtn-momo', [WebhookController::class, 'handleMtnMoMo'])
        ->name('cashkdiopen.webhooks.mtn-momo');
    
    // Bank cards webhooks
    Route::post('/cards', [WebhookController::class, 'handleCards'])
        ->name('cashkdiopen.webhooks.cards');
    
    // Generic webhook handler (auto-detects provider)
    Route::post('/payment/{provider}', [WebhookController::class, 'handleGeneric'])
        ->name('cashkdiopen.webhooks.generic')
        ->where('provider', '^[a-z_]+$');
});

/*
|--------------------------------------------------------------------------
| Webhook Management Routes (Protected)
|--------------------------------------------------------------------------
|
| These routes allow management of webhook logs and retries. They require
| authentication and are typically used by admin dashboards.
|
*/

Route::middleware([
    'cashkdiopen.auth',
    'cashkdiopen.webhook.admin'
])->prefix('admin')->group(function () {
    
    // List webhook logs
    Route::get('/webhooks', [WebhookController::class, 'logs'])
        ->name('cashkdiopen.webhooks.logs');
    
    // Get webhook log details
    Route::get('/webhooks/{webhookLog}', [WebhookController::class, 'showLog'])
        ->name('cashkdiopen.webhooks.show-log');
    
    // Retry failed webhook processing
    Route::post('/webhooks/{webhookLog}/retry', [WebhookController::class, 'retry'])
        ->name('cashkdiopen.webhooks.retry');
    
    // Bulk retry failed webhooks
    Route::post('/webhooks/retry-failed', [WebhookController::class, 'retryFailed'])
        ->name('cashkdiopen.webhooks.retry-failed');
});