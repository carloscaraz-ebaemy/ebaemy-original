<?php

namespace App\Services\System;

use App\Services\Marketplace\ShippingSummaryText;
use App\Services\Tenant\StoreShipping;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Hostname;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * «¿Cómo lo recibes?» del checkout central: por cada tienda del carrito, las
 * modalidades que el comprador puede elegir hacia su destino, con costo y
 * plazo; y la validación server-side de lo que eligió.
 *
 * Fuente de las tarifas: el resumen de envío que cada tienda confirmó
 * (`marketplace_listings.shipping`, viaja en la línea del carrito) leído con
 * ShippingSummaryText::forZone, la MISMA redacción que ven la ficha y el carrito.
 * No toca la base de ninguna tienda para cotizar.
 *
 * Modalidades: pickup (la tienda lo ofrece), delivery (destino Lima/Callao) y
 * agency (destino provincia, con oficina de Shalom). Una tienda sin política
 * confirmada NO bloquea la compra: se elige igual y el costo lo confirma ella
 * por WhatsApp.
 *
 * El destino es UNO por compra (departamento/provincia/distrito); el ubigeo de
 * 6 cifras se resuelve aquí desde esos nombres, nunca se confía en el del
 * navegador. El catálogo sale de la tabla `districts` de una tienda y se
 * guarda en caché (la base system no tiene ubigeo).
 */
class MarketplaceCheckoutShipping
{
    public const MODE_PICKUP   = 'pickup';
    public const MODE_DELIVERY = 'delivery';
    public const MODE_AGENCY   = 'agency';

    public const PENDING_NOTE = 'El costo de envío te lo confirma la tienda por WhatsApp';

    private const CATALOG_KEY = 'mp_checkout_ubigeo_v1';

    public function __construct(private ShalomAgencyCatalog $shalom) {}

    // ── Destino ────────────────────────────────────────────────────────────

