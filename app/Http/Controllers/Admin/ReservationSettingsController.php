<?php

namespace App\Http\Controllers\Admin;

use App\Core\ReservationSettings;
use App\Core\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\TenantSiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReservationSettingsController extends Controller
{
    public function edit(): Response
    {
        $tenant = TenantContext::get();
        abort_unless($tenant, 404);

        $tenantSettings = TenantSiteSetting::query()
            ->where('tenant_id', $tenant->id)
            ->first();

        return Inertia::render('Admin/Settings/ReservationSettings', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'settings' => ReservationSettings::normalize(
                is_array($tenantSettings?->reservation_settings) ? $tenantSettings->reservation_settings : null
            ),
            'actions' => [
                'update' => url()->current(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = TenantContext::get();
        abort_unless($tenant, 404);

        $validated = $request->validate([
            'settings.return_time_policy.mode' => ['required', 'string', Rule::in(['fixed_time', 'same_pickup', 'set_during_reservation'])],
            'settings.return_time_policy.fixed_time' => ['nullable', 'date_format:H:i'],

            'settings.pickup_return_locations' => ['nullable', 'array'],
            'settings.pickup_return_locations.*.name' => ['nullable', 'string', 'max:255'],
            'settings.pickup_return_locations.*.pickup_fee' => ['nullable', 'numeric', 'min:0'],
            'settings.pickup_return_locations.*.return_fee' => ['nullable', 'numeric', 'min:0'],
            'settings.pickup_return_locations.*.pickup_free' => ['nullable', 'boolean'],
            'settings.pickup_return_locations.*.return_free' => ['nullable', 'boolean'],
            'settings.pickup_return_locations.*.is_active' => ['nullable', 'boolean'],

            'settings.kilometer_pricing' => ['nullable', 'array'],
            'settings.kilometer_pricing.*.from_km' => ['nullable', 'integer', 'min:0'],
            'settings.kilometer_pricing.*.to_km' => ['nullable', 'integer', 'min:0'],
            'settings.kilometer_pricing.*.price' => ['nullable', 'numeric', 'min:0'],

            'settings.fuel_pricing' => ['nullable', 'array'],
            'settings.fuel_pricing.*.fuel_level' => ['nullable', 'string', Rule::in(['empty', 'quarter', 'half', 'three_quarters', 'full'])],
            'settings.fuel_pricing.*.price' => ['nullable', 'numeric', 'min:0'],

            'settings.late_return.mode' => ['required', 'string', Rule::in(['hourly', 'daily_after_threshold'])],
            'settings.late_return.hourly_fee' => ['nullable', 'numeric', 'min:0'],
            'settings.late_return.after_hours' => ['nullable', 'integer', 'min:0'],

            'settings.cleaning_fee' => ['nullable', 'numeric', 'min:0'],
        ]);

        $kilometerPricing = data_get($validated, 'settings.kilometer_pricing', []);
        $this->validateKilometerPricingRanges($kilometerPricing);

        $normalized = ReservationSettings::normalize($validated['settings'] ?? []);

        TenantSiteSetting::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            ['reservation_settings' => $normalized]
        );

        return back()->with('success', 'Reservation settings updated successfully.');
    }

    /**
     * @param  array<int, mixed>  $tiers
     */
    private function validateKilometerPricingRanges(array $tiers): void
    {
        $errors = [];

        foreach ($tiers as $index => $tier) {
            if (!is_array($tier)) {
                continue;
            }

            $from = data_get($tier, 'from_km');
            $to = data_get($tier, 'to_km');

            if ($from === null || $from === '' || $to === null || $to === '') {
                continue;
            }

            if ((int) $from > (int) $to) {
                $errors["settings.kilometer_pricing.$index.to_km"] = 'The end kilometer must be greater than or equal to the start kilometer.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
