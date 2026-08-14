<?php

namespace App\Models\Tenant;

use App\Services\Tenant\ImageProcessingService;
use Illuminate\Database\Eloquent\Model;

/**
 * Campaña de sorteo (módulo Sorteos, integrado con Gestión de Pedidos).
 *
 * Ciclo de vida:
 *   draft → active → finished   (o cancelled en cualquier momento)
 *
 * La ventana de participación la definen 3 fechas:
 *   starts_at              → antes de esto NO se aceptan participantes
 *   registration_closes_at → después de esto NO se aceptan participantes
 *   draw_at                → fecha/hora en que se ejecuta el sorteo
 *
 * `phase()` deriva el estado visible al cliente (proximamente / en_curso /
 * finalizado) a partir de esas fechas + el status administrativo.
 *
 * @property int $id
 * @property string $code
 * @property string $status
 */
class Raffle extends Model
{
    protected $connection = 'tenant';

    protected $table = 'raffles';

    protected $fillable = [
        'code',
        'name',
        'description',
        'terms',
        'prize_name',
        'prize_description',
        'prize_image',
        'prize_gallery',
        'prize_quantity',
        'prize_value',
        'status',
        'source',
        'source_filters',
        'eligibility_mode',
        'exclude_past_winners',
        'participants_confirmed_at',
        'starts_at',
        'registration_closes_at',
        'draw_at',
        'winner_published_at',
        'sources',
        'require_paid',
        'purchase_from',
        'purchase_to',
        'min_amount',
        'establishment_id',
        'channel_id',
        'category_ids',
        'item_ids',
        'created_by',
    ];

    protected $casts = [
        'prize_gallery'          => 'array',
        'source_filters'         => 'array',
        'sources'                => 'array',
        'category_ids'           => 'array',
        'item_ids'               => 'array',
        'require_paid'           => 'boolean',
        'prize_quantity'         => 'integer',
        'prize_value'            => 'decimal:2',
        'min_amount'             => 'decimal:2',
        'establishment_id'       => 'integer',
        'channel_id'             => 'integer',
        'created_by'             => 'integer',
        'participants_confirmed_at' => 'datetime',
        'starts_at'              => 'datetime',
        'registration_closes_at' => 'datetime',
        'draw_at'                => 'datetime',
        'winner_published_at'    => 'datetime',
        'purchase_from'          => 'date',
        'purchase_to'            => 'date',
    ];

