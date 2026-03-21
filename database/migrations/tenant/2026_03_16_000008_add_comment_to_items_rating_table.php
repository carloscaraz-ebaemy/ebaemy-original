<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCommentToItemsRatingTable extends Migration
{
    public function up()
    {
        Schema::table('items_rating', function (Blueprint $table) {
            $table->string('reviewer_name')->nullable()->after('value');
            $table->text('comment')->nullable()->after('reviewer_name');
        });
    }

    public function down()
    {
        Schema::table('items_rating', function (Blueprint $table) {
            $table->dropColumn(['reviewer_name', 'comment']);
        });
    }
}
