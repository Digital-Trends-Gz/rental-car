<?php

namespace App\Models;

use App\Notifications\TenantAwareVerifyEmailNotification;
use App\Enums\UserRole;
use App\Traits\BelongsToTenant;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'email_verified_at',
        'password',
        'role',
        'tenant_id',
        'is_active',
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
