<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos que faltaban a nivel variante.
 *
 * Todos nullable con la misma semántica que ya usan sku, barcode, image,
 * sale_unit_price y purchase_unit_price: NULL = hereda del producto padre. Es el
 * patrón que mejor funciona del módulo y aquí se extiende a lo que se quedó
 * fuera:
 *
 *   compare_at_price + ventana → no se podía liquidar solo la talla 45 sin
 *       liquidar el producto entero. El espejo del marketplace YA guarda oferta
 *       por variante, pero se calculaba al sincronizar y no se podía definir:
 *       la capacidad existía en el destino y faltaba en el origen.
 *   stock_min → sin él no hay alerta de reposición por talla, que es justo la
 *       que importa (el modelo puede tener 20 unidades y la 38 en cero).
 *   weight/length/width/height → una talla 45 pesa más que una 36 y el cálculo
 *       de envío usaba el peso del padre para las dos.
 *   min_margin_pct → el guardarraíl de precio usaba siempre el margen mínimo
 *       del padre, aunque una variante tuviera un costo muy distinto.
 *
 * Aditiva e idempotente. Nada de lo existente cambia de comportamiento: con
 * todo a NULL, cada campo sigue resolviéndose al valor del padre igual que hoy.
 */
return new class extends Migration
{
    /** columna => closure que la define */
    private function columnas(): array
    {
        return [
            'compare_at_price' => fn (Blueprint $t) => $t->decimal('compare_at_price', 12, 4)->nullable()
                ->comment('Precio tachado de esta variante; null = hereda del producto'),
            'compare_at_from'  => fn (Blueprint $t) => $t->date('compare_at_from')->nullable(),
            'compare_at_until' => fn (Blueprint $t) => $t->date('compare_at_until')->nullable(),
            'stock_min'        => fn (Blueprint $t) => $t->decimal('stock_min', 12, 4)->nullable()
                ->comment('Stock mínimo de esta variante; null = hereda del producto'),
            'min_margin_pct'   => fn (Blueprint $t) => $t->decimal('min_margin_pct', 5, 2)->nullable(),
            'weight'           => fn (Blueprint $t) => $t->decimal('weight', 10, 3)->nullable()->comment('kg'),
            'length'           => fn (Blueprint $t) => $t->decimal('length', 10, 2)->nullable()->comment('cm'),
            'width'            => fn (Blueprint $t) => $t->decimal('width', 10, 2)->nullable()->comment('cm'),
            'height'           => fn (Blueprint $t) => $t->decimal('height', 10, 2)->nullable()->comment('cm'),
        ];
    }

    public function up(): void
    {
        if (!Schema::hasTable('item_variants')) return;

        foreach ($this->columnas() as $nombre => $definir) {
            if (Schema::hasColumn('item_variants', $nombre)) continue;

            Schema::table('item_variants', function (Blueprint $table) use ($definir) {
                $definir($table);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('item_variants')) return;

        $existentes = array_filter(
            array_keys($this->columnas()),
            fn ($c) => Schema::hasColumn('item_variants', $c)
        );

        if (empty($existentes)) return;

        Schema::table('item_variants', function (Blueprint $table) use ($existentes) {
            $table->dropColumn(array_values($existentes));
        });
    }
};
