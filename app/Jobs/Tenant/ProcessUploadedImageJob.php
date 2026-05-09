<?php

namespace App\Jobs\Tenant;

use App\Jobs\TenantAwareJob;
use App\Models\Tenant\ImageProcessingJob;
use App\Services\Tenant\ImageProcessingService;
use Illuminate\Support\Facades\Log;

/**
 * ProcessUploadedImageJob
 *
 * Procesa una imagen subida vía /items/upload-async. El registro de
 * ImageProcessingJob ya existe (creado por el endpoint) — el job lo
 * busca por UUID, marca processing, ejecuta el pipeline y deja
 * filename / filename_medium / filename_small en la fila.
 *
 * El frontend hace polling a /items/upload-jobs/{uuid} y reacciona al
 * status. NO requiere item_id — la imagen procesada se asigna al item
 * recién cuando el seller guarda el form.
 *
 * Diferencia con ProcessProductImageJob (legacy): aquel sí escribe en
 * items.image directamente porque ya tiene item_id. Este NO toca items.
 */
class ProcessUploadedImageJob extends TenantAwareJob
{
    public int $tries   = 3;
    public int $timeout = 180;

    public function __construct(
        public string $jobUuid,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $job = ImageProcessingJob::where('uuid', $this->jobUuid)->first();
        if (!$job) {
            Log::warning("[ProcessUploadedImageJob] Registro no encontrado: {$this->jobUuid}");
            return;
        }

        $job->update([
            'status'     => 'processing',
            'started_at' => now(),
            'attempts'   => $job->attempts + 1,
        ]);

        try {
            $result = ImageProcessingService::processAndStore(
                $job->original_path,
                $job->base_name,
            );

            $job->update([
                'status'           => 'completed',
                'filename'         => $result['main']   ?? null,
                'filename_medium'  => $result['medium'] ?? null,
                'filename_small'   => $result['small']  ?? null,
                'finished_at'      => now(),
            ]);

            Log::info("[ProcessUploadedImageJob] {$this->jobUuid} OK: " . ($result['main'] ?? '?'));

        } catch (\Throwable $e) {
            // El service ya hizo cleanup del temp en caso de éxito; en falla
            // lo borramos aquí para no acumular basura.
            if (!empty($job->original_path) && file_exists($job->original_path)) {
                @unlink($job->original_path);
            }

            $job->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at'   => now(),
            ]);

            Log::error("[ProcessUploadedImageJob] {$this->jobUuid} FAIL: " . $e->getMessage());
            $this->fail($e);
        }
    }

    public function failed(\Throwable $exception): void
    {
        // Si llegamos aquí, los $tries se agotaron. El handle() ya marcó failed
        // pero blindamos el caso donde la excepción fue antes de poder hacerlo.
        $job = ImageProcessingJob::where('uuid', $this->jobUuid)->first();
        if ($job && $job->status !== 'failed') {
            $job->update([
                'status'        => 'failed',
                'error_message' => $exception->getMessage(),
                'finished_at'   => now(),
            ]);
        }
    }
}
