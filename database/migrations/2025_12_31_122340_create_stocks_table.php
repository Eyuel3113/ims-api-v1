<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->decimal('quantity', 15, 2)->default(0);
            $table->date('expiry_date')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'warehouse_id', 'expiry_date']); // prevent duplicate batch
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};