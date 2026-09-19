<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Hoja de reclamación del Libro de Reclamaciones.
 *
 * Vive en la BD del tenant: el aislamiento es por conexión, no por scope.
 *
 * @mixin ModelTenant
 */
class Claim extends ModelTenant
{
    use SoftDeletes;

    protected $table = 'claims';

    /** Plazo legal de respuesta: 15 días hábiles improrrogables (D.S. 101-2022-PCM). */
    public const RESPONSE_BUSINESS_DAYS = 15;

    /** Días hábiles restantes a partir de los cuales el plazo se pinta en ámbar. */
    public const WARNING_BUSINESS_DAYS = 3;

    public const TYPE_RECLAMO = 'reclamo';
    public const TYPE_QUEJA   = 'queja';

    public const STATUS_REGISTERED = 'registered';
    public const STATUS_IN_REVIEW  = 'in_review';
    public const STATUS_ANSWERED   = 'answered';
    public const STATUS_CLOSED     = 'closed';

    public const STATUSES = [
        self::STATUS_REGISTERED => 'Registrado',
        self::STATUS_IN_REVIEW  => 'En revisión',
        self::STATUS_ANSWERED   => 'Respondido',
        self::STATUS_CLOSED     => 'Cerrado',
    ];

    /** Estados en los que el reloj del plazo legal ya se detuvo. */
    public const CLOSED_STATUSES = [self::STATUS_ANSWERED, self::STATUS_CLOSED];

    protected $fillable = [
        'year', 'correlative', 'code', 'public_token',
        'type', 'item_type',
        'document_type', 'document_number', 'names', 'surnames', 'email', 'phone',
        'address', 'department_id', 'province_id', 'district_id',
        'is_minor', 'guardian_name',
        'order_id', 'order_reference', 'purchase_date', 'currency', 'amount',
        'product_description', 'detail', 'consumer_request', 'files',
        'status', 'due_date', 'answered_at', 'answer', 'actions_taken',
        'request_accepted', 'rejection_grounds', 'answered_by_user_id',
        'customer_mail_status', 'customer_mail_error', 'customer_mail_sent_at',
        'tenant_mail_status', 'tenant_mail_error', 'tenant_mail_sent_at', 'tenant_mail_to',
        'answer_mail_status', 'answer_mail_error', 'answer_mail_sent_at',
        'ip', 'user_agent', 'submission_token',
    ];

    protected $casts = [
        'files'                 => 'array',
        'is_minor'              => 'boolean',
        'request_accepted'      => 'boolean',
        'purchase_date'         => 'date',
        'due_date'              => 'date',
        'answered_at'           => 'datetime',
        'customer_mail_sent_at' => 'datetime',
        'tenant_mail_sent_at'   => 'datetime',
        'answer_mail_sent_at'   => 'datetime',
        'amount'                => 'float',
    ];

    /**
     * Nunca se devuelve el token al listado del panel: es la llave con la que
     * el consumidor abre su copia sin sesión.
     */
    protected $hidden = ['public_token'];

    // ── Relaciones ────────────────────────────────────────────────────────

    public function events()
    {
        return $this->hasMany(ClaimEvent::class, 'claim_id')->orderBy('id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function answeredBy()
    {
        return $this->belongsTo(User::class, 'answered_by_user_id');
    }

    // ── Plazo legal ───────────────────────────────────────────────────────

    /**
     * Fecha límite de respuesta: $days días HÁBILES después de $from.
     *
     * Reutiliza el calendario peruano que ya mantiene Envíos
     * (`ShippingRequest::HOLIDAYS`) en vez de duplicarlo: si se agrega un
     * feriado allí, el Libro lo respeta el mismo día.
     */
    public static function dueDateFrom(Carbon $from, int $days = self::RESPONSE_BUSINESS_DAYS): Carbon
    {
        $holidays = array_flip(ShippingRequest::HOLIDAYS);
        $cursor   = $from->copy()->startOfDay();
        $counted  = 0;
        $guard    = 0;

        while ($counted < $days && $guard++ < 400) {
            $cursor->addDay();
            if ($cursor->isWeekend() || isset($holidays[$cursor->toDateString()])) {
                continue;
            }
            $counted++;
        }

        return $cursor;
    }

    /** ¿El reloj del plazo sigue corriendo? */
    public function isDeadlineRunning(): bool
    {
        return !in_array($this->status, self::CLOSED_STATUSES, true);
    }

    /**
     * Días hábiles que quedan hasta la fecha límite. Negativo = vencido.
     * Cuando el caso ya está respondido o cerrado, devuelve null.
     */
    public function businessDaysLeft(?Carbon $now = null): ?int
    {
        if (!$this->due_date || !$this->isDeadlineRunning()) {
            return null;
        }

        $today = ($now ?? now())->copy()->startOfDay();
        $due   = $this->due_date->copy()->startOfDay();

        if ($due->greaterThanOrEqualTo($today)) {
            return ShippingRequest::businessDaysBetween($today, $due);
        }

        return -ShippingRequest::businessDaysBetween($due, $today);
    }

    /**
     * Semáforo del plazo: ok | warning | overdue | done.
     * `done` es el caso ya respondido o cerrado — no vence.
     */
    public function deadlineState(?Carbon $now = null): string
    {
        $left = $this->businessDaysLeft($now);

        if ($left === null) {
            return 'done';
        }
        if ($left < 0) {
            return 'overdue';
        }

        return $left <= self::WARNING_BUSINESS_DAYS ? 'warning' : 'ok';
    }

    // ── Presentación ──────────────────────────────────────────────────────

    public function fullName(): string
    {
        return trim($this->names . ' ' . $this->surnames);
    }

    public function typeLabel(): string
    {
        return $this->type === self::TYPE_QUEJA ? 'Queja' : 'Reclamo';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Documento del consumidor enmascarado. Los listados del panel no tienen
     * por qué mostrar el DNI completo de nadie.
     */
    public function maskedDocument(): string
    {
        $n = (string) $this->document_number;

        return strlen($n) <= 4 ? $n : str_repeat('*', strlen($n) - 4) . substr($n, -4);
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeOverdue($q)
    {
        return $q->whereNotIn('status', self::CLOSED_STATUSES)
                 ->whereDate('due_date', '<', now()->toDateString());
    }

    public function scopeOpen($q)
    {
        return $q->whereNotIn('status', self::CLOSED_STATUSES);
    }
}
