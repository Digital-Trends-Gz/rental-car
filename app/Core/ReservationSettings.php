<?php

namespace App\Core;

use App\Models\SiteSetting;

class ReservationSettings
{
    public const KEY = 'reservation_settings';

    public static function defaults(): array
    {
        return [
            'return_time_policy' => [
                'mode' => 'fixed_time',
                'fixed_time' => '18:00',
            ],
            'pickup_return_locations' => [],
            'kilometer_pricing' => [],
            'fuel_pricing' => [],
            'late_return' => [
                'mode' => 'hourly',
                'hourly_fee' => 0,
                'after_hours' => 0,
            ],
            'cleaning_fee' => 0,
        ];
    }

    public static function load(): array
    {
        $stored = SiteSetting::query()
            ->where('key', self::KEY)
            ->value('value');

        return self::normalize(is_array($stored) ? $stored : null);
    }

    public static function normalize(?array $data): array
    {
        $settings = array_replace_recursive(self::defaults(), is_array($data) ? $data : []);

        $settings['return_time_policy'] = self::normalizeReturnTimePolicy($settings['return_time_policy'] ?? []);
        $settings['pickup_return_locations'] = self::normalizePickupReturnLocations($settings['pickup_return_locations'] ?? []);
        $settings['kilometer_pricing'] = self::normalizeKilometerPricing($settings['kilometer_pricing'] ?? []);
        $settings['fuel_pricing'] = self::normalizeFuelPricing($settings['fuel_pricing'] ?? []);
        $settings['late_return'] = self::normalizeLateReturn($settings['late_return'] ?? []);
        $settings['cleaning_fee'] = self::normalizeMoney($settings['cleaning_fee'] ?? 0);

        return $settings;
    }

    public static function activePickupReturnLocations(array $settings): array
    {
        $locations = $settings['pickup_return_locations'] ?? [];

        return array_values(array_filter(
            is_array($locations) ? $locations : [],
            static fn ($location) => is_array($location) && ($location['is_active'] ?? true)
        ));
    }

    public static function findPickupReturnLocation(array $settings, ?string $name): ?array
    {
        $needle = trim((string) ($name ?? ''));
        if ($needle === '') {
            return null;
        }

        foreach (self::activePickupReturnLocations($settings) as $location) {
            $locationName = trim((string) ($location['name'] ?? ''));
            if ($locationName !== '' && strcasecmp($locationName, $needle) === 0) {
                return $location;
            }
        }

        return null;
    }

    public static function resolveLocationFee(array $settings, ?string $name, string $type = 'return'): float
    {
        $location = self::findPickupReturnLocation($settings, $name);
        if (!$location) {
            return 0.0;
        }

        $feeKey = $type === 'pickup' ? 'pickup_fee' : 'return_fee';
        $freeKey = $type === 'pickup' ? 'pickup_free' : 'return_free';

        if (!empty($location[$freeKey])) {
            return 0.0;
        }

        return self::normalizeMoney($location[$feeKey] ?? 0);
    }

    public static function resolveKilometerRate(array $settings, ?float $kilometers): float
    {
        $kilometers = max(0.0, (float) ($kilometers ?? 0));
        $tiers = $settings['kilometer_pricing'] ?? [];
        if (!is_array($tiers) || $kilometers <= 0) {
            return 0.0;
        }

        $tiers = array_values(array_filter($tiers, static fn ($tier) => is_array($tier)));
        usort($tiers, static function (array $a, array $b): int {
            $aFrom = isset($a['from_km']) && $a['from_km'] !== '' ? (int) $a['from_km'] : PHP_INT_MAX;
            $bFrom = isset($b['from_km']) && $b['from_km'] !== '' ? (int) $b['from_km'] : PHP_INT_MAX;

            return $aFrom <=> $bFrom;
        });

        foreach ($tiers as $tier) {
            $from = isset($tier['from_km']) && $tier['from_km'] !== '' ? (int) $tier['from_km'] : null;
            $to = isset($tier['to_km']) && $tier['to_km'] !== '' ? (int) $tier['to_km'] : null;
            $price = self::normalizeMoney($tier['price'] ?? 0);

            if ($price <= 0) {
                continue;
            }

            if (($from === null || $kilometers >= $from) && ($to === null || $kilometers <= $to)) {
                return $price;
            }
        }

        $fallback = $tiers[count($tiers) - 1] ?? null;
        return is_array($fallback) ? self::normalizeMoney($fallback['price'] ?? 0) : 0.0;
    }

