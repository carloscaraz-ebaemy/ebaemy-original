<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_price_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('item_id');
            $table->decimal('old_price', 12, 2);
            $table->decimal('new_price', 12, 2);
            $table->string('changed_by', 100)->nullable()->comment('user email or system');
            $table->string('source', 30)->default('manual')->comment('manual, import, promotion');
            $table->timestamp('created_at')->useCurrent();

            $table->index('item_id');
            $table->index('created_at');

            $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_price_history');
    }
};
