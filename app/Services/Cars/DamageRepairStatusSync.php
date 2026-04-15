<?php

namespace App\Services\Cars;

use App\Enums\DamageRepairStatus;
use App\Models\CarDamageCase;
use App\Models\DamageRepair;
use Carbon\CarbonImmutable;

class DamageRepairStatusSync
{
    public function syncForCase(int|CarDamageCase $damageCase): void
    {
        $case = $damageCase instanceof CarDamageCase
            ? $damageCase
            : CarDamageCase::query()->find($damageCase);

        if (!$case) {
            return;
        }

        $activeRepairExists = DamageRepair::query()
            ->where('car_damage_case_id', $case->id)
            ->whereIn('status', DamageRepairStatus::activeValues())
            ->exists();

        if ($activeRepairExists) {
            $case->update([
                'status' => 'in_repair',
                'repaired_at' => null,
            ]);

            return;
        }

        $lastCompletedRepair = DamageRepair::query()
            ->where('car_damage_case_id', $case->id)
            ->where('status', DamageRepairStatus::COMPLETED->value)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->first();

        if ($lastCompletedRepair) {
            $case->update([
                'status' => 'repaired',
                'repaired_at' => $lastCompletedRepair->completed_at ?? CarbonImmutable::now(),
            ]);

            return;
        }

        $case->update([
            'status' => 'open',
            'repaired_at' => null,
        ]);
    }
}
