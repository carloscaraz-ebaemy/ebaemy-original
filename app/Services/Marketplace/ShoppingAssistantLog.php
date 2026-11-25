<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Registro y presupuesto del asistente de compras.
 *
 * Guarda cada conversación y cada turno en la BD del sistema (ver la
 * migración create_marketplace_assistant_tables) y lleva el contador diario
 * de tokens que corta el servicio cuando se pasa del tope configurado
 * (MARKETPLACE_ASSISTANT_DAILY_TOKENS). Sin esto el bot era ciego: no se
 * sabía qué preguntaban ni cuánto costaba.
 *
 * Nunca lanza: un fallo al registrar se anota en el log y el chat sigue.
 */
class ShoppingAssistantLog
{
    private const T_CONV = 'marketplace_assistant_conversations';
    private const T_MSG  = 'marketplace_assistant_messages';
    private const T_EVT  = 'marketplace_assistant_events';

    /** Precio por millón de tokens (USD) para la estimación del panel. */
    public static function prices(): array
    {
        return [
            'in'    => (float) config('services.anthropic.price_in', 0.10),
            'out'   => (float) config('services.anthropic.price_out', 0.50),
            'cache' => (float) config('services.anthropic.price_cache', 0.01),
        ];
    }

    public static function costUsd(int $in, int $out, int $cache = 0): float
    {
        $p = static::prices();
        return ($in * $p['in'] + $out * $p['out'] + $cache * $p['cache']) / 1_000_000;
    }

    // ───────────────────────── presupuesto diario ─────────────────────────

    private static function budgetKey(): string
    {
        return 'mp-assistant-tokens:' . now()->format('Y-m-d');
    }

    public static function dailyBudget(): int
    {
        return (int) config('services.anthropic.daily_tokens', 2_000_000);
    }

    public static function tokensToday(): int
    {
        return (int) Cache::get(static::budgetKey(), 0);
    }

    /** true si hoy ya se gastó el tope y no hay que llamar a la API. */
    public static function overBudget(): bool
    {
        $tope = static::dailyBudget();
        return $tope > 0 && static::tokensToday() >= $tope;
    }

    public static function addTokens(int $n): void
    {
        if ($n <= 0) return;
        $k = static::budgetKey();
        try {
            Cache::add($k, 0, now()->addDays(2));
            Cache::increment($k, $n);
        } catch (\Throwable $e) {
            // sin caché no hay tope: mejor seguir que caerse
        }
    }

    // ───────────────────────── conversaciones ─────────────────────────

