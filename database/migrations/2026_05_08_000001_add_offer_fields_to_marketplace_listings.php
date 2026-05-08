<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 0 — Sincronización de descuentos del tenant al marketplace central.
 *
 * Hasta ahora `marketplace_listings` solo tenía `price` (sale_unit_price del
 * tenant) y `mp_price` (override manual del tenant para el marketplace). Las
 * promociones automáticas del tenant (DiscountRule activas con canal
 * 'marketplace': flash_sale, volume, bundle) NO se reflejaban en el listado
 * público.
 *
 * Estos 4 campos permiten que el sync calcule el "precio efectivo público"
 * pasando por el PromotionEngine del tenant y persista metadata útil para
 * la UI: badge de descuento, precio tachado, countdown de expiración.
 *
 *   is_on_offer    bool       → flag rápido para queries "ofertas activas"
 *   original_price decimal    → precio sin descuento (para tachar en UI)
 *   offer_ends_at  timestamp  → expiración de la promo (NULL = sin fecha)
 *   discount_pct   tinyint    → % de descuento ya calculado (0-100)
 *
 * Idempotente con hasColumn — `marketplace_listings` es tabla productiva.
 */
return new class extends Migration {
    public function up(): void
    {
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

        // Índice compuesto para queries del home: "Ofertas activas que no han
        // expirado" en una sola lectura. El scope `onOffer()` del modelo usa
        // exactamente este patrón.
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
            foreach (['discount_pct', 'offer_ends_at', 'original_price', 'is_on_offer'] as $col) {
                if (Schema::hasColumn('marketplace_listings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
