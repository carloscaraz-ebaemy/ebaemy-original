<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SALES CHANNELS — Tabla maestra de canales de venta.
 *
 * Ejemplos de canales:
 *   - ecommerce  → tienda online
 *   - pos        → punto de venta físico
 *   - whatsapp   → ventas por WhatsApp
 *   - phone      → ventas telefónicas
 *   - marketplace→ MercadoLibre, Amazon, etc.
 *
 * Diseño consciente:
 *   - warehouse_id  → almacén por defecto para despacho desde este canal
 *   - is_active     → permite desactivar canal sin borrar historial
 *   - settings JSON → configuración extendida sin más migraciones
 */
class CreateSalesChannelsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('sales_channels')) return;

        Schema::create('sales_channels', function (Blueprint $table) {
            $table->increments('id');

            $table->string('name', 60)
                  ->comment('Nombre visible: "Tienda Online", "POS Centro", etc.');

            $table->enum('type', ['ecommerce', 'pos', 'whatsapp', 'phone', 'marketplace', 'other'])
                  ->default('other')
                  ->comment('Tipo de canal — usado para lógica de negocio y reportes');

            $table->string('code', 20)->unique()
                  ->comment('Código corto único: ECOM, POS01, WHA01 — útil en reportes');

            // Almacén por defecto desde donde se despacha este canal.
            // nullable → permite canal sin almacén asignado (e.g., marketplace externo)
            $table->unsignedInteger('warehouse_id')->nullable();

            $table->boolean('is_active')->default(true);

            // Configuración extendida: color, ícono, comisión, URL, etc.
            $table->json('settings')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sales_channels');
    }
}
