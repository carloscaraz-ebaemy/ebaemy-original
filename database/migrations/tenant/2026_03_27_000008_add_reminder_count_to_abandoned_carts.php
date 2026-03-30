<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('abandoned_carts')) return;
        if (Schema::hasColumn('abandoned_carts', 'reminder_count')) return;
        Schema::table('abandoned_carts', function (Blueprint $table) {
            $table->tinyInteger('reminder_count')->default(0)->after('reminder_sent_at');
            $table->timestamp('last_reminder_at')->nullable()->after('reminder_count');
            $table->string('discount_code', 50)->nullable()->after('last_reminder_at');
        });
    }
    public function down(): void
    {
        if (!Schema::hasTable('abandoned_carts')) return;
        Schema::table('abandoned_carts', function (Blueprint $table) {
            $table->dropColumn(['reminder_count', 'last_reminder_at', 'discount_code']);
        });
    }
};
