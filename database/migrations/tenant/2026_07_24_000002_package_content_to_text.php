<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Detalle del producto" (package_content) pasa de varchar(255) a TEXT para
 * permitir una descripción multilínea del contenido del paquete que redacta el
 * personal de almacén. Campo interno: nunca se pide ni se muestra al cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('tenant')->hasTable('shipping_requests')
            || !Schema::connection('tenant')->hasColumn('shipping_requests', 'package_content')) {
            return;
        }
        // change() requiere doctrine/dbal; con SQL directo evitamos esa dependencia.
        DB::connection('tenant')->statement('ALTER TABLE `shipping_requests` MODIFY `package_content` TEXT NULL');
    }

    public function down(): void
    {
        if (Schema::connection('tenant')->hasTable('shipping_requests')
            && Schema::connection('tenant')->hasColumn('shipping_requests', 'package_content')) {
            DB::connection('tenant')->statement('ALTER TABLE `shipping_requests` MODIFY `package_content` VARCHAR(255) NULL');
        }
    }
};
