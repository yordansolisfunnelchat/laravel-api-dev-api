<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldsToProductsTable extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('images')->nullable();
            $table->string('external_link')->nullable();
            $table->json('discounts')->nullable();
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('images');
            $table->dropColumn('external_link');
            $table->dropColumn('discounts');
        });
    }
}