<?php

namespace App\Http\Controllers;

use App\Services\System\MarketplaceCartService;
use Illuminate\Http\Request;

/**
 * Endpoints REST + vista del carrito multi-tienda del marketplace central.
 * Persiste en sesión (no toca BD) hasta que el cliente confirma checkout.
 */
class MarketplaceCartController extends Controller
{
    public function __construct(private MarketplaceCartService $cart) {}

    /**
     * GET /marketplace/cart — vista HTML agrupada por tienda.
     */
    public function show()
    {
        $this->cart->refresh();
        $stores  = $this->cart->groupedByStore();
        $summary = $this->cart->summary();

        // Si el carrito está vacío, mostramos "Vistos recientemente" como
        // path de recovery: el comprador que abandonó algo en una sesión
        // anterior puede retomarlo desde aquí.
        $recentlyViewed = collect();
        if ($stores->isEmpty()) {
            $recentlyViewed = app(\App\Services\Marketplace\RecentlyViewedService::class)
                ->listings(null, 8);
        }

        return view('marketplace.cart', compact('stores', 'summary', 'recentlyViewed'));
    }

    /**
     * GET /marketplace/cart/json — para badge del nav y mini-cart.
     */
    public function summary()
    {
        return response()->json($this->cart->summary());
    }

    /**
     * GET /marketplace/cart/mini — detalle compacto para el mini-cart drawer
     * del navbar. Items agrupados por tienda + summary.
     */
    public function mini()
    {
        return response()->json($this->cart->miniDetails());
    }

    /**
     * POST /marketplace/cart — añadir producto.
     * Body: slug (string, required), quantity (int, default 1)
     */
    public function add(Request $request)
    {
        $data = $request->validate([
            'slug'     => 'required|string|max:250',
            'quantity' => 'nullable|integer|min:1|max:99',
        ]);

        $line = $this->cart->add($data['slug'], (int) ($data['quantity'] ?? 1));

        if ($line === null) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no disponible.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Producto añadido al carrito',
            'line'    => $line,
            'summary' => $this->cart->summary(),
        ]);
    }

    /**
     * POST /marketplace/cart/bulk-add — agrega varios productos al carrito
     * de una sola vez. Pensado para el flujo de seleccion multiple desde
     * el listado de ofertas (modo seleccion). Body: { listing_ids: [int,...] }
     *
     * Solo procesa items SIN variantes y con stock — los que requieren
     * elegir opcion se devuelven en `skipped` para que el front avise.
     */
    public function bulkAdd(Request $request)
    {
        $data = $request->validate([
            'listing_ids'   => 'required|array|max:50',
            'listing_ids.*' => 'integer|min:1',
        ]);

        $listings = \App\Models\System\MarketplaceListing::published()
            ->whereIn('id', $data['listing_ids'])
            ->get();

        $added   = [];
        $skipped = [];

        foreach ($listings as $listing) {
            if (!empty($listing->has_variants) || !empty($listing->is_pack)) {
                $skipped[] = [
                    'id'     => $listing->id,
                    'slug'   => $listing->slug,
                    'title'  => $listing->title,
                    'reason' => 'requires_variant',
                ];
                continue;
            }
            $line = $this->cart->add($listing->slug, 1);
            if ($line === null) {
                $skipped[] = [
                    'id'     => $listing->id,
                    'slug'   => $listing->slug,
                    'title'  => $listing->title,
                    'reason' => 'unavailable',
                ];
                continue;
            }
            $added[] = [
                'id'    => $listing->id,
                'slug'  => $listing->slug,
                'title' => $listing->title,
            ];
        }

        return response()->json([
            'success'      => true,
            'added_count'  => count($added),
            'added'        => $added,
            'skipped'      => $skipped,
            'summary'      => $this->cart->summary(),
        ]);
    }

    /**
     * PATCH /marketplace/cart/{listing} — fija cantidad (0 elimina).
     */
    public function update(Request $request, int $listing)
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:0|max:99',
        ]);

        $ok = $this->cart->setQuantity($listing, (int) $data['quantity']);

        if (!$ok) {
            return response()->json([
                'success' => false,
                'message' => 'El producto no estaba en el carrito',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'summary' => $this->cart->summary(),
        ]);
    }

    /**
     * DELETE /marketplace/cart/{listing} — elimina producto del carrito.
     */
    public function destroy(int $listing)
    {
        $this->cart->remove($listing);
        return response()->json([
            'success' => true,
            'summary' => $this->cart->summary(),
        ]);
    }

    /**
     * DELETE /marketplace/cart — vaciar carrito.
     */
    public function clear()
    {
        $this->cart->clear();
        return response()->json([
            'success' => true,
            'summary' => $this->cart->summary(),
        ]);
    }
}
