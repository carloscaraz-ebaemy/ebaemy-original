<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Catalogs\Department;
use App\Models\Tenant\Catalogs\District;
use App\Models\Tenant\Catalogs\Province;
use App\Models\Tenant\Company;
use App\Models\Tenant\ShippingRequest;
use Hyn\Tenancy\Environment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Registro y Control de Envíos (panel del tenant).
 *
 * - Formulario PÚBLICO: el cliente registra sus datos → estado "pendiente".
 * - Panel del encargado: lista/filtra los paquetes, sube la guía de envío
 *   (cambia a "enviado") e imprime el rótulo.
 */
class ShipmentController extends Controller
{
    /** Filtros rápidos del tablero. */
    private const FILTERS = ['todos', 'sin-guia', 'con-guia', 'pendientes', 'enviados-hoy'];

    // ── Panel del encargado ────────────────────────────────────────────────

    public function index(Request $request)
    {
        $filter = $request->input('filter', 'todos');
        if (!in_array($filter, self::FILTERS, true)) {
            $filter = 'todos';
        }

        $query = ShippingRequest::query()->latest('id');

        switch ($filter) {
            case 'sin-guia':     $query->withoutGuide();  break;
            case 'con-guia':     $query->withGuide();     break;
            case 'pendientes':   $query->pending();       break;
            case 'enviados-hoy': $query->sentToday();     break;
        }

        if ($q = trim((string) $request->input('q', ''))) {
            $query->where(function ($w) use ($q) {
                $w->where('full_name', 'like', "%{$q}%")
                  ->orWhere('shipment_code', 'like', "%{$q}%")
                  ->orWhere('tracking_number', 'like', "%{$q}%")
                  ->orWhere('destination_city', 'like', "%{$q}%")
                  ->orWhere('shipping_agency', 'like', "%{$q}%");
            });
        }

        $shipments = $query->paginate(20)->withQueryString();

        // Contadores para las pastillas de filtro (una pasada por estado).
        $counts = [
            'todos'        => ShippingRequest::count(),
            'sin-guia'     => ShippingRequest::withoutGuide()->count(),
            'con-guia'     => ShippingRequest::withGuide()->count(),
            'pendientes'   => ShippingRequest::pending()->count(),
            'enviados-hoy' => ShippingRequest::sentToday()->count(),
        ];

        return view('tenant.shipments.index', [
            'shipments'   => $shipments,
            'filter'      => $filter,
            'counts'      => $counts,
            'q'           => $q,
            'statuses'    => ShippingRequest::STATUSES,
            'departments' => Department::orderBy('description')->get(['id', 'description']),
        ]);
    }

    /** Atajo /registro-envio/sin-guia → index filtrado (filtro crítico). */
    public function withoutGuide(Request $request)
    {
        $request->merge(['filter' => 'sin-guia']);
        return $this->index($request);
    }

    /** Alta manual desde el panel (el encargado registra un envío). */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateShipment($request);
        $data['status']     = ShippingRequest::STATUS_PENDIENTE;
        $data['created_by'] = auth()->id();

        $shipment = ShippingRequest::create($data);
        $this->assignCode($shipment);

