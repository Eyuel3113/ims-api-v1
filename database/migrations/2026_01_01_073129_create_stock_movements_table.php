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
Schema::create('stock_movements', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
    $table->foreignUuid('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
    $table->decimal('quantity', 15, 2);
    $table->enum('type', ['purchase', 'sale', 'adjustment', 'damage', 'lost', 'found']);
    $table->string('reference_type')->nullable(); // Purchase, Sale, Adjustment
    $table->string('reference_id')->nullable();   // UUID of reference
    $table->text('notes')->nullable();
    $table->date('expiry_date')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
