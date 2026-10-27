<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitudes de publicidad de las tiendas: el dueño pide desde su panel
 * (Tienda virtual → «Publicidad») que ebaemy anuncie sus productos en
 * Facebook/Instagram, y ebaemy la gestiona en /admin/marketplace/publicidad.
 *
 * Estados: solicitada → pagada → activa → terminada (o rechazada).
 * `utm_campaign` es el nombre de la campaña en Meta y la clave con la que se
 * cuentan sus pedidos y consultas (marketplace_orders / marketplace_leads).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('marketplace_ad_requests')) {
            return;
        }
        Schema::create('marketplace_ad_requests', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('hostname_id')->index();
            $table->string('subdomain', 80);
            $table->string('store_name', 160)->nullable();
            $table->string('requested_by', 120)->nullable();
            $table->string('contact_phone', 30)->nullable();

            $table->string('scope', 20);                      // tienda | categoria | productos
            $table->string('scope_detail', 500)->nullable();  // categoría elegida o productos
            $table->string('zone', 20)->default('lima');      // lima | peru
            $table->decimal('budget', 10, 2);
            $table->unsignedSmallInteger('days');
            $table->string('notes', 500)->nullable();

            $table->string('status', 20)->default('solicitada');
            $table->string('utm_campaign', 80)->nullable()->index();
            $table->decimal('spent', 10, 2)->nullable();
            $table->string('admin_notes', 500)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_ad_requests');
    }
};
