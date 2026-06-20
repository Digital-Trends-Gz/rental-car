<?php

namespace App\Support;

use App\Models\ContractReturnReport;
use Illuminate\Database\Eloquent\Builder;

class ClientReturnDebt
{
    public static function outstandingQuery(int $tenantId, ?int $clientId = null): Builder
    {
        return ContractReturnReport::query()
            ->where('tenant_id', $tenantId)
            ->where('payment_status', 'not_paid')
            ->where('total_extra_charges', '>', 0)
            ->when($clientId, function (Builder $query) use ($clientId): void {
                $query->whereHas('reservation', fn (Builder $reservationQuery) => $reservationQuery->where('user_id', $clientId));
            });
    }

    public static function hasOutstanding(int $tenantId, int $clientId): bool
    {
        if ($tenantId <= 0 || $clientId <= 0) {
            return false;
        }

        return self::outstandingQuery($tenantId, $clientId)->exists();
    }

    public static function outstandingTotal(int $tenantId, int $clientId): float
    {
        if ($tenantId <= 0 || $clientId <= 0) {
            return 0.0;
        }

        return (float) self::outstandingQuery($tenantId, $clientId)->sum('total_extra_charges');
    }

    /**
     * @param  array<int, int>  $clientIds
     * @return array<int, float>
     */
    public static function outstandingTotalsByClientIds(int $tenantId, array $clientIds): array
    {
        if ($tenantId <= 0 || $clientIds === []) {
            return [];
        }

        return ContractReturnReport::query()
            ->selectRaw('reservations.user_id as client_id, SUM(contract_return_reports.total_extra_charges) as total')
            ->join('reservations', 'reservations.id', '=', 'contract_return_reports.reservation_id')
            ->where('contract_return_reports.tenant_id', $tenantId)
            ->where('contract_return_reports.payment_status', 'not_paid')
            ->where('contract_return_reports.total_extra_charges', '>', 0)
            ->whereIn('reservations.user_id', $clientIds)
            ->groupBy('reservations.user_id')
            ->pluck('total', 'client_id')
            ->mapWithKeys(fn ($total, $clientId): array => [(int) $clientId => round((float) $total, 2)])
            ->all();
    }

    public static function blockingMessage(?float $amount = null, ?string $locale = null): string
    {
        $locale = $locale === 'ar' ? 'ar' : 'en';
        $formattedAmount = $amount !== null && $amount > 0
            ? number_format($amount, 2)
            : null;

        if ($locale === 'ar') {
            $suffix = $formattedAmount !== null
                ? " المبلغ المستحق: {$formattedAmount}."
                : '';

            return 'هذا العميل لديه رسوم رجوع غير مدفوعة ولا يمكن إنشاء حجز جديد قبل التسوية.'.$suffix;
        }

        $suffix = $formattedAmount !== null
            ? " Outstanding amount: {$formattedAmount}."
            : '';

        return 'This client has unpaid return charges and cannot create a new reservation before settlement.'.$suffix;
    }
}
