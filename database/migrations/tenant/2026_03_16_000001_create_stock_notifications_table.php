<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id')->index();
            $table->string('email', 180)->index();
            $table->string('name', 120)->nullable();
            $table->boolean('notified')->default(false);
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['item_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_notifications');
    }
};
