<?php

namespace App\Http\Controllers\Tenant\Logistic;

use App\Enums\DeliveryTypeEnum;
use App\Enums\LogisticStatusEnum;
use App\Enums\StockMovementTypeEnum;
use App\Exceptions\InvalidOrderTransitionException;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Item;
use App\Models\Tenant\ItemWarehouse;
use App\Models\Tenant\LogisticOrder;
use App\Models\Tenant\LogisticShippingGuide;
use App\Models\Tenant\SaleNote;
use App\Models\Tenant\StockMovement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Tenant\CourierCompany;
use App\Services\Tenant\Carrier\CarrierServiceFactory;
use App\Services\Tenant\Carrier\ShipmentRequest;
use Modules\Inventory\Models\Warehouse;

/**
 * WarehouseDispatchController — Panel del almacenero para Notas de Venta.
 *
 * Flujo de estados:
 *   PENDIENTE → PREPARANDO → LISTO_DESPACHO → DESPACHADO
 *
 * Roles permitidos: admin, warehouse (validado con LogisticOrderPolicy).
 * Aislamiento multitenant: automático vía UsesTenantConnection en SaleNote.
 */
class WarehouseDispatchController extends Controller
{
    /**
     * Verificar permiso logístico antes de cada acción web (no AJAX).
     * Si no tiene permiso, redirige al dashboard en lugar de devolver JSON.
     */
    private function checkLogisticAccess(): ?RedirectResponse
    {
        $user = auth()->user();
        if (!$user || !$user->hasLogisticModule()) {
            return redirect()->route('tenant.dashboard.index')
                ->with('error', 'No tienes acceso al módulo de despacho.');
        }
        return null;
    }

    // ═══════════════════════════════════════════════════════════════
    // HISTORIAL DE DESPACHOS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Historial de pedidos ya finalizados (DESPACHADO y RECOGIDO).
     * GET /logistic/sale-notes/history
     */
    public function history(Request $request)
    {
        if ($redirect = $this->checkLogisticAccess()) return $redirect;
        $this->authorize('viewWarehouseQueue', LogisticOrder::class);

        $query = SaleNote::where('requires_warehouse_dispatch', true)
            ->whereIn('logistic_status', [
                LogisticStatusEnum::DESPACHADO->value,
                LogisticStatusEnum::RECOGIDO->value,
                LogisticStatusEnum::ANULADO->value,
            ])
            ->with([
                'customer:id,name,number',
                'user:id,name',
            ])
            ->orderBy('dispatch_date', 'desc')
            ->orderBy('updated_at', 'desc');

        // Filtros
        if ($request->filled('status')) {
            $query->where('logistic_status', $request->status);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('dispatch_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('dispatch_date', '<=', $request->date_to);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('customer', fn($c) => $c->where('name', 'like', "%$search%"))
                  ->orWhere('series', 'like', "%$search%")
                  ->orWhere('number', 'like', "%$search%")
                  ->orWhere('courier_name', 'like', "%$search%")
                  ->orWhere('tracking_number', 'like', "%$search%")
                  ->orWhere('pickup_person', 'like', "%$search%");
            });
        }

        $saleNotes = $query->paginate(25)->withQueryString();

        $totals = [
            'DESPACHADO' => SaleNote::where('requires_warehouse_dispatch', true)
                ->where('logistic_status', LogisticStatusEnum::DESPACHADO->value)->count(),
            'RECOGIDO'   => SaleNote::where('requires_warehouse_dispatch', true)
                ->where('logistic_status', LogisticStatusEnum::RECOGIDO->value)->count(),
            'ANULADO'    => SaleNote::where('requires_warehouse_dispatch', true)
                ->where('logistic_status', LogisticStatusEnum::ANULADO->value)->count(),
        ];

