<?php

namespace App\Services\Tenant;

use App\Models\Tenant\ShippingRequest;
use App\Services\System\ShalomApi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Estado de una guía de Shalom, guardado en el envío.
 *
 * Shalom responde con los hitos de su propio seguimiento (registrado, en
 * origen, en tránsito, en destino, reparto, entregado) y la fecha de cada uno.
 * Se guardan en `carrier_events` para que la página del cliente los muestre
 * igual que la web de Shalom, sin consultar el API en cada visita.
 *
 * `carrier_events` también guarda lo que ebaemy sabe de la guía y Shalom no
 * devuelve en su seguimiento: `ose_id`, `clave`, `created_via`, `created_at`,
 * `notified_destino`. Por eso aquí SIEMPRE se mezcla, nunca se reemplaza:
 * reemplazarlo borraba la clave y el «quitar guía» de las guías reales.
 *
 * Solo informa: no cambia el estado del envío ni del pedido.
 */
class ShalomTracking
{
    /** Hitos en orden, con el texto que ve el cliente. */
    public const STEPS = [
        'registrado' => 'Registrado en Shalom',
        'origen'     => 'En la agencia de origen',
        'transito'   => 'En camino a tu ciudad',
        'destino'    => 'Listo para recoger en tu oficina',
        'reparto'    => 'En reparto a domicilio',
        'entregado'  => 'Entregado',
    ];

    /** Días hábiles (lunes a sábado) que una guía creada puede esperar sin entregarse. */
    public const STALE_DAYS = 2;

    public function __construct(private ShalomApi $api) {}

    public static function supported(): bool
    {
        static $memo = [];
        try {
            $schema = \Illuminate\Support\Facades\Schema::connection('tenant');
            $db = $schema->getConnection()->getDatabaseName();
            if (!array_key_exists($db, $memo)) {
                $memo[$db] = $schema->hasColumn('shipping_requests', 'tracking_code');
            }

            return $memo[$db];
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function applies(ShippingRequest $s): bool
    {
        return $s->shipping_agency === 'Shalom'
            && preg_match('/^\d{6,12}$/', (string) $s->tracking_number)
            && preg_match('/^[A-Z0-9]{4}$/', (string) $s->tracking_code);
    }

    /** ¿El envío tiene una guía de Shalom (cargada a mano o creada por API)? */
    public static function hasGuide(ShippingRequest $s): bool
    {
        return !empty($s->tracking_code) || !empty(($s->carrier_events ?? [])['ose_id']);
    }

    /** Normaliza lo que escribe el operador: «sh-66479331» → 66479331, « 3kth» → 3KTH. */
    public static function normalizeNumber(?string $v): ?string
    {
        $v = trim((string) $v);
        $digits = preg_replace('/\D/', '', $v);

        return strlen($digits) >= 6 && strlen($digits) <= 12 ? $digits : ($v !== '' ? $v : null);
    }

    public static function normalizeCode(?string $v): ?string
    {
        $v = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $v));

        return $v !== '' ? substr($v, 0, 12) : null;
    }

    /**
     * Qué cambio rompería la guía ya creada. Con guía, el destino y quien
     * recoge están grabados en Shalom: cambiarlos aquí deja una guía que va a
     * otra parte. Devuelve el motivo para rechazar, o null.
     */
    public static function guardChange(ShippingRequest $s, array $data): ?string
    {
        if (!self::hasGuide($s)) {
            return null;
        }
        if (array_key_exists('shalom_air', $data) && (bool) $data['shalom_air'] !== (bool) $s->shalom_air) {
            return 'Este envío ya tiene la guía de Shalom ' . ($s->tracking_number ?: '') . ' por vía '
                . ($s->shalom_air ? 'aérea' : 'terrestre') . '. Para cambiar la vía, quita primero la guía y anúlala en pro.shalom.pe.';
        }
        foreach (['delivery_type', 'shipping_agency', 'district_id', 'agency_office_id', 'dni', 'pickup_person_dni'] as $campo) {
            if (array_key_exists($campo, $data) && (string) $data[$campo] !== (string) $s->{$campo}) {
                return 'Este envío ya tiene la guía de Shalom ' . ($s->tracking_number ?: '') . ': el destino y quien recoge ya están '
                    . 'registrados en Shalom. Cámbialos desde «Guía de Shalom» en el menú del pedido, para que Shalom y el panel digan lo mismo.';
            }
        }

        return null;
    }

