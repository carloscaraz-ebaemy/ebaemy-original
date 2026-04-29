<?php

namespace App\Services\System;

use App\Models\System\Configuration as SystemConfiguration;
use App\Models\System\SystemWhatsAppLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service para envío de WhatsApp desde el SuperAdmin (ebaemy.com) hacia
 * los tenants y/o números arbitrarios. Usa la configuración SYSTEM:
 *   - system.configurations.qr_api_url
 *   - system.configurations.qr_api_token
 *
 * No depende del WhatsAppService de tenant (que resuelve driver según
 * hostname activo, lo cual NO aplica en contexto system).
 *
 * Cada envío queda auditado en system_whatsapp_logs.
 */
class WhatsAppSystemService
{
    public function send(
        string $phone,
        string $message,
        ?int $tenantHostnameId = null,
        ?string $recipientName = null,
        string $source = 'manual'
    ): bool {
        $phone = $this->normalizePhone($phone);

        $log = SystemWhatsAppLog::create([
            'tenant_hostname_id' => $tenantHostnameId,
            'recipient_phone'    => $phone,
            'recipient_name'     => $recipientName,
            'message'            => mb_substr($message, 0, 4000),
            'status'             => 'pending',
            'source'             => $source,
            'admin_user_id'      => Auth::guard('admin')->id(),
        ]);

        $config = SystemConfiguration::first();

        if (!$config || empty($config->qr_api_url) || empty($config->qr_api_token)) {
            $log->update([
                'status'        => 'failed',
                'error_message' => 'WhatsApp no configurado en system.configurations (qr_api_url/token)',
            ]);
            return false;
        }

        $url = rtrim($config->qr_api_url, '/');

        try {
            $response = Http::withToken($config->qr_api_token)
                ->withOptions(['verify' => false])
                ->timeout(15)
                ->post($url . '/api/message/send-text', [
                    'number'  => $phone,
                    'message' => $message,
                ]);

            if ($response->successful()) {
                $log->update([
                    'status'  => 'sent',
                    'sent_at' => now(),
                ]);
                Log::channel('payments')->info('System WhatsApp sent', [
                    'phone'  => $phone,
                    'tenant' => $tenantHostnameId,
                    'source' => $source,
                ]);
                return true;
            }

            $log->update([
                'status'        => 'failed',
                'error_message' => "Gateway respondió {$response->status()}: " . mb_substr($response->body(), 0, 400),
            ]);
            return false;
        } catch (\Throwable $e) {
            $log->update([
                'status'        => 'failed',
                'error_message' => mb_substr('Exception: ' . $e->getMessage(), 0, 500),
            ]);
            Log::warning('System WhatsApp send failed', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function isConfigured(): bool
    {
        $config = SystemConfiguration::first();
        return $config && !empty($config->qr_api_url) && !empty($config->qr_api_token);
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (strlen($digits) === 9 && $digits[0] === '9') {
            return '51' . $digits;
        }
        return $digits;
    }
}
