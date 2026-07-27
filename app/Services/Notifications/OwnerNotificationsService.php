<?php

namespace App\Services\Notifications;

use App\Enums\CarStatus;
use App\Enums\CarViolationStatus;
use App\Enums\ContractStatus;
use App\Enums\MaintenanceRecordStatus;
use App\Enums\PaymentStatus;
use App\Models\Car;
use App\Models\CarMaintenance;
use App\Models\CarViolation;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\TenantSiteSetting;
use App\Models\User;
use App\Support\CurrencyCatalog;
use App\Support\TenantTranslations;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OwnerNotificationsService
{
    public function activeAlerts(User $user, ?int $branchId, string $locale): array
    {
        $today = Carbon::today();
        $tenantId = (int) $user->tenant_id;

        $lateReturns = Contract::query()
            ->where('tenant_id', $tenantId)
            ->pendingReturnTask($today)
            ->whereDate('end_date', '<', $today);
        $this->applyContractBranchScope($lateReturns, $branchId);

        $unpaidViolations = CarViolation::query()
            ->where('tenant_id', $tenantId)
            ->where('status', CarViolationStatus::PENDING->value);
        $this->applyDirectBranchScope($unpaidViolations, $branchId);

        $maintenanceCars = Car::query()
            ->where('tenant_id', $tenantId)
            ->where('status', CarStatus::MAINTENANCE->value);
        $this->applyDirectBranchScope($maintenanceCars, $branchId);

        $lateReturnsCount = $lateReturns->count();
        $unpaidViolationsCount = $unpaidViolations->count();
        $maintenanceCarsCount = $maintenanceCars->count();

        return [
            $this->activeAlertPayload(
                'late_returns',
                'clock',
                $lateReturnsCount,
                $locale,
                '#FEE2E2',
                '#EF4444',
                ['count' => $lateReturnsCount],
                ['type' => 'late_returns']
            ),
            $this->activeAlertPayload(
                'unpaid_violations',
                'receipt',
                $unpaidViolationsCount,
                $locale,
                '#FFEDD5',
                '#F97316',
                ['count' => $unpaidViolationsCount],
                ['type' => 'violations', 'status' => 'pending']
            ),
            $this->activeAlertPayload(
                'maintenance_cars',
                'wrench',
                $maintenanceCarsCount,
                $locale,
                '#F1F5F9',
                '#64748B',
                ['count' => $maintenanceCarsCount],
                ['type' => 'maintenance']
            ),
        ];
    }

    public function latestNotifications(User $user, ?int $branchId, string $locale, int $limit = 100): Collection
    {
        $limit = max(1, min(500, $limit));
        $tenantId = (int) $user->tenant_id;
        $currency = $this->tenantCurrency($tenantId, $locale);

        return collect()
            ->merge($this->lateReturnItems($tenantId, $branchId, $locale))
            ->merge($this->violationItems($tenantId, $branchId, $locale, $currency))
            ->merge($this->maintenanceItems($tenantId, $branchId, $locale))
            ->merge($this->reservationItems($tenantId, $branchId, $locale))
            ->merge($this->paymentItems($tenantId, $branchId, $locale, $currency))
            ->sortByDesc(fn (array $item): int => $item['occurred_at_sort'] ?? 0)
            ->take($limit)
            ->map(function (array $item): array {
                unset($item['occurred_at_sort']);

                return $item;
            })
            ->values();
    }

    public function paginated(User $user, ?int $branchId, string $locale, int $perPage, int $page): LengthAwarePaginator
    {
        $items = $this->attachReadState(
            $user,
            $this->latestNotifications($user, $branchId, $locale, 500),
            $locale
        );

        $perPage = max(1, min(100, $perPage));
        $page = max(1, $page);

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    public function unreadCount(User $user, ?int $branchId, string $locale): int
    {
        return $this->attachReadState($user, $this->latestNotifications($user, $branchId, $locale, 500), $locale)
            ->filter(fn (array $item): bool => empty($item['read_at']))
            ->count();
    }

    public function markLatestAsRead(User $user, ?int $branchId, string $locale): int
    {
        $items = $this->attachReadState($user, $this->latestNotifications($user, $branchId, $locale, 500), $locale)
            ->filter(fn (array $item): bool => empty($item['read_at']));

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

    private function lateReturnItems(int $tenantId, ?int $branchId, string $locale): Collection
    {
        $now = now();
        $query = Contract::query()
            ->with(['reservation.user:id,name,email', 'reservation.car:id,branch_id,year,make,model,license_plate'])
            ->where('tenant_id', $tenantId)
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
        $this->applyContractBranchScope($query, $branchId);

        return $query
            ->latest('end_date')
            ->limit(50)
            ->get()
            ->map(function (Contract $contract) use ($locale): array {
                $reservation = $contract->reservation;
                $carName = $this->carName($reservation?->car);
                $clientName = $reservation?->user?->name;
                $occurredAt = $this->contractReturnAt($contract) ?? $contract->updated_at;

                return $this->notificationPayload(
                    id: 'owner:late_return:'.$contract->id,
                    type: 'late_return',
                    icon: 'clock',
                    accent: '#EF4444',
                    background: '#FEE2E2',
                    locale: $locale,
                    titleKey: 'late_return.title',
                    descriptionKey: 'late_return.description',
                    replacements: [
                        'car' => $carName,
                        'client' => $clientName ?: $this->text('notifications.unknown_client', $locale, 'Client'),
                    ],
                    occurredAt: $occurredAt,
                    data: [
                        'contract_id' => $contract->id,
                        'contract_number' => $contract->contract_number,
                        'reservation_id' => $reservation?->id,
                        'reservation_number' => $reservation?->reservation_number,
                        'car_id' => $reservation?->car?->id,
                        'car_name' => $carName,
                        'client_name' => $clientName,
                    ],
                    action: ['type' => 'contract', 'id' => $contract->id]
                );
            });
    }

    private function violationItems(int $tenantId, ?int $branchId, string $locale, array $currency): Collection
    {
        $query = CarViolation::query()
            ->with(['car:id,branch_id,year,make,model,license_plate', 'issuedTo:id,name,email'])
            ->where('tenant_id', $tenantId)
            ->where('status', CarViolationStatus::PENDING->value);
        $this->applyDirectBranchScope($query, $branchId);

        return $query
            ->latest('violation_date')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(function (CarViolation $violation) use ($locale, $currency): array {
                return $this->notificationPayload(
                    id: 'owner:unpaid_violation:'.$violation->id,
                    type: 'unpaid_violation',
                    icon: 'receipt',
                    accent: '#F97316',
                    background: '#FFEDD5',
                    locale: $locale,
                    titleKey: 'unpaid_violation.title',
                    descriptionKey: 'unpaid_violation.description',
                    replacements: [
                        'amount' => $this->formatMoney((float) $violation->amount, $currency),
                        'client' => $violation->issuedTo?->name ?: $this->text('notifications.unknown_client', $locale, 'Client'),
                    ],
                    occurredAt: $violation->created_at,
                    data: [
                        'violation_id' => $violation->id,
                        'violation_number' => $violation->violation_number,
                        'car_id' => $violation->car_id,
                        'car_name' => $this->carName($violation->car),
                        'client_name' => $violation->issuedTo?->name,
                        'amount' => (float) $violation->amount,
                    ],
                    action: ['type' => 'violation', 'id' => $violation->id]
                );
            });
    }

    private function maintenanceItems(int $tenantId, ?int $branchId, string $locale): Collection
    {
        $query = CarMaintenance::query()
            ->with(['car:id,branch_id,year,make,model,license_plate'])
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [
                MaintenanceRecordStatus::SCHEDULED->value,
                MaintenanceRecordStatus::IN_PROGRESS->value,
            ]);
        $this->applyDirectBranchScope($query, $branchId);

        return $query
            ->latest('scheduled_date')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(function (CarMaintenance $maintenance) use ($locale): array {
                return $this->notificationPayload(
                    id: 'owner:maintenance_required:'.$maintenance->id,
                    type: 'maintenance_required',
                    icon: 'wrench',
                    accent: '#64748B',
                    background: '#F1F5F9',
                    locale: $locale,
                    titleKey: 'maintenance_required.title',
                    descriptionKey: 'maintenance_required.description',
                    replacements: [
                        'car' => $this->carName($maintenance->car),
                    ],
                    occurredAt: $maintenance->scheduled_date?->startOfDay() ?? $maintenance->created_at,
                    data: [
                        'maintenance_id' => $maintenance->id,
                        'car_id' => $maintenance->car_id,
                        'car_name' => $this->carName($maintenance->car),
                        'status' => $maintenance->status instanceof MaintenanceRecordStatus ? $maintenance->status->value : $maintenance->status,
                        'scheduled_date' => $maintenance->scheduled_date?->toDateString(),
                    ],
                    action: ['type' => 'maintenance', 'id' => $maintenance->id]
                );
            });
    }

    private function reservationItems(int $tenantId, ?int $branchId, string $locale): Collection
    {
        $query = Reservation::query()
            ->with(['user:id,name,email', 'car:id,branch_id,year,make,model,license_plate', 'car.branch:id,name'])
            ->where('tenant_id', $tenantId);
        $this->applyReservationBranchScope($query, $branchId);

        return $query
            ->latest('created_at')
            ->limit(50)
            ->get()
            ->map(function (Reservation $reservation) use ($locale): array {
                return $this->notificationPayload(
                    id: 'owner:new_reservation:'.$reservation->id,
                    type: 'new_reservation',
                    icon: 'calendar',
                    accent: '#14B8A6',
                    background: '#CCFBF1',
                    locale: $locale,
                    titleKey: 'new_reservation.title',
                    descriptionKey: 'new_reservation.description',
                    replacements: [
                        'branch' => $reservation->car?->branch?->name ?: '',
                        'days' => (string) ($reservation->total_days ?? $reservation->duration ?? 0),
                    ],
                    occurredAt: $reservation->created_at,
                    data: [
                        'reservation_id' => $reservation->id,
                        'reservation_number' => $reservation->reservation_number,
                        'car_id' => $reservation->car_id,
                        'car_name' => $this->carName($reservation->car),
                        'client_name' => $reservation->user?->name,
                        'total_days' => (int) ($reservation->total_days ?? 0),
                    ],
                    action: ['type' => 'reservation', 'id' => $reservation->id]
                );
            });
    }

    private function paymentItems(int $tenantId, ?int $branchId, string $locale, array $currency): Collection
    {
        $query = Payment::query()
            ->with(['reservation.user:id,name,email', 'reservation.car:id,branch_id,year,make,model,license_plate'])
            ->where('tenant_id', $tenantId)
            ->where('status', PaymentStatus::COMPLETED->value);
        $this->applyPaymentBranchScope($query, $branchId);

        return $query
            ->latest('processed_at')
            ->latest('created_at')
            ->limit(50)
            ->get()
            ->map(function (Payment $payment) use ($locale, $currency): array {
                $amount = (float) ($payment->base_amount ?? $payment->amount);

                return $this->notificationPayload(
                    id: 'owner:payment_received:'.$payment->id,
                    type: 'payment_received',
                    icon: 'cash',
                    accent: '#10B981',
                    background: '#DCFCE7',
                    locale: $locale,
                    titleKey: 'payment_received.title',
                    descriptionKey: 'payment_received.description',
                    replacements: [
                        'amount' => $this->formatMoney($amount, $currency),
                        'client' => $payment->reservation?->user?->name ?: $this->text('notifications.unknown_client', $locale, 'Client'),
                    ],
                    occurredAt: $payment->processed_at ?? $payment->created_at,
                    data: [
                        'payment_id' => $payment->id,
                        'payment_number' => $payment->payment_number,
                        'reservation_id' => $payment->reservation_id,
                        'reservation_number' => $payment->reservation?->reservation_number,
                        'client_name' => $payment->reservation?->user?->name,
                        'amount' => $amount,
                    ],
                    action: ['type' => 'payment', 'id' => $payment->id]
                );
            });
    }

    private function attachReadState(User $user, Collection $items, string $locale): Collection
    {
        if ($items->isEmpty()) {
            return $items;
        }

        $keys = $items->pluck('id')->filter()->unique()->values()->all();
        $readAtByKey = DB::table('operational_notification_reads')
            ->where('user_id', $user->id)
            ->whereIn('notification_key', $keys)
            ->pluck('read_at', 'notification_key');

        return $items
            ->map(function (array $item) use ($readAtByKey, $locale): array {
                $readAt = $readAtByKey[$item['id']] ?? null;
                $item['read_at'] = $readAt;
                $item['is_read'] = $readAt !== null;
                $item['time_ago'] = $this->timeAgo($item['occurred_at'] ?? null, $locale);

                return $item;
            })
            ->values();
    }

    private function notificationPayload(
        string $id,
        string $type,
        string $icon,
        string $accent,
        string $background,
        string $locale,
        string $titleKey,
        string $descriptionKey,
        array $replacements,
        ?Carbon $occurredAt,
        array $data,
        array $action
    ): array {
        return [
            'id' => $id,
            'type' => $type,
            'title' => $this->text('notifications.'.$titleKey, $locale, $type),
            'description' => $this->text('notifications.'.$descriptionKey, $locale, '', $replacements),
            'occurred_at' => $occurredAt?->toIso8601String(),
            'time_ago' => $this->timeAgo($occurredAt?->toIso8601String(), $locale),
            'is_read' => false,
            'read_at' => null,
            'icon' => $icon,
            'accent' => $accent,
            'background' => $background,
            'data' => $data,
            'action' => $action,
            'occurred_at_sort' => $occurredAt?->timestamp ?? 0,
        ];
    }

    private function activeAlertPayload(
        string $key,
        string $icon,
        int $count,
        string $locale,
        string $background,
        string $accent,
        array $replacements,
        array $action
    ): array {
        return [
            'key' => $key,
            'title' => $this->text('alerts.'.$key, $locale, $key),
            'description' => $this->text('alerts.'.$key.'_description', $locale, '', $replacements),
            'count' => $count,
            'icon' => $icon,
            'accent' => $accent,
            'background' => $background,
            'action' => $action,
        ];
    }

    private function applyDirectBranchScope(Builder $query, ?int $branchId): void
    {
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
    }

    private function applyReservationBranchScope(Builder $query, ?int $branchId): void
    {
        if ($branchId) {
            $query->whereHas('car', fn (Builder $query) => $query->where('branch_id', $branchId));
        }
    }

    private function applyContractBranchScope(Builder $query, ?int $branchId): void
    {
        if ($branchId) {
            $query->where(function (Builder $query) use ($branchId): void {
                $query->where('branch_id', $branchId)
                    ->orWhereHas('reservation.car', fn (Builder $query) => $query->where('branch_id', $branchId));
            });
        }
    }

    private function applyPaymentBranchScope(Builder $query, ?int $branchId): void
    {
        if ($branchId) {
            $query->whereHas('reservation.car', fn (Builder $query) => $query->where('branch_id', $branchId));
        }
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

    private function tenantCurrency(int $tenantId, string $locale): array
    {
        $tenant = Tenant::query()->find($tenantId);
        if (!$tenant) {
            return CurrencyCatalog::find('USD', $locale);
        }

        $settings = TenantSiteSetting::forTenant($tenant);

        return CurrencyCatalog::forTenant($tenant, data_get($settings, 'market_location.currency_code'), $locale);
    }

    private function formatMoney(float $amount, array $currency): string
    {
        $symbol = trim((string) ($currency['symbol'] ?? $currency['code'] ?? ''));

        return trim($symbol.' '.number_format($amount, 2));
    }

    private function timeAgo(?string $occurredAt, string $locale): ?string
    {
        if (!$occurredAt) {
            return null;
        }

        $minutes = max(0, Carbon::parse($occurredAt)->diffInMinutes(now()));

        if ($minutes < 60) {
            return $this->text('time.minutes_ago', $locale, ':count min', ['count' => (string) $minutes]);
        }

        $hours = (int) floor($minutes / 60);
        if ($hours < 24) {
            return $this->text('time.hours_ago', $locale, ':count h', ['count' => (string) $hours]);
        }

        $days = (int) floor($hours / 24);

        return $this->text('time.days_ago', $locale, ':count d', ['count' => (string) $days]);
    }

    private function text(string $key, string $locale, string $fallback, array $replacements = []): string
    {
        $translationKey = 'owner_api.'.$key;
        $fileKey = 'site.'.$translationKey;
        $fileFallback = trans($fileKey, [], $locale);

        if (!is_string($fileFallback) || $fileFallback === $fileKey) {
            $fileFallback = $fallback;
        }

        $value = TenantTranslations::get($translationKey, $locale, $fileFallback);

        foreach ($replacements as $name => $replacement) {
            $value = str_replace(':'.$name, (string) $replacement, $value);
        }

        return $value;
    }
}