    /**
     * Consulta y guarda. Devuelve false si Shalom no la encuentra todavía
     * (una guía recién emitida tarda en aparecer) o si el API falla.
     */
    public function refresh(ShippingRequest $s): bool
    {
        if (!self::applies($s)) {
            return false;
        }

        try {
            $r = $this->api->track($s->tracking_number, $s->tracking_code);
        } catch (\Throwable $e) {
            $s->forceFill(['carrier_checked_at' => now()])->saveQuietly();

            return false;
        }

        $st = $r['statuses']['data'] ?? [];
        $search = $r['search']['data'] ?? [];
        if (!$st && !$search) {
            // Respuesta vacía: no se pisa lo que ya se sabía.
            $s->forceFill(['carrier_checked_at' => now()])->saveQuietly();

            return false;
        }

        $events = [];
        foreach (array_keys(self::STEPS) as $k) {
            $fecha = $st[$k]['fecha'] ?? null;
            if ($k === 'reparto' && !$fecha) {
                continue;      // solo existe si la guía lleva reparto
            }
            try {
                $at = $fecha ? Carbon::parse($fecha)->format('Y-m-d H:i') : null;
            } catch (\Throwable $e) {
                $at = null;    // formato de fecha inesperado: el hito sin fecha
            }
            $events[] = ['key' => $k, 'at' => $at];
        }

        $last = null;
        foreach ($events as $e) {
            if ($e['at']) {
                $last = $e['key'];
            }
        }

        $ce = array_merge($s->carrier_events ?? [], [
            'events'  => $events,
            'delay'   => !empty($st['demora']),
            'origin'  => $search['origen']['nombre'] ?? (($s->carrier_events ?? [])['origin'] ?? null),
            'dest'    => $search['destino']['nombre'] ?? (($s->carrier_events ?? [])['dest'] ?? null),
            'eta'     => $search['tiempo_llegada'] ?? (($s->carrier_events ?? [])['eta'] ?? null),
            'shalom_content' => $search['contenido'] ?? (($s->carrier_events ?? [])['shalom_content'] ?? null),
        ]);

        $estado = self::noRetroceder($s->carrier_status, $last);
        $ce = self::conPrecio($s, $ce, $search, $estado);
        $s->forceFill(['carrier_status' => $estado, 'carrier_events' => $ce, 'carrier_checked_at' => now()])->saveQuietly();

        if ($estado === 'destino') {
            self::notifyArrived($s);
        }

        return true;
    }

    /**
     * El precio del envío según Shalom. Al crear la guía Shalom da una
     * cotización (por el tamaño declarado); al recibir el paquete en la
     * agencia lo mide y pesa y fija el monto real, que puede subir o bajar
     * (2026-10-29: paquete S a S/ 12 quedó como mini XS a S/ 10). Se guarda
     * el último monto y, desde que el paquete está en manos de Shalom, se da
     * por confirmado. El primer cambio frente a la cotización va a la bitácora.
     */
    private static function conPrecio(ShippingRequest $s, array $ce, array $search, ?string $estado): array
    {
        if (isset($search['monto']) && is_numeric($search['monto']) && (float) $search['monto'] > 0) {
            $ce['price_shalom'] = round((float) $search['monto'] + (float) ($search['montoAdicional'] ?? 0), 2);
            if ($estado && $estado !== 'registrado') {
                $ce['price_confirmed'] = true;
            }
        }
        if (!empty($search['tipo_pago'])) {
            $ce['pay_type'] = (string) $search['tipo_pago'];
        }
        $cot = self::cotizado($ce);
        if (!empty($ce['price_confirmed']) && empty($ce['price_logged']) && $cot !== null
            && abs($cot - (float) $ce['price_shalom']) >= 0.01) {
            $ce['price_logged'] = now()->toDateTimeString();
            \App\Models\Tenant\ShippingAuditLog::log(\App\Models\Tenant\ShippingAuditLog::ACTION_EDIT, $s->id, 'shalom_price',
                number_format($cot, 2), number_format((float) $ce['price_shalom'], 2),
                'Shalom ajustó el precio al recibir el paquete' . (!empty($ce['shalom_content']) ? ' (' . $ce['shalom_content'] . ')' : '') . '.');
        }

        return $ce;
    }

    /** Cotización al crear la guía (las guías de antes la tienen solo en la respuesta guardada). */
    private static function cotizado(array $ce): ?float
    {
        if (isset($ce['price_quoted']) && is_numeric($ce['price_quoted'])) {
            return (float) $ce['price_quoted'];
        }
        $raw = !empty($ce['register_raw']) ? json_decode((string) $ce['register_raw'], true) : null;
        $p = is_array($raw) ? data_get($raw, 'data.precio') : null;

        return is_numeric($p) ? (float) $p : null;
    }

