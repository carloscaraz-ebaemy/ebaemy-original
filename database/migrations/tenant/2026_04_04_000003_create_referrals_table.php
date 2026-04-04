<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referrer_user_id')->comment('Usuario que refiere');
            $table->unsignedBigInteger('referred_user_id')->nullable()->comment('Usuario referido');
            $table->string('referral_code', 20)->unique();
            $table->string('status', 20)->default('pending'); // pending, completed, rewarded
            $table->unsignedBigInteger('referrer_coupon_id')->nullable();
            $table->unsignedBigInteger('referred_coupon_id')->nullable();
            $table->timestamps();

            $table->index('referrer_user_id');
            $table->index('referral_code');
        });

        // Agregar referral_code a persons (clientes ecommerce)
        if (!Schema::hasColumn('persons', 'referral_code')) {
            Schema::table('persons', function (Blueprint $table) {
                $table->string('referral_code', 20)->nullable()->unique()->after('email');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
        if (Schema::hasColumn('persons', 'referral_code')) {
            Schema::table('persons', function (Blueprint $table) {
                $table->dropColumn('referral_code');
            });
        }
    }
};
