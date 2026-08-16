<?php

namespace App\Support;

use App\Models\User;

class FinancialVisibility
{
    public const FINANCIAL_PERMISSION = 'tenant-view-financials';
    public const GRANULAR_FINANCIAL_PERMISSION = 'tenant-financials.view';

    public static function canViewFinancialAmounts(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->hasPermission(self::GRANULAR_FINANCIAL_PERMISSION)
            || $user->hasPermission(self::FINANCIAL_PERMISSION);
    }

    public static function numericAmount(float|int|string|null $amount, bool $canViewFinancialAmounts): float
    {
        if (!$canViewFinancialAmounts) {
            return 0.0;
        }

        return (float) ($amount ?? 0);
    }
}
