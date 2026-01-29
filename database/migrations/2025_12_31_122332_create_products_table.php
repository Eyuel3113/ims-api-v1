<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code')->unique();
            $table->foreignUuid('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('unit'); // pcs, kg, box, etc.
            $table->string('barcode')->unique()->nullable();
            $table->string('photo')->nullable(); // path to photo
            $table->integer('min_stock')->default(0);
            $table->boolean('has_expiry')->default(false);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};