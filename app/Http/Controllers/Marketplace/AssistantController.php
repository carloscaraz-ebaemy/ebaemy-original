<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Services\Marketplace\ShoppingAssistant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Chat del asistente de compras (burbuja del marketplace).
 *
 *  GET  /marketplace/asistente/estado  → si está encendido; la burbuja solo
 *       aparece si sí. Las páginas se cachean iguales para todos, por eso no
 *       se decide en el Blade.
 *  POST /marketplace/asistente         → { message, history[] } → { text, products[] }
 *
 * Cada mensaje cuesta una llamada a la API: va con throttle por IP en la
 * ruta, y el texto y el historial se recortan aquí antes de llegar al modelo.
 */
class AssistantController extends Controller
{
    public function status()
    {
        return response()
            ->json(['enabled' => ShoppingAssistant::isAvailable()])
            ->header('Cache-Control', 'public, max-age=300');
    }

    public function message(Request $request, ShoppingAssistant $assistant)
    {
        if (!ShoppingAssistant::isAvailable()) {
            return response()->json(['error' => 'El asistente no está disponible.'], 503);
        }

        $data = $request->validate([
            'message'        => 'required|string|max:500',
            'history'        => 'sometimes|array|max:20',
            'history.*.role' => 'required_with:history|string|in:user,assistant',
            'history.*.text' => 'required_with:history|string|max:2000',
        ]);

        // Tope diario por IP además del throttle por minuto de la ruta: un
        // script puede gastar 12/min sin pausa, una persona no llega a 80/día.
        $clave = 'mp-assistant-day:' . $request->ip();
        if (RateLimiter::tooManyAttempts($clave, 80)) {
            return response()->json([
                'text'     => 'Llegaste al límite de consultas de hoy. Puedes seguir usando el buscador de arriba.',
                'products' => [],
            ]);
        }
        RateLimiter::hit($clave, 86400);

        $buyer = auth('marketplace')->user();

        return response()
            ->json($assistant->reply($data['history'] ?? [], $data['message'], $buyer?->id))
            ->header('Cache-Control', 'no-store, private');
    }
}
