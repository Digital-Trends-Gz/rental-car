<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Core\PlateFormatSettings;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlateFormatTemplatesController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('SuperAdmin/Settings/PlateFormatTemplates', [
            'settings' => PlateFormatSettings::loadGlobal(),
            'actions' => [
                'update' => route('superadmin.settings.plate-format-templates.update'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'settings.plate_formats' => ['nullable', 'array'],
            'settings.plate_formats.*.code' => ['nullable', 'string', 'max:120'],
            'settings.plate_formats.*.name' => ['nullable', 'string', 'max:255'],
            'settings.plate_formats.*.country' => ['nullable', 'string', 'size:2'],
            'settings.plate_formats.*.mask' => ['nullable', 'string', 'max:120'],
            'settings.plate_formats.*.example' => ['nullable', 'string', 'max:255'],
            'settings.plate_formats.*.is_active' => ['nullable', 'boolean'],
        ]);

        $normalized = PlateFormatSettings::normalize($validated['settings']['plate_formats'] ?? []);

        SiteSetting::query()->updateOrCreate(
            ['key' => PlateFormatSettings::GLOBAL_KEY],
            ['value' => $normalized]
        );

        return back()->with('success', 'Plate format templates updated successfully.');
    }
}
