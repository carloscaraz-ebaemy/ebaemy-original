<?php

namespace App\Console\Commands;

use App\Models\Tenant\MarketplaceOrder;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;

/**
 * Marca en bloque pedidos de marketplace como "boleta ya emitida (fuera de
 * EBAEMY)". Útil para históricos ya despachados/entregados que se facturaron
 * en otro sistema — así NO vuelven a pedir "Generar boleta" ni se duplican.
 *
 * Saga no expone por API si un pedido tiene comprobante, por eso se marca por
 * criterio (estado + fecha), no por consulta.
 *
 *   php artisan marketplace:mark-invoiced --tenant=UUID --dry-run
 *   php artisan marketplace:mark-invoiced --tenant=UUID
 *   php artisan marketplace:mark-invoiced --tenant=UUID --statuses=delivered,shipped --before=2026-06-17
 */
class MarketplaceMarkInvoiced extends Command
{
    protected $signature = 'marketplace:mark-invoiced
                            {--tenant= : UUID del website (obligatorio)}
                            {--statuses=delivered,shipped : Estados a marcar (coma-separados)}
                            {--before= : Solo pedidos con ordered_at anterior a esta fecha (Y-m-d). Por defecto: hoy}
                            {--dry-run : Solo cuenta, no marca}';

    protected $description = 'Marca en bloque pedidos de marketplace como boleta ya emitida fuera de EBAEMY';

    public function handle(): int
    {
        $uuid = $this->option('tenant');
        if (!$uuid) {
            $this->error('Falta --tenant=UUID.');
            return self::FAILURE;
        }
        $website = Website::where('uuid', $uuid)->first();
        if (!$website) {
            $this->error("No existe el tenant {$uuid}.");
            return self::FAILURE;
        }
        app(Environment::class)->tenant($website);

        $statuses = array_filter(array_map('trim', explode(',', (string) $this->option('statuses'))));
        $before = $this->option('before') ?: now()->toDateString();

        // Solo los que NO tienen boleta de EBAEMY (document_id null) ni marca previa.
        $query = MarketplaceOrder::whereIn('status', $statuses)
            ->whereNull('document_id')
            ->whereNull('invoice_uploaded_at')
            ->whereDate('ordered_at', '<', $before);

        $count = (clone $query)->count();
        $this->info("Pedidos a marcar (estados: " . implode(',', $statuses) . " | ordered_at < {$before}): {$count}");

        if ($this->option('dry-run')) {
            $this->line('[DRY-RUN] No se marcó nada.');
            return self::SUCCESS;
        }
        if ($count === 0) {
            return self::SUCCESS;
        }

        $marked = 0;
        foreach ($query->cursor() as $mp) {
            $mp->invoice_uploaded_at = $mp->ordered_at ?? now();
            $mp->invoice_upload_error = 'Histórico: facturado fuera de EBAEMY (marca en bloque)';
            $mp->save();
            $marked++;
        }

        $this->info("Marcados: {$marked}");
        return self::SUCCESS;
    }
}
