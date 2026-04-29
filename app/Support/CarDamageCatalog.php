<?php

namespace App\Support;

class CarDamageCatalog
{
    public static function reportTypes(): array
    {
        return [
            ['value' => 'before_delivery', 'label' => __('contracts.damage_catalog.report_types.before_delivery')],
            ['value' => 'after_return', 'label' => __('contracts.damage_catalog.report_types.after_return')],
            ['value' => 'periodic_inspection', 'label' => __('contracts.damage_catalog.report_types.periodic_inspection')],
            ['value' => 'maintenance_check', 'label' => __('contracts.damage_catalog.report_types.maintenance_check')],
            ['value' => 'custom', 'label' => __('contracts.damage_catalog.report_types.custom')],
        ];
    }

    public static function statuses(): array
    {
        return [
            ['value' => 'draft', 'label' => __('contracts.damage_catalog.statuses.draft')],
            ['value' => 'finalized', 'label' => __('contracts.damage_catalog.statuses.finalized')],
        ];
    }

    public static function damageTypes(): array
    {
        return [
            ['value' => 'scratch', 'label' => __('contracts.damage_catalog.damage_types.scratch')],
            ['value' => 'dent', 'label' => __('contracts.damage_catalog.damage_types.dent')],
            ['value' => 'crack', 'label' => __('contracts.damage_catalog.damage_types.crack')],
            ['value' => 'paint', 'label' => __('contracts.damage_catalog.damage_types.paint')],
            ['value' => 'broken', 'label' => __('contracts.damage_catalog.damage_types.broken')],
            ['value' => 'missing', 'label' => __('contracts.damage_catalog.damage_types.missing')],
            ['value' => 'other', 'label' => __('contracts.damage_catalog.damage_types.other')],
        ];
    }

    public static function severityLevels(): array
    {
        return [
            ['value' => 'minor', 'label' => __('contracts.damage_catalog.severity_levels.minor')],
            ['value' => 'moderate', 'label' => __('contracts.damage_catalog.severity_levels.moderate')],
            ['value' => 'major', 'label' => __('contracts.damage_catalog.severity_levels.major')],
        ];
    }

    public static function damageTimings(): array
    {
        return [
            ['value' => 'before_pickup', 'label' => __('contracts.damage_catalog.damage_timings.before_pickup')],
            ['value' => 'after_return', 'label' => __('contracts.damage_catalog.damage_timings.after_return')],
        ];
    }

    public static function viewSides(): array
    {
        return [
            ['value' => 'front', 'label' => __('contracts.damage_catalog.view_sides.front')],
            ['value' => 'rear', 'label' => __('contracts.damage_catalog.view_sides.rear')],
            ['value' => 'left', 'label' => __('contracts.damage_catalog.view_sides.left')],
            ['value' => 'right', 'label' => __('contracts.damage_catalog.view_sides.right')],
            ['value' => 'top', 'label' => __('contracts.damage_catalog.view_sides.top')],
        ];
    }