    /**
     * Precio del envío para mostrar: cotizado, el de Shalom y si el cliente
     * lo paga al recoger (contra entrega). Null si no hay ningún dato.
     *
     * @return array{quoted:?float, shalom:?float, confirmed:bool, diff:?float, pay_on_pickup:bool,
     *               content_quoted:?string, content_shalom:?string}|null
     */
    public static function price(ShippingRequest $s): ?array
    {
        if ($s->shipping_agency !== 'Shalom') {
            return null;
        }
        $ce = $s->carrier_events ?? [];
        $cot = self::cotizado($ce);
        $sh = isset($ce['price_shalom']) ? (float) $ce['price_shalom'] : null;
        if ($cot === null && $sh === null) {
            return null;
        }
        $conf = !empty($ce['price_confirmed']) && $sh !== null;

        return [
            'quoted'         => $cot,
            'shalom'         => $sh,
            'confirmed'      => $conf,
            'diff'           => $conf && $cot !== null ? round($sh - $cot, 2) : null,
            'pay_on_pickup'  => stripos((string) ($ce['pay_type'] ?? ''), 'contra') !== false,
            'content_quoted' => isset($ce['content']) ? ucfirst(mb_strtolower(str_replace('PAQUETE ', 'Paquete ', (string) $ce['content']))) : null,
            'content_shalom' => !empty($ce['shalom_content']) ? ucfirst(mb_strtolower((string) $ce['shalom_content'])) : null,
        ];
    }

    /** El estado nunca vuelve atrás: un dato atrasado no deshace «entregado». */
    public static function noRetroceder(?string $actual, ?string $nuevo): ?string
    {
        if (!$nuevo) {
            return $actual;
        }
        $orden = array_keys(self::STEPS);
        $a = $actual ? array_search($actual, $orden, true) : false;
        $n = array_search($nuevo, $orden, true);

        return ($a !== false && $n !== false && $a > $n) ? $actual : $nuevo;
    }

    /**
     * WhatsApp «ya llegó a tu oficina», una sola vez por guía. Lo llaman el
     * webhook y la consulta periódica: el que se entere primero avisa.
     */
    public static function notifyArrived(ShippingRequest $s, ?string $fqdn = null): void
    {
        $ce = $s->carrier_events ?? [];
        if (!empty($ce['notified_destino']) || $s->is_cancelled) {
            return;
        }
        $ce['notified_destino'] = now()->toDateTimeString();
        $s->forceFill(['carrier_events' => $ce])->saveQuietly();

        try {
            $fqdn = $fqdn ?: optional(app(\Hyn\Tenancy\Environment::class)->hostname())->fqdn;
            $digits = preg_replace('/\D/', '', (string) $s->phone);
            if (strlen($digits) === 9) {
                $digits = '51' . $digits;
            }
            if (strlen($digits) < 11) {
                return;
            }
            $name = trim((string) \Illuminate\Support\Str::of((string) $s->full_name)->after(', ')->before(' ')) ?: 'hola';
            $msg = "¡Hola {$name}! 📦\n\nTu pedido ya llegó a *Shalom*"
                . ($s->agency_office_name ? " — oficina *{$s->agency_office_name}*" : '') . ".\n"
                . "Puedes recogerlo con tu DNI.\n"
                . (($p = self::price($s)) && $p['pay_on_pickup'] && ($p['shalom'] ?? $p['quoted'])
                    ? '💵 Pagas S/ ' . number_format((float) ($p['shalom'] ?? $p['quoted']), 2) . " del envío al recoger.\n" : '')
                . "📄 Guía: {$s->tracking_number} · Código: {$s->tracking_code}\n"
                . (!empty($ce['clave']) ? "🔑 Clave de recojo: {$ce['clave']}\n" : '')
                . ($fqdn ? "\n🔎 https://{$fqdn}/envio/seguimiento?code={$s->shipment_code}" : '');
            dispatch(\App\Jobs\SendWhatsAppMessage::text($digits, $msg));
        } catch (\Throwable $e) {
            Log::info('[shalom] WhatsApp de llegada no enviado: ' . $e->getMessage());
        }
    }

