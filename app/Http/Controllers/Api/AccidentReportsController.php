<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Core\MrtaPdfSettings;
use App\Http\Controllers\Controller;
use App\Models\AccidentReport;
use App\Models\Car;
use App\Models\Contract;
use App\Models\TenantSiteSetting;
use App\Models\User;
use App\Support\BranchAccess;
use App\Support\PdfRuntime;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AccidentReportsController extends Controller
{
    public function __construct(private readonly BranchAccess $branchAccess)
    {
    }

    public function options(Request $request): JsonResponse
    {
        $user = $this->authorizeAdminApiUser($request);
        $branchId = $this->resolveBranchId($request, $user);

        return response()->json([
            'contexts' => [
                ['value' => 'contract', 'label' => $this->localized($request, 'With customer', 'مع العميل')],
                ['value' => 'employee', 'label' => $this->localized($request, 'With employee', 'مع موظف')],
                ['value' => 'branch', 'label' => $this->localized($request, 'At office or gate', 'عند المكتب أو البوابة')],
            ],
            'contexts' => $this->contextOptionItems($request),
            'responsibilities' => $this->responsibilityOptions($request),
            'location_types' => $this->locationTypeOptions($request),
            'branches' => $this->branchOptions($user),
            'cars' => $this->carOptions($user, $branchId),
            'employees' => $this->employeeOptions($user, $branchId),
            'contracts' => $this->contractOptions($user, $branchId),
        ]);
    }

    public function contextOptions(Request $request): JsonResponse
    {
        $this->authorizeAdminApiUser($request);

        return response()->json([
            'contexts' => $this->contextOptionItems($request),
        ]);
    }

    public function responsibilityOptionList(Request $request): JsonResponse
    {
        $this->authorizeAdminApiUser($request);

        return response()->json([
            'responsibilities' => $this->responsibilityOptions($request),
        ]);
    }

    public function locationTypeOptionList(Request $request): JsonResponse
    {
        $this->authorizeAdminApiUser($request);

        return response()->json([
            'location_types' => $this->locationTypeOptions($request),
        ]);
    }

    public function branchOptionList(Request $request): JsonResponse
    {
        $user = $this->authorizeAdminApiUser($request);

        return response()->json([
            'branches' => $this->branchOptions($user),
        ]);
    }

    public function carOptionList(Request $request): JsonResponse
    {
        $user = $this->authorizeAdminApiUser($request);
        $branchId = $this->resolveBranchId($request, $user);

        return response()->json([
            'cars' => $this->carOptions($user, $branchId),
        ]);
    }

    public function employeeOptionList(Request $request): JsonResponse
    {
        $user = $this->authorizeAdminApiUser($request);
        $branchId = $this->resolveBranchId($request, $user);

        return response()->json([
            'employees' => $this->employeeOptions($user, $branchId),
        ]);
    }

    public function contractOptionList(Request $request): JsonResponse
    {
        $user = $this->authorizeAdminApiUser($request);
        $branchId = $this->resolveBranchId($request, $user);

        return response()->json([
            'contracts' => $this->contractOptions($user, $branchId),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->authorizeAdminApiUser($request);
        $branchId = $this->resolveBranchId($request, $user);

        $query = AccidentReport::query()
            ->with($this->reportRelations())
            ->latest('accident_at')
            ->latest('id');

        $this->branchAccess->applyToQuery($query, $user, $branchId, 'branch_id');
        $this->applyFilters($query, $request);

        return $this->paginatedReportsResponse(
            $query->paginate($this->perPage($request))->withQueryString(),
            $request
        );
    }

    public function contractIndex(Request $request, Contract $contract): JsonResponse
    {
        $user = $this->authorizeAdminApiUser($request);
        abort_unless($this->canAccessBranchId($user, $contract->branch_id ? (int) $contract->branch_id : null), 403);

        $reports = $contract->accidentReports()
            ->with($this->reportRelations())
            ->latest('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->paginatedReportsResponse($reports, $request);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdminApiUser($request);

        $validated = $this->validateReportRequest($request);
        $files = $this->uploadedPhotoFiles($request);
        $this->validateUploadedPhotos($files);
        $report = $this->createReportFromValidated($request, $validated, $files);

        return response()->json([
            'message' => $this->localized($request, 'Accident report created successfully.', 'تم إنشاء بلاغ الحادث بنجاح.'),
            'accident_report' => $this->reportPayload($report, $request),
        ], 201);
    }

    public function contractStore(Request $request, Contract $contract): JsonResponse
    {
        $user = $this->authorizeAdminApiUser($request);
        abort_unless($this->canAccessBranchId($user, $contract->branch_id ? (int) $contract->branch_id : null), 403);

        $request->merge([
            'accident_context' => 'contract',
            'contract_id' => $contract->id,
        ]);

        $validated = $this->validateReportRequest($request);
        $files = $this->uploadedPhotoFiles($request);
        $this->validateUploadedPhotos($files);
        $report = $this->createReportFromValidated($request, $validated, $files);

        return response()->json([
            'message' => $this->localized($request, 'Accident report created successfully.', 'تم إنشاء بلاغ الحادث بنجاح.'),
            'accident_report' => $this->reportPayload($report, $request),
        ], 201);
    }

    public function show(Request $request, AccidentReport $accidentReport): JsonResponse
    {
        $user = $this->authorizeAdminApiUser($request);
        abort_unless($this->canAccessBranchId($user, $accidentReport->branch_id ? (int) $accidentReport->branch_id : null), 403);

        $accidentReport->load($this->reportRelations());

        return response()->json([
            'accident_report' => $this->reportPayload($accidentReport, $request),
        ]);
    }

    public function mrtaForm(Request $request, AccidentReport $accidentReport): Response
    {
        $user = $this->authorizeAdminApiUser($request);
        abort_unless($this->canAccessBranchId($user, $accidentReport->branch_id ? (int) $accidentReport->branch_id : null), 403);

        $accidentReport->load($this->reportRelations());

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

                return $request->boolean('download')
                    ? $pdf->download($fileName)
                    : $pdf->inline($fileName);
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

    private function validateReportRequest(Request $request): array
    {
        return $request->validate([
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
            'third_party_details' => ['nullable'],
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
    }

    /**
     * @param array<string, mixed> $validated
     * @param array<int|string, UploadedFile> $files
     */
    private function createReportFromValidated(Request $request, array $validated, array $files): AccidentReport
    {
        $user = $this->authorizeAdminApiUser($request);
        $context = (string) $validated['accident_context'];
        $contract = null;
        $reservationId = null;
        $carId = null;
        $branchId = null;
        $employeeId = null;

        if ($context === 'contract') {
            $contract = $this->resolveAccessibleContract($user, (int) $validated['contract_id']);
            $contract->loadMissing(['reservation:id,reservation_number,car_id', 'reservation.car:id,make,model,year,license_plate']);

            $reservationId = $contract->reservation_id;
            $carId = $contract->reservation?->car_id;
            $branchId = $contract->branch_id;
        } else {
            $branchId = $this->resolveAccessibleBranchId($user, (int) $validated['branch_id']);
            $car = $this->resolveAccessibleCar($user, (int) $validated['car_id'], $branchId);
            $carId = $car->id;

            if ($context === 'employee') {
                $employee = $this->resolveAccessibleEmployee($user, (int) $validated['employee_id']);
                $employeeId = $employee->id;
            }
        }

        return DB::transaction(function () use ($request, $validated, $files, $context, $contract, $reservationId, $carId, $branchId, $employeeId): AccidentReport {
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
                'third_party_details' => $this->normalizeThirdPartyDetails($validated['third_party_details'] ?? null),
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

            return $report->load($this->reportRelations());
        });
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $context = trim((string) $request->query('context', ''));
        if (in_array($context, ['contract', 'employee', 'branch'], true)) {
            $query->where('accident_context', $context);
        }

        $status = trim((string) $request->query('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($request->filled('car_id')) {
            $query->where('car_id', (int) $request->integer('car_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('accident_at', '>=', (string) $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('accident_at', '<=', (string) $request->query('to'));
        }
    }

    private function paginatedReportsResponse(LengthAwarePaginator $reports, Request $request): JsonResponse
    {
        return response()->json([
            'data' => $reports->getCollection()
                ->map(fn (AccidentReport $report) => $this->reportPayload($report, $request))
                ->values(),
            'pagination' => [
                'current_page' => $reports->currentPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
                'last_page' => $reports->lastPage(),
                'from' => $reports->firstItem(),
                'to' => $reports->lastItem(),
                'has_more_pages' => $reports->hasMorePages(),
            ],
        ]);
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

        $files = [];
        foreach ($photos as $index => $photo) {
            if ($photo instanceof UploadedFile) {
                $files[$index] = $photo;
                continue;
            }

            if (is_array($photo) && ($photo['file'] ?? null) instanceof UploadedFile) {
                $files[$index] = $photo['file'];
            }
        }

        return $files;
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

    private function normalizeThirdPartyDetails(mixed $details): ?array
    {
        if (is_array($details)) {
            return $details;
        }

        if (is_string($details) && trim($details) !== '') {
            $decoded = json_decode($details, true);

            return is_array($decoded) ? $decoded : ['details' => $details];
        }

        return null;
    }

    private function generateAccidentNumber(): string
    {
        do {
            $number = 'ACC-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
        } while (AccidentReport::where('accident_number', $number)->exists());

        return $number;
    }

    private function reportPayload(AccidentReport $report, Request $request): array
    {
        return [
            'id' => $report->id,
            'accident_number' => $report->accident_number,
            'status' => $report->status,
            'status_label' => $this->statusLabel((string) $report->status, $request),
            'accident_context' => $report->accident_context ?? 'contract',
            'accident_context_label' => $this->contextLabel($report->accident_context ?? 'contract', $request),
            'responsibility' => $report->responsibility,
            'responsibility_label' => $this->responsibilityLabel($report->responsibility, $request),
            'location_type' => $report->location_type,
            'location_type_label' => $this->locationTypeLabel($report->location_type, $request),
            'contract' => $report->contract_id ? [
                'id' => $report->contract_id,
                'contract_number' => $report->contract?->contract_number,
            ] : null,
            'reservation' => $report->reservation_id ? [
                'id' => $report->reservation_id,
                'reservation_number' => $report->reservation?->reservation_number,
            ] : null,
            'branch' => [
                'id' => $report->branch_id,
                'name' => $report->branch?->name,
            ],
            'employee' => $report->employee_id ? [
                'id' => $report->employee_id,
                'name' => $report->employee?->name,
                'email' => $report->employee?->email,
            ] : null,
            'reported_by' => $report->reported_by ? [
                'id' => $report->reported_by,
                'name' => $report->reporter?->name,
                'email' => $report->reporter?->email,
            ] : null,
            'car' => [
                'id' => $report->car_id,
                'name' => trim(implode(' ', array_filter([
                    $report->car?->year,
                    $report->car?->make,
                    $report->car?->model,
                ]))),
                'license_plate' => $report->car?->license_plate,
            ],
            'accident_at' => $report->accident_at?->toISOString(),
            'location' => $report->location,
            'latitude' => $report->latitude,
            'longitude' => $report->longitude,
            'description' => $report->description,
            'police_report_number' => $report->police_report_number,
            'has_injuries' => $report->has_injuries,
            'third_party_involved' => $report->third_party_involved,
            'third_party_details' => $report->third_party_details,
            'mrta' => $this->mrtaPayload($report),
            'mrta_pdf_url' => route('api.accident-reports.mrta-form', ['accidentReport' => $report->id]),
            'notes' => $report->notes,
            'photos' => $report->photos
                ->map(fn ($photo) => [
                    'id' => $photo->id,
                    'photo_type' => $photo->photo_type,
                    'file_name' => $photo->file_name,
                    'mime_type' => $photo->mime_type,
                    'size' => $photo->size,
                    'notes' => $photo->notes,
                    'url' => $this->fileUrl($photo->file_path),
                ])
                ->values(),
            'created_at' => $report->created_at?->toISOString(),
            'updated_at' => $report->updated_at?->toISOString(),
        ];
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

    private function resolveAccessibleContract(User $user, int $contractId): Contract
    {
        $query = Contract::query()->whereKey($contractId);
        $this->branchAccess->applyToQuery($query, $user, null, 'branch_id');
        $contract = $query->first();

        if (!$contract) {
            throw ValidationException::withMessages([
                'contract_id' => $this->localizedForAppLocale('Selected contract is invalid or not accessible.', "\u{0627}\u{0644}\u{0639}\u{0642}\u{062F} \u{0627}\u{0644}\u{0645}\u{062D}\u{062F}\u{062F} \u{063A}\u{064A}\u{0631} \u{0635}\u{062D}\u{064A}\u{062D} \u{0623}\u{0648} \u{063A}\u{064A}\u{0631} \u{0645}\u{062A}\u{0627}\u{062D}."),
            ]);
        }

        return $contract;
    }

    private function resolveAccessibleBranchId(User $user, int $branchId): int
    {
        if (!$this->canAccessBranchId($user, $branchId)) {
            throw ValidationException::withMessages([
                'branch_id' => $this->localizedForAppLocale('Selected branch is invalid or not accessible.', "\u{0627}\u{0644}\u{0641}\u{0631}\u{0639} \u{0627}\u{0644}\u{0645}\u{062D}\u{062F}\u{062F} \u{063A}\u{064A}\u{0631} \u{0635}\u{062D}\u{064A}\u{062D} \u{0623}\u{0648} \u{063A}\u{064A}\u{0631} \u{0645}\u{062A}\u{0627}\u{062D}."),
            ]);
        }

        return $branchId;
    }

    private function resolveAccessibleCar(User $user, int $carId, int $branchId): Car
    {
        $query = Car::query()->whereKey($carId)->where('branch_id', $branchId);
        $this->branchAccess->applyToQuery($query, $user, null, 'branch_id');
        $car = $query->first();

        if (!$car) {
            throw ValidationException::withMessages([
                'car_id' => $this->localizedForAppLocale('Selected car is invalid or not accessible.', "\u{0627}\u{0644}\u{0633}\u{064A}\u{0627}\u{0631}\u{0629} \u{0627}\u{0644}\u{0645}\u{062D}\u{062F}\u{062F}\u{0629} \u{063A}\u{064A}\u{0631} \u{0635}\u{062D}\u{064A}\u{062D}\u{0629} \u{0623}\u{0648} \u{063A}\u{064A}\u{0631} \u{0645}\u{062A}\u{0627}\u{062D}\u{0629}."),
            ]);
        }

        return $car;
    }

    private function resolveAccessibleEmployee(User $user, int $employeeId): User
    {
        $query = User::query()
            ->whereKey($employeeId)
            ->where('role', UserRole::ADMIN)
            ->where('is_active', true);

        $this->branchAccess->applyToQuery($query, $user, null, 'branch_id');
        $employee = $query->first();

        if (!$employee) {
            throw ValidationException::withMessages([
                'employee_id' => $this->localizedForAppLocale('Selected employee is invalid or not accessible.', "\u{0627}\u{0644}\u{0645}\u{0648}\u{0638}\u{0641} \u{0627}\u{0644}\u{0645}\u{062D}\u{062F}\u{062F} \u{063A}\u{064A}\u{0631} \u{0635}\u{062D}\u{064A}\u{062D} \u{0623}\u{0648} \u{063A}\u{064A}\u{0631} \u{0645}\u{062A}\u{0627}\u{062D}."),
            ]);
        }

        return $employee;
    }

    private function branchOptions(User $user): array
    {
        return $this->branchAccess
            ->availableBranchesForUser($user)
            ->map(fn ($branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
            ])
            ->values()
            ->all();
    }

    private function carOptions(User $user, ?int $branchId): array
    {
        $query = Car::query()
            ->select(['id', 'branch_id', 'year', 'make', 'model', 'license_plate'])
            ->orderBy('make')
            ->orderBy('model')
            ->limit(500);

        $this->branchAccess->applyToQuery($query, $user, $branchId, 'branch_id');

        return $query->get()
            ->map(fn (Car $car) => [
                'id' => $car->id,
                'branch_id' => $car->branch_id,
                'label' => trim("{$car->year} {$car->make} {$car->model} ({$car->license_plate})"),
            ])
            ->values()
            ->all();
    }

    private function employeeOptions(User $user, ?int $branchId): array
    {
        $query = User::query()
            ->select(['id', 'tenant_id', 'name', 'email', 'branch_id'])
            ->where('role', UserRole::ADMIN)
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(500);

        $this->branchAccess->applyToQuery($query, $user, $branchId, 'branch_id');

        return $query->get()
            ->map(fn (User $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
                'branch_id' => $employee->branch_id,
            ])
            ->values()
            ->all();
    }

    private function contractOptions(User $user, ?int $branchId): array
    {
        $query = Contract::query()
            ->select(['id', 'contract_number', 'reservation_id', 'renter_name', 'branch_id'])
            ->with(['reservation:id,reservation_number,car_id', 'reservation.car:id,year,make,model,license_plate'])
            ->latest('id')
            ->limit(500);

        $this->branchAccess->applyToQuery($query, $user, $branchId, 'branch_id');

        return $query->get()
            ->map(fn (Contract $contract) => [
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
                'branch_id' => $contract->branch_id,
            ])
            ->values()
            ->all();
    }

    private function contextOptionItems(Request $request): array
    {
        return [
            ['value' => 'contract', 'label' => $this->localized($request, 'With customer', 'مع العميل')],
            ['value' => 'employee', 'label' => $this->localized($request, 'With employee', 'مع موظف')],
            ['value' => 'branch', 'label' => $this->localized($request, 'At office or gate', 'عند المكتب أو البوابة')],
        ];
    }

    private function responsibilityOptions(Request $request): array
    {
        return collect(['customer', 'employee', 'company', 'third_party', 'unknown'])
            ->map(fn (string $value) => [
                'value' => $value,
                'label' => $this->responsibilityLabel($value, $request),
            ])
            ->all();
    }

    private function locationTypeOptions(Request $request): array
    {
        return collect(['road', 'branch_gate', 'parking', 'workshop', 'other'])
            ->map(fn (string $value) => [
                'value' => $value,
                'label' => $this->locationTypeLabel($value, $request),
            ])
            ->all();
    }

    private function reportRelations(): array
    {
        return [
            'photos',
            'contract:id,contract_number,renter_name,renter_phone,renter_id_number',
            'reservation:id,reservation_number',
            'car:id,make,model,year,license_plate',
            'branch:id,name',
            'employee:id,name,email',
            'reporter:id,name,email',
        ];
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

    private function authorizeAdminApiUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless(in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::ADMIN], true), 403);

        return $user;
    }

    private function resolveBranchId(Request $request, User $user): ?int
    {
        $branchId = $this->branchAccess->normalizeRequestedBranchId($request->query('branch_id'));

        if ($branchId && !$this->branchAccess->canAccessBranchId($user, $branchId)) {
            abort(403);
        }

        return $branchId;
    }

    private function canAccessBranchId(User $user, ?int $branchId): bool
    {
        return $this->branchAccess->canAccessBranchId($user, $branchId);
    }

    private function perPage(Request $request): int
    {
        return (int) min(max((int) $request->integer('per_page', 10), 1), 50);
    }

    private function statusLabel(string $status, Request $request): string
    {
        if ($this->language($request) === 'ar') {
            return [
                'reported' => "\u{062A}\u{0645} \u{0627}\u{0644}\u{0625}\u{0628}\u{0644}\u{0627}\u{063A}",
                'under_review' => "\u{0642}\u{064A}\u{062F} \u{0627}\u{0644}\u{0645}\u{0631}\u{0627}\u{062C}\u{0639}\u{0629}",
                'resolved' => "\u{062A}\u{0645} \u{0627}\u{0644}\u{062D}\u{0644}",
                'rejected' => "\u{0645}\u{0631}\u{0641}\u{0648}\u{0636}",
            ][$status] ?? Str::headline($status);
        }

        $labels = [
            'reported' => ['en' => 'Reported', 'ar' => 'تم الإبلاغ'],
            'under_review' => ['en' => 'Under review', 'ar' => 'قيد المراجعة'],
            'resolved' => ['en' => 'Resolved', 'ar' => 'تم الحل'],
            'rejected' => ['en' => 'Rejected', 'ar' => 'مرفوض'],
        ];

        $lang = $this->language($request);

        return $labels[$status][$lang] ?? Str::headline($status);
    }

    private function contextLabel(?string $context, Request $request): string
    {
        return match ($context) {
            'employee' => $this->localized($request, 'With employee', 'مع موظف'),
            'branch' => $this->localized($request, 'At office or gate', 'عند المكتب أو البوابة'),
            default => $this->localized($request, 'With customer', 'مع العميل'),
        };
    }

    private function responsibilityLabel(?string $responsibility, Request $request): string
    {
        return match ($responsibility) {
            'customer' => $this->localized($request, 'Customer', 'العميل'),
            'employee' => $this->localized($request, 'Employee', 'الموظف'),
            'company' => $this->localized($request, 'Company', 'الشركة'),
            'third_party' => $this->localized($request, 'Third party', 'طرف ثالث'),
            'unknown' => $this->localized($request, 'Unknown', 'غير معروف'),
            default => '-',
        };
    }

    private function locationTypeLabel(?string $locationType, Request $request): string
    {
        return match ($locationType) {
            'road' => $this->localized($request, 'Road', 'الطريق'),
            'branch_gate' => $this->localized($request, 'Branch gate', 'بوابة الفرع'),
            'parking' => $this->localized($request, 'Parking', 'المواقف'),
            'workshop' => $this->localized($request, 'Workshop', 'الورشة'),
            'other' => $this->localized($request, 'Other', 'أخرى'),
            default => '-',
        };
    }

    private function localized(Request $request, string $en, string $ar): string
    {
        if ($this->language($request) !== 'ar') {
            return $en;
        }

        return [
            'With customer' => "\u{0645}\u{0639} \u{0627}\u{0644}\u{0639}\u{0645}\u{064A}\u{0644}",
            'With employee' => "\u{0645}\u{0639} \u{0645}\u{0648}\u{0638}\u{0641}",
            'At office or gate' => "\u{0639}\u{0646}\u{062F} \u{0627}\u{0644}\u{0645}\u{0643}\u{062A}\u{0628} \u{0623}\u{0648} \u{0627}\u{0644}\u{0628}\u{0648}\u{0627}\u{0628}\u{0629}",
            'Accident report created successfully.' => "\u{062A}\u{0645} \u{0625}\u{0646}\u{0634}\u{0627}\u{0621} \u{0628}\u{0644}\u{0627}\u{063A} \u{0627}\u{0644}\u{062D}\u{0627}\u{062F}\u{062B} \u{0628}\u{0646}\u{062C}\u{0627}\u{062D}.",
            'Customer' => "\u{0627}\u{0644}\u{0639}\u{0645}\u{064A}\u{0644}",
            'Employee' => "\u{0627}\u{0644}\u{0645}\u{0648}\u{0638}\u{0641}",
            'Company' => "\u{0627}\u{0644}\u{0634}\u{0631}\u{0643}\u{0629}",
            'Third party' => "\u{0637}\u{0631}\u{0641} \u{062B}\u{0627}\u{0644}\u{062B}",
            'Unknown' => "\u{063A}\u{064A}\u{0631} \u{0645}\u{0639}\u{0631}\u{0648}\u{0641}",
            'Road' => "\u{0627}\u{0644}\u{0637}\u{0631}\u{064A}\u{0642}",
            'Branch gate' => "\u{0628}\u{0648}\u{0627}\u{0628}\u{0629} \u{0627}\u{0644}\u{0641}\u{0631}\u{0639}",
            'Parking' => "\u{0627}\u{0644}\u{0645}\u{0648}\u{0627}\u{0642}\u{0641}",
            'Workshop' => "\u{0627}\u{0644}\u{0648}\u{0631}\u{0634}\u{0629}",
            'Other' => "\u{0623}\u{062E}\u{0631}\u{0649}",
        ][$en] ?? $ar;
    }

    private function localizedForAppLocale(string $en, string $ar): string
    {
        return str_starts_with(strtolower((string) app()->getLocale()), 'ar') ? $ar : $en;
    }

    private function language(Request $request): string
    {
        return str_starts_with(strtolower((string) $request->header('Accept-Language')), 'ar') ? 'ar' : 'en';
    }
}
