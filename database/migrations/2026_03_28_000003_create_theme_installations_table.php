<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instalaciones de themes por tenant (BD sistema).
 * Para marketplace: tracking de compras, licencias, versiones.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('theme_installations')) return;

        Schema::create('theme_installations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('theme_id');
            $table->unsignedBigInteger('hostname_id');      // Tenant que instaló
            $table->string('version', 20)->default('1.0.0');
            $table->enum('status', ['active', 'inactive', 'expired'])->default('active');
            $table->json('custom_settings')->nullable();     // Personalizaciones del tenant
            $table->string('license_key', 64)->nullable();   // Para themes premium
            $table->timestamp('installed_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();     // Licencia con vencimiento
            $table->timestamps();

            $table->unique(['theme_id', 'hostname_id']);
            $table->index('hostname_id');
            $table->index('status');

            $table->foreign('theme_id')->references('id')->on('themes')->onDelete('cascade');
        });

        // Agregar campos de marketplace a la tabla themes existente
        if (Schema::hasTable('themes') && !Schema::hasColumn('themes', 'version')) {
            Schema::table('themes', function (Blueprint $table) {
                $table->string('version', 20)->default('1.0.0')->after('sort_order');
                $table->string('author', 100)->nullable()->after('version');
                $table->decimal('price', 8, 2)->default(0)->after('author');
                $table->json('default_settings')->nullable()->after('price');
                $table->json('supported_modes')->nullable()->after('default_settings');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_installations');

        if (Schema::hasTable('themes')) {
            Schema::table('themes', function (Blueprint $table) {
                $cols = ['version', 'author', 'price', 'default_settings', 'supported_modes'];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('themes', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
