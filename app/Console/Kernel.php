<?php

namespace App\Console;

use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Console\Scheduling\Schedule;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule) {
        $schedule->command('tenancy:run tenant:run')
            ->everyMinute();
        // Se ejecutara por hora guardando estado de cpu y memoria (windows/linux)
        $schedule->command('status:server')->everyMinute();
        $schedule->command('order:payments')->everyMinute()->appendOutputTo(storage_path('logs/order_create.log'));
        $schedule->command('ecommerce:send-stock-notifications')->hourly();
        // Libera reservas de stock de checkouts ecommerce abandonados (cada 30 minutos)
        $schedule->command('stock:release-expired --minutes=60')
                 ->everyThirtyMinutes()
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/stock_release.log'));

        // Limpia carritos expirados (más de 7 días sin convertirse en orden)
        $schedule->command('abandoned-carts:purge')
                 ->daily()
                 ->at('03:00')
                 ->appendOutputTo(storage_path('logs/abandoned_carts.log'));

        // Marketplace: recalcula intereses del comprador (categoria_id → score)
        // y purga views > 90 dias. Ambos diario fuera de horario pico.
        $schedule->job(new \App\Jobs\Marketplace\RecalculateMarketplaceUserInterests())
                 ->dailyAt('03:30')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/marketplace_interests.log'));
        $schedule->job(new \App\Jobs\Marketplace\PurgeOldMarketplaceUserViews())
                 ->dailyAt('03:45')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/marketplace_views_purge.log'));

        // Marketplace notificaciones (con consent gating). El driver de
        // mail puede ser smtp ahora y Brevo mas adelante sin cambios.
        // Diario 09:00: detecta drops en favoritos y manda agrupado.
        $schedule->job(new \App\Jobs\Marketplace\DetectAndNotifyPriceDrops())
                 ->dailyAt('09:00')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/marketplace_price_drops.log'));
        // Domingos 10:00: digest semanal de ofertas en categorias seguidas.
        $schedule->job(new \App\Jobs\Marketplace\SendWeeklyMarketplaceDigest())
                 ->weeklyOn(0, '10:00')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/marketplace_weekly_digest.log'));

        // Sincroniza estado de tracking con APIs de carriers (Chazki, 99Minutos, etc.)
        // Solo actúa si hay couriers con api_driver != 'manual' configurados
        $schedule->command('carrier:sync-tracking')
                 ->everyThirtyMinutes()
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/carrier_tracking.log'));

        // Detecta divergencias de inventario (dry-run, solo reporta — no corrige)
        // Para corregir manualmente: php artisan stock:reconcile --fix
        $schedule->command('stock:reconcile --threshold=0.01')
                 ->weekly()
                 ->sundays()
                 ->at('02:00')
                 ->appendOutputTo(storage_path('logs/stock_reconcile.log'));

        // Solicita reseñas a clientes con pedidos entregados hace 3+ días
        $schedule->command('ecommerce:send-review-requests')
                 ->dailyAt('10:00')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/review_requests.log'));

        // Recordatorio de carritos abandonados: secuencia de 3 pasos (1h, 24h, 72h)
        $schedule->command('cart:send-reminders')
                 ->hourly()
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/cart_reminders.log'));

        // Reportes programados por email
        $schedule->command('reports:send-scheduled')
                 ->everyMinute()
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/scheduled_reports.log'));

        // ETL nocturno: sincroniza ventas e items de todos los tenants al warehouse analítico
        // Rango: ayer → hoy (incremental). Incluye snapshot de catálogo con --with-items.
        // Para correr manualmente: php artisan warehouse:sync-etl --from=Y-m-d --to=Y-m-d
        $schedule->command('warehouse:sync-etl --with-items')
                 ->dailyAt('02:30')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/warehouse_etl.log'));
        // Marketplace: sync stock + fetch orders cada 15 min
        $schedule->command('marketplace:sync stock')
                 ->everyFifteenMinutes()
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/marketplace_stock.log'));

        // Marketplace ebaemy (agregador central): refresca el índice de
        // listings de los tenants que publicaron productos.
        $schedule->command('ebaemy-marketplace:sync')
                 ->everyThirtyMinutes()
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/ebaemy_marketplace_sync.log'));

        // Reintenta leads del marketplace central que quedaron failed/new
        // por fallos transitorios (tenant temporalmente down, timeout, etc.).
        // Backoff: solo toca leads con 2+ min de antigüedad; auto-archiva tras 5 intentos.
        $schedule->command('marketplace:retry-failed-leads')
                 ->everyFifteenMinutes()
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/marketplace_retry_leads.log'));

        // Reintenta subpedidos multi-tienda que quedaron failed (Fase 2).
        // Mismo principio que retry-failed-leads pero para tenant_marketplace_orders.
        $schedule->command('marketplace:retry-failed-orders')
                 ->everyFifteenMinutes()
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/marketplace_retry_orders.log'));

        $schedule->command('marketplace:sync orders')
                 ->everyFifteenMinutes()
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/marketplace_orders.log'));

        // Recordatorios por email para pedidos marketplace abandonados
        // (status=pending + unpaid >2h). Máximo 2 envíos por pedido.
        $schedule->command('marketplace:remind-abandoned-orders')
                 ->hourly()
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/marketplace_abandoned.log'));

        // Recordatorios al TENANT cuando un subpedido sigue 'pending'
        // pasadas 2h. Email + WhatsApp, max 3 envios, cooldown 12h.
        $schedule->command('marketplace:remind-pending-tenant-orders')
                 ->hourly()
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/marketplace_tenant_reminders.log'));

        // Marketplace: regenerar feed Meta cada 6 horas
        $schedule->command('marketplace:sync feed --platform=meta')
                 ->everySixHours()
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/marketplace_feed.log'));

        // Verificar dominios personalizados pendientes cada 30 minutos
        $schedule->command('domains:verify-pending')
                 ->everyThirtyMinutes()
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/domain_verification.log'));

        // Recordar pedidos pendientes cada minuto via WhatsApp
        $schedule->command('orders:remind-pending')
                 ->everyMinute()
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/pending_orders_reminder.log'));

        // Llena las tablas para libro mayor - Se desactiva CMAR - buscar opcion de url
        // $schedule->command('account_ledger:fill')->hourly();
        
        //restaurar base de datos demo para restaurant
        // $schedule->command('database:restoredemo')->dailyAt('23:50');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands() {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
