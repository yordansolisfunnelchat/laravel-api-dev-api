<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Modificar el enum para incluir 'system'
        DB::statement("ALTER TABLE messages MODIFY COLUMN sender ENUM('agent', 'customer', 'system') NOT NULL");
    }

    public function down()
    {
        DB::statement("ALTER TABLE messages MODIFY COLUMN sender ENUM('agent', 'customer') NOT NULL");
    }
};