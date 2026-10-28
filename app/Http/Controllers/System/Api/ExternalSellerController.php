<?php

namespace App\Http\Controllers\System\Api;

use App\Http\Controllers\Controller;
use App\Models\System\MarketplaceCategory;
use App\Models\System\MarketplaceExternalSeller;
use App\Services\System\ExternalListingUpsertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * API del marketplace para vendedores externos (PRO 9). Autenticada por
 * VerifyExternalSellerSignature; ver docs/marketplace-vendedores-externos.md.
 */
class ExternalSellerController extends Controller
{
    public function ping(Request $request)
    {
        $seller = $this->seller($request);

        return [
            'ok'           => true,
            'seller'       => $seller->name,
            'status'       => $seller->status,
            'status_label' => $seller->statusLabel(),
            'admin_notes'  => $seller->status === 'approved' ? null : $seller->admin_notes,
            'store_url'    => url('marketplace/tienda/' . strtok($seller->fqdn, '.')),
        ];
    }

    /**
     * Una tienda del sistema externo pide vender en el marketplace. Autenticado
     * con la llave de PLATAFORMA (VerifyMarketplacePlatformSignature), no de
     * tienda: devuelve la clave y el secreto propios de la tienda, que queda
     * por aprobar. Repetirlo renueva el secreto (el sistema guarda el nuevo).
     */
    public function register(Request $request, \App\Services\System\ExternalSellerRegistrar $registrar)
    {
        $data = validator($request->json()->all(), [
            'fqdn'        => 'required|string|max:180',
            'name'        => 'required|string|max:160',
            'webhook_url' => 'required|url|max:255',
            'phone'       => 'nullable|string|max:30',
            'email'       => 'nullable|email|max:150',
        ])->validate();

        // Solo dominios del propio sistema: una tienda de PRO 9 es un subdominio
        // de devaemy.com, y su webhook vive en ese mismo dominio.
        $domain = strtolower((string) ($request->attributes->get('marketplace_platform_config')['domain'] ?? ''));
        $fqdn = strtolower(trim($data['fqdn']));
        $webhookHost = strtolower((string) parse_url($data['webhook_url'], PHP_URL_HOST));
        if ($domain === '' || !str_ends_with($fqdn, '.' . $domain) || $webhookHost !== $fqdn) {
            return response()->json(['message' => 'Dominio no permitido para esta plataforma'], 422);
        }

        try {
            $result = $registrar->register($fqdn, $data['name'], $data['webhook_url'], [
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $seller = $result['seller'];

        return [
            'ok'           => true,
            'api_key'      => $seller->api_key,
            'api_secret'   => $result['secret'],
            'status'       => $seller->status,
            'status_label' => $seller->statusLabel(),
            'store_url'    => url('marketplace/tienda/' . strtok($seller->fqdn, '.')),
        ];
    }

    /** Árbol de categorías del marketplace, para que el vendedor elija dónde publicar. */
    public function categories()
    {
        return [
            'data' => MarketplaceCategory::where('is_active', true)
                ->orderBy('level')->orderBy('sort_order')->orderBy('id')
                ->get(['id', 'parent_id', 'level', 'depth_path', 'name', 'slug', 'full_slug', 'icon',
                       'is_leaf', 'allow_seller_publish', 'is_visible_in_marketplace', 'sort_order']),
        ];
    }

    public function upsert(Request $request, int $remoteItemId, ExternalListingUpsertService $service)
    {
        $seller = $this->seller($request);

        try {
            $listing = $service->upsert($seller, $remoteItemId, $request->json()->all());
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Producto inválido', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('ExternalSeller upsert falló', [
                'seller_id' => $seller->id, 'remote_item_id' => $remoteItemId, 'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'No se pudo guardar el producto'], 500);
        }

        return [
            'ok'           => true,
            'listing_id'   => $listing->id,
            'url'          => url('marketplace/item/' . $listing->slug),
            'has_category' => (bool) $listing->marketplace_category_id,
        ];
    }

    /**
     * Lote de hasta 50 productos en una sola petición (el reenvío masivo del
     * vendedor), para no chocar con el límite de 60 peticiones por minuto.
     * Cada elemento: {remote_item_id, publishable, payload}. Un producto
     * inválido no frena al resto: el resultado va por elemento.
     */
    public function batch(Request $request, ExternalListingUpsertService $service)
    {
        $seller = $this->seller($request);
        $items = (array) $request->json('items', []);
        if (count($items) === 0 || count($items) > 50) {
            return response()->json(['message' => 'El lote debe tener entre 1 y 50 productos'], 422);
        }

        $results = [];
        foreach ($items as $entry) {
            $remoteItemId = (int) ($entry['remote_item_id'] ?? 0);
            if ($remoteItemId <= 0) {
                $results[] = ['remote_item_id' => $remoteItemId, 'ok' => false, 'message' => 'remote_item_id inválido'];
                continue;
            }
            try {
                if (empty($entry['publishable'])) {
                    $service->pause($seller, $remoteItemId);
                    $results[] = ['remote_item_id' => $remoteItemId, 'ok' => true, 'paused' => true];
                } else {
                    $listing = $service->upsert($seller, $remoteItemId, (array) ($entry['payload'] ?? []));
                    $results[] = ['remote_item_id' => $remoteItemId, 'ok' => true, 'listing_id' => $listing->id];
                }
            } catch (ValidationException $e) {
                $results[] = ['remote_item_id' => $remoteItemId, 'ok' => false, 'message' => 'Producto inválido', 'errors' => $e->errors()];
            } catch (\Throwable $e) {
                Log::error('ExternalSeller batch falló', [
                    'seller_id' => $seller->id, 'remote_item_id' => $remoteItemId, 'error' => $e->getMessage(),
                ]);
                $results[] = ['remote_item_id' => $remoteItemId, 'ok' => false, 'message' => 'No se pudo guardar el producto'];
            }
        }

        return ['ok' => true, 'results' => $results];
    }

    public function destroy(Request $request, int $remoteItemId, ExternalListingUpsertService $service)
    {
        return ['ok' => true, 'paused' => $service->pause($this->seller($request), $remoteItemId)];
    }

    /**
     * El vendedor informa el avance de su subpedido: shipped | delivered | cancelled.
     * Nunca retrocede. 'shipped' se acepta pero no cambia el subpedido: ebaemy
     * solo distingue repartido al vendedor (dispatched) y entregado.
     */
    public function orderStatus(Request $request, string $orderNumber)
    {
        $seller = $this->seller($request);
        $status = (string) $request->json('status');
        if (!in_array($status, ['shipped', 'delivered', 'cancelled'], true)) {
            return response()->json(['message' => 'Estado inválido'], 422);
        }

        $order = \App\Models\System\MarketplaceOrder::where('order_number', $orderNumber)->first();
        $sub = $order
            ? \App\Models\System\TenantMarketplaceOrder::where('marketplace_order_id', $order->id)
                ->where('hostname_id', $seller->hostname_id)->first()
            : null;
        if (!$sub) {
            return response()->json(['message' => 'Pedido no encontrado'], 404);
        }

        $rank = ['pending' => 0, 'failed' => 0, 'dispatched' => 1, 'delivered' => 2, 'cancelled' => 2];
        $target = $status === 'shipped' ? 'dispatched' : $status;
        if (($rank[$target] ?? 0) <= ($rank[$sub->status] ?? 0)) {
            return ['ok' => true, 'status' => $sub->status, 'changed' => false];
        }

        $sub->update(['status' => $target]);
        $order->refresh()->recomputeStatus();

        // Snapshot de "mis pedidos" del comprador (mismo mapa que SyncTenantOrderStates).
        if ($order->marketplace_user_id && in_array($target, ['delivered', 'cancelled'], true)) {
            $snapshot = \App\Models\System\MarketplaceUserOrder::where('hostname_id', $seller->hostname_id)
                ->where('order_id', $sub->tenant_order_id)->first();
            \App\Jobs\Marketplace\PushOrderToSystem::dispatch(
                (int) $order->marketplace_user_id,
                (int) $seller->hostname_id,
                (int) $sub->tenant_order_id,
                (float) ($snapshot->total ?? $order->total),
                $snapshot->currency ?? 'PEN',
                $target === 'delivered' ? 'completed' : 'cancelled',
                optional($snapshot->confirmed_at ?? null)->toDateTimeString(),
                $target === 'cancelled' ? now()->toDateTimeString() : null,
                (int) ($snapshot->items_count ?? 0),
                $snapshot->product_categories ?? [],
            )->afterResponse();
        }

        return ['ok' => true, 'status' => $target, 'changed' => true];
    }

    private function seller(Request $request): MarketplaceExternalSeller
    {
        return $request->attributes->get('external_seller');
    }
}
