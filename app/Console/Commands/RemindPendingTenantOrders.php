<?php

namespace App\Console\Commands;

use App\Mail\MarketplaceTenantOrderMail;
use App\Models\System\Client;
use App\Models\System\MarketplaceOrder;
use App\Models\System\MarketplaceOrderItem;
use App\Models\System\TenantMarketplaceOrder;
use Hyn\Tenancy\Environment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Recordatorio al TENANT cuando un subpedido del marketplace sigue
 * 'pending' (no atendido) pasadas 2h.
 *
 * Reenvía mismo email (con banner de RECORDATORIO) + mismo WhatsApp.
 * Se acumula hasta 3 envíos, espaciados al menos 12h entre cada uno
 * para no spammear al seller.
 *
 * Criterios:
 *   - status = 'pending'
 *   - created_at > NOW - 7 dias (no reanima zombies)
 *   - created_at < NOW - 2 horas (espera inicial antes del 1er recordatorio)
 *   - reminder_count < 3
 *   - reminder_sent_at NULL  o  > 12h atrás
 *
 * Scheduling: registrar en Kernel para correr cada hora.
 *
 * Uso manual:
 *   php artisan marketplace:remind-pending-tenant-orders --dry-run
 *   php artisan marketplace:remind-pending-tenant-orders --limit=20
 */
class RemindPendingTenantOrders extends Command
{
    protected $signature = 'marketplace:remind-pending-tenant-orders
                            {--dry-run : Solo lista candidatos, no envia}
                            {--limit=50 : Maximo de envios por corrida}';

    protected $description = 'Email + WhatsApp recordatorio a tenants con subpedidos marketplace pendientes';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit  = max(1, (int) $this->option('limit'));

        $now      = now();
        $minAge   = $now->copy()->subHours(2);
        $maxAge   = $now->copy()->subDays(7);
        $cooldown = $now->copy()->subHours(12);

