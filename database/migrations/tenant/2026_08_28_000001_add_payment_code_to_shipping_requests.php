<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Código de pago (Yape/Plin/operación bancaria) del envío.
 *
 * Al confirmar el pago el encargado escribe el código de la operación. Sirve
 * para detectar el mismo comprobante usado dos veces: no se pone UNIQUE en BD
 * a propósito (hay datos históricos sin código y la excepción administrativa
 * debe poder registrarse), la validación vive en el controlador.
 * Idempotente por columna.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('tenant')->hasTable('shipping_requests')) {
            return;
        }

        Schema::connection('tenant')->table('shipping_requests', function (Blueprint $table) {
            $has = fn ($c) => Schema::connection('tenant')->hasColumn('shipping_requests', $c);
            if (!$has('payment_code')) {
                $table->string('payment_code', 60)->nullable()->after('payment_confirmed_at');
            }
            if (!$has('payment_code_normalized')) {
                // Versión normalizada (mayúsculas, sin espacios ni guiones) que
                // es la que realmente se compara: los códigos se copian a mano.
                $table->string('payment_code_normalized', 60)->nullable()->after('payment_code');
            }
        });

        // Se comprueba por COLUMNA, no por nombre: `shipping:install` puede
        // haber creado ya el índice con el nombre por defecto y quedarían dos.
        if (!$this->hasIndexOnColumn('payment_code_normalized')) {
            Schema::connection('tenant')->table('shipping_requests', function (Blueprint $table) {
                $table->index('payment_code_normalized');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::connection('tenant')->hasTable('shipping_requests')) {
            return;
        }

        Schema::connection('tenant')->table('shipping_requests', function (Blueprint $table) {
            // El índice cae junto con su columna en MySQL.
            foreach (['payment_code', 'payment_code_normalized'] as $c) {
                if (Schema::connection('tenant')->hasColumn('shipping_requests', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }

    private function hasIndexOnColumn(string $column): bool
    {
        try {
            $rows = \Illuminate\Support\Facades\DB::connection('tenant')
                ->select('SHOW INDEX FROM `shipping_requests` WHERE Column_name = ?', [$column]);
            return count($rows) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
};
