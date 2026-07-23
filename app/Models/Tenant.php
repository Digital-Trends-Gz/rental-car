<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'domain',
        'email',
        'phone',
        'country_iso2',
        'phone_country_code',
        'phone_national',
        'phone_e164',
        'plan_id',
        'partner_seats',
        'plan',
        'is_active',
        'settings',
        'trial_ends_at',
        'stripe_account_id',
        'stripe_onboarded_at',
        'stripe_details_submitted',
        'stripe_charges_enabled',
        'stripe_payouts_enabled',
        'stripe_currency',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'plan_id' => 'integer',
            'partner_seats' => 'integer',
            'settings' => 'array',
            'trial_ends_at' => 'datetime',
            'country_iso2' => 'string',
            'phone_country_code' => 'string',
            'phone_national' => 'string',
            'phone_e164' => 'string',
            'stripe_onboarded_at' => 'datetime',
            'stripe_details_submitted' => 'boolean',
            'stripe_charges_enabled' => 'boolean',
            'stripe_payouts_enabled' => 'boolean',
        ];
    }

    /**
     * Get the linked subscription plan.
     */
    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    /**
     * Get the users for this tenant.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * Get the cars for this tenant.
     */
    public function cars(): HasMany
    {
        return $this->hasMany(Car::class);
    }

    /**
     * Get the reservations for this tenant.
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * Get the payments for this tenant.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the tickets for this tenant.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Get the messages for this tenant.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get coupons for this tenant.
     */
    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    public function carDiscounts(): HasMany
    {
        return $this->hasMany(CarDiscount::class);
    }

    /**
     * Get tenant public website settings (branding + content).
     */
    public function siteSetting(): HasOne
    {
        return $this->hasOne(TenantSiteSetting::class);
    }

    public function supportsFeature(string $feature): bool
    {
        return $this->subscriptionPlan?->supportsFeature($feature) ?? false;
    }

    /**
     * Determine if the tenant is on trial.
     */
    public function onTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Determine if the tenant's trial has expired.
     */
    public function trialExpired(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    /**
     * Determine if tenant subscription should be renewed.
     */
    public function requiresSubscriptionRenewal(): bool
    {
        if (!$this->plan_id) {
            return true;
        }

        if (!$this->trial_ends_at || $this->trial_ends_at->isPast()) {
            return true;
        }

        if ($this->relationLoaded('subscriptionPlan')) {
            return !$this->subscriptionPlan || !$this->subscriptionPlan->is_active;
        }

        return !$this->subscriptionPlan()
            ->where('is_active', true)
            ->exists();
    }
}
