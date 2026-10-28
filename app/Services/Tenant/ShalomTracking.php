<?php

namespace App\Services\Tenant;

use App\Models\Tenant\ShippingRequest;
use App\Services\System\ShalomApi;
use Carbon\Carbon;

/**
 * Estado de una guía de Shalom, guardado en el envío.
 *
 * Shalom responde con los hitos de su propio seguimiento (registrado, en
 * origen, en tránsito, en destino, reparto, entregado) y la fecha de cada uno.
 * Se guardan en `carrier_events` para que la página del cliente los muestre
 * igual que la web de Shalom, sin consultar el API en cada visita.
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

    public function __construct(private ShalomApi $api) {}

    public static function supported(): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('shipping_requests', 'tracking_code');
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
        $events = [];
        foreach (array_keys(self::STEPS) as $k) {
            $fecha = $st[$k]['fecha'] ?? null;
            if ($k === 'entregado' && !$fecha && !empty($search['entregado'])) {
                $fecha = $st['entregado']['fecha'] ?? null;
            }
            if ($k === 'reparto' && !$fecha) {
                continue;      // solo existe si la guía lleva reparto
            }
            $events[] = ['key' => $k, 'at' => $fecha ? Carbon::parse($fecha)->format('Y-m-d H:i') : null];
        }

        $last = null;
        foreach ($events as $e) {
            if ($e['at']) {
                $last = $e['key'];
            }
        }

        $s->forceFill([
            'carrier_status'     => $last,
            'carrier_events'     => [
                'events'   => $events,
                'delay'    => !empty($st['demora']),
                'origin'   => $search['origen']['nombre'] ?? null,
                'dest'     => $search['destino']['nombre'] ?? null,
                'eta'      => $search['tiempo_llegada'] ?? null,
                'content'  => $search['contenido'] ?? null,
            ],
            'carrier_checked_at' => now(),
        ])->saveQuietly();

        return true;
    }

    /** ¿Conviene volver a consultar? Cada 30 min mientras no esté entregada. */
    public static function stale(ShippingRequest $s): bool
    {
        return $s->carrier_status !== 'entregado'
            && (!$s->carrier_checked_at || Carbon::parse($s->carrier_checked_at)->lt(now()->subMinutes(30)));
    }
}
