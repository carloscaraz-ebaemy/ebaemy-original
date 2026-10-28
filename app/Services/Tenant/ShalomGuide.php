<?php

namespace App\Services\Tenant;

use App\Models\Tenant\ShippingRequest;
use App\Models\Tenant\ShippingSetting;
use App\Services\System\ShalomApi;
use App\Services\System\ShalomPlan;
use RuntimeException;

/**
 * Crear la guía de Shalom desde ebaemy, en la cuenta Shalom Pro de la tienda
 * (su instancia), ANTES de llevar el paquete a la agencia.
 *
 * Origen = oficina «Despacho desde» de la tienda; destino = la oficina que
 * eligió el cliente. Shalom devuelve la guía y su código, que quedan en el
 * envío: el rótulo oficial se baja con ellos y el seguimiento arranca solo.
 *
 * La respuesta de Shalom es un «espejo» sin esquema publicado: el número, el
 * código y el ose_id se buscan por nombre en toda la respuesta, y si no
 * aparecen se buscan en los envíos pendientes de la cuenta. La respuesta
 * cruda se guarda (recortada) para poder revisar qué devolvió.
 */
class ShalomGuide
{
    /** Tamaños que Shalom reconoce en `content` (automatizan tipo y costo). */
    public const CONTENTS = ['SOBRE', 'PAQUETE XXS', 'PAQUETE XS', 'PAQUETE S', 'PAQUETE M', 'PAQUETE L'];

    public function __construct(private ShalomApi $api) {}

    /** Por qué no se puede crear la guía de este envío, o null si se puede. */
    public static function blocker(ShippingRequest $s, ?ShippingSetting $st = null): ?string
    {
        $st = $st ?? ShippingSetting::current();
        if (!ShalomPlan::allowsInstances()) {
            return 'El plan de Shalom de ebaemy no incluye crear guías.';
        }
        if ($s->shipping_agency !== 'Shalom') {
            return 'El envío no va por Shalom.';
        }
        if (empty($st->shalom_instance_id)) {
            return 'Conecta tu cuenta de Shalom Pro en la política de envío.';
        }
        if (empty($st->shalom_origin_id)) {
            return 'Elige en la política de envío la oficina de Shalom desde donde despachas.';
        }
        if (empty($s->agency_office_id)) {
            return 'El cliente no eligió su oficina de Shalom: complétala en el envío.';
        }
        if (!empty($s->tracking_code) || !empty(($s->carrier_events ?? [])['ose_id'])) {
            return 'Este envío ya tiene guía de Shalom.';
        }
        // Un intento que se cortó (tiempo agotado) pudo crear la guía en
        // Shalom igual: reintentar a ciegas deja dos guías reales.
        $intento = ($s->carrier_events ?? [])['create_attempt_at'] ?? null;
        if ($intento && \Carbon\Carbon::parse($intento)->gt(now()->subMinutes(15))) {
            return 'Hace poco se intentó crear esta guía y no hubo respuesta clara de Shalom. Revisa en pro.shalom.pe '
                . 'si se creó antes de reintentar (podrás reintentar en 15 minutos).';
        }

        return null;
    }

    /**
     * @return array{number:?string, code:?string, ose_id:?string, clave:string}
     */
    /** Lo que Shalom acepta como declaración jurada en envíos aéreos. */
    public const AIR_DECLARATIONS = ['Artículos de uso personal', 'Documentos', 'Ropa', 'Electrodomésticos'];

    public function create(ShippingRequest $s, string $content, ?string $clave = null, ?string $declaracion = null): array
    {
        // Doble clic o dos operadores a la vez: una sola creación por envío.
        $lock = \Illuminate\Support\Facades\Cache::lock('shalom_guide:' . $s->getConnection()->getDatabaseName() . ':' . $s->id, 180);
        if (!$lock->get()) {
            throw new RuntimeException('Ya se está creando la guía de este envío. Espera un momento.');
        }
        try {
            return $this->createLocked($s->fresh(), $content, $clave, $declaracion);
        } finally {
            $lock->release();
        }
    }

