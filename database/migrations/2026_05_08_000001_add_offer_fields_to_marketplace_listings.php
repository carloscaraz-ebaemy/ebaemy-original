<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 0 — Sincronización de descuentos y soporte de variantes en el
 * índice central del marketplace.
 *
 * Hasta ahora `marketplace_listings` solo tenía `price` (sale_unit_price del
 * tenant) y `mp_price` (override manual). NO contemplaba:
 *   1. Promociones automáticas del tenant (DiscountRule con canal
 *      'marketplace': flash_sale, volume, bundle).
 *   2. Productos con variantes (talla/color/etc.) — ni precio mínimo, ni
 *      flag para que la UI muestre "Desde S/X".
 *
 * Esta migración agrega 7 columnas en dos bloques semánticos:
 *
 * Bloque OFERTAS (descuentos)
 *   is_on_offer    bool       → flag rápido para queries "ofertas activas"
 *   original_price decimal    → precio sin descuento (para tachar en UI)
 *   offer_ends_at  timestamp  → expiración de la promo (NULL = sin fecha)
 *   discount_pct   tinyint    → % de descuento ya calculado (0-100)
 *
 * Bloque VARIANTES (preparación de Fase 0.B futura)
 *   has_variants   bool       → el item del tenant tiene item_variants
 *   min_price      decimal    → MIN(variants.sale_unit_price) — para "Desde S/X"
 *   max_price      decimal    → MAX(variants.sale_unit_price) — para rango UI
 *
 * Los campos de variantes se llenan desde el sync (Commit B) calculando MIN/MAX
 * sobre `item_variants` cuando `items.has_variants=true`. La estructura completa
 * de variantes en marketplace (selector de variante en detalle, dispatch
 * cross-tenant con variant_id) queda para Fase 0.B en una migración aparte
 * que cree `marketplace_listing_variants`.
 *
 * Idempotente con hasColumn — `marketplace_listings` es tabla productiva.
 */
return new class extends Migration {
    public function up(): void
    {
        // Bloque OFERTAS
        Schema::table('marketplace_listings', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_listings', 'is_on_offer')) {
                $table->boolean('is_on_offer')->default(false)->after('mp_price');
            }
            if (!Schema::hasColumn('marketplace_listings', 'original_price')) {
                $table->decimal('original_price', 10, 2)->nullable()->after('is_on_offer');
            }
            if (!Schema::hasColumn('marketplace_listings', 'offer_ends_at')) {
                $table->timestamp('offer_ends_at')->nullable()->after('original_price');
            }
            if (!Schema::hasColumn('marketplace_listings', 'discount_pct')) {
                $table->unsignedTinyInteger('discount_pct')->nullable()->after('offer_ends_at');
            }
        });

        // Bloque VARIANTES
        Schema::table('marketplace_listings', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_listings', 'has_variants')) {
                $table->boolean('has_variants')->default(false)->after('discount_pct');
            }
            if (!Schema::hasColumn('marketplace_listings', 'min_price')) {
                $table->decimal('min_price', 10, 2)->nullable()->after('has_variants');
            }
            if (!Schema::hasColumn('marketplace_listings', 'max_price')) {
                $table->decimal('max_price', 10, 2)->nullable()->after('min_price');
            }
        });

        // Índices: un compuesto para queries del home tipo "Ofertas activas
        // que no expiraron" (el scope onOffer() lo usa). `has_variants` lo
        // dejamos sin índice por ahora — es selectivo solo cuando filtremos
        // explícitamente por "productos con variantes" (raro en home).
        Schema::table('marketplace_listings', function (Blueprint $table) {
            $existing = collect(
                \DB::connection('system')->select(
                    "SHOW INDEX FROM marketplace_listings WHERE Key_name = 'mp_listings_offer_idx'"
                )
            );
            if ($existing->isEmpty()) {
                $table->index(['is_on_offer', 'offer_ends_at'], 'mp_listings_offer_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_listings', function (Blueprint $table) {
            $existing = collect(
                \DB::connection('system')->select(
                    "SHOW INDEX FROM marketplace_listings WHERE Key_name = 'mp_listings_offer_idx'"
                )
            );
            if ($existing->isNotEmpty()) {
                $table->dropIndex('mp_listings_offer_idx');
            }
        });

        Schema::table('marketplace_listings', function (Blueprint $table) {
            $cols = [
                'max_price', 'min_price', 'has_variants',
                'discount_pct', 'offer_ends_at', 'original_price', 'is_on_offer',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('marketplace_listings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
