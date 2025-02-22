<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTestInstancesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('test_instances', function (Blueprint $table) {
            $table->id(); // Crea una columna "id" BIGINT UNSIGNED auto incrementable
            $table->unsignedBigInteger('user_id');
            $table->string('name', 255);
            $table->string('phone_number', 20)->nullable();
            $table->enum('status', [
                'active',
                'disconnected',
                'connecting',
                'initializing',
                'created',
                'qr_ready',
                'refused',
                'inactive'
            ])->default('disconnected');
            $table->text('qr_code')->nullable();
            $table->timestamps();

            // Agregar la clave foránea a "users"
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('test_instances');
    }
}
