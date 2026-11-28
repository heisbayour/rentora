<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\BelongsToOrganization;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string $password
 * @property Role $role
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 * @property Carbon|null $password_set_at
 * @property Carbon|null $removed_at
 * @property Carbon|null $email_verified_at
 * @property string|null $two_factor_secret
 * @property Carbon|null $two_factor_confirmed_at
 * @property-read Tenant|null $tenant
 */
class User extends Authenticatable
{
    use BelongsToOrganization;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'name',
        'email',
        'phone',
        'password',
        'role',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
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
            'last_login_at' => 'datetime',
            'password_set_at' => 'datetime',
            'removed_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * password_set_at follows every real password change. An invited staff
     * member is created with a random password and password_set_at null, so
     * the console can offer "Get invite link" until they choose one.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->isDirty('password') && ! $user->isDirty('password_set_at')) {
                $user->password_set_at = now();
            }
        });
    }

    /** The tenant record behind a tenant portal account. @return HasOne<Tenant, $this> */
    public function tenant(): HasOne
    {
        return $this->hasOne(Tenant::class);
    }

    /** A developer counts as an owner everywhere an owner is allowed. */
    public function hasRole(Role ...$roles): bool
    {
        if (in_array($this->role, $roles, true)) {
            return true;
        }

        return $this->role === Role::Developer && in_array(Role::Owner, $roles, true);
    }

    /** The reset email goes through the Settings SMTP server; callers catch failures (DECISIONS 104). */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function isDeveloper(): bool
    {
        return $this->role === Role::Developer;
    }

    public function awaitingPassword(): bool
    {
        return $this->password_set_at === null;
    }

    public function isStaff(): bool
    {
        return $this->role->isStaff();
    }

    /** Owners and developers (who hold every owner permission). */
    public function isOwner(): bool
    {
        return $this->role->actsAsOwner();
    }

    /** Owners and managers may enable TOTP two-factor authentication (spec 8.1). */
    public function canUseTwoFactor(): bool
    {
        return $this->hasRole(Role::Owner, Role::Manager);
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeStaff(Builder $query): Builder
    {
        return $query->whereIn('role', array_map(fn (Role $role) => $role->value, Role::staff()))->whereNull('removed_at');
    }
}
