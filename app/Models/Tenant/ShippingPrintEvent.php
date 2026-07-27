<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

/**
 * Impresión de rótulos: una fila por cada vez que se manda a imprimir.
 *
 * `sequence` 1 es la impresión original del lote; de la 2 en adelante son
 * reimpresiones y exigen motivo. El historial NUNCA se sobrescribe: cada
 * impresión agrega una fila, así se puede auditar cuántas veces y por qué se
 * reimprimió un lote o un rótulo suelto.
 */
class ShippingPrintEvent extends Model
{
    protected $connection = 'tenant';

    protected $table = 'shipping_print_events';

    protected $fillable = [
        'batch_id',
        'shipment_id',
        'sequence',
        'is_reprint',
        'reason',
        'label_count',
        'format',
        'user_id',
        'user_name',
        'ip',
    ];

    protected $casts = [
        'batch_id'    => 'integer',
        'shipment_id' => 'integer',
        'sequence'    => 'integer',
        'label_count' => 'integer',
        'user_id'     => 'integer',
        'is_reprint'  => 'boolean',
    ];

    public function batch()
    {
        return $this->belongsTo(ShippingPrintBatch::class, 'batch_id');
    }

    public function shipment()
    {
        return $this->belongsTo(ShippingRequest::class, 'shipment_id');
    }

    public function scopeReprints($query)
    {
        return $query->where('is_reprint', true);
    }

    /**
     * Registra una impresión. Calcula la secuencia sola: si ya hubo una
     * impresión previa del mismo lote (o del mismo envío suelto), la nueva
     * queda marcada como reimpresión.
     */
    public static function record(
        ?int $batchId,
        ?int $shipmentId,
        int $labelCount,
        ?string $format = null,
        ?string $reason = null
    ): self {
        $query = static::query();

        if ($batchId) {
            $query->where('batch_id', $batchId);
        } elseif ($shipmentId) {
            $query->whereNull('batch_id')->where('shipment_id', $shipmentId);
        }

        $previous = $batchId || $shipmentId ? $query->max('sequence') : 0;
        $sequence = ((int) $previous) + 1;

        $user = auth()->user();

        return static::create([
            'batch_id'    => $batchId,
            'shipment_id' => $shipmentId,
            'sequence'    => $sequence,
            'is_reprint'  => $sequence > 1,
            'reason'      => $reason,
            'label_count' => $labelCount,
            'format'      => $format,
            'user_id'     => $user?->id,
            'user_name'   => $user?->name,
            'ip'          => request()?->ip(),
        ]);
    }
}
