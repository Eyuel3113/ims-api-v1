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
        Schema::table('purchases', function (Blueprint $table) {
            $table->foreignUuid('supplier_id')->nullable()->change();
            $table->string('supplier_name')->nullable()->after('supplier_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->foreignUuid('supplier_id')->nullable(false)->change();
            $table->dropColumn('supplier_name');
        });
    }
};
