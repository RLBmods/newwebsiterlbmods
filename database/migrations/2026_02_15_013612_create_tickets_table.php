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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->enum('type', ['Billing', 'Support', 'Report', 'HWID Reset', 'Other']);
            $table->enum('priority', ['Normal', 'High', 'Critical']);
            $table->text('message'); // Initial message
            $table->foreignId('user_id')->constrained(); // customer_id in legacy
            $table->unsignedBigInteger('support_agent_id')->nullable();
            $table->enum('status', ['Open', 'Answered', 'Closed'])->default('Open');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
