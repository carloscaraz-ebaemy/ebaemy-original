<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Tenant\Company;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\Item;
use App\Models\Tenant\Raffle;
use App\Models\Tenant\RaffleParticipant;
use App\Models\Tenant\RafflePrizeOption;
use App\Models\Tenant\RaffleWinner;
use App\Models\Tenant\SalesChannel;
use App\Services\Tenant\ImageProcessingService;
use App\Services\Tenant\RaffleEligibilityService;
use App\Services\Tenant\Raffles\ParticipantSource;
use App\Services\Tenant\Raffles\ParticipantSourceRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Modules\Item\Models\Category;

/**
 * Módulo de Sorteos integrado con Gestión de Pedidos.
 *
 * Flujo completo:
 *   1. El admin crea la campaña (premio, vigencia, criterios de elegibilidad).
 *   2. "Generar participantes" lee los pedidos con pago confirmado que cumplen
 *      los criterios y crea un RaffleParticipant por cliente (sin duplicados),
 *      cada uno con su token único → /sorteo/{token}.
 *   3. El cliente entra al enlace, ve el premio y las bases, y acepta.
 *   4. "Realizar sorteo" elige al azar entre los que ACEPTARON y registra el
 *      acto (quién, cuándo, sobre qué universo).
 */
class RaffleController extends Controller
{
    // ── Panel ──────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $status = $request->input('status');
        if (!array_key_exists($status, Raffle::STATUSES)) {
            $status = null;
        }

        $query = Raffle::query()->withCount([
            'participants',
            'participants as accepted_count' => fn ($q) => $q->where('status', RaffleParticipant::STATUS_ACCEPTED),
            'winners',
        ]);

        if ($status) {
            $query->where('status', $status);
        }

