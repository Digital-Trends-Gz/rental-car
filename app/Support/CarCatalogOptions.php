<?php

namespace App\Support;

use Illuminate\Support\Collection;

class CarCatalogOptions
{
    /**
     * @return array<int, array{value:string,label:string}>
     */
    public static function yearOptions(int $startYear = 1990): array
    {
        $currentYear = (int) now()->format('Y') + 1;

        return collect(range($currentYear, $startYear))
            ->map(fn (int $year) => [
                'value' => (string) $year,
                'label' => (string) $year,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{
     *     value:string,
     *     label:string,
     *     models: array<int, array{
     *         value:string,
     *         label:string,
     *         years: array<int, array{value:string,label:string}>
     *     }>
     * }>
     */
    public static function makeOptions(): array
    {
        return self::catalog()
            ->map(fn (array $make) => [
                'value' => (string) $make['make'],
                'label' => (string) $make['make'],
                'models' => collect($make['models'] ?? [])
                    ->map(function (array|string $model) use ($make) {
                        $modelName = is_array($model) ? (string) ($model['name'] ?? '') : (string) $model;
                        $years = is_array($model) && !empty($model['years'])
                            ? self::normalizeYearOptions($model['years'])
                            : self::modelYearOptions((string) $make['make'], $modelName);

                        return [
                            'value' => $modelName,
                            'label' => $modelName,
                            'years' => $years,
                        ];
                    })
                    ->filter(fn (array $model) => $model['value'] !== '')
                    ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int|string>  $years
     * @return array<int, array{value:string,label:string}>
     */
    protected static function normalizeYearOptions(array $years): array
    {
        return collect($years)
            ->map(fn (int|string $year) => (string) $year)
            ->filter()
            ->unique()
            ->sortDesc()
            ->values()
            ->map(fn (string $year) => [
                'value' => $year,
                'label' => $year,
            ])
            ->all();
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    protected static function modelYearOptions(string $make, string $model): array
    {
        $defaultStart = 2015;
        $rangeOverrides = self::yearRangeOverrides();
        $makeOverrides = $rangeOverrides[$make] ?? [];
        $startYear = $makeOverrides[$model] ?? $defaultStart;

        return self::yearOptions($startYear);
    }

    /**
     * @return array<string, array<string, int>>
     */
    protected static function yearRangeOverrides(): array
    {
        return [
            'Audi' => ['A8' => 2010, 'Q7' => 2010],
            'BMW' => ['5 Series' => 2010, '7 Series' => 2010, 'X5' => 2010, 'X7' => 2019],
            'Cadillac' => ['Escalade' => 2010],
            'Chevrolet' => ['Malibu' => 2010, 'Silverado' => 2010, 'Suburban' => 2010, 'Tahoe' => 2010],
            'Chrysler' => ['300C' => 2010, 'Pacifica' => 2017],
            'Dodge' => ['Challenger' => 2010, 'Charger' => 2010, 'Durango' => 2010],
            'Ford' => ['Explorer' => 2010, 'F-150' => 2010, 'Mustang' => 2010, 'Ranger' => 2012],
            'GMC' => ['Sierra' => 2010, 'Yukon' => 2010],
            'Honda' => ['Accord' => 2010, 'Civic' => 2010, 'CR-V' => 2010, 'Odyssey' => 2010, 'Pilot' => 2010],
            'Hyundai' => ['Accent' => 2010, 'Elantra' => 2010, 'Sonata' => 2010],
            'Infiniti' => ['Q50' => 2014, 'QX80' => 2014],
            'Isuzu' => ['D-Max' => 2012],
            'Jaguar' => ['F-Type' => 2014, 'XF' => 2010],
            'Jeep' => ['Grand Cherokee' => 2010, 'Wrangler' => 2010],
            'Kia' => ['Carnival' => 2015, 'Cerato' => 2010, 'Rio' => 2010, 'Sorento' => 2010, 'Sportage' => 2010],
            'Land Rover' => ['Discovery' => 2010, 'Range Rover' => 2010, 'Range Rover Sport' => 2010],
            'Lexus' => ['ES' => 2010, 'GX' => 2010, 'IS' => 2010, 'LX' => 2010, 'RX' => 2010],
            'Mazda' => ['Mazda 3' => 2010, 'Mazda 6' => 2010, 'CX-5' => 2012, 'CX-9' => 2010],
            'Mercedes-Benz' => ['C-Class' => 2010, 'E-Class' => 2010, 'G-Class' => 2010, 'S-Class' => 2010],
            'Mini' => ['Hatch' => 2010, 'Countryman' => 2011],
            'Mitsubishi' => ['L200' => 2010, 'Outlander' => 2010],
            'Nissan' => ['Altima' => 2010, 'Maxima' => 2010, 'Patrol' => 2010, 'Pathfinder' => 2010, 'Sunny' => 2010, 'X-Trail' => 2010],
            'Peugeot' => ['301' => 2013, '508' => 2011],
            'Porsche' => ['911' => 2010, 'Cayenne' => 2010, 'Panamera' => 2010],
            'Renault' => ['Duster' => 2012, 'Symbol' => 2010],
            'Rolls-Royce' => ['Ghost' => 2010, 'Phantom' => 2010],
            'Skoda' => ['Octavia' => 2010, 'Superb' => 2010],
            'Suzuki' => ['Swift' => 2010, 'Vitara' => 2015],
            'Tesla' => ['Model S' => 2012, 'Model X' => 2015, 'Model 3' => 2017, 'Model Y' => 2020],
            'Toyota' => ['Camry' => 2010, 'Corolla' => 2010, 'Fortuner' => 2010, 'Hilux' => 2010, 'Land Cruiser' => 2010, 'Prado' => 2010, 'RAV4' => 2010, 'Yaris' => 2010],
            'Volkswagen' => ['Golf' => 2010, 'Passat' => 2010, 'Polo' => 2010, 'Touareg' => 2010],
            'Volvo' => ['S60' => 2010, 'S90' => 2017, 'XC60' => 2010, 'XC90' => 2010],
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{make:string,models:array<int, string|array{name:string,years:array<int,int|string>}>}>
     */
    protected static function catalog(): Collection
    {
        /** @var array<int, array{make:string,models:array<int, string|array{name:string,years:array<int,int|string>}>}> $catalog */
        $catalog = json_decode(
            file_get_contents(resource_path('data/car-catalog.json')) ?: '[]',
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        return collect($catalog)
            ->filter(fn (array $make) => !empty($make['make']) && !empty($make['models']))
            ->sortBy('make', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }
}
