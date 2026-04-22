<?php

namespace App\Services\Tenant;

use App\Models\Tenant\ConfigurationEcommerce;
use App\Models\Tenant\WhatsAppMessageLog;
use App\Services\Tenant\WhatsApp\Contracts\WhatsAppDriverInterface;
use App\Services\Tenant\WhatsApp\WhatsAppDriverFactory;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp — servicio principal del sistema.
 *
 * Actúa como fachada (Facade): mantiene la API pública histórica
 * (send, notifyClientOrderReceived, etc.) pero delega toda la
 * comunicación real a un driver seleccionado por WhatsAppDriverFactory.
 *
 * Drivers disponibles: meta_cloud (oficial), qr_api, none (no-op).
 *
 * Cada envío se registra en `whatsapp_messages_log` para auditoría y
 * dashboard — previamente solo se loggeaba a laravel.log.
 *
 * Backward compatible: TODOS los métodos anteriores siguen funcionando.
 */
class WhatsAppService
{
    protected WhatsAppDriverInterface $driver;
    protected ?string $vendorPhone = null;

    public function __construct(?WhatsAppDriverInterface $driver = null)
    {
        $this->driver = $driver ?? WhatsAppDriverFactory::make();

        $ecomConfig = ConfigurationEcommerce::first();
        $this->vendorPhone = $ecomConfig->phone_whatsapp
            ?? $ecomConfig->whatsapp_vendor_number
            ?? null;
    }

    // ══════════════════════════════════════════════════════════════
    // API PÚBLICA — backward compatible
    // ══════════════════════════════════════════════════════════════

    public function isEnabled(): bool
    {
        return $this->driver->isConfigured();
    }

    public function isConfigured(): bool
    {
        return $this->isEnabled();
    }

    public function driverName(): string
    {
        return $this->driver->name();
    }

    public function send(string $phone, string $message, ?string $source = null, ?int $sourceId = null): bool
    {
        if (!$this->driver->isConfigured()) {
            $this->logFailed($phone, $message, 'driver not configured', $source, $sourceId);
            return false;
        }

        $ok = $this->driver->send($phone, $message);

        if ($ok) {
            $this->logSent($phone, $message, 'text', null, $source, $sourceId);
        } else {
            $this->logFailed($phone, $message, $this->driver->lastError() ?? 'unknown', $source, $sourceId);
        }

        return $ok;
    }

    public function sendTemplate(string $phone, string $templateName, array $params = [], string $lang = 'es', ?string $source = null, ?int $sourceId = null): bool
    {
        if (!$this->driver->isConfigured()) {
            $this->logFailed($phone, "[template:{$templateName}]", 'driver not configured', $source, $sourceId);
            return false;
        }

        $ok = $this->driver->sendTemplate($phone, $templateName, $params, $lang);

        if ($ok) {
            $this->logSent($phone, "[template:{$templateName}] " . json_encode($params), 'template', $templateName, $source, $sourceId);
        } else {
            $this->logFailed($phone, "[template:{$templateName}]", $this->driver->lastError() ?? 'unknown', $source, $sourceId, $templateName);
        }

        return $ok;
    }

    // Backward compat aliases
    public function sendText(string $to, string $message): bool { return $this->send($to, $message); }

    // ══════════════════════════════════════════════════════════════
    // NOTIFICACIONES DE PEDIDO (API histórica, backward compatible)
    // ══════════════════════════════════════════════════════════════

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
        return $this->send($phone, $msg, 'order', (int) $orderId);
    }

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
        return $this->send($this->vendorPhone, $msg, 'order_admin', (int) $orderId);
    }

    /**
     * Notifica al dueño de la tienda que llegó un pedido desde el marketplace
     * central (ebaemy.com/marketplace). Distingue del flujo de tienda propia
     * para que el tenant sepa inmediatamente que debe revisar el canal MKP01.
     * Usa $vendorPhone configurado en configuration_ecommerce.phone_whatsapp.
     */
    public function notifyAdminMarketplaceOrder(string $clientName, string $orderId, float $total, string $productTitle, int $quantity, ?string $customerPhone = null): bool
    {
        if (!$this->vendorPhone) return false;

        $msg = "🌐 *PEDIDO DESDE MARKETPLACE EBAEMY #{$orderId}*\n\n"
             . "👤 Cliente: *{$clientName}*\n"
             . "🛒 Producto: *{$productTitle}*\n"
             . "📦 Cantidad: {$quantity}\n"
             . "💰 Total: *S/ " . number_format($total, 2) . "*\n";

        if ($customerPhone) {
            $msg .= "📱 Contacto: {$customerPhone}\n";
        }

        $msg .= "\nRevisa el pedido en tu panel y coordina con el cliente.";

        return $this->send($this->vendorPhone, $msg, 'order_marketplace', (int) $orderId);
    }

    public function notifyClientOrderDispatched(string $phone, string $name, string $orderId, ?string $tracking = null): bool
    {
        $msg = "¡Hola {$name}! 📦\n\n"
             . "Tu pedido *#{$orderId}* ha sido *despachado*.\n";
        if ($tracking) {
            $msg .= "Seguimiento: *{$tracking}*\n";
        }
        $msg .= "\n¡Pronto lo recibirás! 🚚";
        return $this->send($phone, $msg, 'order', (int) $orderId);
    }

    public function notifyClientOrderDelivered(string $phone, string $name, string $orderId): bool
    {
        $msg = "¡Hola {$name}! ✅\n\n"
             . "Tu pedido *#{$orderId}* ha sido *entregado*.\n\n"
             . "¿Todo bien? Déjanos tu opinión ⭐\n"
             . "¡Gracias por confiar en nosotros!";
        return $this->send($phone, $msg, 'order', (int) $orderId);
    }

    // Aliases legacy
    public function notifyCustomerOrderReceived(...$args): bool { return $this->notifyClientOrderReceived(...$args); }
    public function notifyVendorNewOrder(string $clientName, string $orderId, float $total, array $items): bool
    {
        return $this->notifyAdminNewOrder($clientName, $orderId, $total, $items);
    }

    // ══════════════════════════════════════════════════════════════
    // LOGGING — auditoría en whatsapp_messages_log
    // ══════════════════════════════════════════════════════════════

    protected function logSent(string $phone, string $message, string $type = 'text', ?string $templateName = null, ?string $source = null, ?int $sourceId = null): void
    {
        $this->writeLog([
            'phone'         => $phone,
            'driver'        => $this->driver->name(),
            'type'          => $type,
            'template_name' => $templateName,
            'message'       => substr($message, 0, 2000),
            'status'        => 'sent',
            'source'        => $source,
            'source_id'     => $sourceId,
            'user_id'       => auth()->id(),
            'sent_at'       => now(),
        ]);
    }

    protected function logFailed(string $phone, string $message, string $error, ?string $source = null, ?int $sourceId = null, ?string $templateName = null): void
    {
        $this->writeLog([
            'phone'         => $phone,
            'driver'        => $this->driver->name(),
            'type'          => $templateName ? 'template' : 'text',
            'template_name' => $templateName,
            'message'       => substr($message, 0, 2000),
            'status'        => 'failed',
            'source'        => $source,
            'source_id'     => $sourceId,
            'error_message' => substr($error, 0, 500),
            'user_id'       => auth()->id(),
        ]);
    }

    protected function writeLog(array $data): void
    {
        try {
            WhatsAppMessageLog::create($data);
        } catch (\Throwable $e) {
            // No propagar — el log es secundario, nunca debe romper el envío.
            Log::warning('WhatsApp log write failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
