<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convierte `items.target_margin_pct` de MARKUP sobre costo a MARGEN sobre venta.
 *
 * ── Qué pasó ───────────────────────────────────────────────────────────────
 * La instalación de la Fase 1 de precios copió el campo viejo tal cual:
 *
 *     UPDATE items SET target_margin_pct = percentage_of_profit WHERE ...
 *
 * Pero `percentage_of_profit` es MARKUP —la ficha calcula
 * `precio = costo × (1 + %/100)`— y `target_margin_pct` lo consume
 * `PriceCalculator` como MARGEN sobre venta, con `precio = costo / (1 − %/100)`.
 * El número cambió de significado sin cambiar de valor.
 *
 * La prueba está en los propios datos: hay productos con 122.22 y 128.57, y un
 * margen sobre venta no puede pasar de 100 —`listPriceFromMargin()` lanza
 * excepción si lo recibe— mientras que un markup de 122% es perfectamente
 * normal. Entraron porque la copia fue por SQL directo y se salteó la
 * validación `between:0,99.99` de `ItemRequest`.
 *
 * ── Por qué importa ────────────────────────────────────────────────────────
 * `floor_price` y el monitor nocturno de márgenes se calculan contra ese
 * objetivo. Con el valor equivocado, el piso de precio y las alertas de margen
 * erosionado apuntan a un número que nadie pretendió.
 *
 * ── La conversión ──────────────────────────────────────────────────────────
 *     margen = markup / (1 + markup / 100)
 *
 * Comprobado contra producción: markup 100 → margen 50 (costo 165, venta 330),
 * y markup 38.89 → margen 28.0, que es exactamente el margen real de ese
 * producto. Se convierte la INTENCIÓN (qué objetivo se buscaba), no el margen
 * real que arroja el precio guardado hoy.
 *
 * NO se toca ningún precio: `sale_unit_price` y `purchase_unit_price` quedan
 * intactos, y `percentage_of_profit` también, que sigue siendo la fuente del
 * campo de la ficha.
 *
 * Solo se convierten los valores > 100, que son imposibles como margen, y los
 * que siguen coincidiendo con `percentage_of_profit` — o sea los que nadie ha
 * tocado desde la copia. Un `target_margin_pct` que ya difiera del viejo campo
 * lo puso alguien a mano con criterio de margen y se respeta.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('items')
            || !Schema::hasColumn('items', 'target_margin_pct')
            || !Schema::hasColumn('items', 'percentage_of_profit')) {
            return;
        }

        DB::table('items')
            ->whereNotNull('target_margin_pct')
            ->where('target_margin_pct', '>', 0)
            ->where(function ($q) {
                // Imposible como margen: solo puede ser markup.
                $q->where('target_margin_pct', '>=', 100)
                  // O intacto desde la copia de la Fase 1.
                  ->orWhereColumn('target_margin_pct', 'percentage_of_profit');
            })
            ->orderBy('id')
            ->chunkById(200, function ($items) {
                foreach ($items as $item) {
                    $markup = (float) $item->target_margin_pct;

                    // margen = markup / (1 + markup/100). Con markup > 0 el
                    // divisor nunca es cero, así que no hay caso degenerado.
                    $margen = $markup / (1 + $markup / 100);

                    // La columna es decimal(5,2) y `ItemRequest` exige < 100:
                    // el tope deja el valor dentro de lo que el resto del
                    // sistema acepta. Un markup enorme tiende a 100 y ahí se
                    // queda.
                    $margen = min(99.99, round($margen, 2));

                    DB::table('items')
                        ->where('id', $item->id)
                        ->update(['target_margin_pct' => $margen]);
                }
            });
    }

    public function down(): void
    {
        // La vuelta atrás es markup = margen / (1 − margen/100), pero aplicarla
        // a ciegas reconvertiría también los valores que ya eran margen de
        // verdad. Si hay que revertir, se restaura el respaldo previo
        // (backups/items_pricing_pre_margin_fix_*.sql).
    }
};
