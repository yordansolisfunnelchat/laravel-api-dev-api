<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            // Añadir el campo sync_attempts si no existe
            if (!Schema::hasColumn('agents', 'sync_attempts')) {
                $table->unsignedInteger('sync_attempts')->default(0);
            }
            
            // Modificar el campo sync_status para aceptar los nuevos estados
            if (Schema::hasColumn('agents', 'sync_status')) {
                // Solo si estamos usando MySQL, que permite modificar enums
                if (DB::connection()->getDriverName() === 'mysql') {
                    DB::statement("ALTER TABLE agents MODIFY COLUMN sync_status ENUM('pending', 'pending_async', 'pending_retry', 'sync_in_progress', 'retrying', 'synced', 'failed') DEFAULT 'pending'");
                } else {
                    // Para otros motores de DB, podríamos cambiar a string
                    $table->string('sync_status')->default('pending')->change();
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            if (Schema::hasColumn('agents', 'sync_attempts')) {
                $table->dropColumn('sync_attempts');
            }
            
            // Revertir el campo sync_status (solo en MySQL)
            if (DB::connection()->getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE agents MODIFY COLUMN sync_status ENUM('pending', 'synced', 'failed') DEFAULT 'pending'");
            }
        });
    }
};