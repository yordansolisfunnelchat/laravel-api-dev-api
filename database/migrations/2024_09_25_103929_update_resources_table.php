<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->string('name')->after('id');
            $table->enum('type', ['link', 'image', 'video', 'document', 'instruction'])->change();
            $table->boolean('auto_sync')->default(false)->after('url');
            $table->enum('sync_frequency', ['daily', 'weekly', 'monthly'])->nullable()->after('auto_sync');
            $table->enum('sync_status', ['pending', 'in_progress', 'completed', 'failed'])->nullable()->after('sync_frequency');
            $table->timestamp('last_synced_at')->nullable()->after('sync_status');
            $table->longText('content')->nullable()->after('description');
        });
    }

    public function down()
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->dropColumn(['name', 'auto_sync', 'sync_frequency', 'sync_status', 'last_synced_at', 'content']);
            $table->enum('type', ['image', 'document', 'video', 'audio', 'link'])->change();
        });
    }
};