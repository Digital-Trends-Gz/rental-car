<?php

namespace App\Services\Cars;

use App\Enums\UserRole;
use App\Models\CarDocument;
use App\Models\User;
use App\Notifications\CarDocumentExpiryNotification;
use Carbon\CarbonImmutable;

class CarDocumentReminderService
{
    private const REMINDER_DAYS = [10, 5, 3, 1, 0];

    /**
     * @return array{checked:int,notified:int}
     */
    public function run(): array
    {
        $today = CarbonImmutable::today();

        $documents = CarDocument::query()
            ->withoutGlobalScope('tenant')
            ->with('car:id,tenant_id,year,make,model,license_plate')
            ->where('is_active', true)
            ->whereDate('expiry_date', '>=', $today->toDateString())
            ->whereDate('expiry_date', '<=', $today->addDays(10)->toDateString())
            ->get();

        $checked = $documents->count();
        $notified = 0;

        foreach ($documents as $document) {
            $daysRemaining = $document->expiry_date
                ? (int) (CarbonImmutable::parse($document->expiry_date)->startOfDay()->diffInDays($today, false) * -1)
                : null;

            if ($daysRemaining === null || !in_array($daysRemaining, self::REMINDER_DAYS, true)) {
                continue;
            }

            $reminderColumn = CarDocument::reminderColumnForDays($daysRemaining);
            if ($reminderColumn === null || $document->{$reminderColumn} !== null) {
                continue;
            }

            $admins = User::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $document->tenant_id)
                ->where('role', UserRole::ADMIN)
                ->where('is_active', true)
                ->get();

            if ($admins->isEmpty()) {
                continue;
            }

            foreach ($admins as $admin) {
                $admin->notify(new CarDocumentExpiryNotification($document, $daysRemaining));
                $notified++;
            }

            $document->forceFill([
                $reminderColumn => now(),
            ])->save();
        }

        return [
            'checked' => $checked,
            'notified' => $notified,
        ];
    }
}
