<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(2),
            'description' => $this->faker->paragraph(),
            'features' => ['Core booking', 'Admin dashboard'],
            'feature_flags' => [
                'client_portal' => true,
                'booking_calendar' => true,
                'cash_payments' => true,
                'extension_request' => true,
                'force_extend_contract' => true,
                'car_documents' => true,
                'maintenance_module' => true,
                'violations_module' => true,
                'damage_reports' => true,
                'reports_module' => true,
                'pdf_export' => true,
                'ai_contract_extraction' => true,
                'whatsapp_notifications' => true,
                'sms_notifications' => true,
                'email_notifications' => true,
                'custom_branding' => true,
                'custom_domain' => true,
                'stripe_connect' => true,
                'coupon_system' => true,
                'auto_discounts' => true,
                'roles_and_permissions' => true,
            ],
            'monthly_price' => 29.99,
            'yearly_price' => 299.99,
            'one_time_price' => 0,
            'max_employees' => 5,
            'max_branches' => 2,
            'max_cars' => 10,
            'max_contracts' => 25,
            'openai_requests_per_day' => 50,
            'is_active' => true,
        ];
    }
}
