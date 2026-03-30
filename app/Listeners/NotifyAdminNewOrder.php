<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotifyAdminNewOrder
{
    public function handle($event)
    {
        $order = $event->order ?? null;
        if (!$order) return;

        Log::channel('payments')->info('New ecommerce order received', [
            'order_id' => $order->id,
            'total' => $order->total,
            'customer' => $order->customer['apellidos_y_nombres_o_razon_social'] ?? 'Guest',
        ]);

        // Notify all admin users via email
        try {
            $admins = \App\Models\Tenant\User::where('type', 'admin')->get();
            foreach ($admins as $admin) {
                if ($admin->email) {
                    Mail::raw(
                        "Nuevo pedido ecommerce #{$order->id}\n" .
                        "Cliente: " . ($order->customer['apellidos_y_nombres_o_razon_social'] ?? 'Invitado') . "\n" .
                        "Total: S/ " . number_format($order->total, 2) . "\n" .
                        "Revisar en: /orders",
                        function ($message) use ($admin, $order) {
                            $message->to($admin->email)
                                    ->subject("Nuevo pedido ecommerce #" . str_pad($order->id, 6, '0', STR_PAD_LEFT));
                        }
                    );
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to notify admin of new order', ['error' => $e->getMessage()]);
        }
    }
}
