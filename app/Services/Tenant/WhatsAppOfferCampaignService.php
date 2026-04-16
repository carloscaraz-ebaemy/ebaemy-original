<?php

namespace App\Services\Tenant;

use App\Models\Tenant\FlashSale;
use App\Models\Tenant\Person;
use App\Models\Tenant\WhatsAppOfferCampaign;
use App\Models\Tenant\WhatsAppOfferCampaignMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class WhatsAppOfferCampaignService
{
    public function runForFlashSale(int $flashSaleId, array $options = []): array
    {
        $limitCustomers = max(1, min((int)($options['limit_customers'] ?? 200), 2000));
        $maxProducts = max(1, min((int)($options['max_products'] ?? 3), 8));
        $cooldownHours = max(0, min((int)($options['cooldown_hours'] ?? 48), 720));
        $force = (bool)($options['force'] ?? false);
        $baseEcommerceUrl = rtrim((string)($options['base_ecommerce_url'] ?? url('/ecommerce')), '/');

        $flashSale = FlashSale::query()
            ->with(['items' => function ($q) {
                $q->select('items.id', 'items.slug', 'items.description', 'items.sale_unit_price', 'items.stock')
                    ->where('apply_store', 1)
                    ->where('active', 1)
                    ->whereNotNull('internal_id');
            }])
            ->find($flashSaleId);

        if (!$flashSale) {
            return ['success' => false, 'message' => 'No existe la flash sale seleccionada.'];
        }

        if (!$force && !$flashSale->is_active_now) {
            return ['success' => false, 'message' => 'La flash sale no esta activa en este momento.'];
        }

        $products = $this->getDiscountProducts($flashSale, $maxProducts, $baseEcommerceUrl);
        if ($products->isEmpty()) {
            return ['success' => false, 'message' => 'No hay productos con descuento y stock para enviar.'];
        }

        $wa = new WhatsAppService();
        if (!$wa->isEnabled()) {
            return ['success' => false, 'message' => 'WhatsApp no esta configurado para este tenant.'];
        }

        $customersQuery = Person::query()
            ->whereType('customers')
            ->whereIsEnabled()
            ->whereNotNull('telephone')
            ->where('telephone', '<>', '')
            ->orderBy('id');

        if (!$force && $cooldownHours > 0 && Schema::hasTable('whatsapp_offer_campaign_messages')) {
            $from = now()->subHours($cooldownHours);
            $customersQuery->whereNotIn('id', function ($q) use ($from) {
                $q->select('person_id')
                    ->from('whatsapp_offer_campaign_messages')
                    ->where('status', 'sent')
                    ->where('sent_at', '>=', $from);
            });
        }

        $customers = $customersQuery->limit($limitCustomers)->get(['id', 'name', 'telephone']);
        if ($customers->isEmpty()) {
            return ['success' => false, 'message' => 'No hay clientes elegibles para enviar en este momento.'];
        }

        $canLogCampaigns = Schema::hasTable('whatsapp_offer_campaigns') && Schema::hasTable('whatsapp_offer_campaign_messages');

        $campaign = null;
        if ($canLogCampaigns) {
            $campaign = WhatsAppOfferCampaign::create([
                'name' => $options['campaign_name'] ?? ('Flash Sale - ' . $flashSale->title),
                'flash_sale_id' => $flashSale->id,
                'status' => 'processing',
                'total_customers' => $customers->count(),
                'sent_count' => 0,
                'failed_count' => 0,
                'meta' => [
                    'max_products' => $maxProducts,
                    'cooldown_hours' => $cooldownHours,
                    'products' => $products->values()->all(),
                ],
                'started_at' => now(),
            ]);
        }

        $sent = 0;
        $failed = 0;

        foreach ($customers as $customer) {
            $message = $this->buildMessage($customer->name, $flashSale->title, $flashSale->ends_at, $products);
            $ok = false;
            $errorMessage = null;

            try {
                $ok = $wa->send((string)$customer->telephone, $message);
                if ($ok) {
                    $sent++;
                } else {
                    $failed++;
                    $errorMessage = 'No se pudo enviar por proveedor WhatsApp.';
                }
            } catch (\Throwable $e) {
                $failed++;
                $errorMessage = $e->getMessage();
                Log::warning('[WhatsAppOfferCampaign] Error enviando mensaje', [
                    'person_id' => $customer->id,
                    'phone' => $customer->telephone,
                    'error' => $e->getMessage(),
                ]);
            }

            if ($campaign) {
                WhatsAppOfferCampaignMessage::updateOrCreate(
                    [
                        'campaign_id' => $campaign->id,
                        'person_id' => $customer->id,
                    ],
                    [
                        'phone' => $customer->telephone,
                        'status' => $ok ? 'sent' : 'failed',
                        'payload' => [
                            'products' => $products->values()->all(),
                            'text' => $message,
                        ],
                        'error_message' => $errorMessage,
                        'sent_at' => $ok ? now() : null,
                    ]
                );
            }
        }

        if ($campaign) {
            $campaign->update([
                'status' => 'completed',
                'sent_count' => $sent,
                'failed_count' => $failed,
                'finished_at' => now(),
            ]);
        }

        return [
            'success' => true,
            'message' => 'Campana enviada',
            'campaign_id' => $campaign?->id,
            'flash_sale_id' => $flashSale->id,
            'customers' => $customers->count(),
            'sent' => $sent,
            'failed' => $failed,
            'products' => $products->count(),
        ];
    }

    private function getDiscountProducts(FlashSale $flashSale, int $maxProducts, string $baseEcommerceUrl): Collection
    {
        $products = collect();

        foreach ($flashSale->items as $item) {
            $regularPrice = (float)$item->sale_unit_price;
            $flashPrice = (float)($item->pivot->flash_price ?? 0);
            $stock = (float)($item->stock ?? 0);

            if ($regularPrice <= 0 || $flashPrice <= 0 || $flashPrice >= $regularPrice) {
                continue;
            }
            if ($stock <= 0) {
                continue;
            }

            $discountPct = (int)round((($regularPrice - $flashPrice) / $regularPrice) * 100);
            $slug = $item->slug ?: $item->id;

            $products->push([
                'item_id' => $item->id,
                'description' => $item->description,
                'regular_price' => $regularPrice,
                'flash_price' => $flashPrice,
                'discount_pct' => max(1, $discountPct),
                'url' => $baseEcommerceUrl . '/item/' . $slug,
            ]);
        }

        return $products
            ->sortByDesc('discount_pct')
            ->take($maxProducts)
            ->values();
    }

    private function buildMessage(?string $customerName, string $flashSaleTitle, $endsAt, Collection $products): string
    {
        $firstName = trim((string)$customerName);
        if ($firstName !== '' && str_contains($firstName, ' ')) {
            $firstName = explode(' ', $firstName)[0];
        }
        $greeting = $firstName !== '' ? "Hola {$firstName}" : 'Hola';

        $lines = [
            "{$greeting}, tenemos descuentos para ti.",
            '',
            "Oferta: {$flashSaleTitle}",
            'Vigente hasta: ' . optional($endsAt)->format('d/m/Y H:i'),
            '',
            'Productos destacados:',
        ];

        foreach ($products as $product) {
            $lines[] = sprintf(
                '- %s: S/ %s (antes S/ %s, -%s%%)',
                $product['description'],
                number_format((float)$product['flash_price'], 2),
                number_format((float)$product['regular_price'], 2),
                $product['discount_pct']
            );
            $lines[] = '  ' . $product['url'];
        }

        $lines[] = '';
        $lines[] = 'Responde este mensaje y te ayudamos con tu pedido.';

        return implode("\n", $lines);
    }
}
