<?php

namespace App\Support;

class TenantPdfTemplateRegistry
{
    public const DEFAULT_CONTRACT_TEMPLATE = 'classic';

    /**
     * @return array<string, array{
     *     value:string,
     *     label:array{en:string,ar:string},
     *     description:array{en:string,ar:string},
     *     view:string
     * }>
     */
    public static function contractTemplates(): array
    {
        return [
            'classic' => [
                'value' => 'classic',
                'label' => [
                    'en' => 'Classic Contract',
                    'ar' => 'العقد الكلاسيكي',
                ],
                'description' => [
                    'en' => 'The current bilingual contract layout with the traditional table design.',
                    'ar' => 'التصميم الحالي ثنائي اللغة مع الجداول التقليدية المعتمدة في العقود الحالية.',
                ],
                'view' => 'admin.contracts.pdf',
            ],
            'modern' => [
                'value' => 'modern',
                'label' => [
                    'en' => 'Modern Summary',
                    'ar' => 'التصميم الحديث',
                ],
                'description' => [
                    'en' => 'A cleaner layout with summary blocks, modern spacing, and simplified sections.',
                    'ar' => 'تصميم أنظف مع بطاقات ملخص وتباعد حديث وأقسام مبسطة.',
                ],
                'view' => 'admin.contracts.templates.modern',
            ],
        ];
    }

    /**
     * @return array<int, array{
     *     value:string,
     *     label:array{en:string,ar:string},
     *     description:array{en:string,ar:string}
     * }>
     */
    public static function contractTemplateOptions(): array
    {
        return array_values(array_map(
            fn (array $template) => [
                'value' => $template['value'],
                'label' => $template['label'],
                'description' => $template['description'],
            ],
            self::contractTemplates()
        ));
    }

    /**
     * @return array{
     *     value:string,
     *     label:array{en:string,ar:string},
     *     description:array{en:string,ar:string},
     *     view:string
     * }
     */
    public static function resolveContractTemplate(?string $value): array
    {
        $templates = self::contractTemplates();
        $key = trim((string) $value);

        return $templates[$key] ?? $templates[self::DEFAULT_CONTRACT_TEMPLATE];
    }

    /**
     * @return array<int, string>
     */
    public static function contractTemplateValues(): array
    {
        return array_keys(self::contractTemplates());
    }
}
