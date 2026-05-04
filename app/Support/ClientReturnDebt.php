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

    public static function blockingMessage(?float $amount = null): string
    {
        $suffix = $amount !== null && $amount > 0
            ? ' Outstanding amount: '.number_format($amount, 2).'.'
            : '';

        return 'This client has unpaid return charges and cannot create a new reservation before settlement.'.$suffix;
    }
}
