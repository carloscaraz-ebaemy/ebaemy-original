<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('configuration_ecommerce')) return;

        Schema::table('configuration_ecommerce', function (Blueprint $table) {
            if (!Schema::hasColumn('configuration_ecommerce', 'notification_interval')) {
                $table->unsignedInteger('notification_interval')->default(5)->after('phone_whatsapp');
            }
            if (!Schema::hasColumn('configuration_ecommerce', 'notify_new_order')) {
                $table->boolean('notify_new_order')->default(true)->after('notification_interval');
            }
            if (!Schema::hasColumn('configuration_ecommerce', 'notify_pending_reminder')) {
                $table->boolean('notify_pending_reminder')->default(true)->after('notify_new_order');
            }
            if (!Schema::hasColumn('configuration_ecommerce', 'notify_order_confirmed')) {
                $table->boolean('notify_order_confirmed')->default(true)->after('notify_pending_reminder');
            }
            if (!Schema::hasColumn('configuration_ecommerce', 'notify_customer_order')) {
                $table->boolean('notify_customer_order')->default(true)->after('notify_order_confirmed');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('configuration_ecommerce')) return;
        Schema::table('configuration_ecommerce', function (Blueprint $table) {
            $cols = ['notification_interval', 'notify_new_order', 'notify_pending_reminder', 'notify_order_confirmed', 'notify_customer_order'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('configuration_ecommerce', $col)) $table->dropColumn($col);
            }
        });
    }
};
