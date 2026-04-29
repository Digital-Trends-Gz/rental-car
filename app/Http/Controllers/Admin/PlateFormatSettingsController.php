<?php

namespace App\Http\Controllers\Admin;

use App\Core\PlateFormatSettings;
use App\Core\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\TenantSiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PlateFormatSettingsController extends Controller
{
    public function edit(): Response
    {
        $tenant = TenantContext::get();
        abort_unless($tenant, 404);

        $tenantSettings = TenantSiteSetting::query()
            ->where('tenant_id', $tenant->id)
            ->first();

        return Inertia::render('Admin/Settings/PlateFormats', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'settings' => PlateFormatSettings::normalize(
                is_array($tenantSettings?->plate_formats) ? $tenantSettings->plate_formats : null
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
            'settings.plate_formats' => ['nullable', 'array'],
            'settings.plate_formats.*.code' => ['nullable', 'string', 'max:100'],
            'settings.plate_formats.*.name' => ['nullable', 'string', 'max:255'],
            'settings.plate_formats.*.country' => ['nullable', 'string', 'max:255'],
            'settings.plate_formats.*.mask' => ['nullable', 'string', 'max:100'],
            'settings.plate_formats.*.example' => ['nullable', 'string', 'max:255'],
            'settings.plate_formats.*.is_active' => ['nullable', 'boolean'],
        ]);

        $plateFormats = data_get($validated, 'settings.plate_formats', []);
        $this->validatePlateFormats($plateFormats);

        $normalized = PlateFormatSettings::normalize($validated['settings']['plate_formats'] ?? []);

        TenantSiteSetting::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            ['plate_formats' => $normalized]
        );

        return back()->with('success', 'License plate formats updated successfully.');
    }

    /**
     * @param  array<int, mixed>  $formats
     */
    private function validatePlateFormats(array $formats): void
    {
        $errors = [];

        foreach ($formats as $index => $format) {
            if (!is_array($format)) {
                continue;
            }

            $name = trim((string) data_get($format, 'name', ''));
            $country = trim((string) data_get($format, 'country', ''));
            $mask = trim((string) data_get($format, 'mask', ''));
            $example = trim((string) data_get($format, 'example', ''));

            if ($name === '' && $country === '' && $mask === '' && $example === '') {
                continue;
            }

            if ($mask === '') {
                $errors["settings.plate_formats.$index.mask"] = 'The format mask is required for each plate format.';
                continue;
            }

            if (PlateFormatSettings::maskToRegex($mask) === null) {
                $errors["settings.plate_formats.$index.mask"] = 'The format mask is not valid.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
