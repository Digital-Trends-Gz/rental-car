<?php

namespace App\Services\Rentals;

use App\Enums\CarStatus;
use App\Enums\ReservationStatus;
use App\Models\Car;
use App\Models\Reservation;

class RentalStatusSyncService
{
    /**
     * @return array{activated:int,completed:int,cars_updated:int,checked_at:string,dry_run:bool,reserve_before_hours:int}
     */
    public function run(bool $dryRun = false): array
    {
        $now = now();
        $reserveBeforeHours = $this->reserveBeforeHours();

        $toComplete = Reservation::withoutGlobalScope('tenant')
            ->where('status', ReservationStatus::ACTIVE->value)
            ->whereRaw("TIMESTAMP(end_date, COALESCE(return_time, '18:00:00')) < ?", [$now->toDateTimeString()])
            ->get(['id', 'car_id']);

        $activated = 0;
        $completed = $toComplete->count();
        $carIds = $toComplete->pluck('car_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (!$dryRun) {
            if ($completed > 0) {
                Reservation::withoutGlobalScope('tenant')
                    ->whereIn('id', $toComplete->pluck('id')->all())
                    ->update([
                        'status' => ReservationStatus::COMPLETED->value,
                        'updated_at' => $now,
                    ]);
            }
        }

        $carsUpdated = $this->syncCarsByIds($carIds, $dryRun, $reserveBeforeHours);

        return [
            'activated' => $activated,
            'completed' => $completed,
            'cars_updated' => $carsUpdated,
            'checked_at' => $now->toDateTimeString(),
            'dry_run' => $dryRun,
            'reserve_before_hours' => $reserveBeforeHours,
        ];
    }

    /**
     * @param  array<int, int|string|null>  $carIds
     */
    public function syncCarsByIds(array $carIds, bool $dryRun = false, ?int $reserveBeforeHours = null): int
    {
        $carIds = collect($carIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($carIds === []) {
            return 0;
        }

        $reserveBeforeHours = $reserveBeforeHours ?? $this->reserveBeforeHours();
        $updated = 0;

        Car::withoutGlobalScope('tenant')
            ->whereIn('id', $carIds)
            ->get(['id', 'status'])
            ->each(function (Car $car) use (&$updated, $dryRun, $reserveBeforeHours) {
                $currentStatus = $car->status instanceof CarStatus
                    ? $car->status
                    : CarStatus::tryFrom((string) $car->status);

                // Do not override statuses controlled by operations modules.
                if (!$currentStatus || !in_array($currentStatus, [CarStatus::AVAILABLE, CarStatus::RESERVED, CarStatus::RENTED], true)) {
                    return;
                }

                $targetStatus = $this->targetStatusForCar((int) $car->id, $reserveBeforeHours);
                if ($targetStatus === $currentStatus) {
                    return;
                }

                $updated++;
                if ($dryRun) {
                    return;
                }

                $car->forceFill(['status' => $targetStatus->value])->saveQuietly();
            });

        return $updated;
    }

    public function targetStatusForCar(int $carId, ?int $reserveBeforeHours = null): CarStatus
    {
        $now = now();
        $reserveBeforeHours = $reserveBeforeHours ?? $this->reserveBeforeHours();
        $reserveUntil = $now->copy()->addHours(max(0, $reserveBeforeHours));

        $hasActive = Reservation::withoutGlobalScope('tenant')
            ->where('car_id', $carId)
            ->where('status', ReservationStatus::ACTIVE->value)
            ->exists();

        if ($hasActive) {
            return CarStatus::RENTED;
        }

        $hasPendingOrConfirmedSoon = Reservation::withoutGlobalScope('tenant')
            ->where('car_id', $carId)
            ->whereIn('status', [
                ReservationStatus::AWAITING_PAYMENT->value,
                ReservationStatus::PENDING->value,
                ReservationStatus::CONFIRMED->value,
            ])
            ->whereRaw("TIMESTAMP(start_date, COALESCE(pickup_time, '09:00:00')) <= ?", [$reserveUntil->toDateTimeString()])
            ->exists();

        if ($hasPendingOrConfirmedSoon) {
            return CarStatus::RESERVED;
        }

        return CarStatus::AVAILABLE;
    }

    private function reserveBeforeHours(): int
    {
        return max(0, (int) config('rentals.reserve_before_hours', 24));
    }
}
