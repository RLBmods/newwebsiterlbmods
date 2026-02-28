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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->string('image_url')->nullable();
            $table->text('description')->nullable();
            $table->string('file_name')->nullable();
            $table->string('download_url')->nullable();
            $table->string('type')->index();
            $table->boolean('status')->default(true)->index();
            $table->string('api_url')->nullable();
            $table->string('api_key')->nullable();
            $table->string('license_identifier')->nullable();
            $table->integer('license_level')->default(1);
            $table->string('version')->default('1.0.0');
            $table->boolean('reseller_can_sell')->default(true);
            $table->string('reseller_file_name')->nullable();
            $table->string('tutorial_link')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
