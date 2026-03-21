<?php

namespace App\Services\Tenant;

use App\Exceptions\InsufficientStockException;
use App\Models\Tenant\Item;
use App\Models\Tenant\ItemWarehouse;
use App\Models\Tenant\LogisticOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CheckoutService — Procesa el checkout del Ecommerce.
 *
 * REGLAS CRÍTICAS de seguridad:
 *   1. Solo valida stock_available (NO stock_physical). Evita vender stock comprometido.
 *   2. Los precios se recalculan en backend (NO se confía en el frontend).
 *   3. El stock se reserva (PROVINCE_COMMIT) dentro de createProvinceOrder().
 *   4. Si el pago falla → cancelOrder() libera el PROVINCE_COMMIT con PROVINCE_CANCEL.
 *   5. Toda operación de stock usa lockForUpdate() + transaction.
 */
class CheckoutService
{
    public function __construct(
        private readonly OrderService $orderService
    ) {}

    /**
     * Procesa el checkout del ecommerce.
     *
     * @param array $data {
     *   customer_id (opcional — guest o registrado),
     *   warehouse_id,
     *   delivery_type (province|pickup),
     *   destination_district, destination_address, recipient_name, recipient_phone,
     *   items: [{ item_id, quantity }],
     *   currency_type_id,
     *   payment_method (culqi_token, mercadopago_token, transferencia, etc.),
     *   notes
     * }
     */
    public function processCheckout(array $data): LogisticOrder
    {
        return DB::transaction(function () use ($data) {

            // 1. Validar y enriquecer ítems con precios del backend
            $enrichedItems = $this->enrichAndValidateItems($data['items'], $data['warehouse_id']);
            $data['items'] = $enrichedItems;

            // 2. Crear la orden logística — createProvinceOrder valida stock,
            //    aplica PROVINCE_COMMIT y lo registra en StockMovement.
            //    Si falla, el transaction rollback revierte todo automáticamente.
            $order = $this->orderService->createProvinceOrder(array_merge($data, [
                'source'                  => 'ecommerce',
                'emit_document_on_confirm' => false, // Se emite al despachar
            ]));

            Log::info('[CheckoutService] Checkout procesado', [
                'order_id'     => $order->id,
                'customer_id'  => $data['customer_id'] ?? 'guest',
                'total'        => $order->total,
                'items_count'  => count($enrichedItems),
            ]);

            return $order;
        });
    }

    /**
     * Libera las reservas de stock si el pago falla o el usuario abandona el carrito.
     */
    public function releaseCheckoutReservation(LogisticOrder $order): void
    {
        // cancelOrder() libera el PROVINCE_COMMIT con PROVINCE_CANCEL y marca la orden cancelada.
        $this->orderService->cancelOrder($order, 'Checkout no completado');
    }

    // ─── Helpers privados ────────────────────────────────────────────────────

    /**
     * Enriquece los ítems del carrito con precios del backend.
     * Valida que haya stock_available suficiente.
     *
     * @throws InsufficientStockException
     */
    private function enrichAndValidateItems(array $cartItems, int $warehouseId): array
    {
        $enriched = [];

        foreach ($cartItems as $cartItem) {
            // Obtener precio actual desde la base de datos (NO del frontend)
            $item = Item::where('id', $cartItem['item_id'])
                        ->where('apply_store', true)
                        ->firstOrFail();

            $iw = ItemWarehouse::where('item_id', $cartItem['item_id'])
                               ->where('warehouse_id', $warehouseId)
                               ->lockForUpdate()
                               ->first();

            // Validar contra stock_available (NO stock_physical)
            $available = $iw?->stock_available ?? 0;
            if ($available < $cartItem['quantity']) {
                throw new InsufficientStockException(
                    "'{$item->description}' no tiene stock suficiente. " .
                    "Disponible: {$available}, solicitado: {$cartItem['quantity']}"
                );
            }

            $enriched[] = [
                'item_id'                 => $item->id,
                'description'             => $item->description,
                'unit_type_id'            => $item->unit_type_id ?? 'NIU',
                'quantity'                => (float) $cartItem['quantity'],
                // Precio del backend — NO del frontend
                'unit_price'              => (float) $item->sale_unit_price,
                'affectation_igv_type_id' => $item->affectation_igv_type_id ?? '10',
            ];
        }

        return $enriched;
    }
}
