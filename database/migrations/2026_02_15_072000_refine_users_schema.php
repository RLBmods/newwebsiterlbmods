<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, standardize existing roles
        DB::table('users')->where('role', 'member')->update(['role' => 'user']);
        DB::table('users')->whereNotIn('role', ['user', 'reseller', 'admin'])->update(['role' => 'user']);

        Schema::table('users', function (Blueprint $table) {
            // Drop redundant/useless columns
            $table->dropColumn([
                'code',
                'status',
                'profile_picture',
                'banner_url',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at'
            ]);

            // Change role to enum
            // Note: In SQLite (if used for testing) this might be tricky, 
            // but for MySQL/PostgreSQL it works as intended.
            $table->enum('role', ['user', 'reseller', 'admin'])->default('user')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('code')->nullable();
            $table->string('status')->default('active');
            $table->string('profile_picture')->nullable()->default('/assets/avatars/default-avatar.png');
            $table->string('banner_url')->nullable()->default('/assets/banners/default-banner.png');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes',)->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            
            $table->string('role')->default('user')->change();
        });
    }
};
