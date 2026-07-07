<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    public const FEATURE_KEYS = [
        'client_portal',
        'booking_calendar',
        'cash_payments',
        'extension_request',
        'force_extend_contract',
        'car_documents',
        'maintenance_module',
        'violations_module',
        'damage_reports',
        'reports_module',
        'pdf_export',
        'ai_contract_extraction',
        'whatsapp_notifications',
        'sms_notifications',
        'email_notifications',
        'custom_branding',
        'custom_domain',
        'stripe_connect',
        'coupon_system',
        'auto_discounts',
        'roles_and_permissions',
    ];

    protected $fillable = [
        'name',
        'description',
        'sort_order',
        'features',
        'feature_flags',
        'monthly_price',
        'monthly_price_id',
        'yearly_price',
        'yearly_price_id',
        'one_time_price',
        'one_time_price_id',
        'max_employees',
        'max_branches',
        'max_cars',
        'max_contracts',
        'openai_requests_per_day',
        'is_active',
    ];

    protected $casts = [
        'features' => 'array',
        'feature_flags' => 'array',
        'is_active' => 'boolean',
        'monthly_price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        'one_time_price' => 'decimal:2',
        'max_employees' => 'integer',
        'max_branches' => 'integer',
        'max_cars' => 'integer',
        'max_contracts' => 'integer',
        'openai_requests_per_day' => 'integer',
        'sort_order' => 'integer',
    ];

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'plan_id');
    }

    public function supportsFeature(string $feature): bool
    {
        if (empty($this->feature_flags)) {
            return true;
        }

        if (!in_array($feature, self::FEATURE_KEYS, true)) {
            return false;
        }

        if (!array_key_exists($feature, $this->feature_flags)) {
            return true;
        }

        return (bool) ($this->feature_flags[$feature] ?? true);
    }
}
