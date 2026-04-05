<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_reports', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('report_type', 50)->comment('daily_sales, weekly_summary, monthly_kpis, stock_alert, top_products');
            $table->string('frequency', 20)->comment('daily, weekly, monthly');
            $table->string('send_to', 500)->comment('Emails separados por coma');
            $table->string('send_time', 5)->default('08:00')->comment('HH:mm');
            $table->unsignedTinyInteger('send_day')->nullable()->comment('1-7 para weekly, 1-28 para monthly');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_reports');
    }
};