    public static function resolveFuelFee(array $settings, ?string $fuelLevel): float
    {
        $fuelLevel = trim((string) ($fuelLevel ?? ''));
        if ($fuelLevel === '') {
            return 0.0;
        }

        $rules = $settings['fuel_pricing'] ?? [];
        if (!is_array($rules)) {
            return 0.0;
        }

        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            if (strcasecmp((string) ($rule['fuel_level'] ?? ''), $fuelLevel) === 0) {
                return self::normalizeMoney($rule['price'] ?? 0);
            }
        }

        return 0.0;
    }

    public static function resolveFuelFeeByLoss(array $settings, ?string $startFuelLevel, ?string $returnFuelLevel): float
    {
        $lossLevel = self::resolveFuelLossLevel($startFuelLevel, $returnFuelLevel);
        if ($lossLevel === null) {
            return 0.0;
        }

        return self::resolveFuelFee($settings, $lossLevel);
    }

    public static function resolveFuelCreditByGain(array $settings, ?string $startFuelLevel, ?string $returnFuelLevel): float
    {
        $gainLevel = self::resolveFuelGainLevel($startFuelLevel, $returnFuelLevel);
        if ($gainLevel === null) {
            return 0.0;
        }

        return self::resolveFuelFee($settings, $gainLevel);
    }

    public static function resolveFuelLossLevel(?string $startFuelLevel, ?string $returnFuelLevel): ?string
    {
        $start = self::fuelFraction($startFuelLevel);
        $return = self::fuelFraction($returnFuelLevel);

        if ($start === null || $return === null) {
            return null;
        }

        $loss = max(0.0, $start - $return);
        if ($loss <= 0) {
            return null;
        }

        return self::fractionToFuelLevel($loss);
    }

    public static function resolveFuelGainLevel(?string $startFuelLevel, ?string $returnFuelLevel): ?string
    {
        $start = self::fuelFraction($startFuelLevel);
        $return = self::fuelFraction($returnFuelLevel);

        if ($start === null || $return === null) {
            return null;
        }

        $gain = max(0.0, $return - $start);
        if ($gain <= 0) {
            return null;
        }

        return self::fractionToFuelLevel($gain);
    }

    public static function resolveCleaningFee(array $settings): float
    {
        return self::normalizeMoney($settings['cleaning_fee'] ?? 0);
    }

    public static function resolveLateHourlyFee(array $settings): float
    {
        $lateReturn = $settings['late_return'] ?? [];

        return self::normalizeMoney(is_array($lateReturn) ? ($lateReturn['hourly_fee'] ?? 0) : 0);
    }

    private static function normalizeReturnTimePolicy(mixed $value): array
    {
        $data = is_array($value) ? $value : [];
        $mode = (string) ($data['mode'] ?? 'fixed_time');
        if ($mode === 'fixed_fee') {
            $mode = 'fixed_time';
        }

        if (!in_array($mode, ['fixed_time', 'same_pickup', 'set_during_reservation'], true)) {
            $mode = 'fixed_time';
        }

        return [
            'mode' => $mode,
            'fixed_time' => self::normalizeTime(
                $data['fixed_time'] ?? ($data['fixed_fee'] ?? '18:00')
            ),
        ];
    }

    private static function normalizeTime(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return '18:00';
        }

        if (preg_match('/^(\d{1,2})(?::(\d{1,2}))?$/', $value, $matches)) {
            $hour = max(0, min(23, (int) $matches[1]));
            $minute = isset($matches[2]) ? max(0, min(59, (int) $matches[2])) : 0;

            return sprintf('%02d:%02d', $hour, $minute);
        }

        if (preg_match('/^(\d{1,2}):(\d{2})$/', $value, $matches)) {
            $hour = max(0, min(23, (int) $matches[1]));
            $minute = max(0, min(59, (int) $matches[2]));

            return sprintf('%02d:%02d', $hour, $minute);
        }

        return '18:00';
    }

    private static function normalizePickupReturnLocations(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $locations = [];

        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? ''));
            $pickupFee = self::normalizeMoney($item['pickup_fee'] ?? 0);
            $returnFee = self::normalizeMoney($item['return_fee'] ?? 0);
            $pickupFree = (bool) ($item['pickup_free'] ?? false);
            $returnFree = (bool) ($item['return_free'] ?? false);
            $isActive = array_key_exists('is_active', $item) ? (bool) $item['is_active'] : true;

            if ($name === '' && $pickupFee === 0.0 && $returnFee === 0.0 && !$pickupFree && !$returnFree) {
                continue;
            }

            $locations[] = [
                'name' => $name,
                'pickup_fee' => $pickupFee,
                'return_fee' => $returnFee,
                'pickup_free' => $pickupFree,
                'return_free' => $returnFree,
                'is_active' => $isActive,
            ];
        }

        return $locations;
    }

    private static function normalizeKilometerPricing(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $tiers = [];

        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }

            $from = isset($item['from_km']) && $item['from_km'] !== '' ? (int) $item['from_km'] : null;
            $to = isset($item['to_km']) && $item['to_km'] !== '' ? (int) $item['to_km'] : null;
            $price = self::normalizeMoney($item['price'] ?? 0);

            if ($from === null && $to === null && $price === 0.0) {
                continue;
            }

            if ($from !== null && $to !== null && $from > $to) {
                [$from, $to] = [$to, $from];
            }

            $tiers[] = [
                'from_km' => $from,
                'to_km' => $to,
                'price' => $price,
            ];
        }

        return $tiers;
    }

    private static function normalizeFuelPricing(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $allowedLevels = ['empty', 'quarter', 'half', 'three_quarters', 'full'];
        $rules = [];

        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }

            $level = trim((string) ($item['fuel_level'] ?? ''));
            $price = self::normalizeMoney($item['price'] ?? 0);

            if ($level === '' && $price === 0.0) {
                continue;
            }

            if (!in_array($level, $allowedLevels, true)) {
                continue;
            }

            $rules[] = [
                'fuel_level' => $level,
                'price' => $price,
            ];
        }

        return $rules;
    }

    private static function normalizeLateReturn(mixed $value): array
    {
        $data = is_array($value) ? $value : [];
        $mode = (string) ($data['mode'] ?? 'hourly');
        if (!in_array($mode, ['hourly', 'daily_after_threshold'], true)) {
            $mode = 'hourly';
        }

        return [
            'mode' => $mode,
            'hourly_fee' => self::normalizeMoney($data['hourly_fee'] ?? 0),
            'after_hours' => isset($data['after_hours']) && $data['after_hours'] !== ''
                ? max(0, (int) $data['after_hours'])
                : 0,
        ];
    }

    private static function normalizeMoney(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return round((float) $value, 2);
    }

    private static function fuelFraction(?string $level): ?float
    {
        $level = trim((string) ($level ?? ''));
        if ($level === '') {
            return null;
        }

        return match ($level) {
            'empty' => 0.0,
            'quarter' => 0.25,
            'half' => 0.5,
            'three_quarters' => 0.75,
            'full' => 1.0,
            default => null,
        };
    }

    private static function fractionToFuelLevel(float $fraction): ?string
    {
        $quarterSteps = (int) round(max(0.0, min(1.0, $fraction)) / 0.25);

        return match ($quarterSteps) {
            0 => null,
            1 => 'quarter',
            2 => 'half',
            3 => 'three_quarters',
            4 => 'full',
            default => null,
        };
    }
}
