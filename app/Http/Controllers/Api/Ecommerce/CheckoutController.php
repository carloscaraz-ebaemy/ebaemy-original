<?php

namespace App\Http\Controllers\Api\Ecommerce;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\CheckoutRequest;
use App\Http\Resources\Tenant\LogisticOrderResource;
use App\Models\Tenant\LogisticOrder;
use App\Services\Tenant\CheckoutService;
use Illuminate\Http\JsonResponse;

/**
 * CheckoutController — API pública del Ecommerce.
 *
 * ENDPOINT: POST /api/ecommerce/checkout
 *
 * El tenant se resuelve por subdominio a través del middleware
 * de Hyn\Tenancy (ya configurado en el proyecto).
 *
 * Los precios se recalculan en el backend (CheckoutService).
 * El frontend NUNCA dicta precios.
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkoutService
    ) {}

    /**
     * Procesa el checkout del ecommerce.
     * Crea un pedido provincia en la cola del almacén.
     */
    public function store(CheckoutRequest $request): JsonResponse
    {
        $order = $this->checkoutService->processCheckout($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Pedido creado exitosamente. Será procesado por nuestro almacén.',
            'data'    => new LogisticOrderResource($order),
        ], 201);
    }

    /**
     * Obtiene el estado de un pedido (para polling o confirmación post-pago).
     */
    public function show(int $orderId): JsonResponse
    {
        $order = LogisticOrder::where('id', $orderId)
                              ->where('source', 'ecommerce')
                              ->with(['items', 'shippingGuide'])
                              ->firstOrFail();

        return response()->json([
            'success' => true,
            'data'    => new LogisticOrderResource($order),
        ]);
    }

    /**
     * Cancela una reserva de checkout (pago fallido o abandono).
     */
    public function cancel(int $orderId): JsonResponse
    {
        $order = LogisticOrder::where('id', $orderId)
                              ->where('source', 'ecommerce')
                              ->firstOrFail();

        $this->checkoutService->releaseCheckoutReservation($order);

        return response()->json([
            'success' => true,
            'message' => 'Pedido cancelado y stock liberado.',
        ]);
    }

    /**
     * Consulta el stock_available de productos para el Ecommerce.
     * Solo expone stock_available — nunca stock_physical ni stock_committed.
     */
    public function stockAvailability(int $warehouseId): JsonResponse
    {
        $items = \App\Models\Tenant\ItemWarehouse::where('warehouse_id', $warehouseId)
            ->with('item:id,description,sale_unit_price,image,apply_store,slug')
            ->get()
            ->filter(fn($iw) => $iw->item && $iw->item->apply_store)
            ->map(fn($iw) => [
                'item_id'         => $iw->item_id,
                'description'     => $iw->item->description,
                'slug'            => $iw->item->slug,
                'unit_price'      => $iw->item->sale_unit_price,
                'image'           => $iw->item->image,
                'stock_available' => $iw->stock_available, // Solo este valor para el frontend
                'in_stock'        => $iw->stock_available > 0,
            ]);

        return response()->json([
            'success' => true,
            'data'    => $items->values(),
        ]);
    }
}
