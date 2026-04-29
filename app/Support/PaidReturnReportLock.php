<?php

namespace App\Support;

use App\Models\CarDamageReport;
use App\Models\Contract;
use App\Models\ContractReturnReport;
use App\Models\Reservation;

class PaidReturnReportLock
{
    public static function contract(Contract $contract): bool
    {
        return ContractReturnReport::query()
            ->withoutGlobalScopes()
            ->where('contract_id', $contract->getKey())
            ->where('payment_status', 'paid')
            ->exists();
    }

    public static function reservation(Reservation $reservation): bool
    {
        return ContractReturnReport::query()
            ->withoutGlobalScopes()
            ->where('reservation_id', $reservation->getKey())
            ->where('payment_status', 'paid')
            ->exists();
    }

    public static function damageReport(CarDamageReport $damageReport): bool
    {
        return ContractReturnReport::query()
            ->withoutGlobalScopes()
            ->where('contract_id', $damageReport->contract_id)
            ->where('payment_status', 'paid')
            ->exists();
    }
}
