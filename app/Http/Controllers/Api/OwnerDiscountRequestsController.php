<?php

namespace App\Http\Controllers\Api;

use App\Enums\DiscountRequestStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\DiscountRequest;
use App\Models\User;
use App\Services\DiscountRequests\DiscountRequestDecisionService;
use App\Support\BranchAccess;
use App\Support\TenantTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OwnerDiscountRequestsController extends Controller
{
    public function __construct(
        private readonly BranchAccess $branchAccess,
        private readonly DiscountRequestDecisionService $decisionService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->authorizedOwner($request);
        $locale = $this->resolveLocale($request);
        $branchId = $this->resolveOwnerBranchId($request, $user);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = DiscountRequest::query()
            ->with($this->relationships());

        $this->applyBranchScope($query, $user, $branchId);
        $this->applyPendingApprovalFilters($query, $search);

        $requests = $query
            ->orderByRaw("CASE WHEN status = ? THEN 0 ELSE 1 END", [DiscountRequestStatus::PENDING->value])
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'status' => 'success',
            'locale' => $locale,
            'branch_id' => $branchId,
            'summary' => $this->summaryPayload($user, $branchId, $locale),
            'filters' => [
                'status' => DiscountRequestStatus::PENDING->value,
                'search' => $search,
                'branch_id' => $branchId,
            ],
            'data' => $requests->getCollection()
                ->map(fn (DiscountRequest $discountRequest): array => $this->listPayload($discountRequest, $locale))
                ->values()
                ->all(),
            'pagination' => [
                'current_page' => $requests->currentPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
                'last_page' => $requests->lastPage(),
            ],
        ]);
    }

    public function count(Request $request): JsonResponse
    {
        $user = $this->authorizedOwner($request);
        $locale = $this->resolveLocale($request);
        $branchId = $this->resolveOwnerBranchId($request, $user);

        return response()->json([
            'status' => 'success',
            'locale' => $locale,
            'branch_id' => $branchId,
            'summary' => $this->summaryPayload($user, $branchId, $locale),
        ]);
    }

    public function show(Request $request, DiscountRequest $discountRequest): JsonResponse
    {
        $user = $this->authorizedOwner($request);
        $locale = $this->resolveLocale($request);

        $discountRequest->loadMissing($this->relationships());
        abort_unless($this->canAccessDiscountRequest($discountRequest, $user), 403);

        return response()->json([
            'status' => 'success',
            'locale' => $locale,
            'data' => $this->detailPayload($discountRequest, $locale),
        ]);
    }

    public function approve(Request $request, DiscountRequest $discountRequest): JsonResponse
    {
        $user = $this->authorizedOwner($request);
        $locale = $this->resolveLocale($request);

        $discountRequest->loadMissing('reservation.car:id,branch_id');
        abort_unless($this->canAccessDiscountRequest($discountRequest, $user), 403);

        $approvedRequest = $this->decisionService->approve($discountRequest, $user);
        $approvedRequest->loadMissing($this->relationships());

        return response()->json([
            'status' => 'success',
            'message' => $this->ownerText('discount_requests.messages.approved', $locale, 'Discount request approved.'),
            'data' => $this->detailPayload($approvedRequest, $locale),
        ]);
    }

    public function reject(Request $request, DiscountRequest $discountRequest): JsonResponse
    {
        $user = $this->authorizedOwner($request);
        $locale = $this->resolveLocale($request);

        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $discountRequest->loadMissing('reservation.car:id,branch_id');
        abort_unless($this->canAccessDiscountRequest($discountRequest, $user), 403);

        $rejectedRequest = $this->decisionService->reject(
            $discountRequest,
            $user,
            $validated['review_note'] ?? null
        );
        $rejectedRequest->loadMissing($this->relationships());

        return response()->json([
            'status' => 'success',
            'message' => $this->ownerText('discount_requests.messages.rejected', $locale, 'Discount request rejected.'),
            'data' => $this->detailPayload($rejectedRequest, $locale),
        ]);
    }

    private function relationships(): array
    {
        return [
            'requestedBy:id,name,email,branch_id',
            'reviewedBy:id,name,email',
            'reservation:id,reservation_number,user_id,car_id,start_date,end_date,total_amount,discount_amount,status,created_at',
            'reservation.user:id,name,email',
            'reservation.car:id,branch_id,year,make,model,license_plate,fuel_type',
            'reservation.car.files',
            'reservation.car.branch:id,name,city,country,address',
            'contract:id,contract_number,created_at',
            'returnReport:id,report_number,total_extra_charges,discount,payment_status,created_at',
        ];
    }

    private function applyPendingApprovalFilters(Builder $query, string $search): void
    {
        $query->where('status', DiscountRequestStatus::PENDING->value);

        if ($search === '') {
            return;
        }

        $query->where(function (Builder $q) use ($search): void {
            $q->where('reason', 'like', "%{$search}%")
                ->orWhereHas('reservation', fn (Builder $reservationQuery) => $reservationQuery->where('reservation_number', 'like', "%{$search}%"))
                ->orWhereHas('contract', fn (Builder $contractQuery) => $contractQuery->where('contract_number', 'like', "%{$search}%"))
                ->orWhereHas('reservation.user', function (Builder $clientQuery) use ($search): void {
                    $clientQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                ->orWhereHas('requestedBy', function (Builder $employeeQuery) use ($search): void {
                    $employeeQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
        });
    }

    private function applyBranchScope(Builder $query, User $user, ?int $branchId): void
    {
        if ($branchId) {
            $query->whereHas('reservation.car', fn (Builder $carQuery) => $carQuery->where('branch_id', $branchId));
        }
    }

    private function summaryPayload(User $user, ?int $branchId, string $locale): array
    {
        $baseQuery = DiscountRequest::query();
        $this->applyBranchScope($baseQuery, $user, $branchId);

        $pending = (clone $baseQuery)->where('status', DiscountRequestStatus::PENDING->value)->count();

        return [
            'title' => $this->ownerText('discount_requests.summary.title', $locale, 'Pending approval requests'),
            'description' => $this->ownerText('discount_requests.summary.description', $locale, 'Need your review and decision.'),
            'pending_count' => $pending,
            'total_count' => $pending,
        ];
    }

    private function listPayload(DiscountRequest $discountRequest, string $locale): array
    {
        $status = $this->statusValue($discountRequest);
        $clientName = $discountRequest->reservation?->user?->name
            ?: $this->ownerText('discount_requests.unknown_client', $locale, 'Client');
        $carName = $this->carName($discountRequest);

        return [
            'id' => $discountRequest->id,
            'type' => 'discount_request',
            'type_label' => $this->ownerText('discount_requests.type_label', $locale, 'Discount request'),
            'title' => $this->ownerText('discount_requests.type_label', $locale, 'Discount request'),
            'description' => $this->formatDiscountDescription($discountRequest, $locale, $clientName),
            'status' => $status,
            'status_label' => $this->statusLabel($status, $locale),
            'branch' => $this->branchPayload($discountRequest),
            'client' => [
                'id' => $discountRequest->reservation?->user?->id,
                'name' => $clientName,
                'email' => $discountRequest->reservation?->user?->email,
            ],
            'employee' => $this->userPayload($discountRequest->requestedBy),
            'car' => [
                'id' => $discountRequest->reservation?->car?->id,
                'name' => $carName,
                'license_plate' => $discountRequest->reservation?->car?->license_plate,
                'image_url' => $this->absoluteUrl($discountRequest->reservation?->car?->image_url),
            ],
            'amounts' => $this->amountPayload($discountRequest),
            'requested_at' => optional($discountRequest->created_at)->toIso8601String(),
            'formatted_time' => optional($discountRequest->created_at)->format('Y-m-d H:i'),
            'icon' => 'tag',
            'accent' => '#0EA5E9',
            'actions' => [
                'can_approve' => $status === DiscountRequestStatus::PENDING->value,
                'can_reject' => $status === DiscountRequestStatus::PENDING->value,
            ],
        ];
    }

    private function detailPayload(DiscountRequest $discountRequest, string $locale): array
    {
        $payload = $this->listPayload($discountRequest, $locale);

        $payload['reservation'] = $discountRequest->reservation ? [
            'id' => $discountRequest->reservation->id,
            'reservation_number' => $discountRequest->reservation->reservation_number,
            'status' => $discountRequest->reservation->status instanceof \BackedEnum
                ? $discountRequest->reservation->status->value
                : (string) $discountRequest->reservation->status,
            'start_date' => optional($discountRequest->reservation->start_date)->toDateString(),
            'end_date' => optional($discountRequest->reservation->end_date)->toDateString(),
            'duration_days' => $this->durationDays($discountRequest),
            'total_amount' => (float) ($discountRequest->reservation->total_amount ?? 0),
            'discount_amount' => (float) ($discountRequest->reservation->discount_amount ?? 0),
        ] : null;
        $payload['contract'] = $discountRequest->contract ? [
            'id' => $discountRequest->contract->id,
            'contract_number' => $discountRequest->contract->contract_number,
            'created_at' => optional($discountRequest->contract->created_at)->toIso8601String(),
        ] : null;
        $payload['return_report'] = $discountRequest->returnReport ? [
            'id' => $discountRequest->returnReport->id,
            'report_number' => $discountRequest->returnReport->report_number,
            'total_extra_charges' => (float) ($discountRequest->returnReport->total_extra_charges ?? 0),
            'discount' => (float) ($discountRequest->returnReport->discount ?? 0),
            'payment_status' => $discountRequest->returnReport->payment_status,
            'created_at' => optional($discountRequest->returnReport->created_at)->toIso8601String(),
        ] : null;
        $payload['reviewed_by'] = $this->userPayload($discountRequest->reviewedBy);
        $payload['reason'] = $discountRequest->reason;
        $payload['review_note'] = $discountRequest->review_note;
        $payload['timeline'] = $this->timelinePayload($discountRequest, $locale);
        $payload['previous_approved_discounts'] = $this->previousApprovedDiscountsPayload($discountRequest);
        $payload['created_at'] = optional($discountRequest->created_at)->toIso8601String();
        $payload['reviewed_at'] = optional($discountRequest->reviewed_at)->toIso8601String();
        $payload['approved_at'] = optional($discountRequest->approved_at)->toIso8601String();
        $payload['rejected_at'] = optional($discountRequest->rejected_at)->toIso8601String();

        return $payload;
    }

    private function timelinePayload(DiscountRequest $discountRequest, string $locale): array
    {
        return array_values(array_filter([
            $discountRequest->reservation?->created_at ? [
                'key' => 'reservation_created',
                'label' => $this->ownerText('discount_requests.timeline.reservation_created', $locale, 'Reservation created'),
                'date' => optional($discountRequest->reservation->created_at)->toIso8601String(),
                'formatted_date' => optional($discountRequest->reservation->created_at)->format('Y-m-d H:i'),
            ] : null,
            $discountRequest->contract?->created_at ? [
                'key' => 'contract_created',
                'label' => $this->ownerText('discount_requests.timeline.contract_created', $locale, 'Contract created'),
                'date' => optional($discountRequest->contract->created_at)->toIso8601String(),
                'formatted_date' => optional($discountRequest->contract->created_at)->format('Y-m-d H:i'),
            ] : null,
            $discountRequest->returnReport?->created_at ? [
                'key' => 'return_report_created',
                'label' => $this->ownerText('discount_requests.timeline.return_report_created', $locale, 'Return report created'),
                'date' => optional($discountRequest->returnReport->created_at)->toIso8601String(),
                'formatted_date' => optional($discountRequest->returnReport->created_at)->format('Y-m-d H:i'),
            ] : null,
            $discountRequest->created_at ? [
                'key' => 'approval_requested',
                'label' => $this->ownerText('discount_requests.timeline.approval_requested', $locale, 'Approval request submitted'),
                'date' => optional($discountRequest->created_at)->toIso8601String(),
                'formatted_date' => optional($discountRequest->created_at)->format('Y-m-d H:i'),
            ] : null,
        ]));
    }

    private function previousApprovedDiscountsPayload(DiscountRequest $discountRequest): array
    {
        if (!$discountRequest->contract_return_report_id) {
            return [];
        }

        return DiscountRequest::query()
            ->with(['requestedBy:id,name,email', 'reviewedBy:id,name,email'])
            ->where('contract_return_report_id', $discountRequest->contract_return_report_id)
            ->where('status', DiscountRequestStatus::APPROVED->value)
            ->whereKeyNot($discountRequest->id)
            ->orderByDesc('approved_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (DiscountRequest $previousRequest): array => [
                'id' => $previousRequest->id,
                'requested_by' => $this->userPayload($previousRequest->requestedBy),
                'reviewed_by' => $this->userPayload($previousRequest->reviewedBy),
                'base_amount' => (float) $previousRequest->base_amount,
                'discount_type' => $previousRequest->discount_type,
                'discount_value' => (float) $previousRequest->discount_value,
                'discount_amount' => (float) $previousRequest->discount_amount,
                'final_amount' => (float) $previousRequest->final_amount,
                'reason' => $previousRequest->reason,
                'created_at' => optional($previousRequest->created_at)->toIso8601String(),
                'approved_at' => optional($previousRequest->approved_at)->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    private function amountPayload(DiscountRequest $discountRequest): array
    {
        return [
            'base_amount' => (float) $discountRequest->base_amount,
            'discount_type' => $discountRequest->discount_type,
            'discount_value' => (float) $discountRequest->discount_value,
            'discount_amount' => (float) $discountRequest->discount_amount,
            'final_amount' => (float) $discountRequest->final_amount,
        ];
    }

    private function branchPayload(DiscountRequest $discountRequest): ?array
    {
        $branch = $discountRequest->reservation?->car?->branch;

        if (!$branch) {
            return null;
        }

        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'city' => $branch->city,
            'country' => $branch->country,
            'address' => $branch->address,
        ];
    }

    private function userPayload(?User $user): ?array
    {
        if (!$user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }

    private function formatDiscountDescription(DiscountRequest $discountRequest, string $locale, string $clientName): string
    {
        $discount = $discountRequest->discount_type === 'percentage'
            ? number_format((float) $discountRequest->discount_value, 2).'%'
            : number_format((float) $discountRequest->discount_amount, 2);

        return $this->ownerText('discount_requests.description', $locale, 'Discount :discount requested - client :client', [
            'discount' => $discount,
            'client' => $clientName,
        ]);
    }

    private function carName(DiscountRequest $discountRequest): ?string
    {
        $car = $discountRequest->reservation?->car;

        if (!$car) {
            return null;
        }

        return trim(sprintf(
            '%s %s %s',
            (string) ($car->year ?? ''),
            (string) ($car->make ?? ''),
            (string) ($car->model ?? '')
        ));
    }

    private function durationDays(DiscountRequest $discountRequest): ?int
    {
        $start = $discountRequest->reservation?->start_date;
        $end = $discountRequest->reservation?->end_date;

        if (!$start || !$end) {
            return null;
        }

        return (int) max(1, $start->diffInDays($end) + 1);
    }

    private function statusValue(DiscountRequest $discountRequest): string
    {
        return $discountRequest->status instanceof DiscountRequestStatus
            ? $discountRequest->status->value
            : (string) $discountRequest->status;
    }

    private function statusLabel(string $status, string $locale): string
    {
        return $this->ownerText('discount_requests.statuses.'.$status, $locale, ucfirst($status));
    }

    private function canAccessDiscountRequest(DiscountRequest $discountRequest, User $user): bool
    {
        $discountRequest->loadMissing('reservation.car:id,branch_id');

        return $this->branchAccess->canAccessBranchId(
            $user,
            $discountRequest->reservation?->car?->branch_id ? (int) $discountRequest->reservation->car->branch_id : null
        );
    }

    private function authorizedOwner(Request $request): User
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless($user->role === UserRole::ADMIN, 403);
        abort_unless(!empty($user->tenant_id), 403);
        abort_unless($this->branchAccess->canUseOwnerApis($user), 403);

        return $user;
    }

    private function resolveOwnerBranchId(Request $request, User $user): ?int
    {
        $branchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));

        if (!$this->branchAccess->canAccessAllBranches($user)) {
            return $this->branchAccess->ownerScopedBranchId($user);
        }

        if (!$branchId) {
            return null;
        }

        $exists = Branch::query()
            ->where('tenant_id', (int) $user->tenant_id)
            ->whereKey($branchId)
            ->exists();

        if (!$exists) {
            throw ValidationException::withMessages([
                'branch_id' => [$this->ownerText('errors.branch_invalid', $this->resolveLocale($request), 'Selected branch is invalid or not accessible.')],
            ]);
        }

        return $branchId;
    }

    private function absoluteUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        return url($url);
    }

    private function resolveLocale(Request $request): string
    {
        $supportedLocales = array_values(array_filter(
            (array) config('app.available_locales', ['en']),
            static fn ($locale) => is_string($locale) && $locale !== ''
        ));
        $fallback = (string) config('app.fallback_locale', config('app.locale', 'en'));
        $preferred = $request->getPreferredLanguage($supportedLocales);

        if (is_string($preferred) && $preferred !== '') {
            return $preferred;
        }

        return in_array($fallback, $supportedLocales, true) ? $fallback : ($supportedLocales[0] ?? 'en');
    }

    private function ownerText(string $key, string $locale, string $fallback, array $replace = []): string
    {
        $translationKey = 'owner_api.'.$key;
        $fileKey = 'site.'.$translationKey;
        $fileFallback = trans($fileKey, $replace, $locale);

        if (!is_string($fileFallback) || $fileFallback === $fileKey) {
            $fileFallback = $fallback;
            foreach ($replace as $name => $value) {
                $fileFallback = str_replace(':'.$name, (string) $value, $fileFallback);
            }
        }

        $translated = TenantTranslations::get($translationKey, $locale, $fileFallback);

        foreach ($replace as $name => $value) {
            $translated = str_replace(':'.$name, (string) $value, $translated);
        }

        return $translated;
    }
}
