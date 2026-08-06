<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class PhotoHistoryTranslationsSeeder extends Seeder
{
    public function run(): void
    {
        $landingSetting = SiteSetting::query()
            ->where('key', 'landing_page')
            ->first();

        if (!$landingSetting) {
            $this->command->warn('Landing page setting not found. Creating it...');
            $landingSetting = SiteSetting::create([
                'key' => 'landing_page',
                'value' => ['translations' => []],
            ]);
        }

        $value = is_array($landingSetting->value) ? $landingSetting->value : [];
        $translations = is_array($value['translations'] ?? null) ? $value['translations'] : [];

        // English translations
        $translations['en'] = array_merge($translations['en'] ?? [], [
            'dashboard' => array_merge($translations['en']['dashboard'] ?? [], [
                'admin' => array_merge($translations['en']['dashboard']['admin'] ?? [], [
                    'cars' => array_merge($translations['en']['dashboard']['admin']['cars'] ?? [], [
                        'photo_history' => [
                            'edit_record' => 'Edit Record',
                            'new_record' => 'New Record',
                            'back_to_history' => 'Back to History',
                            'reason' => 'Reason',
                            'select_reason' => 'Select Reason',
                            'reason_before_delivery' => 'Before Delivery',
                            'reason_after_return' => 'After Return',
                            'reason_new_damage' => 'New Damage',
                            'reason_after_cleaning' => 'After Cleaning',
                            'reason_after_maintenance' => 'After Maintenance',
                            'photos' => 'Photos',
                            'notes_optional' => 'Notes (Optional)',
                            'notes_placeholder' => 'Enter notes here...',
                            'cancel' => 'Cancel',
                            'save' => 'Save',
                        ],
                    ]),
                ]),
            ]),
        ]);

        // Arabic translations
        $translations['ar'] = array_merge($translations['ar'] ?? [], [
            'dashboard' => array_merge($translations['ar']['dashboard'] ?? [], [
                'admin' => array_merge($translations['ar']['dashboard']['admin'] ?? [], [
                    'cars' => array_merge($translations['ar']['dashboard']['admin']['cars'] ?? [], [
                        'photo_history' => [
                            'edit_record' => 'تعديل السجل',
                            'new_record' => 'سجل جديد',
                            'back_to_history' => 'العودة للسجلات',
                            'reason' => 'السبب',
                            'select_reason' => 'اختر السبب',
                            'reason_before_delivery' => 'قبل التسليم',
                            'reason_after_return' => 'بعد الاستلام',
                            'reason_new_damage' => 'ضرر جديد',
                            'reason_after_cleaning' => 'بعد التنظيف',
                            'reason_after_maintenance' => 'بعد الصيانة',
                            'photos' => 'الصور',
                            'notes_optional' => 'ملاحظات (اختياري)',
                            'notes_placeholder' => 'اكتب ملاحظات هنا...',
                            'cancel' => 'إلغاء',
                            'save' => 'حفظ',
                        ],
                    ]),
                ]),
            ]),
        ]);

        $value['translations'] = $translations;
        $landingSetting->update(['value' => $value]);

        $this->command->info('✅ Photo History translations added successfully!');
        $this->command->info('   - English: 15 keys added');
        $this->command->info('   - Arabic: 15 keys added');
    }
}
