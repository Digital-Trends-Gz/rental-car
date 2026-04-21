<?php

namespace App\Support;

final class CompanyOwners
{
    public static function blankRow(): array
    {
        return [
            'name' => '',
            'commercial_registration_number' => '',
            'tax_number' => '',
            'civil_number' => '',
        ];
    }

    public static function normalize(mixed $value): array
    {
        $rows = is_array($value) ? $value : [];
        $owners = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $owner = [
                'name' => trim((string) ($row['name'] ?? '')),
                'commercial_registration_number' => trim((string) ($row['commercial_registration_number'] ?? '')),
                'tax_number' => trim((string) ($row['tax_number'] ?? '')),
                'civil_number' => trim((string) ($row['civil_number'] ?? '')),
            ];

            if ($owner['name'] === ''
                && $owner['commercial_registration_number'] === ''
                && $owner['tax_number'] === ''
                && $owner['civil_number'] === '') {
                continue;
            }

            $owners[] = $owner;
        }

        return $owners;
    }
}
