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
        Schema::create('download_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key_value', 32);
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('product_id');
            $table->text('file_url');
            $table->dateTime('expiration_time');
            $table->timestamp('used_at')->nullable();
            $table->dateTime('last_download_at')->nullable();
            $table->enum('status', ['unused', 'used'])->default('unused');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->integer('download_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('download_keys');
    }
};
