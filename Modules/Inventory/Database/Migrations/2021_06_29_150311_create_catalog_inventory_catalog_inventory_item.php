<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCatalogInventoryCatalogInventoryItem extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('catalog_inventory_catalog_inventory_item', function (Blueprint $table) {
            $table->unsignedBigInteger("catalog_inventory_id");
            $table->unsignedBigInteger("catalog_inventory_item_id");

            $table->foreign('catalog_inventory_id', 'inventory_id')->references('id')->on('catalog_inventories')->onDelete('cascade');
            $table->foreign('catalog_inventory_item_id', 'inventory_item_id')->references('id')->on('catalog_inventory_items')->onDelete('cascade');

            $table->unique(['catalog_inventory_id', 'catalog_inventory_item_id'], "inventory_compound");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('catalog_inventory_catalog_inventory_item');
    }
}
