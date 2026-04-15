<?php

namespace App\Enums;

use Illuminate\Support\Collection;

enum CarColor: string
{
    case WHITE = 'white';
    case BLACK = 'black';
    case SILVER = 'silver';
    case GRAY = 'gray';
    case RED = 'red';
    case BLUE = 'blue';
    case GREEN = 'green';
    case YELLOW = 'yellow';
    case ORANGE = 'orange';
    case BROWN = 'brown';
    case BEIGE = 'beige';
    case CHAMPAGNE = 'champagne';
    case GOLD = 'gold';
    case BURGUNDY = 'burgundy';
    case PURPLE = 'purple';
    case PINK = 'pink';
    case CYAN = 'cyan';
    case BRONZE = 'bronze';
    case TEAL = 'teal';
    case OLIVE = 'olive';
    case MAROON = 'maroon';
    case INDIGO = 'indigo';
    case MAGENTA = 'magenta';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function toArray(): array
    {
        return [
            self::WHITE->value => [
                'name' => 'White',
                'hex' => '#F9FAFB',
            ],
            self::BLACK->value => [
                'name' => 'Black',
                'hex' => '#1F2937',
            ],
            self::SILVER->value => [
                'name' => 'Silver',
                'hex' => '#E5E7EB',
            ],
            self::GRAY->value => [
                'name' => 'Gray',
                'hex' => '#9CA3AF',
            ],
            self::RED->value => [
                'name' => 'Red',
                'hex' => '#FEE2E2',
            ],
            self::BLUE->value => [
                'name' => 'Blue',
                'hex' => '#DBEAFE',
            ],
            self::GREEN->value => [
                'name' => 'Green',
                'hex' => '#DCFCE7',
            ],
            self::YELLOW->value => [
                'name' => 'Yellow',
                'hex' => '#FEF9C3',
            ],
            self::ORANGE->value => [
                'name' => 'Orange',
                'hex' => '#FFEDD5',
            ],
            self::BROWN->value => [
                'name' => 'Brown',
                'hex' => '#F3E8D2',
            ],
            self::BEIGE->value => [
                'name' => 'Beige',
                'hex' => '#F5F5DC',
            ],
            self::CHAMPAGNE->value => [
                'name' => 'Champagne',
                'hex' => '#F7E7CE',
            ],
            self::GOLD->value => [
                'name' => 'Gold',
                'hex' => '#FFD700',
            ],
            self::BURGUNDY->value => [
                'name' => 'Burgundy',
                'hex' => '#800020',
            ],
            self::PURPLE->value => [
                'name' => 'Purple',
                'hex' => '#A855F7',
            ],
            self::PINK->value => [
                'name' => 'Pink',
                'hex' => '#FFC0CB',
            ],
            self::CYAN->value => [
                'name' => 'Cyan',
                'hex' => '#06B6D4',
            ],
            self::BRONZE->value => [
                'name' => 'Bronze',
                'hex' => '#CD7F32',
            ],
            self::TEAL->value => [
                'name' => 'Teal',
                'hex' => '#008080',
            ],
            self::OLIVE->value => [
                'name' => 'Olive',
                'hex' => '#808000',
            ],
            self::MAROON->value => [
                'name' => 'Maroon',
                'hex' => '#800000',
            ],
            self::INDIGO->value => [
                'name' => 'Indigo',
                'hex' => '#4B0082',
            ],
            self::MAGENTA->value => [
                'name' => 'Magenta',
                'hex' => '#FF00FF',
            ],
        ];
    }

    public static function forFrontend(): array
    {
        return array_map(
            fn ($color) => [
                'name' => $color['name'],
                'value' => $color['value'] ?? $color['name'],
                'hex' => $color['hex'],
            ],
            array_map(
                fn ($value, $key) => ['value' => $key] + $value,
                self::toArray(),
                array_keys(self::toArray())
            )
        );
    }
}
