<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Tenant\StoreShipping;
use Illuminate\Http\Request;

/**
 * Formulario «¿Cómo envías tus productos?»: la política de envío de la tienda
 * que usan su tienda online, el marketplace y el catálogo de anuncios.
 */
class StoreShippingController extends Controller
{
    public function show(StoreShipping $shipping)
    {
        if (!$shipping->available()) {
            return response()->json(['available' => false]);
        }

        return ['available' => true] + $shipping->formData();
    }

    /** Cuenta Shalom Pro de la tienda: guardar y, si el plan lo permite, conectar. */
    public function shalomAccount(Request $request, StoreShipping $shipping)
    {
        abort_unless(optional(auth()->user())->type === 'admin', 403, 'Solo el administrador puede cambiar la cuenta de Shalom.');
        $d = $request->validate([
            'user'       => ['nullable', 'string', 'max:150'],
            'password'   => ['nullable', 'string', 'max:150'],
            'clave'      => ['nullable', 'digits:4'],
            'only_clave' => ['nullable', 'boolean'],
        ], ['clave.digits' => 'La clave de recojo son 4 dígitos.']);

        $r = $shipping->saveShalomAccount(
            trim((string) ($d['user'] ?? '')) ?: null,
            $d['password'] ?? null,
            $d['clave'] ?? null,
            (bool) ($d['only_clave'] ?? false)
        );

        return response()->json($r + ['shalom_pro' => $shipping->formData()['shalom_pro']], $r['saved'] ? 200 : 409);
    }

    /**
     * Buscar la oficina de Shalom desde donde despacha la tienda (panel).
     * Lee el catálogo guardado en el sistema, no el API.
     */
    public function shalomOffices(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        try {
            $rows = \App\Models\System\ShalomAgency::where('listed', true)
                ->when($q !== '', function ($w) use ($q) {
                    foreach (preg_split('/\s+/', $q) as $t) {
                        $w->where(function ($x) use ($t) {
                            $x->where('name', 'like', "%{$t}%")->orWhere('full_name', 'like', "%{$t}%")
                              ->orWhere('address', 'like', "%{$t}%");
                        });
                    }
                })
                ->orderBy('department')->orderBy('province')->orderBy('name')
                ->limit(40)->get();
        } catch (\Throwable $e) {
            return [];
        }

        return $rows->map(fn ($a) => [
            'id'      => $a->ter_id,
            'name'    => $a->name,
            'place'   => trim($a->province . ', ' . $a->department, ', '),
            'address' => $a->address,
        ])->values();
    }

    /** «Así lo verá el comprador» con los datos del formulario, sin guardar. */
    public function preview(Request $request, StoreShipping $shipping)
    {
        return $shipping->preview($request->all());
    }

    public function save(Request $request, StoreShipping $shipping)
    {
        abort_unless($shipping->available(), 409, 'Falta actualizar la base de datos de la tienda.');
        abort_unless(optional(auth()->user())->type === 'admin', 403, 'Solo el administrador puede cambiar los envíos.');

        $data = $request->validate(StoreShipping::rules(), [
            'lima_cost.required_if'     => 'Indica cuánto cobras por el envío en Lima.',
            'province_cost.required_if' => 'Indica cuánto cobras por el envío a provincias.',
            'agency_fee.required_if'    => 'Indica cuánto cobras por llevarlo a la agencia.',
            'pickup_district.required_if' => 'Indica en qué distrito se recoge.',
            'lima_days_max.gte'         => 'El máximo de días en Lima no puede ser menor que el mínimo.',
            'province_transit_max.gte'  => 'El máximo de días de viaje no puede ser menor que el mínimo.',
            'province_agencies.max'     => 'Elige como máximo ' . StoreShipping::MAX_AGENCIES . ' agencias.',
            'cutoff_time.date_format'   => 'La hora de corte no es válida.',
        ]);

        $shipping->save($data);

        return [
            'success' => true,
            'message' => 'Envíos guardados. Ya se muestran en tu tienda y en ebaemy.com.',
            'record'  => $shipping->formData(),
        ];
    }
}
