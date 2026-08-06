# Client Documents Page - Translation Implementation

## Overview
تم تحديث صفحة Client Documents لاستخدام نظام Landing Translation بدلاً من النصوص الثابتة.

## Changes Made

### 1. Added Translations to LandingSettingsController
تم إضافة جميع الترجمات المطلوبة في دالة `defaultTranslationRows()` في الملف:
`app/Http/Controllers/SuperAdmin/LandingSettingsController.php`

#### English Translations (lines 1690-1713)
```php
'dashboard.admin.clients.documents.title' => 'Client Documents',
'dashboard.admin.clients.documents.back_to_client' => 'Back To Client',
'dashboard.admin.clients.documents.document_file' => 'Document File',
'dashboard.admin.clients.documents.run_ocr_extraction' => 'Run OCR Extraction',
'dashboard.admin.clients.documents.extracting' => 'Extracting...',
'dashboard.admin.clients.documents.apply_extracted_to_all_fields' => 'Apply Extracted To All Fields',
'dashboard.admin.clients.documents.save_document' => 'Save Document',
'dashboard.admin.clients.documents.saving' => 'Saving...',
'dashboard.admin.clients.documents.document_saved' => 'Document saved.',
'dashboard.admin.clients.documents.document_save_failed' => 'Document save failed. Check the fields and try again.',
'dashboard.admin.clients.documents.upload_file_first' => 'Upload a file first, then run extraction.',
'dashboard.admin.clients.documents.extraction_completed' => 'Document extraction completed.',
'dashboard.admin.clients.documents.extraction_failed' => 'Document extraction failed.',
'dashboard.admin.clients.documents.extraction_request_failed' => 'Document extraction request failed.',
'dashboard.admin.clients.documents.raw_ocr_output' => 'Raw OCR Output',
'dashboard.admin.clients.documents.raw_text' => 'Raw Text',
'dashboard.admin.clients.documents.json' => 'JSON',
'dashboard.admin.clients.documents.no_ocr_text_yet' => 'No OCR text yet.',
'dashboard.admin.clients.documents.confidence' => 'Confidence',
'dashboard.admin.clients.documents.local_ocr_enabled' => 'enabled',
'dashboard.admin.clients.documents.local_ocr_disabled' => 'disabled',
'dashboard.admin.clients.documents.local_ocr_status' => 'Local OCR is :status.',
'dashboard.admin.clients.documents.python_binary' => 'Python binary',
```

#### Arabic Translations (lines 2413-2436)
```php
'dashboard.admin.clients.documents.title' => 'مستندات العميل',
'dashboard.admin.clients.documents.back_to_client' => 'العودة للعميل',
'dashboard.admin.clients.documents.document_file' => 'ملف المستند',
'dashboard.admin.clients.documents.run_ocr_extraction' => 'تشغيل استخراج OCR',
'dashboard.admin.clients.documents.extracting' => 'جاري الاستخراج...',
'dashboard.admin.clients.documents.apply_extracted_to_all_fields' => 'تطبيق البيانات المستخرجة على جميع الحقول',
'dashboard.admin.clients.documents.save_document' => 'حفظ المستند',
'dashboard.admin.clients.documents.saving' => 'جاري الحفظ...',
'dashboard.admin.clients.documents.document_saved' => 'تم حفظ المستند.',
'dashboard.admin.clients.documents.document_save_failed' => 'فشل حفظ المستند. تحقق من الحقول وحاول مرة أخرى.',
'dashboard.admin.clients.documents.upload_file_first' => 'قم برفع ملف أولاً، ثم شغل الاستخراج.',
'dashboard.admin.clients.documents.extraction_completed' => 'تم استخراج المستند بنجاح.',
'dashboard.admin.clients.documents.extraction_failed' => 'فشل استخراج المستند.',
'dashboard.admin.clients.documents.extraction_request_failed' => 'فشل طلب استخراج المستند.',
'dashboard.admin.clients.documents.raw_ocr_output' => 'مخرجات OCR الخام',
'dashboard.admin.clients.documents.raw_text' => 'النص الخام',
'dashboard.admin.clients.documents.json' => 'JSON',
'dashboard.admin.clients.documents.no_ocr_text_yet' => 'لا يوجد نص OCR بعد.',
'dashboard.admin.clients.documents.confidence' => 'الثقة',
'dashboard.admin.clients.documents.local_ocr_enabled' => 'مفعل',
'dashboard.admin.clients.documents.local_ocr_disabled' => 'معطل',
'dashboard.admin.clients.documents.local_ocr_status' => 'OCR المحلي :status.',
'dashboard.admin.clients.documents.python_binary' => 'ملف Python التنفيذي',
```

### 2. Updated Vue Component
تم تحديث الملف `resources/js/Pages/Admin/Clients/Documents.vue` لاستخدام نظام الترجمة:

#### Changes:
- استخدام `useTrans` composable
- تعريف `translationRoot` و `translate` function
- استبدال جميع النصوص الثابتة بدوال ترجمة
- إصلاح مشكلة TypeScript مع `raw_output` بتحويله إلى JSON string

#### Key Updates:
```typescript
const { t } = useTrans();
const translationRoot = 'dashboard.admin.clients.documents';
const translate = (key: string) => t(`${translationRoot}.${key}`);
```

#### Template Updates:
- `<h1>` title: `{{ translate('title') }}`
- Back button: `{{ translate('back_to_client') }}`
- Document file label: `{{ translate('document_file') }}`
- Buttons: `translate('run_ocr_extraction')`, `translate('save_document')`, etc.
- Status messages: `translate('document_saved')`, `translate('extraction_failed')`, etc.
- OCR section: `translate('raw_ocr_output')`, `translate('raw_text')`, `translate('json')`

### 3. Fixed TypeScript Issues
Fixed TypeScript error with `raw_output` by converting it to JSON string before sending:
```typescript
raw_output: rawOutput[documentType] ? JSON.stringify(rawOutput[documentType]) : null,
```

## How to Access Translations

### For Administrators:
1. Navigate to: `/superadmin/settings/landing-translations`
2. Search for: `dashboard.admin.clients.documents`
3. Edit translations as needed
4. Click "Save Translations"

### For Developers:
Translations are loaded automatically from `LandingSettingsController::defaultTranslationRows()` and merged with any custom translations from the database.

## Translation Keys Structure

All keys follow this pattern:
```
dashboard.admin.clients.documents.{key_name}
```

Example:
- `dashboard.admin.clients.documents.title` → "Client Documents" (EN) / "مستندات العميل" (AR)
- `dashboard.admin.clients.documents.save_document` → "Save Document" (EN) / "حفظ المستند" (AR)

## Testing

To test the translations:
1. Visit: `https://default-tenant.real-rent-car-main.test/ar/admin/clients/16/documents`
2. Switch between English and Arabic
3. Verify all text elements are translated correctly

## Files Modified

1. `app/Http/Controllers/SuperAdmin/LandingSettingsController.php` - Added translations
2. `resources/js/Pages/Admin/Clients/Documents.vue` - Updated to use translation system

## Notes

- ✅ All hardcoded text replaced with translation keys
- ✅ Both English and Arabic translations added
- ✅ TypeScript errors fixed
- ✅ Follows existing project translation patterns
- ✅ Compatible with landing translation management system
