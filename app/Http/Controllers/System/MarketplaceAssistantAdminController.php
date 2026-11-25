<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Services\Marketplace\ShoppingAssistant;
use App\Services\Marketplace\ShoppingAssistantLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * SuperAdmin → Marketplace → Asistente IA.
 *
 * Lo que el bot hace y cuesta: conversaciones y mensajes por día, cuántas
 * búsquedas acaban sin resultado, 👍/👎, clics en tarjetas, agregados al
 * carrito, salidas a WhatsApp, tokens y costo estimado. Abajo, las preguntas
 * que terminaron sin producto (son las que hay que arreglar en el buscador o
 * en el catálogo) y las últimas conversaciones para leerlas una a una.
 */
class MarketplaceAssistantAdminController extends Controller
{
    public function index(Request $request)
    {
        $dias = (int) $request->input('dias', 30);
        $dias = in_array($dias, [7, 30, 90], true) ? $dias : 30;
        $desde = now()->subDays($dias)->startOfDay();
        $db = DB::connection('system');

        $conv = $db->table('marketplace_assistant_conversations')->where('created_at', '>=', $desde);
        $kpi = (array) $conv->clone()->selectRaw('
            COUNT(*) AS conversaciones,
            COALESCE(SUM(messages_count),0) AS mensajes,
            COALESCE(SUM(zero_results_count),0) AS sin_resultados,
            COALESCE(SUM(clicks_count),0) AS clics,
            COALESCE(SUM(cart_adds_count),0) AS carrito,
            COALESCE(SUM(handoffs_count),0) AS whatsapp,
            COALESCE(SUM(feedback_up),0) AS up,
            COALESCE(SUM(feedback_down),0) AS down,
            COALESCE(SUM(input_tokens),0) AS tin,
            COALESCE(SUM(output_tokens),0) AS tout,
            COALESCE(SUM(cache_read_tokens),0) AS tcache,
            SUM(CASE WHEN buyer_id IS NOT NULL THEN 1 ELSE 0 END) AS conectados
        ')->first();

        $kpi['costo_usd'] = ShoppingAssistantLog::costUsd((int) $kpi['tin'], (int) $kpi['tout'], (int) $kpi['tcache']);
        $kpi['sin_resultados_pct'] = $kpi['mensajes'] > 0 ? round($kpi['sin_resultados'] * 100 / $kpi['mensajes']) : 0;
        $kpi['con_clic_pct'] = $kpi['conversaciones'] > 0
            ? round($conv->clone()->where('clicks_count', '>', 0)->count() * 100 / $kpi['conversaciones']) : 0;
        $kpi['satisfaccion_pct'] = ($kpi['up'] + $kpi['down']) > 0 ? round($kpi['up'] * 100 / ($kpi['up'] + $kpi['down'])) : null;

        $porDia = $db->table('marketplace_assistant_messages')
            ->where('role', 'assistant')->where('created_at', '>=', $desde)
            ->selectRaw('DATE(created_at) AS dia, COUNT(*) AS mensajes, SUM(zero_results) AS sin_resultados, SUM(input_tokens+output_tokens) AS tokens, AVG(latency_ms) AS latencia, SUM(error IS NOT NULL) AS errores')
            ->groupBy('dia')->orderBy('dia')->get();

        // Preguntas que acabaron sin producto: el turno del comprador anterior
        // a una respuesta con zero_results.
        $sinResultado = $db->table('marketplace_assistant_messages as a')
            ->join('marketplace_assistant_messages as u', function ($j) {
                $j->on('u.conversation_id', '=', 'a.conversation_id')->on('u.id', '=', DB::raw('a.id - 1'));
            })
            ->where('a.role', 'assistant')->where('a.zero_results', 1)->where('a.created_at', '>=', $desde)
            ->orderByDesc('a.id')->limit(40)
            ->get(['u.text as pregunta', 'a.tools', 'a.created_at', 'a.conversation_id']);

        $herramientas = $db->table('marketplace_assistant_messages')
            ->where('role', 'assistant')->where('created_at', '>=', $desde)->whereNotNull('tools')
            ->pluck('tools')->flatMap(fn ($t) => array_column((array) json_decode((string) $t, true), 'name'))
            ->countBy()->sortDesc();

        $conversaciones = $db->table('marketplace_assistant_conversations')
            ->orderByDesc('last_message_at')->limit(50)->get();

        $estado = [
            'encendido'   => ShoppingAssistant::isAvailable(),
            'modelo'      => (string) config('services.anthropic.assistant_model'),
            'tokens_hoy'  => ShoppingAssistantLog::tokensToday(),
            'tope_diario' => ShoppingAssistantLog::dailyBudget(),
            'precios'     => ShoppingAssistantLog::prices(),
        ];

        return view('system.marketplace.assistant', compact('dias', 'kpi', 'porDia', 'sinResultado', 'herramientas', 'conversaciones', 'estado'));
    }

    public function show(int $id)
    {
        $db = DB::connection('system');
        $conv = $db->table('marketplace_assistant_conversations')->where('id', $id)->first();
        abort_unless($conv, 404);

        $mensajes = $db->table('marketplace_assistant_messages')->where('conversation_id', $id)->orderBy('id')->get()
            ->map(function ($m) {
                $m->tools = (array) json_decode((string) $m->tools, true);
                $m->products = (array) json_decode((string) $m->products, true);
                $m->suggestions = (array) json_decode((string) $m->suggestions, true);
                return $m;
            });
        $eventos = $db->table('marketplace_assistant_events')->where('conversation_id', $id)->orderBy('id')->get();
        $costo = ShoppingAssistantLog::costUsd((int) $conv->input_tokens, (int) $conv->output_tokens, (int) $conv->cache_read_tokens);

        return view('system.marketplace.assistant_show', compact('conv', 'mensajes', 'eventos', 'costo'));
    }
}
