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
        DB::statement("ALTER TABLE stock_movements MODIFY COLUMN type ENUM('purchase', 'sale', 'adjustment', 'damage', 'lost', 'found', 'opening_stock', 'expired') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE stock_movements MODIFY COLUMN type ENUM('purchase', 'sale', 'adjustment', 'damage', 'lost', 'found', 'opening_stock') NOT NULL");
    }
};
