<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->boolean('has_waiting_time')->default(false);
            $table->boolean('status')->default(true)->change(); // Por si no existe
            $table->json('keywords')->nullable()->change(); // Por si no existe
            $table->text('pause_condition')->nullable()->change(); // Por si no existe
            $table->enum('activation_mode', ['always', 'keywords'])->default('always')->change(); // Por si no existe
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('has_waiting_time');
        });
    }
};