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
            $table->string('order_id')->unique()->after('id')->nullable();
        });
        
        // Populate existing records if any (though this is a new project)
        // \App\Models\Purchase::all()->each(function ($purchase) {
        //     $purchase->update(['order_id' => 'RLBmods-' . \Illuminate\Support\Str::uuid()]);
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn('order_id');
        });
    }
};
