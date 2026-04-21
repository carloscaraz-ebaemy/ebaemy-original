<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leads/solicitudes generados desde el marketplace central. Se crean cuando el
 * cliente final pide comprar o solicitar información de un producto. En Fase 2
 * el lead se convierte en una Order dentro del tenant dueño del producto
 * usando su canal de venta `marketplace`.
 */
class CreateMarketplaceLeadsTable extends Migration
{
    public function up()
    {
        Schema::create('marketplace_leads', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('listing_id')->index();
            $table->unsignedInteger('hostname_id')->nullable()->index();
            $table->string('tenant_fqdn', 180);
            $table->unsignedInteger('remote_item_id');

            $table->string('customer_name', 180);
            $table->string('customer_phone', 40)->nullable();
            $table->string('customer_email', 180)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->text('message')->nullable();

            // Snapshot del precio/título al momento del lead
            $table->string('snapshot_title', 255)->nullable();
            $table->decimal('snapshot_price', 12, 2)->nullable();

            $table->enum('status', ['new', 'sent_to_tenant', 'converted', 'archived', 'failed'])
                  ->default('new')
                  ->index();
            $table->string('tenant_order_external_id', 64)->nullable()
                  ->comment('external_id de la Order creada en el tenant');
            $table->string('sync_error', 500)->nullable();

            $table->ipAddress('source_ip')->nullable();
            $table->string('source_ua', 255)->nullable();

            $table->timestamps();

            $table->foreign('listing_id')
                  ->references('id')->on('marketplace_listings')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('marketplace_leads');
    }
}
