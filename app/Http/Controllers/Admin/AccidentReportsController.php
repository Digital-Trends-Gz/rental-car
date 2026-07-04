<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\UserRole;
use App\Models\AccidentReport;
use App\Models\Car;
use App\Models\Contract;
use App\Models\TenantSiteSetting;
use App\Models\User;
use App\Core\MrtaPdfSettings;
use App\Support\BranchAccess;
use App\Support\PdfRuntime;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;
use Throwable;

class AccidentReportsController extends Controller
{
    public function __construct(private BranchAccess $branchAccess)
    {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $requestedBranchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);

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

        $query = AccidentReport::query()
            ->with([
                'contract:id,contract_number,renter_name,branch_id',
                'reservation:id,reservation_number',
                'car:id,make,model,year,license_plate',
                'branch:id,name',
                'reporter:id,name',
                'employee:id,name',
            ])
            ->withCount('photos');

        $this->branchAccess->applyToQuery($query, $user, $branchId, 'branch_id');

        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('accident_number', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('police_report_number', 'like', "%{$search}%")
                    ->orWhereHas('contract', fn (Builder $contractQuery) => $contractQuery
                        ->where('contract_number', 'like', "%{$search}%")
                        ->orWhere('renter_name', 'like', "%{$search}%"))
                    ->orWhereHas('reservation', fn (Builder $reservationQuery) => $reservationQuery
                        ->where('reservation_number', 'like', "%{$search}%"))
                    ->orWhereHas('car', function (Builder $carQuery) use ($search): void {
                        $carQuery->where('make', 'like', "%{$search}%")
                            ->orWhere('model', 'like', "%{$search}%")
                            ->orWhere('license_plate', 'like', "%{$search}%");
                    });
            });
        }

        $reports = $query
            ->latest('accident_at')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        $reports->getCollection()->transform(fn (AccidentReport $report) => [
            'id' => $report->id,
            'accident_number' => $report->accident_number,
            'status' => $report->status,
            'status_label' => $this->statusLabel($report->status),
            'status_color' => $this->statusColor($report->status),
            'accident_context' => $report->accident_context ?? 'contract',
            'accident_context_label' => $this->contextLabel($report->accident_context ?? 'contract'),
            'contract_number' => $report->contract?->contract_number,
            'reservation_number' => $report->reservation?->reservation_number,
            'renter_name' => $report->contract?->renter_name,
            'employee_name' => $report->employee?->name,
            'car' => $this->carLabel($report),
            'branch' => $report->branch?->name ?? '-',
            'location' => $report->location,
            'accident_at' => optional($report->accident_at)?->format('Y-m-d H:i'),
            'photos_count' => (int) $report->photos_count,
            'show_url' => route('admin.accident-reports.show', $this->routeParams($request, ['accident_report' => $report])),
        ]);

        return Inertia::render('Admin/AccidentReports/Index', [
            'reports' => $reports,
            'statuses' => $this->statusOptions(),
            'branches' => $branchOptions,
            'canAccessAllBranches' => $canAccessAllBranches,
            'filters' => [
                'search' => $search,
                'status' => $status === '' ? 'all' : $status,
                'branch_id' => $branchId,
            ],
            'indexUrl' => route('admin.accident-reports.index', $this->routeParams($request)),
            'createUrl' => route('admin.accident-reports.create', $this->routeParams($request)),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/AccidentReports/Create', [
            'contracts' => $this->contractOptions($request),
            'cars' => $this->carOptions($request),
            'branches' => $this->branchOptions($request),
            'employees' => $this->employeeOptions($request),
            'statuses' => $this->statusOptions(),
            'responsibilities' => $this->responsibilityOptions(),
            'locationTypes' => $this->locationTypeOptions(),
            'indexUrl' => route('admin.accident-reports.index', $this->routeParams($request)),
            'submitUrl' => route('admin.accident-reports.store', $this->routeParams($request)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->normalizeBooleanInputs($request, ['has_injuries', 'third_party_involved']);

        $validated = $request->validate([
            'accident_context' => ['required', Rule::in(['contract', 'employee', 'branch'])],
            'contract_id' => ['nullable', 'integer', 'required_if:accident_context,contract'],
            'car_id' => ['nullable', 'integer', 'required_unless:accident_context,contract'],
            'branch_id' => ['nullable', 'integer', 'required_unless:accident_context,contract'],
            'employee_id' => ['nullable', 'integer', 'required_if:accident_context,employee'],
            'responsibility' => ['nullable', Rule::in(['customer', 'employee', 'company', 'third_party', 'unknown'])],
            'location_type' => ['nullable', Rule::in(['road', 'branch_gate', 'parking', 'workshop', 'other'])],
            'accident_at' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'description' => ['required', 'string', 'max:3000'],
            'police_report_number' => ['nullable', 'string', 'max:100'],
            'has_injuries' => ['nullable', 'boolean'],
            'third_party_involved' => ['nullable', 'boolean'],
            'third_party_name' => ['nullable', 'string', 'max:255'],
            'third_party_phone' => ['nullable', 'string', 'max:100'],
            'third_party_plate_number' => ['nullable', 'string', 'max:100'],
            'mrta_accident_types' => ['nullable'],
            'mrta_first_party' => ['nullable'],
            'mrta_second_party' => ['nullable'],
            'mrta_witnesses' => ['nullable'],
            'mrta_accident_causes' => ['nullable'],
            'mrta_vehicle_damages' => ['nullable'],
            'mrta_insurance' => ['nullable'],
            'mrta_signatures' => ['nullable'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'photos' => ['nullable', 'array', 'max:10'],
            'photo_types' => ['nullable', 'array'],
            'photo_types.*' => ['nullable', 'string', 'max:50'],
            'photo_notes' => ['nullable', 'array'],
            'photo_notes.*' => ['nullable', 'string', 'max:1000'],
        ]);

        if (config('app.demo_mode')) {
            return back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $files = $this->uploadedPhotoFiles($request);
        $this->validateUploadedPhotos($files);

        $context = (string) $validated['accident_context'];
        $contract = null;
        $reservationId = null;
        $carId = null;
        $branchId = null;
        $employeeId = null;

        if ($context === 'contract') {
            $contract = $this->resolveAccessibleContract($request, (int) $validated['contract_id']);
            $contract->loadMissing(['reservation:id,reservation_number,car_id', 'reservation.car:id,year,make,model,license_plate']);

            $reservationId = $contract->reservation_id;
            $carId = $contract->reservation?->car_id;
            $branchId = $contract->branch_id;
        } else {
            $branchId = $this->resolveAccessibleBranchId($request, (int) $validated['branch_id']);
            $car = $this->resolveAccessibleCar($request, (int) $validated['car_id'], $branchId);
            $carId = $car->id;

            if ($context === 'employee') {
                $employee = $this->resolveAccessibleEmployee($request, (int) $validated['employee_id']);
                $employeeId = $employee->id;
            }
        }

        $report = DB::transaction(function () use ($request, $validated, $context, $contract, $reservationId, $carId, $branchId, $employeeId, $files): AccidentReport {
            $report = AccidentReport::create([
                'contract_id' => $contract?->id,
                'reservation_id' => $reservationId,
                'car_id' => $carId,
                'branch_id' => $branchId,
                'reported_by' => $request->user()?->id,
                'employee_id' => $employeeId,
                'accident_context' => $context,
                'responsibility' => $validated['responsibility'] ?? ($context === 'contract' ? 'customer' : 'unknown'),
                'location_type' => $validated['location_type'] ?? null,
                'accident_number' => $this->generateAccidentNumber(),
                'status' => 'reported',
                'accident_at' => $validated['accident_at'] ?? null,
                'location' => $validated['location'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'description' => $validated['description'],
                'police_report_number' => $validated['police_report_number'] ?? null,
                'has_injuries' => $request->boolean('has_injuries'),
                'third_party_involved' => $request->boolean('third_party_involved'),
                'third_party_details' => $this->thirdPartyDetails($validated),
                'mrta_accident_types' => $this->normalizeStringList($validated['mrta_accident_types'] ?? null),
                'mrta_first_party' => $this->normalizeJsonObject($validated['mrta_first_party'] ?? null),
                'mrta_second_party' => $this->normalizeJsonObject($validated['mrta_second_party'] ?? null),
                'mrta_witnesses' => $this->normalizeJsonList($validated['mrta_witnesses'] ?? null),
                'mrta_accident_causes' => $this->normalizeStringList($validated['mrta_accident_causes'] ?? null),
                'mrta_vehicle_damages' => $this->normalizeJsonObject($validated['mrta_vehicle_damages'] ?? null),
                'mrta_insurance' => $this->normalizeJsonObject($validated['mrta_insurance'] ?? null),
                'mrta_signatures' => $this->normalizeJsonObject($validated['mrta_signatures'] ?? null),
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->storePhotos($report, $files, $request->input('photo_types', []), $request->input('photo_notes', []));

            return $report;
        });

        return redirect()
            ->route('admin.accident-reports.show', $this->routeParams($request, ['accident_report' => $report]))
            ->with('success', 'Accident report created successfully.');
    }

    public function show(Request $request, AccidentReport $accidentReport): Response
    {
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $accidentReport->branch_id ? (int) $accidentReport->branch_id : null), 403);

        $accidentReport->loadMissing([
            'photos',
            'contract:id,contract_number,renter_name,renter_phone,renter_id_number,branch_id',
            'reservation:id,reservation_number,start_date,end_date',
            'car:id,year,make,model,license_plate',
            'branch:id,name',
            'reporter:id,name,email',
            'employee:id,name,email',
        ]);

        return Inertia::render('Admin/AccidentReports/Show', [
            'report' => [
                'id' => $accidentReport->id,
                'accident_number' => $accidentReport->accident_number,
                'status' => $accidentReport->status,
                'status_label' => $this->statusLabel($accidentReport->status),
                'status_color' => $this->statusColor($accidentReport->status),
                'accident_context' => $accidentReport->accident_context ?? 'contract',
                'accident_context_label' => $this->contextLabel($accidentReport->accident_context ?? 'contract'),
                'responsibility' => $accidentReport->responsibility,
                'responsibility_label' => $this->responsibilityLabel($accidentReport->responsibility),
                'location_type' => $accidentReport->location_type,
                'location_type_label' => $this->locationTypeLabel($accidentReport->location_type),
                'contract_number' => $accidentReport->contract?->contract_number,
                'reservation_number' => $accidentReport->reservation?->reservation_number,
                'renter_name' => $accidentReport->contract?->renter_name,
                'renter_phone' => $accidentReport->contract?->renter_phone,
                'renter_id_number' => $accidentReport->contract?->renter_id_number,
                'car' => $this->carLabel($accidentReport),
                'branch' => $accidentReport->branch?->name,
                'reported_by' => $accidentReport->reporter?->name,
                'reported_by_email' => $accidentReport->reporter?->email,
                'employee_name' => $accidentReport->employee?->name,
                'employee_email' => $accidentReport->employee?->email,
                'accident_at' => optional($accidentReport->accident_at)?->format('Y-m-d H:i'),
                'location' => $accidentReport->location,
                'latitude' => $accidentReport->latitude,
                'longitude' => $accidentReport->longitude,
                'description' => $accidentReport->description,
                'police_report_number' => $accidentReport->police_report_number,
                'has_injuries' => $accidentReport->has_injuries,
                'third_party_involved' => $accidentReport->third_party_involved,
                'third_party_details' => $accidentReport->third_party_details,
                'mrta' => $this->mrtaPayload($accidentReport),
                'mrta_pdf_url' => route('admin.accident-reports.mrta-form', $this->routeParams($request, ['accidentReport' => $accidentReport])),
                'notes' => $accidentReport->notes,
                'photos' => $accidentReport->photos->map(fn ($photo) => [
                    'id' => $photo->id,
                    'photo_type' => $photo->photo_type,
                    'file_name' => $photo->file_name,
                    'mime_type' => $photo->mime_type,
                    'size' => $photo->size,
                    'notes' => $photo->notes,
                    'url' => $this->fileUrl($photo->file_path),
                ])->values(),
                'created_at' => optional($accidentReport->created_at)?->format('Y-m-d H:i'),
            ],
            'indexUrl' => route('admin.accident-reports.index', $this->routeParams($request)),
        ]);
    }

    public function mrtaForm(Request $request, AccidentReport $accidentReport)
    {
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $accidentReport->branch_id ? (int) $accidentReport->branch_id : null), 403);

        $accidentReport->loadMissing([
            'photos',
            'contract:id,contract_number,renter_name,renter_phone,renter_id_number,branch_id',
            'reservation:id,reservation_number,start_date,end_date',
            'car:id,year,make,model,license_plate',
            'branch:id,name',
            'reporter:id,name,email',
            'employee:id,name,email',
        ]);

        PdfRuntime::ensureDompdfDirectories();

        $fileName = sprintf('mrta-accident-%s.pdf', $accidentReport->accident_number ?: $accidentReport->id);
        $viewData = [
            'report' => $accidentReport,
            'payload' => $this->mrtaPayload($accidentReport),
            'mrtaPdfSettings' => MrtaPdfSettings::forTenantSiteSetting(
                TenantSiteSetting::query()
                    ->with('files')
                    ->where('tenant_id', $accidentReport->tenant_id)
                    ->first()
            ),
        ];

        if (PdfRuntime::canUseBrowsershot()) {
            try {
                $pdf = Pdf::view('admin.accident-reports.mrta-form', $viewData)
                    ->format(Format::A4)
                    ->portrait()
                    ->margins(0, 0, 0, 0)
                    ->withBrowsershot(fn (Browsershot $browsershot) => $this->configureBrowsershot($browsershot));

                return ($request->boolean('download')
                    ? $pdf->download($fileName)
                    : $pdf->inline($fileName))->toResponse($request);
            } catch (Throwable $e) {
                report($e);
            }
        }

        $pdf = DomPdf::loadView('admin.accident-reports.mrta-form', $viewData)
            ->setPaper('a4')
            ->setOption('isRemoteEnabled', true)
            ->setOption('fontDir', PdfRuntime::dompdfFontDirectory())
            ->setOption('fontCache', PdfRuntime::dompdfFontDirectory())
            ->setOption('tempDir', PdfRuntime::dompdfTempDirectory());

        return $request->boolean('download')
            ? $pdf->download($fileName)
            : $pdf->stream($fileName);
    }

    private function configureBrowsershot(Browsershot $browsershot): void
    {
        $nodeBinary = PdfRuntime::nodeBinary();
        if ($nodeBinary) {
            $browsershot->setNodeBinary($nodeBinary);
        }

        $npmBinary = PdfRuntime::npmBinary();
        if ($npmBinary) {
            $browsershot->setNpmBinary($npmBinary);
        }

        $chromePath = PdfRuntime::chromeBinary();
        if ($chromePath) {
            $browsershot->setChromePath($chromePath);
        }

        $browsershot
            ->noSandbox()
            ->addChromiumArguments([
                'disable-dev-shm-usage',
                'disable-gpu',
            ])
            ->setOption('printBackground', true)
            ->setOption('preferCSSPageSize', true)
            ->waitUntilNetworkIdle(false)
            ->timeout(120)
            ->newHeadless();
    }

    private function contractOptions(Request $request)
    {
        $query = Contract::query()
            ->select(['id', 'contract_number', 'reservation_id', 'renter_name', 'renter_phone', 'renter_id_number', 'branch_id'])
            ->with([
                'reservation:id,reservation_number,car_id',
                'reservation.car:id,year,make,model,license_plate',
            ])
            ->latest('id')
            ->limit(300);

        $this->branchAccess->applyToQuery($query, $request->user(), null, 'branch_id');

        return $query->get()->map(fn (Contract $contract) => [
            'id' => $contract->id,
            'label' => collect([
                $contract->contract_number,
                $contract->reservation?->reservation_number,
                $contract->renter_name,
                $contract->reservation?->car
                    ? trim("{$contract->reservation->car->year} {$contract->reservation->car->make} {$contract->reservation->car->model} ({$contract->reservation->car->license_plate})")
                    : null,
            ])->filter()->implode(' - '),
            'contract_number' => $contract->contract_number,
            'reservation_number' => $contract->reservation?->reservation_number,
            'renter_name' => $contract->renter_name,
            'renter_phone' => $contract->renter_phone,
            'renter_id_number' => $contract->renter_id_number,
            'car_license_plate' => $contract->reservation?->car?->license_plate,
            'car' => $contract->reservation?->car
                ? trim("{$contract->reservation->car->year} {$contract->reservation->car->make} {$contract->reservation->car->model} ({$contract->reservation->car->license_plate})")
                : '-',
        ])->values();
    }

    private function carOptions(Request $request)
    {
        $query = Car::query()
            ->select(['id', 'branch_id', 'year', 'make', 'model', 'license_plate'])
            ->with('branch:id,name')
            ->orderBy('make')
            ->orderBy('model')
            ->limit(500);

        $this->branchAccess->applyToQuery($query, $request->user(), null, 'branch_id');

        return $query->get()->map(fn (Car $car) => [
            'id' => $car->id,
            'branch_id' => $car->branch_id,
            'label' => trim("{$car->year} {$car->make} {$car->model} ({$car->license_plate})"),
            'license_plate' => $car->license_plate,
            'branch_name' => $car->branch?->name,
        ])->values();
    }

    private function branchOptions(Request $request)
    {
        return $this->branchAccess
            ->availableBranchesForUser($request->user())
            ->map(fn ($branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
            ])
            ->values();
    }

    private function employeeOptions(Request $request)
    {
        $query = User::query()
            ->select(['id', 'name', 'email', 'branch_id'])
            ->with('branch:id,name')
            ->where('role', UserRole::ADMIN)
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(500);

        $this->branchAccess->applyToQuery($query, $request->user(), null, 'branch_id');

        return $query->get()->map(fn (User $employee) => [
            'id' => $employee->id,
            'name' => $employee->name,
            'email' => $employee->email,
            'branch_id' => $employee->branch_id,
            'branch_name' => $employee->branch?->name,
        ])->values();
    }

    private function responsibilityOptions(): array
    {
        return collect(['customer', 'employee', 'company', 'third_party', 'unknown'])
            ->map(fn (string $value) => [
                'value' => $value,
                'label' => $this->responsibilityLabel($value),
            ])
            ->all();
    }

    private function locationTypeOptions(): array
    {
        return collect(['road', 'branch_gate', 'parking', 'workshop', 'other'])
            ->map(fn (string $value) => [
                'value' => $value,
                'label' => $this->locationTypeLabel($value),
            ])
            ->all();
    }

    private function resolveAccessibleContract(Request $request, int $contractId): Contract
    {
        $query = Contract::query()->whereKey($contractId);
        $this->branchAccess->applyToQuery($query, $request->user(), null, 'branch_id');
        $contract = $query->first();

        if (! $contract) {
            throw ValidationException::withMessages([
                'contract_id' => 'Selected contract is invalid or not accessible.',
            ]);
        }

        return $contract;
    }

    private function resolveAccessibleBranchId(Request $request, int $branchId): int
    {
        if (!$this->branchAccess->canAccessBranchId($request->user(), $branchId)) {
            throw ValidationException::withMessages([
                'branch_id' => 'Selected branch is invalid or not accessible.',
            ]);
        }

        return $branchId;
    }

    private function resolveAccessibleCar(Request $request, int $carId, int $branchId): Car
    {
        $query = Car::query()->whereKey($carId)->where('branch_id', $branchId);
        $this->branchAccess->applyToQuery($query, $request->user(), null, 'branch_id');

        $car = $query->first();

        if (!$car) {
            throw ValidationException::withMessages([
                'car_id' => 'Selected car is invalid or not accessible.',
            ]);
        }

        return $car;
    }

    private function resolveAccessibleEmployee(Request $request, int $employeeId): User
    {
        $query = User::query()
            ->whereKey($employeeId)
            ->where('role', UserRole::ADMIN)
            ->where('is_active', true);

        $this->branchAccess->applyToQuery($query, $request->user(), null, 'branch_id');

        $employee = $query->first();

        if (!$employee) {
            throw ValidationException::withMessages([
                'employee_id' => 'Selected employee is invalid or not accessible.',
            ]);
        }

        return $employee;
    }

    /**
     * @return array<int|string, UploadedFile>
     */
    private function uploadedPhotoFiles(Request $request): array
    {
        $photos = $request->file('photos', []);

        if ($photos instanceof UploadedFile) {
            return [0 => $photos];
        }

        if (! is_array($photos)) {
            return [];
        }

        return array_filter($photos, fn ($photo) => $photo instanceof UploadedFile);
    }

    /**
     * @param array<int|string, UploadedFile> $files
     */
    private function validateUploadedPhotos(array $files): void
    {
        foreach ($files as $index => $file) {
            $validator = Validator::make(
                ['file' => $file],
                ['file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240']]
            );

            if ($validator->fails()) {
                throw ValidationException::withMessages([
                    "photos.$index" => $validator->errors()->first('file'),
                ]);
            }
        }
    }

    /**
     * @param array<int|string, UploadedFile> $files
     */
    private function storePhotos(AccidentReport $report, array $files, array $types, array $notes): void
    {
        foreach ($files as $index => $file) {
            $path = $file->store("accident-reports/{$report->id}", config('vilt-filepond.storage_disk'));

            $report->photos()->create([
                'photo_type' => $types[$index] ?? null,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'notes' => $notes[$index] ?? null,
            ]);
        }
    }

    private function thirdPartyDetails(array $validated): ?array
    {
        $details = [
            'name' => $validated['third_party_name'] ?? null,
            'phone' => $validated['third_party_phone'] ?? null,
            'plate_number' => $validated['third_party_plate_number'] ?? null,
        ];

        $details = array_filter($details, fn ($value) => filled($value));

        return $details === [] ? null : $details;
    }

    /**
     * Laravel's boolean validation accepts real booleans and 1/0, but not
     * string "true"/"false" values sent by multipart form-data clients.
     *
     * @param array<int, string> $fields
     */
    private function normalizeBooleanInputs(Request $request, array $fields): void
    {
        $normalized = [];

        foreach ($fields as $field) {
            if (! $request->has($field)) {
                continue;
            }

            $value = $request->input($field);

            if (is_bool($value) || $value === 0 || $value === 1 || $value === '0' || $value === '1') {
                $normalized[$field] = $value;
                continue;
            }

            if (is_scalar($value)) {
                $boolean = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                if ($boolean !== null) {
                    $normalized[$field] = $boolean;
                }
            }
        }

        if ($normalized !== []) {
            $request->merge($normalized);
        }
    }

    private function mrtaPayload(AccidentReport $report): array
    {
        $firstParty = $this->mergeDefaults($report->mrta_first_party, [
            'vehicle_no' => $report->car?->license_plate,
            'driver_name' => $report->contract?->renter_name,
            'address_tel' => $report->contract?->renter_phone,
            'driving_license_no_category' => $report->contract?->renter_id_number,
            'sex_nationality' => null,
            'insurance_company' => null,
            'insurance_type' => null,
            'insurance_policy_no' => null,
        ]);

        $thirdParty = is_array($report->third_party_details) ? $report->third_party_details : [];
        $secondParty = $this->mergeDefaults($report->mrta_second_party, [
            'vehicle_no' => $thirdParty['plate_number'] ?? null,
            'driver_name' => $thirdParty['name'] ?? null,
            'address_tel' => $thirdParty['phone'] ?? null,
            'driving_license_no_category' => $thirdParty['license_no'] ?? null,
            'sex_nationality' => $thirdParty['nationality'] ?? null,
            'insurance_company' => $thirdParty['insurance_company'] ?? null,
            'insurance_type' => $thirdParty['insurance_type'] ?? null,
            'insurance_policy_no' => $thirdParty['insurance_policy_no'] ?? null,
        ]);

        return [
            'date' => $report->accident_at?->format('Y-m-d'),
            'time' => $report->accident_at?->format('H:i'),
            'location' => $report->location,
            'accident_types' => $this->normalizeStringList($report->mrta_accident_types),
            'first_party' => $firstParty,
            'second_party' => $secondParty,
            'witnesses' => $this->normalizeJsonList($report->mrta_witnesses),
            'accident_causes' => $this->normalizeStringList($report->mrta_accident_causes),
            'vehicle_damages' => $this->normalizeJsonObject($report->mrta_vehicle_damages),
            'insurance' => $this->normalizeJsonObject($report->mrta_insurance),
            'signatures' => $this->normalizeJsonObject($report->mrta_signatures),
        ];
    }

    private function normalizeJsonObject(mixed $value): array
    {
        $normalized = $this->normalizeJsonValue($value);

        return is_array($normalized) ? $normalized : [];
    }

    private function normalizeJsonList(mixed $value): array
    {
        $normalized = $this->normalizeJsonValue($value);

        if (! is_array($normalized)) {
            return [];
        }

        return array_values(array_filter($normalized, fn ($item) => $item !== null && $item !== ''));
    }

    private function normalizeStringList(mixed $value): array
    {
        $normalized = $this->normalizeJsonValue($value);

        if (is_string($normalized)) {
            $normalized = preg_split('/\s*,\s*/', $normalized) ?: [];
        }

        if (! is_array($normalized)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($item) => is_scalar($item) ? trim((string) $item) : null,
            $normalized
        )));
    }

    private function normalizeJsonValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }

        return null;
    }

    private function mergeDefaults(?array $value, array $defaults): array
    {
        return array_replace($defaults, array_filter($value ?? [], fn ($item) => $item !== null && $item !== ''));
    }

    private function generateAccidentNumber(): string
    {
        do {
            $number = 'ACC-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
        } while (AccidentReport::where('accident_number', $number)->exists());

        return $number;
    }

    private function carLabel(AccidentReport $report): string
    {
        return $report->car
            ? trim("{$report->car->year} {$report->car->make} {$report->car->model} ({$report->car->license_plate})")
            : '-';
    }

    private function fileUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $normalized = ltrim((string) preg_replace('/^storage\//', '', $path), '/');

        if (preg_match('/^https?:\/\//i', $normalized)) {
            return $normalized;
        }

        return Storage::disk(config('vilt-filepond.storage_disk'))->url($normalized);
    }

    private function statusOptions(): array
    {
        return collect(['reported', 'under_review', 'resolved', 'rejected'])
            ->map(fn (string $status) => [
                'value' => $status,
                'label' => $this->statusLabel($status),
                'color' => $this->statusColor($status),
            ])
            ->all();
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'reported' => 'Reported',
            'under_review' => 'Under review',
            'resolved' => 'Resolved',
            'rejected' => 'Rejected',
            default => Str::headline($status),
        };
    }

    private function statusColor(string $status): string
    {
        return match ($status) {
            'reported' => '#F59E0B',
            'under_review' => '#2563EB',
            'resolved' => '#059669',
            'rejected' => '#DC2626',
            default => '#6B7280',
        };
    }

    private function contextLabel(?string $context): string
    {
        return match ($context) {
            'employee' => 'With employee',
            'branch' => 'At office or gate',
            default => 'With customer',
        };
    }

    private function responsibilityLabel(?string $responsibility): string
    {
        return match ($responsibility) {
            'customer' => 'Customer',
            'employee' => 'Employee',
            'company' => 'Company',
            'third_party' => 'Third party',
            'unknown' => 'Unknown',
            default => '-',
        };
    }

    private function locationTypeLabel(?string $locationType): string
    {
        return match ($locationType) {
            'road' => 'Road',
            'branch_gate' => 'Branch gate',
            'parking' => 'Parking',
            'workshop' => 'Workshop',
            'other' => 'Other',
            default => '-',
        };
    }

    private function routeParams(Request $request, array $params = []): array
    {
        $subdomain = $request->route('subdomain') ?? $request->input('subdomain');

        if ($subdomain) {
            return ['subdomain' => $subdomain] + $params;
        }

        return $params;
    }
}
