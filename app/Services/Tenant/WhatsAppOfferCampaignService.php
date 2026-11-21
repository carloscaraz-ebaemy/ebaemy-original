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

    // ══════════════════════════════════════════════════════════════════
    // Campañas por PRODUCTOS (2026-11-21): la tienda elige qué productos
    // anunciar y a quién; los mensajes salen espaciados por la cola.
    // ══════════════════════════════════════════════════════════════════

    public const AUDIENCIAS = ['all', 'buyers_12m', 'buyers_3m'];

    /** Segundos entre mensajes: por debajo de 20 parece robot. */
    public const ESPACIADO_MIN = 20;
    public const ESPACIADO_DEFECTO = 45;

    /** Tope de mensajes de campaña por día y tienda (todas las campañas). */
    public const TOPE_DIARIO_MAX = 300;
    public const TOPE_DIARIO_DEFECTO = 120;

    /**
     * Clientes elegibles: habilitados, con teléfono, sin baja, y según la
     * audiencia: todos, o solo quienes compraron en los últimos N meses
     * (comprobantes o notas de venta a su nombre).
     */
    public function clientesElegibles(string $audiencia, int $cooldownHours = 72)
    {
        $q = Person::query()
            ->whereType('customers')
            ->whereIsEnabled()
            ->whereNotNull('telephone')
            ->where('telephone', '<>', '')
            ->orderBy('id');

        if (Schema::hasColumn('persons', 'whatsapp_opt_out')) {
            $q->where(fn ($w) => $w->whereNull('whatsapp_opt_out')->orWhere('whatsapp_opt_out', 0));
        }

        $meses = ['buyers_12m' => 12, 'buyers_3m' => 3][$audiencia] ?? null;
        if ($meses) {
            $desde = now()->subMonths($meses)->toDateString();
            $q->where(function ($w) use ($desde) {
                $w->whereIn('id', fn ($s) => $s->select('customer_id')->from('documents')->where('date_of_issue', '>=', $desde)->whereNotNull('customer_id'))
                  ->orWhereIn('id', fn ($s) => $s->select('customer_id')->from('sale_notes')->where('date_of_issue', '>=', $desde)->whereNotNull('customer_id'));
            });
        }

        // No insistir a quien recibió una campaña hace poco.
        if ($cooldownHours > 0 && Schema::hasTable('whatsapp_offer_campaign_messages')) {
            $from = now()->subHours($cooldownHours);
            $q->whereNotIn('id', fn ($s) => $s->select('person_id')->from('whatsapp_offer_campaign_messages')
                ->whereIn('status', ['sent', 'pending'])
                ->where(fn ($x) => $x->where('sent_at', '>=', $from)->orWhere('status', 'pending')));
        }

        return $q;
    }

    /**
     * Productos a anunciar, a partir de los ids elegidos: solo los que están
     * en la tienda virtual y con stock. Precio de oferta si lo tienen.
     */
    public function productosParaCampana(array $itemIds, string $baseEcommerceUrl): Collection
    {
        $items = \App\Models\Tenant\Item::query()
            ->whereIn('id', array_values(array_unique(array_map('intval', $itemIds))))
            ->where('apply_store', 1)
            ->get();

        $out = collect();
        foreach ($items as $item) {
            $stock = (float) ($item->stock ?? 0);
            if ($stock <= 0 && !$item->has_variants) {
                continue;
            }
            $regular = (float) $item->sale_unit_price;
            // Oferta: el precio de una Flash Sale activa, si la hay (misma
            // regla que el marketplace en MarketplaceListingSyncService).
            $oferta = null;
            try {
                $flash = \DB::connection('tenant')->table('flash_sale_items as fsi')
                    ->join('flash_sales as fs', 'fs.id', '=', 'fsi.flash_sale_id')
                    ->where('fsi.item_id', $item->id)
                    ->where('fs.active', 1)
                    ->where('fs.ends_at', '>', now())
                    ->where(fn ($q) => $q->whereNull('fs.starts_at')->orWhere('fs.starts_at', '<=', now()))
                    ->orderBy('fsi.flash_price')
                    ->value('fsi.flash_price');
                $oferta = ($flash && (float) $flash > 0 && (float) $flash < $regular) ? (float) $flash : null;
            } catch (\Throwable $e) {
                $oferta = null;
            }
            $out->push([
                'item_id'       => $item->id,
                'description'   => $item->description,
                'regular_price' => $regular,
                'offer_price'   => $oferta,
                'url'           => $baseEcommerceUrl . '/item/' . ($item->slug ?: $item->id),
            ]);
        }

        return $out->values();
    }

    /**
     * Texto del mensaje para un cliente. `$intro` es lo que escribió la
     * tienda; admite {nombre}. Cierra con cómo darse de baja.
     */
    public function textoCampana(?string $customerName, string $intro, Collection $products, string $storeName): string
    {
        $firstName = trim((string) $customerName);
        if ($firstName !== '' && str_contains($firstName, ' ')) {
            $firstName = explode(' ', $firstName)[0];
        }
        $firstName = $firstName !== '' ? mb_convert_case(mb_strtolower($firstName), MB_CASE_TITLE) : '';

        $intro = trim(str_replace(['{nombre}', '{NOMBRE}'], $firstName, $intro));
        $intro = preg_replace('/^Hola\s*,/u', 'Hola,', $intro); // «Hola , Juan» → «Hola, Juan» si no hay nombre
        $intro = trim(preg_replace('/\s+,/u', ',', $intro));

        $lines = [$intro !== '' ? $intro : ($firstName !== '' ? "Hola {$firstName}, te compartimos estas novedades de {$storeName}." : "Hola, te compartimos estas novedades de {$storeName}."), ''];
        foreach ($products as $p) {
            $lines[] = $p['offer_price']
                ? sprintf('• %s: S/ %s (antes S/ %s)', $p['description'], number_format($p['offer_price'], 2), number_format($p['regular_price'], 2))
                : sprintf('• %s: S/ %s', $p['description'], number_format($p['regular_price'], 2));
            $lines[] = '  ' . $p['url'];
        }
        $lines[] = '';
        $lines[] = 'Responde este mensaje y te ayudamos con tu pedido.';
        $lines[] = 'Si no quieres recibir más avisos, responde BAJA.';

        return implode("\n", $lines);
    }

    /**
     * Crea la campaña con sus mensajes en `pending` y programa el primer
     * lote. No envía nada en el acto: la cola lo hace espaciado.
     */
    public function crearPorProductos(array $itemIds, array $opt): array
    {
        $wa = new WhatsAppService();
        if (!$wa->isEnabled()) {
            return ['success' => false, 'message' => 'WhatsApp no está conectado en esta tienda. Conéctalo en WhatsApp → Conexión.'];
        }
        if ($motivo = $wa->downReason()) {
            return ['success' => false, 'message' => $motivo];
        }

        $audiencia = in_array($opt['audience'] ?? 'all', self::AUDIENCIAS, true) ? $opt['audience'] : 'all';
        $espaciado = max(self::ESPACIADO_MIN, min((int) ($opt['spacing_seconds'] ?? self::ESPACIADO_DEFECTO), 600));
        $tope      = max(10, min((int) ($opt['daily_cap'] ?? self::TOPE_DIARIO_DEFECTO), self::TOPE_DIARIO_MAX));
        $cooldown  = max(0, min((int) ($opt['cooldown_hours'] ?? 72), 720));
        $limite    = max(1, min((int) ($opt['limit_customers'] ?? 1000), 5000));

        $products = $this->productosParaCampana($itemIds, $opt['base_ecommerce_url'] ?? url('/ecommerce'));
        if ($products->isEmpty()) {
            return ['success' => false, 'message' => 'Ninguno de los productos elegidos está en la tienda virtual con stock.'];
        }

        $customers = $this->clientesElegibles($audiencia, $cooldown)->limit($limite)->get(['id', 'name', 'telephone']);
        if ($customers->isEmpty()) {
            return ['success' => false, 'message' => 'No hay clientes elegibles para esa audiencia.'];
        }

        $storeName = (string) (optional(\App\Models\Tenant\Company::query()->first())->trade_name ?: 'nuestra tienda');
        $intro = (string) ($opt['message'] ?? '');

        $campaign = WhatsAppOfferCampaign::create([
            'name'            => $opt['campaign_name'] ?: ('Campaña ' . now()->format('d/m/Y')),
            'kind'            => 'products',
            'status'          => 'queued',
            'total_customers' => $customers->count(),
            'sent_count'      => 0,
            'failed_count'    => 0,
            'meta'            => [
                'audience'        => $audiencia,
                'spacing_seconds' => $espaciado,
                'daily_cap'       => $tope,
                'cooldown_hours'  => $cooldown,
                'intro'           => $intro,
                'products'        => $products->all(),
            ],
        ]);

        $ahora = now();
        $rows = [];
        foreach ($customers as $c) {
            $rows[] = [
                'campaign_id'   => $campaign->id,
                'person_id'     => $c->id,
                'phone'         => $c->telephone,
                'status'        => 'pending',
                'payload'       => json_encode(['text' => $this->textoCampana($c->name, $intro, $products, $storeName)], JSON_UNESCAPED_UNICODE),
                'created_at'    => $ahora,
                'updated_at'    => $ahora,
            ];
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            WhatsAppOfferCampaignMessage::insert($chunk);
        }

        $programados = $this->programarLote($campaign);

        return [
            'success'     => true,
            'message'     => "Campaña creada: {$customers->count()} clientes. Hoy saldrán {$programados}, uno cada {$espaciado} s; el resto, los días siguientes.",
            'campaign_id' => $campaign->id,
            'customers'   => $customers->count(),
            'scheduled'   => $programados,
            'products'    => $products->count(),
        ];
    }

    /**
     * Programa los mensajes pendientes que caben en el tope de HOY (contando
     * lo ya enviado o programado hoy por cualquier campaña de la tienda), uno
     * cada N segundos. Devuelve cuántos programó. Lo llama la creación y el
     * comando diario `whatsapp:campaigns-continue`.
     */
    public function programarLote(WhatsAppOfferCampaign $campaign): int
    {
        if (!in_array($campaign->status, ['queued', 'processing'], true)) {
            return 0;
        }
        $meta      = $campaign->meta ?? [];
        $espaciado = max(self::ESPACIADO_MIN, (int) ($meta['spacing_seconds'] ?? self::ESPACIADO_DEFECTO));
        $tope      = max(10, min((int) ($meta['daily_cap'] ?? self::TOPE_DIARIO_DEFECTO), self::TOPE_DIARIO_MAX));

        $hoy = now()->startOfDay();
        $usadosHoy = WhatsAppOfferCampaignMessage::query()
            ->where(fn ($q) => $q->where('sent_at', '>=', $hoy)->orWhere('scheduled_at', '>=', $hoy))
            ->count();
        $cupo = $tope - $usadosHoy;
        if ($cupo <= 0) {
            return 0;
        }

        // Continúa después del último programado para no solapar lotes.
        $ultimo = WhatsAppOfferCampaignMessage::where('status', 'pending')->whereNotNull('scheduled_at')->max('scheduled_at');
        $inicio = $ultimo && \Carbon\Carbon::parse($ultimo)->gt(now()) ? \Carbon\Carbon::parse($ultimo) : now();

        $pendientes = $campaign->messages()->where('status', 'pending')->whereNull('scheduled_at')->orderBy('id')->limit($cupo)->get();
        $i = 0;
        foreach ($pendientes as $msg) {
            $i++;
            $cuando = $inicio->copy()->addSeconds($i * $espaciado);
            $msg->update(['scheduled_at' => $cuando]);
            \App\Jobs\SendWhatsAppCampaignMessage::dispatch($msg->id)->delay($cuando);
        }

        if ($i > 0 && $campaign->status === 'queued') {
            $campaign->update(['started_at' => $campaign->started_at ?: now()]);
        }

        return $i;
    }

    /** Marca la campaña como terminada cuando no queda nada pendiente. */
    public function cerrarSiTermino(WhatsAppOfferCampaign $campaign): void
    {
        if (!in_array($campaign->status, ['queued', 'processing'], true)) {
            return;
        }
        if ($campaign->messages()->where('status', 'pending')->exists()) {
            return;
        }
        $campaign->update([
            'status'       => 'completed',
            'sent_count'   => $campaign->messages()->where('status', 'sent')->count(),
            'failed_count' => $campaign->messages()->where('status', 'failed')->count(),
            'finished_at'  => now(),
        ]);
    }

    /** Detiene la campaña: lo pendiente pasa a `skipped`; lo enviado se queda. */
    public function cancelar(WhatsAppOfferCampaign $campaign): int
    {
        $n = $campaign->messages()->where('status', 'pending')->update([
            'status'        => 'skipped',
            'error_message' => 'Campaña cancelada por la tienda.',
            'scheduled_at'  => null,
        ]);
        $campaign->update(['status' => 'cancelled', 'finished_at' => now()]);

        return $n;
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
