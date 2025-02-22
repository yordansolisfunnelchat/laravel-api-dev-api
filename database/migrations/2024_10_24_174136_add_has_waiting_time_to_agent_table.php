<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->boolean('has_waiting_time')->default(true)->after('pause_condition');
        });
    }

    public function down()
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('has_waiting_time');
        });
    }
};