        return view('tenant.logistic.dispatch_history', compact('saleNotes', 'totals'));
    }

    // ═══════════════════════════════════════════════════════════════
    // COLA DE PEDIDOS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Lista de Notas de Venta en cola del almacén.
     * GET /logistic/sale-notes/queue
     */
    public function index(Request $request)
    {
        if ($redirect = $this->checkLogisticAccess()) return $redirect;
        $this->authorize('viewWarehouseQueue', LogisticOrder::class);

        $query = SaleNote::warehouseQueue()
            ->with([
                'customer:id,name,number',
                'user:id,name',
                'items.relation_item:id,description,internal_id',
            ]);

        // Filtro por estado
        if ($request->filled('status')) {
            $query->where('logistic_status', $request->status);
        }

        // Filtro por fecha
        if ($request->filled('date_from')) {
            $query->whereDate('date_of_issue', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('date_of_issue', '<=', $request->date_to);
        }

        $saleNotes = $query->paginate(20)->withQueryString();

        // Contadores por estado
        $counters = SaleNote::where('requires_warehouse_dispatch', true)
            ->whereIn('logistic_status', LogisticStatusEnum::values())
            ->selectRaw('logistic_status, COUNT(*) as total')
            ->groupBy('logistic_status')
            ->pluck('total', 'logistic_status');

        // ── Métricas del día ────────────────────────────────────────────
        $today = now()->toDateString();

        $metrics = [
            'dispatched_today' => SaleNote::where('requires_warehouse_dispatch', true)
                ->whereIn('logistic_status', [
                    LogisticStatusEnum::DESPACHADO->value,
                    LogisticStatusEnum::RECOGIDO->value,
                ])
                ->whereDate('dispatch_date', $today)
                ->count(),

            'avg_minutes' => (int) SaleNote::where('requires_warehouse_dispatch', true)
                ->whereIn('logistic_status', LogisticStatusEnum::values())
                ->whereNotNull('dispatch_date')
                ->whereDate('dispatch_date', $today)
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, dispatch_date)) as avg_m')
                ->value('avg_m'),

            'by_user' => SaleNote::where('requires_warehouse_dispatch', true)
                ->whereIn('logistic_status', [
                    LogisticStatusEnum::DESPACHADO->value,
                    LogisticStatusEnum::RECOGIDO->value,
                ])
                ->whereDate('dispatch_date', $today)
                ->whereNotNull('warehouse_user_id')
                ->selectRaw('warehouse_user_id, COUNT(*) as total')
                ->with('warehouseUser:id,name')
                ->groupBy('warehouse_user_id')
                ->get(),
        ];

        return view('tenant.logistic.dispatch_queue', compact('saleNotes', 'counters', 'metrics'));
    }

    /**
     * Polling: retorna conteo actual de la cola para detección de cambios.
     * GET /logistic/sale-notes/queue-count
     */
    public function queueCount(): JsonResponse
    {
        $this->authorize('viewWarehouseQueue', LogisticOrder::class);

        $counts = SaleNote::where('requires_warehouse_dispatch', true)
            ->whereIn('logistic_status', array_map(
                fn(LogisticStatusEnum $s) => $s->value,
                LogisticStatusEnum::queueStatuses()
            ))
            ->selectRaw('logistic_status, COUNT(*) as total, MAX(is_urgent) as has_urgent, MAX(created_at) as last_created')
            ->groupBy('logistic_status')
            ->get()
            ->keyBy(fn($r) => $r->logistic_status instanceof \App\Enums\LogisticStatusEnum
                ? $r->logistic_status->value
                : (string) $r->logistic_status
            );

        $pendiente = $counts->get('PENDIENTE');

        return response()->json([
            'total_pending'   => (int) ($pendiente->total ?? 0),
            'has_urgent'      => (bool) ($pendiente->has_urgent ?? false),
            'last_created_at' => $pendiente->last_created ?? null,
            'counts'          => $counts->map(fn($r) => (int) $r->total),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // DETALLE DEL PEDIDO
    // ═══════════════════════════════════════════════════════════════

    /**
     * Detalle de una Nota de Venta con ítems y stock disponible por almacén.
     * GET /logistic/sale-notes/queue/{saleNote}
     */
    /**
     * Retorna HTML parcial para el modal de detalle.
     * GET /logistic/sale-notes/queue/{saleNote}/detail
     */
    public function detail(SaleNote $saleNote)
    {
        if ($redirect = $this->checkLogisticAccess()) return $redirect;
        $this->authorize('viewWarehouseQueue', LogisticOrder::class);

        $saleNote->load([
            'customer:id,name,number',
            'user:id,name',
            'items.relation_item:id,description,internal_id',
        ]);

        return view('tenant.logistic.partials.detail_modal_body', compact('saleNote'));
    }

    public function show(SaleNote $saleNote)
    {
        if ($redirect = $this->checkLogisticAccess()) return $redirect;
        $this->authorize('viewWarehouseQueue', LogisticOrder::class);

        $saleNote->load([
            'customer:id,name,number,address',
            'user:id,name',
            'items.relation_item:id,description,internal_id',
        ]);

        // Almacenes disponibles en el tenant para el selector multi-almacén
        $warehouses = Warehouse::orderBy('description')->get(['id', 'description']);

        // Couriers configurados + últimos usados (para datalist)
        $couriers = CourierCompany::active()->pluck('name');

        // Agregar los últimos 5 couriers usados que no estén ya en la lista
        $recentCouriers = \App\Models\Tenant\SaleNote::where('requires_warehouse_dispatch', true)
            ->whereNotNull('courier_name')
            ->orderBy('dispatch_date', 'desc')
            ->limit(20)
            ->pluck('courier_name')
            ->unique()
            ->take(5);

        $couriers = $couriers->merge($recentCouriers)->unique()->values();

        return view('tenant.logistic.dispatch_form', compact('saleNote', 'warehouses', 'couriers'));
    }

    // ═══════════════════════════════════════════════════════════════
    // TRANSICIONES DE ESTADO
    // ═══════════════════════════════════════════════════════════════

    /**
     * PREPARANDO → PENDIENTE (devolver a la cola)
     * POST /logistic/sale-notes/queue/{saleNote}/cancel
     */
    public function cancelPreparation(SaleNote $saleNote): RedirectResponse
    {
        if ($redirect = $this->checkLogisticAccess()) return $redirect;
        $this->authorize('viewWarehouseQueue', LogisticOrder::class);

        if ($saleNote->logistic_status !== LogisticStatusEnum::PREPARANDO) {
            return redirect()
                ->route('logistic.sale_notes.queue')
                ->with('error', 'Solo se puede devolver un pedido que está en preparación.');
        }

        $saleNote->update([
            'logistic_status'   => LogisticStatusEnum::PENDIENTE->value,
            'warehouse_user_id' => null,
        ]);

        return redirect()
            ->route('logistic.sale_notes.queue')
            ->with('success', "Pedido #{$saleNote->number_full} devuelto a la cola de pendientes.");
    }

    /**
     * PENDIENTE → PREPARANDO
     * El almacenero toma el pedido para prepararlo.
     * POST /logistic/sale-notes/queue/{saleNote}/process
     */
    public function process(SaleNote $saleNote): RedirectResponse
    {
        if ($redirect = $this->checkLogisticAccess()) return $redirect;
        $this->authorize('viewWarehouseQueue', LogisticOrder::class);

        $this->assertTransition($saleNote, LogisticStatusEnum::PREPARANDO);

        $warehouse   = Warehouse::where('establishment_id', $saleNote->establishment_id)->first();
        $warehouseId = $warehouse?->id;

        $saleNote->update([
            'logistic_status'   => LogisticStatusEnum::PREPARANDO->value,
            'warehouse_user_id' => auth()->id(),
            'warehouse_id'      => $warehouseId,
        ]);

        return redirect()
            ->route('logistic.sale_notes.show', $saleNote)
            ->with('success', "Pedido #{$saleNote->number_full} en preparación.");
    }

    /**
     * PREPARANDO → LISTO_DESPACHO
     * POST /logistic/sale-notes/queue/{saleNote}/ready
     */
    public function ready(Request $request, SaleNote $saleNote): RedirectResponse
    {
        if ($redirect = $this->checkLogisticAccess()) return $redirect;
        $this->authorize('viewWarehouseQueue', LogisticOrder::class);

        $this->assertTransition($saleNote, LogisticStatusEnum::LISTO_DESPACHO);

        // Guardar sobreescritura de almacenes por ítem (opcional)
        $warehouseOverrides = $request->input('warehouse_overrides', []);

        $updateData = ['logistic_status' => LogisticStatusEnum::LISTO_DESPACHO->value];

        if ($request->filled('warehouse_notes')) {
            $updateData['warehouse_notes'] = $request->warehouse_notes;
        }

        if (!empty($warehouseOverrides)) {
            $updateData['reference_data'] = json_encode([
                'warehouse_overrides' => $warehouseOverrides,
            ]);
        }

        $saleNote->update($updateData);

        return redirect()
            ->route('logistic.sale_notes.show', $saleNote)
            ->with('success', "Pedido #{$saleNote->number_full} listo para despacho.");
    }

    /**
     * LISTO_DESPACHO → DESPACHADO (courier)
     * Registra courier, tracking, fecha y descuenta stock si hay sobreescritura de almacén.
     * POST /logistic/sale-notes/queue/{saleNote}/dispatch
     */
    public function dispatchOrder(Request $request, SaleNote $saleNote): RedirectResponse
    {
        if ($redirect = $this->checkLogisticAccess()) return $redirect;
        $this->authorize('viewWarehouseQueue', LogisticOrder::class);

        $this->assertTransition($saleNote, LogisticStatusEnum::DESPACHADO);

        $request->validate([
            'courier_name'          => 'required|string|max:150',
            'tracking_number'       => 'nullable|string|max:100',
            'dispatch_date'         => 'required|date',
            'warehouse_notes'       => 'nullable|string|max:500',
            'shipping_packages'     => 'nullable|integer|min:1',
            'shipping_cost_agency'  => 'nullable|numeric|min:0',
            'shipping_carrier_type' => 'nullable|in:propio,tercero',
            'shipping_carrier_cost' => 'nullable|numeric|min:0',
            'shipping_paid_by'      => 'nullable|in:empresa,tercero,cliente',
            'warehouse_overrides'   => 'nullable|array',
            'warehouse_overrides.*.item_id'      => 'required_with:warehouse_overrides|integer',
            'warehouse_overrides.*.warehouse_id' => 'required_with:warehouse_overrides|integer',
        ]);

        $carrierType = $request->shipping_carrier_type ?? 'propio';
        $carrierCost = $carrierType === 'tercero' ? max(0, (float) $request->shipping_carrier_cost) : 0;

        // ── L3: Pre-validar carrier API ANTES de marcar DESPACHADO ────────────
        // Si el courier tiene integración API y la llamada falla con un error
        // de configuración (auth, account inactiva, malformación), abortamos
        // el despacho. NO marcamos como DESPACHADO una NV cuyo shipment real
        // no se creó — el cliente recibiría tracking falso.
        // Si falla por timeout/red (transitorio), permitimos despacho manual
        // con tracking ingresado por el operador.
        $carrierTrackingFromApi = null;
        $carrierLabelUrl        = null;
        $carrierApiMessage      = null;
        $carrierService         = null;

        try {
            $carrierService = CarrierServiceFactory::makeByName($request->courier_name);

            if ($carrierService->hasApiIntegration()) {
                $shipmentReq = ShipmentRequest::fromSaleNote($saleNote);
                $result      = $carrierService->createShipment($shipmentReq);

                $carrierTrackingFromApi = $result->trackingCode ?? null;
                $carrierLabelUrl        = $result->labelUrl ?? null;
                $carrierApiMessage      = "Envío creado en {$request->courier_name}. Tracking: {$carrierTrackingFromApi}";

                Log::info('[WarehouseDispatch] Shipment created via carrier API (pre-dispatch).', [
                    'sale_note_id' => $saleNote->id,
                    'carrier'      => $carrierService->getDriver(),
                    'tracking'     => $carrierTrackingFromApi,
                    'label_url'    => $carrierLabelUrl,
                ]);
            }
        } catch (\App\Services\Tenant\Carrier\CarrierApiException $e) {
            // Errores explícitos del carrier (auth, validation, account suspended).
            // Estos NO son recuperables — abortamos despacho.
            Log::error('[WarehouseDispatch] Carrier API config error — abortando despacho.', [
                'sale_note_id' => $saleNote->id,
                'courier'      => $request->courier_name,
                'error'        => $e->getMessage(),
            ]);
            return back()->withErrors([
                'courier_name' => "No se puede despachar con {$request->courier_name}: {$e->getMessage()}. " .
                                  "Verifica la configuración del carrier o cambia a despacho manual."
            ]);
        } catch (\Throwable $e) {
            // Error genérico (timeout, red, DNS). Permitimos despacho manual
            // pero registramos el incidente y avisamos al operador.
            Log::warning('[WarehouseDispatch] Carrier API unreachable — despacho manual.', [
                'sale_note_id' => $saleNote->id,
                'courier'      => $request->courier_name,
                'error'        => $e->getMessage(),
            ]);
            $carrierApiMessage = "Despacho registrado en modo manual. " .
                                  "(API carrier temporalmente no disponible — el tracking se ingresó manual)";
        }

        // Resolver tracking final: priorizar el devuelto por la API si existe
        $finalTracking = $carrierTrackingFromApi ?: $request->tracking_number;

        DB::connection('tenant')->transaction(function () use ($request, $saleNote, $carrierType, $carrierCost, $finalTracking) {

            $this->applyWarehouseOverrides($request, $saleNote);
            $this->applyMainWarehouseDispatch($request, $saleNote, LogisticStatusEnum::DESPACHADO);

            $saleNote->update([
                'logistic_status'       => LogisticStatusEnum::DESPACHADO->value,
                'courier_name'          => $request->courier_name,
                'tracking_number'       => $finalTracking,
                'dispatch_date'         => $request->dispatch_date,
                'warehouse_user_id'     => auth()->id(),
                'warehouse_notes'       => $request->warehouse_notes,
                'shipping_packages'     => max(1, (int) ($request->shipping_packages ?? 1)),
                'shipping_cost_agency'  => max(0, (float) ($request->shipping_cost_agency ?? 0)),
                'shipping_carrier_type' => $carrierType,
                'shipping_carrier_cost' => $carrierCost,
                'shipping_paid_by'      => $request->shipping_paid_by ?? 'empresa',
            ]);
        });

        // Generar Guía de Remisión PDF
        $freshSaleNote = $saleNote->fresh();
        $guide = $this->generateGuideForSaleNote($freshSaleNote);

        // Notificar al vendedor que creó la NV
        if ($saleNote->user_id && $saleNote->user_id !== auth()->id()) {
            $this->pushDispatchNotification($saleNote, LogisticStatusEnum::DESPACHADO);
        }

        $successMsg = "Pedido #{$saleNote->number_full} despachado. Courier: {$request->courier_name}.";
        if ($carrierApiMessage) {
            $successMsg .= " {$carrierApiMessage}";
        }

        return redirect()
            ->route('logistic.sale_notes.queue')
            ->with('success', $successMsg)
            ->with('guide_id', $guide?->id);
    }

    /**
     * LISTO_DESPACHO → RECOGIDO (cliente viene a recoger)
     * POST /logistic/sale-notes/queue/{saleNote}/pickup
     */
    public function confirmPickup(Request $request, SaleNote $saleNote): RedirectResponse
    {
        if ($redirect = $this->checkLogisticAccess()) return $redirect;
        $this->authorize('viewWarehouseQueue', LogisticOrder::class);

        $this->assertTransition($saleNote, LogisticStatusEnum::RECOGIDO);

        $request->validate([
            'pickup_person'   => 'nullable|string|max:150',
            'dispatch_date'   => 'required|date',
            'warehouse_notes' => 'nullable|string|max:500',
            'warehouse_overrides' => 'nullable|array',
            'warehouse_overrides.*.item_id'     => 'required_with:warehouse_overrides|integer',
            'warehouse_overrides.*.warehouse_id' => 'required_with:warehouse_overrides|integer',
        ]);

        DB::connection('tenant')->transaction(function () use ($request, $saleNote) {

            $this->applyWarehouseOverrides($request, $saleNote);
            $this->applyMainWarehouseDispatch($request, $saleNote, LogisticStatusEnum::RECOGIDO);

            $saleNote->update([
                'logistic_status'   => LogisticStatusEnum::RECOGIDO->value,
                'pickup_person'     => $request->pickup_person,
                'dispatch_date'     => $request->dispatch_date,
                'warehouse_user_id' => auth()->id(),
                'warehouse_notes'   => $request->warehouse_notes,
            ]);
        });

        // Notificar al vendedor que creó la NV
        if ($saleNote->user_id && $saleNote->user_id !== auth()->id()) {
            $this->pushDispatchNotification($saleNote, LogisticStatusEnum::RECOGIDO);
        }

        $name = $request->pickup_person ?? 'el cliente';
        return redirect()
            ->route('logistic.sale_notes.queue')
            ->with('success', "Pedido #{$saleNote->number_full} entregado a {$name}.");
    }

    /**
     * Aplica PROVINCE_DISPATCH para ítems que NO tienen warehouse_override (almacén principal).
     * Los ítems con override ya reciben su movimiento en applyWarehouseOverrides().
     */
    private function applyMainWarehouseDispatch(Request $request, SaleNote $saleNote, LogisticStatusEnum $status): void
    {
        $overrideItemIds = collect($request->input('warehouse_overrides', []))->pluck('item_id')->map('intval');

        $warehouseId = $saleNote->warehouse_id
            ?? Warehouse::where('establishment_id', $saleNote->establishment_id)->value('id');

        if (!$warehouseId) return;

        $saleNote->loadMissing('items');

        foreach ($saleNote->items as $item) {
            if ($overrideItemIds->contains((int) $item->item_id)) continue;

            $iw = ItemWarehouse::where('item_id',     $item->item_id)
                               ->where('warehouse_id', $warehouseId)
                               ->lockForUpdate()
                               ->first();

            if (!$iw || $item->quantity <= 0) continue;

            $iw->applyStockMovement(StockMovementTypeEnum::PROVINCE_DISPATCH, (float) $item->quantity);

            $label = $status === LogisticStatusEnum::RECOGIDO ? 'Retiro' : 'Despacho';
            StockMovement::record(
                $iw,
                StockMovementTypeEnum::PROVINCE_DISPATCH,
                (float) $item->quantity,
                auth()->id(),
                $saleNote,
                "{$label} NV #{$saleNote->number_full} — Almacén principal"
            );
        }
    }

    /**
     * Aplica movimientos de stock para almacenes alternativos seleccionados.
     */
    private function applyWarehouseOverrides(Request $request, SaleNote $saleNote): void
    {
        $overrides = collect($request->input('warehouse_overrides', []));

        if ($overrides->isEmpty()) return;

        // Sólo los item_id que realmente pertenecen a esta NV
        $validItemIds = $saleNote->items->pluck('item_id')->map('intval');

        foreach ($overrides as $override) {
            if (empty($override['warehouse_id'])) continue;
            if (!$validItemIds->contains((int) $override['item_id'])) continue;

            $iw = ItemWarehouse::where('item_id', $override['item_id'])
                ->where('warehouse_id', $override['warehouse_id'])
                ->lockForUpdate()
                ->first();

            if (!$iw) continue;

            $saleNoteItem = $saleNote->items->firstWhere('item_id', $override['item_id']);
            $qty = $saleNoteItem?->quantity ?? 0;

            if ($qty > 0) {
                $iw->applyStockMovement(StockMovementTypeEnum::PROVINCE_DISPATCH, $qty);

                StockMovement::record(
                    $iw,
                    StockMovementTypeEnum::PROVINCE_DISPATCH,
                    $qty,
                    auth()->id(),
                    $saleNote,
                    "Entrega NV #{$saleNote->number_full} — Almacén alternativo"
                );
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // ETIQUETA DE DESPACHO
    // ═══════════════════════════════════════════════════════════════

    /**
     * Retorna el HTML de la etiqueta listo para inyectar en la página (sin full HTML wrapper).
     * GET /logistic/sale-notes/queue/{saleNote}/label-html
     */
    public function labelHtml(SaleNote $saleNote)
    {
        if ($redirect = $this->checkLogisticAccess()) return $redirect;
        $this->authorize('viewWarehouseQueue', LogisticOrder::class);

        $saleNote->load([
            'customer:id,name,number,address',
            'user:id,name',
            'items.relation_item:id,description,internal_id',
        ]);

        return response()->view('tenant.logistic.dispatch_label_partial', compact('saleNote'))
            ->header('Content-Type', 'text/html');
    }

    /**
     * Impresión en lote: varias etiquetas en una sola página.
     * GET /logistic/sale-notes/queue/labels/batch?ids=1,2,3
     */
    public function printBatchLabels(Request $request)
    {
        if ($redirect = $this->checkLogisticAccess()) return $redirect;
        $this->authorize('viewWarehouseQueue', LogisticOrder::class);

        $ids = array_filter(
            array_map('intval', explode(',', $request->input('ids', '')))
        );

        if (empty($ids)) {
            abort(400, 'No se especificaron IDs de pedidos.');
        }

        $saleNotes = SaleNote::whereIn('id', $ids)
            ->with([
                'customer:id,name,number,address',
                'user:id,name',
                'items.relation_item:id,description,internal_id',
            ])
            ->get();

        return view('tenant.logistic.dispatch_labels_batch', compact('saleNotes'));
    }

    /**
     * Vista imprimible con datos del despacho (courier, guía, cliente, ítems).
     * GET /logistic/sale-notes/queue/{saleNote}/label
     */
    public function printLabel(SaleNote $saleNote)
    {
        if ($redirect = $this->checkLogisticAccess()) return $redirect;
        $this->authorize('viewWarehouseQueue', LogisticOrder::class);

        $saleNote->load([
            'customer:id,name,number,address',
            'user:id,name',
            'items.relation_item:id,description,internal_id',
        ]);

        return view('tenant.logistic.dispatch_label', compact('saleNote'));
    }

    // ═══════════════════════════════════════════════════════════════
    // API: STOCK DISPONIBLE POR ÍTEM
    // ═══════════════════════════════════════════════════════════════

    /**
     * Devuelve el stock disponible de un ítem en todos los almacenes del tenant.
     * GET /api/logistic/sale-notes/stock-by-item/{item}
     */
    public function stockByItem(Item $item): JsonResponse
    {
        $this->authorize('viewWarehouseQueue', LogisticOrder::class);
        $stocks = ItemWarehouse::where('item_id', $item->id)
            ->with('warehouse:id,description')
            ->get()
            ->map(fn($iw) => [
                'warehouse_id'    => $iw->warehouse_id,
                'warehouse_name'  => $iw->warehouse?->description ?? 'Sin nombre',
                'stock_physical'  => $iw->stock_physical,
                'stock_committed' => $iw->stock_committed,
                'stock_available' => $iw->stock_available,
            ]);

        return response()->json([
            'success' => true,
            'item'    => ['id' => $item->id, 'description' => $item->description],
            'stocks'  => $stocks,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // HELPERS PRIVADOS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Guarda una notificación en caché para que el vendedor la vea
     * la próxima vez que cargue una página del sistema.
     * Usa caché (no sesión) para que sea accesible entre sesiones distintas.
     */
    private function pushDispatchNotification(SaleNote $saleNote, LogisticStatusEnum $status): void
    {
        $tenantUuid = app(\Hyn\Tenancy\Environment::class)->tenant()?->uuid ?? 'default';
        $key        = "dispatch_notifications_{$tenantUuid}_{$saleNote->user_id}";

        $current   = \Illuminate\Support\Facades\Cache::get($key, []);
        $current[] = [
            'type'         => $status->value,
            'number'       => $saleNote->number_full,
            'customer'     => $saleNote->customer->name ?? '—',
            'courier'      => $saleNote->courier_name,
            'tracking'     => $saleNote->tracking_number,
            'pickup_person'=> $saleNote->pickup_person,
            'dispatched_at'=> now()->format('d/m/Y H:i'),
        ];

        \Illuminate\Support\Facades\Cache::put($key, $current, now()->addHours(24));
    }

    /**
     * Valida que la transición de estado sea permitida.
     *
     * @throws InvalidOrderTransitionException
     */
    private function assertTransition(SaleNote $saleNote, LogisticStatusEnum $newStatus): void
    {
        if (!$saleNote->logistic_status) {
            throw new InvalidOrderTransitionException(
                "La Nota de Venta #{$saleNote->number_full} no tiene estado logístico asignado."
            );
        }

        if (!$saleNote->logistic_status->canTransitionTo($newStatus)) {
            throw new InvalidOrderTransitionException(
                "No se puede pasar de '{$saleNote->logistic_status->label()}' a '{$newStatus->label()}'"
            );
        }
    }

    // ─── Guía de Remisión ─────────────────────────────────────────────────────

    /**
     * Genera el PDF de la Guía de Remisión para una SaleNote despachada.
     * Crea el registro LogisticShippingGuide y almacena el PDF.
     */
    private function generateGuideForSaleNote(SaleNote $saleNote): ?LogisticShippingGuide
    {
        try {
            $saleNote->load(['items.relation_item', 'person']);
            $company = \App\Models\Tenant\Company::first();

            $guide = LogisticShippingGuide::create([
                'sale_note_id'        => $saleNote->id,
                'carrier_name'        => $saleNote->courier_name,
                'tracking_code'       => $saleNote->tracking_number,
                'origin_address'      => optional($saleNote->establishment)->address ?? '',
                'destination_address' => $saleNote->shipping_address ?? '',
                'destination_ubigeo'  => $saleNote->shipping_district_id ?? '',
                'dispatch_date'       => $saleNote->dispatch_date ?? now(),
                'issued_by'           => auth()->id(),
                'status'              => 'generated',
            ]);

            // $company ya fue cargado arriba

            $html = view('tenant.logistic.shipping_guide_nv_pdf', compact('saleNote', 'guide', 'company'))->render();

            $pdf  = Pdf::loadHTML($html)->setPaper('a4');
            $uuid = app(\Hyn\Tenancy\Environment::class)->tenant()?->uuid ?? 'default';
            $path = "tenants/{$uuid}/shipping_guides/guide_{$guide->id}.pdf";

            Storage::put($path, $pdf->output());
            $guide->update(['pdf_path' => $path]);

            return $guide;

        } catch (\Throwable $e) {
            Log::error('[WarehouseDispatch] Error generando guía PDF: ' . $e->getMessage());
            return null;
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // ANULACIÓN DE DESPACHO
    // ═══════════════════════════════════════════════════════════════

    /**
     * DESPACHADO → ANULADO: revierte el stock físico y comprometido.
     * POST /logistic/sale-notes/queue/{saleNote}/annul
     */
    public function annulDispatch(Request $request, SaleNote $saleNote): RedirectResponse
    {
        if ($redirect = $this->checkLogisticAccess()) return $redirect;
        $this->authorize('viewWarehouseQueue', LogisticOrder::class);

        $this->assertTransition($saleNote, LogisticStatusEnum::ANULADO);

        $request->validate([
            'annul_reason' => 'required|string|max:500',
        ]);

        DB::connection('tenant')->transaction(function () use ($request, $saleNote) {

            $saleNote->load('items');

            // Almacén por defecto del establecimiento (fallback si el ítem no tiene warehouse_id)
            $defaultWarehouseId = \Modules\Inventory\Models\Warehouse::where('establishment_id', $saleNote->establishment_id)
                ->value('id');

            foreach ($saleNote->items as $item) {
                $qty         = (float) ($item->quantity ?? 0);
                $warehouseId = $item->warehouse_id ?? $defaultWarehouseId;

                if ($qty <= 0 || !$warehouseId) continue;

                $iw = ItemWarehouse::where('item_id', $item->item_id)
                    ->where('warehouse_id', $warehouseId)
                    ->lockForUpdate()
                    ->first();

                if (!$iw) continue;

                // Solo aplicar DISPATCH_ANNUL si hubo un PROVINCE_DISPATCH registrado
                // (es decir, si el despacho usó warehouse_overrides y redujo stock_physical).
                // Para despachos normales sin overrides, PROVINCE_DISPATCH nunca se aplicó
                // y aplicar DISPATCH_ANNUL inflaría stock_physical incorrectamente.
                $wasPhysicallyDispatched = StockMovement::where('reference_type', SaleNote::class)
                    ->where('reference_id', $saleNote->id)
                    ->where('item_id', $item->item_id)
                    ->where('type', StockMovementTypeEnum::PROVINCE_DISPATCH->value)
                    ->exists();

                if ($wasPhysicallyDispatched) {
                    $iw->applyStockMovement(StockMovementTypeEnum::DISPATCH_ANNUL, $qty);
                    StockMovement::record(
                        $iw,
                        StockMovementTypeEnum::DISPATCH_ANNUL,
                        $qty,
                        auth()->id(),
                        $saleNote,
                        "Anulación despacho NV #{$saleNote->number_full}: {$request->annul_reason}"
                    );
                }

                // Siempre liberar la reserva committed (PROVINCE_COMMIT se aplica al crear la NV)
                $iw->applyStockMovement(StockMovementTypeEnum::PROVINCE_CANCEL, $qty);
                StockMovement::record(
                    $iw,
                    StockMovementTypeEnum::PROVINCE_CANCEL,
                    $qty,
                    auth()->id(),
                    $saleNote,
                    "Libera reserva committed - anulación {$saleNote->number_full}"
                );
            }

            $notes = $saleNote->warehouse_notes
                ? $saleNote->warehouse_notes . ' | ANULADO: ' . $request->annul_reason
                : 'ANULADO: ' . $request->annul_reason;

            $saleNote->update([
                'logistic_status' => LogisticStatusEnum::ANULADO->value,
                'warehouse_notes' => $notes,
            ]);
        });

        return redirect()
            ->route('logistic.sale_notes.history')
            ->with('success', "Despacho #{$saleNote->number_full} anulado. Stock revertido correctamente.");
    }

    /**
     * Descarga el PDF de una guía de remisión.
     * GET /logistic/sale-notes/shipping-guide/{guide}/pdf
     */
    public function downloadGuidePdf(LogisticShippingGuide $guide)
    {
        if ($redirect = $this->checkLogisticAccess()) return $redirect;
        $this->authorize('viewWarehouseQueue', LogisticOrder::class);

        if (!$guide->pdf_path || !Storage::exists($guide->pdf_path)) {
            abort(404, 'PDF no disponible.');
        }

        return response(Storage::get($guide->pdf_path), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="guia-remision-' . $guide->id . '.pdf"',
        ]);
    }
}
