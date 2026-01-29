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
Schema::create('sales', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('invoice_number')->unique();
    $table->date('sale_date');
    $table->decimal('total_amount', 15, 2)->default(0);
    $table->text('notes')->nullable();
    $table->boolean('is_active')->default(true);
    $table->softDeletes();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
