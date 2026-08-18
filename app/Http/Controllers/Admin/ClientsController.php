<?php

namespace App\Http\Controllers\Admin;

use App\Core\TenantContext;
use App\Enums\ClientDocumentExtractionStatus;
use App\Enums\ClientDocumentType;
use App\Http\Controllers\Controller;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\ClientDocument;
use App\Models\Payment;
use App\Models\User;
use App\Services\ClientDocuments\LocalClientDocumentExtractor;
use App\Services\Clients\ClientStatusService;
use App\Rules\DigitsOnly;
use App\Rules\LettersOnly;
use App\Support\BranchAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;
use MohamedGaldi\ViltFilepond\Services\FilePondService;

class ClientsController extends Controller
{
    public function __construct(
        private BranchAccess $branchAccess,
        private FilePondService $filePondService,
        private LocalClientDocumentExtractor $localClientDocumentExtractor,
        private ClientStatusService $clientStatusService
    )
    {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $requestedBranchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));

        $branchOptions = $this->branchAccess
            ->availableBranchesForUser($user)
            ->map(fn ($branch) => ['id' => $branch->id, 'name' => $branch->name])
            ->values();
        $allowedBranchIds = $branchOptions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $branchId = ($requestedBranchId && in_array($requestedBranchId, $allowedBranchIds, true))
            ? $requestedBranchId
            : null;

        $accessibleBranchIds = $this->branchAccess->accessibleBranchIds($user);

        $query = User::query()
            ->where('role', UserRole::CLIENT)
            ->when(!$canAccessAllBranches, function ($q) use ($accessibleBranchIds) {
                if (empty($accessibleBranchIds)) {
                    $q->whereRaw('1 = 0');
                } else {
                    $q->whereIn('branch_id', $accessibleBranchIds);
                }
            })
            ->when($canAccessAllBranches && $branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($status, function ($q) use ($status) {
                if ($status === 'active') {
                    $q->where('is_active', true);
                } elseif ($status === 'suspended') {
                    $q->where('is_active', false);
                }
            })
            ->withCount(['reservations', 'payments'])
            ->with('branch:id,name')
            ->orderBy('name');

        $clients = $query->paginate(10)->withQueryString();
        $locale = $this->dashboardLocale($request);

        $clients->getCollection()->transform(function (User $client) use ($locale): User {
            $status = $this->clientStatusService->build($client, $locale);

            $client->setAttribute('client_status', [
                'status' => $status['overall_status'],
                'label' => $status['overall_label'],
                'can_book' => $status['can_book'],
                'flags_count' => $status['flags_count'],
                'blocking_flags' => $status['blocking_flags'],
            ]);

            return $client;
        });

        $statusCountsQuery = User::query()
            ->where('role', UserRole::CLIENT);
        if ($canAccessAllBranches) {
            if ($branchId) {
                $statusCountsQuery->where('branch_id', $branchId);
            }
        } elseif (!empty($accessibleBranchIds)) {
            $statusCountsQuery->whereIn('branch_id', $accessibleBranchIds);
        } else {
            $statusCountsQuery->whereRaw('1 = 0');
        }
        $statusCounts = [
            'active' => (clone $statusCountsQuery)->where('is_active', true)->count(),
            'suspended' => (clone $statusCountsQuery)->where('is_active', false)->count(),
        ];

        $statuses = [
            'active' => ['label' => 'Active', 'count' => $statusCounts['active'], 'color' => '#10B981'],
            'suspended' => ['label' => 'Suspended', 'count' => $statusCounts['suspended'], 'color' => '#EF4444'],
        ];

        return Inertia::render('Admin/Clients/Index', [
            'clients' => $clients,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'branch_id' => $branchId,
            ],
            'statuses' => $statuses,
            'branches' => $branchOptions,
            'canAccessAllBranches' => $canAccessAllBranches,
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);

        $branches = $this->branchAccess
            ->availableBranchesForUser($user)
            ->map(fn ($branch) => ['id' => $branch->id, 'name' => $branch->name])
            ->values();

        return Inertia::render('Admin/Clients/Create', [
            'branches' => $branches,
            'canAccessAllBranches' => $canAccessAllBranches,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        if (config('app.demo_mode')) {
            return redirect()
                ->back()
                ->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', new LettersOnly()],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->where(fn ($query) => $query->where('tenant_id', $this->tenantId())),
            ],
            'civil_number' => ['required', 'string', 'max:255', new DigitsOnly()],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('tenant_id', $this->tenantId())),
            ],
        ]);

        $branchId = $this->branchAccess->resolveWritableBranchId(
            $user,
            $this->branchAccess->normalizeRequestedBranchId($validated['branch_id'] ?? null)
        );

        $client = User::create([
            'name' => $validated['name'],
            'civil_number' => trim((string) $validated['civil_number']),
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => UserRole::CLIENT,
            'tenant_id' => $this->tenantId(),
            'branch_id' => $branchId,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        if ($request->boolean('inline') || $request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'client' => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                    'civil_number' => $client->civil_number,
                ],
                'message' => 'Client created successfully.',
            ]);
        }

        return redirect()
            ->route('admin.clients.index', [
                'subdomain' => $request->route('subdomain'),
            ])
            ->with('success', 'Client created successfully.');
    }

    public function show(Request $request, User $client): Response
    {
        $this->ensureClientAccessible($client, $request->user());
        $clientStatus = $this->clientStatusService->build($client, $this->dashboardLocale($request));

        $totalSpent = Payment::where('user_id', $client->id)
            ->where('status', PaymentStatus::COMPLETED)
            ->sum('amount');

        $reservations = $client->reservations()
            ->with(['car'])
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'reservations_page')
            ->withQueryString();

        $payments = $client->payments()
            ->with(['reservation'])
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'payments_page')
            ->withQueryString();

        $notes = $client->clientNotes()
            ->with('creator:id,name')
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn ($note) => [
                'id' => $note->id,
                'note' => $note->note,
                'created_at' => $note->created_at?->toIso8601String(),
                'creator' => $note->creator ? [
                    'id' => $note->creator->id,
                    'name' => $note->creator->name,
                ] : null,
            ]);

        return Inertia::render('Admin/Clients/Show', [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'is_active' => (bool) $client->is_active,
                'created_at' => $client->created_at,
                'status' => $clientStatus['overall_status'],
                'status_label' => $clientStatus['overall_label'],
            ],
            'clientStatus' => $clientStatus,
            'stats' => [
                'total_reservations' => $client->reservations()->count(),
                'total_payments' => $client->payments()->count(),
                'total_spent' => (float) $totalSpent,
                'total_documents' => $client->clientDocuments()->count(),
            ],
            'reservations' => $reservations,
            'payments' => $payments,
            'notes' => $notes,
            'actions' => [
                'documents' => route('admin.clients.documents', [
                    'subdomain' => request()->route('subdomain'),
                    'client' => $client->id,
                ]),
                'store_note' => route('admin.clients.notes.store', [
                    'subdomain' => request()->route('subdomain'),
                    'client' => $client->id,
                ]),
            ],
        ]);
    }

    public function storeNote(Request $request, User $client): RedirectResponse
    {
        $this->ensureClientAccessible($client, $request->user());

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:5000'],
        ]);

        $client->clientNotes()->create([
            'tenant_id' => $this->tenantId(),
            'note' => trim((string) $validated['note']),
            'created_by' => $request->user()?->id,
        ]);

        return redirect()
            ->back()
            ->with('success', 'Client note added successfully.');
    }

    public function documents(Request $request, User $client): Response
    {
        $this->ensureClientAccessible($client, $request->user());

        $client->loadMissing([
            'branch:id,name',
            'clientDocuments.files',
        ]);

        $documents = collect(ClientDocumentType::options())
            ->map(function (array $type) use ($client) {
                /** @var ClientDocument|null $document */
                $document = $client->clientDocuments
                    ->first(fn (ClientDocument $doc) => $doc->document_type?->value === $type['value']);

                return [
                    'document_type' => $type['value'],
                    'label' => $type['label'],
                    'description' => $type['description'],
                    'id' => $document?->id,
                    'extraction_status' => $document?->extraction_status?->value ?? ClientDocumentExtractionStatus::NOT_REQUESTED->value,
                    'extraction_status_label' => $document?->extraction_status?->label() ?? ClientDocumentExtractionStatus::NOT_REQUESTED->label(),
                    'extraction_provider' => $document?->extraction_provider,
                    'extraction_engine' => $document?->extraction_engine,
                    'confidence' => $document?->confidence !== null ? (float) $document->confidence : null,
                    'raw_text' => $document?->raw_text,
                    'raw_output' => $document?->raw_output,
                    'extracted_data' => $document?->extracted_data ?? [],
                    'approved_data' => $document?->approved_data ?? [],
                    'reviewed_at' => $document?->reviewed_at?->toIso8601String(),
                    'files' => $document ? $this->collectionFiles($document, 'scan') : [],
                ];
            })
            ->values()
            ->all();

        return Inertia::render('Admin/Clients/Documents', [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'branch_name' => $client->branch?->name,
            ],
            'documents' => $documents,
            'fieldSchema' => $this->documentFieldSchema(),
            'actions' => [
                'back' => route('admin.clients.show', [
                    'subdomain' => $request->route('subdomain'),
                    'client' => $client->id,
                ]),
                'save' => route('admin.clients.documents.save', [
                    'subdomain' => $request->route('subdomain'),
                    'client' => $client->id,
                ]),
                'extract' => route('admin.clients.documents.extract', [
                    'subdomain' => $request->route('subdomain'),
                    'client' => $client->id,
                ]),
            ],
            'ocr' => [
                'enabled' => (bool) config('local_ocr.enabled', true),
                'python_binary' => (string) config('local_ocr.python_binary', 'python'),
            ],
        ]);
    }

    public function extractDocument(Request $request, User $client): JsonResponse
    {
        $this->ensureClientAccessible($client, $request->user());

        $validated = $request->validate([
            'document_type' => ['required', Rule::in(ClientDocumentType::values())],
            'temp_folders' => ['required', 'array', 'min:1'],
            'temp_folders.*' => ['string'],
        ]);

        try {
            $result = $this->localClientDocumentExtractor->extractFromTempFolders(
                $validated['temp_folders'],
                (string) $validated['document_type']
            );

            return response()->json([
                'message' => 'Document extraction completed.',
                'fields' => $result['fields'],
                'raw_output' => $result['raw_output'],
                'raw_text' => $result['raw_text'],
                'confidence' => $result['confidence'],
                'provider' => $result['provider'],
                'engine' => $result['engine'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => (string) $e->getMessage(),
            ], 422);
        }
    }

    public function saveDocument(Request $request, User $client): RedirectResponse
    {
        $this->ensureClientAccessible($client, $request->user());

        $tenantId = (int) (TenantContext::id() ?? $request->user()?->tenant_id ?? 0);
        if ($tenantId <= 0) {
            abort(404);
        }

        $validated = $request->validate([
            'document_type' => ['required', Rule::in(ClientDocumentType::values())],
            'approved_data' => ['nullable', 'array'],
            'extracted_data' => ['nullable', 'array'],
            'raw_output' => ['nullable', 'array'],
            'raw_text' => ['nullable', 'string'],
            'confidence' => ['nullable', 'numeric', 'between:0,1'],
            'extraction_provider' => ['nullable', 'string', 'max:100'],
            'extraction_engine' => ['nullable', 'string', 'max:100'],
            'temp_folders' => ['array'],
            'temp_folders.*' => ['string'],
            'removed_file_ids' => ['array'],
            'removed_file_ids.*' => ['integer'],
        ]);

        $document = ClientDocument::query()->firstOrNew([
            'tenant_id' => $tenantId,
            'user_id' => $client->id,
            'document_type' => (string) $validated['document_type'],
        ]);

        $approvedData = is_array($validated['approved_data'] ?? null)
            ? array_filter($validated['approved_data'], fn ($value) => $value !== null && $value !== '')
            : [];
        $extractedData = is_array($validated['extracted_data'] ?? null)
            ? array_filter($validated['extracted_data'], fn ($value) => $value !== null && $value !== '')
            : [];

        $document->extracted_data = $extractedData !== [] ? $extractedData : null;
        $document->approved_data = $approvedData !== [] ? $approvedData : null;
        $document->raw_output = $validated['raw_output'] ?? null;
        $document->raw_text = $this->nullableString($validated['raw_text'] ?? null);
        $document->confidence = $validated['confidence'] ?? null;
        $document->extraction_provider = $this->nullableString($validated['extraction_provider'] ?? null);
        $document->extraction_engine = $this->nullableString($validated['extraction_engine'] ?? null);

        if ($approvedData !== []) {
            $document->extraction_status = ClientDocumentExtractionStatus::REVIEWED;
            $document->reviewed_at = Carbon::now();
            $document->reviewed_by_user_id = $request->user()?->id;
        } elseif ($extractedData !== [] || !empty($validated['raw_output'])) {
            $document->extraction_status = ClientDocumentExtractionStatus::COMPLETED;
        } else {
            $document->extraction_status = ClientDocumentExtractionStatus::NOT_REQUESTED;
        }

        $document->save();

        $this->syncDocumentFiles($document, $request);

        return redirect()
            ->route('admin.clients.documents', [
                'subdomain' => $request->route('subdomain'),
                'client' => $client->id,
            ])
            ->with('success', 'Client document saved successfully.');
    }

    public function suspend(User $client)
    {
        // Restrict this action
        return redirect()
            ->back()
            ->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');

        $client->is_active = false;
        $client->save();

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('success', 'Client suspended successfully.');
    }

    public function activate(User $client)
    {
        // Restrict this action
        return redirect()
            ->back()
            ->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');

        $client->is_active = true;
        $client->save();

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('success', 'Client activated successfully.');
    }

    private function ensureClientAccessible(User $client, ?User $actor): void
    {
        abort_unless($client->role === UserRole::CLIENT, 404);
        abort_unless($this->branchAccess->canAccessBranchId($actor, $client->branch_id ? (int) $client->branch_id : null), 403);
    }

    private function dashboardLocale(Request $request): string
    {
        $locale = $request->route('locale') ?: app()->getLocale();
        $locale = strtolower(substr((string) $locale, 0, 2));

        return in_array($locale, ['ar', 'en'], true) ? $locale : 'en';
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    private function documentFieldSchema(): array
    {
        $root = 'site.dashboard.admin.clients.documents.fields';

        return [
            ['key' => 'document_number', 'label' => __("$root.document_number")],
            ['key' => 'full_name', 'label' => __("$root.full_name")],
            ['key' => 'date_of_birth', 'label' => __("$root.date_of_birth")],
            ['key' => 'expiry_date', 'label' => __("$root.expiry_date")],
            ['key' => 'issue_date', 'label' => __("$root.issue_date")],
            ['key' => 'nationality', 'label' => __("$root.nationality")],
            ['key' => 'license_class', 'label' => __("$root.license_class")],
            ['key' => 'address', 'label' => __("$root.address")],
            ['key' => 'place_of_issue', 'label' => __("$root.place_of_issue")],
        ];
    }

    private function syncDocumentFiles(ClientDocument $document, Request $request): void
    {
        $tempFolders = $request->input('temp_folders', []);
        $removedIds = $request->input('removed_file_ids', []);

        $tempFolders = is_array($tempFolders) ? $tempFolders : [];
        $removedIds = is_array($removedIds) ? $removedIds : [];

        if (!empty($tempFolders)) {
            $existingIds = $document->files()->where('collection', 'scan')->pluck('id')->all();
            $removedIds = array_values(array_unique(array_merge($removedIds, $existingIds)));
        }

        $this->filePondService->handleFileUpdates(
            $document,
            $tempFolders,
            $removedIds,
            'scan'
        );
    }

    private function collectionFiles(ClientDocument $document, string $collection): array
    {
        $files = $document->relationLoaded('files')
            ? $document->files->where('collection', $collection)->values()
            : $document->files()->where('collection', $collection)->get();

        return $files->map(fn ($file) => [
            'id' => $file->id,
            'url' => $this->storageUrl($file->path),
        ])->values()->all();
    }

    private function storageUrl(?string $path): ?string
    {
        $path = trim((string) ($path ?? ''));
        if ($path === '') {
            return null;
        }

        $normalized = ltrim($path, '/');
        if (str_starts_with($normalized, 'storage/')) {
            $normalized = substr($normalized, strlen('storage/'));
        }

        return Storage::url($normalized);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function tenantId(): int
    {
        return (int) (TenantContext::id() ?? auth()->user()?->tenant_id ?? 0);
    }
}
