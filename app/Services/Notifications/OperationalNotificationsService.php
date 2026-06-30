<?php

namespace App\Services\Notifications;

use App\Enums\ContractStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\CarDamageReport;
use App\Models\Contract;
use App\Models\Reservation;
use App\Models\User;
use App\Support\BranchAccess;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class OperationalNotificationsService
{
    public function __construct(private readonly BranchAccess $branchAccess)
    {
    }

    public function resolveLocale(Request $request): string
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

    public function resolveBranchId(Request $request, User $user): ?int
    {
        $requestedBranchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));

        if ($this->branchAccess->canAccessAllBranches($user)) {
            return $requestedBranchId;
        }

        return (int) ($user->branch_id ?? 0) > 0 ? (int) $user->branch_id : null;
    }

    public function forUser(User $user, ?int $branchId = null, int $limit = 20, string $locale = 'en', ?string $type = null): Collection
    {
        $type = trim((string) $type);
        $limit = max(1, min(500, $limit));

        $items = collect()
            ->merge($type === '' || $type === 'overdue_return' ? $this->overdueReturnNotifications($user, $branchId, $locale) : [])
            ->merge($type === '' || $type === 'missing_documents' ? $this->missingDocumentNotifications($user, $branchId, $locale) : [])
            ->merge($type === '' || $type === 'new_damage' ? $this->newDamageNotifications($user, $branchId, $locale) : [])
            ->merge($type === '' || $type === 'missing_payment' ? $this->missingPaymentNotifications($user, $branchId, $locale) : [])
            ->sort(function (array $a, array $b): int {
                $dateComparison = ($b['occurred_at_sort'] ?? 0) <=> ($a['occurred_at_sort'] ?? 0);

                if ($dateComparison !== 0) {
                    return $dateComparison;
                }

                return ($a['priority_rank'] ?? 999) <=> ($b['priority_rank'] ?? 999);
            })
            ->take($limit)
            ->map(function (array $item): array {
                unset($item['priority_rank'], $item['occurred_at_sort']);

                return $item;
            })
            ->values();

        return $this->attachReadState($user, $items);
    }

    public function unreadForUser(User $user, ?int $branchId = null, int $limit = 500, string $locale = 'en', ?string $type = null): Collection
    {
        return $this->forUser($user, $branchId, $limit, $locale, $type)
            ->filter(fn (array $item): bool => empty($item['read_at']))
            ->values();
    }

    public function markCurrentAsRead(User $user, ?int $branchId = null, string $locale = 'en', ?string $type = null): int
    {
        $items = $this->unreadForUser($user, $branchId, 500, $locale, $type);
        if ($items->isEmpty()) {
            return 0;
        }

        $now = now();
        $rows = $items
            ->pluck('id')
            ->filter()
            ->unique()
            ->map(fn (string $id): array => [
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'notification_key' => $id,
                'read_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        DB::table('operational_notification_reads')->upsert(
            $rows,
            ['user_id', 'notification_key'],
            ['read_at', 'updated_at']
        );

        return count($rows);
    }

    private function attachReadState(User $user, Collection $items): Collection
    {
        if ($items->isEmpty()) {
            return $items;
        }

        $keys = $items
            ->pluck('id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $readAtByKey = DB::table('operational_notification_reads')
            ->where('user_id', $user->id)
            ->whereIn('notification_key', $keys)
            ->pluck('read_at', 'notification_key');

        return $items
            ->map(function (array $item) use ($readAtByKey): array {
                $item['read_at'] = $readAtByKey[$item['id']] ?? null;

                return $item;
            })
            ->values();
    }

    private function overdueReturnNotifications(User $user, ?int $branchId, string $locale): Collection
    {
        $now = now();
        $query = Contract::query()
            ->with(['reservation.user:id,name,email', 'reservation.car:id,branch_id,year,make,model,license_plate'])
            ->where('status', ContractStatus::ACTIVE->value)
            ->where(function (Builder $query) use ($now): void {
                $query->whereDate('end_date', '<', $now->toDateString())
                    ->orWhere(function (Builder $query) use ($now): void {
                        $query->whereDate('end_date', $now->toDateString())
                            ->whereHas('reservation', function (Builder $query) use ($now): void {
                                $query->whereNotNull('return_time')
                                    ->whereTime('return_time', '<', $now->format('H:i:s'));
                            });
                    });
            });

        $this->applyContractBranchScope($query, $user, $branchId);

        return $query
            ->latest('end_date')
            ->limit(30)
            ->get()
            ->map(function (Contract $contract) use ($now, $locale): array {
                $reservation = $contract->reservation;
                $carName = $this->carName($reservation?->car);
                $returnAt = $this->contractReturnAt($contract);
                $minutesLate = $returnAt ? (int) max(0, $returnAt->diffInMinutes($now)) : 0;

                return $this->notification(
                    id: 'overdue_return:'.$contract->id,
                    type: 'overdue_return',
                    priority: 'important',
                    priorityRank: 1,
                    label: $this->text($locale, 'Important', 'مهم', 'اہم'),
                    title: $this->text($locale, 'Overdue car', 'سيارة متأخرة', 'تاخیر شدہ گاڑی'),
                    message: $this->text(
                        $locale,
                        trim($carName.' is overdue by '.$this->durationLabel($minutesLate, $locale).'.'),
                        trim($carName.' متأخرة '.$this->durationLabel($minutesLate, $locale).'.'),
                        trim($carName.' '.$this->durationLabel($minutesLate, $locale).' تاخیر سے ہے۔')
                    ),
                    occurredAt: $returnAt,
                    data: [
                        'contract_id' => $contract->id,
                        'contract_number' => $contract->contract_number,
                        'reservation_id' => $reservation?->id,
                        'reservation_number' => $reservation?->reservation_number,
                        'car_id' => $reservation?->car?->id,
                        'car_name' => $carName,
                        'client_name' => $reservation?->user?->name,
                        'minutes_late' => $minutesLate,
                    ],
                    color: '#FEE2E2',
                    textColor: '#DC2626'
                );
            });
    }

    private function missingDocumentNotifications(User $user, ?int $branchId, string $locale): Collection
    {
        $query = Contract::query()
            ->with([
                'reservation.user:id,name,email',
                'reservation.car:id,branch_id,year,make,model,license_plate',
                'primaryDriver.documents:id,contract_driver_id,document_type,side',
            ])
            ->whereIn('status', [ContractStatus::DRAFT->value, ContractStatus::PENDING->value, ContractStatus::ACTIVE->value])
            ->whereHas('primaryDriver', function (Builder $query): void {
                $query->whereDoesntHave('documents');
            });

        $this->applyContractBranchScope($query, $user, $branchId);

        return $query
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(function (Contract $contract) use ($locale): array {
                $reservation = $contract->reservation;
                $number = $reservation?->reservation_number ?? $contract->contract_number;

                return $this->notification(
                    id: 'missing_documents:'.$contract->id,
                    type: 'missing_documents',
                    priority: 'review',
                    priorityRank: 2,
                    label: $this->text($locale, 'Review', 'مراجعة', 'جائزہ'),
                    title: $this->text($locale, 'Missing documents', 'وثائق ناقصة', 'نامکمل دستاویزات'),
                    message: $this->text(
                        $locale,
                        'Reservation '.$number.' needs customer document photos.',
                        'حجز '.$number.' يحتاج صور وثائق العميل.',
                        'ریزرویشن '.$number.' کو کسٹمر دستاویزات کی تصاویر درکار ہیں۔'
                    ),
                    occurredAt: $contract->updated_at,
                    data: [
                        'contract_id' => $contract->id,
                        'contract_number' => $contract->contract_number,
                        'reservation_id' => $reservation?->id,
                        'reservation_number' => $reservation?->reservation_number,
                        'client_name' => $reservation?->user?->name,
                        'car_name' => $this->carName($reservation?->car),
                    ],
                    color: '#FFEDD5',
                    textColor: '#EA580C'
                );
            });
    }

    private function newDamageNotifications(User $user, ?int $branchId, string $locale): Collection
    {
        $query = CarDamageReport::query()
            ->with(['car:id,branch_id,year,make,model,license_plate', 'contract:id,contract_number', 'reservation:id,reservation_number'])
            ->withCount('items')
            ->where('report_type', 'after_return')
            ->where('status', 'draft')
            ->whereHas('items');

        $this->applyDamageReportBranchScope($query, $user, $branchId);

        return $query
            ->latest('inspected_at')
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(function (CarDamageReport $report) use ($locale): array {
                $carName = $this->carName($report->car);

                return $this->notification(
                    id: 'new_damage:'.$report->id,
                    type: 'new_damage',
                    priority: 'accident',
                    priorityRank: 3,
                    label: $this->text($locale, 'Incident', 'حادث', 'حادثہ'),
                    title: $this->text($locale, 'New damage', 'ضرر جديد', 'نیا نقصان'),
                    message: $this->text(
                        $locale,
                        'Damage was recorded on '.$carName.'.',
                        'تم تسجيل ضرر على '.$carName.'.',
                        $carName.' پر نقصان ریکارڈ کیا گیا۔'
                    ),
                    occurredAt: $report->inspected_at ?? $report->created_at,
                    data: [
                        'damage_report_id' => $report->id,
                        'damage_report_number' => $report->report_number,
                        'contract_id' => $report->contract_id,
                        'contract_number' => $report->contract?->contract_number,
                        'reservation_id' => $report->reservation_id,
                        'reservation_number' => $report->reservation?->reservation_number,
                        'car_id' => $report->car_id,
                        'car_name' => $carName,
                        'items_count' => (int) $report->items_count,
                    ],
                    color: '#FFE4E6',
                    textColor: '#E11D48'
                );
            });
    }

    private function missingPaymentNotifications(User $user, ?int $branchId, string $locale): Collection
    {
        $query = Reservation::query()
            ->with(['user:id,name,email', 'car:id,branch_id,year,make,model,license_plate', 'contract.returnStatusReport'])
            ->withSum(['payments as completed_payments_sum' => function (Builder $query): void {
                $query->where('status', PaymentStatus::COMPLETED->value);
            }], 'amount')
            ->whereIn('status', [
                ReservationStatus::PENDING->value,
                ReservationStatus::CONFIRMED->value,
                ReservationStatus::ACTIVE->value,
                ReservationStatus::COMPLETED_WAIT_CONTRACT->value,
            ]);

        $this->applyReservationBranchScope($query, $user, $branchId);

        return $query
            ->latest('id')
            ->limit(80)
            ->get()
            ->map(function (Reservation $reservation) use ($locale): ?array {
                $returnReportTotal = (float) ($reservation->contract?->returnStatusReport?->total_extra_charges ?? 0);
                $totalDue = max(0, (float) $reservation->total_amount + $returnReportTotal);
                $paid = (float) ($reservation->completed_payments_sum ?? 0);
                $balance = round(max(0, $totalDue - $paid), 2);

                if ($balance <= 0) {
                    return null;
                }

                $carName = $this->carName($reservation->car);

                return $this->notification(
                    id: 'missing_payment:'.$reservation->id,
                    type: 'missing_payment',
                    priority: 'payment',
                    priorityRank: 4,
                    label: $this->text($locale, 'Payment', 'دفع', 'ادائیگی'),
                    title: $this->text($locale, 'Missing payment', 'دفع ناقص', 'باقی ادائیگی'),
                    message: $this->text(
                        $locale,
                        'Reservation '.$carName.' needs payment '.$this->money($balance).'.',
                        'حجز '.$carName.' يحتاج دفع '.$this->money($balance).'.',
                        $carName.' ریزرویشن کو '.$this->money($balance).' ادائیگی درکار ہے۔'
                    ),
                    occurredAt: $reservation->updated_at,
                    data: [
                        'reservation_id' => $reservation->id,
                        'reservation_number' => $reservation->reservation_number,
                        'contract_id' => $reservation->contract?->id,
                        'contract_number' => $reservation->contract?->contract_number,
                        'car_id' => $reservation->car?->id,
                        'car_name' => $carName,
                        'client_name' => $reservation->user?->name,
                        'total_due' => round($totalDue, 2),
                        'paid_amount' => round($paid, 2),
                        'balance_due' => $balance,
                    ],
                    color: '#DBEAFE',
                    textColor: '#2563EB'
                );
            })
            ->filter()
            ->take(30)
            ->values();
    }

    private function notification(
        string $id,
        string $type,
        string $priority,
        int $priorityRank,
        string $label,
        string $title,
        string $message,
        ?Carbon $occurredAt,
        array $data,
        string $color,
        string $textColor
    ): array {
        return [
            'id' => $id,
            'type' => $type,
            'priority' => $priority,
            'label' => $label,
            'title' => $title,
            'message' => $message,
            'occurred_at' => $occurredAt?->toIso8601String(),
            'data' => $data,
            'ui' => [
                'badge_background' => $color,
                'badge_color' => $textColor,
            ],
            'priority_rank' => $priorityRank,
            'occurred_at_sort' => $occurredAt?->timestamp ?? 0,
        ];
    }

    private function text(string $locale, string $en, string $ar, string $ur): string
    {
        return match (strtolower($locale)) {
            'ar' => $ar,
            'ur' => $ur,
            default => $en,
        };
    }

    private function durationLabel(int $minutes, string $locale): string
    {
        if ($minutes < 60) {
            return $this->text($locale, "{$minutes} minutes", "{$minutes} دقيقة", "{$minutes} منٹ");
        }

        $hours = round($minutes / 60, 1);

        return $this->text($locale, "{$hours} hours", "{$hours} ساعة", "{$hours} گھنٹے");
    }

    private function money(float $amount): string
    {
        return '$'.number_format($amount, 2);
    }

    private function carName($car): string
    {
        $name = trim(sprintf(
            '%s %s %s',
            (string) ($car?->year ?? ''),
            (string) ($car?->make ?? ''),
            (string) ($car?->model ?? '')
        ));

        return $name !== '' ? $name : 'Car';
    }

    private function contractReturnAt(Contract $contract): ?Carbon
    {
        if (!$contract->end_date) {
            return null;
        }

        $date = $contract->end_date->toDateString();
        $time = $contract->reservation?->return_time?->format('H:i:s') ?: '23:59:59';

        return Carbon::parse($date.' '.$time);
    }

    private function applyReservationBranchScope(Builder $query, User $user, ?int $branchId): void
    {
        if ($this->branchAccess->canAccessAllBranches($user)) {
            if ($branchId) {
                $query->whereHas('car', fn (Builder $q) => $q->where('branch_id', $branchId));
            }

            return;
        }

        $userBranchId = (int) ($user->branch_id ?? 0);
        $query->whereHas('car', fn (Builder $q) => $q->where('branch_id', $userBranchId > 0 ? $userBranchId : -1));
    }

    private function applyContractBranchScope(Builder $query, User $user, ?int $branchId): void
    {
        if ($this->branchAccess->canAccessAllBranches($user)) {
            if ($branchId) {
                $query->whereHas('reservation.car', fn (Builder $q) => $q->where('branch_id', $branchId));
            }

            return;
        }

        $userBranchId = (int) ($user->branch_id ?? 0);
        $query->whereHas('reservation.car', fn (Builder $q) => $q->where('branch_id', $userBranchId > 0 ? $userBranchId : -1));
    }

    private function applyDamageReportBranchScope(Builder $query, User $user, ?int $branchId): void
    {
        if ($this->branchAccess->canAccessAllBranches($user)) {
            if ($branchId) {
                $query->whereHas('car', fn (Builder $q) => $q->where('branch_id', $branchId));
            }

            return;
        }

        $userBranchId = (int) ($user->branch_id ?? 0);
        $query->whereHas('car', fn (Builder $q) => $q->where('branch_id', $userBranchId > 0 ? $userBranchId : -1));
    }
}
