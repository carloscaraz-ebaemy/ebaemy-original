<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\System\Configuration as SystemConfiguration;
use App\Models\System\ShalomAgency;
use App\Services\System\ShalomAgencyCatalog;
use App\Services\System\ShalomApi;
use Illuminate\Http\Request;

/**
 * SuperAdmin → Shalom.
 *
 * Una llave de ebaemy para todas las tiendas. Si se vence o se borra en
 * shalom-api.lat, aquí se pega la nueva sin tocar el servidor. El catálogo
 * de oficinas queda guardado en `shalom_agencies` pase lo que pase con la
 * llave.
 */
class ShalomSystemController extends Controller
{
    public function __construct(private ShalomApi $api, private ShalomAgencyCatalog $catalog) {}

    public function index()
    {
        $config = SystemConfiguration::first();
        $key = $this->api->key();

        return view('system.shalom.index', [
            'config'     => $config,
            'keySource'  => $this->api->keySource(),
            'keyPreview' => $key ? (substr($key, 0, 7) . '…' . substr($key, -4)) : null,
            'plan'       => \App\Services\System\ShalomPlan::current(),
            'webhookOn'  => !empty(optional($config)->getRawOriginal('shalom_webhook_secret')),
            'guides'     => \Illuminate\Support\Facades\Schema::hasTable('shalom_guides')
                ? \Illuminate\Support\Facades\DB::table('shalom_guides')->count() : 0,
            'stats'      => [
                'total'      => ShalomAgency::count(),
                'selectable' => ShalomAgency::selectable()->count(),
                'delisted'   => ShalomAgency::where('listed', false)->count(),
            ],
        ]);
    }

    /**
     * Guarda una llave nueva. Se valida contra el API ANTES de guardarla:
     * pegar una llave mala no debe tumbar la que funciona.
     */
    public function updateKey(Request $request)
    {
        $data = $request->validate(['shalom_api_key' => 'required|string|min:10|max:200']);
        $key = trim($data['shalom_api_key']);

        try {
            $check = $this->api->validate($key);
        } catch (\Throwable $e) {
            return back()->with('shalom_error', 'La llave no se guardó: ' . $e->getMessage());
        }

        if (empty($check['valid'])) {
            return back()->with('shalom_error', 'Shalom API dice que esa llave no es válida. No se guardó.');
        }

        $config = SystemConfiguration::first();
        if (!$config) {
            return back()->with('shalom_error', 'La configuración del sistema no está inicializada.');
        }
        $config->forceFill(['shalom_api_key' => $key])->save();

        return back()->with('shalom_ok', 'Llave guardada y verificada. Todas las tiendas la usan desde ahora.');
    }

    /** El plan contratado: decide si se encienden instancias y webhooks. */
    public function updatePlan(Request $request)
    {
        $data = $request->validate(['shalom_plan' => 'required|in:' . implode(',', array_keys(\App\Services\System\ShalomPlan::PLANS))]);
        SystemConfiguration::first()->forceFill(['shalom_plan' => $data['shalom_plan']])->save();

        return back()->with('shalom_ok', 'Plan actualizado: ' . \App\Services\System\ShalomPlan::PLANS[$data['shalom_plan']]['label'] . '.');
    }

    /**
     * Registra en Shalom la URL del webhook (una para toda la cuenta) y guarda
     * el secreto de firma, que Shalom muestra completo solo en esta respuesta.
     */
    public function webhook()
    {
        $url = rtrim(config('app.url') ?: 'https://ebaemy.com', '/') . '/webhooks/shalom';
        try {
            $r = $this->api->configureWebhook($url, true);
        } catch (\Throwable $e) {
            return back()->with('shalom_error', 'No se pudo activar: ' . $e->getMessage());
        }
        $secret = $r['webhook']['secret'] ?? null;
        if (!$secret) {
            return back()->with('shalom_error', 'Shalom no devolvió el secreto de firma.');
        }
        SystemConfiguration::first()->forceFill(['shalom_webhook_secret' => $secret])->save();

        return back()->with('shalom_ok', "Avisos activados: Shalom avisará a {$url} cada cambio de estado.");
    }

    public function check()
    {
        try {
            $r = $this->api->validate();
        } catch (\Throwable $e) {
            return back()->with('shalom_error', $e->getMessage());
        }

        $uso = isset($r['currentUsage']) ? (' · consultas este mes: ' . $r['currentUsage']) : '';
        $limite = array_key_exists('limit', $r) ? (' de ' . ($r['limit'] ?? 'sin límite')) : '';

        return back()->with(!empty($r['valid']) ? 'shalom_ok' : 'shalom_error',
            (!empty($r['valid']) ? 'La llave funciona' : 'La llave NO es válida') . $uso . $limite);
    }

    public function sync()
    {
        try {
            $r = $this->catalog->sync();
        } catch (\Throwable $e) {
            return back()->with('shalom_error', 'No se pudo sincronizar (el catálogo guardado sigue igual): ' . $e->getMessage());
        }

        return back()->with('shalom_ok', "Catálogo actualizado: {$r['total']} oficinas ({$r['created']} nuevas, {$r['delisted']} ya no aparecen).");
    }
}
