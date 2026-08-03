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

## Owner Discount Requests API

Implemented as the mobile owner/partner API for the same workflow used by the admin dashboard page:

- `GET /api/owner/discount-requests?branch_id=&status=pending&page=1&per_page=20`
- `GET /api/owner/discount-requests/count?branch_id=`
- `GET /api/owner/discount-requests/{discountRequest}`
- `POST /api/owner/discount-requests/{discountRequest}/approve`
- `POST /api/owner/discount-requests/{discountRequest}/reject`

Rules:

- The source table is `discount_requests`; no parallel approval table was added.
- The API accepts optional `branch_id`. Empty means all tenant branches; a valid branch filters requests by the reservation car branch.
- The authenticated owner/partner tenant is always used. The API does not accept `tenant_id` from the request.
- Approve/reject uses `DiscountRequestDecisionService`, the same service used by the admin dashboard actions.
- Approving a request updates the linked return report discount, total extra charges, payment status, reviewer, and timestamps.
- Rejecting a request stores reviewer, optional review note, and timestamps.
- Text is returned using `Accept-Language` through `owner_api.discount_requests.*` translation keys.

## Owner Fleet API

Implemented for the owner/partner mobile fleet screen:

- `GET /api/owner/fleet?branch_id=&status=all&search=&page=1&per_page=20`
- `GET /api/owner/fleet/statuses?branch_id=`
- `GET /api/owner/fleet/{car}?branch_id=`

Rules:

- The authenticated owner/partner tenant is always used. The API does not accept `tenant_id` from the request.
- `tenant-owner` and `tenant-partner` are treated the same.
- `branch_id` is optional. Empty means all tenant branches; a valid branch filters cars, counts, revenue, and next events.
- Fleet statuses come from the dashboard `CarStatus` enum, not the static mobile mockup chips.
- Supported statuses: `draft`, `available`, `reserved`, `rented`, `maintenance`, `cleaning`, `unavailable`, `retired`.
- The list endpoint returns summary counts, status filters with counts, paginated cars, monthly revenue per car, branch location, and the nearest reservation/return event.
- The show endpoint returns one car with images, additional photos, stats, recent reservations, monthly revenue, and nearest event.
- Text is returned using `Accept-Language` through `owner_api.fleet.*` translation keys.

## Owner Reservations API

Implemented for the owner/partner mobile reservations screen:

- `GET /api/owner/reservations?branch_id=&status=all&payment_status=all&date=&date_type=pickup&search=&page=1&per_page=20`
- `GET /api/owner/reservations/summary?branch_id=`
- `GET /api/owner/reservations/{reservation}?branch_id=`

Rules:

- The authenticated owner/partner tenant is always used. The API does not accept `tenant_id` from the request.
- `tenant-owner` and `tenant-partner` are treated the same.
- `branch_id` is optional. Empty means all tenant branches; a valid branch filters reservations by the reservation car branch.
- Reservation statuses come from the dashboard `ReservationStatus` enum, not static mobile mockup chips.
- Payment status is derived from completed payments:
  - `paid`: completed paid amount is greater than or equal to reservation total.
  - `partial`: completed paid amount is greater than zero and less than reservation total.
  - `not_paid`: no completed paid amount.
- Date filters:
  - `date_type=pickup` filters `start_date`.
  - `date_type=return` filters `end_date`.
  - `date_type=created` filters `created_at`.
- Search checks reservation number, customer name/email/phone, car make/model/year/license plate, and branch name.
- Summary counts follow the same filters as the list: `branch_id`, `status`, `payment_status`, `date`, `date_type`, and `search`. Pagination parameters only affect the returned page data, not the summary counts.
- The list endpoint returns summary cards, paginated reservations, car image, customer, branch location, reservation status, payment status, pickup/return dates, and payment totals.
- The show endpoint returns one reservation with payments, contract, return report summary, and timeline events.
- Text is returned using `Accept-Language` through `owner_api.reservations.*` translation keys.

## Owner API Documentation Rule

From now on, every change to an `owner` API must be reflected in this file before the task is considered complete.

Required for each owner API change:

- Add or update the endpoint section in `analysis/ownerapi.md`.
- Document request parameters, filters, response shape, and branch-scope rules.
- Confirm that `tenant_id` is always resolved from the authenticated owner/partner user and is never accepted from request input.
- Confirm that `branch_id` is optional, validated against the authenticated tenant, and applied consistently to counts, cards, lists, charts, and details.
- Add every API-facing label/message/status to the Landing Translation keys under `owner_api.*`.
- Keep the API response text resolved by `Accept-Language` through `App\Support\TenantTranslations`.

## 2026-08-02 Owner Notifications Translation Fix

Issue:

- `GET /api/owner/notifications?branch_id=&page=1&per_page=20` returned mojibake Arabic text for section titles and active alert labels.
- The endpoint was already using `TenantTranslations`, but the Arabic fallback values under `lang/ar/site.php -> owner_api` were corrupted.
- No valid override was found in global Landing Translation settings or tenant translation settings, so the API fell back to the corrupted file values.

Fixed translation groups:

- `owner_api.alerts.*`
- `owner_api.notifications.sections.*`
- `owner_api.notifications.late_return.*`
- `owner_api.notifications.unpaid_violation.*`
- `owner_api.notifications.maintenance_required.*`
- `owner_api.notifications.new_reservation.*`
- `owner_api.notifications.payment_received.*`
- `owner_api.notifications.discount_request.*`
- `owner_api.notifications.messages.marked_read`
- `owner_api.time.minutes_ago`
- `owner_api.time.hours_ago`
- `owner_api.time.days_ago`

Verification:

- `php -l lang\ar\site.php`
- `php artisan optimize:clear`
- Confirmed Arabic fallback output for notification sections, active alerts, and time labels.

