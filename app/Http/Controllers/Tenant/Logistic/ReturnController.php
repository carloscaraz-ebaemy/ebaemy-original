<?php

namespace App\Http\Controllers\Tenant\Logistic;

use App\Http\Controllers\Controller;
use App\Models\Tenant\LogisticReturn;
use App\Models\Tenant\LogisticReturnItem;
use App\Models\Tenant\ItemWarehouse;
use App\Models\Tenant\SaleNote;
use App\Models\Tenant\Warehouse;
use App\Models\Tenant\StockMovement;
use App\Enums\StockMovementTypeEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReturnController extends Controller
{
    /**
     * Lista de devoluciones.
     */
    public function index(Request $request)
    {
        $query = LogisticReturn::with(['saleNote', 'warehouse', 'user', 'items'])
            ->orderByDesc('created_at');

        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->search) {
            $query->whereHas('saleNote', function ($q) use ($request) {
                $q->where('series', 'like', "%{$request->search}%")
                  ->orWhere('number', 'like', "%{$request->search}%");
            })->orWhere('tracking_number', 'like', "%{$request->search}%");
        }

        $returns    = $query->paginate(20)->withQueryString();
        $warehouses = Warehouse::orderBy('description')->get();
        $counters   = [
            'pendiente' => LogisticReturn::where('status', 'PENDIENTE')->count(),
            'recibido'  => LogisticReturn::where('status', 'RECIBIDO')->count(),
            'procesado' => LogisticReturn::where('status', 'PROCESADO')->count(),
        ];

        return view('tenant.logistic.returns.index', compact('returns', 'warehouses', 'counters'));
    }

    /**
     * Formulario de nueva devolución.
     */
    public function create(Request $request)
    {
        $warehouses = Warehouse::orderBy('description')->get();
        $reasons    = LogisticReturn::reasons();
        $saleNote   = null;

        if ($request->sale_note_id) {
            $saleNote = SaleNote::with(['items.relation_item', 'person'])
                ->find($request->sale_note_id);
        }

        return view('tenant.logistic.returns.create', compact('warehouses', 'reasons', 'saleNote'));
    }

    /**
     * Busca una nota de venta por número de documento o tracking.
     */
    public function searchOrder(Request $request)
    {
        $q = trim($request->input('q', ''));

        if (str_contains($q, '-')) {
            [$series, $number] = explode('-', $q, 2);
            $saleNote = SaleNote::where('series', trim($series))
                ->where('number', (int) trim($number))
                ->with(['items.relation_item', 'person'])
                ->first();
        } else {
            $saleNote = SaleNote::where('tracking_number', $q)
                ->orWhere('number', (int) $q)
                ->with(['items.relation_item', 'person'])
                ->first();
        }

        if (!$saleNote) {
            return response()->json(['error' => 'Pedido no encontrado'], 404);
        }

        return response()->json([
            'id'          => $saleNote->id,
            'number_full' => $saleNote->number_full,
            'customer'    => optional($saleNote->person)->name,
            'status'      => optional($saleNote->logistic_status)?->label(),
            'items'       => $saleNote->items->map(fn($i) => [
                'item_id'     => $i->item_id,
                'description' => optional($i->relation_item)->description,
                'quantity'    => $i->quantity,
                'unit_price'  => $i->unit_price,
            ]),
        ]);
    }

    /**
     * Registra la devolución (estado: RECIBIDO).
     */
    public function store(Request $request)
    {
        $request->validate([
            'warehouse_id'           => 'required|integer',
            'reason'                 => 'required|string',
            'items'                  => 'required|array|min:1',
            'items.*.item_id'        => 'required|integer',
            'items.*.quantity'       => 'required|numeric|min:0.01',
            'items.*.condition'      => 'required|in:BUENO,DANADO,PARCIAL',
            'items.*.unit_price'     => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($request) {
            $return = LogisticReturn::create([
                'sale_note_id'    => $request->sale_note_id ?: null,
                'warehouse_id'    => $request->warehouse_id,
                'user_id'         => auth()->id(),
                'status'          => 'RECIBIDO',
                'reason'          => $request->reason,
                'courier_name'    => $request->courier_name,
                'tracking_number' => $request->tracking_number,
                'notes'           => $request->notes,
                'received_at'     => now(),
            ]);

            foreach ($request->items as $itemData) {
                $qtyRestocked = $itemData['condition'] === 'BUENO'
                    ? (float) $itemData['quantity']
                    : ($itemData['condition'] === 'PARCIAL' ? (float) ($itemData['quantity_restocked'] ?? 0) : 0);

                LogisticReturnItem::create([
                    'logistic_return_id' => $return->id,
                    'item_id'            => $itemData['item_id'],
                    'warehouse_id'       => $request->warehouse_id,
                    'quantity_returned'  => $itemData['quantity'],
                    'quantity_restocked' => $qtyRestocked,
                    'condition'          => $itemData['condition'],
                    'unit_price'         => $itemData['unit_price'],
                    'notes'              => $itemData['notes'] ?? null,
                ]);
            }
        });

        return redirect()->route('logistic.returns.index')
            ->with('success', 'Devolución registrada correctamente.');
    }

    /**
     * Detalle de una devolución.
     */
    public function show(LogisticReturn $return)
    {
        $return->load(['saleNote.person', 'warehouse', 'user', 'items.item']);
        return view('tenant.logistic.returns.show', compact('return'));
    }

    /**
     * Procesa la devolución: reingresa stock de ítems en buen estado.
     */
    public function process(LogisticReturn $return)
    {
        if ($return->isProcesado()) {
            return back()->with('error', 'Esta devolución ya fue procesada.');
        }

        DB::transaction(function () use ($return) {
            foreach ($return->items as $returnItem) {
                if ($returnItem->quantity_restocked > 0) {
                    $iw = ItemWarehouse::where('item_id', $returnItem->item_id)
                        ->where('warehouse_id', $returnItem->warehouse_id)
                        ->lockForUpdate()
                        ->first();

                    if ($iw) {
                        $iw->applyStockMovement(StockMovementTypeEnum::RETURN_RESTOCK, $returnItem->quantity_restocked);

                        StockMovement::record(
                            $iw,
                            StockMovementTypeEnum::RETURN_RESTOCK,
                            $returnItem->quantity_restocked,
                            auth()->id(),
                            $return,
                            "Devolución #{$return->id} — {$return->reasonLabel()}"
                        );
                    }
                }

                // Registro de dañados (sin movimiento de stock)
                if ($returnItem->condition !== 'BUENO' &&
                    $returnItem->quantity_returned > $returnItem->quantity_restocked) {
                    $damaged = $returnItem->quantity_returned - $returnItem->quantity_restocked;
                    $iw = ItemWarehouse::where('item_id', $returnItem->item_id)
                        ->where('warehouse_id', $returnItem->warehouse_id)
                        ->first();

                    if ($iw) {
                        StockMovement::record(
                            $iw,
                            StockMovementTypeEnum::RETURN_DAMAGED,
                            $damaged,
                            auth()->id(),
                            $return,
                            "Devolución #{$return->id} — producto dañado"
                        );
                    }
                }
            }

            $return->update([
                'status'       => 'PROCESADO',
                'processed_at' => now(),
            ]);
        });

        return back()->with('success', 'Devolución procesada. Stock actualizado correctamente.');
    }
}
