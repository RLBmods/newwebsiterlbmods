<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class ImportLegacyUsers extends Command
{
    protected $signature = 'import:legacy-users';
    protected $description = 'Import legacy users from usertable.sql';

    public function handle()
    {
        $path = base_path('usertable.sql');
        if (!file_exists($path)) {
            $this->error("SQL file not found at " . $path);
            return;
        }

        $this->info("Importing usertable.sql into temporary table...");
        
        // Read the SQL file
        $sql = file_get_contents($path);
        
        // Remove some problematic lines if any, or just run it
        // Note: The SQL file starts with CREATE TABLE `usertable`
        try {
            DB::unprepared($sql);
        } catch (\Exception $e) {
            $this->error("Error importing SQL: " . $e->getMessage());
            // If it's already created, we might want to continue or wipe it
        }

        if (!Schema::hasTable('usertable')) {
            $this->error("Table 'usertable' was not created. Check SQL errors.");
            return;
        }

        $count = DB::table('usertable')->count();
        $this->info("Found " . $count . " users to import.");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        DB::table('usertable')->orderBy('id')->chunk(100, function ($legacyUsers) use ($bar) {
            foreach ($legacyUsers as $legacy) {
                // Map roles
                $role = match ($legacy->role) {
                    'reseller' => 'reseller',
                    'support', 'developer', 'manager', 'founder' => 'admin',
                    default => 'user',
                };

                // Map status for email_verified_at
                $verifiedAt = $legacy->status === 'verified' ? now() : null;

                // We use DB::table to avoid double-hashing by Eloquent's 'hashed' cast
                $userId = DB::table('users')->updateOrInsert(
                    ['email' => $legacy->email],
                    [
                        'name' => $legacy->name,
                        'password' => $legacy->password, // Already hashed
                        'discord_id' => (string) $legacy->discordid,
                        'balance' => $legacy->balance ?: 0,
                        'role' => $role,
                        'banned' => (bool) $legacy->banned,
                        'muted' => (bool) $legacy->muted,
                        'last_login_at' => $legacy->last_login ? new \DateTime($legacy->last_login) : null,
                        'last_activity_at' => $legacy->last_activity ? new \DateTime($legacy->last_activity) : null,
                        'last_login_ip' => $legacy->current_ip,
                        'product_access' => $legacy->product_access,
                        'email_verified_at' => $verifiedAt,
                        'created_at' => $legacy->created_at ? new \DateTime($legacy->created_at) : now(),
                        'updated_at' => $legacy->updated_at ? new \DateTime($legacy->updated_at) : now(),
                    ]
                );

                $bar->advance();
            }
        });

        $bar->finish();
        $this->info("\nImport complete!");
        
        // Optionally drop the temporary table
        Schema::dropIfExists('usertable');
    }
}
