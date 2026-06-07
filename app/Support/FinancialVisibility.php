<?php

namespace App\Support;

use App\Models\User;

class FinancialVisibility
{
    public const FINANCIAL_PERMISSION = 'tenant-view-financials';

    public static function canViewFinancialAmounts(?User $user): bool
    {
        return (bool) ($user?->hasPermission(self::FINANCIAL_PERMISSION) ?? false);
    }

    public static function numericAmount(float|int|string|null $amount, bool $canViewFinancialAmounts): float
    {
        if (!$canViewFinancialAmounts) {
            return 0.0;
        }

        return (float) ($amount ?? 0);
    }
}
