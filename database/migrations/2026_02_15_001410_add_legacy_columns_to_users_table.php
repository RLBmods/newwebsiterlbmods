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
        Schema::table('users', function (Blueprint $table) {
            $table->string('code')->nullable();
            $table->string('status')->default('active');
            $table->unsignedBigInteger('discord_id')->nullable()->index();
            $table->decimal('balance', 10, 2)->default(0);
            $table->string('profile_picture')->nullable()->default('/assets/avatars/default-avatar.png');
            $table->string('banner_url')->nullable()->default('/assets/banners/default-banner.png');
            $table->string('role')->default('member')->index();
            $table->boolean('banned')->default(false)->index();
            $table->boolean('muted')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'code',
                'status',
                'discord_id',
                'balance',
                'profile_picture',
                'banner_url',
                'role',
                'banned',
                'muted',
                'last_login_at',
                'last_activity_at',
            ]);
        });
    }
};