        return back()->with('success', "Envío {$shipment->shipment_code} registrado.");
    }

    /**
     * Subir la guía de envío. Guarda el N° de guía + el archivo, cambia el
     * estado a "enviado" y registra sent_at = now().
     */
    public function uploadGuide(Request $request, ShippingRequest $shipment): RedirectResponse
    {
        $request->validate([
            'tracking_number' => 'required|string|max:120',
            'guide_file'      => 'required|file|mimes:jpg,jpeg,png,pdf|max:8192',
            'observation'     => 'nullable|string|max:255',
        ], [], [
            'tracking_number' => 'número de guía',
            'guide_file'      => 'archivo de la guía',
        ]);

        $uuid = app(Environment::class)->tenant()?->uuid ?? 'default';
        $path = $request->file('guide_file')->store("tenants/{$uuid}/shipping_guides");

        // Si reemplaza una guía anterior, borrar el archivo viejo.
        if ($shipment->shipping_guide_path && Storage::exists($shipment->shipping_guide_path)) {
            Storage::delete($shipment->shipping_guide_path);
        }

        $shipment->update([
            'tracking_number'     => $request->tracking_number,
            'shipping_guide_path' => $path,
            'observation'         => $request->filled('observation') ? $request->observation : $shipment->observation,
            'status'              => ShippingRequest::STATUS_ENVIADO,
            'sent_at'             => now(),
        ]);

        return back()->with('success', "Guía {$shipment->tracking_number} cargada. Envío marcado como Enviado.");
    }

    /** Cambiar el estado del paquete (preparando / listo / entregado / …). */
    public function updateStatus(Request $request, ShippingRequest $shipment): RedirectResponse
    {
        $request->validate([
            'status' => 'required|in:' . implode(',', array_keys(ShippingRequest::STATUSES)),
        ]);

        $update = ['status' => $request->status];
        // Si lo marcan enviado manualmente y no tenía fecha, sellarla.
        if ($request->status === ShippingRequest::STATUS_ENVIADO && !$shipment->sent_at) {
            $update['sent_at'] = now();
        }
        $shipment->update($update);

        return back()->with('success', "Estado actualizado a «{$shipment->status_label}».");
    }

    /**
     * Consulta RENIEC (dni) o SUNAT (ruc) vía ApiPeruDev usando el token del
     * tenant. Reemplaza al deprecado services/dni (Jne) que reventaba con
     * "Trying to access array offset on null" cuando la API libre fallaba.
     * Devuelve {success, data:{name, address, ...}} o {success:false, message}.
     */
    public function lookupDocument(string $type, string $number)
    {
        $type   = strtolower($type);
        $number = preg_replace('/\D+/', '', $number);

        if (!in_array($type, ['dni', 'ruc'], true)) {
            return response()->json(['success' => false, 'message' => 'Tipo de documento inválido.'], 200);
        }
        if (($type === 'dni' && strlen($number) !== 8) || ($type === 'ruc' && strlen($number) !== 11)) {
            return response()->json(['success' => false, 'message' => 'Número de documento inválido.'], 200);
        }

        try {
            $res = (new \Modules\ApiPeruDev\Data\ServiceData())->service($type, $number);
            if (!is_array($res) || empty($res['success'])) {
                return response()->json([
                    'success' => false,
                    'message' => is_array($res) && !empty($res['message']) ? $res['message'] : 'No se encontraron datos.',
                ], 200);
            }
            return response()->json($res, 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo consultar (verifica el token de RENIEC/SUNAT).',
            ], 200);
        }
    }

    /** Ubigeo: provincias de un departamento (para la cascada). Público. */
    public function provinces(string $department)
    {
        return response()->json(
            Province::where('department_id', $department)->orderBy('description')->get(['id', 'description'])
        );
    }

    /** Ubigeo: distritos de una provincia (para la cascada). Público. */
    public function districts(string $province)
    {
        return response()->json(
            District::where('province_id', $province)->orderBy('description')->get(['id', 'description'])
        );
    }

    /** Editar los datos de un envío (mismo set de reglas que el alta). */
    public function update(Request $request, ShippingRequest $shipment): RedirectResponse
    {
        $data = $this->validateShipment($request);
        $shipment->update($data);

        return back()->with('success', "Envío {$shipment->shipment_code} actualizado.");
    }

    /** Anular un envío (queda en estado 'anulado', no se borra). */
    public function cancel(ShippingRequest $shipment): RedirectResponse
    {
        $shipment->update(['status' => ShippingRequest::STATUS_ANULADO]);

        return back()->with('success', "Envío {$shipment->shipment_code} anulado.");
    }

    /** Rótulo imprimible del envío (standalone, listo para imprimir/PDF). */
    public function printLabel(ShippingRequest $shipment)
    {
        $company = Company::first();

        return view('tenant.shipments.label', [
            'shipment' => $shipment,
            'company'  => $company,
        ]);
    }

    /** Descarga/streaming de la guía de envío (archivo privado del tenant). */
    public function downloadGuide(ShippingRequest $shipment)
    {
        abort_unless($shipment->shipping_guide_path && Storage::exists($shipment->shipping_guide_path), 404);

        $ext  = strtolower(pathinfo($shipment->shipping_guide_path, PATHINFO_EXTENSION));
        $mime = $ext === 'pdf' ? 'application/pdf' : ($ext === 'png' ? 'image/png' : 'image/jpeg');

        return response(Storage::get($shipment->shipping_guide_path), 200, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'inline; filename="guia-' . ($shipment->tracking_number ?: $shipment->shipment_code) . '.' . $ext . '"',
        ]);
    }

    // ── Formulario público (lo llena el cliente) ───────────────────────────

    public function publicForm()
    {
        $company = Company::first();

        return view('tenant.shipments.public', [
            'company'     => $company,
            'sent'        => session('shipment_code'),
            'departments' => Department::orderBy('description')->get(['id', 'description']),
        ]);
    }

    public function publicStore(Request $request): RedirectResponse
    {
        $data = $this->validateShipment($request, true);
        $data['status']         = ShippingRequest::STATUS_PENDIENTE;
        $data['accepted_terms'] = true;

        // Anti-duplicado: si el mismo teléfono ya registró a la misma ciudad en
        // los últimos 10 min y sigue pendiente, reusamos ese registro en vez de
        // crear otro. Cubre el doble clic y el "creo que no funcionó, reintento"
        // sin bloquear un envío genuinamente distinto (otra ciudad o más tarde).
        $recent = ShippingRequest::where('phone', $data['phone'])
            ->where('destination_city', $data['destination_city'])
            ->where('status', ShippingRequest::STATUS_PENDIENTE)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->latest('id')
            ->first();

        if ($recent) {
            return redirect()->route('shipments.public.form')
                ->with('shipment_code', $recent->shipment_code)
                ->with('duplicate', true);
        }

        $shipment = ShippingRequest::create($data);
        $this->assignCode($shipment);

        return redirect()->route('shipments.public.form')
            ->with('shipment_code', $shipment->shipment_code)
            ->with('success', 'Tus datos se registraron. Guarda tu código de envío: ' . $shipment->shipment_code);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Reglas compartidas entre alta pública y de panel. En el público se exige
     * aceptar los términos.
     */
    private function validateShipment(Request $request, bool $public = false): array
    {
        $rules = [
            'full_name'            => 'required|string|max:160',
            'dni'                  => 'nullable|string|max:15',
            'phone'                => 'required|string|max:20',
            'shipping_destination' => 'nullable|string|max:255',
            'destination_city'     => 'nullable|string|max:120',
            'department_id'        => 'nullable|string|max:2',
            'province_id'          => 'nullable|string|max:4',
            'district_id'          => 'required|string|max:6',
            'shipping_agency'      => 'nullable|string|max:120',
            'package_content'      => 'nullable|string|max:255',
            'package_count'        => 'nullable|integer|min:1|max:9999',
            'notes'                => 'nullable|string|max:255',
            'observation'          => 'nullable|string|max:255',
            'order_id'             => 'nullable|integer',
        ];
        if ($public) {
            $rules['accepted_terms'] = 'accepted';
        }

        $data = $request->validate($rules, [], [
            'full_name'      => 'nombre completo',
            'phone'          => 'teléfono',
            'district_id'    => 'distrito de destino',
            'accepted_terms' => 'aceptación de términos',
        ]);

        // accepted_terms no es columna a asignar desde validación directa en
        // panel; se controla arriba. Quitarlo del payload común.
        unset($data['accepted_terms']);

        // N° de bultos: mínimo 1 (default) si no lo indicaron.
        $data['package_count'] = (int) ($request->input('package_count') ?: 1);

        // Ubigeo autoritativo: derivar provincia, departamento y el nombre de
        // ciudad (para el tablero/rótulo) a partir del distrito seleccionado.
        if (!empty($data['district_id'])) {
            $dist = District::find($data['district_id']);
            if ($dist) {
                $data['province_id']      = $dist->province_id;
                $prov                     = Province::find($dist->province_id);
                $data['department_id']    = $prov ? $prov->department_id : ($data['department_id'] ?? null);
                $data['destination_city'] = $dist->description;
            }
        }

        return $data;
    }

    /** Asigna el código legible ENV-000XXX tras crear la fila. */
    private function assignCode(ShippingRequest $shipment): void
    {
        if (!$shipment->shipment_code) {
            $shipment->shipment_code = ShippingRequest::buildCode($shipment->id);
            $shipment->save();
        }
    }
}
