<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\System\MarketplaceAdRequest;
use Illuminate\Http\Request;

/**
 * Bandeja de solicitudes de publicidad de las tiendas (/admin/marketplace/publicidad).
 * Las crea Tenant\AdRequestController desde la Tienda virtual.
 */
class MarketplaceAdRequestController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $requests = MarketplaceAdRequest::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByRaw("FIELD(status, 'solicitada', 'pagada', 'activa', 'terminada', 'rechazada')")
            ->orderByDesc('id')
            ->paginate(30)->withQueryString();

        $counts = MarketplaceAdRequest::query()
            ->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('system.marketplace.ads', [
            'requests' => $requests,
            'counts'   => $counts,
            'status'   => $status,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $r = MarketplaceAdRequest::findOrFail($id);
        $d = $request->validate([
            'status'      => 'required|in:' . implode(',', array_keys(MarketplaceAdRequest::STATUSES)),
            'spent'       => 'nullable|numeric|min:0|max:100000',
            'admin_notes' => 'nullable|string|max:500',
        ]);

        // Las fechas se marcan solas al cambiar de estado: el dueño ve cuándo
        // empezó y terminó su campaña sin que nadie las escriba.
        if ($d['status'] === 'activa' && !$r->starts_at) {
            $d['starts_at'] = now();
        }
        if (in_array($d['status'], ['terminada', 'rechazada'], true) && !$r->ends_at) {
            $d['ends_at'] = now();
        }
        $r->update($d);

        return back()->with('ok', 'Solicitud #' . $r->id . ' de ' . $r->store_name . ': ' . $r->statusLabel() . '.');
    }
}
