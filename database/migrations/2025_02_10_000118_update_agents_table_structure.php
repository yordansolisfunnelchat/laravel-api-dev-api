<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            // Primero eliminamos las columnas si existen para evitar errores
            $table->dropColumn([
                'has_waiting_time',
                'sync_status',
                'sync_error'
            ]);
        });

        Schema::table('agents', function (Blueprint $table) {
            // Agregamos las nuevas columnas
            $table->boolean('has_waiting_time')->default(false);
            $table->enum('sync_status', ['pending', 'synced', 'failed'])->default('pending');
            $table->text('sync_error')->nullable();
            
            // Modificamos las columnas existentes si es necesario
            if (!Schema::hasColumn('agents', 'status')) {
                $table->boolean('status')->default(true);
            }
            if (!Schema::hasColumn('agents', 'keywords')) {
                $table->json('keywords')->nullable();
            }
            if (!Schema::hasColumn('agents', 'pause_condition')) {
                $table->text('pause_condition')->nullable();
            }
            if (!Schema::hasColumn('agents', 'activation_mode')) {
                $table->enum('activation_mode', ['always', 'keywords'])->default('always');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn([
                'has_waiting_time',
                'sync_status',
                'sync_error'
            ]);
        });
    }
};