    /** Normaliza para comparar nombres: sin tildes, minúsculas, un solo espacio. */
    public static function fold(?string $t): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_strtolower(Str::ascii((string) $t))));
    }

    /** «Pariñas» / «PARIÑAS» → «Pariñas»; solo cambia lo escrito en mayúsculas. */
    public static function pretty(?string $t): string
    {
        $t = trim((string) $t);
        if ($t !== '' && $t === mb_strtoupper($t)) {
            $t = mb_convert_case(mb_strtolower($t), MB_CASE_TITLE, 'UTF-8');
            $t = preg_replace_callback('/ (De|Del|La|Las|Los|Y) /u', fn ($m) => mb_strtolower($m[0]), $t);
        }

        return $t;
    }

    /** «Talara (Piura)»; en Lima y Callao basta el distrito y la ciudad. */
    public static function placeLabel(?string $dep, ?string $prov, ?string $dist): string
    {
        $d = self::pretty($dist);
        $e = self::pretty($dep);
        if ($d === '') {
            return '';
        }

        // «Pariñas, Talara (Piura)»: la provincia ayuda cuando el distrito no
        // es lo que el comprador llama su ciudad; se omite si repite otro nombre.
        $p = self::pretty($prov);
        if ($p !== '' && self::fold($p) !== self::fold($d) && self::fold($p) !== self::fold($e)) {
            $d .= ', ' . $p;
        }

        return ($e !== '' && self::fold($e) !== self::fold($d)) ? $d . ' (' . $e . ')' : $d;
    }

    /** 'lima' (Lima Metropolitana o Callao), 'province', o null si no hay destino. */
    public function zone(?string $ubigeo): ?string
    {
        $u = preg_replace('/\D+/', '', (string) $ubigeo);
        if (strlen($u) !== 6) {
            return null;
        }

        return StoreShipping::isLima($u) ? 'lima' : 'province';
    }

    /**
     * Ubigeo INEI de 6 cifras para los nombres que eligió el comprador, o null
     * si no coinciden EXACTAMENTE con un distrito (no se adivina).
     *
     * @param array<int,int|string> $hostnameIds tiendas del carrito: de una de ellas se lee el catálogo
     */
    public function resolveUbigeo(?string $dep, ?string $prov, ?string $dist, array $hostnameIds = []): ?string
    {
        if (self::fold($dist) === '' || self::fold($prov) === '' || self::fold($dep) === '') {
            return null;
        }
        $map = $this->catalog($hostnameIds);
        $code = $map[self::fold($dep) . '|' . self::fold($prov) . '|' . self::fold($dist)] ?? null;

        return is_string($code) && $code !== '' ? $code : null;
    }

    /** ¿El código existe en el catálogo? (para validar un ubigeo recibido). */
    public function ubigeoExists(string $ubigeo, array $hostnameIds = []): bool
    {
        return in_array($ubigeo, $this->catalog($hostnameIds), true);
    }

    /** @return array<string,string> «dep|prov|dist» (plegado) → ubigeo */
    private function catalog(array $hostnameIds): array
    {
        $cached = Cache::get(self::CATALOG_KEY);
        if (is_array($cached) && $cached) {
            return $cached;
        }

        $tenancy = app(Environment::class);
        $previous = $tenancy->tenant();
        $ids = array_values(array_unique(array_map('intval', $hostnameIds)));
        // Si ninguna tienda del carrito responde, cualquier otra sirve: el INEI es el mismo.
        try {
            $ids = array_merge($ids, Hostname::query()->whereNotNull('website_id')->orderBy('id')->limit(3)->pluck('id')->all());
        } catch (\Throwable $e) {
            // se sigue con las del carrito
        }

        foreach (array_unique($ids) as $id) {
            try {
                $host = Hostname::find($id);
                if (!$host || !$host->website) {
                    continue;
                }
                $tenancy->tenant($host->website);
                $rows = DB::connection('tenant')->table('districts as d')
                    ->join('provinces as p', 'p.id', '=', 'd.province_id')
                    ->join('departments as dep', 'dep.id', '=', 'p.department_id')
                    ->get(['d.id as code', 'd.description as dist', 'p.description as prov', 'dep.description as dep']);
                if ($rows->count() < 1000) {
                    continue;   // catálogo incompleto en esa tienda: probar otra
                }
                $map = [];
                foreach ($rows as $r) {
                    $k = self::fold($r->dep) . '|' . self::fold($r->prov) . '|' . self::fold($r->dist);
                    $map[$k] = array_key_exists($k, $map) ? '' : str_pad((string) $r->code, 6, '0', STR_PAD_LEFT);
                }
                Cache::put(self::CATALOG_KEY, $map, now()->addDay());

                return $map;
            } catch (\Throwable $e) {
                Log::warning('[MarketplaceCheckoutShipping] catálogo de ubigeo no disponible', ['host' => $id, 'error' => $e->getMessage()]);
            } finally {
                $tenancy->tenant($previous ?: null);
            }
        }

        return [];
    }

    // ── Opciones por tienda ────────────────────────────────────────────────

    /**
     * Todo lo que el navegador necesita para pintar «¿Cómo lo recibes?».
     *
     * Solo devuelve las tiendas del carrito de ESTA sesión y solo lo que el
     * comprador va a ver; nada de otras tiendas.
     *
     * @param Collection $stores resultado de MarketplaceCartService::groupedByStore()
     */
    public function quote(Collection $stores, ?string $ubigeo, string $placeLabel = ''): array
    {
        $zone = $this->zone($ubigeo);
        $offices = ['scope' => 'none', 'offices' => [], 'others' => []];
        if ($zone === 'province') {
            try {
                $offices = $this->shalom->forDistrict((string) $ubigeo);
            } catch (\Throwable $e) {
                Log::info('[MarketplaceCheckoutShipping] sin catálogo de oficinas', ['error' => $e->getMessage()]);
            }
        }
        $hasOffices = !empty($offices['offices']) || !empty($offices['others']);

        $out = [];
        foreach ($stores as $store) {
            $out[] = [
                'hostname_id' => (int) $store['hostname_id'],
                'name'        => (string) ($store['tenant_name'] ?: $store['tenant_fqdn']),
                'has_policy'  => is_array($store['shipping'] ?? null),
                'options'     => $this->optionsFor($store, $zone, $placeLabel, $hasOffices),
            ];
        }

        return [
            'ubigeo'  => $zone ? $ubigeo : null,
            'zone'    => $zone,
            'place'   => $placeLabel,
            'stores'  => $out,
            'offices' => $offices,
            'pending_note' => self::PENDING_NOTE,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function optionsFor(array $store, ?string $zone, string $placeLabel = '', bool $hasOffices = false): array
    {
        $s = is_array($store['shipping'] ?? null) ? $store['shipping'] : null;
        $subtotal = (float) ($store['subtotal'] ?? 0);
        $options = [];

        if (!empty($s['pickup'])) {
            $k = $s['pickup'];
            $donde = !empty($k['district']) ? self::pretty($k['district']) : null;
            $hours = !empty($k['hours']) ? (int) $k['hours'] : null;
            $options[] = [
                'mode'           => self::MODE_PICKUP,
                'title'          => 'Recojo en tienda',
                'price'          => 'Gratis',
                'amount'         => 0.0,
                'pending'        => false,
                'free'           => true,
                'eta'            => $hours ? 'Listo en ' . $hours . ($hours === 1 ? ' hora' : ' horas') : 'La tienda te avisa cuando esté listo',
                'detail'         => $donde ? 'Lo recoges en ' . $donde : 'Lo recoges en la tienda; te dicen dónde por WhatsApp',
                'where'          => $donde,
                'needs_office'   => false,
                'office_required' => false,
                'needs_address'  => false,
            ];
        }

        if ($zone === 'lima' || $zone === 'province') {
            // El plazo lo dice eta() (suma salida + viaje); forZone se queda con
            // el resto de la frase (gratis desde, flete en destino, agencias),
            // para no repetir el plazo dos veces con cifras distintas.
            $sinPlazo = $s;
            if ($sinPlazo) {
                if (isset($sinPlazo['lima']) && is_array($sinPlazo['lima'])) {
                    $sinPlazo['lima']['days'] = null;
                }
                if (isset($sinPlazo['province']) && is_array($sinPlazo['province'])) {
                    $sinPlazo['province']['transit'] = null;
                }
            }
            $r = ShippingSummaryText::forZone($sinPlazo, $zone, $subtotal);
            $mode = $zone === 'lima' ? self::MODE_DELIVERY : self::MODE_AGENCY;
            $agencies = array_map('mb_strtolower', array_map('strval', (array) ($s['province']['agencies'] ?? [])));
            $shalomOk = !$agencies || (bool) array_filter($agencies, fn ($a) => str_contains($a, 'shalom'));
            $needsOffice = $mode === self::MODE_AGENCY && $hasOffices && $shalomOk;

            $options[] = [
                'mode'           => $mode,
                'title'          => $mode === self::MODE_DELIVERY ? 'Delivery Lima/Callao' : 'Envío a provincia por agencia',
                'price'          => $s ? $r['text'] : 'Te lo confirma la tienda',
                'amount'         => $r['amount'],
                'pending'        => (bool) $r['pending'],
                'free'           => (bool) $r['free'],
                'eta'            => $this->eta($s, $zone),
                // Sin política, el aviso va en la nota de la tienda (no repetirlo aquí).
                'detail'         => $s ? (string) $r['detail'] : '',
                'where'          => $placeLabel,
                'needs_office'   => $needsOffice,
                // Sin política NO se bloquea la compra: la oficina queda opcional.
                'office_required' => $needsOffice && $s !== null,
                'needs_address'  => $mode === self::MODE_DELIVERY,
            ];
        }

        return $options;
    }

    private function eta(?array $s, string $zone): string
    {
        if (!$s) {
            return '';
        }
        if ($zone === 'lima') {
            $d = $s['lima']['days'] ?? null;

            return $d ? self::range((int) ($d[0] ?? 0), (int) ($d[1] ?? ($d[0] ?? 0))) : '';
        }
        $p = $s['province'] ?? null;
        if (!$p) {
            return '';
        }
        $h = isset($p['handling']) && $p['handling'] !== null ? (int) $p['handling'] : 0;
        if (!empty($p['transit'])) {
            return self::range($h + (int) $p['transit'][0], $h + (int) ($p['transit'][1] ?? $p['transit'][0]));
        }

        return isset($p['handling']) && $p['handling'] !== null ? 'Sale a la agencia en ' . $h . ($h === 1 ? ' día hábil' : ' días hábiles') : '';
    }

    private static function range(int $a, int $b): string
    {
        if ($b < $a) {
            [$a, $b] = [$b, $a];
        }
        if ($a === $b) {
            return $a === 0 ? 'Llega el mismo día' : 'Llega en ' . $a . ($a === 1 ? ' día hábil' : ' días hábiles');
        }

        return 'Llega en ' . $a . ' a ' . $b . ' días hábiles';
    }

    /**
     * La frase que el comprador ve ANTES de pagar y que queda guardada:
     * «Envío a Talara (Piura) · Agencia Shalom Of. X · S/ 12.00 · Llega en 3 a 5 días hábiles»
     * o «Recojo en tienda · Miraflores».
     */
    public static function label(string $mode, string $place, ?string $officeName, array $opt): string
    {
        if ($mode === self::MODE_PICKUP) {
            return 'Recojo en tienda' . (!empty($opt['where']) ? ' · ' . $opt['where'] : '');
        }
        $parts = [];
        if ($mode === self::MODE_AGENCY) {
            $parts[] = 'Envío a ' . ($place ?: 'provincia') . ($officeName ? ' · Agencia Shalom ' . $officeName : ' por agencia');
        } else {
            $parts[] = 'Delivery a ' . ($place ?: 'Lima/Callao');
        }
        $parts[] = (string) ($opt['price'] ?? '');
        if (!empty($opt['eta'])) {
            $parts[] = $opt['eta'];
        }

        return implode(' · ', array_filter($parts, fn ($p) => $p !== ''));
    }

    // ── Validación de lo elegido ───────────────────────────────────────────

    /**
     * @param Collection $stores    carrito agrupado por tienda
     * @param array      $input     request('ship'): [hostname_id => ['mode' => ..., 'office_id' => ...]]
     * @param ?string    $ubigeo    ya resuelto en servidor (null si no hay destino)
     * @return array{plans:array<int,array>, errors:array<string,string>, any_delivery:bool, all_pickup:bool}
     */
    public function resolveSelection(Collection $stores, array $input, ?string $ubigeo, string $placeLabel): array
    {
        $zone = $this->zone($ubigeo);
        $offices = ['offices' => [], 'others' => []];
        if ($zone === 'province') {
            try {
                $offices = $this->shalom->forDistrict((string) $ubigeo);
            } catch (\Throwable $e) {
                // sin catálogo: la oficina queda opcional
            }
        }
        $officeById = [];
        foreach (array_merge($offices['offices'] ?? [], $offices['others'] ?? []) as $o) {
            $officeById[(int) $o['id']] = $o;
        }
        $hasOffices = (bool) $officeById;

        $plans = [];
        $errors = [];
        $anyDelivery = false;
        $allPickup = true;

        foreach ($stores as $store) {
            $hid = (int) $store['hostname_id'];
            $name = (string) ($store['tenant_name'] ?: $store['tenant_fqdn']);
            $sel = (array) ($input[$hid] ?? []);
            $mode = (string) ($sel['mode'] ?? '');
            $options = collect($this->optionsFor($store, $zone, $placeLabel, $hasOffices))->keyBy('mode');

            if ($mode === '' || !$options->has($mode)) {
                $errors["ship.$hid.mode"] = $zone === null && $mode !== ''
                    ? 'Elige tu distrito para ver cómo te llega el pedido de ' . $name . '.'
                    : 'Elige cómo recibes el pedido de ' . $name . '.';
                $allPickup = false;
                continue;
            }

            $opt = $options->get($mode);
            $office = null;
            if ($opt['needs_office']) {
                $oid = (int) ($sel['office_id'] ?? 0);
                if ($oid > 0 && isset($officeById[$oid])) {
                    $office = $officeById[$oid];
                } elseif ($opt['office_required']) {
                    $errors["ship.$hid.office_id"] = 'Elige la oficina de Shalom donde recoges el pedido de ' . $name . '.';
                }
            }

            $plans[$hid] = [
                'mode'          => $mode,
                'cost'          => $opt['amount'],
                'pending'       => (bool) $opt['pending'],
                'has_policy'    => is_array($store['shipping'] ?? null),
                'eta'           => $opt['eta'] ?: null,
                'label'         => self::label($mode, $placeLabel, $office['name'] ?? null, $opt),
                'office_id'     => $office ? (int) $office['id'] : null,
                'office_name'   => $office['name'] ?? null,
                'office_address'=> $office['address'] ?? null,
                'where'         => $opt['where'] ?? null,
            ];
            $anyDelivery = $anyDelivery || $mode === self::MODE_DELIVERY;
            $allPickup = $allPickup && $mode === self::MODE_PICKUP;
        }

        return ['plans' => $plans, 'errors' => $errors, 'any_delivery' => $anyDelivery, 'all_pickup' => $allPickup && $plans !== []];
    }

    // ── Cómo se cuenta el destino (WhatsApp, correos) ──────────────────────

    public const MODE_LABELS = [
        self::MODE_PICKUP   => 'Recojo en tienda',
        self::MODE_DELIVERY => 'Delivery Lima/Callao',
        self::MODE_AGENCY   => 'Envío a provincia por agencia',
    ];

    /** «S/ 12.00», «Gratis», «Por confirmar» o «S/ 12.00 + por confirmar». */
    public static function costText(?float $cost, bool $pending): string
    {
        if ($cost !== null && $cost > 0) {
            return 'S/ ' . number_format($cost, 2) . ($pending ? ' + por confirmar' : '');
        }

        return $pending ? 'Por confirmar' : 'Gratis';
    }

    /**
     * El destino de UNA tienda tal como se lo cuenta el marketplace al vendedor
     * y al comprador: modalidad, ciudad, provincia, departamento, oficina,
     * costo y plazo. Con pedidos anteriores al selector (sin modalidad
     * guardada) devuelve solo lo que sí se sabía: dirección y ubicación.
     *
     * @return array{mode:?string,mode_label:?string,city:string,province:string,department:string,place:string,
     *   address:?string,office:?string,office_address:?string,cost:string,eta:?string,label:?string,has_policy:bool}
     */
    public static function describe($order, $sub = null): array
    {
        $mode = $sub->shipping_mode ?? null;
        $addr = trim((string) $order->delivery_address);
        $hasAddr = $addr !== '' && mb_strtolower($addr) !== 'sin dirección';
        $city = self::pretty($order->delivery_district);
        $prov = self::pretty($order->delivery_province);
        $dep  = self::pretty($order->delivery_department);
        $cost = $sub && $sub->shipping_cost !== null ? (float) $sub->shipping_cost : null;

        return [
            'mode'           => $mode,
            'mode_label'     => $mode ? (self::MODE_LABELS[$mode] ?? null) : null,
            'city'           => $city,
            'province'       => $prov,
            'department'     => $dep,
            'place'          => self::placeLabel($order->delivery_department, $order->delivery_province, $order->delivery_district),
            'address'        => ($mode === self::MODE_PICKUP || !$hasAddr) ? null : $addr,
            'office'         => $sub->agency_office_name ?? null,
            'office_address' => $sub->agency_office_address ?? null,
            'cost'           => $mode ? self::costText($cost, (bool) ($sub->shipping_pending ?? false)) : '',
            'eta'            => $sub->shipping_eta ?? null,
            'label'          => $sub->shipping_label ?? null,
            'has_policy'     => (bool) ($sub->shipping_has_policy ?? false),
        ];
    }

    // ── Perfil del comprador con cuenta ────────────────────────────────────

    /** Lo último que dio el comprador, para precargarlo la próxima vez. */
    public function rememberProfile(?object $user, array $data): void
    {
        if (!$user || !method_exists($user, 'forceFill')) {
            return;
        }
        try {
            $user->forceFill(['checkout_profile' => array_filter($data, fn ($v) => $v !== null && $v !== '')])->save();
        } catch (\Throwable $e) {
            Log::info('[MarketplaceCheckoutShipping] perfil de entrega no guardado', ['error' => $e->getMessage()]);
        }
    }
}
