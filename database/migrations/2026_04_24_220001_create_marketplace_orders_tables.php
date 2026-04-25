<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketplace Fase 2 — pedido multi-tienda.
 *
 * Crea el esqueleto en BD CENTRAL para soportar carritos con productos de
 * múltiples sellers. Cada compra desde ebaemy.com/marketplace genera:
 *
 *   marketplace_orders         → cabecera del pedido del comprador
 *   marketplace_order_items    → líneas (snapshot del listing al momento)
 *   tenant_marketplace_orders  → puente 1↔N: un subpedido por tienda involucrada,
 *                                 con FK opcional al Order ya creado dentro del tenant
 *
 * NO toca BDs de tenants. El despacho a cada tenant lo hace
 * MarketplaceMultiOrderDispatcher (reusa el patrón de MarketplaceOrderDispatcher
 * existente para el flujo lead 1-item).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_orders')) {
            Schema::create('marketplace_orders', function (Blueprint $table) {
                $table->id();
                $table->string('order_number', 40)->unique()->comment('humano: MP-2026-0001234');

                // Comprador
                $table->string('customer_name', 180);
                $table->string('customer_doc_type', 12)->nullable()->comment('DNI|RUC|CE');
                $table->string('customer_doc_number', 20)->nullable();
                $table->string('customer_phone', 40);
                $table->string('customer_email', 180)->nullable();

                // Entrega
                $table->text('delivery_address');
                $table->string('delivery_department', 80)->nullable();
                $table->string('delivery_province', 80)->nullable();
                $table->string('delivery_district', 80)->nullable();
                $table->text('delivery_notes')->nullable();

                // Totales
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('total', 12, 2)->default(0);
                $table->unsignedSmallInteger('items_count')->default(0);
                $table->unsignedSmallInteger('stores_count')->default(0);

                // Estados
                $table->string('status', 32)->default('pending')
                    ->comment('pending|partially_confirmed|confirmed|partially_cancelled|completed|cancelled');
                $table->string('payment_status', 32)->default('unpaid')
                    ->comment('unpaid|paid|refunded — fase 1 todo en unpaid');
                $table->string('source', 32)->default('web');
                $table->string('session_token', 64)->nullable()
                    ->comment('para que un guest pueda volver a ver su pedido');

                // Telemetría
                $table->string('source_ip', 64)->nullable();
                $table->string('source_ua', 255)->nullable();

                $table->timestamps();

                $table->index('status');
                $table->index('created_at');
                $table->index('customer_phone');
                $table->index('customer_email');
            });
        }

        if (!Schema::hasTable('marketplace_order_items')) {
            Schema::create('marketplace_order_items', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('marketplace_order_id');
                $table->unsignedBigInteger('listing_id')->nullable()
                    ->comment('FK marketplace_listings — null si el listing fue borrado entre add y checkout');
                $table->unsignedBigInteger('hostname_id');
                $table->string('tenant_fqdn', 180);
                $table->unsignedBigInteger('remote_item_id');

                // Snapshot al momento del checkout
                $table->string('title', 250);
                $table->string('slug', 250)->nullable();
                $table->string('image_url', 500)->nullable();
                $table->decimal('unit_price', 12, 2);
                $table->unsignedInteger('quantity');
                $table->decimal('total', 12, 2);

                $table->timestamps();

                $table->foreign('marketplace_order_id')
                    ->references('id')->on('marketplace_orders')
                    ->onDelete('cascade');

                $table->index('hostname_id');
                $table->index('listing_id');
            });
        }

        if (!Schema::hasTable('tenant_marketplace_orders')) {
            Schema::create('tenant_marketplace_orders', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('marketplace_order_id');
                $table->unsignedBigInteger('hostname_id');
                $table->string('tenant_fqdn', 180);
                $table->unsignedBigInteger('client_id')->nullable();

                $table->decimal('subtotal', 12, 2)->default(0);
                $table->unsignedSmallInteger('item_count')->default(0);

                // FK al Order creado dentro de la BD del tenant (si el dispatch
                // tuvo éxito). No es FK física porque vive en otra base.
                $table->unsignedBigInteger('tenant_order_id')->nullable();
                $table->string('tenant_order_external_id', 64)->nullable()
                    ->comment('uuid del Order del tenant — útil para reconciliación');

                $table->string('status', 32)->default('pending')
                    ->comment('pending|dispatched|failed|cancelled|delivered');
                $table->text('sync_error')->nullable();
                $table->unsignedTinyInteger('retry_count')->default(0);
                $table->timestamp('dispatched_at')->nullable();

                $table->timestamps();

                $table->foreign('marketplace_order_id')
                    ->references('id')->on('marketplace_orders')
                    ->onDelete('cascade');

                $table->index(['marketplace_order_id', 'hostname_id']);
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_marketplace_orders');
        Schema::dropIfExists('marketplace_order_items');
        Schema::dropIfExists('marketplace_orders');
    }
};