    public static function zoneDefinitions(): array
    {
        return [
            ['code' => 'front_bumper', 'label' => __('contracts.damage_catalog.zones.front_bumper')],
            ['code' => 'hood', 'label' => __('contracts.damage_catalog.zones.hood')],
            ['code' => 'windshield', 'label' => __('contracts.damage_catalog.zones.windshield')],
            ['code' => 'roof', 'label' => __('contracts.damage_catalog.zones.roof')],
            ['code' => 'rear_glass', 'label' => __('contracts.damage_catalog.zones.rear_glass')],
            ['code' => 'trunk', 'label' => __('contracts.damage_catalog.zones.trunk')],
            ['code' => 'rear_bumper', 'label' => __('contracts.damage_catalog.zones.rear_bumper')],
            ['code' => 'left_headlight', 'label' => __('contracts.damage_catalog.zones.left_headlight')],
            ['code' => 'right_headlight', 'label' => __('contracts.damage_catalog.zones.right_headlight')],
            ['code' => 'left_taillight', 'label' => __('contracts.damage_catalog.zones.left_taillight')],
            ['code' => 'right_taillight', 'label' => __('contracts.damage_catalog.zones.right_taillight')],
            ['code' => 'left_front_fender', 'label' => __('contracts.damage_catalog.zones.left_front_fender')],
            ['code' => 'left_front_door', 'label' => __('contracts.damage_catalog.zones.left_front_door')],
            ['code' => 'left_rear_door', 'label' => __('contracts.damage_catalog.zones.left_rear_door')],
            ['code' => 'left_rear_quarter', 'label' => __('contracts.damage_catalog.zones.left_rear_quarter')],
            ['code' => 'left_mirror', 'label' => __('contracts.damage_catalog.zones.left_mirror')],
            ['code' => 'right_front_fender', 'label' => __('contracts.damage_catalog.zones.right_front_fender')],
            ['code' => 'right_front_door', 'label' => __('contracts.damage_catalog.zones.right_front_door')],
            ['code' => 'right_rear_door', 'label' => __('contracts.damage_catalog.zones.right_rear_door')],
            ['code' => 'right_rear_quarter', 'label' => __('contracts.damage_catalog.zones.right_rear_quarter')],
            ['code' => 'right_mirror', 'label' => __('contracts.damage_catalog.zones.right_mirror')],
        ];
    }

