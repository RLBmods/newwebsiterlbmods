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
            $table->unsignedBigInteger('checkout_order_id')->nullable()->after('user_id');
            
            // Add a unique index to prevent duplicate purchases for the same order item
            $table->unique(['checkout_order_id', 'product_id'], 'unique_order_product_purchase');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropUnique('unique_order_product_purchase');
            $table->dropColumn('checkout_order_id');
        });
    }
};
