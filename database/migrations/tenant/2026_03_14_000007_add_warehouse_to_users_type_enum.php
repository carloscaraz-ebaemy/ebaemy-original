<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddWarehouseToUsersTypeEnum extends Migration
{
    public function up()
    {
        DB::connection('tenant')->statement(
            "ALTER TABLE users MODIFY COLUMN type ENUM('admin', 'seller', 'warehouse') NOT NULL DEFAULT 'admin'"
        );
    }

    public function down()
    {
        DB::connection('tenant')->statement(
            "ALTER TABLE users MODIFY COLUMN type ENUM('admin', 'seller') NOT NULL DEFAULT 'admin'"
        );
    }
}
