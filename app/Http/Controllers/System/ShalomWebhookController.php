<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\System\Configuration;
use App\Models\Tenant\ShippingRequest;
use App\Services\Tenant\ShalomTracking;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * POST https://ebaemy.com/webhooks/shalom
 *
 * shalom-api.lat avisa aquí cada cambio de estado de una guía suscrita
 * (evento tracking.status_changed). Es UN webhook para toda la cuenta de
 * ebaemy: la tienda se resuelve con el mapa `shalom_guides`.
 *
 * Firma: X-Shalom-Signature: t=<unix>,v1=<hex>, con
 * v1 = HMAC-SHA256("<t>.<cuerpo crudo>", whsec_…). Sin firma válida, 401.
 *
 * Responde 200 aunque la guía no sea nuestra: si respondiéramos error,
 * Shalom reintentaría algo que nunca va a funcionar.
 */
class ShalomWebhookController extends Controller
{
    private const STEP = [
        'REGISTERED'       => 'registrado',
        'EN_ORIGIN'        => 'origen',
        'IN_TRANSIT'       => 'transito',
        'AT_DESTINATION'   => 'destino',
        'OUT_FOR_DELIVERY' => 'reparto',
        'DELIVERED'        => 'entregado',
    ];

    public function handle(Request $request, Environment $tenancy)
    {
        $raw = $request->getContent();
        if (!$this->validSignature($raw, (string) $request->header('X-Shalom-Signature'))) {
            return response()->json(['ok' => false, 'error' => 'firma inválida'], 401);
        }

        $p = json_decode($raw, true) ?: [];
        $number = (string) ($p['orderNumber'] ?? '');
        $code = strtoupper((string) ($p['orderCode'] ?? ''));
        $step = self::STEP[$p['statusCode'] ?? ''] ?? null;
        if ($number === '' || $code === '') {
            return response()->json(['ok' => true, 'ignored' => 'sin guía']);
        }

        $map = DB::connection('system')->table('shalom_guides')
            ->where('order_number', $number)->where('order_code', $code)->first();
        if (!$map) {
            return response()->json(['ok' => true, 'ignored' => 'guía desconocida']);
        }

        try {
            $website = Website::find($map->website_id);
            if (!$website) {
                return response()->json(['ok' => true, 'ignored' => 'tienda inexistente']);
            }
            $tenancy->tenant($website);
            $s = ShippingRequest::find($map->shipping_request_id);
            if ($s && $step) {
                $fqdn = optional($website->hostnames()->first())->fqdn;
                $this->apply($s, $step, $p, $fqdn);
            }
            DB::connection('system')->table('shalom_guides')->where('id', $map->id)
                ->update(['last_status' => $step, 'last_event_at' => now(), 'updated_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('[shalom-webhook] ' . $number . ': ' . $e->getMessage());

            return response()->json(['ok' => false], 500);   // que Shalom reintente
        } finally {
            $tenancy->tenant(null);
        }

        return response()->json(['ok' => true]);
    }

    private function validSignature(string $raw, string $header): bool
    {
        try {
            $secret = Configuration::first()->shalom_webhook_secret ?? null;
        } catch (\Throwable $e) {
            $secret = null;
        }
        if (!$secret || !preg_match('/t=(\d+),\s*v1=([a-f0-9]+)/i', $header, $m)) {
            return false;
        }
        // Avisos de más de un día: no son reintentos, son repeticiones.
        if (abs(time() - (int) $m[1]) > 86400) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $m[1] . '.' . $raw, $secret), strtolower($m[2]));
    }

    /** Marca el hito con su fecha y avisa al cliente cuando ya puede recoger. */
    private function apply(ShippingRequest $s, string $step, array $p, ?string $fqdn): void
    {
        $ce = $s->carrier_events ?: ['events' => []];
        $events = $ce['events'] ?? [];
        $at = !empty($p['statusAt']) ? \Carbon\Carbon::parse($p['statusAt'])->setTimezone('America/Lima')->format('Y-m-d H:i') : now()->format('Y-m-d H:i');

        $found = false;
        foreach ($events as &$e) {
            if ($e['key'] === $step) {
                $e['at'] = $e['at'] ?: $at;
                $found = true;
            }
        }
        unset($e);
        if (!$found) {
            $events[] = ['key' => $step, 'at' => $at];
            $order = array_keys(ShalomTracking::STEPS);
            usort($events, fn ($a, $b) => array_search($a['key'], $order) <=> array_search($b['key'], $order));
        }
        $ce['events'] = $events;
        $ce['delay'] = !empty($p['delayed']);

        // Un aviso atrasado no hace retroceder el estado (llega «En destino»
        // cuando ya estaba «Entregado»): el hito se anota, el estado se queda.
        $orden = array_keys(ShalomTracking::STEPS);
        $actual = $s->carrier_status ? array_search($s->carrier_status, $orden, true) : -1;
        $nuevo = array_search($step, $orden, true);
        $estado = ($actual !== false && $actual > $nuevo) ? $s->carrier_status : $step;

        $avisar = $estado === 'destino' && empty($ce['notified_destino']);
        if ($avisar) {
            $ce['notified_destino'] = now()->toDateTimeString();
        }

        $s->forceFill(['carrier_status' => $estado, 'carrier_events' => $ce, 'carrier_checked_at' => now()])->saveQuietly();

        if ($avisar) {
            $this->notifyArrived($s, $ce, $fqdn);
        }
    }

    private function notifyArrived(ShippingRequest $s, array $ce, ?string $fqdn): void
    {
        try {
            $digits = preg_replace('/\D/', '', (string) $s->phone);
            if (strlen($digits) === 9) {
                $digits = '51' . $digits;
            }
            if (strlen($digits) < 11) {
                return;
            }
            $name = \Illuminate\Support\Str::of((string) $s->full_name)->after(', ')->before(' ') ?: 'hola';
            $msg = "¡Hola {$name}! 📦\n\nTu pedido ya llegó a *Shalom*"
                . ($s->agency_office_name ? " — oficina *{$s->agency_office_name}*" : '') . ".\n"
                . "Puedes recogerlo con tu DNI.\n"
                . "📄 Guía: {$s->tracking_number} · Código: {$s->tracking_code}\n"
                . (!empty($ce['clave']) ? "🔑 Clave de recojo: {$ce['clave']}\n" : '')
                . "\n🔎 " . url('envio/seguimiento?code=' . $s->shipment_code);
            dispatch(\App\Jobs\SendWhatsAppMessage::text($digits, $msg));
        } catch (\Throwable $e) {
            Log::info('[shalom-webhook] WhatsApp no enviado: ' . $e->getMessage());
        }
    }
}
