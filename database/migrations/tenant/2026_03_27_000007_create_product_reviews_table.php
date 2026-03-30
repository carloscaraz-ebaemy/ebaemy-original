<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('product_reviews')) return;
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('person_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('reviewer_name', 150);
            $table->string('reviewer_email', 200)->nullable();
            $table->tinyInteger('rating')->unsigned(); // 1-5
            $table->string('title', 255)->nullable();
            $table->text('body')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->boolean('verified_purchase')->default(false);
            $table->text('admin_reply')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['item_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('product_reviews'); }
};
