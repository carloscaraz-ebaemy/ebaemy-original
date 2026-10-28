<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Configuration;
use App\Models\Tenant\ShippingSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * La política de envío de la tienda: UNA fuente para su tienda online, el
 * marketplace ebaemy.com y el catálogo de anuncios.
 *
 *  - quote():   cuánto cuesta y cuánto tarda el envío a un destino (checkout).
 *  - summary(): el resumen que se copia en cada listing del marketplace.
 *  - save():    valida, guarda, la marca como confirmada y la empuja al marketplace.
 *
 * Mientras la tienda no la confirma (`shipping_confirmed_at` NULL) no se
 * presenta como cierta en ningún sitio y no se puede publicar en el
 * marketplace: ver MarketplaceShippingGate.
 *
 * Lima = provincias 1501 (Lima Metropolitana) y 0701 (Callao) del ubigeo; el
 * resto del departamento de Lima (Huaral, Cañete…) es provincia.
 */
class StoreShipping
{
    public const LIMA_MODES     = ['free', 'fixed', 'quote'];
    public const PROVINCE_MODES = ['free', 'fixed', 'destination', 'quote'];
    public const MAX_AGENCIES   = 12;

    private ?ShippingSetting $settings = null;

    public function available(): bool
    {
        try {
            return Schema::connection('tenant')->hasColumn('shipping_settings', 'shipping_confirmed_at');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function settings(): ShippingSetting
    {
        return $this->settings ??= (ShippingSetting::first() ?? new ShippingSetting());
    }

    public function isConfirmed(): bool
    {
        return $this->available() && $this->settings()->shipping_confirmed_at !== null;
    }

    public static function isLima(?string $ubigeo): bool
    {
        $u = preg_replace('/\D/', '', (string) $ubigeo);
        if (strlen($u) >= 4) {
            return in_array(substr($u, 0, 4), ['1501', '0701'], true);
        }

        return in_array($u, ['15', '07'], true);
    }

    /**
     * Envío a un destino. `cost` null = no se puede fijar ahora (a cotizar o
     * pago en destino): el checkout no lo suma y lo explica con `label`.
     *
     * @return array{type:string,mode:string,cost:?float,label:string,days_min:?int,days_max:?int,free_applied:bool}
     */
    public function quote(?string $ubigeo, bool $pickup = false, float $subtotal = 0.0): array
    {
        $st = $this->settings();

        if ($pickup && $st->pickup_enabled) {
            $h = $st->pickup_ready_hours;
            return ['type' => 'pickup', 'mode' => 'free', 'cost' => 0.0, 'label' => 'Recojo gratis',
                    'days_min' => 0, 'days_max' => $h ? (int) ceil($h / 24) : 0, 'free_applied' => false];
        }

        $lima = self::isLima($ubigeo);
        $mode = $lima ? (string) $st->lima_mode : (string) $st->province_mode;
        $cost = $lima ? $st->lima_cost : $st->province_cost;

        [$dmin, $dmax] = $lima
            ? [$st->lima_days_min, $st->lima_days_max]
            : [
                $st->province_handling_days !== null || $st->province_transit_min !== null
                    ? (int) $st->province_handling_days + (int) $st->province_transit_min : null,
                $st->province_handling_days !== null || $st->province_transit_max !== null
                    ? (int) $st->province_handling_days + (int) $st->province_transit_max : null,
            ];

        $out = ['type' => $lima ? 'lima' : 'province', 'mode' => $mode, 'cost' => null, 'label' => '',
                'days_min' => $dmin !== null ? (int) $dmin : null, 'days_max' => $dmax !== null ? (int) $dmax : null,
                'free_applied' => false];

        switch ($mode) {
            case 'free':
                $out['cost'] = 0.0;
                $out['label'] = 'Envío gratis';
                break;
            case 'fixed':
                $out['cost'] = round((float) $cost, 2);
                $out['label'] = 'Envío S/ ' . number_format($out['cost'], 2);
                break;
            case 'destination':
                // Lo que cobra la tienda es llevarlo a la agencia; el flete va aparte.
                $out['cost'] = $st->agency_charge;
                $out['label'] = 'Pago en destino a la agencia'
                    . ($st->agency_charge ? ' + S/ ' . number_format($st->agency_charge, 2) . ' de envío a agencia' : '');
                break;
            default: // quote
                $out['label'] = 'A cotizar' . ($cost ? ' desde S/ ' . number_format((float) $cost, 2) : '');
        }

        // Envío gratis por monto: solo sobre lo que cobra la tienda (no el flete en destino).
        $umbral = (float) (optional(Configuration::first())->ecommerce_free_shipping_threshold ?? 0);
        if ($umbral > 0 && $subtotal >= $umbral && in_array($mode, ['fixed', 'destination'], true) && $out['cost'] > 0) {
            $out['cost'] = 0.0;
            $out['free_applied'] = true;
            $out['label'] = $mode === 'destination' ? 'Envío a agencia gratis · flete en destino' : 'Envío gratis';
        }

        return $out;
    }

    /** Resumen para marketplace_listings.shipping; null si no está confirmada. */
    public function summary(): ?array
    {
        if (!$this->isConfirmed()) {
            return null;
        }

        return $this->buildSummary(
            $this->settings(),
            (float) (optional(Configuration::first())->ecommerce_free_shipping_threshold ?? 0)
        );
    }

    /**
     * Cómo lo vería el comprador con estos datos, SIN guardarlos: la vista
     * previa del formulario usa las mismas frases que el marketplace.
     */
    public function preview(array $d): array
    {
        $st = new ShippingSetting();
        $st->forceFill([
            'lima_mode' => $d['lima_mode'] ?? null, 'lima_cost' => $d['lima_cost'] ?? null,
            'lima_days_min' => $d['lima_days_min'] ?? null, 'lima_days_max' => $d['lima_days_max'] ?? null,
            'province_mode' => $d['province_mode'] ?? null, 'province_cost' => $d['province_cost'] ?? null,
            'province_handling_days' => $d['province_handling_days'] ?? null,
            'province_transit_min' => $d['province_transit_min'] ?? null,
            'province_transit_max' => $d['province_transit_max'] ?? null,
            'province_agencies' => $d['province_agencies'] ?? [],
            'pickup_enabled' => !empty($d['pickup_enabled']), 'pickup_district' => $d['pickup_district'] ?? null,
            'pickup_ready_hours' => $d['pickup_ready_hours'] ?? null,
            'agency_fee_mode' => $d['agency_fee_mode'] ?? null, 'agency_fee' => $d['agency_fee'] ?? null,
            'cutoff_time' => $d['cutoff_time'] ?? null,
        ]);
        $s = $this->buildSummary($st, (float) ($d['free_from'] ?? 0));

        return [
            'lines' => \App\Services\Marketplace\ShippingSummaryText::lines($s),
            'notes' => \App\Services\Marketplace\ShippingSummaryText::notes($s),
            'badge' => \App\Services\Marketplace\ShippingSummaryText::badge($s),
        ];
    }

    private function buildSummary(ShippingSetting $st, float $umbral): array
    {

        $pair = fn ($a, $b) => ($a === null && $b === null) ? null : [(int) ($a ?? $b), (int) ($b ?? $a)];

        return [
            'v'    => 1,
            'lima' => $st->lima_mode ? [
                'mode' => $st->lima_mode,
                'cost' => $st->lima_cost !== null ? (float) $st->lima_cost : null,
                'days' => $pair($st->lima_days_min, $st->lima_days_max),
            ] : null,
            'province' => $st->province_mode ? [
                'mode'       => $st->province_mode,
                'cost'       => $st->province_cost !== null ? (float) $st->province_cost : null,
                'agency_fee' => $st->province_mode === 'destination' ? $st->agency_charge : null,
                'handling'   => $st->province_handling_days,
                'transit'    => $pair($st->province_transit_min, $st->province_transit_max),
                'agencies'   => array_values(array_filter((array) ($st->province_agencies ?? []))),
            ] : null,
            'pickup' => $st->pickup_enabled ? [
                'district' => $st->pickup_district,
                'hours'    => $st->pickup_ready_hours,
            ] : null,
            'free_from' => $umbral > 0 ? $umbral : null,
            'cutoff'    => $st->cutoff_hhmm,
            // Cambios y devoluciones (StoreReturnsPolicy): viaja en el mismo
            // resumen para que la ficha diga «Cambios hasta N días».
            'returns'   => $this->returnsSummary(),
        ];
    }

    private function returnsSummary(): ?array
    {
        try {
            return app(StoreReturnsPolicy::class)->summary();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Guarda la cuenta Shalom Pro de la tienda y, si el plan de ebaemy ya
     * incluye instancias, la conecta (crea la instancia y hace login).
     * Con el plan Básico solo guarda: la conexión queda para cuando se active.
     *
     * @return array{saved:bool, connected:bool, message:string}
     */
    public function saveShalomAccount(?string $user, ?string $password, ?string $clave = null, bool $soloClave = false): array
    {
        $st = $this->settings();
        $schema = \Illuminate\Support\Facades\Schema::connection('tenant');
        if (!$schema->hasColumn('shipping_settings', 'shalom_pro_user')) {
            return ['saved' => false, 'connected' => false, 'message' => 'Falta actualizar la base de datos de la tienda.'];
        }

        // La clave fija se guarda sola, sin tocar la cuenta ni reconectar.
        if ($schema->hasColumn('shipping_settings', 'shalom_default_clave')) {
            $st->shalom_default_clave = preg_match('/^\d{4}$/', (string) $clave) ? $clave : null;
        }
        if ($soloClave) {
            $st->save();

            return ['saved' => true, 'connected' => !empty($st->shalom_instance_id), 'message' => $st->shalom_default_clave
                ? "Clave de recojo {$st->shalom_default_clave} guardada para todas las guías."
                : 'Sin clave fija: cada guía tendrá su propia clave.'];
        }

        $st->shalom_pro_user = $user ?: null;
        if ($password !== null && $password !== '') {
            $st->shalom_pro_password = $password;
        }
        if (!$user) {                        // quitar la cuenta = desconectar
            $st->shalom_pro_password = null;
            $st->shalom_instance_id = null;
            $st->shalom_instance_connected_at = null;
        }
        $st->save();

        if (!$user || !\App\Services\System\ShalomPlan::allowsInstances()) {
            return ['saved' => true, 'connected' => false, 'message' => $user
                ? 'Cuenta de Shalom Pro guardada. Se conectará cuando ebaemy active la creación de guías.'
                : 'Cuenta de Shalom Pro quitada.'];
        }

        if (empty($st->getRawOriginal('shalom_pro_password'))) {
            return ['saved' => true, 'connected' => false, 'message' => 'Falta la contraseña de Shalom Pro.'];
        }

        $api = app(\App\Services\System\ShalomApi::class);
        try {
            if (!$st->shalom_instance_id) {
                $host = optional(app(\Hyn\Tenancy\Environment::class)->hostname())->fqdn ?: 'tienda';
                $st->shalom_instance_id = $api->createInstance($host);
                $st->save();
            }
            $r = $api->loginInstance($st->shalom_instance_id, $st->shalom_pro_user, $st->shalom_pro_password);
            if (empty($r['success'])) {
                return ['saved' => true, 'connected' => false, 'message' => 'Shalom Pro no aceptó el usuario o la contraseña.'];
            }
            $st->shalom_instance_connected_at = now();
            $st->save();
        } catch (\Throwable $e) {
            return ['saved' => true, 'connected' => false, 'message' => 'Guardada, pero no se pudo conectar: ' . $e->getMessage()];
        }

        return ['saved' => true, 'connected' => true, 'message' => 'Cuenta de Shalom Pro conectada.'];
    }

    /** La oficina de Shalom (catálogo del sistema) para mostrarla en el panel. */
    public function shalomOffice($terId): ?array
    {
        if (!$terId) {
            return null;
        }
        try {
            $a = \App\Models\System\ShalomAgency::find((int) $terId);
        } catch (\Throwable $e) {
            return null;
        }

        return $a ? ['id' => $a->ter_id, 'name' => $a->name, 'place' => trim($a->province . ', ' . $a->department, ', '), 'address' => $a->address] : null;
    }

    /** Datos para el formulario: lo guardado, o una propuesta a partir de lo que ya hay. */
    public function formData(): array
    {
        $st = $this->settings();
        $confirmed = $this->isConfirmed();

        return [
            'confirmed'          => $confirmed,
            'confirmed_at'       => optional($st->shipping_confirmed_at)->format('Y-m-d H:i'),
            'lima_mode'          => $st->lima_mode ?: 'fixed',
            'lima_cost'          => $st->lima_cost !== null ? (float) $st->lima_cost : null,
            'lima_days_min'      => $st->lima_days_min ?? 1,
            'lima_days_max'      => $st->lima_days_max ?? 2,
            'province_mode'      => $st->province_mode ?: 'destination',
            'province_cost'      => $st->province_cost !== null ? (float) $st->province_cost : null,
            'province_handling_days' => $st->province_handling_days ?? 1,
            'province_transit_min'   => $st->province_transit_min ?? 2,
            'province_transit_max'   => $st->province_transit_max ?? 5,
            'province_agencies'  => $st->province_agencies ?: ['Shalom'],
            'agency_fee_mode'    => $st->fee_mode,
            'agency_fee'         => $st->agency_fee !== null ? (float) $st->agency_fee : null,
            'pickup_enabled'     => (bool) $st->pickup_enabled,
            'pickup_district'    => $st->pickup_district,
            'pickup_ready_hours' => $st->pickup_ready_hours ?? 24,
            'free_from'          => (float) (optional(Configuration::first())->ecommerce_free_shipping_threshold ?? 0) ?: null,
            'cutoff_time'        => $st->cutoff_hhmm,
            'agencies_catalog'   => \App\Models\Tenant\ShippingRequest::AGENCIES,
            // Oficina de Shalom de origen: con ella se cotiza el viaje real.
            'shalom_origin_id'   => $st->shalom_origin_id ? (int) $st->shalom_origin_id : null,
            'shalom_origin'      => $this->shalomOffice($st->shalom_origin_id),
            // Cuenta Shalom Pro de la tienda (la contraseña nunca viaja).
            'shalom_pro'         => [
                'user'         => $st->shalom_pro_user,
                'has_password' => !empty($st->getRawOriginal('shalom_pro_password')),
                'connected'    => !empty($st->shalom_instance_id),
                'connected_at' => optional($st->shalom_instance_connected_at)->format('d/m/Y H:i'),
                'plan_allows'  => \App\Services\System\ShalomPlan::allowsInstances(),
                'clave'        => $st->shalom_default_clave,
            ],
        ];
    }

    public static function rules(): array
    {
        return [
            'lima_mode'      => ['required', Rule::in(self::LIMA_MODES)],
            'lima_cost'      => ['nullable', 'numeric', 'min:0', 'max:9999', 'required_if:lima_mode,fixed'],
            'lima_days_min'  => ['required', 'integer', 'min:0', 'max:30'],
            'lima_days_max'  => ['required', 'integer', 'min:0', 'max:30', 'gte:lima_days_min'],
            'province_mode'  => ['required', Rule::in(self::PROVINCE_MODES)],
            'province_cost'  => ['nullable', 'numeric', 'min:0', 'max:9999', 'required_if:province_mode,fixed'],
            'province_handling_days' => ['required', 'integer', 'min:0', 'max:15'],
            'province_transit_min'   => ['required', 'integer', 'min:0', 'max:30'],
            'province_transit_max'   => ['required', 'integer', 'min:0', 'max:30', 'gte:province_transit_min'],
            // El mismo tope que el selector (`MAX_AGENCIES` en el formulario).
            // Estaba en 6 sin que el selector lo supiera: elegir 7 agencias
            // devolvia 422 y el vendedor veia «No se pudo guardar».
            'province_agencies'      => ['nullable', 'array', 'max:' . self::MAX_AGENCIES],
            'province_agencies.*'    => ['string', 'max:60'],
            'agency_fee_mode'        => ['nullable', Rule::in(array_keys(ShippingSetting::FEE_MODES))],
            'agency_fee'             => ['nullable', 'numeric', 'min:0', 'max:999', 'required_if:agency_fee_mode,amount'],
            'pickup_enabled'         => ['boolean'],
            'pickup_district'        => ['nullable', 'string', 'max:120', 'required_if:pickup_enabled,true'],
            'pickup_ready_hours'     => ['nullable', 'integer', 'min:1', 'max:720'],
            'free_from'              => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'cutoff_time'            => ['nullable', 'date_format:H:i'],
            'shalom_origin_id'       => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** Guarda y confirma la política; la empuja a los listings del marketplace. */
    public function save(array $d): void
    {
        $st = $this->settings();
        $st->fill([
            'lima_mode'              => $d['lima_mode'],
            'lima_cost'              => $d['lima_mode'] === 'free' ? null : ($d['lima_cost'] ?? null),
            'lima_days_min'          => $d['lima_days_min'],
            'lima_days_max'          => $d['lima_days_max'],
            'province_mode'          => $d['province_mode'],
            'province_cost'          => in_array($d['province_mode'], ['free', 'destination'], true) ? null : ($d['province_cost'] ?? null),
            'province_handling_days' => $d['province_handling_days'],
            'province_transit_min'   => $d['province_transit_min'],
            'province_transit_max'   => $d['province_transit_max'],
            'province_agencies'      => array_values(array_unique(array_filter($d['province_agencies'] ?? []))),
            'pickup_enabled'         => (bool) ($d['pickup_enabled'] ?? false),
            'pickup_district'        => !empty($d['pickup_enabled']) ? ($d['pickup_district'] ?? null) : null,
            'pickup_ready_hours'     => !empty($d['pickup_enabled']) ? ($d['pickup_ready_hours'] ?? null) : null,
            'cutoff_time'            => $d['cutoff_time'] ?? null,
            'shipping_confirmed_at'  => now(),
        ]);
        // Hasta que la migración pase por todas las tiendas, la columna puede
        // no existir: no se escribe y el resto de la política se guarda igual.
        if (array_key_exists('shalom_origin_id', $d)
            && \Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('shipping_settings', 'shalom_origin_id')) {
            $st->shalom_origin_id = $d['shalom_origin_id'] ?: null;
        }
        // La tarifa «llevarlo a la agencia» es la misma que usa el módulo de Envíos.
        if (!empty($d['agency_fee_mode'])) {
            $st->agency_fee_mode = $d['agency_fee_mode'];
            $st->agency_fee = $d['agency_fee_mode'] === ShippingSetting::FEE_AMOUNT ? ($d['agency_fee'] ?? 0) : null;
        }
        $st->save();

        $conf = Configuration::first();
        if ($conf && array_key_exists('free_from', $d)) {
            $conf->ecommerce_free_shipping_threshold = $d['free_from'] ?: null;
            $conf->save();
        }

        $this->pushToMarketplace();
    }

    /** Copia el resumen en todos los listings de esta tienda (best-effort). */
    public function pushToMarketplace(): int
    {
        try {
            $hostname = app(\Hyn\Tenancy\Contracts\CurrentHostname::class);
            if (!$hostname) {
                return 0;
            }

            return app(\App\Services\System\MarketplaceListingSyncService::class)
                ->refreshShipping((int) $hostname->id, $this->summary());
        } catch (\Throwable $e) {
            Log::warning('[StoreShipping] no se pudo empujar el envío al marketplace', ['error' => $e->getMessage()]);

            return 0;
        }
    }
}
