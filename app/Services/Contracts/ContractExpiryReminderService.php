<?php

namespace App\Services\Contracts;

use App\Enums\UserRole;
use App\Models\Contract;
use App\Models\User;
use App\Notifications\ContractEndingTomorrowNotification;
use Carbon\CarbonImmutable;

class ContractExpiryReminderService
{
    private const KIND = 'contract_ending_tomorrow';

    /**
     * @return array{checked:int,notified:int}
     */
    public function run(): array
    {
        $tomorrow = CarbonImmutable::tomorrow()->toDateString();

        $contracts = Contract::query()
            ->withoutGlobalScope('tenant')
            ->with([
                'reservation:id,reservation_number,user_id,car_id',
                'reservation.user:id,name,email',
                'reservation.car:id,year,make,model,license_plate',
            ])
            ->where('status', 'active')
            ->whereNotNull('reservation_id')
            ->whereDate('end_date', $tomorrow)
            ->get();

        $checked = $contracts->count();
        $notified = 0;

        foreach ($contracts as $contract) {
            $admins = User::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $contract->tenant_id)
                ->where('role', UserRole::ADMIN)
                ->where('is_active', true)
                ->get();

            if ($admins->isEmpty()) {
                continue;
            }

            foreach ($admins as $admin) {
                if ($this->alreadySentToday($admin, $contract->id)) {
                    continue;
                }

                $admin->notify(new ContractEndingTomorrowNotification($contract));
                $notified++;
            }
        }

        return [
            'checked' => $checked,
            'notified' => $notified,
        ];
    }

    private function alreadySentToday(User $user, int $contractId): bool
    {
        return $user->notifications()
            ->where('type', ContractEndingTomorrowNotification::class)
            ->where('data->kind', self::KIND)
            ->where('data->contract_id', $contractId)
            ->whereDate('created_at', today())
            ->exists();
    }
}