        if ($q = trim((string) $request->input('q'))) {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('code', 'like', "%{$q}%")
                  ->orWhere('prize_name', 'like', "%{$q}%");
            });
        }

        $raffles = $query->orderByDesc('id')->paginate(20)->withQueryString();

        // Indicadores globales del módulo.
        $counts = Raffle::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        $globals = [
            'active'       => (int) ($counts[Raffle::STATUS_ACTIVE] ?? 0),
            'finished'     => (int) ($counts[Raffle::STATUS_FINISHED] ?? 0),
            'draft'        => (int) ($counts[Raffle::STATUS_DRAFT] ?? 0),
            'cancelled'    => (int) ($counts[Raffle::STATUS_CANCELLED] ?? 0),
            'participants' => RaffleParticipant::count(),
            'accepted'     => RaffleParticipant::where('status', RaffleParticipant::STATUS_ACCEPTED)->count(),
            'winners'      => RaffleWinner::count(),
        ];
        $globals['acceptance'] = $globals['participants'] > 0
            ? round($globals['accepted'] * 100 / $globals['participants'], 1)
            : 0.0;

        return view('tenant.raffles.index', compact('raffles', 'globals', 'status'));
    }

    public function create()
    {
        $raffle = new Raffle([
            'status'         => Raffle::STATUS_DRAFT,
            'prize_quantity' => 1,
            'require_paid'   => true,
            'sources'        => [Raffle::SOURCE_DOCUMENTS, Raffle::SOURCE_SALE_NOTES],
        ]);

        return view('tenant.raffles.form', $this->formData($raffle));
    }

    public function edit(Raffle $raffle)
    {
        return view('tenant.raffles.form', $this->formData($raffle));
    }

    public function store(Request $request)
    {
        $data = $this->validateRaffle($request);

        $data['code']       = Raffle::nextCode();
        $data['created_by'] = auth()->id();

        $raffle = new Raffle();
        $this->fillRaffle($raffle, $data, $request);
        $raffle->save();

        $this->syncPrizeOptions($raffle, $request);

        return redirect()->route('raffles.show', $raffle)
                         ->with('success', "Sorteo {$raffle->code} creado." . $this->imageWarning());
    }

    /** Aviso de las imágenes que no se pudieron procesar, si las hubo. */
    private function imageWarning(): string
    {
        if (empty($this->imageErrors)) {
            return '';
        }

        return ' ⚠️ No se pudo procesar: ' . implode(' · ', $this->imageErrors);
    }

    public function update(Request $request, Raffle $raffle)
    {
        $data = $this->validateRaffle($request, $raffle);

        $this->fillRaffle($raffle, $data, $request);
        $raffle->save();

        $this->syncPrizeOptions($raffle, $request);

        return redirect()->route('raffles.show', $raffle)
                         ->with('success', 'Sorteo actualizado.' . $this->imageWarning());
    }

    /**
     * Ficha del sorteo: métricas, participantes y ganadores.
     * El conteo de elegibles se calcula aquí porque el admin necesita ver si
     * los criterios están bien antes de generar la lista.
     */
    public function show(Request $request, Raffle $raffle)
    {
        $service = new RaffleEligibilityService();

        $stats     = null;
        $statsFail = null;
        try {
            $stats = $service->analyze($raffle)['stats'];
        } catch (\Throwable $e) {
            $statsFail = $e->getMessage();
            Log::warning('[Raffles] No se pudo calcular el universo: ' . $e->getMessage());
        }

        $metrics = $raffle->metrics($stats['eligible'] ?? null);
        $source  = $service->sourceFor($raffle);

        $pStatus = $request->input('p');
        if (!array_key_exists($pStatus, RaffleParticipant::STATUSES)) {
            $pStatus = null;
        }

        $participants = $raffle->participants()
            // La tabla muestra la opción elegida: se precarga para no hacer
            // una consulta por fila.
            ->with('prizeOption:id,name')
            ->when($pStatus, fn ($q) => $q->where('status', $pStatus))
            ->when(trim((string) $request->input('q')), function ($q, $term) {
                $q->where(function ($w) use ($term) {
                    $w->where('full_name', 'like', "%{$term}%")
                      ->orWhere('document', 'like', "%{$term}%")
                      ->orWhere('email', 'like', "%{$term}%")
                      ->orWhere('phone', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('is_winner')
            ->orderByRaw("field(status, ?, ?, ?)", [
                RaffleParticipant::STATUS_ACCEPTED,
                RaffleParticipant::STATUS_INVITED,
                RaffleParticipant::STATUS_DECLINED,
            ])
            ->orderByDesc('total_amount')
            ->paginate(30)
            ->withQueryString();

        $winners = $raffle->winners()->with('participant')->get();

        $activeFilters = $this->describeFilters($source, $raffle);

        return view('tenant.raffles.show', compact(
            'raffle', 'metrics', 'participants', 'winners', 'pStatus',
            'stats', 'statsFail', 'source', 'activeFilters'
        ));
    }

    /**
     * Vista previa del universo (paso 4): cuántos se encontraron, cuántos
     * quedan tras deduplicar y cuántos se descartan, sin crear nada.
     */
    public function preview(Raffle $raffle)
    {
        try {
            $analysis = (new RaffleEligibilityService())->analyze($raffle);
        } catch (\Throwable $e) {
            Log::warning('[Raffles] preview: ' . $e->getMessage());

            return response()->json([
                'ok'      => false,
                'message' => 'No se pudo calcular el universo: ' . $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok'    => true,
            'stats' => $analysis['stats'],
            'rows'  => $analysis['rows']->take(50)->map(fn ($r) => [
                'name'     => $r['full_name'],
                'document' => $r['document'],
                'email'    => $r['email'],
                'phone'    => $r['phone'],
                'orders'   => $r['orders_count'],
                'total'    => round($r['total_amount'], 2),
                'last'     => $r['last_purchase_at'],
            ])->values(),
        ]);
    }

    /**
     * Confirma los participantes (paso 4→5): crea la lista desde el origen
     * configurado. Si el sorteo aún está en borrador, lo deja Activo para que
     * el enlace público empiece a funcionar.
     */
    public function syncParticipants(Request $request, Raffle $raffle)
    {
        if (in_array($raffle->status, [Raffle::STATUS_FINISHED, Raffle::STATUS_CANCELLED], true)) {
            return back()->with('error', 'El sorteo ya está cerrado.');
        }

        try {
            $result = (new RaffleEligibilityService())->syncParticipants($raffle);
        } catch (\Throwable $e) {
            Log::error('[Raffles] syncParticipants: ' . $e->getMessage());
            return back()->with('error', 'No se pudo generar la lista: ' . $e->getMessage());
        }

        $stats = $result['stats'];

        $msg = $result['created'] > 0
            ? "Se confirmaron {$result['created']} participantes nuevos (total {$result['total']})."
            : "No hay clientes nuevos que cumplan los criterios (total {$result['total']}).";

        $msg .= " Encontrados: {$stats['found']} · únicos: {$stats['unique']}"
              . " · duplicados eliminados: {$stats['duplicates']} · descartados: {$stats['rejected']}.";

        // Activar la campaña al confirmar, si el admin lo pidió y sigue en borrador.
        if ($request->boolean('activate') && $raffle->status === Raffle::STATUS_DRAFT && $result['total'] > 0) {
            $raffle->update(['status' => Raffle::STATUS_ACTIVE]);
            $msg .= ' El sorteo quedó ACTIVO y el enlace público ya funciona.';
        }

        return back()->with($result['total'] > 0 ? 'success' : 'error', $msg);
    }

    /**
     * Envía la invitación por WhatsApp a los participantes que aún no la
     * recibieron (o a uno concreto si viene `participant`).
     */
    public function invite(Request $request, Raffle $raffle)
    {
        if (!$raffle->acceptsParticipation()) {
            return back()->with('error', 'El sorteo no está aceptando participaciones: ' . $raffle->acceptanceWindow()[1]);
        }

        $query = $raffle->participants()->where('status', RaffleParticipant::STATUS_INVITED);

        if ($id = $request->input('participant')) {
            $query->where('id', $id);
        } elseif (!$request->boolean('resend')) {
            $query->whereNull('invited_at');
        }

        $company = Company::first();
        $store   = $company->trade_name ?? $company->name ?? 'nuestra tienda';

        $sent = 0;
        $skipped = 0;

        foreach ($query->cursor() as $participant) {
            $phone = $participant->whatsappPhone();

            if (!$phone) {
                $skipped++;
                continue;
            }

            $message = $this->invitationMessage($raffle, $participant, $store);

            try {
                SendWhatsAppMessage::text($phone, $message);
                $participant->update([
                    'invited_at'  => now(),
                    'invited_via' => 'whatsapp',
                ]);
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('[Raffles] WhatsApp falló para participante ' . $participant->id . ': ' . $e->getMessage());
                $skipped++;
            }
        }

        $msg = "Invitaciones enviadas: {$sent}.";
        if ($skipped > 0) {
            $msg .= " Sin enviar (sin teléfono válido o error): {$skipped}.";
        }

        return back()->with($sent > 0 ? 'success' : 'error', $msg);
    }

    /** Marca la invitación como entregada a mano (el admin copió el enlace). */
    public function markInvited(Raffle $raffle, RaffleParticipant $participant)
    {
        abort_if($participant->raffle_id !== $raffle->id, 404);

        $participant->update(['invited_at' => now(), 'invited_via' => 'manual']);

        return back()->with('success', 'Invitación marcada como enviada.');
    }

    /**
     * Realiza el sorteo: elige al azar entre los participantes que ACEPTARON
     * y que aún no ganaron, tantos como premios queden por asignar.
     */
    public function draw(Request $request, Raffle $raffle)
    {
        if ($raffle->status !== Raffle::STATUS_ACTIVE) {
            return back()->with('error', 'Solo se puede sortear una campaña en estado Activo.');
        }

        $prizes    = max(1, (int) $raffle->prize_quantity);
        $assigned  = $raffle->winners()->count();
        $remaining = $prizes - $assigned;

        if ($remaining < 1) {
            return back()->with('error', 'Ya se asignaron todos los premios de este sorteo.');
        }

        // Cuántos sacar en esta ejecución (por defecto todos los que faltan).
        $take = (int) $request->input('quantity', $remaining);
        $take = max(1, min($take, $remaining));

        $result = DB::connection('tenant')->transaction(function () use ($raffle, $take) {
            $pool = $raffle->participants()
                           ->accepted()
                           ->where('is_winner', false)
                           ->lockForUpdate()
                           // prize_option_id va en el select o la relación se
                           // carga vacía y el ganador perdería su elección.
                           ->get(['id', 'full_name', 'document', 'prize_option_id']);

            if ($pool->isEmpty()) {
                return ['ok' => false, 'message' => 'No hay participantes que hayan aceptado y estén disponibles.'];
            }

            $take   = min($take, $pool->count());
            $picked = $pool->shuffle()->take($take);

            $position = $raffle->winners()->max('position') ?? 0;
            $user     = auth()->user();
            $names    = [];

            foreach ($picked as $participant) {
                $position++;

                RaffleWinner::create([
                    'raffle_id'      => $raffle->id,
                    'participant_id' => $participant->id,
                    'position'       => $position,
                    'prize_name'     => $raffle->prize_name ?: $raffle->name,
                    'prize_image'    => $raffle->prize_image,
                    // Se congela la alternativa que eligió el ganador: la ficha
                    // sigue siendo válida aunque después se edite la opción.
                    'prize_option_name' => optional($participant->prizeOption)->name,
                    'drawn_at'       => now(),
                    'drawn_by'       => $user?->id,
                    'drawn_by_name'  => $user?->name,
                    'draw_snapshot'  => [
                        'pool_size'   => $pool->count(),
                        'accepted'    => $raffle->accepted()->count(),
                        'participants'=> $raffle->participants()->count(),
                        'executed_at' => now()->toDateTimeString(),
                    ],
                    'delivery_status'=> RaffleWinner::DELIVERY_PENDING,
                ]);

                $participant->update(['is_winner' => true]);
                $names[] = $participant->full_name;
            }

            return ['ok' => true, 'names' => $names, 'pool' => $pool->count()];
        });

        if (!$result['ok']) {
            return back()->with('error', $result['message']);
        }

        // Si ya no quedan premios por asignar, la campaña se cierra sola.
        if ($raffle->winners()->count() >= $prizes) {
            $raffle->update(['status' => Raffle::STATUS_FINISHED]);
        }

        return back()->with('success', '🏆 Ganador(es): ' . implode(', ', $result['names'])
            . " — elegidos al azar entre {$result['pool']} participantes.");
    }

    /** Cambia el estado administrativo de la campaña. */
    public function updateStatus(Request $request, Raffle $raffle)
    {
        $request->validate([
            'status' => ['required', Rule::in(array_keys(Raffle::STATUSES))],
        ]);

        $raffle->update(['status' => $request->input('status')]);

        return back()->with('success', 'Estado actualizado a ' . Raffle::STATUSES[$raffle->status] . '.');
    }

    /** Marca el premio como entregado / pendiente. */
    public function updateDelivery(Request $request, Raffle $raffle, RaffleWinner $winner)
    {
        abort_if($winner->raffle_id !== $raffle->id, 404);

        $request->validate([
            'delivery_status' => ['required', Rule::in([RaffleWinner::DELIVERY_PENDING, RaffleWinner::DELIVERY_DELIVERED])],
            'delivery_note'   => ['nullable', 'string', 'max:255'],
        ]);

        $delivered = $request->input('delivery_status') === RaffleWinner::DELIVERY_DELIVERED;

        $winner->update([
            'delivery_status' => $request->input('delivery_status'),
            'delivered_at'    => $delivered ? now() : null,
            'delivery_note'   => $request->input('delivery_note'),
        ]);

        return back()->with('success', $delivered ? 'Premio marcado como entregado.' : 'Entrega revertida a pendiente.');
    }

    public function destroy(Raffle $raffle)
    {
        if ($raffle->winners()->exists()) {
            return back()->with('error', 'No se puede eliminar un sorteo que ya tiene ganadores. Cámbialo a Cancelado.');
        }

        DB::connection('tenant')->transaction(function () use ($raffle) {
            $raffle->participants()->delete();
            $raffle->delete();
        });

        return redirect()->route('raffles.index')->with('success', 'Sorteo eliminado.');
    }

    /** Exporta los participantes a CSV (mismos filtros que la ficha). */
    public function export(Request $request, Raffle $raffle)
    {
        $pStatus = $request->input('p');
        if (!array_key_exists($pStatus, RaffleParticipant::STATUSES)) {
            $pStatus = null;
        }

        $query = $raffle->participants()
                        ->when($pStatus, fn ($q) => $q->where('status', $pStatus))
                        ->orderBy('id');

        $filename = 'sorteo-' . $raffle->code . '-participantes.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            // BOM para que Excel respete los acentos.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Nombre', 'Documento', 'Email', 'Teléfono', 'Estado', 'Pedidos', 'Monto', 'Última compra', 'Invitado', 'Aceptó', 'Ganador', 'Enlace'], ';');

            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $p) {
                    fputcsv($out, [
                        $p->full_name,
                        $p->document,
                        $p->email,
                        $p->phone,
                        $p->status_label,
                        $p->orders_count,
                        number_format((float) $p->total_amount, 2, '.', ''),
                        optional($p->last_purchase_at)->format('Y-m-d'),
                        optional($p->invited_at)->format('Y-m-d H:i'),
                        optional($p->accepted_at)->format('Y-m-d H:i'),
                        $p->is_winner ? 'SI' : '',
                        $p->invitationUrl(),
                    ], ';');
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ── Público (enlace del cliente) ───────────────────────────────────────

    /** Landing del enlace único: premio, fechas, bases y botón de aceptar. */
    public function publicShow(string $token)
    {
        $participant = RaffleParticipant::where('token', $token)->firstOrFail();
        $raffle      = $participant->raffle;

        abort_if(!$raffle, 404);

        [$open, $reason] = $raffle->acceptanceWindow();

        $company = Company::first();
        $winner  = $participant->is_winner
            ? RaffleWinner::where('participant_id', $participant->id)->first()
            : null;

        return view('tenant.raffles.public', compact('raffle', 'participant', 'company', 'open', 'reason', 'winner'));
    }

    /** El cliente acepta participar: aquí queda registrado oficialmente. */
    public function publicAccept(Request $request, string $token)
    {
        $participant = RaffleParticipant::where('token', $token)->firstOrFail();
        $raffle      = $participant->raffle;

        abort_if(!$raffle, 404);

        [$open, $reason] = $raffle->acceptanceWindow();

        if (!$open) {
            return redirect()->route('raffles.public.show', $token)->with('error', $reason);
        }

        if ($participant->status === RaffleParticipant::STATUS_ACCEPTED) {
            return redirect()->route('raffles.public.show', $token);
        }

        if (!$request->boolean('accept_terms')) {
            return redirect()->route('raffles.public.show', $token)
                             ->with('error', 'Debes aceptar las bases y condiciones para participar.');
        }

        // Elección del premio, si el sorteo ofrece alternativas. Se valida
        // contra las opciones DE ESTE sorteo para que nadie mande otro id.
        $optionId = null;

        if ($raffle->hasPrizeOptions()) {
            $optionId = $raffle->prizeOptions()->active()
                               ->where('id', $request->input('prize_option_id'))
                               ->value('id');

            if (!$optionId) {
                return redirect()->route('raffles.public.show', $token)
                                 ->with('error', 'Elige cuál premio quieres antes de confirmar tu participación.');
            }
        }

        $participant->update([
            'prize_option_id'   => $optionId,
            'status'            => RaffleParticipant::STATUS_ACCEPTED,
            'accepted_at'       => now(),
            'accept_ip'         => $request->ip(),
            'accept_user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            // Si el cliente corrige sus datos de contacto, los guardamos.
            'phone'             => $request->filled('phone') ? mb_substr($request->input('phone'), 0, 30) : $participant->phone,
            'email'             => $request->filled('email') ? mb_substr($request->input('email'), 0, 160) : $participant->email,
        ]);

        return redirect()->route('raffles.public.show', $token)
                         ->with('success', '¡Listo! Ya estás participando.');
    }

    /** El cliente declina la invitación. */
    public function publicDecline(string $token)
    {
        $participant = RaffleParticipant::where('token', $token)->firstOrFail();

        if ($participant->status !== RaffleParticipant::STATUS_ACCEPTED) {
            $participant->update(['status' => RaffleParticipant::STATUS_DECLINED]);
        }

        return redirect()->route('raffles.public.show', $token);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Buscador de productos para el criterio "productos específicos" del
     * formulario (chips). Limitado a 20 resultados.
     */
    public function searchItems(Request $request)
    {
        $term = trim((string) $request->input('q'));

        $query = Item::query()->select('id', 'description', 'internal_id');

        if ($term !== '') {
            $query->where(function ($w) use ($term) {
                $w->where('description', 'like', "%{$term}%")
                  ->orWhere('internal_id', 'like', "%{$term}%");
            });
        }

        // `ids` permite rehidratar los chips ya seleccionados al editar.
        if ($ids = array_filter((array) $request->input('ids', []))) {
            $query->orWhereIn('id', $ids);
        }

        return response()->json(
            $query->orderBy('description')->limit(20)->get()->map(fn ($i) => [
                'id'    => $i->id,
                'label' => trim(($i->internal_id ? "[{$i->internal_id}] " : '') . $i->description),
            ])
        );
    }

    private function formData(Raffle $raffle): array
    {
        // Los productos ya seleccionados se resuelven aquí para pintar los chips.
        // Viven en el filtro `items` del origen (antes en la columna item_ids).
        $selectedIds   = array_filter((array) ($raffle->source_filters['items'] ?? $raffle->item_ids ?? []));
        $selectedItems = collect();

        if (!empty($selectedIds)) {
            $selectedItems = Item::whereIn('id', $selectedIds)
                                 ->get(['id', 'description', 'internal_id'])
                                 ->map(fn ($i) => [
                                     'id'    => $i->id,
                                     'label' => trim(($i->internal_id ? "[{$i->internal_id}] " : '') . $i->description),
                                 ])
                                 ->values();
        }

        $registry = (new RaffleEligibilityService())->registry();

        return [
            'raffle'        => $raffle,
            'sources'       => $registry->all(),
            'filterOptions' => $this->filterOptions(),
            'selectedItems' => $selectedItems,
        ];
    }

    /**
     * Catálogos que los esquemas de filtros referencian por nombre
     * (`'options' => 'categories'`), resueltos una sola vez por render.
     */
    private function filterOptions(): array
    {
        $safe = function (callable $fn) {
            try {
                return $fn();
            } catch (\Throwable $e) {
                return [];
            }
        };

        return [
            'establishments' => $safe(fn () => Establishment::orderBy('description')->pluck('description', 'id')->all()),
            'categories'     => $safe(fn () => Category::orderBy('name')->pluck('name', 'id')->all()),
            'channels'       => $safe(fn () => SalesChannel::orderBy('name')->pluck('name', 'id')->all()),
            'marketplace_channels' => $safe(fn () => DB::connection('tenant')->table('marketplace_channels')
                                                        ->orderBy('name')->pluck('name', 'id')->all()),
            'marketplace_statuses' => $safe(function () {
                $rows = DB::connection('tenant')->table('marketplace_orders')
                          ->select('status')->distinct()->pluck('status')->filter()->all();
                return array_combine($rows, $rows) ?: [];
            }),
        ];
    }

    /**
     * Filtros activos de un sorteo en texto legible, para mostrarlos en la
     * ficha sin que el usuario tenga que abrir el formulario.
     */
    private function describeFilters(ParticipantSource $source, Raffle $raffle): array
    {
        $options = $this->filterOptions();
        $out     = [];

        foreach ($source->filters() as $filter) {
            $value = $raffle->source_filters[$filter['key']] ?? null;

            if ($value === null || $value === '' || $value === [] || $value === false || $value === '0') {
                continue;
            }

            $catalog = is_string($filter['options'] ?? null)
                ? ($options[$filter['options']] ?? [])
                : ($filter['options'] ?? []);

            $text = match ($filter['type']) {
                'boolean'     => 'Sí',
                'multiselect' => collect((array) $value)->map(fn ($v) => $catalog[$v] ?? $v)->implode(', '),
                'select'      => $catalog[$value] ?? $value,
                'items'       => count((array) $value) . ' producto(s)',
                'textarea'    => substr_count(trim((string) $value), "\n") + 1 . ' línea(s)',
                default       => (string) $value,
            };

            $out[] = ['label' => $filter['label'], 'value' => $text];
        }

        return $out;
    }

    private function validateRaffle(Request $request, ?Raffle $raffle = null): array
    {
        return $request->validate([
            'name'                   => ['required', 'string', 'max:160'],
            'description'            => ['nullable', 'string', 'max:5000'],
            'terms'                  => ['nullable', 'string', 'max:20000'],
            'status'                 => ['required', Rule::in(array_keys(Raffle::STATUSES))],

            'prize_name'             => ['nullable', 'string', 'max:160'],
            'prize_description'      => ['nullable', 'string', 'max:5000'],
            'prize_quantity'         => ['required', 'integer', 'min:1', 'max:999'],
            'prize_value'            => ['nullable', 'numeric', 'min:0'],
            'prize_image_file'       => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp,bmp,heic,heif', 'max:15360'],
            'gallery_files'          => ['nullable', 'array', 'max:10'],
            'gallery_files.*'        => ['image', 'mimes:jpeg,jpg,png,gif,webp,bmp,heic,heif', 'max:15360'],
            'remove_gallery'         => ['nullable', 'array'],

            'starts_at'              => ['nullable', 'date'],
            'registration_closes_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'draw_at'                => ['nullable', 'date', 'after_or_equal:registration_closes_at'],
            'winner_published_at'    => ['nullable', 'date'],

            // Origen de participantes: la clave se valida contra el registro;
            // los filtros se sanean por esquema en sanitizeFilters().
            'source'                 => ['required', Rule::in((new ParticipantSourceRegistry())->keys())],
            'min_amount'             => ['nullable', 'numeric', 'min:0'],
        ], [
            'registration_closes_at.after_or_equal' => 'El cierre de registro no puede ser anterior al inicio del sorteo.',
            'draw_at.after_or_equal'                => 'La fecha del sorteo no puede ser anterior al cierre de registro.',
            'source.required'                       => 'Elige de dónde saldrán los participantes.',
            'source.in'                             => 'El origen de participantes elegido no existe.',
        ]);
    }

    /**
     * Convierte los filtros crudos del formulario a los tipos que declara el
     * esquema del origen, descartando claves desconocidas. Así el JSON que se
     * guarda siempre corresponde al origen elegido.
     */
    private function sanitizeFilters(ParticipantSource $source, Request $request): array
    {
        $raw = (array) $request->input('filters', []);
        $out = [];

        foreach ($source->filters() as $filter) {
            $key   = $filter['key'];
            $value = $raw[$key] ?? null;

            switch ($filter['type']) {
                case 'boolean':
                    // Un checkbox desmarcado no se envía: la ausencia es "false".
                    $out[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    break;

                case 'number':
                    $out[$key] = ($value === null || $value === '') ? null : (float) $value;
                    break;

                case 'date':
                    $out[$key] = $this->validDate($value);
                    break;

                case 'multiselect':
                case 'items':
                    $out[$key] = array_values(array_filter(
                        (array) $value,
                        fn ($v) => $v !== null && $v !== ''
                    ));
                    break;

                case 'textarea':
                    $out[$key] = $value !== null ? mb_substr((string) $value, 0, 200000) : null;
                    break;

                default: // select, text
                    $out[$key] = ($value === null || $value === '') ? null : mb_substr((string) $value, 0, 255);
            }
        }

        return array_filter($out, fn ($v) => $v !== null && $v !== [] && $v !== '');
    }

    /** Devuelve la fecha si es válida (Y-m-d), o null. */
    private function validDate($value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function fillRaffle(Raffle $raffle, array $data, Request $request): void
    {
        $raffle->fill([
            'name'                   => $data['name'],
            'description'            => $data['description'] ?? null,
            'terms'                  => $data['terms'] ?? null,
            'status'                 => $data['status'],
            'prize_name'             => $data['prize_name'] ?? null,
            'prize_description'      => $data['prize_description'] ?? null,
            'prize_quantity'         => $data['prize_quantity'],
            'prize_value'            => $data['prize_value'] ?? null,
            'starts_at'              => $data['starts_at'] ?? null,
            'registration_closes_at' => $data['registration_closes_at'] ?? null,
            'draw_at'                => $data['draw_at'] ?? null,
            'winner_published_at'    => $data['winner_published_at'] ?? null,
            'min_amount'             => $data['min_amount'] ?? null,
        ]);

        // Origen de participantes + sus filtros propios.
        $source = (new ParticipantSourceRegistry())->resolveOrDefault($data['source']);

        $raffle->source         = $source->key();
        $raffle->source_filters = $this->sanitizeFilters($source, $request);

        if (isset($data['code'])) {
            $raffle->code = $data['code'];
        }
        if (isset($data['created_by'])) {
            $raffle->created_by = $data['created_by'];
        }

        // Imagen principal del premio.
        if ($request->hasFile('prize_image_file')) {
            $stored = $this->storeImage($request->file('prize_image_file'), $raffle->name);
            if ($stored) {
                $raffle->prize_image = $stored;
            }
        }

        // Galería: quitar las marcadas y añadir las nuevas.
        $gallery = collect($raffle->prize_gallery ?? []);

        if ($remove = $request->input('remove_gallery')) {
            $gallery = $gallery->reject(fn ($f) => in_array($f, (array) $remove, true));
        }

        foreach ((array) $request->file('gallery_files', []) as $file) {
            $stored = $this->storeImage($file, $raffle->name);
            if ($stored) {
                $gallery->push($stored);
            }
        }

        $raffle->prize_gallery = $gallery->filter()->unique()->values()->all();
    }

    /**
     * Guarda las alternativas de premio entre las que elige el cliente.
     *
     * Llega como arrays paralelos desde el formulario (`options[name][]`,
     * `options[description][]`, `options[id][]` y los archivos
     * `option_images[]`). Las existentes se actualizan conservando su imagen
     * si no se subió una nueva; las que el admin quitó se eliminan.
     */
    private function syncPrizeOptions(Raffle $raffle, Request $request): void
    {
        $names  = (array) $request->input('options.name', []);
        $descs  = (array) $request->input('options.description', []);
        $ids    = (array) $request->input('options.id', []);
        $files  = (array) $request->file('options.image', []);

        $kept = [];

        foreach ($names as $i => $name) {
            $name = trim((string) $name);

            if ($name === '') {
                continue;   // fila vacía del formulario
            }

            $option = !empty($ids[$i])
                ? $raffle->prizeOptions()->find($ids[$i])
                : null;

            if (!$option) {
                $option = new RafflePrizeOption(['raffle_id' => $raffle->id]);
            }

            $option->raffle_id   = $raffle->id;
            $option->name        = mb_substr($name, 0, 160);
            $option->description = isset($descs[$i]) ? mb_substr(trim((string) $descs[$i]), 0, 500) : null;
            $option->sort_order  = $i;
            $option->is_active   = true;

            if (!empty($files[$i])) {
                $stored = $this->storeImage($files[$i], $name);
                if ($stored) {
                    $option->image = $stored;
                }
            }

            $option->save();
            $kept[] = $option->id;
        }

        // Las opciones que el admin quitó del formulario se eliminan, salvo
        // que algún participante ya la haya elegido (se conserva su decisión).
        $raffle->prizeOptions()
               ->whereNotIn('id', $kept ?: [0])
               ->get()
               ->each(function ($option) {
                   if ($option->participants()->exists()) {
                       $option->update(['is_active' => false]);
                   } else {
                       $option->delete();
                   }
               });
    }

    /**
     * Procesa una imagen con el pipeline estándar (WEBP + 3 tamaños) y
     * devuelve el filename `main`. Nunca revienta el guardado del sorteo:
     * si la imagen falla, se registra y se continúa.
     */
    private function storeImage($file, string $context): ?string
    {
        if (!$file || !$file->isValid()) {
            return null;
        }

        try {
            $temp = $file->getRealPath();
            $base = ImageProcessingService::sanitizeFilename(
                $file->getClientOriginalName() ?: 'premio',
                'sorteo'
            );

            $result = ImageProcessingService::processAndStore($temp, $base);

            return $result['main'] ?? null;
        } catch (\Throwable $e) {
            Log::warning("[Raffles] Imagen del premio ({$context}) no procesada: " . $e->getMessage());
            // Se acumula para avisarle al admin: antes la imagen se perdía en
            // silencio y el sorteo quedaba guardado sin foto sin explicación.
            $this->imageErrors[] = ($file->getClientOriginalName() ?: 'la imagen') . ': ' . $e->getMessage();
            return null;
        }
    }

    /** Fallos de imagen de la petición actual, para avisarlos al guardar. */
    private array $imageErrors = [];

    private function invitationMessage(Raffle $raffle, RaffleParticipant $participant, string $store): string
    {
        $lines = [];
        $lines[] = "🎁 *{$raffle->name}*";
        $lines[] = '';
        $lines[] = "¡Hola {$participant->full_name}! Por tus compras en {$store} estás invitado a participar en nuestro sorteo.";

        if ($raffle->prize_name) {
            $lines[] = '';
            $lines[] = "🏆 Premio: {$raffle->prize_name}";
        }
        if ($raffle->draw_at) {
            $lines[] = '📅 Sorteo: ' . $raffle->draw_at->format('d/m/Y H:i');
        }
        if ($raffle->registration_closes_at) {
            $lines[] = '⏳ Registro hasta: ' . $raffle->registration_closes_at->format('d/m/Y H:i');
        }

        $lines[] = '';
        $lines[] = 'Confirma tu participación aquí:';
        $lines[] = $participant->invitationUrl();

        return implode("\n", $lines);
    }
}
