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
Schema::create('sale_items', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('sale_id')->constrained('sales')->cascadeOnDelete();
    $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
    $table->foreignUuid('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
    $table->decimal('quantity', 15, 2);
    $table->decimal('unit_price', 15, 2);
    $table->decimal('total_price', 15, 2);
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
