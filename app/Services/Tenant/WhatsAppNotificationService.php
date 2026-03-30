<?php
namespace App\Services\Tenant;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Tenant\ConfigurationEcommerce;

class WhatsAppNotificationService
{
    protected ?string $token;
    protected ?string $phoneNumberId;

    public function __construct()
    {
        $config = ConfigurationEcommerce::first();
        $this->token = $config->whatsapp_api_token ?? null;
        $this->phoneNumberId = $config->whatsapp_phone_number_id ?? null;
    }

    public function isConfigured(): bool
    {
        return !empty($this->token) && !empty($this->phoneNumberId);
    }

    public function sendOrderConfirmation(string $phone, string $customerName, string $orderId, float $total): bool
    {
        return $this->sendMessage($phone,
            "Hola {$customerName}!\n\n" .
            "Tu pedido *#{$orderId}* ha sido recibido.\n" .
            "Total: *S/ " . number_format($total, 2) . "*\n\n" .
            "Te notificaremos cuando este listo para envio. Gracias por tu compra!"
        );
    }

    public function sendOrderDispatched(string $phone, string $customerName, string $orderId, ?string $trackingNumber = null): bool
    {
        $msg = "Hola {$customerName}!\n\n" .
            "Tu pedido *#{$orderId}* ha sido despachado.\n";
        if ($trackingNumber) {
            $msg .= "Numero de seguimiento: *{$trackingNumber}*\n";
        }
        $msg .= "\nPronto lo recibiras!";
        return $this->sendMessage($phone, $msg);
    }

    public function sendOrderDelivered(string $phone, string $customerName, string $orderId): bool
    {
        return $this->sendMessage($phone,
            "Hola {$customerName}!\n\n" .
            "Tu pedido *#{$orderId}* ha sido entregado.\n\n" .
            "Todo bien con tu compra? Dejanos tu resena.\n" .
            "Gracias por confiar en nosotros!"
        );
    }

    public function sendAbandonedCartReminder(string $phone, string $customerName, string $cartUrl): bool
    {
        return $this->sendMessage($phone,
            "Hola {$customerName}!\n\n" .
            "Dejaste productos en tu carrito. Necesitas ayuda?\n\n" .
            "Completa tu compra aqui: {$cartUrl}\n\n" .
            "Los articulos tienen stock limitado!"
        );
    }

    protected function sendMessage(string $phone, string $text): bool
    {
        if (!$this->isConfigured()) {
            Log::debug('WhatsApp not configured, skipping notification', ['phone' => $phone]);
            return false;
        }

        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) === 9) $phone = '51' . $phone; // Peru prefix

        try {
            $response = Http::withToken($this->token)
                ->post("https://graph.facebook.com/v18.0/{$this->phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'text',
                    'text' => ['body' => $text],
                ]);

            if ($response->successful()) {
                Log::channel('payments')->info('WhatsApp sent', ['phone' => $phone]);
                return true;
            }

            Log::warning('WhatsApp API error', ['status' => $response->status(), 'body' => $response->body()]);
            return false;
        } catch (\Throwable $e) {
            Log::warning('WhatsApp send failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
