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
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_vatable')->default(true)->after('selling_price');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('tax_amount', 15, 2)->default(0)->after('total_amount');
            $table->decimal('grand_total', 15, 2)->default(0)->after('tax_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_vatable');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['tax_amount', 'grand_total']);
        });
    }
};