Important:

- If the API shows broken text again, first check whether a bad override exists in Landing Translation or tenant translations before changing controller/service logic.

## 2026-08-03 Owner & Partner API 403 Forbidden Access Fix

Issue:

- Owner and Partner API requests to `/api/owner/*` (e.g. `GET /api/owner/branches`) intermittently returned `403 Forbidden`.
- Running `php artisan optimize:clear` temporarily resolved the issue, but it reoccurred when requests were made via mobile/API without prior web dashboard login.
- `BranchAccess::canAccessAllBranches()` strictly required an explicit `tenant-owner` or `tenant-partner` role record in the `role_user` database table and lacked the fallback capabilities present in `ApiAccessMode::isOwnerCapable()`.
- Primary tenant owner admins and partner accounts created via API or seeders were missing automatic role synchronization because `TenantAdminAccessSync` was only invoked inside web `AdminMiddleware`.

Fix Details:

1. **BranchAccess Authorization Fallback & Auto-Sync**:
   - Updated `BranchAccess::canAccessAllBranches()` in `app/Support/BranchAccess.php` to invoke `ApiAccessMode::isOwnerCapable($user)` as a fallback when role records are pending.
   - Automatically triggers `TenantAdminAccessSync::syncUser()` on owner-capable users so role relations are created and persisted in the DB upon API execution.

2. **Refined Employee vs. Owner & Partner Discrimination**:
   - Updated `ApiAccessMode::isOwnerCapable()` in `app/Support/ApiAccessMode.php` to check if a user is assigned to a specific branch (`branch_id !== null`). Users with explicit branch assignments remain scoped as employees unless assigned an explicit `tenant-owner` or `tenant-partner` role.
   - Users without branch assignment (`branch_id === null`) or holding `tenant-partner` / `tenant-owner` roles are correctly identified as owner-capable across all API routes.

3. **API Authentication Role Sync**:
   - Added automatic `TenantAdminAccessSync::syncUser()` calls inside `AuthController::login()` and `AuthController::switchMode()` for admin users in `app/Http/Controllers/Api/AuthController.php`.

4. **Automated Test Coverage**:
   - Added feature tests in `tests/Feature/Api/OwnerDashboardControllerTest.php` ensuring owner admins without pre-attached roles and partner admins with `tenant-partner` roles receive `200 OK` on `/api/owner/branches`.
   - Confirmed full test suite pass across `OwnerDashboardControllerTest` and `DailyTasksControllerTest`.

## 2026-08-03 Owner Fleet Show API Screen Alignment Analysis

Comparison between current `GET /api/owner/fleet/{car}` response shape and the mobile design mockup for the Car Details screen:

Missing / Required Fields to match the mobile screen:

1. **`occupancy_rate` (نسبة الإشغال)**:
   - Percentage indicator (e.g. `72%`) representing current monthly car utilization.
   - Fields: `occupancy_rate` (float/int), `formatted_occupancy_rate` (string, e.g. `"72%"`).

2. **`upcoming_reservations_summary` (الحجوزات القادمة)**:
   - Summary card for upcoming reservations.
   - Fields: `count` (int, e.g. `2`), `subtitle` (string, e.g. `"حجوزتان قادمتان"`), `nearest_date_label` (string, e.g. `"18 مايو، 11:00 ص"`).

3. **`last_maintenance` (آخر صيانة)**:
   - Summary card for latest car maintenance.
   - Fields: `count` (int, e.g. `0`), `date` (string, e.g. `"2024-04-10"`), `formatted_date` (string), `days_ago` (int, e.g. `32`), `days_ago_label` (string, e.g. `"قبل 32 يوم"`), `status` (string, e.g. `"good"`), `status_label` (string, e.g. `"جيد"`), `status_color` (string).

4. **`damage_record_summary` (سجل الأضرار)**:
   - Summary card for car damage history.
   - Fields: `count` (int, e.g. `0`), `description` (string, e.g. `"لا توجد أضرار مسجلة"`), `status` (string, e.g. `"excellent"`), `status_label` (string, e.g. `"ممتاز"`), `status_color` (string).

5. **`quick_actions` (إجراءات سريعة)**:
   - Quick action chips at the bottom of the screen.
   - Actions: `schedule_maintenance` (جدولة صيانة), `view_reservations` (عرض الحجوزات), `transfer_branch` (نقل إلى فرع آخر). (Skipped UI chips per user instruction).

Implementation Status (2026-08-03):

- **Monthly Revenue Calculation Fix**: Calculated for the last 30 rolling days (`Carbon::today()->subDays(30)` to `endOfDay()`) and subtracted `refunded_amount` (`net_revenue = COALESCE(base_amount, amount) - COALESCE(refunded_amount, 0)`).
- **Occupancy Rate**: Implemented `occupancy_rate` (int percentage for the last 30 rolling days) and `formatted_occupancy_rate` (`"72%"`).
- **Upcoming Reservations Summary**: Implemented `upcoming_reservations_summary` containing `count`, `subtitle`, and `nearest_date_label` (e.g. `"18 مايو، 11:00 ص"`).
- **Last Maintenance Summary**: Implemented `last_maintenance` containing `count`, `date`, `formatted_date`, `days_ago`, `days_ago_label` (e.g. `"قبل 32 يوم"`), `status`, `status_label`, and `status_color`.
- **Damage Record Summary**: Implemented `damage_record_summary` containing `count`, `description` (e.g. `"لا توجد أضرار مسجلة"`), `status`, `status_label`, and `status_color`.
- **Quick Actions**: Excluded per explicit user request.
- **Tests**: Created `tests/Feature/Api/OwnerFleetControllerTest.php` and verified 100% pass rate.
