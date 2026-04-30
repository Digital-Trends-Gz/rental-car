<?php

namespace App\Http\Controllers\Admin;

use App\Core\TenantContext;
use App\Enums\CarViolationStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\CarViolation;
use App\Models\TenantSiteSetting;
use App\Models\Reservation;
use App\Models\User;
use App\Models\ViolationType;
use App\Support\BranchAccess;
use App\Support\PdfRuntime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;
use Throwable;

class CarViolationsController extends Controller
{
    public function __construct(private BranchAccess $branchAccess)
    {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $requestedBranchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));
        $carId = $this->branchAccess->normalizeRequestedBranchId($request->input('car_id'));

        $branchOptions = $this->branchAccess
            ->availableBranchesForUser($user)
            ->map(fn ($branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
            ])
            ->values();

        $allowedBranchIds = $branchOptions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $branchId = ($requestedBranchId && in_array($requestedBranchId, $allowedBranchIds, true))
            ? $requestedBranchId
            : null;

        $carsQuery = Car::query()
            ->select(['id', 'make', 'model', 'year', 'license_plate', 'branch_id'])
            ->orderBy('make')
            ->orderBy('model');
        $this->branchAccess->applyToQuery($carsQuery, $user, $branchId);
        $cars = $carsQuery
            ->get()
            ->map(fn (Car $car) => [
                'id' => $car->id,
                'label' => trim("{$car->year} {$car->make} {$car->model} ({$car->license_plate})"),
            ])
            ->values();

        $query = CarViolation::query()->with([
            'car:id,make,model,year,license_plate',
            'branch:id,name',
            'issuedTo:id,name',
            'violationType:id,name',
        ]);

        $this->branchAccess->applyToQuery($query, $user, $branchId, 'branch_id');

        if ($carId) {
            $query->where('car_id', $carId);
        }

        $query
            ->when($status !== '' && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('violation_number', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%")
                        ->orWhere('authority', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhereHas('car', function ($carQuery) use ($search) {
                            $carQuery->where('make', 'like', "%{$search}%")
                                ->orWhere('model', 'like', "%{$search}%")
                                ->orWhere('license_plate', 'like', "%{$search}%");
                        });
                });
            });

        $violations = $query
            ->latest('violation_date')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        $violations->getCollection()->transform(function (CarViolation $violation) {
            $statusValue = $violation->status instanceof CarViolationStatus
                ? $violation->status->value
                : (string) $violation->status;
            $statusLabel = $violation->status instanceof CarViolationStatus
                ? $violation->status->label()
                : ucfirst(str_replace('_', ' ', $statusValue));
            $statusColor = $violation->status instanceof CarViolationStatus
                ? $violation->status->color()
                : '#6B7280';

            return [
                'id' => $violation->id,
                'violation_number' => $violation->violation_number,
                'car' => $violation->car
                    ? trim("{$violation->car->year} {$violation->car->make} {$violation->car->model} ({$violation->car->license_plate})")
                    : '-',
                'type' => $violation->violationType?->name ?? $violation->type,
                'amount' => (float) $violation->amount,
                'status' => $statusValue,
                'status_label' => $statusLabel,
                'status_color' => $statusColor,
                'violation_date' => optional($violation->violation_date)?->toDateString(),
                'due_date' => optional($violation->due_date)?->toDateString(),
                'branch' => $violation->branch?->name ?? '-',
                'issued_to' => $violation->issuedTo?->name ?? '-',
                'edit_url' => route('admin.car-violations.edit', $violation),
                'destroy_url' => route('admin.car-violations.destroy', $violation),
                'notice_edit_url' => route('admin.car-violations.notice.edit', $violation),
                'notice_pdf_url' => route('admin.car-violations.notice.pdf', $violation),
                'notice_print_url' => route('admin.car-violations.notice.print', $violation),
            ];
        });

        $statuses = collect(CarViolationStatus::cases())->map(fn ($statusCase) => [
            'value' => $statusCase->value,
            'label' => $statusCase->label(),
            'color' => $statusCase->color(),
        ])->values();

        return Inertia::render('Admin/CarViolations/Index', [
            'violations' => $violations,
            'statuses' => $statuses,
            'branches' => $branchOptions,
            'cars' => $cars,
            'canAccessAllBranches' => $canAccessAllBranches,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'branch_id' => $branchId,
                'car_id' => $carId,
            ],
            'indexUrl' => route('admin.car-violations.index'),
            'createUrl' => route('admin.car-violations.create'),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/CarViolations/Edit', [
            'violation' => null,
            ...$this->formOptions($request),
            'indexUrl' => route('admin.car-violations.index'),
            'submitUrl' => route('admin.car-violations.store'),
            'method' => 'post',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateViolation($request);

        if (config('app.demo_mode')) {
            return back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $car = $this->resolveAccessibleCar($request, (int) $validated['car_id']);
        $reservation = $this->resolveAccessibleReservation($request, $car, $validated['reservation_id'] ?? null);
        $issuedToUserId = $this->resolveIssuedToUserId($request, $car, $reservation, $validated['branch_owner_user_id'] ?? null);
        $violationType = $this->resolveViolationType($request, (int) $validated['violation_type_id']);

        $status = $validated['status'];
        $paidAt = $validated['paid_at'] ?? null;
        if ($status === CarViolationStatus::PAID->value && empty($paidAt)) {
            $paidAt = now()->toDateTimeString();
        }

        CarViolation::create([
            'car_id' => $car->id,
            'branch_id' => $car->branch_id,
            'reservation_id' => $reservation?->id,
            'violation_type_id' => $violationType->id,
            'issued_to_user_id' => $issuedToUserId,
            'created_by' => $request->user()?->id,
            'violation_number' => $validated['violation_number'] ?? null,
            'violation_date' => $validated['violation_date'],
            'type' => $violationType->name,
            'amount' => $validated['amount'],
            'status' => $status,
            'due_date' => $validated['due_date'] ?? $validated['violation_date'],
            'paid_at' => $paidAt,
            'payment_reference' => $validated['payment_reference'] ?? null,
            'authority' => $validated['authority'] ?? null,
            'location' => $validated['location'] ?? null,
            'description' => $validated['description'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('admin.car-violations.index')
            ->with('success', 'Car violation created successfully.');
    }

    public function edit(Request $request, CarViolation $carViolation): Response
    {
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $carViolation->branch_id ? (int) $carViolation->branch_id : null), 403);

        $selectedViolationTypeId = $carViolation->violation_type_id;
        if (!$selectedViolationTypeId && filled($carViolation->type)) {
            $selectedViolationTypeId = ViolationType::query()
                ->where('name', $carViolation->type)
                ->value('id');
        }

        return Inertia::render('Admin/CarViolations/Edit', [
            'violation' => [
                'id' => $carViolation->id,
                'car_id' => $carViolation->car_id,
                'reservation_id' => $carViolation->reservation_id,
                'violation_type_id' => $selectedViolationTypeId ? (int) $selectedViolationTypeId : null,
                'issued_to_user_id' => $carViolation->issued_to_user_id,
                'branch_owner_user_id' => $carViolation->reservation_id ? null : $carViolation->issued_to_user_id,
                'violation_number' => $carViolation->violation_number,
                'violation_date' => optional($carViolation->violation_date)?->toDateString(),
                'type' => $carViolation->violationType?->name ?? $carViolation->type,
                'amount' => (float) $carViolation->amount,
                'status' => $carViolation->status instanceof CarViolationStatus ? $carViolation->status->value : (string) $carViolation->status,
                'due_date' => optional($carViolation->due_date)?->toDateString(),
                'paid_at' => optional($carViolation->paid_at)?->format('Y-m-d\TH:i'),
                'payment_reference' => $carViolation->payment_reference,
                'authority' => $carViolation->authority,
                'location' => $carViolation->location,
                'description' => $carViolation->description,
                'notes' => $carViolation->notes,
            ],
            ...$this->formOptions($request, $carViolation),
            'noticePdfUrl' => route('admin.car-violations.notice.pdf', $carViolation),
            'noticePrintUrl' => route('admin.car-violations.notice.print', $carViolation),
            'indexUrl' => route('admin.car-violations.index'),
            'submitUrl' => route('admin.car-violations.update', $carViolation),
            'method' => 'put',
        ]);
    }

    public function update(Request $request, CarViolation $carViolation): RedirectResponse
    {
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $carViolation->branch_id ? (int) $carViolation->branch_id : null), 403);

        $validated = $this->validateViolation($request, $carViolation);

        if (config('app.demo_mode')) {
            return back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $car = $this->resolveAccessibleCar($request, (int) $validated['car_id']);
        $reservation = $this->resolveAccessibleReservation($request, $car, $validated['reservation_id'] ?? null, $carViolation);
        $issuedToUserId = $this->resolveIssuedToUserId($request, $car, $reservation, $validated['branch_owner_user_id'] ?? null);
        $violationType = $this->resolveViolationType($request, (int) $validated['violation_type_id']);

        $status = $validated['status'];
        $paidAt = $validated['paid_at'] ?? null;
        if ($status === CarViolationStatus::PAID->value && empty($paidAt)) {
            $paidAt = now()->toDateTimeString();
        }
        if ($status !== CarViolationStatus::PAID->value) {
            $paidAt = null;
        }

        $carViolation->update([
            'car_id' => $car->id,
            'branch_id' => $car->branch_id,
            'reservation_id' => $reservation?->id,
            'violation_type_id' => $violationType->id,
            'issued_to_user_id' => $issuedToUserId,
            'violation_number' => $validated['violation_number'] ?? null,
            'violation_date' => $validated['violation_date'],
            'type' => $violationType->name,
            'amount' => $validated['amount'],
            'status' => $status,
            'due_date' => $validated['due_date'] ?? $validated['violation_date'],
            'paid_at' => $paidAt,
            'payment_reference' => $validated['payment_reference'] ?? null,
            'authority' => $validated['authority'] ?? null,
            'location' => $validated['location'] ?? null,
            'description' => $validated['description'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('admin.car-violations.index')
            ->with('success', 'Car violation updated successfully.');
    }

    public function noticePdf(Request $request, CarViolation $carViolation)
    {
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $carViolation->branch_id ? (int) $carViolation->branch_id : null), 403);

        return $this->renderNoticePdf($request, $carViolation, true);
    }

    public function noticePrint(Request $request, CarViolation $carViolation)
    {
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $carViolation->branch_id ? (int) $carViolation->branch_id : null), 403);

        return $this->renderNoticePdf($request, $carViolation, false);
    }

    public function noticeEdit(Request $request, CarViolation $carViolation): Response
    {
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $carViolation->branch_id ? (int) $carViolation->branch_id : null), 403);

        $tenant = $carViolation->tenant;
        $settings = $tenant ? \App\Models\TenantSiteSetting::forTenant($tenant) : [];
        $pdfHeader = data_get($settings, 'pdf_header', []);
        $policeNotice = data_get($settings, 'police_notice', []);
        $officeLine = (string) data_get($policeNotice, 'office_line.ar')
            ?: (string) data_get($policeNotice, 'company_name.ar')
            ?: (string) data_get($pdfHeader, 'company_name.ar')
            ?: $this->resolveCompanyName($carViolation);
        $licenseNumber = (string) data_get($pdfHeader, 'cr_number') ?: '';
        $address = (string) data_get($policeNotice, 'company_address.ar') ?: '';
        $phone = (string) data_get($policeNotice, 'company_phone.ar') ?: (string) data_get($policeNotice, 'company_phone.en') ?: '';
        $department = (string) data_get($pdfHeader, 'registry_label.ar')
            ?: (string) data_get($policeNotice, 'registry_label.ar')
            ?: 'شرطة غزة';

        $carViolation->loadMissing([
            'car:id,year,make,model,license_plate,color,branch_id',
            'branch:id,name,phone_1,phone_2,whatsapp',
            'reservation:id,reservation_number,car_id,user_id,start_date,end_date,pickup_time,return_time',
            'reservation.user:id,name,email',
            'reservation.car:id,year,make,model,license_plate',
            'reservation.contract:id,reservation_id,contract_number,contract_date,renter_name,renter_phone,renter_id_number,start_date,end_date,plate_number',
            'violationType:id,name',
            'issuedTo:id,name,email',
        ]);

        $reservation = $carViolation->reservation;
        $contract = $reservation?->contract;
        $car = $carViolation->car ?? $reservation?->car;
        $renter = $reservation?->user;
        $branch = $carViolation->branch ?? $car?->branch;

        return Inertia::render('Admin/CarViolations/Notice', [
            'violation' => [
                'id' => $carViolation->id,
                'violation_number' => $carViolation->violation_number ?: ('VIOL-'.$carViolation->id),
                'violation_date' => optional($carViolation->violation_date)?->toDateString(),
                'authority' => $carViolation->authority,
                'location' => $carViolation->location,
                'amount' => (float) $carViolation->amount,
                'status' => $carViolation->status instanceof CarViolationStatus ? $carViolation->status->value : (string) $carViolation->status,
                'type' => $carViolation->violationType?->name ?? $carViolation->type,
                'car' => $car ? trim(($car->year ? $car->year.' ' : '').($car->make ?? '').' '.($car->model ?? '')) : '-',
                'plate_number' => $contract?->plate_number ?: $car?->license_plate ?: '-',
                'car_color' => $car?->color ?? '-',
                'contract_number' => $contract?->contract_number ?: '-',
                'contract_date' => optional($contract?->contract_date)?->toDateString(),
                'reservation_number' => $reservation?->reservation_number ?: '-',
                'renter_name' => $contract?->renter_name ?: ($renter?->name ?? '-'),
                'renter_phone' => $contract?->renter_phone ?: '-',
                'renter_id' => $contract?->renter_id_number ?: '-',
                'rental_period' => collect([
                    optional($contract?->start_date)?->toDateString(),
                    optional($contract?->end_date)?->toDateString(),
                ])->filter()->implode(' - ') ?: '-',
                'branch_name' => $branch?->name ?? '-',
            ],
            'settings' => [
                'pdf_header' => $pdfHeader,
                'police_notice' => $policeNotice,
            ],
            'defaults' => [
                'office_line' => $officeLine,
                'license_number' => $licenseNumber,
                'address' => $address,
                'phone' => $phone,
                'department' => $department,
                'country_en' => (string) data_get($pdfHeader, 'country.en') ?: 'Sultanate of Oman',
                'country_ar' => (string) data_get($pdfHeader, 'country.ar') ?: 'سلطنة عمان',
            ],
            'actions' => [
                'save_defaults' => route('admin.car-violations.notice.update', $carViolation),
                'download_pdf' => route('admin.car-violations.notice.pdf', $carViolation),
                'print_pdf' => route('admin.car-violations.notice.print', $carViolation),
                'back_url' => route('admin.car-violations.index'),
            ],
        ]);
    }

    public function noticeUpdate(Request $request, CarViolation $carViolation): RedirectResponse
    {
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $carViolation->branch_id ? (int) $carViolation->branch_id : null), 403);

        if (config('app.demo_mode')) {
            return back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $validated = $request->validate([
            'police_notice.office_line.ar' => ['nullable', 'string', 'max:255'],
            'pdf_header.cr_number' => ['nullable', 'string', 'max:100'],
            'pdf_header.country.en' => ['nullable', 'string', 'max:255'],
            'pdf_header.country.ar' => ['nullable', 'string', 'max:255'],
            'police_notice.company_address.ar' => ['nullable', 'string', 'max:500'],
            'police_notice.company_phone.ar' => ['nullable', 'string', 'max:100'],
            'pdf_header.registry_label.ar' => ['nullable', 'string', 'max:100'],
        ]);

        $tenant = $carViolation->tenant;
        abort_unless($tenant, 404);

        $siteSetting = TenantSiteSetting::firstOrCreate(['tenant_id' => $tenant->id]);
        $existing = TenantSiteSetting::forTenant($tenant);

        $pdfHeader = data_get($existing, 'pdf_header', []);
        $policeNotice = data_get($existing, 'police_notice', []);

        data_set($policeNotice, 'office_line.ar', $this->nullableString(data_get($validated, 'police_notice.office_line.ar')));
        data_set($pdfHeader, 'cr_number', $this->nullableString(data_get($validated, 'pdf_header.cr_number')));
        data_set($pdfHeader, 'country.en', $this->nullableString(data_get($validated, 'pdf_header.country.en')));
        data_set($pdfHeader, 'country.ar', $this->nullableString(data_get($validated, 'pdf_header.country.ar')));
        data_set($policeNotice, 'company_address.ar', $this->nullableString(data_get($validated, 'police_notice.company_address.ar')));
        data_set($policeNotice, 'company_phone.ar', $this->nullableString(data_get($validated, 'police_notice.company_phone.ar')));
        data_set($pdfHeader, 'registry_label.ar', $this->nullableString(data_get($validated, 'pdf_header.registry_label.ar')));

        $siteSetting->update([
            'pdf_header' => $pdfHeader,
            'police_notice' => $policeNotice,
        ]);

        return back()->with('success', 'Police notice defaults updated successfully.');
    }

    public function destroy(Request $request, CarViolation $carViolation): RedirectResponse
    {
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $carViolation->branch_id ? (int) $carViolation->branch_id : null), 403);

        if (config('app.demo_mode')) {
            return back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $carViolation->delete();

        return back()->with('success', 'Car violation deleted successfully.');
    }

    private function validateViolation(Request $request, ?CarViolation $carViolation = null): array
    {
        $tenantId = (int) (TenantContext::id() ?? $request->user()?->tenant_id ?? 0);

        return $request->validate([
            'car_id' => ['required', 'integer', Rule::exists('cars', 'id')],
            'reservation_id' => [
                'nullable',
                'integer',
                Rule::exists('reservations', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'violation_type_id' => [
                'required',
                'integer',
                Rule::exists('violation_types', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'branch_owner_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)->where('role', UserRole::ADMIN->value)),
            ],
            'violation_number' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('car_violations', 'violation_number')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId))
                    ->ignore($carViolation?->id),
            ],
            'violation_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'string', Rule::enum(CarViolationStatus::class)],
            'due_date' => ['nullable', 'date'],
            'paid_at' => ['nullable', 'date'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'authority' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function resolveAccessibleCar(Request $request, int $carId): Car
    {
        $query = Car::query()->whereKey($carId);
        $this->branchAccess->applyToQuery($query, $request->user(), null);
        $car = $query->first();

        abort_if(!$car, 422, 'Selected car is not accessible.');

        return $car;
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Request $request, ?CarViolation $carViolation = null): array
    {
        $tenantId = (int) (TenantContext::id() ?? $request->user()?->tenant_id ?? 0);
        $user = $request->user();

        $carsQuery = Car::query()->select(['id', 'year', 'make', 'model', 'license_plate', 'branch_id']);
        $this->branchAccess->applyToQuery($carsQuery, $user, null);
        $cars = $carsQuery
            ->orderBy('make')
            ->orderBy('model')
            ->get()
            ->map(fn (Car $car) => [
                'id' => $car->id,
                'label' => trim("{$car->year} {$car->make} {$car->model} ({$car->license_plate})"),
                'branch_id' => $car->branch_id,
            ])
            ->values();

        $branchOwners = User::query()
            ->select(['id', 'name', 'email', 'branch_id'])
            ->where('tenant_id', $tenantId)
            ->where('role', UserRole::ADMIN->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (User $branchOwner) => [
                'id' => $branchOwner->id,
                'label' => trim($branchOwner->name.' ('.$branchOwner->email.')'),
                'branch_id' => $branchOwner->branch_id,
            ])
            ->values();

        $recentCutoff = Carbon::now()->subMonths(6)->startOfDay();
        $reservations = Reservation::query()
            ->select(['id', 'reservation_number', 'car_id', 'user_id', 'start_date', 'end_date'])
            ->where('tenant_id', $tenantId)
            ->whereDate('end_date', '>=', $recentCutoff->toDateString())
            ->with([
                'user:id,name,email',
                'car:id,year,make,model,license_plate',
                'contract:id,reservation_id,contract_number,contract_date,renter_name,renter_phone,plate_number,start_date,end_date',
            ])
            ->latest('end_date')
            ->limit(300)
            ->get()
            ->map(fn (Reservation $reservation) => [
                'id' => $reservation->id,
                'label' => $this->reservationOptionLabel($reservation),
                'car_id' => $reservation->car_id,
                'car_label' => $reservation->car
                    ? trim("{$reservation->car->year} {$reservation->car->make} {$reservation->car->model} ({$reservation->car->license_plate})")
                    : null,
                'user_id' => $reservation->user_id,
                'user_label' => $reservation->user ? trim($reservation->user->name.' ('.$reservation->user->email.')') : null,
                'contract_id' => $reservation->contract?->id,
                'contract_number' => $reservation->contract?->contract_number,
                'contract_date' => optional($reservation->contract?->contract_date)?->toDateString(),
                'renter_name' => $reservation->contract?->renter_name ?? $reservation->user?->name,
                'renter_phone' => $reservation->contract?->renter_phone,
                'rental_period' => collect([
                    optional($reservation->contract?->start_date)?->toDateString(),
                    optional($reservation->contract?->end_date)?->toDateString(),
                ])->filter()->implode(' - '),
            ])
            ->values();

        if ($carViolation?->reservation_id && !$reservations->contains(fn ($reservation) => (int) $reservation['id'] === (int) $carViolation->reservation_id)) {
            $currentReservation = Reservation::query()
                ->select(['id', 'reservation_number', 'car_id', 'user_id', 'start_date'])
                ->with([
                    'user:id,name,email',
                    'car:id,year,make,model,license_plate',
                    'contract:id,reservation_id,contract_number,contract_date,renter_name,renter_phone,plate_number,start_date,end_date',
                ])
                ->where('tenant_id', $tenantId)
                ->find($carViolation->reservation_id);

            if ($currentReservation) {
                $reservations->push([
                    'id' => $currentReservation->id,
                    'label' => $this->reservationOptionLabel($currentReservation),
                    'car_id' => $currentReservation->car_id,
                    'car_label' => $currentReservation->car
                        ? trim("{$currentReservation->car->year} {$currentReservation->car->make} {$currentReservation->car->model} ({$currentReservation->car->license_plate})")
                        : null,
                    'user_id' => $currentReservation->user_id,
                    'user_label' => $currentReservation->user ? trim($currentReservation->user->name.' ('.$currentReservation->user->email.')') : null,
                    'contract_id' => $currentReservation->contract?->id,
                    'contract_number' => $currentReservation->contract?->contract_number,
                    'contract_date' => optional($currentReservation->contract?->contract_date)?->toDateString(),
                    'renter_name' => $currentReservation->contract?->renter_name ?? $currentReservation->user?->name,
                    'renter_phone' => $currentReservation->contract?->renter_phone,
                    'rental_period' => collect([
                        optional($currentReservation->contract?->start_date)?->toDateString(),
                        optional($currentReservation->contract?->end_date)?->toDateString(),
                    ])->filter()->implode(' - '),
                ]);
            }
        }

        if (
            $carViolation?->issued_to_user_id
            && !$carViolation->reservation_id
            && !$branchOwners->contains(fn ($branchOwner) => (int) $branchOwner['id'] === (int) $carViolation->issued_to_user_id)
        ) {
            $currentBranchOwner = User::query()
                ->select(['id', 'name', 'email', 'branch_id'])
                ->where('tenant_id', $tenantId)
                ->whereKey($carViolation->issued_to_user_id)
                ->first();

            if ($currentBranchOwner) {
                $branchOwners->push([
                    'id' => $currentBranchOwner->id,
                    'label' => trim($currentBranchOwner->name.' ('.$currentBranchOwner->email.')'),
                    'branch_id' => $currentBranchOwner->branch_id,
                ]);
            }
        }

        $statuses = collect(CarViolationStatus::cases())->map(fn ($statusCase) => [
            'value' => $statusCase->value,
            'label' => $statusCase->label(),
            'color' => $statusCase->color(),
        ])->values();

        $violationTypes = ViolationType::query()
            ->select(['id', 'name'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (ViolationType $violationType) => [
                'id' => $violationType->id,
                'label' => $violationType->name,
            ])
            ->values();

        if (
            $carViolation?->violation_type_id
            && !$violationTypes->contains(fn ($type) => (int) $type['id'] === (int) $carViolation->violation_type_id)
        ) {
            $currentViolationType = ViolationType::query()
                ->select(['id', 'name'])
                ->find($carViolation->violation_type_id);

            if ($currentViolationType) {
                $violationTypes->push([
                    'id' => $currentViolationType->id,
                    'label' => $currentViolationType->name,
                ]);
            }
        }

        return [
            'cars' => $cars,
            'branchOwners' => $branchOwners->values(),
            'reservations' => $reservations,
            'violationTypes' => $violationTypes->values(),
            'statuses' => $statuses,
        ];
    }

    private function resolveAccessibleReservation(Request $request, Car $car, mixed $reservationId, ?CarViolation $carViolation = null): ?Reservation
    {
        if ($reservationId === null || $reservationId === '') {
            return null;
        }

        $reservation = Reservation::query()
            ->where('tenant_id', (int) (TenantContext::id() ?? $request->user()?->tenant_id ?? 0))
            ->where('car_id', $car->id)
            ->whereKey((int) $reservationId)
            ->first();

        if (!$reservation) {
            throw ValidationException::withMessages([
                'reservation_id' => 'Selected reservation is invalid for this car.',
            ]);
        }

        $recentCutoff = Carbon::now()->subMonths(6)->startOfDay();
        $isCurrentViolationReservation = $carViolation && (int) $carViolation->reservation_id === (int) $reservation->id;

        if (!$isCurrentViolationReservation && optional($reservation->end_date)->lt($recentCutoff)) {
            throw ValidationException::withMessages([
                'reservation_id' => 'Selected reservation must be within the last 6 months.',
            ]);
        }

        return $reservation;
    }

    private function resolveIssuedToUserId(Request $request, Car $car, ?Reservation $reservation, mixed $branchOwnerUserId): ?int
    {
        if ($reservation) {
            if (empty($reservation->user_id)) {
                throw ValidationException::withMessages([
                    'reservation_id' => 'Selected reservation has no linked user.',
                ]);
            }

            return (int) $reservation->user_id;
        }

        $branchOwnerId = is_numeric($branchOwnerUserId) ? (int) $branchOwnerUserId : null;
        if (!$branchOwnerId) {
            throw ValidationException::withMessages([
                'branch_owner_user_id' => 'Please select a branch owner when no reservation is linked.',
            ]);
        }

        $query = User::query()
            ->where('tenant_id', (int) (TenantContext::id() ?? $request->user()?->tenant_id ?? 0))
            ->where('role', UserRole::ADMIN->value)
            ->where('is_active', true)
            ->whereKey($branchOwnerId);

        if ($car->branch_id) {
            $query->where(function ($builder) use ($car) {
                $builder->where('branch_id', $car->branch_id)
                    ->orWhereNull('branch_id');
            });
        }

        $branchOwner = $query->first();
        if (!$branchOwner) {
            throw ValidationException::withMessages([
                'branch_owner_user_id' => 'Selected branch owner is invalid for this car branch.',
            ]);
        }

        return (int) $branchOwner->id;
    }

    private function reservationOptionLabel(Reservation $reservation): string
    {
        $number = $reservation->reservation_number ?: ('Reservation #'.$reservation->id);
        $date = optional($reservation->start_date)?->format('Y-m-d');
        $clientName = trim((string) optional($reservation->user)->name);

        return collect([$number, $date, $clientName])
            ->filter(fn ($value) => filled($value))
            ->implode(' - ');
    }

    private function resolveViolationType(Request $request, int $violationTypeId): ViolationType
    {
        $violationType = ViolationType::query()
            ->where('tenant_id', (int) (TenantContext::id() ?? $request->user()?->tenant_id ?? 0))
            ->find($violationTypeId);

        if (!$violationType) {
            throw ValidationException::withMessages([
                'violation_type_id' => 'Selected violation type is invalid.',
            ]);
        }

        return $violationType;
    }

    private function renderNoticePdf(Request $request, CarViolation $carViolation, bool $download)
    {
        $tenant = $carViolation->tenant;
        $settings = $tenant ? \App\Models\TenantSiteSetting::forTenant($tenant) : [];
        $pdfHeader = array_replace_recursive(
            data_get($settings, 'pdf_header', []),
            (array) $request->input('pdf_header', [])
        );
        $policeNotice = array_replace_recursive(
            data_get($settings, 'police_notice', []),
            (array) $request->input('police_notice', [])
        );

        $carViolation->loadMissing([
            'car:id,year,make,model,license_plate,branch_id',
            'car.branch:id,name,phone_1,phone_2,whatsapp',
            'branch:id,name,phone_1,phone_2,whatsapp',
            'reservation:id,reservation_number,car_id,user_id,start_date,end_date,pickup_time,return_time',
            'reservation.user:id,name,email',
            'reservation.car:id,year,make,model,license_plate',
            'reservation.contract:id,reservation_id,contract_number,contract_date,renter_name,renter_phone,renter_id_number,vehicle_odometer,vehicle_fuel_level,plate_number,price_per_day,price_per_week,price_per_month,allowed_km_per_day,allowed_km_per_week,allowed_km_per_month',
            'violationType:id,name',
            'issuedTo:id,name,email',
            'creator:id,name,email',
        ]);

        $reservation = $carViolation->reservation;
        $contract = $reservation?->contract;
        $car = $carViolation->car ?? $reservation?->car;
        $renter = $reservation?->user;
        $branch = $carViolation->branch ?? $car?->branch;

        $viewData = [
            'violation' => $carViolation,
            'reservation' => $reservation,
            'contract' => $contract,
            'car' => $car,
            'renter' => $renter,
            'branch' => $branch,
            'companyName' => $this->resolveCompanyName($carViolation),
            'companyNameArabic' => (string) data_get($policeNotice, 'company_name.ar')
                ?: (string) data_get($pdfHeader, 'company_name.ar')
                ?: (string) data_get($pdfHeader, 'company_name.en')
                ?: $this->resolveCompanyName($carViolation),
            'companyLogo' => $this->resolveCompanyLogo($carViolation),
            'generatedAt' => now(),
            'pdfHeader' => $pdfHeader,
            'policeNotice' => $policeNotice,
        ];

        $fileName = ($carViolation->violation_number ?: ('violation-'.$carViolation->id)).'-police-notice.pdf';

        if (PdfRuntime::hasNodeBinary()) {
            try {
                $pdf = Pdf::view('admin.car_violations.notice', $viewData)
                    ->format('a4')
                    ->orientation('portrait');

                return $download ? $pdf->download($fileName) : $pdf->inline($fileName);
            } catch (Throwable $e) {
                report($e);
            }
        }

        $pdf = DomPdf::loadView('admin.car_violations.notice', $viewData)
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isRemoteEnabled', true)
            ->setPaper('a4', 'portrait');

        return $download ? $pdf->download($fileName) : $pdf->stream($fileName);
    }

    private function resolveCompanyName(CarViolation $carViolation): string
    {
        $tenant = $carViolation->tenant;
        $settings = $tenant ? \App\Models\TenantSiteSetting::forTenant($tenant) : [];

        return (string) data_get($settings, 'company.name')
            ?: (string) $tenant?->name
            ?: config('app.name');
    }

    private function resolveCompanyLogo(CarViolation $carViolation): ?string
    {
        $tenant = $carViolation->tenant;
        $settings = $tenant ? \App\Models\TenantSiteSetting::forTenant($tenant) : [];

        $logo = data_get($settings, 'logo_url')
            ?: data_get($settings, 'branding.logo_url')
            ?: data_get($settings, 'branding.logo');

        if (! filled($logo)) {
            return null;
        }

        $logo = trim((string) $logo);

        if (preg_match('#^https?://#i', $logo)) {
            return $logo;
        }

        $relative = $logo;
        if (str_starts_with($relative, '/')) {
            $relative = ltrim($relative, '/');
        }
        if (str_starts_with($relative, 'storage/')) {
            $relative = substr($relative, strlen('storage/'));
            $localPath = public_path('storage/'.$relative);
        } elseif (str_starts_with($relative, 'app/')) {
            $localPath = storage_path('app/'.substr($relative, strlen('app/')));
        } else {
            $localPath = public_path($relative);
        }

        if (is_file($localPath)) {
            $mime = @mime_content_type($localPath) ?: 'image/png';
            $content = file_get_contents($localPath);
            if ($content !== false) {
                return 'data:'.$mime.';base64,'.base64_encode($content);
            }
        }

        return url('/'.ltrim($logo, '/'));
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
