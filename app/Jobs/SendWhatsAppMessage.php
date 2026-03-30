<?php

namespace App\Jobs;

use App\Services\Tenant\WhatsAppService;
use Illuminate\Support\Facades\Log;

/**
 * Extiende TenantAwareJob para que el queue worker restaure el contexto
 * de tenant antes de ejecutar handle() — sin esto, WhatsAppService no
 * podría leer Configuration de la BD correcta del tenant.
 */
class SendWhatsAppMessage extends TenantAwareJob
{
    public int $tries = 3;
    public int $backoff = 30; // seconds between retries

    private string $type;      // 'text' | 'customer_order' | 'vendor_order'
    private array  $payload;

    // ── Factory methods ──────────────────────────────────────────────────────

    public static function text(string $to, string $message): self
    {
        return new self('text', compact('to', 'message'));
    }

    public static function customerOrder(string $phone, string $customerName, string $orderNumber, float $total, string $storeName): self
    {
        return new self('customer_order', compact('phone', 'customerName', 'orderNumber', 'total', 'storeName'));
    }

    public static function vendorOrder(string $customerName, string $orderNumber, float $total, array $items): self
    {
        return new self('vendor_order', compact('customerName', 'orderNumber', 'total', 'items'));
    }

    // ─────────────────────────────────────────────────────────────────────────

    public function __construct(string $type, array $payload)
    {
        parent::__construct(); // captura el UUID del tenant activo para el worker
        $this->type    = $type;
        $this->payload = $payload;
    }

    public function handle(WhatsAppService $wa): void
    {
        if (!$wa->isConfigured()) {
            Log::info('SendWhatsAppMessage: WhatsApp not configured, job skipped.');
            return;
        }

        match ($this->type) {
            'text' => $wa->sendText(
                $this->payload['to'],
                $this->payload['message']
            ),
            'customer_order' => $wa->notifyCustomerOrderReceived(
                $this->payload['phone'],
                $this->payload['customerName'],
                $this->payload['orderNumber'],
                $this->payload['total'],
                $this->payload['storeName']
            ),
            'vendor_order' => $wa->notifyVendorNewOrder(
                $this->payload['customerName'],
                $this->payload['orderNumber'],
                $this->payload['total'],
                $this->payload['items']
            ),
            default => Log::warning("SendWhatsAppMessage: unknown type [{$this->type}]"),
        };
    }

    public function failed(\Throwable $e): void
    {
        Log::error("SendWhatsAppMessage job failed [{$this->type}]: " . $e->getMessage());
    }
}