    private function createLocked(ShippingRequest $s, string $content, ?string $clave, ?string $declaracion = null): array
    {
        $st = ShippingSetting::current();
        if ($why = self::blocker($s, $st)) {
            throw new RuntimeException($why);
        }
        if (!in_array($content, self::CONTENTS, true)) {
            throw new RuntimeException('Elige el tamaño del paquete.');
        }
        try {
            $vigente = \App\Models\System\ShalomAgency::selectable()->find((int) $s->agency_office_id);
        } catch (\Throwable $e) {
            $vigente = true;   // sin catálogo: que decida Shalom
        }
        if (!$vigente) {
            throw new RuntimeException('La oficina ' . ($s->agency_office_name ?: '') . ' ya no recibe envíos de Shalom. Elige otra en el envío.');
        }

        // Empresa (RUC): Shalom entrega a una PERSONA con DNI.
        $esEmpresa = strlen(preg_replace('/\D/', '', (string) $s->dni)) === 11;
        $dni = preg_replace('/\D/', '', (string) ($esEmpresa ? $s->pickup_person_dni : $s->dni));
        $fullName = (string) ($esEmpresa ? $s->pickup_person_name : $s->full_name);
        if (strlen($dni) !== 8) {
            throw new RuntimeException('Shalom necesita el DNI (8 dígitos) de quien recoge.');
        }
        $phone = preg_replace('/\D/', '', (string) $s->phone);
        $phone = strlen($phone) > 9 ? substr($phone, -9) : $phone;
        if (strlen($phone) !== 9) {
            throw new RuntimeException('Shalom necesita un celular de 9 dígitos.');
        }

        [$nombres, $paterno, $materno] = $this->names($fullName, $dni);
        // Prioridad: la del diálogo, la clave fija de la tienda, o una nueva.
        $clave = preg_match('/^\d{4}$/', (string) $clave) ? $clave
            : (preg_match('/^\d{4}$/', (string) ($st->shalom_default_clave ?? '')) ? $st->shalom_default_clave
            : str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT));

        // Marca del intento ANTES de llamar: si se corta, no se reintenta a ciegas.
        $s->forceFill(['carrier_events' => array_merge($s->carrier_events ?? [], ['create_attempt_at' => now()->toDateTimeString()])])->saveQuietly();

        // Aéreo: destino «0» + ter_id y declaración jurada obligatoria.
        $aereo = !empty($s->shalom_air);
        if ($aereo && !in_array($declaracion, self::AIR_DECLARATIONS, true)) {
            throw new RuntimeException('El envío es aéreo: elige qué contiene el paquete (declaración jurada).');
        }
        $extra = $aereo ? ['aereo' => 1, 'declaracion_jurada' => $declaracion] : [];

        $r = $this->api->registerGuide($st->shalom_instance_id, $extra + [
            'origen'    => (int) $st->shalom_origin_id,
            'destino'   => $aereo ? '0' . (int) $s->agency_office_id : (int) $s->agency_office_id,
            'documento' => $dni,
            'name'      => $nombres,
            'firstname' => $paterno,
            'lastname'  => $materno,
            'phone'     => (int) $phone,
            'content'   => $content,
            'cantidad'  => max(1, (int) ($s->package_count ?: 1)),
            'clave'     => $clave,
        ]);

        if (isset($r['success']) && $r['success'] === false) {
            throw new RuntimeException('Shalom rechazó la guía: ' . ($r['message'] ?? $r['error'] ?? 'sin detalle'));
        }

        $found = $this->extract($r);
        if (!$found['number'] || !$found['code']) {
            try {
                $pend = $this->api->pendingShipments($st->shalom_instance_id);
                $found = array_merge($found, array_filter($this->extract($this->matchPending($pend, $dni, $found['ose_id']))));
            } catch (\Throwable $e) {
                // Sin pendientes: queda con lo que haya (el ose_id basta para el rótulo).
            }
        }

        $ce = $s->carrier_events ?: [];
        unset($ce['create_attempt_at']);
        $ce['ose_id'] = $found['ose_id'];
        $ce['clave'] = $clave;
        $ce['content'] = $content;
        $ce['air'] = $aereo;
        $ce['created_via'] = 'api';
        $ce['created_at'] = now()->toDateTimeString();
        $ce['register_raw'] = mb_substr(json_encode($r, JSON_UNESCAPED_UNICODE), 0, 4000);

        $s->forceFill([
            'tracking_number' => $found['number'] ?: $s->tracking_number,
            'tracking_code'   => $found['code'] ?: $s->tracking_code,
            'carrier_events'  => $ce,
        ])->save();

        $tr = app(ShalomTracking::class);
        $tr->watch($s);
        $tr->refresh($s);

