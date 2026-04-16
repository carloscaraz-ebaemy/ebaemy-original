<?php

namespace App\Console\Commands;

use App\Services\Tenant\WhatsAppOfferCampaignService;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendWhatsAppDiscountCampaign extends Command
{
    protected $signature = 'ecommerce:send-whatsapp-discounts
        {flash_sale_id : ID de flash sale}
        {--tenant= : UUID del tenant (opcional)}
        {--limit=200 : Maximo de clientes por tenant}
        {--max-products=3 : Productos a incluir por mensaje}
        {--cooldown=48 : Horas minimas sin reenvio al mismo cliente}
        {--force : Enviar aunque la oferta no este activa}';

    protected $description = 'Envia descuentos de flash sale por WhatsApp a clientes registrados';

    public function handle(Environment $tenancy): int
    {
        $flashSaleId = (int)$this->argument('flash_sale_id');
        $tenantUuid = $this->option('tenant');
        $limit = (int)$this->option('limit');
        $maxProducts = (int)$this->option('max-products');
        $cooldown = (int)$this->option('cooldown');
        $force = (bool)$this->option('force');

        $query = Website::with('hostnames');
        if (!empty($tenantUuid)) {
            $query->where('uuid', $tenantUuid);
        }

        $websites = $query->get();
        if ($websites->isEmpty()) {
            $this->error('No se encontraron tenants para ejecutar.');
            return self::FAILURE;
        }

        $globalSent = 0;
        $globalFailed = 0;

        foreach ($websites as $website) {
            try {
                $tenancy->tenant($website);

                $fqdn = optional($website->hostnames->first())->fqdn;
                $scheme = config('tenant.force_https') ? 'https' : 'http';
                $baseEcommerceUrl = $fqdn ? "{$scheme}://{$fqdn}/ecommerce" : url('/ecommerce');

                $result = (new WhatsAppOfferCampaignService())->runForFlashSale($flashSaleId, [
                    'campaign_name' => "Flash Sale {$flashSaleId}",
                    'limit_customers' => $limit,
                    'max_products' => $maxProducts,
                    'cooldown_hours' => $cooldown,
                    'force' => $force,
                    'base_ecommerce_url' => $baseEcommerceUrl,
                ]);

                if (!($result['success'] ?? false)) {
                    $this->warn("Tenant {$website->uuid}: {$result['message']}");
                    continue;
                }

                $globalSent += (int)($result['sent'] ?? 0);
                $globalFailed += (int)($result['failed'] ?? 0);

                $this->info(sprintf(
                    'Tenant %s: enviados=%d, fallidos=%d, clientes=%d',
                    $website->uuid,
                    (int)($result['sent'] ?? 0),
                    (int)($result['failed'] ?? 0),
                    (int)($result['customers'] ?? 0)
                ));
            } catch (\Throwable $e) {
                Log::error('[SendWhatsAppDiscountCampaign] Error en tenant', [
                    'tenant' => $website->uuid,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Tenant {$website->uuid}: {$e->getMessage()}");
            } finally {
                $tenancy->tenant(null);
            }
        }

        $this->line("Total enviados: {$globalSent}");
        $this->line("Total fallidos: {$globalFailed}");

        return self::SUCCESS;
    }
}

