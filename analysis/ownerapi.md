# Owner API Analysis

## الهدف

نحتاج نبني API خاص بتطبيق المالك/صاحب المكتب `owner` بحيث يعرض بيانات المكتب حسب الفروع. الموظف حاليًا يرجع بياناته حسب الفرع، لذلك نطاق العمل هنا هو صاحب المكتب الذي يستطيع رؤية كل الفروع أو اختيار فرع محدد من التطبيق.

## المصطلحات

- `tenant_id`: المكتب/الشركة المالكة للبيانات. هذا يجب أن يؤخذ من المستخدم المصادق عليه أو سياق الـ tenant، وليس من الطلب.
- `branch_id`: الفرع داخل المكتب. هذا هو الفلتر الذي يرسله تطبيق المالك عند اختيار فرع محدد.
- `brand_id`: إذا لم يكن موجودًا فعليًا في الجداول الحالية، لا نستخدمه كفلتر. المقصود في سيناريو الصور هو `branch_id`.

## القاعدة الأساسية

كل API خاص بالمالك يجب أن يطبق قاعدتين:

1. البيانات محصورة دائمًا داخل `tenant_id` الخاص بالمستخدم.
2. إذا أرسل المستخدم `branch_id` يتم عرض بيانات هذا الفرع فقط، وإذا لم يرسل فرعًا يتم عرض بيانات كل فروع المكتب.

لا يجب قبول `tenant_id` من body أو query params لأن هذا يفتح مشكلة وصول لبيانات مكاتب أخرى.

## الموجود حاليًا

يوجد كلاس جاهز:

`app\Support\BranchAccess.php`

ويحتوي على:

- `canAccessAllBranches($user)`
- `availableBranchesForUser($user)`
- `normalizeRequestedBranchId($value)`
- `applyToQuery($query, $user, $requestedBranchId, $column = 'branch_id')`
- `canAccessBranchId($user, $branchId)`
- `resolveWritableBranchId($user, $requestedBranchId)`

هذا يعني أننا لا نحتاج نبدأ من الصفر. الأفضل أن نعيد استخدام نفس المنطق في owner APIs، مع التأكد أن صاحب المكتب يستطيع رؤية كل الفروع.

## صلاحيات الوصول

الـ owner API يجب أن يكون تحت:

- `auth:sanctum`
- مستخدم tenant owner أو tenant partner أو admin يملك صلاحية رؤية كل الفروع.

مهم: في سيناريو تطبيق المالك، `tenant-owner` و `tenant-partner` يعاملان نفس المعاملة. يعني الـ partner هو owner من ناحية الـ API ونطاق البيانات، ويجب أن يستطيع رؤية نفس بيانات الفروع التي يراها owner.

قاعدة مقترحة:

- صاحب المكتب أو الشريك يرى كل الفروع.
- إذا اختار فرعًا، يرجع بيانات هذا الفرع فقط.
- إذا أرسل فرع غير تابع لمكتبه، يرجع خطأ.
- الموظف العادي لا يستخدم owner API، أو يرجع له `403`.

## فلترة الفروع

شكل الطلب:

```http
GET /api/owner/dashboard/summary?branch_id=2
```

القواعد:

- `branch_id` غير موجود: عرض كل الفروع.
- `branch_id=null` أو فارغ: عرض كل الفروع.
- `branch_id=0`: نعامله مثل كل الفروع إذا احتاج التطبيق ذلك.
- `branch_id=2`: عرض الفرع رقم 2 فقط.
- `branch_id` غير تابع للـ tenant: خطأ `403` أو `422`.

التوصية:

- `403` إذا الفرع موجود لكن المستخدم لا يملك صلاحية الوصول له.
- `422` إذا قيمة `branch_id` غير صحيحة.

## الـ APIs المقترحة للمرحلة الأولى

### 1. قائمة الفروع

```http
GET /api/owner/branches
```

ترجع الفروع التي يستطيع المالك اختيارها في bottom sheet.

Response مقترح:

```json
{
  "data": [
    {
      "id": null,
      "name": "All branches",
      "is_all": true
    },
    {
      "id": 1,
      "name": "Main branch",
      "city": "Muscat",
      "country": "Oman",
      "is_all": false
    }
  ]
}
```

