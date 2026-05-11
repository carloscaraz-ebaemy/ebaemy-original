<?php

namespace App\Jobs\System;

use App\Models\System\SellerApplication;
use App\Models\System\SystemAdminNotification;
use App\Services\System\SellerApplicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Procesa la aprobación de una SellerApplication en background. El controller
 * marca status='approving' y dispatcha este job vía dispatchAfterResponse —
 * la HTTP response se cierra antes para evitar timeout de nginx (30s) durante
 * el migrate masivo (345+ tablas, ~60s en server de 4 GiB).
 *
 * Si falla, marca status='failed' + crea notificación con el error para que
 * el SuperAdmin reintente desde la lista.
 */
class ProcessSellerApprovalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;        // No retry — el SuperAdmin puede reintentar manualmente
    public int $timeout = 600;      // 10 min para migrate masivo

    public function __construct(
        public int $applicationId,
        public ?int $reviewerId,
        public int $planId,
        public array $options = []
    ) {}

    public function handle(SellerApplicationService $service): void
    {
        // Subir el límite PHP por si el job corre en sync (mismo proceso PHP-FPM)
        @set_time_limit(600);
        @ini_set('memory_limit', '512M');

        $application = SellerApplication::find($this->applicationId);
        if (!$application) {
            Log::warning("[ProcessSellerApprovalJob] application {$this->applicationId} no existe");
            return;
        }

        try {
            $result = $service->approve($application, $this->reviewerId, $this->planId, $this->options);

            if ($result['success'] ?? false) {
                // El service ya marcó approved internamente; solo creamos notif.
                SystemAdminNotification::notify(
                    'seller_approved',
                    '✅ Tenant creado: ' . ($application->business_name ?: $application->requested_subdomain),
                    'Subdomain: ' . $application->requested_subdomain . '.ebaemy.com'
                        . ' · ' . ($application->contact_email ?: '—'),
                    '/admin/seller-applications',
                    '✅',
                    'seller_application',
                    $application->id
                );
                Log::info("[ProcessSellerApprovalJob] {$application->id} OK");
            } else {
                // Restaurar status previo + notificar al admin con el motivo
                $application->refresh();
                $application->update(['status' => 'under_review']);
                SystemAdminNotification::notify(
                    'seller_approval_failed',
                    '❌ Falló al crear tenant: ' . ($application->business_name ?: $application->requested_subdomain),
                    $result['message'] ?? 'error desconocido',
                    '/admin/seller-applications',
                    '⚠️',
                    'seller_application',
                    $application->id
                );
                Log::warning("[ProcessSellerApprovalJob] {$application->id} FAIL: " . ($result['message'] ?? '—'));
            }
        } catch (\Throwable $e) {
            // Restaurar status + notificar
            try {
                $application->refresh();
                $application->update(['status' => 'under_review']);
            } catch (\Throwable $_) {}
            SystemAdminNotification::notify(
                'seller_approval_failed',
                '❌ Error al crear tenant: ' . ($application->business_name ?: $application->requested_subdomain),
                substr($e->getMessage(), 0, 300),
                '/admin/seller-applications',
                '⚠️',
                'seller_application',
                $application->id
            );
            Log::error("[ProcessSellerApprovalJob] {$application->id} EXCEPTION: " . $e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("[ProcessSellerApprovalJob] FAILED permanente app={$this->applicationId}: " . $exception->getMessage());
    }
}
