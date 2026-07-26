<?php

namespace App\Models\Tenant;

use App\Services\Tenant\ImageProcessingService;
use Illuminate\Database\Eloquent\Model;

/**
 * Ganador de un sorteo.
 *
 * Congela el premio (nombre + imagen) al momento del sorteo para que la ficha
 * del ganador siga siendo válida aunque después se edite la campaña, y guarda
 * la auditoría del acto: quién lo ejecutó, cuándo y sobre qué universo
 * (`draw_snapshot` = pool de participantes + total).
 *
 * @property int $id
 * @property int $raffle_id
 * @property int $participant_id
 */
class RaffleWinner extends Model
{
    protected $connection = 'tenant';

    protected $table = 'raffle_winners';

    protected $fillable = [
        'raffle_id',
        'participant_id',
        'position',
        'prize_name',
        'prize_image',
        'drawn_at',
        'drawn_by',
        'drawn_by_name',
        'draw_snapshot',
        'delivery_status',
        'delivered_at',
        'delivery_note',
    ];

    protected $casts = [
        'raffle_id'      => 'integer',
        'participant_id' => 'integer',
        'position'       => 'integer',
        'drawn_by'       => 'integer',
        'draw_snapshot'  => 'array',
        'drawn_at'       => 'datetime',
        'delivered_at'   => 'datetime',
    ];

    public const DELIVERY_PENDING   = 'pending';
    public const DELIVERY_DELIVERED = 'delivered';

    public const DELIVERY_LABELS = [
        self::DELIVERY_PENDING   => 'Pendiente de entrega',
        self::DELIVERY_DELIVERED => 'Entregado',
    ];

    // ── Relaciones ─────────────────────────────────────────────────────────

    public function raffle()
    {
        return $this->belongsTo(Raffle::class, 'raffle_id');
    }

    public function participant()
    {
        return $this->belongsTo(RaffleParticipant::class, 'participant_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function getDeliveryLabelAttribute(): string
    {
        return self::DELIVERY_LABELS[$this->delivery_status] ?? $this->delivery_status;
    }

    public function prizeImageUrl(?string $variant = 'medium'): ?string
    {
        if (!$this->prize_image) {
            return null;
        }
        return ImageProcessingService::getUrl($this->prize_image, $variant);
    }
}
