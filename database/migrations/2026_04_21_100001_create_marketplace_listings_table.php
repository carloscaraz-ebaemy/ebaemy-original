<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla en la BD central (landlord) que espeja los productos que cada tenant
 * decide publicar en el marketplace de ebaemy.com.
 *
 * No reemplaza a `items` del tenant — es solo un índice consultado por la vista
 * pública. El sync lo mantiene actualizado desde los tenants (pull o push).
 * El checkout redirige al tenant original (Fase 1); la Fase 2 captura el lead
 * en central y crea la Order en el tenant.
 */
class CreateMarketplaceListingsTable extends Migration
{
    public function up()
    {
        Schema::create('marketplace_listings', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Identificación del tenant dueño del producto
            $table->unsignedInteger('hostname_id')->nullable()->index();
            $table->string('tenant_fqdn', 180)->index()
                  ->comment('Hostname del tenant, p.ej. demo.ebaemy.com');
            $table->unsignedInteger('client_id')->nullable()->index()
                  ->comment('FK a clients.id para filtrar por plan/permisos');

            // Identificación del item en la BD del tenant
            $table->unsignedInteger('remote_item_id')
                  ->comment('items.id dentro de la BD del tenant');

            // Datos denormalizados para la vitrina
            $table->string('title', 255);
            $table->string('slug', 255)->index();
            $table->string('internal_id', 80)->nullable();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->string('image_url', 500)->nullable();
            $table->string('category_name', 150)->nullable()->index();
            $table->string('brand_name', 150)->nullable();

            // Precio — mp_price anula al precio del tenant si está seteado
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('mp_price', 12, 2)->nullable();
            $table->unsignedInteger('stock')->default(0);

            // Estado de publicación y moderación
            $table->enum('status', ['draft', 'pending_review', 'active', 'paused', 'rejected'])
                  ->default('active')
                  ->index();
            $table->boolean('is_active')->default(true)->index();
            $table->string('rejection_reason', 255)->nullable();

            // Ranking / relevancia en el listado
            $table->unsignedInteger('sort_score')->default(0)->index();
            $table->unsignedInteger('view_count')->default(0);
            $table->unsignedInteger('lead_count')->default(0);

            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            // Un item del tenant no se duplica en listings
            $table->unique(['hostname_id', 'remote_item_id'], 'uniq_listing_per_tenant_item');
        });
    }

    public function down()
    {
        Schema::dropIfExists('marketplace_listings');
    }
}
