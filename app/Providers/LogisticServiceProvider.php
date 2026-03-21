<?php

namespace App\Providers;

use App\Services\Tenant\BillingService;
use App\Services\Tenant\CheckoutService;
use App\Services\Tenant\OrderService;
use Illuminate\Support\ServiceProvider;

/**
 * LogisticServiceProvider — Registra los servicios del sistema logístico.
 *
 * Registro en config/app.php → providers[]:
 *   App\Providers\LogisticServiceProvider::class,
 */
class LogisticServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // BillingService: integra con CoreFacturalo para emitir comprobantes SUNAT
        $this->app->singleton(BillingService::class);

        // OrderService: lógica de negocio de pedidos logísticos (tienda + provincia)
        $this->app->singleton(OrderService::class, function ($app) {
            return new OrderService($app->make(BillingService::class));
        });

        // CheckoutService: procesa el checkout del Ecommerce
        $this->app->singleton(CheckoutService::class, function ($app) {
            return new CheckoutService($app->make(OrderService::class));
        });
    }

    public function boot(): void
    {
        //
    }
}
