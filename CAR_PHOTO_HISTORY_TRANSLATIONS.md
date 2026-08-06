# Car Photo History - Translation Keys Added

## Overview
تمت إضافة ترجمات صفحة إنشاء/تعديل سجل صور السيارة (Car Photo History) إلى نظام Landing Translation.

## الملفات المتأثرة
- `app/Http/Controllers/SuperAdmin/LandingSettingsController.php` - تم تحديث دالة `defaultTranslationRows()`

## Translation Keys المضافة

تم إضافة المفاتيح التالية إلى نظام الترجمة:

| Translation Key | English Default Value | الاستخدام |
|----------------|----------------------|----------|
| `dashboard.admin.cars.photo_history.edit_record` | Edit Record | عنوان الصفحة عند التعديل |
| `dashboard.admin.cars.photo_history.new_record` | New Record | عنوان الصفحة عند الإنشاء |
| `dashboard.admin.cars.photo_history.back_to_history` | Back to History | زر العودة للسجلات |
| `dashboard.admin.cars.photo_history.reason` | Reason | تسمية حقل السبب |
| `dashboard.admin.cars.photo_history.select_reason` | Select Reason | خيار افتراضي في القائمة المنسدلة |
| `dashboard.admin.cars.photo_history.reason_before_delivery` | Before Delivery | خيار: قبل التسليم |
| `dashboard.admin.cars.photo_history.reason_after_return` | After Return | خيار: بعد الاستلام |
| `dashboard.admin.cars.photo_history.reason_new_damage` | New Damage | خيار: ضرر جديد |
| `dashboard.admin.cars.photo_history.reason_after_cleaning` | After Cleaning | خيار: بعد التنظيف |
| `dashboard.admin.cars.photo_history.reason_after_maintenance` | After Maintenance | خيار: بعد الصيانة |
| `dashboard.admin.cars.photo_history.photos` | Photos | تسمية حقل الصور |
| `dashboard.admin.cars.photo_history.notes_optional` | Notes (Optional) | تسمية حقل الملاحظات |
| `dashboard.admin.cars.photo_history.notes_placeholder` | Enter notes here... | نص توضيحي لحقل الملاحظات |
| `dashboard.admin.cars.photo_history.cancel` | Cancel | زر الإلغاء |
| `dashboard.admin.cars.photo_history.save` | Save | زر الحفظ |

## كيفية الوصول للترجمات

### من لوحة SuperAdmin:
1. اذهب إلى: `/superadmin/settings/landing-translations`
2. ابحث عن: `dashboard.admin.cars.photo_history`
3. أو استخدم البحث بـ: `photo_history` أو `photo history`

### تصفية حسب القسم (Section):
- اختر `dashboard` من قائمة Sections
- ستظهر جميع ترجمات لوحة التحكم بما فيها Photo History

## الترجمة التلقائية

يمكن استخدام زر **Auto Translate** في صفحة Landing Translations لترجمة جميع النصوص تلقائيًا إلى العربية باستخدام OpenAI.

## ملاحظات مهمة

1. **الصفحة الحالية تستخدم `localize()`**:
   - الملف: `resources/js/Pages/Admin/Cars/PhotoHistories/Edit.vue`
   - يجب تحديث الصفحة لاستخدام نظام الترجمة الرسمي `t()` بدلاً من `localize()`

2. **التحديث المطلوب**:
   ```vue
   // قبل:
   localize('Edit Record', 'تعديل السجل')
   
   // بعد:
   t('dashboard.admin.cars.photo_history.edit_record')
   ```

3. **الموقع في الكود**:
   - السطر 824-912 في `LandingSettingsController.php`
   - القسم: `// Car Photo History`

## الخطوات التالية (اختياري)

إذا أردت استخدام نظام الترجمة الكامل:

1. تحديث `Edit.vue` لاستخدام `t()` بدلاً من `localize()`
2. حذف دالة `localize()` من الملف
3. حذف الترجمات المباشرة (hardcoded) من الكود

## Verification

للتحقق من أن الترجمات تعمل:

```bash
# من terminal
php artisan tinker

# ثم نفذ:
$rows = app(App\Http\Controllers\SuperAdmin\LandingSettingsController::class)->defaultTranslationRows('en');
echo $rows['dashboard.admin.cars.photo_history.edit_record'] ?? 'Not found';
// يجب أن يطبع: Edit Record
```

---

**تاريخ الإضافة:** 2026-08-06  
**المطور:** Kiro AI Assistant
