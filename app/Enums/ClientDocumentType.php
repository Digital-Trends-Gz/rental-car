<?php

namespace App\Enums;

enum ClientDocumentType: string
{
    case DRIVER_LICENSE_FRONT = 'driver_license_front';
    case DRIVER_LICENSE_BACK = 'driver_license_back';
    case ID_CARD_FRONT = 'id_card_front';
    case ID_CARD_BACK = 'id_card_back';
    case PASSPORT = 'passport';

    public function label(): string
    {
        return match ($this) {
            self::DRIVER_LICENSE_FRONT => __('site.dashboard.admin.clients.documents.types.driver_license_front.label'),
            self::DRIVER_LICENSE_BACK => __('site.dashboard.admin.clients.documents.types.driver_license_back.label'),
            self::ID_CARD_FRONT => __('site.dashboard.admin.clients.documents.types.id_card_front.label'),
            self::ID_CARD_BACK => __('site.dashboard.admin.clients.documents.types.id_card_back.label'),
            self::PASSPORT => __('site.dashboard.admin.clients.documents.types.passport.label'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::DRIVER_LICENSE_FRONT => __('site.dashboard.admin.clients.documents.types.driver_license_front.description'),
            self::DRIVER_LICENSE_BACK => __('site.dashboard.admin.clients.documents.types.driver_license_back.description'),
            self::ID_CARD_FRONT => __('site.dashboard.admin.clients.documents.types.id_card_front.description'),
            self::ID_CARD_BACK => __('site.dashboard.admin.clients.documents.types.id_card_back.description'),
            self::PASSPORT => __('site.dashboard.admin.clients.documents.types.passport.description'),
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }

    /**
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(static fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'description' => $case->description(),
        ], self::cases());
    }
}
