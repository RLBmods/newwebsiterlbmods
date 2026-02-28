<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'discord_id',
        'google_id',
        'balance',
        'avatar',
        'role',
        'banned',
        'muted',
        'last_login_at',
        'last_activity_at',
        'product_access',
        'otp_code',
        'otp_expires_at',
        'current_workspace_id',
        'parent_id',
        'reseller_discount',
        'last_login_ip',
        'known_ips',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'banned' => 'boolean',
            'muted' => 'boolean',
            'last_login_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'balance' => 'decimal:2',
            'otp_expires_at' => 'datetime',
            'reseller_discount' => 'decimal:2',
            'known_ips' => 'array',
        ];
    }
    /**
     * Send the email verification notification.
     *
     * @return void
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new \App\Notifications\VerifyEmailOTP);
    }

    /**
     * Get the transactions for the user.
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the workspaces owned by the user.
     */
    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    /**
     * Get the workspaces the user belongs to.
     */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members')
            ->withPivot(['role', 'permissions'])
            ->withTimestamps();
    }

    /**
     * Get the user's current workspace.
     */
    public function currentWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'current_workspace_id');
    }

    /**
     * Get the user's parent reseller.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    /**
     * Get the user's sub-resellers.
     */
    public function subResellers(): HasMany
    {
        return $this->hasMany(User::class, 'parent_id');
    }

    /**
     * Check if the user has a specific workspace permission.
     */
    public function hasWorkspacePermission(string $permission): bool
    {
        if (!$this->current_workspace_id) {
            return false;
        }

        $membership = WorkspaceMember::where('workspace_id', $this->current_workspace_id)
            ->where('user_id', $this->id)
            ->first();

        if (!$membership) {
            return false;
        }

        if ($membership->role === 'owner' || $membership->role === 'manager') {
            return true;
        }

        return in_array($permission, $membership->permissions ?? []);
    }

    /**
     * Get the security logs for the user.
     */
    public function securityLogs(): HasMany
    {
        return $this->hasMany(SecurityLog::class);
    }
}