### 2. ملخص لوحة المالك

```http
GET /api/owner/dashboard/summary?branch_id=&period=today
```

يخدم شاشة الرئيسية في تطبيق المالك.

Response مقترح:

```json
{
  "tenant": {
    "id": 2,
    "name": "DT 2"
  },
  "selected_branch": {
    "id": 1,
    "name": "Main branch"
  },
  "currency": {
    "code": "OMR",
    "symbol": "ر.ع"
  },
  "cards": {
    "today_revenue": {
      "value": 12450,
      "change_percent": 12.5,
      "comparison_label": "Compared with yesterday"
    },
    "available_cars": {
      "value": 126,
      "change_percent": 5.4
    },
    "active_reservations": {
      "value": 86,
      "change_percent": 8.1
    },
    "late_returns": {
      "value": 7,
      "change_value": 2
    },
    "rented_cars": {
      "value": 58,
      "change_percent": 3.2
    }
  },
  "revenue_chart": [
    {
      "date": "2026-07-19",
      "label": "Jul 19",
      "value": 7200
    }
  ],
  "quick_alerts": [
    {
      "key": "late_returns",
      "title": "Late returns",
      "description": "5 delayed return operations",
      "count": 3
    }
  ],
  "notification_badge_count": 3
}
```

## APIs لاحقة

بعد ملخص الرئيسية، نحتاج نبني APIs تفصيلية حسب كل tab:

- `GET /api/owner/notifications?branch_id=`
- `GET /api/owner/fleet?branch_id=&status=&search=`
- `GET /api/owner/reservations?branch_id=&status=&date_from=&date_to=`
- `GET /api/owner/contracts?branch_id=&status=&date_from=&date_to=`
- `GET /api/owner/approvals?branch_id=`
- `GET /api/owner/payments/summary?branch_id=&date_from=&date_to=`
- `GET /api/owner/alerts?branch_id=`

## تعريفات الأرقام في الشاشة

هذه التعريفات يجب تثبيتها قبل التنفيذ:

- `today_revenue`: مجموع المدفوعات المحصلة اليوم داخل الفرع/كل الفروع.
- `available_cars`: عدد السيارات المتاحة للتأجير.
- `active_reservations`: الحجوزات النشطة أو القادمة حسب تعريف النظام الحالي.
- `late_returns`: العقود التي انتهى وقت إرجاعها ولم يتم إغلاقها/إرجاع السيارة.
- `rented_cars`: السيارات المرتبطة بعقود فعالة أو حالتها `rented`.

إذا كان عندنا منطق جاهز في `DashboardController` الحالي، الأفضل نعيد استخدامه أو ننقل الجزء المشترك إلى service حتى لا يصير في اختلاف بين dashboard والـ owner app.

## خطة التنفيذ

### Phase 1: البنية الأساسية

- إنشاء route group باسم `owner`.
- إضافة controller خاص مثل `OwnerDashboardController`.
- إعادة استخدام `BranchAccess`.
- إضافة endpoint للفروع.
- إضافة endpoint للملخص الرئيسي.

### Phase 2: توحيد الحسابات

- مراجعة حسابات revenue والسيارات والحجوزات مع dashboard الحالي.
- فصل الحسابات المشتركة في service إذا كان التكرار سيزيد.
- التأكد أن كل query عليها tenant scope و branch scope.

### Phase 3: التنبيهات والموافقات

- Owner notifications.
- Approval requests مثل طلبات الخصم أو طلبات تحتاج موافقة.
- Quick alerts للصفحة الرئيسية.

### Phase 4: القوائم التفصيلية

- Fleet list.
- Reservations list.
- Contracts list.
- Payments list/summary.

### Phase 5: اللغة والتنسيق

- كل الرسائل و labels ترجع حسب `Accept-Language`.
- الأرقام لا تتحول لنصوص مترجمة في الـ API إلا إذا التطبيق يحتاج ذلك.
- العملة ترجع كـ object حتى التطبيق ينسقها بشكل صحيح.

## قواعد الاختبار

