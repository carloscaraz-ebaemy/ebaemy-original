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

    /** Minutos que se espera a Shalom tras un intento sin respuesta antes de buscar la guía. */
    public const ESPERA_MIN = 3;

    public function __construct(private ShalomApi $api) {}

    /**
     * ¿Este envío lleva el rótulo OFICIAL de Shalom en vez del de ebaemy?
     *
     * Con la cuenta de la tienda conectada, la guía se crea desde ebaemy y el
     * rótulo sale de ella (trae número y código de Shalom). Imprimirle además
     * el de ebaemy era pegar dos rótulos distintos en el mismo paquete; en el
     * lote pasaba siempre, porque el lote no sabía de Shalom.
     */
    public static function replacesLabel(ShippingRequest $s, ?ShippingSetting $st = null): bool
    {
        if ($s->shipping_agency !== 'Shalom' || $s->delivery_type !== ShippingRequest::DELIVERY_AGENCIA) {
            return false;
        }
        // La tienda eligió para este paquete su propio rótulo y sacar la guía
        // en el mostrador. Si después se crea la guía desde ebaemy, vuelve a
        // mandar la guía.
        return self::accountReady($s, $st) && !self::usesStoreLabel($s);
    }

    /** ¿Se puede crear la guía de este paquete con la cuenta Shalom de la tienda? */
    public static function accountReady(ShippingRequest $s, ?ShippingSetting $st = null): bool
    {
        if ($s->shipping_agency !== 'Shalom' || $s->delivery_type !== ShippingRequest::DELIVERY_AGENCIA) {
            return false;
        }
        $st = $st ?? ShippingSetting::current();

        return ShalomPlan::allowsInstances() && !empty($st->shalom_instance_id) && !empty($st->shalom_origin_id);
    }

    /**
     * ¿Este paquete de Shalom va con el rótulo de la tienda y la guía del
     * mostrador? Pedidos de antes de conectar la cuenta, o cuando Shalom no
     * deja crear la guía (caída, datos que faltan) y el paquete tiene que salir.
     */
    public static function usesStoreLabel(ShippingRequest $s): bool
    {
        return !empty(($s->carrier_events ?? [])['store_label']) && !ShalomTracking::hasGuide($s);
    }

    /** Marca (o desmarca) el paquete para salir con el rótulo de la tienda. */
    public static function setStoreLabel(ShippingRequest $s, bool $on, ?string $nota = null): void
    {
        $ce = $s->carrier_events ?? [];
        if ((bool) ($ce['store_label'] ?? false) === $on) {
            return;
        }
        if ($on) {
            $ce['store_label'] = now()->toDateTimeString();
        } else {
            unset($ce['store_label']);
        }
        $s->forceFill(['carrier_events' => $ce])->save();
        \App\Models\Tenant\ShippingAuditLog::log(\App\Models\Tenant\ShippingAuditLog::ACTION_EDIT, $s->id, 'shalom_label',
            $on ? 'guía de Shalom' : 'rótulo de la tienda', $on ? 'rótulo de la tienda' : 'guía de Shalom',
            $nota ?: ($on ? 'Sale con el rótulo de la tienda; la guía se saca en el mostrador de Shalom.' : null));
    }

    /** Tamaño típico de la tienda para la guía, o null si no lo configuró. */
    public static function defaultContent(?ShippingSetting $st = null): ?string
    {
        $pk = ShalomPackage::normalize(($st ?? ShippingSetting::current())->shalom_package ?? null);
        if (!$pk) {
            return null;
        }

        return $pk['type'] === 'size' ? $pk['size'] : 'PAQUETE L';
    }

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
        if (empty($s->shalom_air) && (int) $s->agency_office_id === (int) $st->shalom_origin_id) {
            return 'La oficina de destino (' . ($s->agency_office_name ?: 'la elegida') . ') es la misma desde donde despachas: '
                . 'Shalom no envía a la misma oficina. Corrige la oficina del cliente en el envío.';
        }
        // Un intento que se cortó (tiempo agotado) pudo crear la guía en
        // Shalom igual. Pasados unos minutos, `recover()` la busca en la
        // cuenta antes de crear otra; antes, Shalom puede seguir registrándola.
        $intento = ($s->carrier_events ?? [])['create_attempt_at'] ?? null;
        if ($intento && \Carbon\Carbon::parse($intento)->gt(now()->subMinutes(self::ESPERA_MIN))) {
            return 'Shalom todavía puede estar registrando la guía del intento anterior. Vuelve a intentar en '
                . self::ESPERA_MIN . ' minutos: antes de crear otra, la buscaremos en tu cuenta de Shalom.';
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
        // Intento anterior sin respuesta clara: el 28-10 un reintento a ciegas
        // dejó dos guías reales (importacionesdeywa, pedido 177). Primero se
        // busca en la cuenta de Shalom la que pudo quedar creada.
        if (!empty(($s->carrier_events ?? [])['create_attempt_at']) && !ShalomTracking::hasGuide($s)
            && \Carbon\Carbon::parse($s->carrier_events['create_attempt_at'])->lte(now()->subMinutes(self::ESPERA_MIN))) {
            if ($rec = $this->recover($s, $st)) {
                return $rec;
            }
            $s->refresh();
        }
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

        // Aéreo: destino «0» + ter_id y declaración jurada obligatoria.
        $aereo = !empty($s->shalom_air);
        if ($aereo && !in_array($declaracion, self::AIR_DECLARATIONS, true)) {
            throw new RuntimeException('El envío es aéreo: elige qué contiene el paquete (declaración jurada).');
        }
        $extra = $aereo ? ['aereo' => 1, 'declaracion_jurada' => $declaracion] : [];

        // Marca del intento ANTES de llamar: si se corta, no se reintenta a ciegas.
        $s->forceFill(['carrier_events' => array_merge($s->carrier_events ?? [], ['create_attempt_at' => now()->toDateTimeString()])])->saveQuietly();

        $sinRespuesta = 'La guía pudo crearse igual. Vuelve a intentar en ' . self::ESPERA_MIN
            . ' minutos: antes de crear otra, la buscaremos en tu cuenta de Shalom.';
        try {
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
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Tiempo agotado o sin conexión: no se sabe si se creó. La marca se queda.
            throw new RuntimeException('Shalom no respondió a tiempo. ' . $sinRespuesta);
        } catch (RuntimeException $e) {
            $codigo = (int) $e->getCode();
            if ($codigo >= 400 && $codigo < 500 && !in_array($codigo, [408, 429], true)) {
                // Rechazo claro: no se creó nada. Se puede corregir y reintentar ya.
                $this->clearAttempt($s);
                throw new RuntimeException('Shalom rechazó la guía: ' . preg_replace('/^Shalom API \d+:\s*/', '', $e->getMessage()));
            }
            throw new RuntimeException('Shalom tuvo un problema (' . $e->getMessage() . '). ' . $sinRespuesta);
        }

        if (isset($r['success']) && $r['success'] === false) {
            $this->clearAttempt($s);
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
        $this->warmPdfs($s);

        return $found + ['clave' => $clave];
    }

    /**
     * Busca en los envíos pendientes de la cuenta Shalom la guía de un intento
     * que no tuvo respuesta clara, y si hay UNA sola que calza (mismo DNI de
     * quien recoge, misma oficina de destino, registrada desde el intento) la
     * une al envío. Si hay varias, avisa cuáles anular; si no hay ninguna,
     * libera el envío para crearla de nuevo.
     *
     * @return array|null la guía recuperada, o null si no había nada que recuperar
     */
    public function recover(ShippingRequest $s, ?ShippingSetting $st = null): ?array
    {
        $st = $st ?? ShippingSetting::current();
        $ce = $s->carrier_events ?? [];
        $intento = $ce['create_attempt_at'] ?? null;
        if (!$intento || ShalomTracking::hasGuide($s) || empty($st->shalom_instance_id)) {
            return null;
        }
        $desde = \Carbon\Carbon::parse($intento)->subMinutes(5);

        try {
            $pend = $this->api->pendingShipments($st->shalom_instance_id);
        } catch (\Throwable $e) {
            // Sin poder mirar la cuenta no se crea otra a ciegas (media hora como mucho).
            if (\Carbon\Carbon::parse($intento)->gt(now()->subMinutes(30))) {
                throw new RuntimeException('No pudimos confirmar con Shalom si la guía del intento anterior se creó. '
                    . 'Revisa en pro.shalom.pe (Envíos pendientes) antes de reintentar, o prueba de nuevo en unos minutos.');
            }
            $this->clearAttempt($s);

            return null;
        }

        $dni = $this->recipientDni($s);
        $cands = collect(is_array($pend) ? $pend : [])->filter(function ($r) use ($s, $dni, $desde) {
            if (!is_array($r) || data_get($r, 'was_delete_of_empresarial') || data_get($r, 'was_deny_of_empresarial')) {
                return false;
            }
            $f = data_get($r, 'date_receive_of_empresarial');

            return (string) data_get($r, 'receiver_person.document') === $dni
                && (int) data_get($r, 'destination_station.ter_id') === (int) $s->agency_office_id
                && $f && \Carbon\Carbon::parse($f)->gte($desde);
        })->reject(fn ($r) => ShippingRequest::where('id', '!=', $s->id)
            ->where('tracking_number', (string) data_get($r, 'service_order_guia_empresarial'))->exists())
          ->values();

        if ($cands->count() > 1) {
            throw new RuntimeException('En tu cuenta de Shalom hay ' . $cands->count() . ' guías pendientes para este cliente desde el intento anterior ('
                . $cands->map(fn ($r) => data_get($r, 'service_order_guia_empresarial'))->implode(', ')
                . '). Anula en pro.shalom.pe las que sobren y vuelve a intentar.');
        }
        if ($cands->isEmpty()) {
            // No quedó creada: se puede crear de nuevo.
            $this->clearAttempt($s);

            return null;
        }

        $r = $cands->first();
        $found = [
            'number' => (string) data_get($r, 'service_order_guia_empresarial') ?: null,
            'code'   => strtoupper((string) data_get($r, 'code_service_order_empresarial')) ?: null,
            'ose_id' => (string) data_get($r, 'service_order_id_empresarial') ?: null,
        ];
        $clave = (string) (data_get($r, 'code_val') ?: ($ce['clave'] ?? ''));
        unset($ce['create_attempt_at']);
        $ce['ose_id'] = $found['ose_id'];
        $ce['clave'] = $clave;
        $ce['air'] = !empty($s->shalom_air);
        $ce['created_via'] = 'api-recuperada';
        $ce['created_at'] = now()->toDateTimeString();
        $ce['shalom_content'] = data_get($r, 'detail_service.0.type_product.name');
        $s->forceFill(['tracking_number' => $found['number'], 'tracking_code' => $found['code'], 'carrier_events' => $ce])->save();
        \App\Models\Tenant\ShippingAuditLog::log(\App\Models\Tenant\ShippingAuditLog::ACTION_EDIT, $s->id, 'shalom_guide', null, $found['number'],
            'Guía recuperada: el intento anterior sí la creó en Shalom, aunque no respondió a tiempo.');

        $tr = app(ShalomTracking::class);
        $tr->watch($s);
        $tr->refresh($s);
        $this->warmPdfs($s);

        return $found + ['clave' => $clave, 'recovered' => true];
    }

    private function clearAttempt(ShippingRequest $s): void
    {
        $ce = $s->carrier_events ?? [];
        if (array_key_exists('create_attempt_at', $ce)) {
            unset($ce['create_attempt_at']);
            $s->forceFill(['carrier_events' => $ce])->saveQuietly();
        }
    }

    /** DNI de quien recoge en Shalom: el del cliente, o el autorizado si el cliente es empresa (RUC). */
    private function recipientDni(ShippingRequest $s): string
    {
        $esEmpresa = strlen(preg_replace('/\D/', '', (string) $s->dni)) === 11;

        return preg_replace('/\D/', '', (string) ($esEmpresa ? $s->pickup_person_dni : $s->dni));
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

        return $this->cached($s, 'ticket', fn () => $this->api->voucher($st->shalom_instance_id, $ose ? ['ose_id' => $ose] : ['orderNumber' => $s->tracking_number, 'orderCode' => $s->tracking_code]));
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

        return $this->cached($s, 'rotulo', fn () => $this->api->officialLabel($st->shalom_instance_id, $guide));
    }

    /**
     * El PDF de Shalom se guarda la primera vez que se baja.
     *
     * Cada apertura del rótulo lo pedía a Shalom en vivo (varios segundos por
     * envío, y en un lote se sumaban), y para una misma guía el PDF no cambia.
     * El nombre lleva la guía: si se quita y se crea otra, sale un archivo
     * nuevo y el viejo no se vuelve a servir. Solo se guarda si es un PDF.
     */
    private function cached(ShippingRequest $s, string $doc, callable $bajar): string
    {
        $ce = $s->carrier_events ?? [];
        $guia = md5(($ce['ose_id'] ?? '') . '|' . $s->tracking_number . '|' . $s->tracking_code);
        $ruta = 'shalom-pdf/' . $s->getConnection()->getDatabaseName() . '/' . $s->id . '-' . $doc . '-' . $guia . '.pdf';
        $disco = \Illuminate\Support\Facades\Storage::disk('local');

        if ($disco->exists($ruta)) {
            return $disco->get($ruta);
        }
        $pdf = $bajar();
        if (is_string($pdf) && str_starts_with($pdf, '%PDF')) {
            $disco->put($ruta, $pdf);
        }

        return $pdf;
    }

    /**
     * Deja el rótulo y el ticket ya guardados, para que la primera vez que se
     * abran también salgan al instante. Corre después de responder: crear la
     * guía no espera por esto, y si falla, se bajan al abrirlos como antes.
     */
    public function warmPdfs(ShippingRequest $s): void
    {
        app()->terminating(function () use ($s) {
            foreach (['label', 'voucher'] as $m) {
                try {
                    $this->{$m}($s->fresh());
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::info('Shalom: no se pudo adelantar el ' . $m . ' del envío ' . $s->id . ': ' . $e->getMessage());
                }
            }
        });
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
