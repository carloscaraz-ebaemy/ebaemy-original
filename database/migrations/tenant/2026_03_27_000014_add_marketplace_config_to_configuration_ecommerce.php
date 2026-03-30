<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('configuration_ecommerce')) return;
        if (Schema::hasColumn('configuration_ecommerce', 'marketplace_config')) return;

        Schema::table('configuration_ecommerce', function (Blueprint $table) {
            $table->text('marketplace_config')->nullable()->after('theme_template');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('configuration_ecommerce')) return;
        if (!Schema::hasColumn('configuration_ecommerce', 'marketplace_config')) return;
        Schema::table('configuration_ecommerce', function (Blueprint $table) {
            $table->dropColumn('marketplace_config');
        });
    }
};
