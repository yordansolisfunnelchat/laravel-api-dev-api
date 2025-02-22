<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            // Agregamos los campos faltantes uno por uno
            if (!Schema::hasColumn('agents', 'activation_mode')) {
                $table->string('activation_mode')->default('always');
            }
            
            if (!Schema::hasColumn('agents', 'keywords')) {
                $table->json('keywords')->nullable();
            }
            
            if (!Schema::hasColumn('agents', 'status')) {
                $table->boolean('status')->default(true);
            }
            
            if (!Schema::hasColumn('agents', 'pause_condition')) {
                $table->text('pause_condition')->nullable();
            }
            
            if (!Schema::hasColumn('agents', 'has_waiting_time')) {
                $table->boolean('has_waiting_time')->default(false);
            }
            
            if (!Schema::hasColumn('agents', 'sync_status')) {
                $table->string('sync_status')->default('pending');
            }
            
            if (!Schema::hasColumn('agents', 'sync_error')) {
                $table->text('sync_error')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn([
                'activation_mode',
                'keywords',
                'status',
                'pause_condition',
                'has_waiting_time',
                'sync_status',
                'sync_error'
            ]);
        });
    }
};
