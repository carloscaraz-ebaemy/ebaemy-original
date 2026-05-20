<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\System\PushSubscription;
use Illuminate\Http\Request;

/**
 * Gestiona las suscripciones Web Push de compradores del marketplace.
 *
 * Flujo:
 *  1. Frontend pide permiso → PushManager.subscribe() con la VAPID public key
 *  2. POST /marketplace/push/subscribe con la subscription serializada
 *  3. Backend la guarda (idempotente por endpoint)
 *  4. WebPushService envía notificaciones a las suscripciones guardadas
 */
class PushSubscriptionController extends Controller
{
    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'endpoint'        => ['required', 'string'],
            'keys'            => ['required', 'array'],
            'keys.p256dh'     => ['required', 'string'],
            'keys.auth'       => ['required', 'string'],
            'contentEncoding' => ['nullable', 'string'],
        ]);

        // Asociar al comprador logueado si hay sesión marketplace
        $userId = auth('marketplace')->id();

        $sub = PushSubscription::store(
            $data,
            $userId,
            substr((string) $request->userAgent(), 0, 255)
        );

        return response()->json(['success' => true, 'id' => $sub->id]);
    }

    public function unsubscribe(Request $request)
    {
        $endpoint = $request->input('endpoint');
        if ($endpoint) {
            PushSubscription::where('endpoint_hash', hash('sha256', $endpoint))->delete();
        }
        return response()->json(['success' => true]);
    }

    /**
     * Devuelve la VAPID public key para que el frontend la use en subscribe().
     * Es pública por diseño (la privada nunca sale del server).
     */
    public function publicKey()
    {
        return response()->json([
            'success' => true,
            'key'     => config('webpush.public_key') ?: env('VAPID_PUBLIC_KEY', ''),
        ]);
    }
}
