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