    /**
     * Anota la guía en el mapa del sistema (para que el webhook central sepa
     * de qué tienda y envío es) y, si el plan incluye webhooks, la suscribe.
     * Si la guía ya estaba anotada para otro envío, se reasigna a este.
     */
    public function watch(ShippingRequest $s): void
    {
        if (!self::applies($s)) {
            return;
        }
        try {
            $website = app(\Hyn\Tenancy\Environment::class)->tenant();
            if (!$website) {
                return;
            }
            $db = DB::connection('system')->table('shalom_guides');
            $key = ['order_number' => $s->tracking_number, 'order_code' => $s->tracking_code];
            $row = (clone $db)->where($key)->first();
            if (!$row) {
                $db->insert($key + ['website_id' => $website->id, 'shipping_request_id' => $s->id, 'created_at' => now(), 'updated_at' => now()]);
                $row = (clone $db)->where($key)->first();
            } elseif ((int) $row->website_id !== (int) $website->id || (int) $row->shipping_request_id !== (int) $s->id) {
                (clone $db)->where('id', $row->id)->update(['website_id' => $website->id, 'shipping_request_id' => $s->id, 'updated_at' => now()]);
            }
            if (!$row->subscribed && \App\Services\System\ShalomPlan::allowsWebhooks()) {
                $r = $this->api->subscribe($s->tracking_number, $s->tracking_code);
                if (!empty($r['success'])) {
                    (clone $db)->where('id', $row->id)->update(['subscribed' => true, 'updated_at' => now()]);
                }
            }
        } catch (\Throwable $e) {
            Log::info('[shalom] no se pudo suscribir la guía ' . $s->tracking_number . ': ' . $e->getMessage());
        }
    }

    /** Deja de seguir una guía: cancela la suscripción y la borra del mapa. */
    public function unwatch(?string $number, ?string $code): void
    {
        if (!$number || !$code) {
            return;
        }
        try {
            $this->api->unsubscribe($number, $code);
        } catch (\Throwable $e) {
        }
        try {
            DB::connection('system')->table('shalom_guides')
                ->where('order_number', $number)->where('order_code', $code)->delete();
        } catch (\Throwable $e) {
        }
    }

    /**
     * Guía creada desde ebaemy que Shalom todavía no recibió en el mostrador:
     * sin movimiento (o solo «registrado») pasados STALE_DAYS días hábiles.
     */
    public static function notDelivered(ShippingRequest $s): bool
    {
        $ce = $s->carrier_events ?? [];
        if (($ce['created_via'] ?? null) !== 'api' || empty($ce['created_at'])) {
            return false;
        }
        if (!in_array($s->carrier_status, [null, '', 'registrado'], true) || $s->is_cancelled) {
            return false;
        }
        $desde = Carbon::parse($ce['created_at']);

        return $desde->diffInDaysFiltered(fn (Carbon $d) => !$d->isSunday(), now()) >= self::STALE_DAYS;
    }

    /** ¿Se puede quitar? Solo guías creadas desde ebaemy que el paquete aún no usó. */
    public static function removable(ShippingRequest $s): bool
    {
        $ce = $s->carrier_events ?? [];

        return ($ce['created_via'] ?? null) === 'api'
            && in_array($s->carrier_status, [null, '', 'registrado'], true);
    }

    /** Cuántas guías creadas siguen sin entregarse (aviso del menú Envíos). */
    public static function notDeliveredCount(): int
    {
        if (!self::supported()) {
            return 0;
        }

        return ShippingRequest::where('shipping_agency', 'Shalom')
            ->whereNotNull('tracking_code')
            ->where(fn ($q) => $q->whereNull('carrier_status')->orWhere('carrier_status', 'registrado'))
            ->where('created_at', '>=', now()->subDays(60))
            ->get(['id', 'carrier_events', 'carrier_status', 'status'])
            ->filter(fn ($s) => self::notDelivered($s))->count();
    }

    /**
     * Desvincula la guía del envío para poder crear otra. La guía sigue
     * existiendo en Shalom: hay que anularla en pro.shalom.pe (el API no
     * permite anular).
     */
    public function detach(ShippingRequest $s): void
    {
        $this->unwatch($s->tracking_number, $s->tracking_code);

        $s->forceFill([
            'tracking_number'    => null,
            'tracking_code'      => null,
            'carrier_status'     => null,
            'carrier_events'     => null,
            'carrier_checked_at' => null,
        ])->save();
    }

    /** ¿Conviene volver a consultar? Cada 30 min mientras no esté entregada. */
    public static function stale(ShippingRequest $s): bool
    {
        return $s->carrier_status !== 'entregado'
            && (!$s->carrier_checked_at || Carbon::parse($s->carrier_checked_at)->lt(now()->subMinutes(30)));
    }
}
