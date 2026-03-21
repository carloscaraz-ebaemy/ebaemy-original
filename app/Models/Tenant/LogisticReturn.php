<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogisticReturn extends ModelTenant
{
    protected $table = 'logistic_returns';

    protected $fillable = [
        'sale_note_id',
        'warehouse_id',
        'user_id',
        'status',
        'reason',
        'courier_name',
        'tracking_number',
        'notes',
        'received_at',
        'processed_at',
    ];

    protected $casts = [
        'received_at'  => 'datetime',
        'processed_at' => 'datetime',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function saleNote(): BelongsTo
    {
        return $this->belongsTo(SaleNote::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(LogisticReturnItem::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isPendiente(): bool  { return $this->status === 'PENDIENTE'; }
    public function isRecibido(): bool   { return $this->status === 'RECIBIDO'; }
    public function isProcesado(): bool  { return $this->status === 'PROCESADO'; }

    public function statusLabel(): string
    {
        return match($this->status) {
            'PENDIENTE' => 'Pendiente',
            'RECIBIDO'  => 'Recibido',
            'PROCESADO' => 'Procesado',
            default     => $this->status,
        };
    }

    public function statusColor(): string
    {
        return match($this->status) {
            'PENDIENTE' => 'warning',
            'RECIBIDO'  => 'info',
            'PROCESADO' => 'success',
            default     => 'secondary',
        };
    }

    public function reasonLabel(): string
    {
        return match($this->reason) {
            'DEFECTO'           => 'Producto defectuoso',
            'EQUIVOCADO'        => 'Producto equivocado',
            'ARREPENTIMIENTO'   => 'Arrepentimiento del cliente',
            'DANADO_TRANSPORTE' => 'Dañado en transporte',
            'OTRO'              => 'Otro motivo',
            default             => $this->reason ?? '—',
        };
    }

    public static function reasons(): array
    {
        return [
            'DEFECTO'           => 'Producto defectuoso',
            'EQUIVOCADO'        => 'Producto equivocado',
            'ARREPENTIMIENTO'   => 'Arrepentimiento del cliente',
            'DANADO_TRANSPORTE' => 'Dañado en transporte',
            'OTRO'              => 'Otro motivo',
        ];
    }
}
