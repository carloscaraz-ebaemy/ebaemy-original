<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitudes de nuevas categorías oficiales del marketplace, hechas por
 * sellers desde su panel cuando publican un producto y no encuentran una
 * categoría adecuada en el árbol existente.
 *
 * El SuperAdmin las revisa desde /admin/marketplace/category-requests y
 * decide aprobar (lo cual materializa una nueva fila en marketplace_categories)
 * o rechazar con motivo.
 *
 * tenant_id es FK a system.clients pero sin constraint para evitar bloqueos
 * en deletes legítimos (mismo patrón que seller_applications.tenant_id).
 */
class CreateMarketplaceCategoryRequestsTable extends Migration
{
    public function up()
    {
        Schema::create('marketplace_category_requests', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Origen
            $table->unsignedBigInteger('tenant_id')->index()
                  ->comment('FK lógica a system.clients — quién pidió');
            $table->unsignedInteger('user_id')->nullable()
                  ->comment('FK lógica al users del tenant que originó (informativo)');
            $table->unsignedInteger('product_id')->nullable()
                  ->comment('Item del tenant que motivó la solicitud (informativo)');

            // Propuesta del seller
            $table->string('suggested_name', 150);
            $table->unsignedBigInteger('suggested_parent_id')->nullable()
                  ->comment('Si el seller sugiere bajo qué categoría existente debería ir');
            $table->text('description')->nullable()
                  ->comment('Por qué necesita esta categoría — ayuda al admin a decidir');

            // Workflow
            $table->enum('status', ['pending', 'approved', 'rejected'])
                  ->default('pending')->index();
            $table->text('admin_response')->nullable()
                  ->comment('Mensaje del admin al seller, sea aprobación o rechazo');
            $table->unsignedBigInteger('reviewed_by')->nullable()
                  ->comment('FK a system.users (SuperAdmin que respondió)');
            $table->timestamp('reviewed_at')->nullable();

            // Resultado si fue aprobada
            $table->unsignedBigInteger('created_marketplace_category_id')->nullable()
                  ->comment('FK a marketplace_categories — la categoría que se creó al aprobar');

            $table->timestamps();

            // FKs con nombre corto manual: el auto-generado por Laravel
            // excede los 64 chars que MySQL permite para identifiers.
            $table->foreign('suggested_parent_id', 'mcr_suggested_parent_fk')
                  ->references('id')->on('marketplace_categories')
                  ->nullOnDelete();
            $table->foreign('created_marketplace_category_id', 'mcr_created_cat_fk')
                  ->references('id')->on('marketplace_categories')
                  ->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('marketplace_category_requests');
    }
}