    // ── Estados administrativos ────────────────────────────────────────────
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_FINISHED  = 'finished';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT     => 'Borrador',
        self::STATUS_ACTIVE    => 'Activo',
        self::STATUS_FINISHED  => 'Finalizado',
        self::STATUS_CANCELLED => 'Cancelado',
    ];

    /** Color de badge por estado (clases Bootstrap del panel). */
    public const STATUS_COLORS = [
        self::STATUS_DRAFT     => 'secondary',
        self::STATUS_ACTIVE    => 'success',
        self::STATUS_FINISHED  => 'primary',
        self::STATUS_CANCELLED => 'danger',
    ];

    // ── Fases visibles (derivadas de las fechas) ───────────────────────────
    public const PHASE_SOON     = 'proximamente';
    public const PHASE_RUNNING  = 'en_curso';
    public const PHASE_FINISHED = 'finalizado';

    public const PHASE_LABELS = [
        self::PHASE_SOON     => 'Próximamente',
        self::PHASE_RUNNING  => 'En curso',
        self::PHASE_FINISHED => 'Finalizado',
    ];

    /** Orígenes de pedidos que pueden alimentar la lista de elegibles. */
    public const SOURCE_DOCUMENTS  = 'documents';
    public const SOURCE_SALE_NOTES = 'sale_notes';
    public const SOURCE_ORDERS     = 'orders';

    public const SOURCES = [
        self::SOURCE_DOCUMENTS  => 'Comprobantes (facturas / boletas)',
        self::SOURCE_SALE_NOTES => 'Notas de venta',
        self::SOURCE_ORDERS     => 'Pedidos de tienda virtual',
    ];

    // ── Relaciones ─────────────────────────────────────────────────────────

    public function participants()
    {
        return $this->hasMany(RaffleParticipant::class, 'raffle_id');
    }

    public function accepted()
    {
        return $this->hasMany(RaffleParticipant::class, 'raffle_id')
                    ->where('status', RaffleParticipant::STATUS_ACCEPTED);
    }

    public function winners()
    {
        return $this->hasMany(RaffleWinner::class, 'raffle_id')->orderBy('position');
    }

    /** Alternativas de premio entre las que elige el cliente (puede no haber). */
    public function prizeOptions()
    {
        return $this->hasMany(RafflePrizeOption::class, 'raffle_id')
                    ->orderBy('sort_order')->orderBy('id');
    }

    /**
     * ¿El sorteo ofrece varias alternativas de premio?
     * Con una sola opción no tiene sentido pedirle que "elija".
     */
    public function hasPrizeOptions(): bool
    {
        try {
            return $this->prizeOptions()->active()->count() > 1;
        } catch (\Throwable $e) {
            // Tenant sin la tabla todavía: se comporta como premio único.
            return false;
        }
    }

    public function establishment()
    {
        return $this->belongsTo(Establishment::class, 'establishment_id');
    }

    public function channel()
    {
        return $this->belongsTo(SalesChannel::class, 'channel_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Sorteo vigente que se le puede ofrecer a un cliente en un formulario
     * público (p. ej. al registrar un envío): activo y con la ventana de
     * participación abierta. Si hay varios, el que sortea antes.
     *
     * Devuelve null sin reventar si el tenant todavía no tiene el módulo.
     */
    // ── Modo de elegibilidad ───────────────────────────────────────────────

    public const ELIG_CONSENT    = 'consent';
    public const ELIG_HISTORICAL = 'historical';

    /**
     * ¿Hace falta que el participante haya aceptado?
     *
     * En las campañas históricas no: cuando ocurrieron esas compras el sistema
     * todavía no pedía la aceptación, así que exigirla dejaria el sorteo vacio.
     * Se sortea entre quienes cumplen los criterios del negocio y la campaña
     * queda marcada como histórica — no se toca el consentimiento de nadie.
     */
    public function requiresAcceptance(): bool
    {
        return $this->eligibility_mode !== self::ELIG_HISTORICAL;
    }

    public function isHistorical(): bool
    {
        return $this->eligibility_mode === self::ELIG_HISTORICAL;
    }

    /** Criterios en texto, para que el admin pueda auditarlos antes de sortear. */
    public function criteriaSummary(): array
    {
        $c = [];

        if ($this->isHistorical()) {
            $c[] = 'Campaña histórica: elegibilidad por criterios del negocio';
        } else {
            $c[] = 'El cliente aceptó participar desde su enlace';
        }

        if ($this->starts_at || $this->registration_closes_at) {
            $c[] = 'Período: ' . (optional($this->starts_at)->format('d/m/Y') ?: '—')
                 . ' a ' . (optional($this->registration_closes_at)->format('d/m/Y') ?: '—');
        }

        $f = is_array($this->source_filters) ? $this->source_filters : [];
        if (!empty($f['paid']))      $c[] = 'Pago confirmado';
        if (!empty($f['completed'])) $c[] = 'Pedido enviado o entregado';

        $c[] = 'Un participante por cliente (sin duplicados)';

        if ($this->exclude_past_winners) {
            $c[] = 'Se excluye a quienes ya ganaron un sorteo';
        }

        return $c;
    }

    public static function publicActive(): ?self
    {
        try {
            return static::active()
                ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($q) => $q->whereNull('registration_closes_at')->orWhere('registration_closes_at', '>', now()))
                ->where(fn ($q) => $q->whereNull('draw_at')->orWhere('draw_at', '>', now()))
                ->orderByRaw('draw_at is null, draw_at')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ── Estado / vigencia ──────────────────────────────────────────────────

    /**
     * Fase visible del sorteo según las fechas configuradas.
     * Un sorteo cancelado o finalizado administrativamente siempre es "finalizado".
     */
    public function phase(): string
    {
        if (in_array($this->status, [self::STATUS_FINISHED, self::STATUS_CANCELLED], true)) {
            return self::PHASE_FINISHED;
        }

        $now = now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return self::PHASE_SOON;
        }
        if ($this->registration_closes_at && $now->gt($this->registration_closes_at)) {
            return self::PHASE_FINISHED;
        }
        if ($this->draw_at && $now->gt($this->draw_at)) {
            return self::PHASE_FINISHED;
        }

        return self::PHASE_RUNNING;
    }

    public function getPhaseLabelAttribute(): string
    {
        return self::PHASE_LABELS[$this->phase()] ?? '—';
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * ¿Se pueden aceptar participaciones AHORA?
     * Devuelve [bool $ok, string|null $motivo] para poder explicárselo al cliente.
     */
    public function acceptanceWindow(): array
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return [false, 'Este sorteo fue cancelado.'];
        }
        if ($this->status === self::STATUS_DRAFT) {
            return [false, 'Este sorteo aún no está publicado.'];
        }
        if ($this->status === self::STATUS_FINISHED) {
            return [false, 'Este sorteo ya finalizó.'];
        }

        $now = now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return [false, 'El sorteo empieza el ' . $this->starts_at->format('d/m/Y H:i') . '.'];
        }
        if ($this->registration_closes_at && $now->gt($this->registration_closes_at)) {
            return [false, 'El registro de participantes cerró el ' . $this->registration_closes_at->format('d/m/Y H:i') . '.'];
        }
        if ($this->draw_at && $now->gt($this->draw_at)) {
            return [false, 'El sorteo ya se realizó.'];
        }

        return [true, null];
    }

    public function acceptsParticipation(): bool
    {
        return $this->acceptanceWindow()[0];
    }

    /** ¿Ya se puede ejecutar el sorteo? (no exige esperar a draw_at, pero avisa). */
    public function canDraw(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->winners()->count() < max(1, (int) $this->prize_quantity);
    }

    // ── Imágenes ───────────────────────────────────────────────────────────

    public function prizeImageUrl(?string $variant = 'medium'): ?string
    {
        if (!$this->prize_image) {
            return null;
        }
        return ImageProcessingService::getUrl($this->prize_image, $variant);
    }

    /** URLs de la galería (array vacío si no hay). */
    public function galleryUrls(?string $variant = 'medium'): array
    {
        return collect($this->prize_gallery ?? [])
            ->filter()
            ->map(fn ($f) => ImageProcessingService::getUrl($f, $variant))
            ->values()
            ->all();
    }

    // ── Código correlativo ─────────────────────────────────────────────────

    /** Genera el siguiente código SRT-AAAA-000N. */
    public static function nextCode(): string
    {
        $year = now()->format('Y');
        $last = static::where('code', 'like', "SRT-{$year}-%")
                      ->orderByDesc('id')
                      ->value('code');

        $n = $last ? ((int) substr($last, -4)) + 1 : 1;

        return sprintf('SRT-%s-%04d', $year, $n);
    }

    // ── Métricas del panel ─────────────────────────────────────────────────

    /**
     * Indicadores del sorteo. `$eligible` se pasa aparte porque calcularlo
     * consulta las tablas de pedidos (caro) — el panel lo hace solo cuando
     * hace falta.
     */
    public function metrics(?int $eligible = null): array
    {
        $rows = $this->participants()
                     ->selectRaw('status, count(*) as c')
                     ->groupBy('status')
                     ->pluck('c', 'status');

        $invited  = (int) $rows->sum();
        $accepted = (int) ($rows[RaffleParticipant::STATUS_ACCEPTED] ?? 0);
        $declined = (int) ($rows[RaffleParticipant::STATUS_DECLINED] ?? 0);

        return [
            'eligible'    => $eligible,
            'invited'     => $invited,
            'accepted'    => $accepted,
            'declined'    => $declined,
            'pending'     => max(0, $invited - $accepted - $declined),
            'winners'     => $this->winners()->count(),
            'acceptance'  => $invited > 0 ? round($accepted * 100 / $invited, 1) : 0.0,
        ];
    }
}
