<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Services\Marketplace\ShoppingAssistant;
use App\Services\Marketplace\ShoppingAssistantLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Chat del asistente de compras (burbuja del marketplace).
 *
 *  GET  /marketplace/asistente/estado    → si está encendido; la burbuja solo
 *       aparece si sí. Las páginas se cachean iguales para todos, por eso no
 *       se decide en el Blade.
 *  POST /marketplace/asistente           → { message, history[], session, context{url,slug} }
 *       Con `Accept: text/event-stream` responde en vivo: eventos `status`
 *       («Buscando «cojín»…») mientras corre cada herramienta y un `done`
 *       con la respuesta. Sin ese header, JSON de una vez (compatibilidad).
 *  POST /marketplace/asistente/opinion   → { session, message_id, value: 1|-1 }
 *  POST /marketplace/asistente/evento    → { session, type, slug?, message_id? }
 *  GET  /marketplace/asistente/historial → la última conversación del comprador conectado
 *
 * Cada mensaje cuesta una llamada a la API: va con throttle por IP en la
 * ruta, tope diario por IP aquí y tope diario global de tokens en el servicio.
 */
class AssistantController extends Controller
{
    private const SESSION_RULE = 'required|string|regex:/^[A-Za-z0-9\-]{8,64}$/';

    public function status()
    {
        return response()
            ->json(['enabled' => ShoppingAssistant::isAvailable()])
            ->header('Cache-Control', 'public, max-age=300');
    }

    public function message(Request $request, ShoppingAssistant $assistant, ShoppingAssistantLog $log)
    {
        if (!ShoppingAssistant::isAvailable()) {
            return response()->json(['error' => 'El asistente no está disponible.'], 503);
        }

        $data = $request->validate([
            'message'        => 'required|string|max:500',
            'session'        => self::SESSION_RULE,
            'history'        => 'sometimes|array|max:20',
            'history.*.role' => 'required_with:history|string|in:user,assistant',
            'history.*.text' => 'required_with:history|string|max:2000',
            'context'        => 'sometimes|array',
            'context.url'    => 'nullable|string|max:500',
            'context.slug'   => 'nullable|string|max:250|regex:/^[A-Za-z0-9\-]+$/',
        ]);

        $stream = str_contains((string) $request->header('Accept'), 'text/event-stream');

        // Tope diario por IP además del throttle por minuto de la ruta: un
        // script puede gastar 12/min sin pausa, una persona no llega a 80/día.
        $clave = 'mp-assistant-day:' . $request->ip();
        if (RateLimiter::tooManyAttempts($clave, 80)) {
            $r = ['text' => 'Llegaste al límite de consultas de hoy. Puedes seguir usando el buscador de arriba.', 'products' => [], 'suggestions' => []];
            return $stream ? $this->sse(fn ($emit) => $emit('done', $r)) : response()->json($r);
        }
        RateLimiter::hit($clave, 86400);

        $buyer = auth('marketplace')->user();
        $contexto = $data['context'] ?? [];
        $pagina = $contexto['url'] ?? null;

        $convId = $log->conversation(
            $data['session'], $buyer?->id, $request->ip(), $pagina,
            $data['message'], (string) config('services.anthropic.assistant_model')
        );

        $responder = function (?callable $onStatus) use ($assistant, $log, $data, $buyer, $contexto, $convId, $pagina) {
            $r = $assistant->reply($data['history'] ?? [], $data['message'], $buyer?->id, $contexto, $onStatus);
            $meta = $r['meta'] ?? [];
            unset($r['meta']);

            $r['message_id'] = $log->turn($convId, $data['message'], $meta + [
                'text'        => $r['text'],
                'products'    => array_column($r['products'], 'slug'),
                'suggestions' => $r['suggestions'],
                'page_url'    => $pagina,
            ]);
            return $r;
        };

        if (!$stream) {
            return response()->json($responder(null))->header('Cache-Control', 'no-store, private');
        }

        return $this->sse(function (callable $emit) use ($responder) {
            $emit('status', ['text' => 'Pensando…']);
            $emit('done', $responder(fn (string $t) => $emit('status', ['text' => $t])));
        });
    }

    public function feedback(Request $request, ShoppingAssistantLog $log)
    {
        $data = $request->validate([
            'session'    => self::SESSION_RULE,
            'message_id' => 'required|integer|min:1',
            'value'      => 'required|integer|in:1,-1',
            'note'       => 'nullable|string|max:300',
        ]);

        return response()->json(['ok' => $log->feedback($data['session'], (int) $data['message_id'], (int) $data['value'], $data['note'] ?? null)]);
    }

    public function event(Request $request, ShoppingAssistantLog $log)
    {
        $data = $request->validate([
            'session'    => self::SESSION_RULE,
            'type'       => 'required|string|in:click,cart,handoff,open,search_rescue',
            'slug'       => 'nullable|string|max:250',
            'message_id' => 'nullable|integer|min:1',
        ]);

        return response()->json(['ok' => $log->event($data['session'], $data['type'], $data['slug'] ?? null, isset($data['message_id']) ? (int) $data['message_id'] : null)]);
    }

    public function history(ShoppingAssistantLog $log)
    {
        $buyer = auth('marketplace')->user();
        if (!$buyer) {
            return response()->json(['session_key' => null, 'history' => []]);
        }
        return response()->json($log->recentForBuyer((int) $buyer->id))->header('Cache-Control', 'no-store, private');
    }

    /**
     * Respuesta Server-Sent Events. Cada evento es `event: X` + `data: JSON`.
     * X-Accel-Buffering: no evita que nginx acumule la salida.
     */
    private function sse(callable $body): StreamedResponse
    {
        return new StreamedResponse(function () use ($body) {
            @ini_set('zlib.output_compression', '0');
            @ini_set('output_buffering', 'off');
            while (ob_get_level() > 0) { @ob_end_flush(); }

            $emit = function (string $event, array $data) {
                echo "event: {$event}\n";
                echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
                @flush();
            };

            try {
                $body($emit);
            } catch (\Throwable $e) {
                \Log::warning('Asistente marketplace: fallo en el stream', ['e' => $e->getMessage()]);
                $emit('done', ['text' => 'No pude responder ahora. Puedes usar el buscador de arriba mientras tanto.', 'products' => [], 'suggestions' => []]);
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream; charset=utf-8',
            'Cache-Control'     => 'no-store, private',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }
}
