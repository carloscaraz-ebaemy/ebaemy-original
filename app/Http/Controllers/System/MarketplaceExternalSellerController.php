<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\System\MarketplaceExternalSeller;
use App\Models\System\MarketplaceListing;
use Illuminate\Http\Request;

/**
 * Tiendas de PRO 9 que piden vender en el marketplace
 * (/admin/marketplace/tiendas-externas). Se registran solas con el botón
 * "Publicar en ebaemy.com" de PRO 9 y quedan por aprobar; aquí se aprueban,
 * rechazan o suspenden. Aprobar destapa de una vez los productos que ya
 * enviaron.
 */
class MarketplaceExternalSellerController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $sellers = MarketplaceExternalSeller::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByRaw("FIELD(status, 'pending', 'approved', 'suspended', 'rejected')")
            ->orderByDesc('id')
            ->paginate(30)->withQueryString();

        $counts = MarketplaceExternalSeller::query()
            ->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        // Productos enviados por tienda (activos = los que el vendedor dejó a la venta).
        $products = MarketplaceListing::query()
            ->whereIn('hostname_id', $sellers->pluck('hostname_id'))
            ->selectRaw("hostname_id, COUNT(*) as total, SUM(status = 'active') as activos")
            ->groupBy('hostname_id')->get()->keyBy('hostname_id');

        return view('system.marketplace.external_sellers', compact('sellers', 'counts', 'status', 'products'));
    }

    public function update(Request $request, int $id)
    {
        $seller = MarketplaceExternalSeller::findOrFail($id);
        $data = $request->validate([
            'status'      => 'required|in:' . implode(',', array_keys(MarketplaceExternalSeller::STATUSES)),
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $seller->changeStatus($data['status'], $data['admin_notes'] ?? null);

        return back()->with('ok', $seller->name . ': ' . $seller->statusLabel() . '.');
    }
}
