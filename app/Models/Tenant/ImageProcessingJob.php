<?php

namespace App\Models\Tenant;

use App\Models\Tenant\ModelTenant;
use Illuminate\Support\Str;

/**
 * ImageProcessingJob
 *
 * Cola de tracking de imágenes en procesamiento. Ver migración
 * 2026_05_08_000002_create_image_processing_jobs_table para el contrato.
 *
 * Lifecycle:
 *  pending  → recién creado, esperando que el worker lo tome
 *  processing → el job lo agarró y está corriendo Image::make + resize
 *  completed → filenames seteados, frontend puede usarlos
 *  failed   → error_message poblado, frontend muestra mensaje al seller
 */
class ImageProcessingJob extends ModelTenant
{
    protected $table = 'image_processing_jobs';

    protected $fillable = [
        'uuid',
        'user_id',
        'original_path',
        'original_name',
        'base_name',
        'filename',
        'filename_medium',
        'filename_small',
        'status',
        'error_message',
        'attempts',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
        'attempts'    => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $job) {
            if (empty($job->uuid)) {
                $job->uuid = (string) Str::uuid();
            }
        });
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed']);
    }
}
