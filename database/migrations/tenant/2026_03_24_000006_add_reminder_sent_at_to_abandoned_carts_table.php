<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('abandoned_carts')) return;
        if (Schema::hasColumn('abandoned_carts', 'reminder_sent_at')) return;

        Schema::table('abandoned_carts', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->after('recovered_at');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('abandoned_carts')) return;

        Schema::table('abandoned_carts', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
