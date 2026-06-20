<?php

namespace App\Console\Commands;

use App\Models\Tenant\Item;
use App\Models\Tenant\MarketplaceChannel;
use App\Models\Tenant\MarketplaceProduct;
use App\Services\Marketplace\SagaProductPayloadBuilder;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;

/**
 * DRY-RUN: arma el payload que se ENVIARÍA a Saga para un producto y lo muestra
 * SIN llamar a la API. Sirve para inspeccionar el XML (ProductCreate/Update),
 * el stock que se mandaría, la marca homologada y los bloqueadores de validación
 * antes del primer push real.
 *
 * NO toca nada (no escribe, no llama a Saga). Solo lee.
 *
 * Uso:
 *   php artisan marketplace:saga-preview --tenant=UUID --sku=00328
 *   php artisan marketplace:saga-preview --tenant=UUID --item=1234
 */
class MarketplaceSagaPreview extends Command
{
    protected $signature = 'marketplace:saga-preview
                            {--tenant= : UUID del website (tenant)}
                            {--sku= : external_sku / código interno del producto}
                            {--item= : item_id interno (alternativa a --sku)}';

    protected $description = 'DRY-RUN: muestra el XML/stock que se enviaría a Saga para un producto, sin enviarlo';

    public function handle(): int
    {
        $uuid = $this->option('tenant');
        if (!$uuid) {
            $this->error('Falta --tenant=UUID.');
            return self::FAILURE;
        }

        $website = Website::where('uuid', $uuid)->first();
        if (!$website) {
            $this->error("No existe el tenant {$uuid}.");
            return self::FAILURE;
        }
        app(Environment::class)->tenant($website);

        $channel = MarketplaceChannel::where('platform', 'falabella')->where('status', 'active')->first();
        if (!$channel) {
            $this->error('Este tenant no tiene un canal de Saga Falabella activo.');
            return self::FAILURE;
        }

        // Resolver el item (con warehouses para el stock real).
        $item = $this->resolveItem();
        if (!$item) {
            $this->error('No se encontró el producto. Usa --sku=<código> o --item=<id>.');
            return self::FAILURE;
        }

        // Usar el mapeo real si existe (trae attributes/external_id); si no, uno
        // transitorio para previsualizar (NO se guarda).
        $sku = $item->internal_id ?: $item->item_code;
        $mapping = MarketplaceProduct::where('channel_id', $channel->id)
            ->where('item_id', $item->id)
            ->whereNull('item_variant_id')
            ->first();
        if (!$mapping) {
            $mapping = new MarketplaceProduct([
                'channel_id' => $channel->id,
                'item_id'    => $item->id,
                'external_sku' => $sku,
            ]);
            $this->warn('(No hay mapeo guardado: se previsualiza uno transitorio, NO se guarda.)');
        }
        $mapping->setRelation('item', $item);
        $mapping->setRelation('variant', null);

        $builder = new SagaProductPayloadBuilder($channel, $mapping);

        $this->line('');
        $this->info('═══ DRY-RUN Saga · ' . $website->uuid . ' ═══');
        $this->line('Producto : ' . ($item->description ?? ('#' . $item->id)));
        $this->line('SellerSku: ' . ($mapping->external_sku ?: $sku));
        $this->line('Marca    : ' . $builder->brand() . ($item->brand_id ? " (brand_id={$item->brand_id})" : ''));
        $this->line('Categoría Saga: ' . ($builder->sagaCategoryId() ?: '— SIN HOMOLOGAR —'));
        $this->line('Stock a enviar: ' . $builder->quantity() . '  (SUM item_warehouse.stock; items.stock=' . (int) ($item->stock ?? 0) . ')');
        $this->line('Estado actual : sync=' . ($mapping->sync_status ?: 'nuevo') . ' · qc=' . ($mapping->qc_status ?: '-') . ' · external_id=' . ($mapping->external_id ?: '-'));

        $stored = $builder->storedAttributes();
        $this->line('Atributos por producto: ' . (empty($stored) ? '(ninguno)' : json_encode($stored, JSON_UNESCAPED_UNICODE)));

        // Bloqueadores de validación.
        $problems = $builder->validate();
        $this->line('');
        if (empty($problems)) {
            $this->info('✔ Validación: LISTO para publicar (sin bloqueadores).');
        } else {
            $this->error('Validacion: ' . count($problems) . ' bloqueador(es):');
            foreach ($problems as $p) {
                $this->line('   • ' . $p);
            }
        }

        $this->line('');
        $this->info('── XML ProductCreate (nuevo, CON precio) ──');
        $this->line($this->pretty($builder->toXml(true)));

        $this->line('');
        $this->info('── XML ProductUpdate (ya live, SIN precio: no pisa el del seller) ──');
        $this->line($this->pretty($builder->toXml(false)));

        $this->line('');
        $this->info('── XML delist (OFF, reversible) ──');
        $this->line($this->pretty($builder->statusXml('inactive')));

        $this->line('');
        $this->warn('DRY-RUN: no se envió nada a Saga.');
        return self::SUCCESS;
    }

    private function resolveItem(): ?Item
    {
        $itemId = $this->option('item');
        if ($itemId) {
            return Item::with('warehouses')->find((int) $itemId);
        }

        $sku = $this->option('sku');
        if (!$sku) {
            return null;
        }

        // Buscar por mapeo (external_sku) → o por código interno / item_code.
        $mapping = MarketplaceProduct::where('external_sku', $sku)->first();
        if ($mapping) {
            return Item::with('warehouses')->find($mapping->item_id);
        }
        return Item::with('warehouses')
            ->where('internal_id', $sku)
            ->orWhere('item_code', $sku)
            ->first();
    }

    /** Formatea el XML para que sea legible en consola. */
    private function pretty(string $xml): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        if (@$dom->loadXML($xml)) {
            return rtrim($dom->saveXML());
        }
        return $xml; // si por alguna razón no parsea, lo mostramos crudo
    }
}
