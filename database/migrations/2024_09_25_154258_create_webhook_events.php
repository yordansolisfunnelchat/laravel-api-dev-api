<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('instance_name');
            $table->string('event_type');
            $table->json('payload');
            $table->boolean('processed')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('instance_name');
            $table->index('event_type');
            $table->index('processed');
        });
    }

    public function down()
    {
        Schema::dropIfExists('webhook_events');
    }
};