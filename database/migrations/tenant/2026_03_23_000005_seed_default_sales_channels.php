<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed de canales de venta por defecto.
 *
 * Se ejecuta como migración (no seeder) para garantizar que todos los tenants
 * lo reciban automáticamente al correr php artisan migrate.
 *
 * Los canales son activables/desactivables desde el panel de administración.
 */
class SeedDefaultSalesChannels extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('sales_channels')) return;

        // Obtener el primer almacén disponible (warehouse central)
        $defaultWarehouseId = DB::connection('tenant')->table('warehouses')->value('id');

        $channels = [
            [
                'name'         => 'Tienda Online',
                'type'         => 'ecommerce',
                'code'         => 'ECOM',
                'warehouse_id' => $defaultWarehouseId,
                'is_active'    => true,
                'settings'     => json_encode(['icon' => '🛒', 'color' => '#6366f1']),
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            [
                'name'         => 'Punto de Venta',
                'type'         => 'pos',
                'code'         => 'POS01',
                'warehouse_id' => $defaultWarehouseId,
                'is_active'    => true,
                'settings'     => json_encode(['icon' => '🏪', 'color' => '#10b981']),
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            [
                'name'         => 'Ventas WhatsApp',
                'type'         => 'whatsapp',
                'code'         => 'WHA01',
                'warehouse_id' => $defaultWarehouseId,
                'is_active'    => false, // desactivado por defecto
                'settings'     => json_encode(['icon' => '💬', 'color' => '#25d366']),
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            [
                'name'         => 'Ventas Telefónicas',
                'type'         => 'phone',
                'code'         => 'TEL01',
                'warehouse_id' => $defaultWarehouseId,
                'is_active'    => false,
                'settings'     => json_encode(['icon' => '📞', 'color' => '#f59e0b']),
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
        ];

        foreach ($channels as $channel) {
            // Evitar duplicados en re-ejecuciones
            if (!DB::connection('tenant')->table('sales_channels')->where('code', $channel['code'])->exists()) {
                DB::connection('tenant')->table('sales_channels')->insert($channel);
            }
        }
    }

    public function down()
    {
        DB::connection('tenant')->table('sales_channels')->whereIn('code', ['ECOM', 'POS01', 'WHA01', 'TEL01'])->delete();
    }
}
