<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `marketplace_channels.credentials` se creó como columna `json` pero el modelo
 * la castea `encrypted:array`. El valor encriptado (base64) NO es JSON válido,
 * por lo que MySQL 8 rechaza el INSERT/UPDATE con:
 *   "Invalid JSON text: Invalid value" (SQLSTATE 22032 / 3140).
 *
 * Resultado: al guardar las credenciales de un marketplace (Saga, MELI, etc.)
 * la escritura fallaba silenciosamente. Cambiamos la columna a TEXT.
 *
 * Se usa ALTER crudo porque ->change() con doctrine/dbal no altera bien las
 * columnas JSON (reporta DONE pero deja el tipo intacto).
 *
 * Idempotente: solo altera si el tipo actual no es ya `text`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_channels')) {
            return;
        }

        if ($this->currentType() !== 'text') {
            DB::statement('ALTER TABLE `marketplace_channels` MODIFY `credentials` TEXT NULL');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('marketplace_channels')) {
            return;
        }

        if ($this->currentType() !== 'json') {
            DB::statement('ALTER TABLE `marketplace_channels` MODIFY `credentials` JSON NULL');
        }
    }

    private function currentType(): ?string
    {
        $row = DB::selectOne(
            "SELECT DATA_TYPE AS dt FROM information_schema.COLUMNS
             WHERE TABLE_NAME = 'marketplace_channels'
               AND COLUMN_NAME = 'credentials'
               AND TABLE_SCHEMA = DATABASE()"
        );

        return $row ? strtolower($row->dt) : null;
    }
};