    public static function zoneViews(): array
    {
        $zoneLabels = static::zoneLabelMap();

        return array_map(static function (array $zone) use ($zoneLabels): array {
            $zone['label'] = $zoneLabels[$zone['code']] ?? $zone['code'];

            return $zone;
        }, [
            ['code' => 'front_bumper', 'view_side' => 'front', 'x' => 86, 'y' => 118, 'width' => 148, 'height' => 28],
            ['code' => 'hood', 'view_side' => 'front', 'x' => 108, 'y' => 54, 'width' => 104, 'height' => 44],
            ['code' => 'windshield', 'view_side' => 'front', 'x' => 116, 'y' => 22, 'width' => 88, 'height' => 30],
            ['code' => 'left_headlight', 'view_side' => 'front', 'x' => 72, 'y' => 92, 'width' => 30, 'height' => 18],
            ['code' => 'right_headlight', 'view_side' => 'front', 'x' => 218, 'y' => 92, 'width' => 30, 'height' => 18],
            ['code' => 'left_mirror', 'view_side' => 'front', 'x' => 62, 'y' => 44, 'width' => 18, 'height' => 16],
            ['code' => 'right_mirror', 'view_side' => 'front', 'x' => 240, 'y' => 44, 'width' => 18, 'height' => 16],
            ['code' => 'rear_bumper', 'view_side' => 'rear', 'x' => 90, 'y' => 118, 'width' => 140, 'height' => 28],
            ['code' => 'trunk', 'view_side' => 'rear', 'x' => 98, 'y' => 56, 'width' => 124, 'height' => 42],
            ['code' => 'rear_glass', 'view_side' => 'rear', 'x' => 108, 'y' => 20, 'width' => 104, 'height' => 26],
            ['code' => 'left_taillight', 'view_side' => 'rear', 'x' => 72, 'y' => 92, 'width' => 30, 'height' => 18],
            ['code' => 'right_taillight', 'view_side' => 'rear', 'x' => 218, 'y' => 92, 'width' => 30, 'height' => 18],
            ['code' => 'left_mirror', 'view_side' => 'rear', 'x' => 60, 'y' => 46, 'width' => 18, 'height' => 16],
            ['code' => 'right_mirror', 'view_side' => 'rear', 'x' => 242, 'y' => 46, 'width' => 18, 'height' => 16],
            ['code' => 'left_front_fender', 'view_side' => 'left', 'x' => 46, 'y' => 86, 'width' => 34, 'height' => 42],
            ['code' => 'left_front_door', 'view_side' => 'left', 'x' => 84, 'y' => 66, 'width' => 56, 'height' => 66],
            ['code' => 'left_rear_door', 'view_side' => 'left', 'x' => 144, 'y' => 66, 'width' => 56, 'height' => 66],
            ['code' => 'left_rear_quarter', 'view_side' => 'left', 'x' => 204, 'y' => 86, 'width' => 34, 'height' => 42],
            ['code' => 'front_bumper', 'view_side' => 'left', 'x' => 14, 'y' => 98, 'width' => 28, 'height' => 24],
            ['code' => 'rear_bumper', 'view_side' => 'left', 'x' => 242, 'y' => 98, 'width' => 28, 'height' => 24],
            ['code' => 'left_mirror', 'view_side' => 'left', 'x' => 110, 'y' => 46, 'width' => 16, 'height' => 14],
            ['code' => 'hood', 'view_side' => 'left', 'x' => 44, 'y' => 62, 'width' => 40, 'height' => 18],
            ['code' => 'trunk', 'view_side' => 'left', 'x' => 200, 'y' => 62, 'width' => 40, 'height' => 18],
            ['code' => 'right_front_fender', 'view_side' => 'right', 'x' => 238, 'y' => 82, 'width' => 36, 'height' => 40],
            ['code' => 'right_front_door', 'view_side' => 'right', 'x' => 182, 'y' => 64, 'width' => 54, 'height' => 60],
            ['code' => 'right_rear_door', 'view_side' => 'right', 'x' => 122, 'y' => 64, 'width' => 54, 'height' => 60],
            ['code' => 'right_rear_quarter', 'view_side' => 'right', 'x' => 84, 'y' => 84, 'width' => 34, 'height' => 40],
            ['code' => 'front_bumper', 'view_side' => 'right', 'x' => 278, 'y' => 100, 'width' => 28, 'height' => 22],
            ['code' => 'rear_bumper', 'view_side' => 'right', 'x' => 48, 'y' => 100, 'width' => 28, 'height' => 22],
            ['code' => 'right_mirror', 'view_side' => 'right', 'x' => 186, 'y' => 42, 'width' => 16, 'height' => 14],
            ['code' => 'hood', 'view_side' => 'right', 'x' => 232, 'y' => 56, 'width' => 44, 'height' => 18],
            ['code' => 'trunk', 'view_side' => 'right', 'x' => 78, 'y' => 56, 'width' => 44, 'height' => 18],
            ['code' => 'hood', 'view_side' => 'top', 'x' => 108, 'y' => 10, 'width' => 104, 'height' => 24],
            ['code' => 'windshield', 'view_side' => 'top', 'x' => 112, 'y' => 36, 'width' => 96, 'height' => 18],
            ['code' => 'roof', 'view_side' => 'top', 'x' => 112, 'y' => 56, 'width' => 96, 'height' => 44],
            ['code' => 'rear_glass', 'view_side' => 'top', 'x' => 112, 'y' => 102, 'width' => 96, 'height' => 18],
            ['code' => 'trunk', 'view_side' => 'top', 'x' => 108, 'y' => 122, 'width' => 104, 'height' => 24],
            ['code' => 'left_front_door', 'view_side' => 'top', 'x' => 82, 'y' => 44, 'width' => 24, 'height' => 34],
            ['code' => 'left_rear_door', 'view_side' => 'top', 'x' => 82, 'y' => 80, 'width' => 24, 'height' => 34],
            ['code' => 'right_front_door', 'view_side' => 'top', 'x' => 214, 'y' => 44, 'width' => 24, 'height' => 34],
            ['code' => 'right_rear_door', 'view_side' => 'top', 'x' => 214, 'y' => 80, 'width' => 24, 'height' => 34],
        ]);
    }

    public static function zoneCodes(): array
    {
        return array_values(array_map(
            static fn (array $zone): string => $zone['code'],
            static::zoneDefinitions()
        ));
    }

    public static function zoneLabelMap(): array
    {
        $map = [];

        foreach (static::zoneDefinitions() as $zone) {
            $map[$zone['code']] = $zone['label'];
        }

        return $map;
    }
}