    /**
     * Devuelve el id de la conversación de ese navegador, creándola si hace
     * falta. $sessionKey lo genera el cliente (UUID); se valida en el
     * controlador. Si el comprador inició sesión se le asocia.
     */
    public function conversation(string $sessionKey, ?int $buyerId, ?string $ip, ?string $page, string $firstMessage, string $model): ?int
    {
        try {
            $db = DB::connection('system');
            $row = $db->table(self::T_CONV)->where('session_key', $sessionKey)->first(['id', 'buyer_id']);
            if ($row) {
                if ($buyerId && !$row->buyer_id) {
                    $db->table(self::T_CONV)->where('id', $row->id)->update(['buyer_id' => $buyerId]);
                }
                return (int) $row->id;
            }

            return (int) $db->table(self::T_CONV)->insertGetId([
                'session_key'   => $sessionKey,
                'buyer_id'      => $buyerId,
                'ip_hash'       => $ip ? hash('sha256', $ip . config('app.key')) : null,
                'first_page'    => $page ? mb_substr($page, 0, 500) : null,
                'first_message' => mb_substr($firstMessage, 0, 500),
                'model'         => $model,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Asistente marketplace: no se pudo registrar la conversación', ['e' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Guarda el turno del comprador y el del bot y actualiza los agregados.
     *
     * @param array $turno {
     *   text, products (slugs), tools, suggestions, zero_results, input_tokens,
     *   output_tokens, cache_read_tokens, latency_ms, error, page_url, context_slug
     * }
     * @return int|null id del mensaje del bot (para el 👍/👎)
     */
    public function turn(?int $conversationId, string $userText, array $turno): ?int
    {
        if (!$conversationId) return null;

        try {
            $db = DB::connection('system');
            $now = now();

            $db->table(self::T_MSG)->insert([
                'conversation_id' => $conversationId,
                'role'            => 'user',
                'text'            => mb_substr($userText, 0, 2000),
                'page_url'        => isset($turno['page_url']) ? mb_substr((string) $turno['page_url'], 0, 500) : null,
                'context_slug'    => $turno['context_slug'] ?? null,
                'created_at'      => $now,
            ]);

            $id = $db->table(self::T_MSG)->insertGetId([
                'conversation_id'   => $conversationId,
                'role'              => 'assistant',
                'text'              => mb_substr((string) ($turno['text'] ?? ''), 0, 6000),
                'tools'             => !empty($turno['tools']) ? json_encode($turno['tools'], JSON_UNESCAPED_UNICODE) : null,
                'products'          => !empty($turno['products']) ? json_encode(array_values($turno['products']), JSON_UNESCAPED_UNICODE) : null,
                'suggestions'       => !empty($turno['suggestions']) ? json_encode($turno['suggestions'], JSON_UNESCAPED_UNICODE) : null,
                'zero_results'      => !empty($turno['zero_results']),
                'input_tokens'      => (int) ($turno['input_tokens'] ?? 0),
                'output_tokens'     => (int) ($turno['output_tokens'] ?? 0),
                'cache_read_tokens' => (int) ($turno['cache_read_tokens'] ?? 0),
                'latency_ms'        => isset($turno['latency_ms']) ? (int) $turno['latency_ms'] : null,
                'error'             => isset($turno['error']) ? mb_substr((string) $turno['error'], 0, 120) : null,
                'created_at'        => $now,
            ]);

            $db->table(self::T_CONV)->where('id', $conversationId)->update([
                'messages_count'     => $db->raw('messages_count + 1'),
                'zero_results_count' => $db->raw('zero_results_count + ' . (!empty($turno['zero_results']) ? 1 : 0)),
                'products_count'     => $db->raw('products_count + ' . count($turno['products'] ?? [])),
                'input_tokens'       => $db->raw('input_tokens + ' . (int) ($turno['input_tokens'] ?? 0)),
                'output_tokens'      => $db->raw('output_tokens + ' . (int) ($turno['output_tokens'] ?? 0)),
                'cache_read_tokens'  => $db->raw('cache_read_tokens + ' . (int) ($turno['cache_read_tokens'] ?? 0)),
                'last_message_at'    => $now,
                'updated_at'         => $now,
            ]);

            return (int) $id;
        } catch (\Throwable $e) {
            \Log::warning('Asistente marketplace: no se pudo registrar el turno', ['e' => $e->getMessage()]);
            return null;
        }
    }

    /** 👍 (1) o 👎 (-1) sobre un mensaje del bot de ESA conversación. */
    public function feedback(string $sessionKey, int $messageId, int $value, ?string $note = null): bool
    {
        try {
            $db = DB::connection('system');
            $conv = $db->table(self::T_CONV)->where('session_key', $sessionKey)->first(['id']);
            if (!$conv) return false;

            $msg = $db->table(self::T_MSG)->where('id', $messageId)->where('conversation_id', $conv->id)->where('role', 'assistant')->first(['id', 'feedback']);
            if (!$msg) return false;

            $db->table(self::T_MSG)->where('id', $msg->id)->update([
                'feedback'      => $value,
                'feedback_note' => $note ? mb_substr($note, 0, 300) : null,
            ]);

            // Recontar en vez de sumar: el comprador puede cambiar de opinión.
            $up = $db->table(self::T_MSG)->where('conversation_id', $conv->id)->where('feedback', 1)->count();
            $down = $db->table(self::T_MSG)->where('conversation_id', $conv->id)->where('feedback', -1)->count();
            $db->table(self::T_CONV)->where('id', $conv->id)->update(['feedback_up' => $up, 'feedback_down' => $down, 'updated_at' => now()]);

            return true;
        } catch (\Throwable $e) {
            \Log::warning('Asistente marketplace: feedback no registrado', ['e' => $e->getMessage()]);
            return false;
        }
    }

    /** Clic en tarjeta, agregado al carrito, salida a WhatsApp… */
    public function event(string $sessionKey, string $type, ?string $slug = null, ?int $messageId = null): bool
    {
        try {
            $db = DB::connection('system');
            $conv = $db->table(self::T_CONV)->where('session_key', $sessionKey)->first(['id']);
            if (!$conv) return false;

            $db->table(self::T_EVT)->insert([
                'conversation_id' => $conv->id,
                'message_id'      => $messageId,
                'type'            => $type,
                'slug'            => $slug ? mb_substr($slug, 0, 250) : null,
                'created_at'      => now(),
            ]);

            $col = ['click' => 'clicks_count', 'cart' => 'cart_adds_count', 'handoff' => 'handoffs_count'][$type] ?? null;
            if ($col) {
                $db->table(self::T_CONV)->where('id', $conv->id)->update([$col => $db->raw("{$col} + 1"), 'updated_at' => now()]);
            }
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Historial reciente de un comprador conectado, para que al volver desde
     * otro dispositivo o pestaña recupere su conversación. Devuelve los
     * turnos en el formato que usa el navegador.
     */
    public function recentForBuyer(int $buyerId, int $max = 20): array
    {
        try {
            $db = DB::connection('system');
            $conv = $db->table(self::T_CONV)->where('buyer_id', $buyerId)
                ->where('last_message_at', '>=', now()->subDays(14))
                ->orderByDesc('last_message_at')->first(['id', 'session_key']);
            if (!$conv) return ['session_key' => null, 'history' => []];

            $filas = $db->table(self::T_MSG)->where('conversation_id', $conv->id)
                ->orderByDesc('id')->limit($max)->get(['id', 'role', 'text', 'products', 'suggestions'])->reverse();

            $slugs = [];
            foreach ($filas as $f) {
                foreach ((array) json_decode((string) $f->products, true) as $s) $slugs[$s] = true;
            }
            $tarjetas = $slugs ? ShoppingAssistantTools::cardsFor(array_keys($slugs)) : [];

            $history = [];
            foreach ($filas as $f) {
                $prods = [];
                foreach ((array) json_decode((string) $f->products, true) as $s) {
                    if (isset($tarjetas[$s])) $prods[] = $tarjetas[$s];
                }
                $history[] = [
                    'id'          => (int) $f->id,
                    'role'        => $f->role,
                    'text'        => (string) $f->text,
                    'products'    => $prods,
                    'suggestions' => (array) json_decode((string) $f->suggestions, true),
                ];
            }
            return ['session_key' => $conv->session_key, 'history' => $history];
        } catch (\Throwable $e) {
            return ['session_key' => null, 'history' => []];
        }
    }
}
