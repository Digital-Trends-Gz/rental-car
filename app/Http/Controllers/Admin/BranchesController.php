<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\Plans\PlanUsageLimits;
use App\Support\BranchLocationOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use MohamedGaldi\ViltFilepond\Services\FilePondService;

class BranchesController extends Controller
{
    public function __construct(
        private readonly FilePondService $filePondService,
        private readonly PlanUsageLimits $planUsageLimits,
    ) {}

    /**
     * Display a listing of branches.
     */
    public function index(Request $request): Response
    {
        $branches = Branch::query()
            ->when($request->string('search')->toString(), function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('cr_number', 'like', "%{$search}%")
                    ->orWhere('manager_name', 'like', "%{$search}%")
                    ->orWhere('manager_civil_number', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('street_name', 'like', "%{$search}%")
                    ->orWhere('phone_1', 'like', "%{$search}%")
                    ->orWhere('phone_2', 'like', "%{$search}%")
                    ->orWhere('whatsapp', 'like', "%{$search}%");
            })
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/Branches/Index', [
            'branches' => $branches,
            'filters' => [
                'search' => $request->string('search')->toString(),
            ],
        ]);
    }

    /**
     * Show the form for creating a new branch.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Branches/Edit', [
            'branch' => null,
            'showroomFiles' => [],
            'countries' => BranchLocationOptions::countrySelectOptions(app()->getLocale()),
        ]);
    }

    /**
     * Store a newly created branch in storage.
     */
    public function store(Request $request)
    {
        $countryCodes = collect(BranchLocationOptions::countrySelectOptions('en'))
            ->pluck('value')
            ->all();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'country' => ['nullable', 'string', Rule::in($countryCodes)],
            'city' => ['nullable', 'string', 'max:100'],
            'street_name' => ['nullable', 'string', 'max:255'],
            'street_number' => ['nullable', 'string', 'max:50'],
            'building_number' => ['nullable', 'string', 'max:50'],
            'office_number' => ['nullable', 'string', 'max:50'],
            'post_code' => ['nullable', 'string', 'max:50'],
            'google_map_url' => ['nullable', 'url', 'max:1000'],
            'phone_1' => ['nullable', 'string', 'max:50'],
            'phone_2' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'cr_number' => ['nullable', 'string', 'max:255'],
            'manager_name' => ['nullable', 'string', 'max:255'],
            'manager_civil_number' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'showroom_temp_folders' => ['array'],
            'showroom_temp_folders.*' => ['string'],
            'showroom_removed_files' => ['array'],
            'showroom_removed_files.*' => ['integer'],
        ]);

        if (config('app.demo_mode')) {
            return redirect()
                ->back()
                ->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        if ($message = $this->planUsageLimits->branchLimitMessage()) {
            return redirect()->back()->with('error', $message);
        }

        $branch = Branch::create($this->branchAttributes($validated));

        $this->syncShowroomImage($branch, $request);

        return redirect()
            ->route('admin.branches.index', ['subdomain' => $request->route('subdomain')])
            ->with('success', 'Branch created successfully.');
    }

    /**
     * Show the form for editing the specified branch.
     */
    public function edit(Branch $branch): Response
    {
        $branch->loadMissing('files');

        return Inertia::render('Admin/Branches/Edit', [
            'branch' => $branch,
            'showroomFiles' => $branch->files
                ->where('collection', 'showroom')
                ->map(fn ($file) => [
                    'id' => $file->id,
                    'url' => Storage::url($file->path),
                ])
                ->values()
                ->all(),
            'countries' => BranchLocationOptions::countrySelectOptions(app()->getLocale()),
        ]);
    }

    public function cities(Request $request): JsonResponse
    {
        $country = strtoupper(trim((string) $request->query('country', '')));

        return response()->json([
            'cities' => BranchLocationOptions::cityOptionsForCountry($country, app()->getLocale()),
        ]);
    }

    /**
     * Update the specified branch in storage.
     */
    public function update(Request $request)
    {
        $branch = Branch::withoutGlobalScope('tenant')
            ->findOrFail((int) $request->route('branch'));

        $countryCodes = collect(BranchLocationOptions::countrySelectOptions('en'))
            ->pluck('value')
            ->all();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'country' => ['nullable', 'string', Rule::in($countryCodes)],
            'city' => ['nullable', 'string', 'max:100'],
            'street_name' => ['nullable', 'string', 'max:255'],
            'street_number' => ['nullable', 'string', 'max:50'],
            'building_number' => ['nullable', 'string', 'max:50'],
            'office_number' => ['nullable', 'string', 'max:50'],
            'post_code' => ['nullable', 'string', 'max:50'],
            'google_map_url' => ['nullable', 'url', 'max:1000'],
            'phone_1' => ['nullable', 'string', 'max:50'],
            'phone_2' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'cr_number' => ['nullable', 'string', 'max:255'],
            'manager_name' => ['nullable', 'string', 'max:255'],
            'manager_civil_number' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'showroom_temp_folders' => ['array'],
            'showroom_temp_folders.*' => ['string'],
            'showroom_removed_files' => ['array'],
            'showroom_removed_files.*' => ['integer'],
        ]);

        if (config('app.demo_mode')) {
            return redirect()
                ->back()
                ->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        if ($message = $this->planUsageLimits->branchLimitMessage()) {
            return redirect()->back()->with('error', $message);
        }

        Branch::withoutGlobalScope('tenant')
            ->whereKey($branch->id)
            ->update($this->branchAttributes($validated));

        $branch->refresh();
        $this->syncShowroomImage($branch, $request);

        return redirect()
            ->route('admin.branches.index', ['subdomain' => $request->route('subdomain')])
            ->with('success', 'Branch updated successfully.');
    }

    /**
     * Remove the specified branch from storage.
     */
    public function destroy(Branch $branch)
    {
        if (config('app.demo_mode')) {
            return redirect()
                ->back()
                ->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $branch->delete();

        return redirect()
            ->back()
            ->with('success', 'Branch deleted successfully.');
    }

    private function branchAttributes(array $validated): array
    {
        $country = $this->nullableString($validated['country'] ?? null);
        $city = $this->nullableString($validated['city'] ?? null);
        $streetName = $this->nullableString($validated['street_name'] ?? null);
        $streetNumber = $this->nullableString($validated['street_number'] ?? null);
        $buildingNumber = $this->nullableString($validated['building_number'] ?? null);
        $officeNumber = $this->nullableString($validated['office_number'] ?? null);
        $postCode = $this->nullableString($validated['post_code'] ?? null);
        $countryName = BranchLocationOptions::countryNameForCode($country, 'en');

        $addressParts = array_filter([
            $streetName,
            $streetNumber ? "Street No. {$streetNumber}" : null,
            $buildingNumber ? "Building {$buildingNumber}" : null,
            $officeNumber ? "Office {$officeNumber}" : null,
            $city,
            $countryName,
            $postCode ? "Post Code {$postCode}" : null,
        ]);

        return [
            'name' => $validated['name'],
            'address' => empty($addressParts) ? null : implode(', ', $addressParts),
            'phone' => $this->nullableString($validated['phone_1'] ?? null) ?: $this->nullableString($validated['phone_2'] ?? null),
            'email' => $this->nullableString($validated['email'] ?? null),
            'country' => $country,
            'city' => $city,
            'street_name' => $streetName,
            'street_number' => $streetNumber,
            'building_number' => $buildingNumber,
            'office_number' => $officeNumber,
            'post_code' => $postCode,
            'google_map_url' => $this->nullableString($validated['google_map_url'] ?? null),
            'phone_1' => $this->nullableString($validated['phone_1'] ?? null),
            'phone_2' => $this->nullableString($validated['phone_2'] ?? null),
            'whatsapp' => $this->nullableString($validated['whatsapp'] ?? null),
            'cr_number' => $this->nullableString($validated['cr_number'] ?? null),
            'manager_name' => $this->nullableString($validated['manager_name'] ?? null),
            'manager_civil_number' => $this->nullableString($validated['manager_civil_number'] ?? null),
        ];
    }

    private function syncShowroomImage(Branch $branch, Request $request): void
    {
        $tempFolders = $request->input('showroom_temp_folders', []);
        $removedIds = $request->input('showroom_removed_files', []);

        if (!empty($tempFolders)) {
            $existingIds = $branch->files()->where('collection', 'showroom')->pluck('id')->all();
            $removedIds = array_values(array_unique(array_merge($removedIds, $existingIds)));
        }

        $this->filePondService->handleFileUpdates(
            $branch,
            is_array($tempFolders) ? $tempFolders : [],
            is_array($removedIds) ? $removedIds : [],
            'showroom'
        );
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
