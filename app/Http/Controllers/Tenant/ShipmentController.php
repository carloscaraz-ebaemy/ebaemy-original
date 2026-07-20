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

        // Grupos de estado para las tarjetas de métricas (incluyen valores legados).
        $groups = [
            'confirmar'  => ['recibido', 'pendiente'],
            'embalaje'   => ['confirmado', 'preparando'],
            'despacho'   => ['embalando', 'despachado', 'listo'],
            'transito'   => ['en_agencia', 'en_ruta', 'enviado'],
            'entregados' => ['entregado'],
            'cancelados' => ['anulado'],
        ];
        $group = $request->input('group');
        if ($group && isset($groups[$group])) {
            $query->whereIn('status', $groups[$group]);
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

        // Métricas por grupo de estado para el panel de tarjetas.
        $metrics = ['total' => ShippingRequest::count()];
        foreach ($groups as $k => $sts) {
            $metrics[$k] = ShippingRequest::whereIn('status', $sts)->count();
        }

        return view('tenant.shipments.index', [
            'shipments'   => $shipments,
            'filter'      => $filter,
            'counts'      => $counts,
            'metrics'     => $metrics,
            'group'       => $group,
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
        $data['status']     = ShippingRequest::STATUS_RECIBIDO;
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
            'status'              => ShippingRequest::STATUS_EN_AGENCIA,
            'sent_at'             => now(),
        ]);

        // Avisar al cliente por WhatsApp que su envío salió (async, best-effort).
        $this->notifyClientShipped($shipment);

        return back()->with('success', "Guía {$shipment->tracking_number} cargada. Envío marcado como Enviado.");
    }

    /** Cambiar el estado del paquete (preparando / listo / entregado / …). */
    public function updateStatus(Request $request, ShippingRequest $shipment): RedirectResponse
    {
        $request->validate([
            'status' => 'required|in:' . implode(',', array_keys(ShippingRequest::STATUSES)),
        ]);

        $old = $shipment->status;
        $update = ['status' => $request->status];
        // Al llegar a la agencia (o más allá) y sin fecha, sellar sent_at.
        if (in_array($request->status, [ShippingRequest::STATUS_EN_AGENCIA, ShippingRequest::STATUS_EN_RUTA, ShippingRequest::STATUS_ENTREGADO], true) && !$shipment->sent_at) {
            $update['sent_at'] = now();
        }
        $shipment->update($update);

        // WhatsApp automático al cliente por el cambio de estado.
        if ($old !== $shipment->status) {
            $this->notifyStatusChange($shipment);
        }

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

    /**
     * Ubigeo: búsqueda por texto (distrito). Devuelve el ubigeo completo con
     * su ruta legible, para el input de búsqueda del cascader. Público.
     */
    public function searchUbigeo(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $rows = District::with('province.department')
            ->where('description', 'like', "%{$q}%")
            ->orderBy('description')
            ->limit(25)
            ->get();

        return response()->json($rows->map(function ($d) {
            $prov = $d->province;
            $dep  = $prov ? $prov->department : null;
            return [
                'district_id'   => $d->id,
                'province_id'   => $d->province_id,
                'department_id' => $dep ? $dep->id : null,
                'label'         => $d->description
                    . ' — ' . ($prov ? $prov->description : '')
                    . ', ' . ($dep ? $dep->description : ''),
            ];
        })->values());
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
    public function printLabel(Request $request, ShippingRequest $shipment)
    {
        $company = Company::first();

        // Formato de impresión: sticker (10cm), A5 o A4.
        $format = strtolower((string) $request->query('format', 'a5'));
        if (!in_array($format, ['sticker', 'a5', 'a4'], true)) {
            $format = 'a5';
        }

        return view('tenant.shipments.label', [
            'shipment' => $shipment,
            'company'  => $company,
            'ubigeo'   => $this->resolveUbigeo($shipment),
            'format'   => $format,
            'qr'       => $this->makeQr($shipment),
            'barcode'  => $this->makeBarcode($shipment),
        ]);
    }

    /** Impresión por lote: varios rótulos en una hoja A4. */
    public function printBatch(Request $request)
    {
        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn ($x) => (int) trim($x))->filter()->unique()->take(60)->values();

        abort_if($ids->isEmpty(), 404);

        $shipments = ShippingRequest::whereIn('id', $ids)->orderBy('id')->get();
        $items = $shipments->map(fn ($s) => [
            'shipment' => $s,
            'ubigeo'   => $this->resolveUbigeo($s),
            'qr'       => $this->makeQr($s),
            'barcode'  => $this->makeBarcode($s),
        ])->all();

        return view('tenant.shipments.label-batch', [
            'items'   => $items,
            'company' => Company::first(),
        ]);
    }

    /** Ubigeo completo (distrito/provincia/departamento) para el rótulo. */
    private function resolveUbigeo(ShippingRequest $shipment): ?array
    {
        if (!$shipment->district_id) {
            return null;
        }
        $dist = District::with('province.department')->find($shipment->district_id);
        if (!$dist) {
            return null;
        }
        $prov = $dist->province;
        $dep  = $prov ? $prov->department : null;
        return [
            'district'   => $dist->description,
            'province'   => $prov ? $prov->description : null,
            'department' => $dep ? $dep->description : null,
        ];
    }

    /** QR (PNG base64) que abre la página de estado rápido del envío. */
    private function makeQr(ShippingRequest $shipment): ?string
    {
        try {
            $url = url('registro-envio/' . $shipment->id . '/estado-rapido');
            return (new \App\CoreFacturalo\Helpers\QrCode\QrCodeGenerate())->displayPNGBase64($url, 220, 'M');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Cliente existente por documento: devuelve los datos del último envío con
     * ese DNI/RUC para autocompletar (público, throttled).
     */
    public function findClient(string $document)
    {
        $doc = preg_replace('/\D+/', '', $document);
        if (!in_array(strlen($doc), [8, 11], true)) {
            return response()->json(['found' => false]);
        }
        $s = ShippingRequest::where('dni', $doc)->latest('id')->first();
        if (!$s) {
            return response()->json(['found' => false]);
        }
        return response()->json([
            'found' => true,
            'name'  => $s->full_name,
            'data'  => [
                'full_name'            => $s->full_name,
                'phone'                => $s->phone,
                'shipping_destination' => $s->shipping_destination,
                'reference'            => $s->reference,
                'department_id'        => $s->department_id,
                'province_id'          => $s->province_id,
                'district_id'          => $s->district_id,
                'shipping_agency'      => $s->shipping_agency,
            ],
        ]);
    }

    /**
     * Guía de envío pública, por código de envío (para el seguimiento del
     * cliente). Muestra inline o descarga con ?download=1.
     */
    public function publicGuide(Request $request, string $code)
    {
        $shipment = ShippingRequest::where('shipment_code', $code)->first();
        if ($request->boolean('debug')) {
            return response()->json([
                'code' => $code,
                'shipment_found' => (bool) $shipment,
                'path' => $shipment->shipping_guide_path ?? null,
                'exists' => $shipment && $shipment->shipping_guide_path ? Storage::exists($shipment->shipping_guide_path) : null,
                'storage_root' => Storage::path(''),
            ]);
        }
        abort_unless(
            $shipment && $shipment->shipping_guide_path && Storage::exists($shipment->shipping_guide_path),
            404
        );

        $ext  = strtolower(pathinfo($shipment->shipping_guide_path, PATHINFO_EXTENSION));
        $mime = $ext === 'pdf' ? 'application/pdf' : ($ext === 'png' ? 'image/png' : 'image/jpeg');
        $disp = $request->boolean('download') ? 'attachment' : 'inline';

        return response(Storage::get($shipment->shipping_guide_path), 200, [
            'Content-Type'        => $mime,
            'Content-Disposition' => $disp . '; filename="guia-' . ($shipment->tracking_number ?: $shipment->shipment_code) . '.' . $ext . '"',
        ]);
    }

    /** Normaliza un celular peruano a formato internacional (51XXXXXXXXX) o null. */
    private function waPhone($raw): ?string
    {
        $p = preg_replace('/\D+/', '', (string) $raw);
        if (strlen($p) === 9 && $p[0] === '9') {
            $p = '51' . $p;
        }
        return strlen($p) >= 11 ? $p : null;
    }

    /** WhatsApp automático al cliente cuando cambia el estado del envío. */
    private function notifyStatusChange(ShippingRequest $shipment): void
    {
        try {
            $body = ShippingRequest::statusWhatsappMessage($shipment->status);
            if (!$body) {
                return; // este estado no notifica
            }
            $phone = $this->waPhone($shipment->phone);
            if (!$phone) {
                return;
            }
            $name     = \Illuminate\Support\Str::of($shipment->full_name)->before(' ');
            $trackUrl = url('envio/seguimiento?code=' . $shipment->shipment_code);
            $msg = "Hola {$name} 👋\n\n{$body}\n\nCódigo: *{$shipment->shipment_code}*\n🔎 Seguimiento:\n{$trackUrl}";
            dispatch(\App\Jobs\SendWhatsAppMessage::text($phone, $msg));
        } catch (\Throwable $e) {
            \Log::warning('[shipping] WhatsApp de estado no enviado: ' . $e->getMessage());
        }
    }

    /** WhatsApp "registro recibido" apenas el cliente registra su envío. */
    private function notifyClientRegistered(ShippingRequest $shipment): void
    {
        try {
            $phone = $this->waPhone($shipment->phone);
            if (!$phone) {
                return;
            }
            $name     = \Illuminate\Support\Str::of($shipment->full_name)->before(' ');
            $trackUrl = url('envio/seguimiento?code=' . $shipment->shipment_code);
            $msg  = "Hola {$name} 👋\n\nTus datos de envío fueron registrados correctamente.\n\n";
            $msg .= "Código:\n*{$shipment->shipment_code}*\n\n";
            $msg .= "Estado actual: *Registro recibido*.\nEn breve prepararemos tu pedido.\n\n";
            $msg .= "🔎 Consulta tu seguimiento aquí:\n{$trackUrl}\n\n¡Gracias por tu compra!";
            dispatch(\App\Jobs\SendWhatsAppMessage::text($phone, $msg));
        } catch (\Throwable $e) {
            \Log::warning('[shipping] WhatsApp de registro no enviado: ' . $e->getMessage());
        }
    }

    /**
     * Notifica al cliente por WhatsApp que su envío salió (guía + agencia +
     * link de seguimiento). Async vía el job del ERP; best-effort (no rompe
     * la subida de guía si WhatsApp no está configurado o falla).
     */
    private function notifyClientShipped(ShippingRequest $shipment): void
    {
        try {
            $phone = $this->waPhone($shipment->phone);
            if (!$phone) {
                return; // sin celular válido
            }

            $name     = \Illuminate\Support\Str::of($shipment->full_name)->before(' ');
            $trackUrl = url('envio/seguimiento?code=' . $shipment->shipment_code);

            $msg  = "¡Hola {$name}! 📦\n\nTu envío *{$shipment->shipment_code}* ya salió.\n";
            if ($shipment->shipping_agency) $msg .= "🚚 Agencia: {$shipment->shipping_agency}\n";
            if ($shipment->tracking_number) $msg .= "📄 Guía: {$shipment->tracking_number}\n";
            if ($shipment->destination_city) $msg .= "📍 Destino: {$shipment->destination_city}\n";
            $msg .= "\n🔎 Sigue tu envío aquí:\n{$trackUrl}\n\n¡Gracias por tu compra!";

            dispatch(\App\Jobs\SendWhatsAppMessage::text($phone, $msg));
        } catch (\Throwable $e) {
            \Log::warning('[shipping] WhatsApp de envío no enviado: ' . $e->getMessage());
        }
    }

    /** Código de barras Code128 (PNG base64) del código del envío. */
    private function makeBarcode(ShippingRequest $shipment): ?string
    {
        try {
            $gen = new \Picqer\Barcode\BarcodeGeneratorPNG();
            return base64_encode($gen->getBarcode((string) $shipment->shipment_code, $gen::TYPE_CODE_128, 2, 45));
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Página de estado rápido (la abre el QR del rótulo). Muestra el envío y
     * botones grandes para marcar preparando / listo / enviado / entregado.
     */
    public function quickStatus(ShippingRequest $shipment)
    {
        return view('tenant.shipments.quick-status', [
            'shipment' => $shipment,
            'company'  => Company::first(),
            'statuses' => ShippingRequest::STATUSES,
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

    /** Seguimiento público: el cliente consulta su envío por el código ENV. */
    public function publicTracking(Request $request)
    {
        $code     = trim((string) $request->query('code', ''));
        $shipment = null;
        $notFound = false;

        if ($code !== '') {
            $q = strtoupper($code);
            $shipment = ShippingRequest::where('shipment_code', $q)->first();
            // Aceptar también que ingresen solo el número (el id del envío).
            if (!$shipment && ctype_digit($code)) {
                $shipment = ShippingRequest::find((int) $code);
            }
            $notFound = !$shipment;
        }

        return view('tenant.shipments.tracking', [
            'company'  => Company::first(),
            'code'     => $code,
            'shipment' => $shipment,
            'notFound' => $notFound,
            'statuses' => ShippingRequest::STATUSES,
        ]);
    }

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
        $data['status']         = ShippingRequest::STATUS_RECIBIDO;
        $data['accepted_terms'] = true;

        // Anti-duplicado: si el mismo teléfono ya registró a la misma ciudad en
        // los últimos 10 min y sigue pendiente, reusamos ese registro en vez de
        // crear otro. Cubre el doble clic y el "creo que no funcionó, reintento"
        // sin bloquear un envío genuinamente distinto (otra ciudad o más tarde).
        $recent = ShippingRequest::where('phone', $data['phone'])
            ->where('destination_city', $data['destination_city'])
            ->where('status', ShippingRequest::STATUS_RECIBIDO)
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

        // WhatsApp "registro recibido" al cliente (async, best-effort).
        $this->notifyClientRegistered($shipment);

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
            'reference'            => 'nullable|string|max:255',
            'destination_city'     => 'nullable|string|max:120',
            'department_id'        => 'nullable|string|max:2',
            'province_id'          => 'nullable|string|max:4',
            'district_id'          => 'required|string|max:6',
            'shipping_agency'      => 'nullable|string|max:120',
            'package_content'      => 'nullable|string|max:255',
            'package_count'        => 'nullable|integer|min:1|max:9999',
            'weight'               => 'nullable|numeric|min:0|max:999999',
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

    /** Asigna el código legible ENV-AAAAMMDD-000XXX tras crear la fila. */
    private function assignCode(ShippingRequest $shipment): void
    {
        if (!$shipment->shipment_code) {
            $shipment->shipment_code = ShippingRequest::buildCode(
                $shipment->id,
                optional($shipment->created_at)->format('Ymd')
            );
            $shipment->save();
        }
    }
}