        $candidates = TenantMarketplaceOrder::query()
            ->where('status', TenantMarketplaceOrder::STATUS_PENDING)
            ->where('created_at', '<=', $minAge)
            ->where('created_at', '>=', $maxAge)
            ->where(function ($q) {
                $q->whereNull('reminder_count')->orWhere('reminder_count', '<', 3);
            })
            ->where(function ($q) use ($cooldown) {
                $q->whereNull('reminder_sent_at')->orWhere('reminder_sent_at', '<=', $cooldown);
            })
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No hay subpedidos pendientes que califiquen para recordatorio.');
            return 0;
        }

        $this->info(($dryRun ? '[DRY-RUN] ' : '') . "Candidatos: {$candidates->count()}");

        $sent = 0; $failed = 0;
        foreach ($candidates as $sub) {
            $reminderNum = (int) ($sub->reminder_count ?? 0) + 1;
            $this->line(sprintf(
                '  • sub #%d → tenant %s — pedido %s — pendiente desde %s — recordatorio %d/3',
                $sub->id,
                $sub->tenant_fqdn,
                optional($sub->marketplaceOrder)->order_number ?? '?',
                optional($sub->created_at)->diffForHumans() ?? '?',
                $reminderNum
            ));

            if ($dryRun) continue;

            try {
                $this->sendReminderFor($sub, $reminderNum);

                $sub->update([
                    'reminder_sent_at' => now(),
                    'reminder_count'   => $reminderNum,
                ]);

                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('marketplace tenant reminder failed', [
                    'sub_id' => $sub->id,
                    'tenant' => $sub->tenant_fqdn,
                    'error'  => $e->getMessage(),
                ]);
                $this->warn("    ✗ falló: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Enviados: {$sent} · Fallidos: {$failed}");

        return $failed > 0 ? 1 : 0;
    }

    /**
     * Envía email + WhatsApp al tenant para un subpedido pendiente.
     */
    private function sendReminderFor(TenantMarketplaceOrder $sub, int $reminderNum): void
    {
        $order = $sub->marketplaceOrder;
        if (!$order) {
            throw new \RuntimeException('marketplaceOrder no resoluble');
        }

        $client = Client::with('hostname.website')
            ->where('hostname_id', $sub->hostname_id)
            ->first();
        if (!$client || !$client->hostname) {
            throw new \RuntimeException('client/hostname no resoluble');
        }

        // Items de este subpedido (filtrados por hostname).
        $items = MarketplaceOrderItem::query()
            ->where('marketplace_order_id', $order->id)
            ->where('hostname_id', $sub->hostname_id)
            ->get();

        if ($items->isEmpty()) {
            throw new \RuntimeException('items vacíos para este subpedido');
        }

        // Switch al tenant para que el fallback resolveTenantAdminContact
        // pueda leer admin user (mismo patrón que el dispatcher original).
        $tenancy = app(Environment::class);
        $prev = $tenancy->tenant();
        $tenancy->tenant($client->hostname->website);

        try {
            $contact = $this->resolveContact($client);

            if (!empty($contact['email'])) {
                try { \App\Models\System\Configuration::setConfigSmtpMail(); } catch (\Throwable $_) {}
                Mail::to($contact['email'])->send(
                    new MarketplaceTenantOrderMail(
                        $order, $sub, $items, (float) $sub->subtotal,
                        $client->hostname->fqdn, true, $reminderNum
                    )
                );
                Log::info('marketplace tenant reminder email sent', [
                    'sub_id'         => $sub->id,
                    'tenant'         => $client->hostname->fqdn,
                    'to'             => $contact['email'],
                    'reminder_num'   => $reminderNum,
                ]);
            }

            if (!empty($contact['phone'])) {
                $msg  = "⏰ *RECORDATORIO* — Pedido marketplace {$order->order_number}\n\n";
                $msg .= "Llegó hace " . optional($sub->created_at)->diffForHumans() . " y aún no lo atendiste.\n";
                $msg .= "Este es el recordatorio #{$reminderNum}/3.\n\n";
                $msg .= "🏪 Tienda: {$client->hostname->fqdn}\n";
                $msg .= "🛒 {$items->count()} producto" . ($items->count() === 1 ? '' : 's') . "\n";
                $msg .= "💰 Total: *S/ " . number_format((float) $sub->subtotal, 2) . "*\n\n";
                $msg .= "👤 Cliente: *{$order->customer_name}*\n";
                $msg .= "📱 {$order->customer_phone}\n\n";
                $msg .= "Atiendelo: https://{$client->hostname->fqdn}/orders";

                dispatch(\App\Jobs\SendWhatsAppMessage::text($contact['phone'], $msg));

                Log::info('marketplace tenant reminder WA dispatched', [
                    'sub_id'       => $sub->id,
                    'tenant'       => $client->hostname->fqdn,
                    'to'           => $contact['phone'],
                    'reminder_num' => $reminderNum,
                ]);
            }
        } finally {
            $tenancy->tenant($prev);
        }
    }

    /**
     * Misma estrategia que MarketplaceMultiOrderDispatcher: usa clients.contact_email
     * + phone_ws si los tiene; sino fallback a tenant.users (type='admin').
     */
    private function resolveContact(Client $client): array
    {
        $email = !empty($client->contact_email) ? $client->contact_email : $client->email;
        $phone = preg_replace('/\D+/', '', (string) ($client->phone_ws ?? ''));

        $isGenericEmail = !empty($email) && stripos($email, 'admin@ebaemy.com') !== false;
        if (empty($email) || empty($phone) || $isGenericEmail) {
            try {
                // Datos reales del seller en tenant.configurations
                // (los pone el tenant en /ecommerce/configuration).
                $cfg = DB::connection('tenant')->table('configurations')
                    ->where('id', 1)
                    ->first(['information_contact_email', 'phone_whatsapp', 'information_contact_phone']);
                if ($cfg) {
                    if (empty($email) || $isGenericEmail) {
                        $email = $cfg->information_contact_email ?: $email;
                    }
                    if (empty($phone)) {
                        $raw = $cfg->phone_whatsapp ?: ($cfg->information_contact_phone ?: '');
                        $phone = preg_replace('/\D+/', '', (string) $raw);
                    }
                }
            } catch (\Throwable $_) {}
        }

        if (mb_strlen($phone) < 9) $phone = null;

        return ['email' => $email ?: null, 'phone' => $phone];
    }
}
