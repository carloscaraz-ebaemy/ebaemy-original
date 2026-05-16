<?php

namespace App\Services\Marketplace;

use App\Jobs\Marketplace\PushOrderToSystem;
use Hyn\Tenancy\Environment;

/**
 * Sync de pedidos del tenant hacia el agregado del comprador en system.
 *
 * Diseno:
 *  - Tenant es fuente de verdad. System solo guarda snapshot agregado
 *    para personalizacion (no detalle, no lineas).
 *  - Push via job en queue. El tenant NUNCA falla si system esta caido.
 *  - Idempotente: re-push de un mismo pedido actualiza la fila.
 *
 * Como instrumentar el flujo legacy del tenant:
 *
 *   En el lugar donde el pedido se confirma (DocumentController@store,
 *   SaleNoteController, OrderController, etc.) — DESPUES del save() y
 *   DENTRO del mismo if(success) — agregar:
 *
 *     if ($document->customer_marketplace_user_id) {
 *         app(MarketplaceOrderSyncService::class)->syncOrder(
 *             $document->customer_marketplace_user_id,
 *             $document
 *         );
 *     }
 *
 *   $customer_marketplace_user_id lo carga el checkout cuando
 *   auth('marketplace')->check() — guardar al confirmar el pedido.
 *
 * Ver README_ORDER_SYNC.md para detalle.
 */
class MarketplaceOrderSyncService
{
    /**
     * @param int   $marketplaceUserId
     * @param mixed $tenantOrder  Cualquier modelo del tenant con campos
     *                            total, currency, state/status, created_at,
     *                            items (relacion), product_categories
     *                            (optional, sino se derivan).
     */
    public function syncOrder(int $marketplaceUserId, $tenantOrder): void
    {
        $hostnameId = $this->currentHostnameId();
        if (!$hostnameId) return;  // sin contexto de tenant, no hay nada que sync

        $status = $this->mapStatus($tenantOrder);
        $confirmedAt = $status === 'confirmed' || $status === 'completed'
            ? ($tenantOrder->confirmed_at ?? $tenantOrder->created_at ?? null)
            : null;
        $cancelledAt = $status === 'cancelled'
            ? ($tenantOrder->cancelled_at ?? now())
            : null;

        // Derivar categorias si no vienen explicitas (best-effort).
        $categories = $this->extractCategories($tenantOrder);
        $itemsCount = $this->extractItemsCount($tenantOrder);

        PushOrderToSystem::dispatch(
            $marketplaceUserId,
            (int) $hostnameId,
            (int) $tenantOrder->id,
            (float) ($tenantOrder->total ?? 0),
            (string) ($tenantOrder->currency_type_id ?: $tenantOrder->currency ?: 'PEN'),
            $status,
            $confirmedAt ? (string) $confirmedAt : null,
            $cancelledAt ? (string) $cancelledAt : null,
            $itemsCount,
            $categories,
        )->onQueue('default');
    }

    private function currentHostnameId(): ?int
    {
        try {
            $hostname = app(Environment::class)->hostname();
            return $hostname ? (int) $hostname->id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function mapStatus($tenantOrder): string
    {
        $raw = strtolower((string) ($tenantOrder->state_type_id
            ?? $tenantOrder->state
            ?? $tenantOrder->status
            ?? ''));
        return match (true) {
            in_array($raw, ['11', '13', 'cancelled', 'canceled', 'anulado']) => 'cancelled',
            in_array($raw, ['05', '07', 'completed', 'delivered'])           => 'completed',
            in_array($raw, ['01', 'registered', 'confirmed', 'pending'])      => 'confirmed',
            default                                                            => 'confirmed',
        };
    }

    private function extractCategories($tenantOrder): array
    {
        if (isset($tenantOrder->product_categories) && is_array($tenantOrder->product_categories)) {
            return $tenantOrder->product_categories;
        }
        // Mejor effort: si tiene items() con item->marketplace_category_id.
        try {
            $items = $tenantOrder->items ?? null;
            if (!$items) return [];
            $ids = [];
            foreach ($items as $line) {
                $catId = $line->item->marketplace_category_id ?? null;
                if ($catId) $ids[] = (int) $catId;
            }
            return array_values(array_unique($ids));
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function extractItemsCount($tenantOrder): int
    {
        if (isset($tenantOrder->items_count)) return (int) $tenantOrder->items_count;
        try {
            $items = $tenantOrder->items ?? null;
            return $items ? count($items) : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
