<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega qr_api_instance a system.configurations.
 * Necesario para Evolution API v2 que requiere nombre de instancia
 * en el endpoint: POST {url}/message/sendText/{instance}
 *
 * Si el gateway es legacy (devaemy.com tipo) y no usa instance,
 * dejar este campo NULL y el service hará fallback al endpoint viejo.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('configurations')) {
            return;
        }
        if (Schema::hasColumn('configurations', 'qr_api_instance')) {
            return;
        }
        Schema::table('configurations', function (Blueprint $table) {
            $table->string('qr_api_instance', 100)->nullable()
                ->after('qr_api_token')
                ->comment('Nombre de instancia Evolution API (NULL si gateway legacy)');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('configurations') && Schema::hasColumn('configurations', 'qr_api_instance')) {
            Schema::table('configurations', function (Blueprint $table) {
                $table->dropColumn('qr_api_instance');
            });
        }
    }
};
