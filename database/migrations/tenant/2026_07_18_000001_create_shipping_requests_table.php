<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo "Registro y Control de Envíos" (panel del tenant).
 *
 * El cliente registra sus datos (formulario público) → estado "pendiente".
 * El encargado de despacho gestiona el paquete y, cuando lo entrega a la
 * agencia, sube la guía de envío → estado "enviado".
 *
 * Multi-tenant DB-per-tenant: la tabla vive en la BD del tenant, sin columna
 * tenant_id (el aislamiento lo da la propia base de datos).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('shipping_requests')) {
            return;
        }

        Schema::connection('tenant')->create('shipping_requests', function (Blueprint $table) {
            $table->id();

            // Código interno legible del envío (ENV-000125). Se genera tras crear.
            $table->string('shipment_code', 20)->nullable()->unique();

            // Enlace OPCIONAL a un pedido existente (ecommerce/marketplace / NV).
            // FK a tabla legacy → unsignedInteger (no foreignId). Nullable para
            // soportar el caso de registro manual/público independiente.
            $table->unsignedInteger('order_id')->nullable();

            // Datos del destinatario (los registra el cliente o el encargado)
            $table->string('full_name', 160);
            $table->string('dni', 15)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('shipping_destination', 255)->nullable(); // dirección/destino
            $table->string('destination_city', 120)->nullable();      // nombre del distrito (derivado del ubigeo)
            // Ubigeo del destino (códigos: dep 2 · prov 4 · dist 6 dígitos)
            $table->string('department_id', 2)->nullable();
            $table->string('province_id', 4)->nullable();
            $table->string('district_id', 6)->nullable();
            $table->string('shipping_agency', 120)->nullable();       // Shalom, Olva, etc.

            // Detalle del paquete
            $table->string('package_content', 255)->nullable();       // qué se envía
            $table->unsignedSmallInteger('package_count')->default(1); // N° de bultos
            $table->decimal('weight', 8, 2)->nullable();              // peso aprox (kg)
            $table->string('notes', 255)->nullable();                 // información adicional

            // Datos que carga el encargado al despachar
            $table->string('tracking_number', 120)->nullable();       // N° de guía de la agencia
            $table->string('shipping_guide_path', 255)->nullable();   // archivo JPG/PNG/PDF
            $table->string('observation', 255)->nullable();

            // Flujo del paquete: pendiente | preparando | listo | enviado | entregado
            $table->string('status', 20)->default('pendiente');

            $table->boolean('accepted_terms')->default(false);
            $table->timestamp('sent_at')->nullable();

            // Usuario del tenant que registró (si vino del panel, no del público)
            $table->unsignedInteger('created_by')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('order_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('shipping_requests');
    }
};
