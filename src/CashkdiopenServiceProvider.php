<?php

namespace Cashkdiopen\Laravel;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Cashkdiopen\Laravel\Console\Commands\GenerateApiKeyCommand;
use Cashkdiopen\Laravel\Console\Commands\CleanupCommand;
use Cashkdiopen\Laravel\Console\Commands\SyncPaymentStatusCommand;
use Cashkdiopen\Laravel\Contracts\PaymentProviderInterface;
use Cashkdiopen\Laravel\Services\PaymentService;
use Cashkdiopen\Laravel\Services\WebhookService;
use Cashkdiopen\Laravel\Services\SignatureService;
use Cashkdiopen\Laravel\Services\ProviderFactory;

class CashkdiopenServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/cashkdiopen.php', 'cashkdiopen');

        // Register core services
        $this->registerCoreServices();
        
        // Register provider factory
        $this->registerProviderFactory();
        
        // Register facade
        $this->registerFacade();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->bootConfiguration();
        $this->bootDatabase();
        $this->bootRoutes();
        $this->bootCommands();
        $this->bootPublishing();
        $this->bootEventListeners();
    }

    /**
     * Register core services.
     */
    protected function registerCoreServices(): void
    {
        // Payment Service
        $this->app->singleton(PaymentService::class, function ($app) {
            return new PaymentService(
                $app->make(ProviderFactory::class),
                $app->make('config')->get('cashkdiopen')
            );
        });

        // Webhook Service
        $this->app->singleton(WebhookService::class, function ($app) {
            return new WebhookService(
                $app->make(SignatureService::class),
                $app->make('config')->get('cashkdiopen.webhooks')
            );
        });

        // Signature Service
        $this->app->singleton(SignatureService::class, function ($app) {
            return new SignatureService(
                $app->make('config')->get('cashkdiopen.webhooks')
            );
        });
    }

    /**
     * Register provider factory and interface binding.
     */
    protected function registerProviderFactory(): void
    {
        // Provider Factory
        $this->app->singleton(ProviderFactory::class, function ($app) {
            return new ProviderFactory(
                $app->make('config')->get('cashkdiopen.providers', [])
            );
        });

        // Bind the default provider interface
        $this->app->bind(PaymentProviderInterface::class, function ($app) {
            $factory = $app->make(ProviderFactory::class);
            $defaultProvider = $app->make('config')->get('cashkdiopen.default_provider');
            
            return $factory->make($defaultProvider);
        });
    }

    /**
     * Register facade.
     */
    protected function registerFacade(): void
    {
        $this->app->singleton('cashkdiopen', function ($app) {
            return $app->make(PaymentService::class);
        });
    }

    /**
     * Boot configuration.
     */
    protected function bootConfiguration(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/cashkdiopen.php' => config_path('cashkdiopen.php'),
            ], 'cashkdiopen-config');
        }
    }

    /**
     * Boot database migrations.
     */
    protected function bootDatabase(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../database/migrations/' => database_path('migrations'),
            ], 'cashkdiopen-migrations');
        }
    }

    /**
     * Boot API routes.
     */
    protected function bootRoutes(): void
    {
        if (! $this->app->routesAreCached()) {
            $this->loadApiRoutes();
            $this->loadWebhookRoutes();
        }
    }

    /**
     * Load API routes.
     */
    protected function loadApiRoutes(): void
    {
        Route::group([
            'prefix' => 'api/cashkdiopen',
            'middleware' => ['api', 'throttle:api'],
            'namespace' => 'Cashkdiopen\\Laravel\\Http\\Controllers',
        ], function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        });
    }

    /**
     * Load webhook routes.
     */
    protected function loadWebhookRoutes(): void
    {
        $routePrefix = config('cashkdiopen.webhooks.route_prefix', 'webhooks');
        $routeMiddleware = config('cashkdiopen.webhooks.route_middleware', ['api']);

        Route::group([
            'prefix' => $routePrefix,
            'middleware' => $routeMiddleware,
            'namespace' => 'Cashkdiopen\\Laravel\\Http\\Controllers',
        ], function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/webhooks.php');
        });
    }

    /**
     * Boot Artisan commands.
     */
    protected function bootCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateApiKeyCommand::class,
                CleanupCommand::class,
                SyncPaymentStatusCommand::class,
            ]);
        }
    }

    /**
     * Boot publishing.
     */
    protected function bootPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish all assets
            $this->publishes([
                __DIR__ . '/../config/cashkdiopen.php' => config_path('cashkdiopen.php'),
                __DIR__ . '/../database/migrations/' => database_path('migrations'),
            ], 'cashkdiopen');

            // Publish specific asset groups
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/cashkdiopen'),
            ], 'cashkdiopen-views');
        }
    }

    /**
     * Boot event listeners.
     */
    protected function bootEventListeners(): void
    {
        // Register event listeners for payment events
        $events = $this->app['events'];
        
        // Payment events
        $events->listen(
            'Cashkdiopen\\Laravel\\Events\\PaymentCreated',
            'Cashkdiopen\\Laravel\\Listeners\\LogPaymentCreated'
        );
        
        $events->listen(
            'Cashkdiopen\\Laravel\\Events\\PaymentSucceeded',
            'Cashkdiopen\\Laravel\\Listeners\\LogPaymentSucceeded'
        );
        
        $events->listen(
            'Cashkdiopen\\Laravel\\Events\\PaymentFailed',
            'Cashkdiopen\\Laravel\\Listeners\\LogPaymentFailed'
        );
        
        // Webhook events
        $events->listen(
            'Cashkdiopen\\Laravel\\Events\\WebhookReceived',
            'Cashkdiopen\\Laravel\\Listeners\\ProcessWebhook'
        );
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            'cashkdiopen',
            PaymentService::class,
            WebhookService::class,
            SignatureService::class,
            ProviderFactory::class,
            PaymentProviderInterface::class,
        ];
    }
}