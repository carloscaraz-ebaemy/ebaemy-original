<?php

namespace App\Observers;

use App\Models\Tenant\Item;
use App\Models\Tenant\MarketplaceChannel;
use App\Models\Tenant\MarketplaceProduct;
use App\Jobs\Marketplace\PublishProductToSagaJob;
use Hyn\Tenancy\Environment;
use Illuminate\Support\Facades\Log;

/**
 * Auto-publica a Saga Falabella cuando se crea/edita un producto del ERP (Fase 4).
 *
 *  - Producto YA enlazado a un canal Falabella → re-sincroniza (encola push).
 *  - Producto nuevo con apply_store y el canal con `auto_publish` ON → lo publica.
 *
 * Se SILENCIA durante la importación (FalabellaImportService) vía self::$enabled
 * para no re-publicar lo que acabamos de traer DE Saga (bucle de retroalimentación).
 */
class MarketplaceItemObserver
{
    /** Interruptor global para silenciar el auto-publish (ej. importación). */
    public static bool $enabled = true;

    /** Ejecuta $fn con el auto-publish silenciado, restaurando el estado previo. */
    public static function mute(callable $fn)
    {
        $prev = self::$enabled;
        self::$enabled = false;
        try {
            return $fn();
        } finally {
            self::$enabled = $prev;
        }
    }

    public function saved(Item $item): void
    {
        if (!self::$enabled) {
            return;
        }

        try {
            $channels = MarketplaceChannel::active()->where('platform', 'falabella')->get();
        } catch (\Throwable $e) {
            return; // tenant sin tablas de marketplace todavía
        }
        if ($channels->isEmpty()) {
            return;
        }

        $uuid = optional(app(Environment::class)->tenant())->uuid;
        if (!$uuid) {
            return;
        }

        foreach ($channels as $channel) {
            try {
                $mappings = MarketplaceProduct::where('channel_id', $channel->id)
                    ->where('item_id', $item->id)
                    ->where('sync_status', '!=', 'excluded')
                    ->get();

                if ($mappings->isNotEmpty()) {
                    // Re-sincroniza los productos ya enlazados a Saga.
                    foreach ($mappings as $m) {
                        PublishProductToSagaJob::dispatch($uuid, $channel->id, $item->id, $m->item_variant_id);
                    }
                } elseif ((bool) data_get($channel->settings, 'auto_publish', false) && $item->apply_store) {
                    // Auto-publicar productos nuevos (opt-in por canal).
                    PublishProductToSagaJob::dispatch($uuid, $channel->id, $item->id, null);
                }
            } catch (\Throwable $e) {
                // El auto-publish NUNCA debe romper el guardado del producto.
                Log::channel('payments')->warning('Auto-publish Saga: no se pudo encolar', [
                    'item_id'    => $item->id,
                    'channel_id' => $channel->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }
    }
}
