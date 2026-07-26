<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Cliente invitado a un sorteo.
 *
 * Se crea al "generar participantes" desde los pedidos elegibles (estado
 * `invited`) y pasa a `accepted` SOLO cuando el cliente entra a su enlace
 * único y pulsa "Acepto participar" — únicamente los aceptados entran al
 * sorteo.
 *
 * Anti-duplicados: `dedupe_key` (documento → email → teléfono → person_id)
 * tiene índice único junto a raffle_id, así el mismo cliente no puede
 * aparecer dos veces aunque tenga varios pedidos.
 *
 * @property int $id
 * @property int $raffle_id
 * @property string $token
 * @property string $status
 */
class RaffleParticipant extends Model
{
    protected $connection = 'tenant';

    protected $table = 'raffle_participants';

    protected $fillable = [
        'raffle_id',
        'person_id',
        'full_name',
        'document',
        'email',
        'phone',
        'dedupe_key',
        'token',
        'status',
        'orders_count',
        'total_amount',
        'last_purchase_at',
        'invited_at',
        'invited_via',
        'accepted_at',
        'accept_ip',
        'accept_user_agent',
        'is_winner',
    ];

    protected $casts = [
        'raffle_id'        => 'integer',
        'person_id'        => 'integer',
        'orders_count'     => 'integer',
        'total_amount'     => 'decimal:2',
        'is_winner'        => 'boolean',
        'last_purchase_at' => 'date',
        'invited_at'       => 'datetime',
        'accepted_at'      => 'datetime',
    ];

    public const STATUS_INVITED  = 'invited';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';

    public const STATUSES = [
        self::STATUS_INVITED  => 'Invitado',
        self::STATUS_ACCEPTED => 'Aceptó participar',
        self::STATUS_DECLINED => 'Rechazó',
    ];

    public const STATUS_COLORS = [
        self::STATUS_INVITED  => 'warning',
        self::STATUS_ACCEPTED => 'success',
        self::STATUS_DECLINED => 'secondary',
    ];

    // ── Relaciones ─────────────────────────────────────────────────────────

    public function raffle()
    {
        return $this->belongsTo(Raffle::class, 'raffle_id');
    }

    public function person()
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function winner()
    {
        return $this->hasOne(RaffleWinner::class, 'participant_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeAccepted($query)
    {
        return $query->where('status', self::STATUS_ACCEPTED);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /** Token aleatorio no adivinable para el enlace público. */
    public static function makeToken(): string
    {
        do {
            $token = strtoupper(Str::random(10));
        } while (static::where('token', $token)->exists());

        return $token;
    }

    /**
     * Clave de deduplicación del cliente dentro de un sorteo.
     * Prioriza el documento; si no hay, cae a email, teléfono o person_id.
     */
    public static function dedupeKeyFor(?string $document, ?string $email, ?string $phone, ?int $personId): string
    {
        $document = preg_replace('/\D+/', '', (string) $document);
        if ($document !== '') {
            return 'doc:' . $document;
        }

        $email = strtolower(trim((string) $email));
        if ($email !== '') {
            return 'mail:' . $email;
        }

        $phone = preg_replace('/\D+/', '', (string) $phone);
        if ($phone !== '') {
            return 'tel:' . $phone;
        }

        return 'person:' . ($personId ?: uniqid('x', true));
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** URL pública del enlace de invitación. */
    public function invitationUrl(): string
    {
        return url('/sorteo/' . $this->token);
    }

    /** Teléfono normalizado a formato wa.me (Perú por defecto). */
    public function whatsappPhone(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->phone);
        if (strlen($digits) < 9) {
            return null;
        }
        if (strlen($digits) === 9) {
            $digits = '51' . $digits;
        }

        return $digits;
    }
}
