<?php

namespace App\Models;

use App\Notifications\TenantAwareVerifyEmailNotification;
use App\Core\TenantContext;
use App\Enums\UserRole;
use App\Traits\BelongsToTenant;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laratrust\Contracts\LaratrustUser;
use Laratrust\Traits\HasRolesAndPermissions;


class User extends Authenticatable implements LaratrustUser, MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable, BelongsToTenant, HasRolesAndPermissions;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'civil_number',
        'email',
        'phone',
        'whatsapp',
        'email_verified_at',
        'password',
        'role',
        'tenant_id',
        'is_active',
        'plan_locked_at',
        'plan_lock_reason',
        'trial_ends_at',
        'branch_id',
        'provider',
        'provider_id',
    ];

    /**
     * Get the branch the user belongs to.
     */
    public function branch(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)
            ->withPivot('tenant_id')
            ->withTimestamps();
    }

    public function permissions(): MorphToMany
    {
        $relation = $this->morphToMany(Permission::class, 'user', 'permission_user', 'user_id', 'permission_id')
            ->withoutGlobalScope('tenant');

        if ($this->role === UserRole::SUPER_ADMIN) {
            return $relation;
        }

        $tenantId = TenantContext::id() ?? $this->tenant_id ?? auth()->user()?->tenant_id;

        if ($tenantId) {
            $relation->where(function ($query) use ($tenantId) {
                $query->whereNull('permissions.tenant_id')
                    ->orWhere('permissions.tenant_id', $tenantId);
            });
        } else {
            $relation->whereNull('permissions.tenant_id');
        }

        return $relation;
    }

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
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'plan_locked_at' => 'datetime',
            'trial_ends_at' => 'datetime',
        ];
    }

    /**
     * Get the reservations for the user.
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * Get the payments for the user.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    public function clientDocuments(): HasMany
    {
        return $this->hasMany(ClientDocument::class);
    }

    public function clientFlags(): HasMany
    {
        return $this->hasMany(ClientFlag::class);
    }

    public function clientNotes(): HasMany
    {
        return $this->hasMany(ClientNote::class);
    }

    public function contractDrivers(): HasMany
    {
        return $this->hasMany(ContractDriver::class, 'client_id');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new TenantAwareVerifyEmailNotification());
    }

    public function requiresTenantEmailVerification(): bool
    {
        return !empty($this->tenant_id)
            && in_array($this->role, [UserRole::ADMIN, UserRole::CLIENT], true);
    }
}
