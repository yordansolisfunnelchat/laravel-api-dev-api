<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('instance_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('custom_instructions')->nullable();
            $table->enum('activation_mode', ['always', 'keywords'])->default('always');
            $table->json('keywords')->nullable();
            $table->boolean('status')->default(true);
            $table->text('pause_condition')->nullable();
            $table->boolean('has_waiting_time')->default(false);
            $table->enum('sync_status', ['pending', 'synced', 'failed'])->default('pending');
            $table->text('sync_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};



/// Codigo ORIGINAL 👌🏽
// use Illuminate\Database\Migrations\Migration;
// use Illuminate\Database\Schema\Blueprint;
// use Illuminate\Support\Facades\Schema;

// return new class extends Migration
// {
//     public function up(): void
//     {
//         Schema::create('agents', function (Blueprint $table) {
//             $table->id();
//             $table->foreignId('user_id')->constrained()->onDelete('cascade');
//             $table->foreignId('instance_id')->constrained()->onDelete('cascade');
//             $table->string('name');
//             $table->text('custom_instructions')->nullable();
//             $table->timestamps();
//         });
//     }

//     public function down(): void
//     {
//         Schema::dropIfExists('agents');
//     }
// };