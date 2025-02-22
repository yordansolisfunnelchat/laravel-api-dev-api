<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            // Primero, creamos una nueva columna temporal
            $table->json('payload_json')->nullable()->after('payload');

            // Eliminamos la columna antigua
            $table->dropColumn('payload');

            // Renombramos la nueva columna
            $table->renameColumn('payload_json', 'payload');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            // Si necesitas revertir, puedes convertir de nuevo a texto
            $table->text('payload_text')->nullable()->after('payload');
            
            
            $table->dropColumn('payload');
            $table->renameColumn('payload_text', 'payload');
        });
    }
};