يجب اختبار كل endpoint بهذه الحالات:

1. Owner بدون `branch_id`: يرجع مجموع كل الفروع.
2. Owner مع `branch_id` صحيح: يرجع بيانات الفرع فقط.
3. Owner مع `branch_id` من tenant آخر: يرجع خطأ ولا يسرب بيانات.
4. Partner يدخل owner API: يعامل مثل owner ويرجع له نفس البيانات.
5. Employee يحاول يدخل owner API: يرجع `403`.
6. مقارنة مجموع الفروع مع نتيجة كل الفروع للتأكد من الحسابات.
7. اختبار `Accept-Language: ar` و `Accept-Language: en`.
8. اختبار tenant لا يملك فروعًا.

## أسئلة قبل التنفيذ

- هل owner app سيستخدم نفس login/token الحالي أم guard مختلف؟
- هل أسماء الصلاحيات المعتمدة دائمًا `tenant-owner` و `tenant-partner`؟
- هل `branch_id=0` نثبته كقيمة "كل الفروع" في التطبيق؟
- هل مقارنة النسب تكون مع أمس، أم نفس الفترة السابقة؟
- هل revenue يعتمد على كل payments أم فقط completed cash/card payments؟
- هل كل الأرقام تعرض بعملة tenant الأساسية فقط، أم نحتاج تحويل العملات؟

## ملاحظة مهمة

لا نبدأ بتنفيذ endpoints كثيرة مرة واحدة. البداية الأفضل تكون بـ:

1. `GET /api/owner/branches`
2. `GET /api/owner/dashboard/summary`

بعدها نثبت شكل response مع تطبيق الموبايل، ثم نكمل باقي القوائم.

## حالة التنفيذ

تم تنفيذ المرحلة الأولى:

- `GET /api/owner/branches`
- `GET /api/owner/dashboard/summary`

ملاحظات التنفيذ:

- `tenant-owner` و `tenant-partner` يعاملان نفس المعاملة في `BranchAccess`.
- الـ owner APIs لا تقبل `tenant_id` من الطلب.
- `branch_id` اختياري. عند عدم إرساله ترجع بيانات كل الفروع.
- عند إرسال `branch_id` غير تابع للـ tenant يرجع validation error.
- ملخص الإيراد يستخدم `base_amount` إذا كان موجودًا، وإلا يستخدم `amount`.
- مقارنة الإيراد حاليًا مع يوم أمس فقط.
- باقي كروت الأرقام لا ترجع نسبة تغير لأنها تحتاج snapshot/history يومي حتى تكون دقيقة.

## Owner Dashboard Change Accuracy

- Change values for owner dashboard cards now come from persisted daily snapshots in `owner_dashboard_metric_snapshots`.
- The API compares today's live value with yesterday's saved snapshot. It does not infer yesterday from `updated_at`.
- Historical days before the snapshot system runs cannot be recovered 100% for status-based metrics because car status is overwritten in the current row.
- Run `php artisan owner-dashboard:snapshot` to capture today's values manually.
- Run `php artisan owner-dashboard:snapshot --date=2026-07-26` only for controlled testing; it cannot recreate a true historical car status if the snapshot was not captured on that day.
- The scheduler captures owner dashboard snapshots daily at `23:59`.

## Owner Notifications API

Implemented for the owner/partner mobile notifications screen:

- `GET /api/owner/notifications?branch_id=&page=1&per_page=20`
- `GET /api/owner/notifications/count?branch_id=`
- `POST /api/owner/notifications/read-all?branch_id=`

The response is split into:

- `active_alerts`: aggregated operating alerts for late returns, unpaid violations, and maintenance cars.
- `latest_notifications`: a paginated feed sorted by newest first.

Feed item types:

- `late_return`
- `unpaid_violation`
- `maintenance_required`
- `new_reservation`
- `payment_received`

Rules:

- `tenant_id` is always taken from the authenticated owner/partner user.
- `branch_id` is optional. Empty means all branches; a valid branch filters all alert/feed queries.
- Text is returned using `Accept-Language` through `owner_api.*` translation keys.
- Read state is stored in `operational_notification_reads` using owner notification keys.
