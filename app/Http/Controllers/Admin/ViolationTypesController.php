<?php

namespace App\Http\Controllers\Admin;

use App\Core\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\ViolationType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ViolationTypesController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $violationTypes = ViolationType::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($where) use ($search) {
                    $where->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        $violationTypes->getCollection()->transform(function (ViolationType $type) {
            return [
                'id' => $type->id,
                'name' => $type->name,
                'description' => $type->description,
                'is_active' => (bool) $type->is_active,
                'sort_order' => (int) $type->sort_order,
                'edit_url' => route('admin.violation-types.edit', $type),
                'destroy_url' => route('admin.violation-types.destroy', $type),
            ];
        });

        return Inertia::render('Admin/ViolationTypes/Index', [
            'violationTypes' => $violationTypes,
            'filters' => [
                'search' => $search,
            ],
            'indexUrl' => route('admin.violation-types.index'),
            'createUrl' => route('admin.violation-types.create'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/ViolationTypes/Edit', [
            'violationType' => null,
            'indexUrl' => route('admin.violation-types.index'),
            'submitUrl' => route('admin.violation-types.store'),
            'method' => 'post',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (config('app.demo_mode')) {
            return back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $validated = $this->validatePayload($request);

        ViolationType::create($validated + [
            'tenant_id' => $this->tenantId(),
        ]);

        return redirect()
            ->route('admin.violation-types.index')
            ->with('success', 'Violation type created successfully.');
    }

    public function edit(ViolationType $violationType): Response
    {
        return Inertia::render('Admin/ViolationTypes/Edit', [
            'violationType' => [
                'id' => $violationType->id,
                'name' => $violationType->name,
                'description' => $violationType->description,
                'is_active' => (bool) $violationType->is_active,
                'sort_order' => (int) $violationType->sort_order,
            ],
            'indexUrl' => route('admin.violation-types.index'),
            'submitUrl' => route('admin.violation-types.update', $violationType),
            'method' => 'put',
        ]);
    }

    public function update(Request $request, ViolationType $violationType): RedirectResponse
    {
        if (config('app.demo_mode')) {
            return back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $violationType->update($this->validatePayload($request, $violationType));

        return redirect()
            ->route('admin.violation-types.index')
            ->with('success', 'Violation type updated successfully.');
    }

    public function destroy(ViolationType $violationType): RedirectResponse
    {
        if (config('app.demo_mode')) {
            return back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        if ($violationType->violations()->exists()) {
            return back()->with('error', 'Cannot delete a violation type that is already used by violations.');
        }

        $violationType->delete();

        return back()->with('success', 'Violation type deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?ViolationType $violationType = null): array
    {
        $tenantId = $this->tenantId();

        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('violation_types', 'name')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($violationType?->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
    }

    private function tenantId(): int
    {
        return (int) (TenantContext::id() ?? auth()->user()?->tenant_id ?? 0);
    }
}
