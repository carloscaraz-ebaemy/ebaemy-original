<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Un SellerSku sólo puede apuntar a UN producto dentro del mismo canal.
 *
 * Sin este índice nada impedía dos enlaces con el mismo `external_sku` en el
 * mismo canal: la importación busca el enlace por SKU con `first()`, así que un
 * duplicado deja al segundo producto en un limbo (nunca se actualiza) sin error.
 *
 * Idempotente y NO destructiva: si el tenant ya tiene duplicados, NO borra nada
 * y NO crea el índice — los deja anotados en el log para revisarlos a mano,
 * porque cuál de los dos enlaces es el bueno no se puede adivinar aquí.
 */
return new class extends Migration
{
    const INDEX = 'marketplace_products_channel_sku_unique';

    public function up(): void
    {
        // Conexión EXPLÍCITA del tenant: `Schema::getConnection()` devuelve la
        // conexión por defecto (la BD del sistema), así que el guard de
        // duplicados miraba la tabla equivocada y el índice se creaba donde no era.
        $schema = Schema::connection('tenant');
        if (!$schema->hasTable('marketplace_products')) {
            return;
        }

        $conn = $schema->getConnection();

        $exists = !empty($conn->select(
            'SHOW INDEX FROM marketplace_products WHERE Key_name = ?',
            [self::INDEX]
        ));
        if ($exists) {
            return;
        }

        $duplicates = $conn->table('marketplace_products')
            ->selectRaw('channel_id, external_sku, COUNT(*) as total')
            ->whereNotNull('external_sku')
            ->where('external_sku', '!=', '')
            ->groupBy('channel_id', 'external_sku')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            $detail = $duplicates->map(fn($d) => "canal {$d->channel_id} / sku {$d->external_sku} (x{$d->total})")
                ->implode('; ');

            Log::warning("marketplace_products: no se pudo crear el índice único, hay duplicados: {$detail}");
            if (app()->runningInConsole()) {
                echo "  ! marketplace_products tiene enlaces duplicados; índice único NO creado: {$detail}\n";
            }

            return;
        }

        $schema->table('marketplace_products', function (Blueprint $table) {
            $table->unique(['channel_id', 'external_sku'], self::INDEX);
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');
        if (!$schema->hasTable('marketplace_products')) {
            return;
        }

        $exists = !empty($schema->getConnection()->select(
            'SHOW INDEX FROM marketplace_products WHERE Key_name = ?',
            [self::INDEX]
        ));
        if (!$exists) {
            return;
        }

        $schema->table('marketplace_products', function (Blueprint $table) {
            $table->dropUnique(self::INDEX);
        });
    }
};
