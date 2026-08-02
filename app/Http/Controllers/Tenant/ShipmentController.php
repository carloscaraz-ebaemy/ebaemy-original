<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Catalogs\Department;
use App\Models\Tenant\Catalogs\District;
use App\Models\Tenant\Catalogs\Province;
use App\Models\System\MarketplaceListing;
use App\Models\Tenant\Raffle;
use App\Models\Tenant\RaffleParticipant;
use App\Models\Tenant\Company;
use App\Models\Tenant\ShippingAuditLog;
use App\Models\Tenant\ShippingPrintBatch;
use App\Models\Tenant\ShippingPrintEvent;
use App\Models\Tenant\ShippingRequest;
use App\Models\Tenant\ShippingSetting;
use App\Services\Tenant\ShippingBatchService;
use Hyn\Tenancy\Environment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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
    private const FILTERS = [
        'todos', 'sin-guia', 'con-guia', 'pendientes', 'enviados-hoy',
        // Rediseño logístico: por modalidad y por etapa del ciclo.
        'lima', 'provincias', 'recojo',
        'listos-imprimir', 'impresos', 'despachados', 'entregados', 'anulados',
    ];

    /** Etiquetas de los filtros rápidos, en el orden en que se muestran. */
    public const FILTER_LABELS = [
        'todos'           => 'Todos',
        'lima'            => 'Lima',
        'provincias'      => 'Provincias',
        'recojo'          => 'Recojo en tienda',
        'pendientes'      => 'Pendientes',
        'listos-imprimir' => 'Listos para imprimir',
        'impresos'        => 'Impresos',
        'despachados'     => 'Despachados',
        'entregados'      => 'Entregados',
        'anulados'        => 'Anulados',
    ];

    // ── Panel del encargado ────────────────────────────────────────────────

    /**
     * Construye la consulta de la lista aplicando TODOS los filtros del panel
     * (filtro, orden, fecha/rango, tipo, prioridad, grupo de estado, búsqueda).
     * Rellena $ctx con las variables derivadas para que la reusen index() y
     * export() sin duplicar la lógica.
     */
    private function buildListQuery(Request $request, array &$ctx): \Illuminate\Database\Eloquent\Builder
    {
        $filter = $request->input('filter', 'todos');
        if (!in_array($filter, self::FILTERS, true)) {
            $filter = 'todos';
        }

        // Parámetros del semáforo de prioridad (plazo en días hábiles).
        $setting  = ShippingSetting::current();
        $maxDays  = $setting->max_days;
        $skipHol  = (bool) ($setting->aging_skip_holidays ?? true);
        $closed   = ShippingRequest::CLOSED_STATUSES;

        // Orden: recientes (default), antiguos, o PRIORIDAD (más vencidos primero,
        // empujando al final los ya despachados/entregados/anulados).
        $sort  = in_array($request->input('sort'), ['oldest', 'priority'], true)
            ? $request->input('sort') : 'recent';
        $query = ShippingRequest::query();
        if ($sort === 'priority') {
            $ph = implode(',', array_fill(0, count($closed), '?'));
            $query->orderByRaw("CASE WHEN status IN ($ph) THEN 1 ELSE 0 END asc", $closed)
                  ->orderBy('created_at')->orderBy('id');
        } elseif ($sort === 'oldest') {
            $query->orderBy('created_at')->orderBy('id');
        } else {
            $query->orderByDesc('created_at')->orderByDesc('id');
        }

        switch ($filter) {
            case 'sin-guia':     $query->withoutGuide();  break;
            case 'con-guia':     $query->withGuide();     break;
            case 'pendientes':   $query->pending();       break;
            case 'enviados-hoy': $query->sentToday();     break;

            // ── Filtros rápidos del rediseño logístico ──────────────────
            case 'lima':
                $query->where('delivery_type', ShippingRequest::DELIVERY_DOMICILIO); break;
            case 'provincias':
                $query->where('delivery_type', ShippingRequest::DELIVERY_AGENCIA); break;
            case 'recojo':
                $query->where('delivery_type', ShippingRequest::DELIVERY_TIENDA); break;

            case 'listos-imprimir':
                // Sin lote, no anulados y que sí generen rótulo de transporte.
                $query->whereNull('print_batch_id')
                      ->where('status', '!=', ShippingRequest::STATUS_ANULADO)
                      ->where('delivery_type', '!=', ShippingRequest::DELIVERY_TIENDA);
                break;
            case 'impresos':
                $query->whereNotNull('printed_at'); break;
            case 'despachados':
                $query->whereIn('status', [
                    ShippingRequest::STATUS_DESPACHADO,
                    ShippingRequest::STATUS_EN_AGENCIA,
                    ShippingRequest::STATUS_EN_RUTA,
                    ShippingRequest::STATUS_EN_CAMINO,
                    'enviado',
                ]); break;
            case 'entregados':
                $query->where('status', ShippingRequest::STATUS_ENTREGADO); break;
            case 'anulados':
                $query->where('status', ShippingRequest::STATUS_ANULADO); break;
        }

        // Los anulados no aparecen mezclados con la operación viva salvo que
        // se pidan expresamente (filtro 'anulados' o búsqueda por código).
        if (!in_array($filter, ['anulados', 'todos'], true)) {
            $query->where('status', '!=', ShippingRequest::STATUS_ANULADO);
        }

        // Filtro por fecha de registro: un solo selector de RANGO (hoy, ayer,
        // últimos 7/30 días, este mes, mes pasado) que se traduce a desde/hasta.
        // Se conserva el soporte de from/to explícitos por compatibilidad.
        $range = $request->input('range');
        $from  = $request->input('from');
        $to    = $request->input('to');
        if (in_array($range, ['hoy', 'ayer', '7dias', '30dias', 'mes', 'mes_pasado'], true)) {
            $hoy = now();
            switch ($range) {
                case 'hoy':        $from = $to = $hoy->toDateString(); break;
                case 'ayer':       $from = $to = $hoy->copy()->subDay()->toDateString(); break;
                case '7dias':      $from = $hoy->copy()->subDays(6)->toDateString();  $to = $hoy->toDateString(); break;
                case '30dias':     $from = $hoy->copy()->subDays(29)->toDateString(); $to = $hoy->toDateString(); break;
                case 'mes':        $from = $hoy->copy()->startOfMonth()->toDateString(); $to = $hoy->toDateString(); break;
                case 'mes_pasado':
                    $lm   = $hoy->copy()->subMonthNoOverflow();
                    $from = $lm->copy()->startOfMonth()->toDateString();
                    $to   = $lm->copy()->endOfMonth()->toDateString();
                    break;
            }
        } else {
            $range = null;
            // Rango manual (del día X al día Y): si el usuario los eligió al
            // revés, los ordenamos para que igual muestre el periodo esperado.
            if ($from && $to && strtotime($from) && strtotime($to) && strtotime($from) > strtotime($to)) {
                [$from, $to] = [$to, $from];
            }
        }
        if ($from && strtotime($from)) {
            $query->whereDate('created_at', '>=', date('Y-m-d', strtotime($from)));
        }
        if ($to && strtotime($to)) {
            $query->whereDate('created_at', '<=', date('Y-m-d', strtotime($to)));
        }

        // Filtro por modalidad de entrega (Lima / Provincia / Recojo en tienda).
        $type = $request->input('type');
        if (array_key_exists($type, ShippingRequest::DELIVERY_TYPES)) {
            $query->where('delivery_type', $type);
        }

        // Filtro por lote de impresión.
        if ($batchId = $request->input('lote')) {
            $query->where('print_batch_id', (int) $batchId);
        }

        // Filtro por prioridad (antigüedad en días hábiles): 'urgentes' = naranja
        // + rojo (≥ max-1 días), 'vencidos' = rojo (≥ max días). Solo envíos
        // abiertos. Se traduce a un corte de fecha de calendario (SQL-friendly).
        $pri = in_array($request->input('pri'), ['urgentes', 'vencidos'], true)
            ? $request->input('pri') : null;
        if ($pri) {
            $k = $pri === 'vencidos' ? $maxDays : max(1, $maxDays - 1);
            $cutoff = ShippingRequest::agingCutoff($k, $skipHol)->toDateString();
            $query->whereDate('created_at', '<=', $cutoff)
                  ->whereNotIn('status', $closed);
        }

        // Grupos de estado para las tarjetas de métricas (incluyen valores legados
        // y los estados del flujo de motorizado: asignado_motorizado, en_camino).
        $groups = [
            'confirmar'  => ['recibido', 'pendiente'],
            'preparar'   => ['embalando', 'listo', 'confirmado', 'preparando', 'asignado_motorizado'],
            'transito'   => ['en_agencia', 'enviado', 'despachado', 'en_ruta', 'en_camino'],
            'entregados' => ['entregado'],
            'cancelados' => ['anulado'],
            // Claves antiguas (enlaces guardados) — no se muestran como pestaña.
            'embalaje'   => ['confirmado', 'preparando', 'embalando'],
            'despacho'   => ['embalando', 'despachado', 'listo', 'asignado_motorizado'],
        ];
        $group = $request->input('group');

        // Vista por defecto = "bandeja de entrada": solo los pedidos que entran
        // (recién registrados). Solo se aplica si el usuario no pidió otra cosa.
        $hasExplicit = $request->filled('group') || $request->filled('q')
            || $request->filled('from') || $request->filled('to') || $request->filled('range')
            || $request->filled('type') || $request->filled('pri') || $filter !== 'todos';
        if (!$hasExplicit) {
            $group = 'confirmar';
        }

        if ($group && $group !== 'todos' && isset($groups[$group])) {
            $query->whereIn('status', $groups[$group]);
        }

        $q = trim((string) $request->input('q', ''));
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('full_name', 'like', "%{$q}%")
                  ->orWhere('shipment_code', 'like', "%{$q}%")
                  ->orWhere('tracking_number', 'like', "%{$q}%")
                  ->orWhere('destination_city', 'like', "%{$q}%")
                  ->orWhere('shipping_agency', 'like', "%{$q}%");
            });
        }

        $requirePayment = (bool) $setting->require_payment;
        $ctx = compact('filter', 'sort', 'range', 'from', 'to', 'type', 'pri',
            'group', 'groups', 'maxDays', 'skipHol', 'closed', 'q', 'requirePayment');

        return $query;
    }

    public function index(Request $request)
    {
        $ctx   = [];
        $query = $this->buildListQuery($request, $ctx);
        [$filter, $sort, $range, $from, $to, $type, $pri, $group, $groups, $maxDays, $skipHol, $closed, $q] = [
            $ctx['filter'], $ctx['sort'], $ctx['range'], $ctx['from'], $ctx['to'], $ctx['type'], $ctx['pri'],
            $ctx['group'], $ctx['groups'], $ctx['maxDays'], $ctx['skipHol'], $ctx['closed'], $ctx['q'],
        ];

        // Filas por página (selector del pie de tabla).
        $perPage = (int) $request->input('per_page', 20);
        if (!in_array($perPage, [10, 20, 50, 100], true)) {
            $perPage = 20;
        }
        // `printBatch` se precarga: la tabla muestra el lote de cada envío.
        $shipments = $query->with('printBatch')->paginate($perPage)->withQueryString();

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

        // Conteo por tipo de entrega (para las pastillas del panel).
        $metrics['domicilio'] = ShippingRequest::domicilio()->count();
        $metrics['agencia']   = ShippingRequest::agencia()->count();
        $metrics['tienda']    = ShippingRequest::where('delivery_type', ShippingRequest::DELIVERY_TIENDA)->count();
        $metrics['courier_active'] = ShippingRequest::courierActive()->count();

        // Conteo por prioridad (envíos abiertos que ya cruzaron el umbral).
        $cutU = ShippingRequest::agingCutoff(max(1, $maxDays - 1), $skipHol)->toDateString();
        $cutV = ShippingRequest::agingCutoff($maxDays, $skipHol)->toDateString();
        $metrics['urgentes'] = ShippingRequest::whereNotIn('status', $closed)
            ->whereDate('created_at', '<=', $cutU)->count();
        $metrics['vencidos'] = ShippingRequest::whereNotIn('status', $closed)
            ->whereDate('created_at', '<=', $cutV)->count();

        return view('tenant.shipments.index', [
            'shipments'   => $shipments,
            'filter'      => $filter,
            'counts'      => $counts,
            'metrics'     => $metrics,
            'group'       => $group,
            'type'        => $type,
            'q'           => $q,
            'sort'        => $sort,
            'pri'         => $pri,
            'maxDays'     => $maxDays,
            'skipHolidays'=> $skipHol,
            'perPage'     => $perPage,
            'from'        => $from,
            'to'          => $to,
            'range'       => $range,
            'statuses'    => ShippingRequest::STATUSES,
            'requirePayment' => $ctx['requirePayment'],
            'departments' => Department::orderBy('description')->get(['id', 'description']),
        ]);
    }

    /**
     * Exporta a CSV la lista con los MISMOS filtros del panel (Excel abre el
     * archivo con acentos gracias al BOM UTF-8).
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $ctx   = [];
        $query = $this->buildListQuery($request, $ctx);
        $maxDays = $ctx['maxDays'];
        $skipHol = $ctx['skipHol'];

        $filename = 'envios_' . now()->format('Ymd_His') . '.csv';
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->streamDownload(function () use ($query, $maxDays, $skipHol) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM para que Excel respete UTF-8
            fputcsv($out, [
                'Código', 'Cliente', 'Documento', 'N° documento', 'Celular',
                'Tipo', 'Ciudad/Destino', 'Agencia', 'Oficina/Dirección', 'Referencia',
                'Contenido', 'Bultos', 'Peso (kg)', 'Costo envío', 'Pago',
                'Estado', 'Días hábiles', 'Registrado', 'Guía',
            ]);
            $query->chunk(500, function ($rows) use ($out, $maxDays, $skipHol) {
                foreach ($rows as $s) {
                    $age = $s->aging($maxDays, $skipHol);
                    fputcsv($out, [
                        $s->shipment_code ?: ('#' . $s->id),
                        $s->full_name,
                        $s->document_label,
                        $s->dni,
                        $s->phone,
                        $s->is_domicilio ? 'Domicilio' : 'Agencia',
                        $s->destination_city,
                        $s->is_domicilio ? '' : $s->shipping_agency,
                        $s->is_domicilio ? ($s->shipping_destination ?: $s->formatted_address) : ($s->reference ?: $s->shipping_destination),
                        $s->is_domicilio ? $s->reference : '',
                        $s->package_content,
                        $s->package_count,
                        $s->weight,
                        $s->delivery_price ? number_format($s->delivery_price, 2, '.', '') : '',
                        $s->payment_confirmed ? 'Confirmado' : '',
                        $s->status_label,
                        $age['days'] === null ? '' : $age['days'],
                        optional($s->created_at)->format('d/m/Y H:i'),
                        $s->has_guide ? 'Sí' : 'No',
                    ]);
                }
            });
            fclose($out);
        }, $filename, $headers);
    }

    /**
     * Cambia el estado de VARIOS envíos a la vez (barra de selección). Salta los
     * bloqueados por pago y notifica a cada cliente por WhatsApp del cambio.
     */
    public function statusBulk(Request $request): RedirectResponse
    {
        $request->validate([
            'status' => 'required|in:' . implode(',', array_keys(ShippingRequest::STATUSES)),
        ]);
        $ids = collect(explode(',', (string) $request->input('ids', '')))
            ->map(fn ($x) => (int) trim($x))->filter()->unique()->take(200)->values();
        abort_if($ids->isEmpty(), 404);

        $status = $request->input('status');
        $shipments = ShippingRequest::whereIn('id', $ids)->get();

        $done = 0; $skipped = 0;
        foreach ($shipments as $s) {
            if ($this->paymentBlocks($s) || $s->is_cancelled) { $skipped++; continue; }
            $old = $s->status;
            $update = ['status' => $status];
            $sealStatuses = [
                ShippingRequest::STATUS_EN_AGENCIA, ShippingRequest::STATUS_EN_RUTA,
                ShippingRequest::STATUS_EN_CAMINO, ShippingRequest::STATUS_ENTREGADO,
            ];
            if (in_array($status, $sealStatuses, true) && !$s->sent_at) {
                $update['sent_at'] = now();
            }
            $s->update($update);
            if ($old !== $s->status) {
                $this->notifyStatusChange($s);
                $done++;
            }
        }

        $msg = "Estado cambiado en {$done} envío(s).";
        if ($skipped > 0) {
            $msg .= " {$skipped} se omitieron (pago pendiente o anulados).";
        }
        return back()->with('success', $msg);
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
        $this->stampPriority($shipment);

        return back()->with('success', "Envío {$shipment->shipment_code} registrado.");
    }

    /**
     * Subir la guía de envío. Guarda el N° de guía + el archivo, cambia el
     * estado a "enviado" y registra sent_at = now().
     */
    public function uploadGuide(Request $request, ShippingRequest $shipment): RedirectResponse
    {
        if ($this->paymentBlocks($shipment)) {
            return back()->with('error', "Confirma primero el pago de {$shipment->shipment_code} para subir la guía.");
        }

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
        if ($this->paymentBlocks($shipment)) {
            return back()->with('error', "Confirma primero el pago de {$shipment->shipment_code} para cambiar su estado.");
        }

        $request->validate([
            'status'        => 'required|in:' . implode(',', array_keys(ShippingRequest::STATUSES)),
            'courier_name'  => 'nullable|string|max:120',
            'courier_phone' => 'nullable|string|max:20',
        ]);

        $old = $shipment->status;
        $update = ['status' => $request->status];

        // Datos del motorizado al asignarlo (entrega a domicilio).
        if ($request->status === ShippingRequest::STATUS_ASIGNADO) {
            if ($request->filled('courier_name'))  $update['courier_name']  = $request->courier_name;
            if ($request->filled('courier_phone')) $update['courier_phone'] = $request->courier_phone;
        }

        // Sellar sent_at al "salir": agencia (a la agencia/tránsito) o motorizado (en camino).
        $sealStatuses = [
            ShippingRequest::STATUS_EN_AGENCIA, ShippingRequest::STATUS_EN_RUTA,
            ShippingRequest::STATUS_EN_CAMINO, ShippingRequest::STATUS_ENTREGADO,
        ];
        if (in_array($request->status, $sealStatuses, true) && !$shipment->sent_at) {
            $update['sent_at'] = now();
        }
        // Recojo en tienda: sellar la entrega en mano.
        if ($request->status === ShippingRequest::STATUS_ENTREGADO && $shipment->is_pickup) {
            $update['picked_up_at'] = now();
            if ($request->filled('picked_up_by')) {
                $update['picked_up_by'] = mb_substr($request->input('picked_up_by'), 0, 160);
            }
        }

        $shipment->update($update);

        // WhatsApp automático al cliente por el cambio de estado.
        if ($old !== $shipment->status) {
            $this->notifyStatusChange($shipment);

            ShippingAuditLog::log(
                $this->auditActionForStatus($request->status),
                $shipment->id,
                'status',
                $old,
                $shipment->status,
                null,
                $shipment->print_batch_id
            );
        }

        return back()->with('success', "Estado actualizado a «{$shipment->status_label}».");
    }

    /** La bitácora distingue despacho / entrega / recojo de un cambio de estado común. */
    private function auditActionForStatus(string $status): string
    {
        return match ($status) {
            ShippingRequest::STATUS_DESPACHADO, ShippingRequest::STATUS_EN_AGENCIA => ShippingAuditLog::ACTION_DISPATCH,
            ShippingRequest::STATUS_ENTREGADO     => ShippingAuditLog::ACTION_DELIVER,
            ShippingRequest::STATUS_LISTO_RECOJO  => ShippingAuditLog::ACTION_PICKUP,
            default => ShippingAuditLog::ACTION_STATUS,
        };
    }

    // ── Modalidad de entrega ───────────────────────────────────────────────

    /**
     * Cambia la modalidad de entrega y arrastra todo lo que depende de ella:
     * prioridad logística, agencia, guía, ubigeo, coordenadas y precio.
     *
     * Bloqueado si el envío ya está en un lote IMPRESO. Un usuario admin
     * puede forzarlo marcando la excepción, que queda registrada como tal.
     */
    public function changeModality(Request $request, ShippingRequest $shipment): RedirectResponse
    {
        $request->validate([
            'delivery_type' => ['required', Rule::in(array_keys(ShippingRequest::DELIVERY_TYPES))],
            'reason'        => ['nullable', 'string', 'max:255'],
            'force'         => ['nullable', 'boolean'],
        ]);

        $new = $request->input('delivery_type');
        $old = $shipment->delivery_type;

        if ($new === $old) {
            return back()->with('error', 'El envío ya tiene esa modalidad.');
        }

        [$allowed, $blockReason] = $shipment->canChangeModality();
        $isException = false;

        if (!$allowed) {
            // Excepción: solo admin, y queda marcada en la bitácora.
            $isAdmin = (auth()->user()->type ?? '') === 'admin';

            if (!$request->boolean('force') || !$isAdmin) {
                return back()->with('error', $blockReason);
            }

            $isException = true;
        }

        [$changes, $cleared] = $this->modalityCascade($shipment, $new);

        $shipment->forceFill($changes)->save();

        ShippingAuditLog::log(
            ShippingAuditLog::ACTION_MODALITY,
            $shipment->id,
            'delivery_type',
            ShippingRequest::DELIVERY_TYPES[$old] ?? $old,
            ShippingRequest::DELIVERY_TYPES[$new] ?? $new,
            $request->input('reason') ?: ($isException ? 'Excepción sobre lote impreso' : null),
            $shipment->print_batch_id,
            $isException
        );

        ShippingAuditLog::log(
            ShippingAuditLog::ACTION_PRIORITY,
            $shipment->id,
            'priority',
            ShippingRequest::priorityFor($old),
            ShippingRequest::priorityFor($new),
            'Automático por cambio de modalidad',
            $shipment->print_batch_id
        );

        $msg = "Modalidad cambiada a «" . ShippingRequest::DELIVERY_TYPES[$new] . "».";
        if ($isException) {
            $msg .= ' Se registró como EXCEPCIÓN sobre un lote impreso.';
        }
        if (!empty($cleared)) {
            $msg .= ' Se limpiaron: ' . implode(', ', $cleared) . '.';
        }

        return back()->with('success', $msg);
    }

    /**
     * Calcula qué cambia al mover un envío de modalidad.
     *
     * La regla es que no queden datos huérfanos: un recojo en tienda no puede
     * conservar agencia ni guía, y un envío a provincia no debe arrastrar las
     * coordenadas del motorizado.
     *
     * @return array{0: array<string, mixed>, 1: array<int, string>} [cambios, qué se limpió]
     */
    private function modalityCascade(ShippingRequest $shipment, string $new): array
    {
        $changes = [
            'delivery_type' => $new,
            'priority'      => ShippingRequest::priorityFor($new),
        ];

        $cleared = [];

        if ($new === ShippingRequest::DELIVERY_TIENDA) {
            // Recojo en tienda: sin agencia, sin guía, sin ruta, sin cobro de envío.
            foreach (['shipping_agency', 'tracking_number', 'courier_name', 'courier_phone',
                      'latitude', 'longitude', 'google_place_id', 'google_maps_url',
                      'formatted_address', 'distance_km', 'distance_text', 'duration_text'] as $field) {
                if ($shipment->{$field} !== null && $shipment->{$field} !== '') {
                    $changes[$field] = null;
                }
            }
            $changes['delivery_price'] = null;
            $changes['print_batch_id'] = null;   // sale de la cola de rotulado
            $cleared = ['agencia', 'datos de envío', 'cola de rotulado'];
        }

        if ($new === ShippingRequest::DELIVERY_AGENCIA) {
            // Provincia: la ruta del motorizado deja de aplicar.
            foreach (['latitude', 'longitude', 'google_place_id', 'google_maps_url',
                      'distance_km', 'distance_text', 'duration_text', 'courier_name', 'courier_phone'] as $field) {
                if ($shipment->{$field} !== null && $shipment->{$field} !== '') {
                    $changes[$field] = null;
                }
            }
            $cleared[] = 'ruta del motorizado';
        }

        if ($new === ShippingRequest::DELIVERY_DOMICILIO) {
            // Lima: la agencia de provincia deja de aplicar.
            if ($shipment->shipping_agency) {
                $changes['shipping_agency'] = null;
                $cleared[] = 'agencia de transporte';
            }
        }

        // Si el estado actual no existe en el flujo de la modalidad nueva, se
        // regresa al inicio del flujo para no dejar un estado imposible.
        $flow = ShippingRequest::statusOrderFor($new);
        if ($shipment->status !== ShippingRequest::STATUS_ANULADO
            && !in_array($shipment->status, $flow, true)) {
            $changes['status'] = ShippingRequest::STATUS_RECIBIDO;
            $cleared[] = 'estado (vuelve a pendiente)';
        }

        return [$changes, array_values(array_unique($cleared))];
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

        // La modalidad NO se cambia por aquí: tiene su propio flujo con
        // cascada, bloqueo por lote y auditoría (changeModality).
        unset($data['delivery_type']);

        $dirty = collect($data)->filter(fn ($v, $k) => (string) $shipment->{$k} !== (string) $v)->keys();

        $shipment->update($data);

        if ($dirty->isNotEmpty()) {
            ShippingAuditLog::log(
                ShippingAuditLog::ACTION_EDIT,
                $shipment->id,
                null,
                null,
                null,
                'Campos editados: ' . $dirty->implode(', '),
                $shipment->print_batch_id
            );
        }

        return back()->with('success', "Envío {$shipment->shipment_code} actualizado.");
    }

    /** Editar manualmente el precio del envío (el encargado ajusta la estimación). */
    public function updatePrice(Request $request, ShippingRequest $shipment): RedirectResponse
    {
        $data = $request->validate([
            'delivery_price' => 'nullable|numeric|min:0|max:99999',
        ], [], ['delivery_price' => 'precio de envío']);

        $shipment->update(['delivery_price' => $request->filled('delivery_price') ? $data['delivery_price'] : null]);

        return back()->with('success', "Precio de envío actualizado ({$shipment->shipment_code}).");
    }

    /** ¿La tienda exige confirmar el pago y este envío aún no está pagado? */
    private function paymentBlocks(ShippingRequest $shipment): bool
    {
        return ShippingSetting::current()->require_payment && !$shipment->payment_confirmed;
    }

    /** Confirmar (o revertir) el pago del envío. Habilita el resto del flujo. */
    public function confirmPayment(Request $request, ShippingRequest $shipment): RedirectResponse
    {
        $request->validate(['payment_note' => 'nullable|string|max:255']);

        $confirm = !$shipment->payment_confirmed; // toggle
        $shipment->update([
            'payment_confirmed'    => $confirm,
            'payment_confirmed_at' => $confirm ? now() : null,
            'payment_note'         => $confirm ? $request->input('payment_note') : null,
        ]);

        ShippingAuditLog::log(
            ShippingAuditLog::ACTION_PAYMENT,
            $shipment->id,
            'payment_confirmed',
            !$confirm,
            $confirm,
            $request->input('payment_note'),
            $shipment->print_batch_id
        );

        return back()->with('success', $confirm
            ? "Pago confirmado — {$shipment->shipment_code} habilitado."
            : "Se quitó la confirmación de pago de {$shipment->shipment_code}.");
    }

    /** Confirmar el pago de VARIOS envíos a la vez (barra de selección). */
    public function confirmPaymentBulk(Request $request): RedirectResponse
    {
        $ids = collect(explode(',', (string) $request->input('ids', '')))
            ->map(fn ($x) => (int) trim($x))->filter()->unique()->take(200)->values();
        abort_if($ids->isEmpty(), 404);

        $n = ShippingRequest::whereIn('id', $ids)
            ->where('payment_confirmed', false)
            ->update(['payment_confirmed' => true, 'payment_confirmed_at' => now()]);

        return back()->with('success', "Pago confirmado en {$n} envío(s).");
    }

    /** Anular un envío (queda en estado 'anulado', no se borra). */
    /**
     * Anula el envío. NUNCA se borra: cambia de estado, guarda quién, cuándo y
     * por qué, recuerda el estado previo para poder restaurarlo y lo saca de
     * la cola de rotulado si su lote sigue abierto.
     */
    public function cancel(Request $request, ShippingRequest $shipment): RedirectResponse
    {
        if ($shipment->status === ShippingRequest::STATUS_ANULADO) {
            return back()->with('error', 'El envío ya estaba anulado.');
        }

        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $user = auth()->user();
        $old  = $shipment->status;

        $update = [
            'status'               => ShippingRequest::STATUS_ANULADO,
            'status_before_cancel' => $old,
            'cancelled_at'         => now(),
            'cancelled_by'         => $user?->id,
            'cancelled_by_name'    => $user?->name,
            'cancel_reason'        => $request->input('reason'),
        ];

        // Si estaba en un lote todavía abierto, se libera para no ensuciarlo.
        $batchId = $shipment->print_batch_id;
        if ($batchId && !$shipment->isLockedByBatch()) {
            $update['print_batch_id'] = null;
        }

        $shipment->forceFill($update)->save();

        ShippingAuditLog::log(
            ShippingAuditLog::ACTION_CANCEL,
            $shipment->id,
            'status',
            $old,
            ShippingRequest::STATUS_ANULADO,
            $request->input('reason'),
            $batchId
        );

        return back()->with('success', "Envío {$shipment->shipment_code} anulado. Sigue en el historial y puede restaurarse.");
    }

    /**
     * Restaura un envío anulado: recupera su información y vuelve al estado
     * que tenía antes de anularse. Si su lote de entonces ya se imprimió, se
     * devuelve sin lote para que pueda entrar a uno nuevo.
     */
    public function restore(Request $request, ShippingRequest $shipment): RedirectResponse
    {
        if ($shipment->status !== ShippingRequest::STATUS_ANULADO) {
            return back()->with('error', 'Solo se pueden restaurar envíos anulados.');
        }

        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $user = auth()->user();

        // Vuelve a donde estaba; si el estado previo ya no aplica a su
        // modalidad (o no se guardó), arranca de nuevo en pendiente.
        $target = $shipment->status_before_cancel;
        $flow   = ShippingRequest::statusOrderFor($shipment->delivery_type);

        if (!$target || !in_array($target, $flow, true)) {
            $target = ShippingRequest::STATUS_RECIBIDO;
        }

        $shipment->forceFill([
            'status'          => $target,
            'restored_at'     => now(),
            'restored_by'     => $user?->id,
            'restored_by_name' => $user?->name,
            'restore_reason'  => $request->input('reason'),
            'cancelled_at'    => null,
            'cancelled_by'    => null,
            'cancelled_by_name' => null,
            'status_before_cancel' => null,
        ])->save();

        ShippingAuditLog::log(
            ShippingAuditLog::ACTION_RESTORE,
            $shipment->id,
            'status',
            ShippingRequest::STATUS_ANULADO,
            $target,
            $request->input('reason'),
            $shipment->print_batch_id
        );

        $msg = "Envío {$shipment->shipment_code} restaurado a «{$shipment->status_label}».";
        if (!$shipment->print_batch_id && $shipment->needsShippingLabel()) {
            $msg .= ' Ya puede volver a imprimirse.';
        }

        return back()->with('success', $msg);
    }

    /** Rótulo imprimible del envío (standalone, listo para imprimir/PDF). */
    public function printLabel(Request $request, ShippingRequest $shipment)
    {
        if ($this->paymentBlocks($shipment)) {
            return back()->with('error', "Confirma primero el pago de {$shipment->shipment_code} para imprimir su rótulo.");
        }

        // El recojo en tienda no lleva rótulo de transporte: se le entrega un
        // comprobante interno de entrega.
        if ($shipment->is_pickup) {
            return redirect()->route('shipments.pickup_receipt', $shipment);
        }

        // Reimpresión de un rótulo suelto: exige motivo y queda en el historial.
        $reason = trim((string) $request->query('motivo'));

        if ($shipment->print_count > 0 && $reason === '') {
            return back()->with('error',
                "El rótulo de {$shipment->shipment_code} ya se imprimió {$shipment->print_count} vez/veces. "
                . 'Indica el motivo para reimprimirlo.');
        }

        $company = Company::first();

        // Formato de impresión: sticker (10cm), A5 o A4.
        $format = strtolower((string) $request->query('format', 'a5'));
        if (!in_array($format, ['sticker', 'a5', 'a4'], true)) {
            $format = 'a5';
        }

        // Historial de impresiones del rótulo suelto (nunca se sobrescribe).
        $event = ShippingPrintEvent::record(null, $shipment->id, 1, $format, $reason ?: null);

        $shipment->forceFill([
            'print_count' => (int) $shipment->print_count + 1,
            'printed_at'  => now(),
        ])->save();

        ShippingAuditLog::log(
            $event->is_reprint ? ShippingAuditLog::ACTION_REPRINT : ShippingAuditLog::ACTION_PRINT,
            $shipment->id,
            'print_count',
            $shipment->print_count - 1,
            $shipment->print_count,
            $reason ?: null,
            $shipment->print_batch_id
        );

        return view('tenant.shipments.label', [
            'shipment' => $shipment,
            'company'  => $company,
            'ubigeo'   => $this->resolveUbigeo($shipment),
            'format'   => $format,
            'qr'       => $this->makeQr($shipment),
            'barcode'  => $this->makeBarcode($shipment),
        ]);
    }

    /** Impresión por lote: un rótulo por hoja (A5 o A4), varios envíos seguidos. */
    public function printBatch(Request $request)
    {
        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn ($x) => (int) trim($x))->filter()->unique()->take(60)->values();

        abort_if($ids->isEmpty(), 404);

        // Formato de impresión: A5 o A4 (un rótulo por hoja).
        $format = strtolower((string) $request->query('format', 'a5'));
        if (!in_array($format, ['a5', 'a4'], true)) {
            $format = 'a5';
        }

        $all = ShippingRequest::whereIn('id', $ids)->orderBy('id')->get();
        abort_if($all->isEmpty(), 404);

        // Los envíos con pago pendiente no se rotulan (regla `require_payment`).
        // Antes se rechazaban en silencio y, si TODOS estaban bloqueados, el
        // lote abortaba con un 404 mudo. Ahora los separamos: se explica cuáles
        // faltan confirmar en vez de mostrar un "error" sin contexto.
        [$blocked, $printable] = $all->partition(fn ($s) => $this->paymentBlocks($s));

        if ($printable->isEmpty()) {
            return response()->view('tenant.shipments.label-blocked', [
                'blocked' => $blocked->values(),
                'company' => Company::first(),
            ]);
        }

        $items = $printable->values()->map(fn ($s) => [
            'shipment' => $s,
            'ubigeo'   => $this->resolveUbigeo($s),
            'qr'       => $this->makeQr($s),
            'barcode'  => $this->makeBarcode($s),
        ])->all();

        return view('tenant.shipments.label-batch', [
            'items'   => $items,
            'company' => Company::first(),
            'format'  => $format,
            'ids'     => $printable->pluck('id')->implode(','),
            'skipped' => $blocked->values(),
        ]);
    }

    // ── Lotes de impresión ─────────────────────────────────────────────────

    /** Listado de lotes con sus totales y el estado de la ventana de corte. */
    public function batches(Request $request)
    {
        $service = new ShippingBatchService();

        $status = $request->input('estado');
        if (!array_key_exists($status, ShippingPrintBatch::STATUSES)) {
            $status = null;
        }

        $batches = ShippingPrintBatch::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->withCount('shipments')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $window = $service->currentWindow();

        return view('tenant.shipments.batches', [
            'batches'  => $batches,
            'status'   => $status,
            'window'   => $window,
            'ready'    => $service->readyForCurrentBatch(),
            'waiting'  => $service->waitingNextBatch(),
            'openCount'=> ShippingPrintBatch::open()->count(),
        ]);
    }

    /** Crea un lote con los envíos seleccionados en el panel. */
    public function storeBatch(Request $request): RedirectResponse
    {
        $request->validate([
            'ids'    => ['required'],
            'format' => ['nullable', 'in:sticker,a5,a4'],
            'notes'  => ['nullable', 'string', 'max:255'],
        ]);

        $ids = is_array($request->input('ids'))
            ? $request->input('ids')
            : explode(',', (string) $request->input('ids'));

        $result = (new ShippingBatchService())->createBatch(
            $ids,
            $request->input('format', 'a5'),
            $request->input('notes')
        );

        if (!$result['batch']) {
            $detail = collect($result['skipped'])->map(fn ($why, $code) => "{$code}: {$why}")->implode(' · ');

            return back()->with('error', 'No se pudo crear el lote. ' . ($detail ?: 'Ningún envío es elegible.'));
        }

        $msg = "Lote {$result['batch']->code} creado con {$result['added']} envío(s).";
        if (!empty($result['skipped'])) {
            $msg .= ' Quedaron fuera: '
                  . collect($result['skipped'])->map(fn ($why, $code) => "{$code} ({$why})")->implode(', ');
        }

        return redirect()->route('shipments.batches.show', $result['batch'])->with('success', $msg);
    }

    /** Ficha del lote: envíos, historial de impresiones y bitácora. */
    public function showBatch(ShippingPrintBatch $batch)
    {
        $batch->load(['shipments' => fn ($q) => $q->orderBy('priority')->orderBy('id')]);

        return view('tenant.shipments.batch-show', [
            'batch'   => $batch,
            'events'  => $batch->printEvents()->get(),
            'logs'    => $batch->auditLogs()->limit(100)->get(),
            'company' => Company::first(),
        ]);
    }

    /** Retira un envío de un lote todavía abierto. */
    public function removeFromBatch(ShippingRequest $shipment): RedirectResponse
    {
        [$ok, $msg] = (new ShippingBatchService())->removeFromBatch($shipment);

        return back()->with($ok ? 'success' : 'error', $msg);
    }

    /** Descarta un lote abierto y libera sus envíos. */
    public function discardBatch(ShippingPrintBatch $batch): RedirectResponse
    {
        [$ok, $msg] = (new ShippingBatchService())->discardBatch($batch);

        return $ok
            ? redirect()->route('shipments.batches')->with('success', $msg)
            : back()->with('error', $msg);
    }

    /** Cierra un lote ya despachado. */
    public function closeBatch(ShippingPrintBatch $batch): RedirectResponse
    {
        [$ok, $msg] = (new ShippingBatchService())->closeBatch($batch);

        return back()->with($ok ? 'success' : 'error', $msg);
    }

    /**
     * Imprime el lote: devuelve los rótulos y, la primera vez, marca el lote
     * como impreso (a partir de ahí sus envíos quedan bloqueados).
     * Las siguientes veces exigen motivo y se registran como reimpresión.
     */
    public function printBatchLabels(Request $request, ShippingPrintBatch $batch)
    {
        $format = strtolower((string) $request->query('format', $batch->format ?: 'a5'));
        if (!in_array($format, ['a5', 'a4'], true)) {
            $format = 'a5';
        }

        $shipments = $batch->shipments()->orderBy('priority')->orderBy('id')->get();

        if ($shipments->isEmpty()) {
            return redirect()->route('shipments.batches.show', $batch)
                             ->with('error', 'El lote no tiene envíos que rotular.');
        }

        $service = new ShippingBatchService();

        if (!$batch->isPrinted()) {
            $service->markPrinted($batch, $shipments->count(), $format);
            $batch->refresh();
        } else {
            // Reimpresión: el motivo llega por query desde el modal del panel.
            $reason = trim((string) $request->query('motivo'));

            if ($reason === '') {
                return redirect()->route('shipments.batches.show', $batch)
                                 ->with('error', 'Indica el motivo de la reimpresión antes de volver a imprimir el lote.');
            }

            $service->registerReprint($batch, $reason, $format);
        }

        $items = $shipments->map(fn ($s) => [
            'shipment' => $s,
            'ubigeo'   => $this->resolveUbigeo($s),
            'qr'       => $this->makeQr($s),
            'barcode'  => $this->makeBarcode($s),
        ])->all();

        return view('tenant.shipments.label-batch', [
            'items'   => $items,
            'company' => Company::first(),
            'format'  => $format,
            'ids'     => $shipments->pluck('id')->implode(','),
            'skipped' => collect(),
            'batch'   => $batch->fresh(),
        ]);
    }

    // ── Dashboard logístico ────────────────────────────────────────────────

    /** Indicadores de la operación: revisión, impresión, lotes y modalidades. */
    public function dashboard()
    {
        $service = new ShippingBatchService();
        $setting = ShippingSetting::current();

        $byType = ShippingRequest::where('status', '!=', ShippingRequest::STATUS_ANULADO)
                                 ->selectRaw('delivery_type, count(*) c')
                                 ->groupBy('delivery_type')
                                 ->pluck('c', 'delivery_type');

        $overdue = ShippingRequest::query()
            ->whereNotIn('status', ShippingRequest::CLOSED_STATUSES)
            ->where('created_at', '<=', ShippingRequest::agingCutoff($setting->max_days, (bool) $setting->aging_skip_holidays))
            ->count();

        $metrics = [
            'pending_review'   => ShippingRequest::where('status', ShippingRequest::STATUS_RECIBIDO)->count(),
            'pending_print'    => $service->readyForCurrentBatch()->count(),
            'waiting_next'     => $service->waitingNextBatch()->count(),
            'batches_open'     => ShippingPrintBatch::open()->count(),
            'batches_today'    => ShippingPrintBatch::printedToday()->count(),
            'lima'             => (int) ($byType[ShippingRequest::DELIVERY_DOMICILIO] ?? 0),
            'provincia'        => (int) ($byType[ShippingRequest::DELIVERY_AGENCIA] ?? 0),
            'tienda'           => (int) ($byType[ShippingRequest::DELIVERY_TIENDA] ?? 0),
            'cancelled'        => ShippingRequest::where('status', ShippingRequest::STATUS_ANULADO)->count(),
            'restored'         => ShippingRequest::whereNotNull('restored_at')->count(),
            'overdue'          => $overdue,
            'reprints'         => ShippingPrintEvent::reprints()->count(),
        ];

        return view('tenant.shipments.dashboard', [
            'metrics'      => $metrics,
            'window'       => $service->currentWindow(),
            'recentBatches'=> ShippingPrintBatch::orderByDesc('id')->limit(8)->get(),
            'recentLogs'   => ShippingAuditLog::orderByDesc('id')->limit(20)->get(),
            'exceptions'   => ShippingAuditLog::where('is_exception', true)->orderByDesc('id')->limit(10)->get(),
        ]);
    }

    /** Bitácora completa de un envío (modal del panel). */
    public function auditTrail(ShippingRequest $shipment)
    {
        return response()->json([
            'code' => $shipment->shipment_code,
            'logs' => $shipment->auditLogs()->limit(100)->get()->map(fn ($l) => [
                'action'    => $l->action_label,
                'summary'   => $l->summary,
                'notes'     => $l->notes,
                'user'      => $l->user_name,
                'exception' => $l->is_exception,
                'at'        => optional($l->created_at)->format('d/m/Y H:i'),
            ])->values(),
        ]);
    }

    /** Comprobante interno de entrega para los recojos en tienda. */
    public function pickupReceipt(ShippingRequest $shipment)
    {
        abort_unless($shipment->is_pickup, 404);

        return view('tenant.shipments.pickup-receipt', [
            'shipment' => $shipment,
            'company'  => Company::first(),
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

    /**
     * QR (PNG base64) del rótulo. En domicilio → abre la NAVEGACIÓN en Google
     * Maps (para el motorizado). En agencia → la página de estado rápido.
     */
    private function makeQr(ShippingRequest $shipment): ?string
    {
        try {
            if ($shipment->is_domicilio && $shipment->has_coords) {
                $url = $shipment->courier_directions_url;
            } else {
                $url = url('registro-envio/' . $shipment->id . '/estado-rapido');
            }
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
            $body = ShippingRequest::statusWhatsappMessage($shipment->status, $shipment->delivery_type);
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
            // Nombre COMERCIAL de la tienda (marca), no el nombre legal/persona:
            // title_web es la marca que configura cada tenant (ej. "Grupo Alasitas").
            $c        = Company::first();
            $tienda   = ($c->title_web ?? null) ?: ($c->trade_name ?? null) ?: ($c->name ?? null) ?: 'la tienda';

            $msg  = "Hola {$name} 👋\n\nTus datos fueron registrados correctamente.\n\n";
            if ($shipment->is_domicilio) {
                $msg .= "🏍️ Tu pedido será entregado por nuestro *motorizado* hasta tu dirección.\n\n";
            } else {
                $msg .= "📦 Tu pedido será enviado mediante *agencia de transporte*.\n";
                $msg .= "Cuando sea despachado recibirás la guía de envío.\n\n";
            }
            $msg .= "Código:\n*{$shipment->shipment_code}*\n\n";
            $msg .= "🔎 Consulta tu seguimiento aquí:\n{$trackUrl}\n\n";
            $msg .= "Gracias por comprar en {$tienda}.";
            dispatch(\App\Jobs\SendWhatsAppMessage::text($phone, $msg));
        } catch (\Throwable $e) {
            \Log::warning('[shipping] WhatsApp de registro no enviado: ' . $e->getMessage());
        }
    }

    /**
     * Aviso automático al NEGOCIO por WhatsApp con todos los datos del pedido
     * recién registrado (al número configurado en la config de tienda). Así el
     * encargado recibe el pedido sin depender de que el cliente lo reenvíe.
     */
    private function notifyStoreNewOrder(ShippingRequest $shipment): void
    {
        try {
            $to = ShippingSetting::current()->orders_wa;
            if (!$to) {
                return; // no hay número de pedidos configurado
            }
            dispatch(\App\Jobs\SendWhatsAppMessage::text($to, $this->buildOrderSummary($shipment)));
        } catch (\Throwable $e) {
            \Log::warning('[shipping] WhatsApp de pedido al negocio no enviado: ' . $e->getMessage());
        }
    }

    /** Resumen del pedido (para el aviso al negocio). Incluye datos internos. */
    private function buildOrderSummary(ShippingRequest $s): string
    {
        $c      = Company::first();
        $tienda = ($c->title_web ?? null) ?: ($c->trade_name ?? null) ?: ($c->name ?? null) ?: 'la tienda';
        $L = [];
        $L[] = "📦 *NUEVO PEDIDO* — {$tienda}";
        $L[] = "Código: *{$s->shipment_code}*";
        $L[] = "";
        $L[] = "👤 Cliente: {$s->full_name}";
        if ($s->dni)   $L[] = "🪪 {$s->document_label}: {$s->dni}";
        if ($s->phone) $L[] = "📱 Celular: {$s->phone}";
        $L[] = "";
        if ($s->is_domicilio) {
            $L[] = "🏍️ *Entrega a domicilio*";
            if ($s->formatted_address || $s->shipping_destination) $L[] = "📍 Dirección: " . ($s->formatted_address ?: $s->shipping_destination);
            if ($s->reference)      $L[] = "📌 Referencia: {$s->reference}";
            if ($s->maps_link)      $L[] = "🗺️ Ubicación: {$s->maps_link}";
            if ($s->distance_km)    $L[] = "🛵 Distancia: " . ($s->distance_text ?: ($s->distance_km . ' km')) . ($s->duration_text ? " · ~{$s->duration_text}" : '');
            if ($s->delivery_price) $L[] = "💵 Costo aprox. de envío: S/ " . number_format($s->delivery_price, 2);
        } else {
            $L[] = "📦 *Envío por agencia*";
            if ($s->destination_city)     $L[] = "🏙️ Destino: {$s->destination_city}";
            if ($s->shipping_agency)      $L[] = "🏢 Agencia: {$s->shipping_agency}";
            if ($s->shipping_destination) $L[] = "📍 Dirección: {$s->shipping_destination}";
            if ($s->reference)            $L[] = "📌 Referencia: {$s->reference}";
            if ($s->delivery_price)       $L[] = "💵 Servicio tienda→agencia: S/ " . number_format($s->delivery_price, 2);
        }
        if ($s->package_content) $L[] = "📦 Contenido: {$s->package_content}";
        if ($s->notes)           $L[] = "📝 Nota: {$s->notes}";
        $L[] = "";
        $L[] = "🔎 Seguimiento: " . url('envio/seguimiento?code=' . $s->shipment_code);
        return implode("\n", $L);
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

    /**
     * Tablero del MOTORIZADO: lista de entregas a domicilio con nombre, celular,
     * dirección, mapa y botón "Abrir en Google Maps" (navegación directa).
     */
    public function couriers(Request $request)
    {
        $query = ShippingRequest::query()->domicilio()->latest('id');

        $view = $request->input('view', 'activos');
        if ($view === 'entregados') {
            $query->where('status', ShippingRequest::STATUS_ENTREGADO)
                  ->whereDate('sent_at', now()->toDateString());
        } elseif ($view !== 'todos') {
            $query->courierActive();
            $view = 'activos';
        }

        $s = trim((string) $request->input('q', ''));
        if ($s !== '') {
            $query->where(function ($w) use ($s) {
                $w->where('full_name', 'like', "%{$s}%")
                  ->orWhere('shipment_code', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%");
            });
        }

        // Filas por página (selector del pie de tabla).
        $perPage = (int) $request->input('per_page', 20);
        if (!in_array($perPage, [10, 20, 50, 100], true)) {
            $perPage = 20;
        }
        $shipments = $query->paginate($perPage)->withQueryString();

        return view('tenant.shipments.couriers', [
            'company'   => Company::first(),
            'shipments' => $shipments,
            'view'      => $view,
            'q'         => $s,
            'statuses'  => ShippingRequest::STATUSES,
            'mapsKey'   => config('services.google_maps.key'),
            'counts'    => [
                'activos'    => ShippingRequest::courierActive()->count(),
                'entregados' => ShippingRequest::domicilio()->where('status', ShippingRequest::STATUS_ENTREGADO)
                                   ->whereDate('sent_at', now()->toDateString())->count(),
                'todos'      => ShippingRequest::domicilio()->count(),
            ],
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
            'company'     => Company::first(),
            'code'        => $code,
            'shipment'    => $shipment,
            'notFound'    => $notFound,
            'statuses'    => ShippingRequest::STATUSES,
            'statusOrder' => ShippingRequest::statusOrderFor($shipment?->delivery_type),
        ]);
    }

    public function publicForm()
    {
        $company = Company::first();

        $store = ShippingSetting::current();

        // En la pantalla de éxito, cargar el envío recién registrado para armar
        // el mensaje de WhatsApp con TODOS los datos que reenvía el cliente.
        $sent = session('shipment_code');
        $sentShipment = $sent ? ShippingRequest::where('shipment_code', $sent)->first() : null;

        return view('tenant.shipments.public', [
            'company'      => $company,
            'sent'         => $sent,
            'sentType'     => session('shipment_type'),
            'sentShipment' => $sentShipment,
            'ordersWa'     => $store->orders_wa,
            'departments'  => Department::orderBy('description')->get(['id', 'description']),
            'mapsKey'      => config('services.google_maps.key'),
            'storeLat'     => $store->store_latitude,
            'storeLng'     => $store->store_longitude,
            'pricePerKm'   => $store->has_pricing ? (float) $store->price_per_km : null,
            'basePrice'    => (float) $store->base_price,
            'minPrice'     => (float) $store->min_price,
            'agencyFee'    => (float) $store->agency_fee,
            // Dirección de la tienda: se muestra en la rama de recojo para que
            // el cliente sepa a dónde ir.
            'storeAddress' => $store->store_address,
            'mpReel'       => $this->marketplaceReel(),
            // Sorteo vigente: si lo hay, el cliente puede sumarse desde el
            // mismo formulario sin recibir ningún enlace aparte.
            'raffle'       => Raffle::publicActive(),
            'maxDays'      => $store->max_days,
        ]);
    }

    /**
     * Productos de ESTA tienda publicados en el marketplace, para el carrusel
     * de venta cruzada del formulario público.
     *
     * Se priorizan los que tienen descuento REAL (precio anterior > precio
     * actual). Ojo: hay listings marcados `is_on_offer` cuyo `original_price`
     * quedó igual al `price`; para esos NO se pinta el tachado ni el % porque
     * sería anunciar un descuento que el precio no refleja.
     *
     * @return \Illuminate\Support\Collection<int, array>
     */
    private function marketplaceReel()
    {
        try {
            $hostnameId = optional(app(Environment::class)->hostname())->id;

            if (!$hostnameId) {
                return collect();
            }

            return Cache::remember("mp_reel_{$hostnameId}", now()->addMinutes(10), function () use ($hostnameId) {
                // El marketplace vive en el dominio central: sus rutas no están
                // registradas en el dominio del tenant, así que la URL se arma
                // a mano en vez de con route()/url().
                $base = rtrim(config('app.url') ?: ('https://' . env('APP_URL_BASE')), '/');

                return MarketplaceListing::published()
                    ->where('hostname_id', $hostnameId)
                    ->orderByRaw('(original_price IS NOT NULL AND original_price > price) desc')
                    ->orderByDesc('discount_pct')
                    ->orderByDesc('is_featured')
                    ->orderByDesc('sort_score')
                    ->limit(12)
                    ->get(['slug', 'title', 'image_url', 'price', 'mp_price', 'original_price', 'discount_pct'])
                    ->map(function ($l) use ($base) {
                        $price = $l->display_price;
                        $antes = $l->original_price !== null ? (float) $l->original_price : null;
                        // Solo es descuento mostrable si el precio bajó de verdad.
                        $real  = $antes !== null && $antes > $price;

                        return [
                            'title'    => $l->title,
                            'image'    => $l->image_url,
                            'price'    => $price,
                            'before'   => $real ? $antes : null,
                            'discount' => $real ? (int) round((1 - $price / $antes) * 100) : null,
                            'url'      => $base . '/marketplace/item/' . $l->slug,
                        ];
                    });
            });
        } catch (\Throwable $e) {
            // El carrusel es un extra: si el marketplace no responde, el
            // formulario de envíos tiene que seguir funcionando igual.
            Log::warning('[Shipments] No se pudo cargar el carrusel del marketplace: ' . $e->getMessage());

            return collect();
        }
    }

    /** Configuración del módulo: fijar la ubicación (origen) de la tienda. */
    public function settings()
    {
        return view('tenant.shipments.settings', [
            'company' => Company::first(),
            'store'   => ShippingSetting::current(),
            'mapsKey' => config('services.google_maps.key'),
        ]);
    }

    /** Guarda la ubicación de la tienda (origen para el cálculo de distancia). */
    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'store_latitude'  => 'required|numeric|between:-90,90',
            'store_longitude' => 'required|numeric|between:-180,180',
            'store_address'   => 'nullable|string|max:500',
            'price_per_km'    => 'nullable|numeric|min:0|max:9999',
            'base_price'      => 'nullable|numeric|min:0|max:9999',
            'min_price'       => 'nullable|numeric|min:0|max:9999',
            'orders_whatsapp' => 'nullable|string|max:20',
            'agency_fee'      => 'nullable|numeric|min:0|max:99999',
            'require_payment' => 'nullable',
            'max_business_days'   => 'nullable|integer|min:1|max:60',
            'aging_skip_holidays' => 'nullable',
            'cutoff_time'         => 'nullable|date_format:H:i',
        ], [], [
            'store_latitude'  => 'ubicación de la tienda',
            'store_longitude' => 'ubicación de la tienda',
            'price_per_km'    => 'tarifa por km',
            'max_business_days' => 'plazo de atención',
        ]);

        $data['require_payment']     = $request->boolean('require_payment');
        $data['max_business_days']   = (int) ($request->input('max_business_days') ?: 4);
        $data['aging_skip_holidays'] = $request->boolean('aging_skip_holidays');
        // Hora de corte vacía = sin corte (la ventana pasa a ser el día calendario).
        $data['cutoff_time'] = $request->filled('cutoff_time') ? $request->input('cutoff_time') : null;

        $store = ShippingSetting::current();
        $store->update($data);

        return back()->with('success', 'Ubicación de la tienda guardada. Ya se calculará la distancia a cada cliente.');
    }

    public function publicStore(Request $request): RedirectResponse
    {
        $data = $this->validateShipment($request, true);
        $data['status']         = ShippingRequest::STATUS_RECIBIDO;
        $data['accepted_terms'] = true;

        // Anti-duplicado: si el mismo teléfono ya registró (mismo tipo de entrega)
        // en los últimos 10 min y sigue en "recibido", reusamos ese registro en
        // vez de crear otro. Cubre el doble clic y el "creo que no funcionó,
        // reintento" sin bloquear un envío genuinamente distinto (más tarde).
        $recent = ShippingRequest::where('phone', $data['phone'])
            ->where('delivery_type', $data['delivery_type'])
            ->where('status', ShippingRequest::STATUS_RECIBIDO)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->latest('id')
            ->first();

        if ($recent) {
            return redirect()->route('shipments.public.form')
                ->with('shipment_code', $recent->shipment_code)
                ->with('shipment_type', $recent->delivery_type)
                ->with('duplicate', true);
        }

        $shipment = ShippingRequest::create($data);
        $this->assignCode($shipment);
        $this->stampPriority($shipment);

        // WhatsApp "registro recibido" al cliente (async, best-effort).
        $this->notifyClientRegistered($shipment);
        // Aviso automático al negocio con todos los datos del pedido (async).
        $this->notifyStoreNewOrder($shipment);

        // Participación en el sorteo desde el propio formulario (opcional).
        $joined = $this->joinRaffleFromShipment($request, $shipment);

        return redirect()->route('shipments.public.form')
            ->with('shipment_code', $shipment->shipment_code)
            ->with('shipment_type', $shipment->delivery_type)
            ->with('joined_raffle', $joined)
            ->with('success', 'Tus datos se registraron. Guarda tu código de envío: ' . $shipment->shipment_code);
    }

    /**
     * Inscribe al cliente en el sorteo vigente si marcó la casilla.
     *
     * Queda como participante ACEPTADO de una vez: marcó la casilla con las
     * bases a la vista, que es el mismo consentimiento explícito que da quien
     * entra por el enlace. Así no hace falta mandarle nada aparte.
     *
     * Nunca interrumpe el registro del envío: si algo falla, el envío ya está
     * guardado y solo se pierde la inscripción, que se registra en el log.
     *
     * @return string|null Nombre del sorteo al que se sumó, o null.
     */
    private function joinRaffleFromShipment(Request $request, ShippingRequest $shipment): ?string
    {
        if (!$request->boolean('join_raffle')) {
            return null;
        }

        try {
            $raffle = Raffle::publicActive();

            if (!$raffle || !$raffle->acceptsParticipation()) {
                return null;
            }

            $key = RaffleParticipant::dedupeKeyFor($shipment->dni, null, $shipment->phone, null);

            // Puede que ya estuviera invitado por el barrido del admin: en ese
            // caso NO se duplica, solo se marca su aceptación.
            $participant = $raffle->participants()->where('dedupe_key', $key)->first();

            if (!$participant) {
                $participant = new RaffleParticipant([
                    'raffle_id'  => $raffle->id,
                    'full_name'  => mb_substr($shipment->full_name ?: 'Cliente', 0, 200),
                    'document'   => $shipment->dni,
                    'phone'      => $shipment->phone,
                    'dedupe_key' => $key,
                    'token'      => RaffleParticipant::makeToken(),
                ]);
            }

            if ($participant->status === RaffleParticipant::STATUS_ACCEPTED) {
                return $raffle->name;   // ya estaba dentro
            }

            $participant->fill([
                'status'            => RaffleParticipant::STATUS_ACCEPTED,
                'accepted_at'       => now(),
                'accept_ip'         => $request->ip(),
                'accept_user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'invited_via'       => 'envio',
                'invited_at'        => $participant->invited_at ?: now(),
            ])->save();

            return $raffle->name;
        } catch (\Throwable $e) {
            \Log::warning('[Shipments] No se pudo inscribir al sorteo: ' . $e->getMessage());
            return null;
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Reglas compartidas entre alta pública y de panel. En el público se exige
     * aceptar los términos.
     */
    private function validateShipment(Request $request, bool $public = false): array
    {
        // Discriminador de modalidad. Por defecto, agencia (retrocompat).
        $deliveryType = array_key_exists($request->input('delivery_type'), ShippingRequest::DELIVERY_TYPES)
            ? $request->input('delivery_type')
            : ShippingRequest::DELIVERY_AGENCIA;

        $isDomicilio = $deliveryType === ShippingRequest::DELIVERY_DOMICILIO;
        $isPickup    = $deliveryType === ShippingRequest::DELIVERY_TIENDA;

        // Reglas comunes a las tres modalidades.
        $rules = [
            'delivery_type'        => 'nullable|in:' . implode(',', array_keys(ShippingRequest::DELIVERY_TYPES)),
            'full_name'            => 'required|string|max:160',
            'dni'                  => 'nullable|string|max:20',
            'document_type'        => 'nullable|in:dni,ruc,ce,pasaporte',
            'phone'                => 'required|string|max:20',
            'reference'            => 'nullable|string|max:255',
            'package_content'      => 'nullable|string|max:2000',
            'package_count'        => 'nullable|integer|min:1|max:9999',
            'weight'               => 'nullable|numeric|min:0|max:999999',
            'notes'                => 'nullable|string|max:255',
            'observation'          => 'nullable|string|max:255',
            'order_id'             => 'nullable|integer',
        ];

        if ($isPickup) {
            // Recojo en tienda: el cliente pasa por su pedido, así que no hay
            // dirección, agencia, ubigeo ni cobro de envío que validar.
            $rules += [
                'shipping_destination' => 'nullable|string|max:255',
                'destination_city'     => 'nullable|string|max:120',
            ];
        } elseif ($isDomicilio) {
            // Entrega a domicilio (motorizado): dirección + Google Maps.
            $rules += [
                'shipping_destination' => 'required|string|max:500',
                'formatted_address'    => 'nullable|string|max:500',
                'destination_city'     => 'nullable|string|max:120',
                'latitude'             => 'nullable|numeric|between:-90,90',
                'longitude'            => 'nullable|numeric|between:-180,180',
                'google_place_id'      => 'nullable|string|max:255',
                'google_maps_url'      => 'nullable|string|max:500',
                'distance_km'          => 'nullable|numeric|min:0|max:9999',
                'distance_text'        => 'nullable|string|max:40',
                'duration_text'        => 'nullable|string|max:40',
                'delivery_price'       => 'nullable|numeric|min:0|max:99999',
            ];
        } else {
            // Envío por agencia: ubigeo obligatorio + agencia.
            $rules += [
                'shipping_destination' => 'nullable|string|max:255',
                'destination_city'     => 'nullable|string|max:120',
                'department_id'        => 'nullable|string|max:2',
                'province_id'          => 'nullable|string|max:4',
                'district_id'          => 'required|string|max:6',
                'shipping_agency'      => 'nullable|string|max:120',
            ];
        }

        if ($public) {
            $rules['accepted_terms'] = 'accepted';
        }

        $data = $request->validate($rules, [], [
            'full_name'            => 'nombre completo',
            'phone'                => 'teléfono',
            'district_id'          => 'distrito de destino',
            'shipping_destination' => 'dirección de entrega',
            'accepted_terms'       => 'aceptación de términos',
        ]);

        unset($data['accepted_terms']);

        // "Detalle del producto" es interno del almacén: el cliente no puede
        // enviarlo aunque manipule el formulario público.
        if ($public) {
            unset($data['package_content']);
        }

        $data['delivery_type']  = $deliveryType;
        $data['package_count']  = (int) ($request->input('package_count') ?: 1);
        // La prioridad logística se deriva de la modalidad (1 Lima, 2 recojo,
        // 3 provincia) — se sella aquí para que valga desde el alta.
        $data['priority']       = ShippingRequest::priorityFor($deliveryType);

        if ($isPickup) {
            // El recojo no viaja: nada de agencia, guía, ruta ni cobro de envío.
            $data['shipping_agency'] = null;
            $data['delivery_price']  = null;
            $data['destination_city'] = $data['destination_city'] ?? 'Recojo en tienda';
        }

        if ($isDomicilio) {
            // Normalizar coordenadas y construir la URL de Maps si falta.
            $lat = $request->filled('latitude') ? (float) $request->input('latitude') : null;
            $lng = $request->filled('longitude') ? (float) $request->input('longitude') : null;
            $data['latitude']  = $lat;
            $data['longitude'] = $lng;
            if ($lat !== null && $lng !== null && empty($data['google_maps_url'])) {
                $data['google_maps_url'] = 'https://www.google.com/maps/search/?api=1&query=' . $lat . ',' . $lng;
            }
            // Distancia tienda→cliente. Si el front no la trajo (Google Distance
            // Matrix), calculamos la línea recta (haversine) desde el origen.
            $store = ShippingSetting::current();
            if (empty($data['distance_km']) && $lat !== null && $lng !== null && $store->has_origin) {
                $km = ShippingRequest::haversineKm(
                    (float) $store->store_latitude, (float) $store->store_longitude, $lat, $lng
                );
                $data['distance_km']   = $km;
                $data['distance_text'] = $km . ' km aprox.';
            }
            // Precio del servicio (base + km × tarifa, con mínimo). El front lo
            // trae calculado; si falta, lo recalculamos con la config de tarifas.
            if (empty($data['delivery_price']) && !empty($data['distance_km'])) {
                $price = $store->quotePrice((float) $data['distance_km']);
                if ($price !== null) {
                    $data['delivery_price'] = $price;
                }
            }
            // La ciudad para el tablero: locality de Google o el texto libre.
            if (empty($data['destination_city']) && !empty($data['formatted_address'])) {
                $data['destination_city'] = \Illuminate\Support\Str::of($data['formatted_address'])->before(',')->limit(120, '');
            }
            // El ubigeo por agencia no aplica a domicilio: dejar nulo.
            $data['district_id'] = null;
            $data['province_id'] = null;
            $data['department_id'] = null;
        } else {
            // Ubigeo autoritativo: derivar provincia/departamento/ciudad del distrito.
            if (!empty($data['district_id'])) {
                $dist = District::find($data['district_id']);
                if ($dist) {
                    $data['province_id']      = $dist->province_id;
                    $prov                     = Province::find($dist->province_id);
                    $data['department_id']    = $prov ? $prov->department_id : ($data['department_id'] ?? null);
                    $data['destination_city'] = $dist->description;
                }
            }
            // Costo del servicio tienda→agencia (fijo por envío), si está configurado.
            $fee = (float) ShippingSetting::current()->agency_fee;
            if ($fee > 0) {
                $data['delivery_price'] = round($fee, 2);
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

    /**
     * Sella la prioridad logística que corresponde a la modalidad y deja el
     * alta registrada en la bitácora. Se llama al crear un envío, venga del
     * panel o del formulario público.
     */
    private function stampPriority(ShippingRequest $shipment): void
    {
        $priority = ShippingRequest::priorityFor($shipment->delivery_type);

        if ((int) $shipment->priority !== $priority) {
            $shipment->forceFill(['priority' => $priority])->save();
        }

        ShippingAuditLog::log(
            ShippingAuditLog::ACTION_STATUS,
            $shipment->id,
            'status',
            null,
            $shipment->status,
            'Alta del envío · ' . $shipment->delivery_label . ' · ' . $shipment->priority_label
        );
    }
}
