<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El checkout del marketplace guarda lo que el comprador ELIGIÓ para recibir el
 * pedido, en vez de recalcularlo después con la política vigente de cada tienda.
 *
 * Base `system` (carpeta raíz de migraciones). Todo aditivo y nullable: los
 * pedidos anteriores quedan intactos y la confirmación sigue leyéndolos con el
 * cálculo antiguo mientras estas columnas estén en NULL.
 *
 *  - marketplace_orders: el DESTINO (uno por compra) y el total de envío.
 *  - tenant_marketplace_orders: la MODALIDAD de cada tienda (recojo, delivery
 *    Lima o agencia a provincia), su costo, su plazo y la oficina de Shalom.
 *    Va aquí y no en el padre porque cada tienda tiene su política y el
 *    comprador puede recoger en una y recibir por agencia en otra.
 *  - marketplace_users.checkout_profile: los últimos datos de entrega, para no
 *    reescribirlos en la siguiente compra.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('marketplace_orders')) {
            Schema::table('marketplace_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('marketplace_orders', 'delivery_ubigeo')) {
                    $table->string('delivery_ubigeo', 6)->nullable()->after('delivery_district')
                        ->comment('INEI de 6 cifras del distrito de entrega; NULL si todo es recojo');
                }
                if (!Schema::hasColumn('marketplace_orders', 'shipping_total')) {
                    $table->decimal('shipping_total', 12, 2)->nullable()->after('discount_total')
                        ->comment('Suma del envío ya fijado; NULL en pedidos anteriores al selector');
                }
                if (!Schema::hasColumn('marketplace_orders', 'shipping_pending')) {
                    $table->boolean('shipping_pending')->default(false)->after('shipping_total')
                        ->comment('Hay envío que la tienda confirma por WhatsApp');
                }
            });
        }

        if (Schema::hasTable('tenant_marketplace_orders')) {
            Schema::table('tenant_marketplace_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('tenant_marketplace_orders', 'shipping_mode')) {
                    $table->string('shipping_mode', 12)->nullable()
                        ->comment('pickup | delivery (Lima/Callao) | agency (provincia)');
                    $table->decimal('shipping_cost', 10, 2)->nullable();
                    $table->boolean('shipping_pending')->default(false);
                    $table->boolean('shipping_has_policy')->default(false)
                        ->comment('La tienda tenía política de envío confirmada al comprar');
                    $table->string('shipping_eta', 120)->nullable();
                    $table->string('shipping_label', 255)->nullable()
                        ->comment('Frase completa que el comprador vio antes de pagar');
                    $table->unsignedInteger('agency_office_id')->nullable()
                        ->comment('ter_id de shalom_agencies');
                    $table->string('agency_office_name', 180)->nullable();
                    $table->string('agency_office_address', 500)->nullable();
                }
            });
        }

        if (Schema::hasTable('marketplace_users') && !Schema::hasColumn('marketplace_users', 'checkout_profile')) {
            Schema::table('marketplace_users', function (Blueprint $table) {
                $table->json('checkout_profile')->nullable()->after('timezone');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('marketplace_users') && Schema::hasColumn('marketplace_users', 'checkout_profile')) {
            Schema::table('marketplace_users', fn (Blueprint $t) => $t->dropColumn('checkout_profile'));
        }
        if (Schema::hasColumn('tenant_marketplace_orders', 'shipping_mode')) {
            Schema::table('tenant_marketplace_orders', fn (Blueprint $t) => $t->dropColumn([
                'shipping_mode', 'shipping_cost', 'shipping_pending', 'shipping_has_policy', 'shipping_eta',
                'shipping_label', 'agency_office_id', 'agency_office_name', 'agency_office_address',
            ]));
        }
        if (Schema::hasColumn('marketplace_orders', 'delivery_ubigeo')) {
            Schema::table('marketplace_orders', fn (Blueprint $t) => $t->dropColumn(['delivery_ubigeo', 'shipping_total', 'shipping_pending']));
        }
    }
};
