<?php

namespace App\Console\Commands;

use App\Models\Tenant\WhatsAppOfferCampaign;
use App\Services\Tenant\WhatsAppOfferCampaignService;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Cada mañana programa el siguiente lote de las campañas de WhatsApp por
 * productos que quedaron con clientes pendientes (el tope diario no dio
 * para todos). Ver WhatsAppOfferCampaignService::programarLote.
 */
class ContinueWhatsAppCampaigns extends Command
{
    protected $signature = 'whatsapp:campaigns-continue {--tenant= : UUID del tenant (opcional)}';

    protected $description = 'Programa el lote de hoy de las campañas de WhatsApp por productos que siguen pendientes';

    public function handle(Environment $tenancy): int
    {
        $query = Website::with('hostnames');
        if ($uuid = $this->option('tenant')) {
            $query->where('uuid', $uuid);
        }

        $total = 0;
        foreach ($query->get() as $website) {
            try {
                $tenancy->tenant($website);
                if (!Schema::connection('tenant')->hasTable('whatsapp_offer_campaigns')
                    || !Schema::connection('tenant')->hasColumn('whatsapp_offer_campaigns', 'kind')) {
                    continue;
                }

                $service = new WhatsAppOfferCampaignService();
                $campanas = WhatsAppOfferCampaign::where('kind', 'products')
                    ->whereIn('status', ['queued', 'processing'])
                    ->orderBy('id')
                    ->get();

                foreach ($campanas as $campana) {
                    $n = $service->programarLote($campana);
                    $service->cerrarSiTermino($campana->fresh());
                    if ($n > 0) {
                        $fqdn = optional($website->hostnames->first())->fqdn;
                        $this->line("{$fqdn}: campaña #{$campana->id} → {$n} mensajes programados");
                        $total += $n;
                    }
                }
            } catch (\Throwable $e) {
                $this->warn("Tenant {$website->uuid}: " . $e->getMessage());
            }
        }

        $this->info("Total programados hoy: {$total}");

        return self::SUCCESS;
    }
}
