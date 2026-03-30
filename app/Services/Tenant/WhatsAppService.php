<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Configuration;
use App\Models\Tenant\ConfigurationEcommerce;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp via QR API (alasitas.devaemy.com o similar).
 *
 * Endpoint: {url}/api/message/send-text
 * Auth: Bearer {apiKey}
 * Body: { "number": "51XXXXXXXXX", "message": "texto" }
 *
 * Config: configurations.qr_api_enable, qr_api_url, qr_api_apiKey
 * Fallback: configuration_ecommerce.whatsapp_api_token, whatsapp_phone_id (Meta Cloud API)
 */
class WhatsAppService
{
    protected ?string $url = null;
    protected ?string $apiKey = null;
    protected ?string $vendorPhone = null;
    protected bool $enabled = false;
    protected string $driver = 'none'; // qr_api | meta | none

    public function __construct()
    {
        // Intentar QR API primero (configuración del tenant)
        $config = Configuration::first();
        if ($config && $config->qr_api_enable && $config->qr_api_url && $config->qr_api_apiKey) {
            $this->url = rtrim($config->qr_api_url, '/');
            $this->apiKey = $config->qr_api_apiKey;
            $this->enabled = true;
            $this->driver = 'qr_api';
        }

        // Vendor phone (para notificar al admin)
        $ecomConfig = ConfigurationEcommerce::first();
        $this->vendorPhone = $ecomConfig->phone_whatsapp ?? $config->phone_whatsapp ?? null;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    // ══════════════════════════════════════════════════════════════
    // ENVÍO DE MENSAJE
    // ══════════════════════════════════════════════════════════════

    public function send(string $phone, string $message): bool
    {
        if (!$this->enabled) {
            Log::debug('WhatsApp no configurado, mensaje no enviado', ['phone' => $phone]);
            return false;
        }

        $phone = $this->normalizePhone($phone);
        if (!$phone) return false;

        try {
            if ($this->driver === 'qr_api') {
                return $this->sendViaQrApi($phone, $message);
            }
            return false;
        } catch (\Throwable $e) {
            Log::warning('WhatsApp send failed', ['error' => $e->getMessage(), 'phone' => $phone]);
            return false;
        }
    }

    protected function sendViaQrApi(string $phone, string $message): bool
    {
        $response = Http::withToken($this->apiKey)
            ->withOptions(['verify' => false])
            ->timeout(15)
            ->post($this->url . '/api/message/send-text', [
                'number' => $phone,
                'message' => $message,
            ]);

        if ($response->successful()) {
            Log::channel('payments')->info('WhatsApp QR API enviado', [
                'phone' => $phone,
                'driver' => 'qr_api',
            ]);
            return true;
        }

        Log::warning('WhatsApp QR API error', [
            'phone' => $phone,
            'status' => $response->status(),
            'body' => substr($response->body(), 0, 200),
        ]);
        return false;
    }

    protected function normalizePhone(string $phone): ?string
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (empty($phone)) return null;
        if (strlen($phone) === 9 && str_starts_with($phone, '9')) {
            $phone = '51' . $phone;
        }
        return $phone;
    }

    // ══════════════════════════════════════════════════════════════
    // NOTIFICACIONES DE PEDIDO
    // ══════════════════════════════════════════════════════════════

    /**
     * AL CLIENTE: Pedido recibido
     */
    public function notifyClientOrderReceived(string $phone, string $name, string $orderId, float $total, string $storeName = ''): bool
    {
        if (!$storeName) {
            $storeName = \App\Models\Tenant\Company::first()->trade_name ?? 'Tienda Online';
        }
        $msg = "¡Hola {$name}! 🛒\n\n"
             . "Tu pedido *#{$orderId}* ha sido recibido en *{$storeName}*.\n"
             . "Total: *S/ " . number_format($total, 2) . "*\n\n"
             . "📦 Estado: _Pendiente de verificación_\n\n"
             . "Te notificaremos cuando sea despachado.\n"
             . "¡Gracias por tu compra! 🙌";

        return $this->send($phone, $msg);
    }

    /**
     * AL ADMIN/VENDEDOR: Nuevo pedido recibido
     */
    public function notifyAdminNewOrder(string $clientName, string $orderId, float $total, array $items = []): bool
    {
        if (!$this->vendorPhone) return false;

        $itemList = '';
        foreach (array_slice($items, 0, 5) as $item) {
            $item = (array) $item;
            $desc = $item['description'] ?? $item['item']['description'] ?? 'Producto';
            $qty = $item['quantity'] ?? $item['cantidad'] ?? 1;
            $itemList .= "  • {$desc} x{$qty}\n";
        }
        if (count($items) > 5) {
            $itemList .= "  ... y " . (count($items) - 5) . " más\n";
        }

        $msg = "📦 *NUEVO PEDIDO #{$orderId}*\n\n"
             . "👤 Cliente: *{$clientName}*\n"
             . "💰 Total: *S/ " . number_format($total, 2) . "*\n\n"
             . "Productos:\n{$itemList}\n"
             . "Gestiona en el panel de administración.";

        return $this->send($this->vendorPhone, $msg);
    }

    /**
     * AL CLIENTE: Pedido despachado
     */
    public function notifyClientOrderDispatched(string $phone, string $name, string $orderId, ?string $tracking = null): bool
    {
        $msg = "¡Hola {$name}! 📦\n\n"
             . "Tu pedido *#{$orderId}* ha sido *despachado*.\n";
        if ($tracking) {
            $msg .= "Seguimiento: *{$tracking}*\n";
        }
        $msg .= "\n¡Pronto lo recibirás! 🚚";

        return $this->send($phone, $msg);
    }

    /**
     * AL CLIENTE: Pedido entregado
     */
    public function notifyClientOrderDelivered(string $phone, string $name, string $orderId): bool
    {
        $msg = "¡Hola {$name}! ✅\n\n"
             . "Tu pedido *#{$orderId}* ha sido *entregado*.\n\n"
             . "¿Todo bien? Déjanos tu opinión ⭐\n"
             . "¡Gracias por confiar en nosotros!";

        return $this->send($phone, $msg);
    }

    // Backward compatibility
    public function sendText(string $to, string $message): bool { return $this->send($to, $message); }
    public function isConfigured(): bool { return $this->isEnabled(); }
    public function notifyCustomerOrderReceived(...$args): bool { return $this->notifyClientOrderReceived(...$args); }
    public function notifyVendorNewOrder(string $clientName, string $orderId, float $total, array $items): bool { return $this->notifyAdminNewOrder($clientName, $orderId, $total, $items); }
}
