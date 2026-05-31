<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccidentReport;
use App\Models\Contract;
use App\Support\BranchAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

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
            'contract_number' => $report->contract?->contract_number,
            'reservation_number' => $report->reservation?->reservation_number,
            'renter_name' => $report->contract?->renter_name,
            'car' => $this->carLabel($report),
            'branch' => $report->branch?->name ?? '-',
            'location' => $report->location,
            'accident_at' => optional($report->accident_at)?->format('Y-m-d H:i'),
            'photos_count' => (int) $report->photos_count,
            'show_url' => route('admin.accident-reports.show', $report),
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
            'indexUrl' => route('admin.accident-reports.index'),
            'createUrl' => route('admin.accident-reports.create'),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/AccidentReports/Create', [
            'contracts' => $this->contractOptions($request),
            'statuses' => $this->statusOptions(),
            'indexUrl' => route('admin.accident-reports.index'),
            'submitUrl' => route('admin.accident-reports.store'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'contract_id' => ['required', 'integer'],
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

        $contract = $this->resolveAccessibleContract($request, (int) $validated['contract_id']);
        $contract->loadMissing(['reservation:id,reservation_number,car_id', 'reservation.car:id,year,make,model,license_plate']);

        $report = DB::transaction(function () use ($request, $validated, $contract, $files): AccidentReport {
            $reservation = $contract->reservation;

            $report = AccidentReport::create([
                'contract_id' => $contract->id,
                'reservation_id' => $contract->reservation_id,
                'car_id' => $reservation?->car_id,
                'branch_id' => $contract->branch_id,
                'reported_by' => $request->user()?->id,
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
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->storePhotos($report, $files, $request->input('photo_types', []), $request->input('photo_notes', []));

            return $report;
        });

        return redirect()
            ->route('admin.accident-reports.show', $report)
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
        ]);

        return Inertia::render('Admin/AccidentReports/Show', [
            'report' => [
                'id' => $accidentReport->id,
                'accident_number' => $accidentReport->accident_number,
                'status' => $accidentReport->status,
                'status_label' => $this->statusLabel($accidentReport->status),
                'status_color' => $this->statusColor($accidentReport->status),
                'contract_number' => $accidentReport->contract?->contract_number,
                'reservation_number' => $accidentReport->reservation?->reservation_number,
                'renter_name' => $accidentReport->contract?->renter_name,
                'renter_phone' => $accidentReport->contract?->renter_phone,
                'renter_id_number' => $accidentReport->contract?->renter_id_number,
                'car' => $this->carLabel($accidentReport),
                'branch' => $accidentReport->branch?->name,
                'reported_by' => $accidentReport->reporter?->name,
                'accident_at' => optional($accidentReport->accident_at)?->format('Y-m-d H:i'),
                'location' => $accidentReport->location,
                'latitude' => $accidentReport->latitude,
                'longitude' => $accidentReport->longitude,
                'description' => $accidentReport->description,
                'police_report_number' => $accidentReport->police_report_number,
                'has_injuries' => $accidentReport->has_injuries,
                'third_party_involved' => $accidentReport->third_party_involved,
                'third_party_details' => $accidentReport->third_party_details,
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
            'indexUrl' => route('admin.accident-reports.index'),
        ]);
    }

    private function contractOptions(Request $request)
    {
        $query = Contract::query()
            ->select(['id', 'contract_number', 'reservation_id', 'renter_name', 'branch_id'])
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
            'car' => $contract->reservation?->car
                ? trim("{$contract->reservation->car->year} {$contract->reservation->car->make} {$contract->reservation->car->model} ({$contract->reservation->car->license_plate})")
                : '-',
        ])->values();
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
}