        return $found + ['clave' => $clave];
    }

    /** Ticket oficial de Shalom (PDF con el QR que escanea la agencia). */
    public function voucher(ShippingRequest $s): string
    {
        $st = ShippingSetting::current();
        if (empty($st->shalom_instance_id)) {
            throw new RuntimeException('Conecta tu cuenta de Shalom Pro en la configuración de envíos.');
        }
        $ose = ($s->carrier_events ?? [])['ose_id'] ?? null;
        if (!$ose && (!$s->tracking_number || !$s->tracking_code)) {
            throw new RuntimeException('El envío todavía no tiene guía de Shalom.');
        }

        return $this->api->voucher($st->shalom_instance_id, $ose ? ['ose_id' => $ose] : ['orderNumber' => $s->tracking_number, 'orderCode' => $s->tracking_code]);
    }

    /** Rótulo oficial de Shalom (PDF). */
    public function label(ShippingRequest $s): string
    {
        $st = ShippingSetting::current();
        if (empty($st->shalom_instance_id)) {
            throw new RuntimeException('Conecta tu cuenta de Shalom Pro en la política de envío.');
        }
        $ose = ($s->carrier_events ?? [])['ose_id'] ?? null;
        $guide = $ose ? ['ose_id' => $ose] : ['orderNumber' => $s->tracking_number, 'orderCode' => $s->tracking_code];
        if (!$ose && (!$s->tracking_number || !$s->tracking_code)) {
            throw new RuntimeException('El envío todavía no tiene guía de Shalom.');
        }

        return $this->api->officialLabel($st->shalom_instance_id, $guide);
    }

    /**
     * Nombres y apellidos por separado. El nombre que guarda el sistema viene
     * de RENIEC como «PATERNO MATERNO, NOMBRES»; si no tiene coma se pide a
     * Shalom (GET /account/dni) y, como último recurso, se parte por palabras.
     *
     * @return array{0:string,1:string,2:string}
     */
    private function names(string $full, string $dni): array
    {
        $full = trim(preg_replace('/\s+/', ' ', mb_strtoupper($full)));
        if (str_contains($full, ',')) {
            [$ap, $no] = array_map('trim', explode(',', $full, 2));
            $aps = explode(' ', $ap, 2);
            if ($no !== '' && $aps[0] !== '') {
                return [$no, $aps[0], $aps[1] ?? '-'];
            }
        }

        try {
            $d = $this->api->dni($dni);
            $no = $this->find($d, ['nombres', 'name', 'nombre', 'prenombres']);
            $pa = $this->find($d, ['apellidopaterno', 'apellido_paterno', 'paterno', 'firstname', 'ape_paterno']);
            $ma = $this->find($d, ['apellidomaterno', 'apellido_materno', 'materno', 'lastname', 'ape_materno']);
            if ($no && $pa) {
                return [mb_strtoupper($no), mb_strtoupper($pa), mb_strtoupper($ma ?: '-')];
            }
        } catch (\Throwable $e) {
        }

        // «NOMBRES PATERNO MATERNO»: los dos últimos son apellidos.
        $w = explode(' ', $full);
        if (count($w) >= 3) {
            $ma = array_pop($w);
            $pa = array_pop($w);

            return [implode(' ', $w), $pa, $ma];
        }

        return [$w[0] ?? $full, $w[1] ?? '-', '-'];
    }

    /** Número de guía, código y ose_id, busquen donde busquen en la respuesta. */
    private function extract($r): array
    {
        $num = $this->find($r, ['numero_orden', 'orden_numero', 'ordernumber', 'order_number', 'nro_orden', 'numero_guia', 'guia', 'numero', 'ose_numero'], '/^\d{6,12}$/');
        $code = $this->find($r, ['codigo_orden', 'ordercode', 'order_code', 'codigo_seguridad', 'codigo', 'ose_codigo', 'code'], '/^[A-Z0-9]{4}$/i');
        $ose = $this->find($r, ['ose_id', 'service_order_id', 'serviceorderid'], '/^\d+$/');

        return ['number' => $num, 'code' => $code ? strtoupper($code) : null, 'ose_id' => $ose];
    }

    /** El envío pendiente que corresponde: por ose_id, o el último de ese DNI. */
    private function matchPending($pend, string $dni, ?string $ose): array
    {
        $rows = [];
        $walk = function ($v) use (&$walk, &$rows) {
            if (is_array($v)) {
                if ($this->find($v, ['ose_id', 'service_order_id'], null, false)) {
                    $rows[] = $v;
                }
                foreach ($v as $x) {
                    $walk($x);
                }
            }
        };
        $walk($pend);
        foreach ($rows as $row) {
            if ($ose && (string) $this->find($row, ['ose_id', 'service_order_id'], null, false) === (string) $ose) {
                return $row;
            }
        }
        // Sin ose_id no se adivina: el mismo DNI puede tener otras guías
        // pendientes y asignar la equivocada es peor que no asignar.
        return [];
    }

    /** Busca una clave (sin mayúsculas ni guiones) en todo el árbol; opcionalmente que el valor cumpla un patrón. */
    private function find($data, array $keys, ?string $pattern = null, bool $deep = true): ?string
    {
        if (!is_array($data)) {
            return null;
        }
        foreach ($data as $k => $v) {
            if (is_string($k) && in_array(strtolower(str_replace('-', '_', $k)), $keys, true) && (is_string($v) || is_int($v))) {
                $v = trim((string) $v);
                if ($v !== '' && (!$pattern || preg_match($pattern, $v))) {
                    return $v;
                }
            }
        }
        if ($deep) {
            foreach ($data as $v) {
                if (is_array($v) && ($hit = $this->find($v, $keys, $pattern, true))) {
                    return $hit;
                }
            }
        }

        return null;
    }
}
