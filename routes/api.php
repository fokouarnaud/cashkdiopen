<?php

use Illuminate\Support\Facades\Route;
use Cashkdiopen\Laravel\Http\Controllers\PaymentController;

/*
|--------------------------------------------------------------------------
| Cashkdiopen API Routes
|--------------------------------------------------------------------------
|
| Here are the API routes for Cashkdiopen payment processing. All routes
| are automatically prefixed with 'api/cashkdiopen' and include API
| middleware for authentication and rate limiting.
|
*/

Route::middleware([
    'cashkdiopen.auth',
    'cashkdiopen.rate_limit',
    'cashkdiopen.log_requests'
])->group(function () {
    
    /*
    |--------------------------------------------------------------------------
    | Payment Routes
    |--------------------------------------------------------------------------
    */
    
    // Create a new payment
    Route::post('/payments', [PaymentController::class, 'create'])
        ->name('cashkdiopen.payments.create');
    
    // Get payment details
    Route::get('/payments/{payment}', [PaymentController::class, 'show'])
        ->name('cashkdiopen.payments.show');
    
    // Get payment status only
    Route::get('/payments/{payment}/status', [PaymentController::class, 'status'])
        ->name('cashkdiopen.payments.status');
    
    // List payments with filtering
    Route::get('/payments', [PaymentController::class, 'index'])
        ->name('cashkdiopen.payments.index');
    
    // Cancel a payment (if supported by provider)
    Route::post('/payments/{payment}/cancel', [PaymentController::class, 'cancel'])
        ->name('cashkdiopen.payments.cancel');
    
    /*
    |--------------------------------------------------------------------------
    | Provider Routes
    |--------------------------------------------------------------------------
    */
    
    // Get available providers
    Route::get('/providers', [PaymentController::class, 'providers'])
        ->name('cashkdiopen.providers.index');
    
    // Get provider capabilities
    Route::get('/providers/{provider}', [PaymentController::class, 'providerInfo'])
        ->name('cashkdiopen.providers.show');
    
    /*
    |--------------------------------------------------------------------------
    | Utility Routes
    |--------------------------------------------------------------------------
    */
    
    // Validate phone number for mobile money
    Route::post('/validate/phone', [PaymentController::class, 'validatePhone'])
        ->name('cashkdiopen.validate.phone');
    
    // Get supported currencies for provider
    Route::get('/currencies/{provider?}', [PaymentController::class, 'currencies'])
        ->name('cashkdiopen.currencies');
    
    // Health check endpoint
    Route::get('/health', [PaymentController::class, 'health'])
        ->withoutMiddleware(['cashkdiopen.auth'])
        ->name('cashkdiopen.health');
});