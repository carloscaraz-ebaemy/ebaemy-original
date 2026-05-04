<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Shipping zones — costo de envío por distrito.
 *
 * Tabla `shipping_zones` agrupa distritos con un costo y tiempo estimado.
 * Seed mínimo: Lima Metropolitana, Provincias, Retiro en tienda (gratis).
 * Order recibe shipping_cost y shipping_zone_id para trazabilidad.
 */
class CreateShippingZonesAndOrderFields extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('shipping_zones')) {
            Schema::create('shipping_zones', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 80);
                $table->decimal('cost', 10, 2)->default(0);
                $table->unsignedSmallInteger('estimated_days')->default(2);
                // district_ids codificados en JSON (hasta ~200 distritos por zona);
                // si el distrito del cliente matchea, aplica el costo. Vacío = default.
                $table->json('district_ids')->nullable();
                $table->boolean('is_default')->default(false)
                      ->comment('Zona por defecto cuando el distrito no matchea ninguna zona');
                $table->boolean('is_pickup')->default(false)
                      ->comment('Marca zona de recojo en tienda (cost=0)');
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });

            // Seed mínimo — el admin puede ajustar costos después.
            // Las 3 filas TIENEN QUE traer las mismas claves; en batch insert,
            // Laravel arma el SQL con las keys del primer row y MySQL falla
            // con "Column count doesn't match value count" si las siguientes
            // filas no las repiten todas.
            $now = now();
            DB::table('shipping_zones')->insert([
                [
                    'name'           => 'Recojo en tienda',
                    'cost'           => 0,
                    'estimated_days' => 0,
                    'district_ids'   => null,
                    'is_default'     => false,
                    'is_pickup'      => true,
                    'is_active'      => true,
                    'sort_order'     => 0,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ],
                [
                    'name'           => 'Lima Metropolitana',
                    'cost'           => 10.00,
                    'estimated_days' => 1,
                    // Se poblará desde el panel admin con district_ids específicos
                    'district_ids'   => null,
                    'is_default'     => false,
                    'is_pickup'      => false,
                    'is_active'      => true,
                    'sort_order'     => 1,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ],
                [
                    'name'           => 'Provincias',
                    'cost'           => 25.00,
                    'estimated_days' => 5,
                    'district_ids'   => null,
                    'is_default'     => true,  // fallback cuando el distrito no matchea
                    'is_pickup'      => false,
                    'is_active'      => true,
                    'sort_order'     => 2,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ],
            ]);
        }

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'shipping_cost')) {
                $table->decimal('shipping_cost', 10, 2)->default(0)->after('total');
            }
            if (!Schema::hasColumn('orders', 'shipping_zone_id')) {
                $table->unsignedBigInteger('shipping_zone_id')->nullable()->after('shipping_cost');
            }
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['shipping_cost', 'shipping_zone_id']);
        });
        Schema::dropIfExists('shipping_zones');
    }
}
