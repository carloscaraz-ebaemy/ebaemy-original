<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DISCOUNT RULES — Sistema unificado de descuentos automáticos.
 *
 * Tipos de regla:
 *   volume      → Descuento por cantidad: "compra 3+ y obtén 15%"
 *   auto        → Descuento automático por monto: "gasta S/200+ y obtén 10%"
 *   channel     → Descuento por canal: "ecommerce siempre 5% off"
 *   flash_sale  → Oferta relámpago con ventana de tiempo
 *   bundle      → Descuento al comprar este pack específico
 *
 * Diferencia con coupons:
 *   - coupons    → requieren código manual del cliente
 *   - discounts  → se aplican automáticamente según condiciones
 *
 * Diseño:
 *   trigger_json → JSON flexible con condiciones:
 *     volume:   {"min_qty": 3, "item_id": 45}  (item_id opcional = aplica a cualquier producto)
 *     auto:     {"min_amount": 200}
 *     channel:  {"channel_type": "ecommerce"}  o channel_id para canal específico
 *     flash:    tiene starts_at/ends_at
 */
class CreateDiscountRulesTable extends Migration
{
    public function up()
    {
        if (Schema::connection('tenant')->hasTable('discount_rules')) return;

        Schema::connection('tenant')->create('discount_rules', function (Blueprint $table) {
            $table->increments('id');

            $table->string('name', 100)->comment('Nombre interno: "3x2 Laptops", "Ecommerce -5%"');

            $table->enum('type', ['volume', 'auto', 'channel', 'flash_sale', 'bundle'])
                  ->default('volume');

            // Condición de activación (flexible, sin columnas extra por cada tipo)
            $table->json('trigger_json')->nullable()
                  ->comment('Condición: {"min_qty":3,"item_id":45} | {"min_amount":200} | {"channel_type":"ecommerce"}');

            // Tipo de descuento
            $table->enum('discount_type', ['percentage', 'fixed'])
                  ->default('percentage');

            $table->decimal('discount_value', 10, 4)
                  ->comment('Valor del descuento: 15 = 15% si percentage, 20.00 = S/20 si fixed');

            // Alcance — qué productos aplica
            $table->enum('applies_to', ['all', 'item', 'bundle', 'category'])
                  ->default('all');

            $table->unsignedInteger('apply_item_id')->nullable()
                  ->comment('FK a items — solo si applies_to = item o bundle');

            // Canal específico (opcional, si es NULL aplica a todos los canales)
            $table->unsignedInteger('channel_id')->nullable()
                  ->comment('FK a sales_channels — null = aplica a cualquier canal');

            // Control de uso
            $table->unsignedInteger('max_uses')->nullable()
                  ->comment('null = ilimitado');
            $table->unsignedInteger('used_count')->default(0);

            // Vigencia
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->boolean('is_active')->default(true);

            // Prioridad de aplicación (mayor número = se aplica primero)
            $table->unsignedSmallInteger('priority')->default(0);

            // Si es false, no se combina con otras reglas ni cupones
            $table->boolean('stackable')->default(true)
                  ->comment('Permite combinarse con cupones u otras reglas');

            $table->timestamps();

            $table->index(['is_active', 'type']);
            $table->index(['apply_item_id']);
            $table->index(['channel_id']);
        });
    }

    public function down()
    {
        Schema::connection('tenant')->dropIfExists('discount_rules');
    }
}
