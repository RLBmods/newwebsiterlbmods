<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('provider')->index(); // nowpayments, paytabs, etc.
            $table->string('status')->default('pending')->index(); // pending, paid, completed, failed, expired

            $table->string('order_ref')->unique(); // our internal reference

            $table->decimal('total_amount', 10, 2);
            $table->string('currency', 10)->default('usd');

            $table->json('items'); // [{product_id, price_id, quantity, amount}]

            $table->string('external_id')->nullable()->index(); // nowpayments invoice/payment id, paytabs tran_ref, etc.
            $table->text('external_url')->nullable(); // hosted checkout url

            $table->json('meta')->nullable(); // raw payloads/status history
            $table->timestamp('processed_at')->nullable(); // set once purchases created

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_orders');
    }
};

