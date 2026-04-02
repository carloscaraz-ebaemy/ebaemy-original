<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        // ─── Eventos de theme ──────────────────────────────────────────────────
        \App\Events\ThemeChanged::class => [
            \App\Listeners\ClearThemeCache::class,
        ],

        // ─── Eventos logísticos ────────────────────────────────────────────────
        \App\Events\Logistic\ProvinceOrderCreated::class => [
            \App\Listeners\Logistic\LogProvinceOrderCreated::class,
            // \App\Listeners\Logistic\SendWhatsappNotificationToWarehouse::class,
            // \App\Listeners\Logistic\SendEmailNotificationToCustomer::class,
        ],
        \App\Events\Logistic\OrderDispatched::class => [
            // \App\Listeners\Logistic\SendShippingGuideByEmail::class,
            // \App\Listeners\Logistic\SendWhatsappTrackingToCustomer::class,
        ],
        \App\Events\Logistic\OrderStatusChanged::class => [
            \App\Listeners\Logistic\LogOrderStatusChanged::class,
            // \App\Listeners\Logistic\UpdateDashboardStats::class,
        ],

        // ─── Tenant — réplica MySQL de solo-lectura ────────────────────────────
        // Registra 'tenant_read' cuando TENANT_REPLICA_HOST está en .env.
        // Las queries de reportes usan ReplicaConnectionManager para enrutarse ahí.
        \Hyn\Tenancy\Events\Database\ConnectionSet::class => [
            \App\Listeners\Tenancy\SetupReplicaConnection::class,
        ],

        // ─── Tenant provisioning — snapshot de esquema ────────────────────────
        // Importa tenant_schema_snapshot.sql en la nueva DB antes de que
        // MigratesTenants corra las migraciones individuales.
        // Generar/actualizar snapshot: php artisan tenant:snapshot
        \Hyn\Tenancy\Events\Database\Created::class => [
            \App\Listeners\Tenancy\ApplySchemaSnapshot::class,
        ],

        // ─── Eventos ecommerce ─────────────────────────────────────────────────
        \App\Events\Ecommerce\OrderCreated::class => [
            \App\Listeners\Ecommerce\SendOrderConfirmationEmail::class,
            \App\Listeners\Ecommerce\SendWhatsAppNotification::class,
            \App\Listeners\NotifyAdminNewOrder::class,
            \App\Listeners\AutoGenerateSaleNote::class,
            \App\Listeners\Ecommerce\SendFacebookConversionEvent::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        //
    }
}
