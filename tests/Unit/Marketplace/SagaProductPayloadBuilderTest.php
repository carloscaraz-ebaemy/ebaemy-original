<?php

namespace Tests\Unit\Marketplace;

use Tests\TestCase;
use App\Services\Marketplace\SagaProductPayloadBuilder;
use App\Models\Tenant\MarketplaceChannel;
use App\Models\Tenant\MarketplaceProduct;
use Carbon\Carbon;

/**
 * Cubre la validación previa, la lógica de precios (oferta) y la construcción
 * del XML del builder, SIN tocar BD: inyecta como relación 'item' un objeto
 * plano (stdClass) con las propiedades que el builder lee. Así evitamos que
 * Eloquent resuelva relaciones contra la conexión tenant (no configurada en
 * tests unitarios). La categoría queda sin homologar (category_id null).
 */
class SagaProductPayloadBuilderTest extends TestCase
{
    private function item(array $attrs = []): object
    {
        return (object) array_merge([
            'id' => 5, 'description' => 'Polo Azul', 'name' => 'Polo de algodón',
            'category_id' => null, 'category' => null, 'brand' => null,
            'stock' => 10, 'sale_unit_price' => 99.0, 'compare_at_price' => 0.0,
            'compare_at_from' => null, 'compare_at_until' => null,
            'weight' => 0.5, 'length' => 10.0, 'width' => 8.0, 'height' => 3.0,
            'barcode' => 'EAN123', 'item_code_gs1' => null,
            'internal_id' => 'SKU-1', 'image' => null,
            'images' => collect(),
        ], $attrs);
    }

    private function builder(object $item): SagaProductPayloadBuilder
    {
        $channel = new MarketplaceChannel(['platform' => 'falabella']);
        $mapping = new MarketplaceProduct(['external_sku' => 'SKU-1']);
        $mapping->setRelation('item', $item);
        $mapping->setRelation('variant', null);

        return new SagaProductPayloadBuilder($channel, $mapping);
    }

    public function test_validate_detecta_categoria_sin_homologar_y_sin_imagenes()
    {
        $txt = implode(' ', $this->builder($this->item())->validate());

        $this->assertStringContainsString('sin homologar', $txt);
        $this->assertStringContainsString('imágenes', $txt);
    }

    public function test_validate_detecta_precio_y_peso_invalidos()
    {
        $txt = implode(' ', $this->builder($this->item(['sale_unit_price' => 0.0, 'weight' => 0.0]))->validate());

        $this->assertStringContainsString('Precio', $txt);
        $this->assertStringContainsString('peso', $txt);
    }

    public function test_validate_detecta_sin_nombre()
    {
        $txt = implode(' ', $this->builder($this->item(['description' => '', 'name' => '']))->validate());

        $this->assertStringContainsString('nombre', $txt);
    }

    public function test_pricing_usa_compare_at_como_regular_y_venta_como_oferta()
    {
        $p = $this->builder($this->item([
            'sale_unit_price' => 99.0, 'compare_at_price' => 149.0,
            'compare_at_from' => Carbon::parse('2026-06-01'),
            'compare_at_until' => Carbon::parse('2026-06-30'),
        ]))->pricing();

        $this->assertEquals(149.0, $p['price']);
        $this->assertEquals(99.0, $p['sale_price']);
        $this->assertSame('2026-06-01', $p['sale_start']);
        $this->assertSame('2026-06-30', $p['sale_end']);
    }

    public function test_pricing_sin_oferta_cuando_no_hay_compare_at()
    {
        $p = $this->builder($this->item(['sale_unit_price' => 99.0, 'compare_at_price' => 0.0]))->pricing();

        $this->assertEquals(99.0, $p['price']);
        $this->assertNull($p['sale_price']);
    }

    public function test_toxml_incluye_sku_precio_y_dimensiones()
    {
        $xml = $this->builder($this->item())->toXml();

        $this->assertStringContainsString('<SellerSku>SKU-1</SellerSku>', $xml);
        $this->assertStringContainsString('<price>99.00</price>', $xml);
        $this->assertStringContainsString('<package_weight>0.500</package_weight>', $xml);
        $this->assertStringContainsString('<Skus><Sku>', $xml);
    }

    public function test_toxml_escapa_caracteres_xml()
    {
        $xml = $this->builder($this->item(['description' => 'Polo & Co <test>']))->toXml();

        $this->assertStringContainsString('&amp;', $xml);
        $this->assertStringNotContainsString('<test>', $xml);
    }

    public function test_toxml_sin_precio_para_update_no_envia_price()
    {
        // ProductUpdate: no debe reenviar price/sale_price (el seller los maneja
        // en Saga) pero sí el resto (sku, quantity, dimensiones).
        $xml = $this->builder($this->item([
            'sale_unit_price' => 99.0, 'compare_at_price' => 149.0,
            'compare_at_from' => Carbon::parse('2026-06-01'),
            'compare_at_until' => Carbon::parse('2026-06-30'),
        ]))->toXml(false);

        $this->assertStringNotContainsString('<price>', $xml);
        $this->assertStringNotContainsString('<sale_price>', $xml);
        $this->assertStringContainsString('<SellerSku>SKU-1</SellerSku>', $xml);
        $this->assertStringContainsString('<quantity>', $xml);
    }

    public function test_toxml_incluye_oferta_cuando_aplica()
    {
        $xml = $this->builder($this->item([
            'sale_unit_price' => 99.0, 'compare_at_price' => 149.0,
            'compare_at_from' => Carbon::parse('2026-06-01'),
            'compare_at_until' => Carbon::parse('2026-06-30'),
        ]))->toXml();

        $this->assertStringContainsString('<sale_price>99.00</sale_price>', $xml);
        $this->assertStringContainsString('<sale_start_date>2026-06-01</sale_start_date>', $xml);
    }
}
