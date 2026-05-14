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
