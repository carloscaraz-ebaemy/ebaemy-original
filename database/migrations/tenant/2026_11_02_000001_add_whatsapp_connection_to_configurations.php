<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp conectado por QR (Evolution API, como el Pro 9): la instancia de la
 * tienda y el número que quedó vinculado. Mismos nombres que el Pro 9 para que
 * portar entre los dos no exija traducir columnas.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('configurations', function (Blueprint $table) {
            if (!Schema::hasColumn('configurations', 'qr_api_instance')) {
                $table->string('qr_api_instance')->nullable();
            }
            if (!Schema::hasColumn('configurations', 'qr_api_connected_phone')) {
                $table->string('qr_api_connected_phone', 30)->nullable();
            }
            if (!Schema::hasColumn('configurations', 'qr_api_profile_name')) {
                $table->string('qr_api_profile_name')->nullable();
            }
            if (!Schema::hasColumn('configurations', 'qr_api_connection_state')) {
                $table->string('qr_api_connection_state', 20)->default('disconnected');
            }
            if (!Schema::hasColumn('configurations', 'qr_api_connected_at')) {
                $table->timestamp('qr_api_connected_at')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('configurations', function (Blueprint $table) {
            foreach (['qr_api_instance', 'qr_api_connected_phone', 'qr_api_profile_name', 'qr_api_connection_state', 'qr_api_connected_at'] as $c) {
                if (Schema::hasColumn('configurations', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
