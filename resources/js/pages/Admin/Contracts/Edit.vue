<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { useTrans } from '@/composables/useTrans';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
  mode: 'create' | 'edit';
  contract: any | null;
  is_locked?: boolean;
  carData?: Record<string, any> | null;
  currentCarDamages?: Array<Record<string, any>>;
  carDamagesByCar?: Record<number, Array<Record<string, any>>>;
  primaryDriver?: Record<string, any> | null;
  additionalDrivers?: Array<Record<string, any>>;
  reservationOptions: Array<Record<string, any>>;
  reservationFormOptions?: {
    clients: Array<{ id: number; name: string; email: string }>;
    cars: Array<{ id: number; label: string; license_plate: string; branch_name?: string | null; price_per_day: number }>;
  } | null;
  startContractFiles: Array<{ id?: number | null; url?: string | null }>;
  endContractFiles: Array<{ id?: number | null; url?: string | null }>;
  additionalArchive?: Array<Record<string, any>>;
  actions: { index: string; store?: string; update?: string; show?: string; extract?: string; extractDriver?: string; extractCustomerPhoto?: string; reservationStore?: string };
}>();

const { locale } = useTrans();
const arabicTranslations: Record<string, string> = {
  'Pending': 'قيد الانتظار',
  'Confirmed': 'مؤكد',
  'Active': 'نشط',
  'Cancelled': 'ملغى',
  'Select fuel level': 'اختر مستوى الوقود',
  'Empty': 'فارغ',
  '1/4 Tank': 'ربع الخزان',
  '1/2 Tank': 'نصف الخزان',
  '3/4 Tank': 'ثلاثة أرباع الخزان',
  'Full': 'ممتلئ',
  'Select condition': 'اختر الحالة',
  'Clean': 'نظيف',
  'Not Clean': 'غير نظيف',
  'No specific driver': 'لا يوجد سائق محدد',
  'Primary Driver': 'السائق الأساسي',
  'Driver extraction endpoint is not configured.': 'نقطة نهاية استخراج بيانات السائق غير مهيأة.',
  'Select a document type first.': 'اختر نوع المستند أولاً.',
  'Upload at least one document image before extraction.': 'ارفع صورة مستند واحدة على الأقل قبل الاستخراج.',
  'Driver extraction failed.': 'فشل استخراج بيانات السائق.',
  'Document extraction completed.': 'اكتمل استخراج المستند.',
  'Customer photo extraction endpoint is not configured.': 'نقطة نهاية استخراج صورة العميل غير مهيأة.',
  'Upload a front or single document image before extracting the customer photo.': 'ارفع صورة الواجهة أو المستند المفرد قبل استخراج صورة العميل.',
  'Customer photo extraction failed.': 'فشل استخراج صورة العميل.',
  'Reservation creation failed.': 'فشل إنشاء الحجز.',
  'Reservation store route is not configured.': 'مسار حفظ الحجز غير مهيأ.',
  'Create Contract': 'إنشاء عقد',
  'Edit Contract': 'تعديل عقد',
  'Back': 'رجوع',
  'Customer Data': 'بيانات العميل',
  'Primary driver details and document uploads.': 'تفاصيل السائق الأساسي ورفع المستندات.',
  'Primary driver, additional drivers, car data, rental data, and archive.': 'السائق الأساسي، والسائقون الإضافيون، وبيانات السيارة، وبيانات الإيجار، والأرشيف.',
  'Review the extracted AI data carefully before saving this contract.': 'راجع بيانات الذكاء الاصطناعي المستخرجة بعناية قبل حفظ هذا العقد.',
  'Document Type': 'نوع المستند',
  'Full Name': 'الاسم الكامل',
  'Arabic Name': 'الاسم بالعربية',
  'Phone': 'الهاتف',
  'Nationality': 'الجنسية',
  'Car': 'السيارة',
  'Place Of Issue': 'جهة الإصدار',
  'Place of Issue': 'جهة الإصدار',
  'Date Of Birth': 'تاريخ الميلاد',
  'Identity Number': 'رقم الهوية',
  'Residency Number': 'رقم الإقامة',
  'License Number': 'رقم الرخصة',
  'Identity Expiry Date': 'تاريخ انتهاء الهوية',
  'License Expiry Date': 'تاريخ انتهاء الرخصة',
  'Passport Number': 'رقم الجواز',
  'Passport Expiry Date': 'تاريخ انتهاء الجواز',
  'Driving License Issue Date': 'تاريخ إصدار رخصة القيادة',
  'Visa Number': 'رقم التأشيرة',
  'Visa Expiry Date': 'تاريخ انتهاء التأشيرة',
  'Document Front / Single': 'المستند الأمامي / مفرد',
  'Document Back': 'المستند الخلفي',
  'Customer Photo': 'صورة العميل',
  'Customer photo preview': 'معاينة صورة العميل',
  'Extracting Photo...': 'جارٍ استخراج الصورة...',
  'Extract Photo From Document': 'استخراج الصورة من المستند',
  'Extracting...': 'جارٍ الاستخراج...',
  'Extract From Document': 'استخراج من المستند',
  'Confidence': 'نسبة الثقة',
  'I reviewed the AI extracted data and confirm it is correct.': 'راجعت البيانات المستخرجة بواسطة الذكاء الاصطناعي وأؤكد أنها صحيحة.',
  'Additional Drivers': 'السائقون الإضافيون',
  'Additional Archive': 'الأرشيف الإضافي',
  'Independent drivers inside this contract.': 'السائقون المستقلون داخل هذا العقد.',
  'Add Driver': 'إضافة سائق',
  'No additional drivers added.': 'لم تتم إضافة أي سائقين إضافيين.',
  'Upload ID or license and review manually.': 'ارفع الهوية أو الرخصة ثم راجعها يدويًا.',
  'Remove': 'إزالة',
  'Store extra customer documents here. Files already used in the main identity/license section above cannot be added again.': 'خزّن المستندات الإضافية للعميل هنا. لا يمكن إضافة الملفات المستخدمة بالفعل في قسم الهوية/الرخصة أعلاه مرة أخرى.',
  'Add Archive File': 'إضافة ملف أرشيف',
  'Files already used in the main customer document section above cannot be added to this archive.': 'لا يمكن إضافة الملفات المستخدمة بالفعل في قسم مستندات العميل الرئيسي أعلاه إلى هذا الأرشيف.',
  'No additional archive files added.': 'لم تتم إضافة أي ملفات أرشيف إضافية.',
  'Upload one additional customer document for archive only.': 'ارفع مستندًا إضافيًا واحدًا للعميل للأرشيف فقط.',
  'Belongs To': 'يتبع لـ',
  'Title': 'العنوان',
  'Archive File': 'ملف الأرشيف',
  'Car Data': 'بيانات السيارة',
  'Reservation and vehicle details for this contract.': 'تفاصيل الحجز والسيارة لهذا العقد.',
  'Car details are linked to the selected reservation and cannot be edited here.': 'ترتبط تفاصيل السيارة بالحجز المحدد ولا يمكن تعديلها هنا.',
  'Car Details': 'تفاصيل السيارة',
  'Plate Number': 'رقم اللوحة',
  'Vehicle Odometer': 'عداد السيارة',
  'Fuel In Vehicle': 'الوقود في السيارة',
  'Current Car Damages': 'الأضرار الحالية للسيارة',
  'Zone': 'المنطقة',
  'View': 'الجهة',
  'Type': 'النوع',
  'Severity': 'الشدة',
  'Qty': 'الكمية',
  'Rental Data': 'بيانات الإيجار',
  'Contract lifecycle, rental period, amount, and notes.': 'حالة العقد وفترة الإيجار والمبلغ والملاحظات.',
  'Linked Reservation': 'الحجز المرتبط',
  'Search reservation...': 'ابحث عن الحجز...',
  'Clear': 'مسح',
  'No linked reservation': 'لا يوجد حجز مرتبط',
  ' (has contract)': ' (لديه عقد)',
  'No car details': 'لا توجد تفاصيل للسيارة',
  'No reservations found.': 'لا توجد حجوزات.',
  'New Reservation': 'حجز جديد',
  'Reservation': 'الحجز',
  'Contract Date': 'تاريخ العقد',
  'Client': 'العميل',
  'N/A': 'غير متوفر',
  'Contract Number': 'رقم العقد',
  'Status': 'الحالة',
  'Draft': 'مسودة',
  'Completed': 'مكتمل',
  'Rental Start Date': 'تاريخ بدء الإيجار',
  'Rental End Date': 'تاريخ انتهاء الإيجار',
  'Total Amount': 'المبلغ الإجمالي',
  'Currency': 'العملة',
  'Return Mileage': 'عداد العودة',
  'Return Fuel': 'الوقود عند العودة',
  'Return Date / Actual Return Time': 'تاريخ العودة / وقت العودة الفعلي',
  'Vehicle Condition Before Delivery': 'حالة المركبة قبل التسليم',
  'Vehicle Condition After Return': 'حالة المركبة بعد العودة',
  'Notes': 'الملاحظات',
  'Contract Archive': 'أرشيف العقد',
  'Keep contract scans and supporting files here as archive attachments.': 'احتفظ بمسح العقد والملفات الداعمة هنا كمرفقات أرشيف.',
  'Start Rental Contract File': 'ملف عقد بدء الإيجار',
  'End Rental Contract File': 'ملف عقد نهاية الإيجار',
  'Saving...': 'جارٍ الحفظ...',
  'Save Contract': 'حفظ العقد',
  'Cancel': 'إلغاء',
  'Create Reservation': 'إنشاء حجز',
  'Create a reservation here, then link it directly to this contract.': 'أنشئ حجزًا هنا ثم اربطه مباشرةً بهذا العقد.',
  'Select client': 'اختر العميل',
  'Select car': 'اختر السيارة',
  'Start Date': 'تاريخ البدء',
  'End Date': 'تاريخ الانتهاء',
  'Pickup Time': 'وقت الاستلام',
  'Return Time': 'وقت الإرجاع',
  'Pickup Location': 'موقع الاستلام',
  'Return Location': 'موقع الإرجاع',
  'Discount': 'الخصم',
  'Creating...': 'جارٍ الإنشاء...',
};

const localize = (en: string, ar: string) => {
  if (locale.value !== 'ar') {
    return en;
  }

  if (en.startsWith('Primary Driver - ')) {
    return `السائق الأساسي - ${en.slice('Primary Driver - '.length)}`;
  }

  const additionalDriverMatch = en.match(/^Additional Driver (\d+)(?: - (.*))?$/);
  if (additionalDriverMatch) {
    const index = additionalDriverMatch[1];
    const name = additionalDriverMatch[2];
    return name ? `السائق الإضافي ${index} - ${name}` : `السائق الإضافي ${index}`;
  }

  const archiveFileMatch = en.match(/^Archive File (\d+)$/);
  if (archiveFileMatch) {
    return `ملف الأرشيف ${archiveFileMatch[1]}`;
  }

  return arabicTranslations[en] ?? ar;
};

const contractDateMin = computed(() => {
  if (props.mode !== 'create') {
    return undefined;
  }

  const now = new Date();
  const offset = now.getTimezoneOffset() * 60000;
  return new Date(now.getTime() - offset).toISOString().slice(0, 10);
});

const contractStartDateMin = computed(() => form.contract_date || contractDateMin.value);

const contractEndDateMin = computed(() => form.start_date || contractStartDateMin.value);
const isLocked = computed(() => Boolean(props.is_locked));

function formatDateToInput(value: Date): string {
  const offset = value.getTimezoneOffset() * 60000;
  return new Date(value.getTime() - offset).toISOString().slice(0, 10);
}

function addDaysToDateInput(value: string, days: number): string {
  const next = new Date(`${value}T00:00:00`);
  next.setDate(next.getDate() + days);
  return formatDateToInput(next);
}

const contractRentalEndDateMin = computed(() => {
  const source = form.start_date || contractStartDateMin.value;
  if (!source) {
    return undefined;
  }

  return addDaysToDateInput(source, 1);
});

const documentTypeOptions = computed(() => [
  { value: '', label: (usePage<any>().props.locale ?? 'en') === 'ar' ? '\u0627\u062e\u062a\u0631 \u0646\u0648\u0639 \u0627\u0644\u0645\u0633\u062a\u0646\u062f' : 'Select document type' },
  { value: 'passport', label: (usePage<any>().props.locale ?? 'en') === 'ar' ? '\u062c\u0648\u0627\u0632 \u0633\u0641\u0631 (\u0633\u0627\u0626\u062d)' : 'Passport (Tourist)' },
  { value: 'driver_license', label: (usePage<any>().props.locale ?? 'en') === 'ar' ? '\u0631\u062e\u0635\u0629 \u0642\u064a\u0627\u062f\u0629' : 'Driver License' },
  { value: 'id_card', label: (usePage<any>().props.locale ?? 'en') === 'ar' ? '\u0628\u0637\u0627\u0642\u0629 \u0647\u0648\u064a\u0629 (\u0645\u0648\u0627\u0637\u0646)' : 'ID Card (Citizen)' },
  { value: 'residency_card', label: (usePage<any>().props.locale ?? 'en') === 'ar' ? '\u0628\u0637\u0627\u0642\u0629 \u0625\u0642\u0627\u0645\u0629 (\u0645\u0642\u064a\u0645)' : 'Residency Card (Resident)' },
]);

const additionalArchiveDocumentTypeOptions = computed(() => [
  { value: '', label: (usePage<any>().props.locale ?? 'en') === 'ar' ? '\u0627\u062e\u062a\u0631 \u0646\u0648\u0639 \u0627\u0644\u0645\u0633\u062a\u0646\u062f' : 'Select archive type' },
  { value: 'passport', label: (usePage<any>().props.locale ?? 'en') === 'ar' ? '\u062c\u0648\u0627\u0632 \u0633\u0641\u0631' : 'Passport' },
  { value: 'id_card', label: (usePage<any>().props.locale ?? 'en') === 'ar' ? '\u0628\u0637\u0627\u0642\u0629 \u0647\u0648\u064a\u0629' : 'ID Card' },
  { value: 'residency_card', label: (usePage<any>().props.locale ?? 'en') === 'ar' ? '\u0628\u0637\u0627\u0642\u0629 \u0625\u0642\u0627\u0645\u0629' : 'Residency Card' },
  { value: 'driver_license', label: (usePage<any>().props.locale ?? 'en') === 'ar' ? '\u0631\u062e\u0635\u0629 \u0642\u064a\u0627\u062f\u0629' : 'Driver License' },
  { value: 'visa', label: (usePage<any>().props.locale ?? 'en') === 'ar' ? '\u062a\u0623\u0634\u064a\u0631\u0629' : 'Visa' },
  { value: 'insurance', label: (usePage<any>().props.locale ?? 'en') === 'ar' ? '\u062a\u0623\u0645\u064a\u0646' : 'Insurance' },
  { value: 'other', label: (usePage<any>().props.locale ?? 'en') === 'ar' ? '\u0623\u062e\u0631\u0649' : 'Other' },
]);

const reservationStatusOptions = [
  { value: 'pending', label: localize('Pending', 'ط¸â€ڑط¸ظ¹ط·آ¯ ط·آ§ط¸â€‍ط·آ§ط¸â€ ط·ع¾ط·آ¸ط·آ§ط·آ±') },
  { value: 'confirmed', label: localize('Confirmed', 'ط¸â€¦ط·آ¤ط¸ئ’ط·آ¯') },
  { value: 'active', label: localize('Active', 'ط¸â€ ط·آ´ط·آ·') },
  { value: 'cancelled', label: localize('Cancelled', 'ط¸â€¦ط¸â€‍ط·ط›ط¸ظ¹') },
];

const fuelLevelOptions = [
  { value: '', label: localize('Select fuel level', 'ط·آ§ط·آ®ط·ع¾ط·آ± ط¸â€¦ط·آ³ط·ع¾ط¸ث†ط¸â€° ط·آ§ط¸â€‍ط¸ث†ط¸â€ڑط¸ث†ط·آ¯') },
  { value: 'empty', label: localize('Empty', 'ط¸ظ¾ط·آ§ط·آ±ط·ط›') },
  { value: 'quarter', label: localize('1/4 Tank', 'ط·آ±ط·آ¨ط·آ¹ ط·ع¾ط·آ§ط¸â€ ط¸ئ’ط¸ظ¹') },
  { value: 'half', label: localize('1/2 Tank', 'ط¸â€ ط·آµط¸ظ¾ ط·ع¾ط·آ§ط¸â€ ط¸ئ’ط¸ظ¹') },
  { value: 'three_quarters', label: localize('3/4 Tank', 'ط·آ«ط¸â€‍ط·آ§ط·آ«ط·آ© ط·آ£ط·آ±ط·آ¨ط·آ§ط·آ¹ ط·آ§ط¸â€‍ط·ع¾ط·آ§ط¸â€ ط¸ئ’ط¸ظ¹') },
  { value: 'full', label: localize('Full', 'ط¸ظ¾ط¸â€‍') },
];

const vehicleConditionOptions = [
  { value: '', label: localize('Select condition', 'اختر الحالة') },
  { value: 'clean', label: localize('Clean', 'نظيف') },
  { value: 'not_clean', label: localize('Not Clean', 'غير نظيف') },
];

const photoAllowedFileTypes = [
  'image/jpeg',
  'image/png',
];

const documentAllowedFileTypes = [
  'application/pdf',
  'image/jpeg',
  'image/png',
  'image/jpg',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];

const availableReservations = ref(Array.isArray(props.reservationOptions) ? [...props.reservationOptions] : []);
const showReservationModal = ref(false);
const reservationSubmitting = ref(false);
const reservationSearch = ref('');
const reservationMenuOpen = ref(false);
const manualSnapshot = ref<null | {
  car_data: {
    car_id: any;
    car_details: string;
    plate_number: string;
    vehicle_odometer: any;
    vehicle_fuel_level: string;
    price_per_day: any;
    price_per_week: any;
    price_per_month: any;
    allowed_km_per_day: any;
    allowed_km_per_week: any;
    allowed_km_per_month: any;
    branch_id: any;
  };
  rental_data: { start_date: string; end_date: string; total_amount: any };
}>(null);

function documentSlot(side: 'front' | 'back', docs: any[] = [], type = '') {
  const existing = docs.filter((doc) => side === 'front'
    ? doc?.side === 'front' || doc?.side === 'single'
    : doc?.side === 'back');

  return {
    document_type: type || String(existing[0]?.document_type || ''),
    side,
    temp_folders: [],
    removed_file_ids: [],
    existing_files: existing
      .filter((doc) => doc?.id && doc?.url)
      .map((doc) => ({ id: Number(doc.id), url: String(doc.url) })),
  };
}

function buildDriver(driver: any, role: 'primary' | 'additional') {
  const payload = driver || {};
  const docs = Array.isArray(payload.documents) ? payload.documents : [];
  const type = String(payload.document_type || '');

  return {
    id: payload.id ?? null,
    client_id: payload.client_id ?? null,
    role,
    full_name: String(payload.full_name || ''),
    full_name_ar: String(payload.full_name_ar || ''),
    phone: String(payload.phone || ''),
    nationality: String(payload.nationality || ''),
    place_of_issue: String(payload.place_of_issue || ''),
    date_of_birth: String(payload.date_of_birth || ''),
    identity_number: String(payload.identity_number || ''),
    passport_number: String(payload.passport_number || ''),
    passport_expiry_date: String(payload.passport_expiry_date || ''),
    visa_number: String(payload.visa_number || ''),
    visa_expiry_date: String(payload.visa_expiry_date || ''),
    residency_number: String(payload.residency_number || ''),
    license_number: String(payload.license_number || ''),
    license_issue_date: String(payload.license_issue_date || ''),
    identity_expiry_date: String(payload.identity_expiry_date || ''),
    license_expiry_date: String(payload.license_expiry_date || ''),
    extraction_status: String(payload.extraction_status || 'not_requested'),
    extracted_data: payload.extracted_data || null,
    raw_output: payload.raw_output || null,
    confidence: payload.confidence ?? null,
    ai_reviewed: Boolean(payload.ai_reviewed || false),
    ai_review_required: Boolean(payload.extracted_data || payload.extraction_status === 'extracted'),
    notes: String(payload.notes || ''),
    document_type: type,
    documents: [documentSlot('front', docs, type), documentSlot('back', docs, type)],
    customer_photo: payload.customer_photo || null,
    customer_photo_existing_files: Array.isArray(payload.customer_photo_files) ? payload.customer_photo_files : [],
    customer_photo_temp_folders: Array.isArray(payload.customer_photo_temp_folders) ? payload.customer_photo_temp_folders : [],
    customer_photo_removed_file_ids: Array.isArray(payload.customer_photo_removed_file_ids) ? payload.customer_photo_removed_file_ids : [],
    customer_photo_preview_url: String(payload.customer_photo?.url || ''),
    extracting: false,
    extract_error: '',
    extract_success: '',
    photo_extracting: false,
    photo_extract_error: '',
    photo_extract_success: '',
  };
}

function buildAdditionalArchiveItem(entry: any = null) {
  const payload = entry || {};

  return {
    id: payload.id ?? null,
    owner_key: String(payload.owner_key || ''),
    document_type: String(payload.document_type || ''),
    title: String(payload.title || ''),
    notes: String(payload.notes || ''),
    temp_folders: [],
    removed_file_ids: [],
    existing_files: Array.isArray(payload.existing_files)
      ? payload.existing_files
      : [],
  };
}

function syncDocumentType(driver: any) {
  driver.documents.forEach((doc: any) => {
    doc.document_type = driver.document_type;
  });
  driver.extract_error = '';
  driver.extract_success = '';
}

function onDriverFileRemoved(driver: any, index: number, data: { type: string; fileId?: number }) {
  if (data.type !== 'existing' || !data.fileId) return;
  driver.documents[index].removed_file_ids = [...driver.documents[index].removed_file_ids, data.fileId];
  driver.extract_error = '';
  driver.extract_success = '';
}

function onDriverCustomerPhotoRemoved(driver: any, data: { type: string; fileId?: number }) {
  if (data.type !== 'existing' || !data.fileId) {
    driver.customer_photo_preview_url = '';
    return;
  }

  driver.customer_photo_removed_file_ids = [...driver.customer_photo_removed_file_ids, data.fileId];
  driver.customer_photo_existing_files = (driver.customer_photo_existing_files || []).filter((file: any) => Number(file.id) !== Number(data.fileId));
  driver.customer_photo_preview_url = '';
  driver.photo_extract_error = '';
  driver.photo_extract_success = '';
}

function addAdditionalDriver() {
  form.additional_drivers.push(buildDriver(null, 'additional'));
}

function removeAdditionalDriver(index: number) {
  form.additional_drivers.splice(index, 1);
}

function onArchiveFileRemoved(type: 'start' | 'end', data: { type: string; fileId?: number }) {
  if (data.type !== 'existing' || !data.fileId) return;
  if (type === 'start') {
    form.start_contract_removed_files = [...form.start_contract_removed_files, data.fileId];
    return;
  }
  form.end_contract_removed_files = [...form.end_contract_removed_files, data.fileId];
}

function addAdditionalArchiveItem() {
  form.additional_archive.push(buildAdditionalArchiveItem());
}

function removeAdditionalArchiveItem(index: number) {
  const item = form.additional_archive[index];
  if (item?.id) {
    form.additional_archive_removed_ids = [...form.additional_archive_removed_ids, Number(item.id)];
  }
  form.additional_archive.splice(index, 1);
}

function onAdditionalArchiveFileRemoved(index: number, data: { type: string; fileId?: number }) {
  if (data.type !== 'existing' || !data.fileId) return;
  const item = form.additional_archive[index];
  if (!item) return;
  item.removed_file_ids = [...item.removed_file_ids, data.fileId];
  item.existing_files = (item.existing_files || []).filter((file: any) => Number(file.id) !== Number(data.fileId));
}

const form = useForm({
  reservation_id: props.contract?.reservation_id ?? '',
  contract_number: props.contract?.contract_number ?? '',
  status: props.contract?.status ?? 'draft',
  contract_date: props.contract?.contract_date ?? '',
  renter_name: props.contract?.renter_name ?? '',
  renter_id_number: props.contract?.renter_id_number ?? '',
  renter_phone: props.contract?.renter_phone ?? '',
  start_date: props.contract?.start_date ?? '',
  end_date: props.contract?.end_date ?? '',
  total_amount: props.contract?.total_amount ?? '',
  currency: props.contract?.currency ?? 'USD',
  notes: props.contract?.notes ?? '',
  return_odometer: props.contract?.return_odometer ?? '',
  return_fuel_level: props.contract?.return_fuel_level ?? '',
  vehicle_condition_before: props.contract?.vehicle_condition_before ?? '',
  vehicle_condition_after: props.contract?.vehicle_condition_after ?? '',
  actual_return_time: props.contract?.actual_return_time ?? '',
  ai_extracted_data: props.contract?.ai_extracted_data ?? null,
  car_data: {
    car_id: props.carData?.car_id ?? props.contract?.car_data?.car_id ?? '',
    car_details: props.carData?.car_details ?? props.contract?.car_data?.car_details ?? props.contract?.car_details ?? '',
    plate_number: props.carData?.plate_number ?? props.contract?.car_data?.plate_number ?? props.contract?.plate_number ?? '',
    vehicle_odometer: props.carData?.vehicle_odometer ?? props.contract?.car_data?.vehicle_odometer ?? props.contract?.vehicle_odometer ?? '',
    vehicle_fuel_level: props.carData?.vehicle_fuel_level ?? props.contract?.car_data?.vehicle_fuel_level ?? props.contract?.vehicle_fuel_level ?? '',
    price_per_day: props.carData?.price_per_day ?? props.contract?.car_data?.price_per_day ?? props.contract?.price_per_day ?? '',
    price_per_week: props.carData?.price_per_week ?? props.contract?.car_data?.price_per_week ?? props.contract?.price_per_week ?? '',
    price_per_month: props.carData?.price_per_month ?? props.contract?.car_data?.price_per_month ?? props.contract?.price_per_month ?? '',
    allowed_km_per_day: props.carData?.allowed_km_per_day ?? props.contract?.car_data?.allowed_km_per_day ?? props.contract?.allowed_km_per_day ?? '',
    allowed_km_per_week: props.carData?.allowed_km_per_week ?? props.contract?.car_data?.allowed_km_per_week ?? props.contract?.allowed_km_per_week ?? '',
    allowed_km_per_month: props.carData?.allowed_km_per_month ?? props.contract?.car_data?.allowed_km_per_month ?? props.contract?.allowed_km_per_month ?? '',
    branch_id: props.carData?.branch_id ?? props.contract?.car_data?.branch_id ?? '',
  },
  primary_driver: buildDriver(props.primaryDriver ?? props.contract?.primary_driver ?? {
    full_name: props.contract?.renter_name ?? '',
    identity_number: props.contract?.renter_id_number ?? '',
    phone: props.contract?.renter_phone ?? '',
  }, 'primary'),
  additional_drivers: Array.isArray(props.additionalDrivers)
    ? props.additionalDrivers.map((driver) => buildDriver(driver, 'additional'))
    : Array.isArray(props.contract?.additional_drivers)
      ? props.contract.additional_drivers.map((driver: any) => buildDriver(driver, 'additional'))
      : [],
  contract_archive: {
    temp_folders: [],
    removed_file_ids: [],
  },
  additional_archive: Array.isArray(props.additionalArchive)
    ? props.additionalArchive.map((item) => buildAdditionalArchiveItem(item))
    : [],
  additional_archive_removed_ids: [],
  start_contract_temp_folders: [],
  start_contract_removed_files: [],
  end_contract_temp_folders: [],
  end_contract_removed_files: [],
});

const reservationForm = useForm({
  user_id: '',
  car_id: '',
  start_date: '',
  end_date: '',
  pickup_time: '09:00',
  return_time: '18:00',
  pickup_location: '',
  return_location: '',
  discount_amount: 0,
  notes: '',
  status: 'confirmed',
  cancellation_reason: '',
});

syncDocumentType(form.primary_driver);
form.additional_drivers.forEach(syncDocumentType);

const selectedReservation = computed(() => {
  if (!form.reservation_id) return null;
  const selectedId = Number(form.reservation_id);
  return availableReservations.value.find((reservation) => Number(reservation.id) === selectedId) || null;
});
const filteredReservationsBySearch = computed(() => {
  const term = reservationSearch.value.trim().toLowerCase();

  if (!term) {
    return availableReservations.value;
  }

  return availableReservations.value.filter((reservation) =>
    [
      reservation.label,
      reservation.reservation_number,
      reservation.user_name,
      reservation.car_details,
      reservation.plate_number,
    ]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(term)),
  );
});
const selectedCarId = computed(() => Number(selectedReservation.value?.car_id || form.car_data.car_id || 0));
const selectedCarDamages = computed(() => {
  if (!selectedCarId.value) {
    return Array.isArray(props.currentCarDamages) ? props.currentCarDamages : [];
  }

  return props.carDamagesByCar?.[selectedCarId.value] || [];
});
const hasLinkedReservation = computed(() => Boolean(selectedReservation.value));
const currentContractReservationId = computed(() => Number(props.contract?.reservation_id || 0));
const selectedReservationIsCurrentContract = computed(() => {
  if (!selectedReservation.value) return false;
  return Number(selectedReservation.value.id || 0) === currentContractReservationId.value;
});
const selectedReservationUsesAnotherContract = computed(() => {
  if (!selectedReservation.value) return false;
  return Boolean(selectedReservation.value.has_contract) && !selectedReservationIsCurrentContract.value;
});
const selectedReservationNotice = computed(() => {
  if (!selectedReservation.value) {
    return null;
  }

  const status = String(selectedReservation.value.status || '').toLowerCase();

  if (selectedReservationUsesAnotherContract.value) {
    return {
      tone: 'danger',
      title: localize('Already linked to another contract', 'هذه الحجز مرتبط بعقد آخر'),
      message: localize(
        'This reservation already has a contract and cannot be used for a new contract.',
        'هذا الحجز مرتبط بعقد آخر ولا يمكن استخدامه لإنشاء عقد جديد.',
      ),
    };
  }

  if (status === 'completed_wait_contract') {
    return {
      tone: 'warning',
      title: localize('Completed - Waiting for Contract', 'مكتمل - بانتظار العقد'),
      message: localize(
        'This reservation is completed and waiting for contract creation. You can save the contract from here.',
        'هذا الحجز مكتمل وينتظر إنشاء العقد. يمكنك حفظ العقد من هنا.',
      ),
    };
  }

  return {
    tone: 'info',
    title: selectedReservation.value.status_label || localize('Reservation selected', 'تم اختيار الحجز'),
    message: localize(
      'This reservation can be used for the contract.',
      'يمكن استخدام هذا الحجز لإنشاء العقد.',
    ),
  };
});
const reservationClients = computed(() => props.reservationFormOptions?.clients ?? []);
const reservationCars = computed(() => props.reservationFormOptions?.cars ?? []);
const saveError = ref('');
const additionalArchiveOwnerOptions = computed(() => {
  const options = [
    { value: '', label: localize('No specific driver', 'ط·آ¨ط·آ¯ط¸ث†ط¸â€  ط·آ³ط·آ§ط·آ¦ط¸â€ڑ ط¸â€¦ط·آ­ط·آ¯ط·آ¯') },
    { value: 'primary', label: form.primary_driver.full_name ? localize(`Primary Driver - ${form.primary_driver.full_name}`, `ط·آ§ط¸â€‍ط·آ³ط·آ§ط·آ¦ط¸â€ڑ ط·آ§ط¸â€‍ط·آ£ط·آ³ط·آ§ط·آ³ط¸ظ¹ - ${form.primary_driver.full_name}`) : localize('Primary Driver', 'ط·آ§ط¸â€‍ط·آ³ط·آ§ط·آ¦ط¸â€ڑ ط·آ§ط¸â€‍ط·آ£ط·آ³ط·آ§ط·آ³ط¸ظ¹') },
  ];

  form.additional_drivers.forEach((driver: any, index: number) => {
    options.push({
      value: `additional_${index}`,
      label: driver.full_name ? localize(`Additional Driver ${index + 1} - ${driver.full_name}`, `ط·آ§ط¸â€‍ط·آ³ط·آ§ط·آ¦ط¸â€ڑ ط·آ§ط¸â€‍ط·آ¥ط·آ¶ط·آ§ط¸ظ¾ط¸ظ¹ ${index + 1} - ${driver.full_name}`) : localize(`Additional Driver ${index + 1}`, `ط·آ§ط¸â€‍ط·آ³ط·آ§ط·آ¦ط¸â€ڑ ط·آ§ط¸â€‍ط·آ¥ط·آ¶ط·آ§ط¸ظ¾ط¸ظ¹ ${index + 1}`),
    });
  });

  return options;
});

function snapshotManualState() {
  manualSnapshot.value = {
    car_data: {
      car_id: form.car_data.car_id,
      car_details: form.car_data.car_details,
      plate_number: form.car_data.plate_number,
      vehicle_odometer: form.car_data.vehicle_odometer,
      vehicle_fuel_level: form.car_data.vehicle_fuel_level,
      price_per_day: form.car_data.price_per_day,
      price_per_week: form.car_data.price_per_week,
      price_per_month: form.car_data.price_per_month,
      allowed_km_per_day: form.car_data.allowed_km_per_day,
      allowed_km_per_week: form.car_data.allowed_km_per_week,
      allowed_km_per_month: form.car_data.allowed_km_per_month,
      branch_id: form.car_data.branch_id,
    },
    rental_data: {
      start_date: form.start_date,
      end_date: form.end_date,
      total_amount: form.total_amount,
    },
  };
}

function applyReservationData(reservation: Record<string, any>) {
  form.car_data.car_id = reservation.car_id ?? '';
  form.car_data.car_details = reservation.car_details ?? reservation.car ?? '';
  form.car_data.plate_number = reservation.plate_number ?? '';
  form.car_data.vehicle_odometer = reservation.vehicle_odometer ?? '';
  form.car_data.price_per_day = reservation.price_per_day ?? '';
  form.car_data.price_per_week = reservation.price_per_week ?? '';
  form.car_data.price_per_month = reservation.price_per_month ?? '';
  form.car_data.allowed_km_per_day = reservation.allowed_km_per_day ?? '';
  form.car_data.allowed_km_per_week = reservation.allowed_km_per_week ?? '';
  form.car_data.allowed_km_per_month = reservation.allowed_km_per_month ?? '';
  form.car_data.branch_id = reservation.branch_id ?? '';
  form.start_date = reservation.start_date ?? '';
  form.end_date = reservation.end_date ?? '';
  form.total_amount = reservation.total_amount ?? '';
}

function restoreManualState() {
  if (!manualSnapshot.value) return;
  form.car_data.car_id = manualSnapshot.value.car_data.car_id;
  form.car_data.car_details = manualSnapshot.value.car_data.car_details;
  form.car_data.plate_number = manualSnapshot.value.car_data.plate_number;
  form.car_data.vehicle_odometer = manualSnapshot.value.car_data.vehicle_odometer;
  form.car_data.vehicle_fuel_level = manualSnapshot.value.car_data.vehicle_fuel_level;
  form.car_data.price_per_day = manualSnapshot.value.car_data.price_per_day;
  form.car_data.price_per_week = manualSnapshot.value.car_data.price_per_week;
  form.car_data.price_per_month = manualSnapshot.value.car_data.price_per_month;
  form.car_data.allowed_km_per_day = manualSnapshot.value.car_data.allowed_km_per_day;
  form.car_data.allowed_km_per_week = manualSnapshot.value.car_data.allowed_km_per_week;
  form.car_data.allowed_km_per_month = manualSnapshot.value.car_data.allowed_km_per_month;
  form.car_data.branch_id = manualSnapshot.value.car_data.branch_id;
  form.start_date = manualSnapshot.value.rental_data.start_date;
  form.end_date = manualSnapshot.value.rental_data.end_date;
  form.total_amount = manualSnapshot.value.rental_data.total_amount;
}

watch(
  () => form.reservation_id,
  (newValue, oldValue) => {
    const newReservation = availableReservations.value.find((reservation) => Number(reservation.id) === Number(newValue));
    const oldReservation = availableReservations.value.find((reservation) => Number(reservation.id) === Number(oldValue));

    if (newReservation) {
      reservationSearch.value = newReservation.label;
      if (!oldReservation) {
        snapshotManualState();
      }
      applyReservationData(newReservation);
      return;
    }

    if (!reservationMenuOpen.value) {
      reservationSearch.value = '';
    }

    if (oldReservation && !newReservation) {
      restoreManualState();
    }
  },
  { immediate: true },
);

function selectReservation(reservation: Record<string, any>) {
  if (reservation.has_contract && Number(reservation.id) !== Number(form.reservation_id)) {
    return;
  }

  form.reservation_id = String(reservation.id);
  reservationSearch.value = reservation.label;
  reservationMenuOpen.value = false;
}

function clearReservation() {
  form.reservation_id = '';
  reservationSearch.value = '';
  reservationMenuOpen.value = false;
}

function handleReservationBlur() {
  window.setTimeout(() => {
    reservationMenuOpen.value = false;

    if (!selectedReservation.value) {
      reservationSearch.value = '';
    }
  }, 150);
}

function driverTempFolders(driver: any): string[] {
  return driver.documents.flatMap((doc: any) => Array.isArray(doc.temp_folders) ? doc.temp_folders : []);
}

function applyExtractedFields(driver: any, fields: Record<string, any>) {
  const allowedKeys = [
    'full_name',
    'full_name_ar',
    'nationality',
    'place_of_issue',
    'date_of_birth',
    'identity_number',
    'residency_number',
    'license_number',
    'identity_expiry_date',
    'license_expiry_date',
  ];

  allowedKeys.forEach((key) => {
    const value = fields[key];
    if (value === null || value === undefined || value === '') return;
    driver[key] = String(value);
  });
}

function hasAiExtractedData(driver: any) {
  return Boolean(driver?.extracted_data && Object.keys(driver.extracted_data).length > 0)
    || driver?.extraction_status === 'extracted';
}

async function extractDriver(driver: any, role: 'primary' | 'additional', index: number | null = null) {
  driver.extract_error = '';
  driver.extract_success = '';

  if (!props.actions.extractDriver) {
    driver.extract_error = localize('Driver extraction endpoint is not configured.', 'ط¸â€‍ط¸â€¦ ط¸ظ¹ط·ع¾ط¸â€¦ ط·آ¥ط·آ¹ط·آ¯ط·آ§ط·آ¯ ط¸â€¦ط·آ³ط·آ§ط·آ± ط·آ§ط·آ³ط·ع¾ط·آ®ط·آ±ط·آ§ط·آ¬ ط·آ¨ط¸ظ¹ط·آ§ط¸â€ ط·آ§ط·ع¾ ط·آ§ط¸â€‍ط·آ³ط·آ§ط·آ¦ط¸â€ڑ.');
    return;
  }

  if (!driver.document_type) {
    driver.extract_error = localize('Select a document type first.', 'ط·آ§ط·آ®ط·ع¾ط·آ± ط¸â€ ط¸ث†ط·آ¹ ط·آ§ط¸â€‍ط¸â€¦ط·آ³ط·ع¾ط¸â€ ط·آ¯ ط·آ£ط¸ث†ط¸â€‍ط¸â€¹ط·آ§.');
    return;
  }

  const tempFolders = driverTempFolders(driver);
  if (tempFolders.length === 0) {
    driver.extract_error = localize('Upload at least one document image before extraction.', 'ط·آ§ط·آ±ط¸ظ¾ط·آ¹ ط·آµط¸ث†ط·آ±ط·آ© ط¸â€¦ط·آ³ط·ع¾ط¸â€ ط·آ¯ ط¸ث†ط·آ§ط·آ­ط·آ¯ط·آ© ط·آ¹ط¸â€‍ط¸â€° ط·آ§ط¸â€‍ط·آ£ط¸â€ڑط¸â€‍ ط¸â€ڑط·آ¨ط¸â€‍ ط·آ§ط¸â€‍ط·آ§ط·آ³ط·ع¾ط·آ®ط·آ±ط·آ§ط·آ¬.');
    return;
  }

  driver.extracting = true;

  try {
    const response = await fetch(props.actions.extractDriver, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content || '',
      },
      body: JSON.stringify({
        driver_role: role,
        driver_index: index,
        document_type: driver.document_type,
        temp_folders: tempFolders,
      }),
    });

    const payload = await response.json();

    if (!response.ok) {
      driver.extract_error = payload.message || localize('Driver extraction failed.', 'ط¸ظ¾ط·آ´ط¸â€‍ ط·آ§ط·آ³ط·ع¾ط·آ®ط·آ±ط·آ§ط·آ¬ ط·آ¨ط¸ظ¹ط·آ§ط¸â€ ط·آ§ط·ع¾ ط·آ§ط¸â€‍ط·آ³ط·آ§ط·آ¦ط¸â€ڑ.');
      driver.extraction_status = 'failed';
      return;
    }

    const fields = payload.fields && typeof payload.fields === 'object' ? payload.fields : {};
    applyExtractedFields(driver, fields);
    driver.extracted_data = fields;
    driver.raw_output = payload.raw_output || null;
    driver.confidence = typeof payload.confidence === 'number' ? payload.confidence : null;
    driver.extraction_status = payload.status || 'extracted';
    driver.ai_reviewed = false;
    driver.ai_review_required = true;
    driver.extract_success = payload.message || localize('Document extraction completed.', 'ط·ع¾ط¸â€¦ ط·آ§ط·آ³ط·ع¾ط·آ®ط·آ±ط·آ§ط·آ¬ ط·آ¨ط¸ظ¹ط·آ§ط¸â€ ط·آ§ط·ع¾ ط·آ§ط¸â€‍ط¸â€¦ط·آ³ط·ع¾ط¸â€ ط·آ¯ ط·آ¨ط¸â€ ط·آ¬ط·آ§ط·آ­.');
  } catch (error) {
    driver.extract_error = error instanceof Error ? error.message : localize('Driver extraction failed.', 'ط¸ظ¾ط·آ´ط¸â€‍ ط·آ§ط·آ³ط·ع¾ط·آ®ط·آ±ط·آ§ط·آ¬ ط·آ¨ط¸ظ¹ط·آ§ط¸â€ ط·آ§ط·ع¾ ط·آ§ط¸â€‍ط·آ³ط·آ§ط·آ¦ط¸â€ڑ.');
    driver.extraction_status = 'failed';
  } finally {
    driver.extracting = false;
  }
}

async function extractCustomerPhoto(driver: any) {
  driver.photo_extract_error = '';
  driver.photo_extract_success = '';

  if (!props.actions.extractCustomerPhoto) {
    driver.photo_extract_error = localize('Customer photo extraction endpoint is not configured.', 'ط¸â€‍ط¸â€¦ ط¸ظ¹ط·ع¾ط¸â€¦ ط·آ¥ط·آ¹ط·آ¯ط·آ§ط·آ¯ ط¸â€¦ط·آ³ط·آ§ط·آ± ط·آ§ط·آ³ط·ع¾ط·آ®ط·آ±ط·آ§ط·آ¬ ط·آµط¸ث†ط·آ±ط·آ© ط·آ§ط¸â€‍ط·آ¹ط¸â€¦ط¸ظ¹ط¸â€‍.');
    return;
  }

  if (!driver.document_type) {
    driver.photo_extract_error = localize('Select a document type first.', 'ط·آ§ط·آ®ط·ع¾ط·آ± ط¸â€ ط¸ث†ط·آ¹ ط·آ§ط¸â€‍ط¸â€¦ط·آ³ط·ع¾ط¸â€ ط·آ¯ ط·آ£ط¸ث†ط¸â€‍ط¸â€¹ط·آ§.');
    return;
  }

  const tempFolders = Array.isArray(driver.documents?.[0]?.temp_folders)
    ? driver.documents[0].temp_folders.filter(Boolean)
    : [];

  if (tempFolders.length === 0) {
    driver.photo_extract_error = localize('Upload a front or single document image before extracting the customer photo.', 'ط·آ§ط·آ±ط¸ظ¾ط·آ¹ ط·آµط¸ث†ط·آ±ط·آ© ط·آ§ط¸â€‍ط¸ث†ط·آ§ط·آ¬ط¸â€،ط·آ© ط·آ£ط¸ث† ط·آ§ط¸â€‍ط·آµط¸ث†ط·آ±ط·آ© ط·آ§ط¸â€‍ط¸â€¦ط¸ظ¾ط·آ±ط·آ¯ط·آ© ط¸â€ڑط·آ¨ط¸â€‍ ط·آ§ط·آ³ط·ع¾ط·آ®ط·آ±ط·آ§ط·آ¬ ط·آµط¸ث†ط·آ±ط·آ© ط·آ§ط¸â€‍ط·آ¹ط¸â€¦ط¸ظ¹ط¸â€‍.');
    return;
  }

  driver.photo_extracting = true;

  try {
    const response = await fetch(props.actions.extractCustomerPhoto, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content || '',
      },
      body: JSON.stringify({
        document_type: driver.document_type,
        temp_folders: tempFolders,
      }),
    });

    const payload = await response.json();

    if (!response.ok) {
      driver.photo_extract_error = payload.message || localize('Customer photo extraction failed.', 'فشل استخراج صورة العميل.');
      return;
    }

    driver.customer_photo_temp_folders = payload.folder ? [payload.folder] : [];
    driver.customer_photo_removed_file_ids = [];
    driver.customer_photo_existing_files = [];
    driver.customer_photo_preview_url = String(payload.url || '');
    driver.photo_extract_success = payload.message || 'Customer photo extracted successfully.';
  } catch (error) {
    driver.photo_extract_error = error instanceof Error ? error.message : localize('Customer photo extraction failed.', 'فشل استخراج صورة العميل.');
  } finally {
    driver.photo_extracting = false;
  }
}

function resetReservationModal() {
  reservationForm.reset();
  reservationForm.clearErrors();
  reservationForm.pickup_time = '09:00';
  reservationForm.return_time = '18:00';
  reservationForm.discount_amount = 0;
  reservationForm.status = 'confirmed';
  reservationForm.cancellation_reason = '';
}

async function submitReservationFromModal() {
  reservationForm.clearErrors();

  if (!props.actions.reservationStore) {
    reservationForm.setError('user_id', localize('Reservation store route is not configured.', 'مسار حفظ الحجز غير مهيأ.'));
    return;
  }

  reservationSubmitting.value = true;

  try {
    const response = await fetch(props.actions.reservationStore, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content || '',
      },
      body: JSON.stringify({
        user_id: reservationForm.user_id,
        car_id: reservationForm.car_id,
        start_date: reservationForm.start_date,
        end_date: reservationForm.end_date,
        pickup_time: reservationForm.pickup_time,
        return_time: reservationForm.return_time,
        pickup_location: reservationForm.pickup_location,
        return_location: reservationForm.return_location,
        discount_amount: reservationForm.discount_amount,
        notes: reservationForm.notes,
        status: reservationForm.status,
        cancellation_reason: reservationForm.cancellation_reason,
      }),
    });

    const payload = await response.json();

    if (!response.ok) {
      if (payload.errors && typeof payload.errors === 'object') {
        Object.entries(payload.errors).forEach(([key, value]) => {
          reservationForm.setError(key as any, Array.isArray(value) ? String(value[0]) : String(value));
        });
      } else {
        reservationForm.setError('user_id', payload.message || localize('Reservation creation failed.', 'فشل إنشاء الحجز.'));
      }
      return;
    }

    if (payload.reservation) {
      availableReservations.value = [payload.reservation, ...availableReservations.value.filter((item) => Number(item.id) !== Number(payload.reservation.id))];
      form.reservation_id = payload.reservation.id;
    }

    showReservationModal.value = false;
    resetReservationModal();
  } catch (error) {
    reservationForm.setError('user_id', error instanceof Error ? error.message : localize('Reservation creation failed.', 'فشل إنشاء الحجز.'));
  } finally {
    reservationSubmitting.value = false;
  }
}

function submit() {
  if (isLocked.value) {
    return;
  }

  saveError.value = '';
  form.renter_name = String(form.primary_driver.full_name || form.renter_name || '').trim();
  form.renter_id_number = String(form.primary_driver.identity_number || form.renter_id_number || '').trim();
  form.renter_phone = String(form.primary_driver.phone || form.renter_phone || '').trim();
  const submitOptions = {
    preserveScroll: true,
    onError: (errors: Record<string, string | undefined>) => {
      const firstError = Object.values(errors).find((message) => Boolean(message));
      saveError.value = firstError ? String(firstError) : localize('Contract save failed. Please review the errors.', 'فشل حفظ العقد. يرجى مراجعة الأخطاء.');
    },
  };

  if (props.mode === 'create') {
    form.post(props.actions.store || '/admin/contracts', submitOptions);
    return;
  }
  if (!props.actions.update) {
    return;
  }

  form.put(props.actions.update, submitOptions);
}
</script>

<template>
  <Head :title="mode === 'create' ? localize('Create Contract', 'ط·آ¥ط¸â€ ط·آ´ط·آ§ط·طŒ ط·آ¹ط¸â€ڑط·آ¯') : localize('Edit Contract', 'ط·ع¾ط·آ¹ط·آ¯ط¸ظ¹ط¸â€‍ ط·آ§ط¸â€‍ط·آ¹ط¸â€ڑط·آ¯')" />
  <AdminLayout>
    <main class="flex-1 space-y-6 p-8">
      <div class="flex items-start justify-between gap-4">
        <div>
          <h1 class="text-2xl font-semibold">{{ mode === 'create' ? localize('Create Contract', 'ط·آ¥ط¸â€ ط·آ´ط·آ§ط·طŒ ط·آ¹ط¸â€ڑط·آ¯') : localize('Edit Contract', 'ط·ع¾ط·آ¹ط·آ¯ط¸ظ¹ط¸â€‍ ط·آ§ط¸â€‍ط·آ¹ط¸â€ڑط·آ¯') }}</h1>
          <p class="text-sm text-muted-foreground">{{ localize('Primary driver, additional drivers, car data, rental data, and archive.', 'السائق الأساسي، والسائقون الإضافيون، وبيانات السيارة، وبيانات الإيجار، والأرشيف.') }}</p>
        </div>
        <Link :href="actions.index"><Button variant="outline">{{ localize('Back', 'رجوع') }}</Button></Link>
      </div>

      <div v-if="isLocked" class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
        {{ localize('This contract is locked because the return report is marked paid.', 'هذا العقد مقفل لأن تقرير العودة عليه حالة مدفوعة.') }}
      </div>

      <form class="space-y-6" @submit.prevent="submit">
        <section class="space-y-4 rounded-lg border bg-white p-5 shadow-sm">
          <div>
            <h2 class="text-lg font-semibold">{{ localize('Customer Data', 'ط·آ¨ط¸ظ¹ط·آ§ط¸â€ ط·آ§ط·ع¾ ط·آ§ط¸â€‍ط·آ¹ط¸â€¦ط¸ظ¹ط¸â€‍') }}</h2>
            <p class="text-sm text-muted-foreground">{{ localize('Primary driver details and document uploads.', 'ط·آ¨ط¸ظ¹ط·آ§ط¸â€ ط·آ§ط·ع¾ ط·آ§ط¸â€‍ط·آ³ط·آ§ط·آ¦ط¸â€ڑ ط·آ§ط¸â€‍ط·آ£ط·آ³ط·آ§ط·آ³ط¸ظ¹ ط¸ث†ط¸â€¦ط¸â€‍ط¸ظ¾ط·آ§ط·ع¾ ط·آ§ط¸â€‍ط¸â€¦ط·آ³ط·ع¾ط¸â€ ط·آ¯ط·آ§ط·ع¾ ط·آ§ط¸â€‍ط¸â€¦ط·آ±ط¸ظ¾ط¸ث†ط·آ¹ط·آ©.') }}</p>
          </div>
          <div v-if="hasAiExtractedData(form.primary_driver)" class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
            {{ localize('Review the extracted AI data carefully before saving this contract.', 'ط·آ±ط·آ§ط·آ¬ط·آ¹ ط·آ§ط¸â€‍ط·آ¨ط¸ظ¹ط·آ§ط¸â€ ط·آ§ط·ع¾ ط·آ§ط¸â€‍ط¸â€¦ط·آ³ط·ع¾ط·آ®ط·آ±ط·آ¬ط·آ© ط·آ¨ط·آ§ط¸â€‍ط·آ°ط¸ئ’ط·آ§ط·طŒ ط·آ§ط¸â€‍ط·آ§ط·آµط·آ·ط¸â€ ط·آ§ط·آ¹ط¸ظ¹ ط·آ¨ط·آ¹ط¸â€ ط·آ§ط¸ظ¹ط·آ© ط¸â€ڑط·آ¨ط¸â€‍ ط·آ­ط¸ظ¾ط·آ¸ ط¸â€،ط·آ°ط·آ§ ط·آ§ط¸â€‍ط·آ¹ط¸â€ڑط·آ¯.') }}
          </div>
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div>
              <Label for="primary-document-type">{{ localize('Document Type', 'ط¸â€ ط¸ث†ط·آ¹ ط·آ§ط¸â€‍ط¸â€¦ط·آ³ط·ع¾ط¸â€ ط·آ¯') }}</Label>
              <select id="primary-document-type" v-model="form.primary_driver.document_type" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2" @change="syncDocumentType(form.primary_driver)">
                <option v-for="option in documentTypeOptions" :key="option.value || 'empty'" :value="option.value">{{ option.label }}</option>
              </select>
              <InputError :message="form.errors['primary_driver.document_type']" class="mt-1" />
            </div>
            <div><Label for="primary-full-name">{{ localize('Full Name', 'ط·آ§ط¸â€‍ط·آ§ط·آ³ط¸â€¦ ط·آ§ط¸â€‍ط¸ئ’ط·آ§ط¸â€¦ط¸â€‍') }}</Label><Input id="primary-full-name" v-model="form.primary_driver.full_name" maxlength="255" :required="mode === 'create'" /><InputError :message="form.errors['primary_driver.full_name']" class="mt-1" /></div>
            <div><Label for="primary-full-name-ar">{{ localize('Arabic Name', 'ط·آ§ط¸â€‍ط·آ§ط·آ³ط¸â€¦ ط·آ¨ط·آ§ط¸â€‍ط·آ¹ط·آ±ط·آ¨ط¸ظ¹ط·آ©') }}</Label><Input id="primary-full-name-ar" v-model="form.primary_driver.full_name_ar" dir="rtl" maxlength="255" /><InputError :message="form.errors['primary_driver.full_name_ar']" class="mt-1" /></div>
            <div><Label for="primary-phone">{{ localize('Phone', 'ط·آ§ط¸â€‍ط¸â€،ط·آ§ط·ع¾ط¸ظ¾') }}</Label><Input id="primary-phone" v-model="form.primary_driver.phone" inputmode="tel" maxlength="100" :required="mode === 'create'" /><InputError :message="form.errors['primary_driver.phone']" class="mt-1" /></div>
            <div><Label for="primary-nationality">{{ localize('Nationality', 'ط·آ§ط¸â€‍ط·آ¬ط¸â€ ط·آ³ط¸ظ¹ط·آ©') }}</Label><Input id="primary-nationality" v-model="form.primary_driver.nationality" maxlength="100" /><InputError :message="form.errors['primary_driver.nationality']" class="mt-1" /></div>
            <div><Label for="primary-place-of-issue">{{ localize('Place Of Issue', 'ط¸â€¦ط¸ئ’ط·آ§ط¸â€  ط·آ§ط¸â€‍ط·آ¥ط·آµط·آ¯ط·آ§ط·آ±') }}</Label><Input id="primary-place-of-issue" v-model="form.primary_driver.place_of_issue" maxlength="255" /><InputError :message="form.errors['primary_driver.place_of_issue']" class="mt-1" /></div>
            <div><Label for="primary-birth-date">{{ localize('Date Of Birth', 'ط·ع¾ط·آ§ط·آ±ط¸ظ¹ط·آ® ط·آ§ط¸â€‍ط¸â€¦ط¸ظ¹ط¸â€‍ط·آ§ط·آ¯') }}</Label><Input id="primary-birth-date" v-model="form.primary_driver.date_of_birth" type="date" :max="contractDateMin" /><InputError :message="form.errors['primary_driver.date_of_birth']" class="mt-1" /></div>
            <div><Label for="primary-identity-number">{{ localize('Identity Number', 'ط·آ±ط¸â€ڑط¸â€¦ ط·آ§ط¸â€‍ط¸â€،ط¸ث†ط¸ظ¹ط·آ©') }}</Label><Input id="primary-identity-number" v-model="form.primary_driver.identity_number" maxlength="255" :required="mode === 'create'" /><InputError :message="form.errors['primary_driver.identity_number']" class="mt-1" /></div>
            <div><Label for="primary-residency-number">{{ localize('Residency Number', 'ط·آ±ط¸â€ڑط¸â€¦ ط·آ§ط¸â€‍ط·آ¥ط¸â€ڑط·آ§ط¸â€¦ط·آ©') }}</Label><Input id="primary-residency-number" v-model="form.primary_driver.residency_number" maxlength="255" /><InputError :message="form.errors['primary_driver.residency_number']" class="mt-1" /></div>
            <div><Label for="primary-license-number">{{ localize('License Number', 'ط·آ±ط¸â€ڑط¸â€¦ ط·آ§ط¸â€‍ط·آ±ط·آ®ط·آµط·آ©') }}</Label><Input id="primary-license-number" v-model="form.primary_driver.license_number" maxlength="255" /><InputError :message="form.errors['primary_driver.license_number']" class="mt-1" /></div>
            <div><Label for="primary-identity-expiry">{{ localize('Identity Expiry Date', 'ط·ع¾ط·آ§ط·آ±ط¸ظ¹ط·آ® ط·آ§ط¸â€ ط·ع¾ط¸â€،ط·آ§ط·طŒ ط·آ§ط¸â€‍ط¸â€،ط¸ث†ط¸ظ¹ط·آ©') }}</Label><Input id="primary-identity-expiry" v-model="form.primary_driver.identity_expiry_date" type="date" :min="contractDateMin" /><InputError :message="form.errors['primary_driver.identity_expiry_date']" class="mt-1" /></div>
            <div><Label for="primary-license-expiry">{{ localize('License Expiry Date', 'ط·ع¾ط·آ§ط·آ±ط¸ظ¹ط·آ® ط·آ§ط¸â€ ط·ع¾ط¸â€،ط·آ§ط·طŒ ط·آ§ط¸â€‍ط·آ±ط·آ®ط·آµط·آ©') }}</Label><Input id="primary-license-expiry" v-model="form.primary_driver.license_expiry_date" type="date" :min="contractDateMin" /><InputError :message="form.errors['primary_driver.license_expiry_date']" class="mt-1" /></div>
          </div>
          <div v-if="saveError" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
            {{ saveError }}
          </div>
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div><Label for="primary-passport-number">{{ localize('Passport Number', 'رقم الجواز') }}</Label><Input id="primary-passport-number" v-model="form.primary_driver.passport_number" maxlength="255" /><InputError :message="form.errors['primary_driver.passport_number']" class="mt-1" /></div>
            <div><Label for="primary-passport-expiry">{{ localize('Passport Expiry Date', 'تاريخ انتهاء الجواز') }}</Label><Input id="primary-passport-expiry" v-model="form.primary_driver.passport_expiry_date" type="date" :min="contractDateMin" /><InputError :message="form.errors['primary_driver.passport_expiry_date']" class="mt-1" /></div>
            <div><Label for="primary-license-issue-date">{{ localize('Driving License Issue Date', 'تاريخ إصدار رخصة القيادة') }}</Label><Input id="primary-license-issue-date" v-model="form.primary_driver.license_issue_date" type="date" :max="contractDateMin" /><InputError :message="form.errors['primary_driver.license_issue_date']" class="mt-1" /></div>
          </div>
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div><Label for="primary-visa-number">{{ localize('Visa Number', 'رقم التأشيرة') }}</Label><Input id="primary-visa-number" v-model="form.primary_driver.visa_number" maxlength="255" /><InputError :message="form.errors['primary_driver.visa_number']" class="mt-1" /></div>
            <div><Label for="primary-visa-expiry">{{ localize('Visa Expiry Date', 'تاريخ انتهاء التأشيرة') }}</Label><Input id="primary-visa-expiry" v-model="form.primary_driver.visa_expiry_date" type="date" :min="contractDateMin" /><InputError :message="form.errors['primary_driver.visa_expiry_date']" class="mt-1" /></div>
          </div>
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
              <Label class="mb-2 block">{{ localize('Document Front / Single', 'ط·آ§ط¸â€‍ط¸â€¦ط·آ³ط·ع¾ط¸â€ ط·آ¯ ط·آ§ط¸â€‍ط·آ£ط¸â€¦ط·آ§ط¸â€¦ط¸ظ¹ / ط·آ§ط¸â€‍ط¸â€¦ط¸ظ¾ط·آ±ط·آ¯') }}</Label>
              <FileUpload v-model="form.primary_driver.documents[0].temp_folders" :initial-files="form.primary_driver.documents[0].existing_files" :allowed-file-types="documentAllowedFileTypes" :allow-multiple="false" :max-files="1" collection="contract_driver_front" theme="light" width="100%" @file-removed="(data: { type: string; fileId?: number }) => onDriverFileRemoved(form.primary_driver, 0, data)" />
              <InputError :message="form.errors['primary_driver.documents.0.temp_folders']" class="mt-1" />
            </div>
            <div>
              <Label class="mb-2 block">{{ localize('Document Back', 'ط·آ®ط¸â€‍ط¸ظ¾ط¸ظ¹ط·آ© ط·آ§ط¸â€‍ط¸â€¦ط·آ³ط·ع¾ط¸â€ ط·آ¯') }}</Label>
              <FileUpload v-model="form.primary_driver.documents[1].temp_folders" :initial-files="form.primary_driver.documents[1].existing_files" :allowed-file-types="documentAllowedFileTypes" :allow-multiple="false" :max-files="1" collection="contract_driver_back" theme="light" width="100%" @file-removed="(data: { type: string; fileId?: number }) => onDriverFileRemoved(form.primary_driver, 1, data)" />
              <InputError :message="form.errors['primary_driver.documents.1.temp_folders']" class="mt-1" />
            </div>
          </div>
          <div class="space-y-3 rounded-md border bg-slate-50 p-4">
            <div>
              <Label class="mb-2 block">{{ localize('Customer Photo', 'ط·آµط¸ث†ط·آ±ط·آ© ط·آ§ط¸â€‍ط·آ¹ط¸â€¦ط¸ظ¹ط¸â€‍') }}</Label>
              <FileUpload v-model="form.primary_driver.customer_photo_temp_folders" :initial-files="form.primary_driver.customer_photo_existing_files" :allowed-file-types="photoAllowedFileTypes" :allow-multiple="false" :max-files="1" collection="contract_customer_photo" theme="light" width="100%" @file-removed="(data: { type: string; fileId?: number }) => onDriverCustomerPhotoRemoved(form.primary_driver, data)" />
              <InputError :message="form.errors['primary_driver.customer_photo_temp_folders']" class="mt-1" />
            </div>
            <div v-if="form.primary_driver.customer_photo_preview_url" class="max-w-[220px] overflow-hidden rounded-md border bg-white p-2">
              <img :src="form.primary_driver.customer_photo_preview_url" :alt="localize('Customer photo preview', 'ط¸â€¦ط·آ¹ط·آ§ط¸ظ¹ط¸â€ ط·آ© ط·آµط¸ث†ط·آ±ط·آ© ط·آ§ط¸â€‍ط·آ¹ط¸â€¦ط¸ظ¹ط¸â€‍')" class="h-auto w-full rounded object-cover" />
            </div>
            <div class="flex flex-wrap items-center gap-3">
              <Button type="button" variant="outline" :disabled="form.primary_driver.photo_extracting" @click="extractCustomerPhoto(form.primary_driver)">
                {{ form.primary_driver.photo_extracting ? localize('Extracting Photo...', 'ط·آ¬ط·آ§ط·آ±ط¸ع† ط·آ§ط·آ³ط·ع¾ط·آ®ط·آ±ط·آ§ط·آ¬ ط·آ§ط¸â€‍ط·آµط¸ث†ط·آ±ط·آ©...') : localize('Extract Photo From Document', 'ط·آ§ط·آ³ط·ع¾ط·آ®ط·آ±ط·آ§ط·آ¬ ط·آ§ط¸â€‍ط·آµط¸ث†ط·آ±ط·آ© ط¸â€¦ط¸â€  ط·آ§ط¸â€‍ط¸â€¦ط·آ³ط·ع¾ط¸â€ ط·آ¯') }}
              </Button>
              <p v-if="form.primary_driver.photo_extract_success" class="text-sm text-emerald-600">{{ form.primary_driver.photo_extract_success }}</p>
              <p v-if="form.primary_driver.photo_extract_error" class="text-sm text-red-600">{{ form.primary_driver.photo_extract_error }}</p>
            </div>
          </div>
          <div class="flex flex-wrap items-center gap-3">
            <Button type="button" variant="outline" :disabled="form.primary_driver.extracting" @click="extractDriver(form.primary_driver, 'primary')">
              {{ form.primary_driver.extracting ? localize('Extracting...', 'ط·آ¬ط·آ§ط·آ±ط¸ع† ط·آ§ط¸â€‍ط·آ§ط·آ³ط·ع¾ط·آ®ط·آ±ط·آ§ط·آ¬...') : localize('Extract From Document', 'ط·آ§ط·آ³ط·ع¾ط·آ®ط·آ±ط·آ§ط·آ¬ ط¸â€¦ط¸â€  ط·آ§ط¸â€‍ط¸â€¦ط·آ³ط·ع¾ط¸â€ ط·آ¯') }}
            </Button>
            <p v-if="form.primary_driver.extract_success" class="text-sm text-emerald-600">{{ form.primary_driver.extract_success }}</p>
            <p v-if="form.primary_driver.extract_error" class="text-sm text-red-600">{{ form.primary_driver.extract_error }}</p>
            <p v-if="form.primary_driver.confidence !== null" class="text-sm text-muted-foreground">{{ localize('Confidence', 'ط·آ§ط¸â€‍ط·آ«ط¸â€ڑط·آ©') }}: {{ Number(form.primary_driver.confidence).toFixed(2) }}</p>
          </div>
          <div v-if="form.primary_driver.ai_review_required" class="space-y-1">
            <label class="flex items-center gap-2 text-sm font-medium text-foreground">
              <input v-model="form.primary_driver.ai_reviewed" type="checkbox" class="h-4 w-4 rounded border-gray-300" />
              {{ localize('I reviewed the AI extracted data and confirm it is correct.', 'ط¸â€‍ط¸â€ڑط·آ¯ ط·آ±ط·آ§ط·آ¬ط·آ¹ط·ع¾ ط·آ§ط¸â€‍ط·آ¨ط¸ظ¹ط·آ§ط¸â€ ط·آ§ط·ع¾ ط·آ§ط¸â€‍ط¸â€¦ط·آ³ط·ع¾ط·آ®ط·آ±ط·آ¬ط·آ© ط·آ¨ط·آ§ط¸â€‍ط·آ°ط¸ئ’ط·آ§ط·طŒ ط·آ§ط¸â€‍ط·آ§ط·آµط·آ·ط¸â€ ط·آ§ط·آ¹ط¸ظ¹ ط¸ث†ط·آ£ط·آ¤ط¸ئ’ط·آ¯ ط·آµط·آ­ط·ع¾ط¸â€،ط·آ§.') }}
            </label>
            <InputError :message="form.errors['primary_driver.ai_reviewed']" class="mt-1" />
          </div>
        </section>

        <section class="space-y-4 rounded-lg border bg-white p-5 shadow-sm">
          <div class="flex items-center justify-between gap-3">
            <div>
              <h2 class="text-lg font-semibold">{{ localize('Additional Drivers', 'ط·آ§ط¸â€‍ط·آ³ط·آ§ط·آ¦ط¸â€ڑط¸ث†ط¸â€  ط·آ§ط¸â€‍ط·آ¥ط·آ¶ط·آ§ط¸ظ¾ط¸ظ¹ط¸ث†ط¸â€ ') }}</h2>
              <p class="text-sm text-muted-foreground">{{ localize('Independent drivers inside this contract.', 'ط·آ§ط¸â€‍ط·آ³ط·آ§ط·آ¦ط¸â€ڑط¸ث†ط¸â€  ط·آ§ط¸â€‍ط·آ¥ط·آ¶ط·آ§ط¸ظ¾ط¸ظ¹ط¸ث†ط¸â€  ط·آ¶ط¸â€¦ط¸â€  ط¸â€،ط·آ°ط·آ§ ط·آ§ط¸â€‍ط·آ¹ط¸â€ڑط·آ¯.') }}</p>
            </div>
            <Button type="button" variant="outline" @click="addAdditionalDriver">{{ localize('Add Driver', 'ط·آ¥ط·آ¶ط·آ§ط¸ظ¾ط·آ© ط·آ³ط·آ§ط·آ¦ط¸â€ڑ') }}</Button>
          </div>
          <div v-if="form.additional_drivers.length === 0" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">{{ localize('No additional drivers added.', 'ط¸â€‍ط¸â€¦ ط·ع¾ط·ع¾ط¸â€¦ ط·آ¥ط·آ¶ط·آ§ط¸ظ¾ط·آ© ط·آ³ط·آ§ط·آ¦ط¸â€ڑط¸ظ¹ط¸â€  ط·آ¥ط·آ¶ط·آ§ط¸ظ¾ط¸ظ¹ط¸ظ¹ط¸â€ .') }}</div>
          <div v-for="(driver, index) in form.additional_drivers" :key="driver.id ?? `driver-${index}`" class="space-y-4 rounded-md border p-4">
            <div class="flex items-center justify-between gap-3">
              <div>
                <h3 class="font-semibold">{{ localize(`Additional Driver ${index + 1}`, `ط·آ§ط¸â€‍ط·آ³ط·آ§ط·آ¦ط¸â€ڑ ط·آ§ط¸â€‍ط·آ¥ط·آ¶ط·آ§ط¸ظ¾ط¸ظ¹ ${index + 1}`) }}</h3>
                <p class="text-sm text-muted-foreground">{{ localize('Upload ID or license and review manually.', 'ط·آ§ط·آ±ط¸ظ¾ط·آ¹ ط·آ§ط¸â€‍ط¸â€،ط¸ث†ط¸ظ¹ط·آ© ط·آ£ط¸ث† ط·آ§ط¸â€‍ط·آ±ط·آ®ط·آµط·آ© ط·آ«ط¸â€¦ ط·آ±ط·آ§ط·آ¬ط·آ¹ ط·آ§ط¸â€‍ط·آ¨ط¸ظ¹ط·آ§ط¸â€ ط·آ§ط·ع¾ ط¸ظ¹ط·آ¯ط¸ث†ط¸ظ¹ط¸â€¹ط·آ§.') }}</p>
              </div>
              <Button type="button" variant="ghost" @click="removeAdditionalDriver(index)">{{ localize('Remove', 'ط·آ­ط·آ°ط¸ظ¾') }}</Button>
            </div>
            <div v-if="hasAiExtractedData(driver)" class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
              {{ localize('Review the extracted AI data carefully before saving this contract.', 'ط·آ±ط·آ§ط·آ¬ط·آ¹ ط·آ§ط¸â€‍ط·آ¨ط¸ظ¹ط·آ§ط¸â€ ط·آ§ط·ع¾ ط·آ§ط¸â€‍ط¸â€¦ط·آ³ط·ع¾ط·آ®ط·آ±ط·آ¬ط·آ© ط·آ¨ط·آ§ط¸â€‍ط·آ°ط¸ئ’ط·آ§ط·طŒ ط·آ§ط¸â€‍ط·آ§ط·آµط·آ·ط¸â€ ط·آ§ط·آ¹ط¸ظ¹ ط·آ¨ط·آ¹ط¸â€ ط·آ§ط¸ظ¹ط·آ© ط¸â€ڑط·آ¨ط¸â€‍ ط·آ­ط¸ظ¾ط·آ¸ ط¸â€،ط·آ°ط·آ§ ط·آ§ط¸â€‍ط·آ¹ط¸â€ڑط·آ¯.') }}
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
              <div>
                <Label :for="`driver-document-type-${index}`">{{ localize('Document Type', 'ط¸â€ ط¸ث†ط·آ¹ ط·آ§ط¸â€‍ط¸â€¦ط·آ³ط·ع¾ط¸â€ ط·آ¯') }}</Label>
                <select :id="`driver-document-type-${index}`" v-model="driver.document_type" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2" @change="syncDocumentType(driver)">
                  <option v-for="option in documentTypeOptions" :key="`${index}-${option.value || 'empty'}`" :value="option.value">{{ option.label }}</option>
                </select>
              </div>
              <div><Label :for="`driver-full-name-${index}`">{{ localize('Full Name', 'ط·آ§ط¸â€‍ط·آ§ط·آ³ط¸â€¦ ط·آ§ط¸â€‍ط¸ئ’ط·آ§ط¸â€¦ط¸â€‍') }}</Label><Input :id="`driver-full-name-${index}`" v-model="driver.full_name" maxlength="255" /><InputError :message="form.errors[`additional_drivers.${index}.full_name`]" class="mt-1" /></div>
              <div><Label :for="`driver-full-name-ar-${index}`">{{ localize('Arabic Name', 'ط·آ§ط¸â€‍ط·آ§ط·آ³ط¸â€¦ ط·آ¨ط·آ§ط¸â€‍ط·آ¹ط·آ±ط·آ¨ط¸ظ¹ط·آ©') }}</Label><Input :id="`driver-full-name-ar-${index}`" v-model="driver.full_name_ar" dir="rtl" maxlength="255" /><InputError :message="form.errors[`additional_drivers.${index}.full_name_ar`]" class="mt-1" /></div>
              <div><Label :for="`driver-phone-${index}`">{{ localize('Phone', 'ط·آ§ط¸â€‍ط¸â€،ط·آ§ط·ع¾ط¸ظ¾') }}</Label><Input :id="`driver-phone-${index}`" v-model="driver.phone" inputmode="tel" maxlength="100" /><InputError :message="form.errors[`additional_drivers.${index}.phone`]" class="mt-1" /></div>
              <div><Label :for="`driver-nationality-${index}`">{{ localize('Nationality', 'ط·آ§ط¸â€‍ط·آ¬ط¸â€ ط·آ³ط¸ظ¹ط·آ©') }}</Label><Input :id="`driver-nationality-${index}`" v-model="driver.nationality" maxlength="100" /><InputError :message="form.errors[`additional_drivers.${index}.nationality`]" class="mt-1" /></div>
              <div><Label :for="`driver-place-of-issue-${index}`">{{ localize('Place of Issue', 'ط¸â€¦ط¸ئ’ط·آ§ط¸â€  ط·آ§ط¸â€‍ط·آ¥ط·آµط·آ¯ط·آ§ط·آ±') }}</Label><Input :id="`driver-place-of-issue-${index}`" v-model="driver.place_of_issue" maxlength="255" /><InputError :message="form.errors[`additional_drivers.${index}.place_of_issue`]" class="mt-1" /></div>
              <div><Label :for="`driver-birth-date-${index}`">{{ localize('Date Of Birth', 'ط·ع¾ط·آ§ط·آ±ط¸ظ¹ط·آ® ط·آ§ط¸â€‍ط¸â€¦ط¸ظ¹ط¸â€‍ط·آ§ط·آ¯') }}</Label><Input :id="`driver-birth-date-${index}`" v-model="driver.date_of_birth" type="date" :max="contractDateMin" /><InputError :message="form.errors[`additional_drivers.${index}.date_of_birth`]" class="mt-1" /></div>
              <div><Label :for="`driver-identity-number-${index}`">{{ localize('Identity Number', 'ط·آ±ط¸â€ڑط¸â€¦ ط·آ§ط¸â€‍ط¸â€،ط¸ث†ط¸ظ¹ط·آ©') }}</Label><Input :id="`driver-identity-number-${index}`" v-model="driver.identity_number" maxlength="255" /><InputError :message="form.errors[`additional_drivers.${index}.identity_number`]" class="mt-1" /></div>
              <div><Label :for="`driver-residency-number-${index}`">{{ localize('Residency Number', 'ط·آ±ط¸â€ڑط¸â€¦ ط·آ§ط¸â€‍ط·آ¥ط¸â€ڑط·آ§ط¸â€¦ط·آ©') }}</Label><Input :id="`driver-residency-number-${index}`" v-model="driver.residency_number" maxlength="255" /><InputError :message="form.errors[`additional_drivers.${index}.residency_number`]" class="mt-1" /></div>
              <div><Label :for="`driver-license-number-${index}`">{{ localize('License Number', 'ط·آ±ط¸â€ڑط¸â€¦ ط·آ§ط¸â€‍ط·آ±ط·آ®ط·آµط·آ©') }}</Label><Input :id="`driver-license-number-${index}`" v-model="driver.license_number" maxlength="255" /><InputError :message="form.errors[`additional_drivers.${index}.license_number`]" class="mt-1" /></div>
              <div><Label :for="`driver-identity-expiry-${index}`">{{ localize('Identity Expiry Date', 'ط·ع¾ط·آ§ط·آ±ط¸ظ¹ط·آ® ط·آ§ط¸â€ ط·ع¾ط¸â€،ط·آ§ط·طŒ ط·آ§ط¸â€‍ط¸â€،ط¸ث†ط¸ظ¹ط·آ©') }}</Label><Input :id="`driver-identity-expiry-${index}`" v-model="driver.identity_expiry_date" type="date" :min="contractDateMin" /><InputError :message="form.errors[`additional_drivers.${index}.identity_expiry_date`]" class="mt-1" /></div>
              <div><Label :for="`driver-license-expiry-${index}`">{{ localize('License Expiry Date', 'ط·ع¾ط·آ§ط·آ±ط¸ظ¹ط·آ® ط·آ§ط¸â€ ط·ع¾ط¸â€،ط·آ§ط·طŒ ط·آ§ط¸â€‍ط·آ±ط·آ®ط·آµط·آ©') }}</Label><Input :id="`driver-license-expiry-${index}`" v-model="driver.license_expiry_date" type="date" :min="contractDateMin" /><InputError :message="form.errors[`additional_drivers.${index}.license_expiry_date`]" class="mt-1" /></div>
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div><Label :for="`driver-visa-number-${index}`">{{ localize('Visa Number', 'رقم التأشيرة') }}</Label><Input :id="`driver-visa-number-${index}`" v-model="driver.visa_number" maxlength="255" /><InputError :message="form.errors[`additional_drivers.${index}.visa_number`]" class="mt-1" /></div>
              <div><Label :for="`driver-visa-expiry-${index}`">{{ localize('Visa Expiry Date', 'تاريخ انتهاء التأشيرة') }}</Label><Input :id="`driver-visa-expiry-${index}`" v-model="driver.visa_expiry_date" type="date" :min="contractDateMin" /><InputError :message="form.errors[`additional_drivers.${index}.visa_expiry_date`]" class="mt-1" /></div>
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div>
                <Label class="mb-2 block">{{ localize('Document Front / Single', 'ط·آ§ط¸â€‍ط¸â€¦ط·آ³ط·ع¾ط¸â€ ط·آ¯ ط·آ§ط¸â€‍ط·آ£ط¸â€¦ط·آ§ط¸â€¦ط¸ظ¹ / ط·آ§ط¸â€‍ط¸â€¦ط¸ظ¾ط·آ±ط·آ¯') }}</Label>
                <FileUpload v-model="driver.documents[0].temp_folders" :initial-files="driver.documents[0].existing_files" :allowed-file-types="documentAllowedFileTypes" :allow-multiple="false" :max-files="1" collection="contract_additional_driver_front" theme="light" width="100%" @file-removed="(data: { type: string; fileId?: number }) => onDriverFileRemoved(driver, 0, data)" />
                <InputError :message="form.errors[`additional_drivers.${index}.documents.0.temp_folders`]" class="mt-1" />
              </div>
              <div>
                <Label class="mb-2 block">{{ localize('Document Back', 'ط·آ®ط¸â€‍ط¸ظ¾ط¸ظ¹ط·آ© ط·آ§ط¸â€‍ط¸â€¦ط·آ³ط·ع¾ط¸â€ ط·آ¯') }}</Label>
                <FileUpload v-model="driver.documents[1].temp_folders" :initial-files="driver.documents[1].existing_files" :allowed-file-types="documentAllowedFileTypes" :allow-multiple="false" :max-files="1" collection="contract_additional_driver_back" theme="light" width="100%" @file-removed="(data: { type: string; fileId?: number }) => onDriverFileRemoved(driver, 1, data)" />
                <InputError :message="form.errors[`additional_drivers.${index}.documents.1.temp_folders`]" class="mt-1" />
              </div>
            </div>
          <div class="flex flex-wrap items-center gap-3">
              <Button type="button" variant="outline" :disabled="driver.extracting" @click="extractDriver(driver, 'additional', index)">
                {{ driver.extracting ? localize('Extracting...', 'ط·آ¬ط·آ§ط·آ±ط¸ع† ط·آ§ط¸â€‍ط·آ§ط·آ³ط·ع¾ط·آ®ط·آ±ط·آ§ط·آ¬...') : localize('Extract From Document', 'ط·آ§ط·آ³ط·ع¾ط·آ®ط·آ±ط·آ§ط·آ¬ ط¸â€¦ط¸â€  ط·آ§ط¸â€‍ط¸â€¦ط·آ³ط·ع¾ط¸â€ ط·آ¯') }}
              </Button>
              <p v-if="driver.extract_success" class="text-sm text-emerald-600">{{ driver.extract_success }}</p>
              <p v-if="driver.extract_error" class="text-sm text-red-600">{{ driver.extract_error }}</p>
              <p v-if="driver.confidence !== null" class="text-sm text-muted-foreground">{{ localize('Confidence', 'ط·آ§ط¸â€‍ط·آ«ط¸â€ڑط·آ©') }}: {{ Number(driver.confidence).toFixed(2) }}</p>
            </div>
            <div v-if="driver.ai_review_required" class="space-y-1">
              <label class="flex items-center gap-2 text-sm font-medium text-foreground">
                <input v-model="driver.ai_reviewed" type="checkbox" class="h-4 w-4 rounded border-gray-300" />
                {{ localize('I reviewed the AI extracted data and confirm it is correct.', 'ط¸â€‍ط¸â€ڑط·آ¯ ط·آ±ط·آ§ط·آ¬ط·آ¹ط·ع¾ ط·آ§ط¸â€‍ط·آ¨ط¸ظ¹ط·آ§ط¸â€ ط·آ§ط·ع¾ ط·آ§ط¸â€‍ط¸â€¦ط·آ³ط·ع¾ط·آ®ط·آ±ط·آ¬ط·آ© ط·آ¨ط·آ§ط¸â€‍ط·آ°ط¸ئ’ط·آ§ط·طŒ ط·آ§ط¸â€‍ط·آ§ط·آµط·آ·ط¸â€ ط·آ§ط·آ¹ط¸ظ¹ ط¸ث†ط·آ£ط·آ¤ط¸ئ’ط·آ¯ ط·آµط·آ­ط·ع¾ط¸â€،ط·آ§.') }}
              </label>
              <InputError :message="form.errors[`additional_drivers.${index}.ai_reviewed`]" class="mt-1" />
            </div>
          </div>
        </section>

        <section class="space-y-4 rounded-lg border bg-white p-5 shadow-sm">
          <div class="flex items-center justify-between gap-3">
            <div>
              <h2 class="text-lg font-semibold">{{ localize('Additional Archive', 'ط·آ§ط¸â€‍ط·آ£ط·آ±ط·آ´ط¸ظ¹ط¸ظ¾ ط·آ§ط¸â€‍ط·آ¥ط·آ¶ط·آ§ط¸ظ¾ط¸ظ¹') }}</h2>
              <p class="text-sm text-muted-foreground">{{ localize('Store extra customer documents here. Files already used in the main identity/license section above cannot be added again.', 'ط·آ®ط·آ²ط¸â€کط¸â€  ط¸â€،ط¸â€ ط·آ§ ط¸â€¦ط·آ³ط·ع¾ط¸â€ ط·آ¯ط·آ§ط·ع¾ ط·آ§ط¸â€‍ط·آ¹ط¸â€¦ط¸ظ¹ط¸â€‍ ط·آ§ط¸â€‍ط·آ¥ط·آ¶ط·آ§ط¸ظ¾ط¸ظ¹ط·آ©. ط¸â€‍ط·آ§ ط¸ظ¹ط¸â€¦ط¸ئ’ط¸â€  ط·آ¥ط·آ¶ط·آ§ط¸ظ¾ط·آ© ط·آ§ط¸â€‍ط¸â€¦ط¸â€‍ط¸ظ¾ط·آ§ط·ع¾ ط·آ§ط¸â€‍ط¸â€¦ط·آ³ط·ع¾ط·آ®ط·آ¯ط¸â€¦ط·آ© ط·آ³ط·آ§ط·آ¨ط¸â€ڑط¸â€¹ط·آ§ ط¸ظ¾ط¸ظ¹ ط¸â€ڑط·آ³ط¸â€¦ ط·آ§ط¸â€‍ط¸â€،ط¸ث†ط¸ظ¹ط·آ© ط·آ£ط¸ث† ط·آ§ط¸â€‍ط·آ±ط·آ®ط·آµط·آ© ط·آ§ط¸â€‍ط·آ±ط·آ¦ط¸ظ¹ط·آ³ط¸ظ¹ ط¸â€¦ط·آ±ط·آ© ط·آ£ط·آ®ط·آ±ط¸â€°.') }}</p>
            </div>
            <Button type="button" variant="outline" @click="addAdditionalArchiveItem">{{ localize('Add Archive File', 'ط·آ¥ط·آ¶ط·آ§ط¸ظ¾ط·آ© ط¸â€¦ط¸â€‍ط¸ظ¾ ط¸â€‍ط¸â€‍ط·آ£ط·آ±ط·آ´ط¸ظ¹ط¸ظ¾') }}</Button>
          </div>
          <div class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
            {{ localize('Files already used in the main customer document section above cannot be added to this archive.', 'ط¸â€‍ط·آ§ ط¸ظ¹ط¸â€¦ط¸ئ’ط¸â€  ط·آ¥ط·آ¶ط·آ§ط¸ظ¾ط·آ© ط·آ§ط¸â€‍ط¸â€¦ط¸â€‍ط¸ظ¾ط·آ§ط·ع¾ ط·آ§ط¸â€‍ط¸â€¦ط·آ³ط·ع¾ط·آ®ط·آ¯ط¸â€¦ط·آ© ط¸ظ¾ط¸ظ¹ ط¸â€ڑط·آ³ط¸â€¦ ط¸â€¦ط·آ³ط·ع¾ط¸â€ ط·آ¯ط·آ§ط·ع¾ ط·آ§ط¸â€‍ط·آ¹ط¸â€¦ط¸ظ¹ط¸â€‍ ط·آ§ط¸â€‍ط·آ±ط·آ¦ط¸ظ¹ط·آ³ط¸ظ¹ ط·آ£ط·آ¹ط¸â€‍ط·آ§ط¸â€، ط·آ¥ط¸â€‍ط¸â€° ط¸â€،ط·آ°ط·آ§ ط·آ§ط¸â€‍ط·آ£ط·آ±ط·آ´ط¸ظ¹ط¸ظ¾.') }}
          </div>
          <div v-if="form.additional_archive.length === 0" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">{{ localize('No additional archive files added.', 'ط¸â€‍ط¸â€¦ ط·ع¾ط·ع¾ط¸â€¦ ط·آ¥ط·آ¶ط·آ§ط¸ظ¾ط·آ© ط¸â€¦ط¸â€‍ط¸ظ¾ط·آ§ط·ع¾ ط·آ£ط·آ±ط·آ´ط¸ظ¹ط¸ظ¾ط¸ظ¹ط·آ© ط·آ¥ط·آ¶ط·آ§ط¸ظ¾ط¸ظ¹ط·آ©.') }}</div>
          <div v-for="(item, index) in form.additional_archive" :key="item.id ?? `archive-${index}`" class="space-y-4 rounded-md border p-4">
            <div class="flex items-center justify-between gap-3">
              <div>
                <h3 class="font-semibold">{{ localize(`Archive File ${index + 1}`, `ط¸â€¦ط¸â€‍ط¸ظ¾ ط·آ§ط¸â€‍ط·آ£ط·آ±ط·آ´ط¸ظ¹ط¸ظ¾ ${index + 1}`) }}</h3>
                <p class="text-sm text-muted-foreground">{{ localize('Upload one additional customer document for archive only.', 'ط·آ§ط·آ±ط¸ظ¾ط·آ¹ ط¸â€¦ط·آ³ط·ع¾ط¸â€ ط·آ¯ط¸â€¹ط·آ§ ط·آ¥ط·آ¶ط·آ§ط¸ظ¾ط¸ظ¹ط¸â€¹ط·آ§ ط¸ث†ط·آ§ط·آ­ط·آ¯ط¸â€¹ط·آ§ ط¸â€‍ط¸â€‍ط·آ¹ط¸â€¦ط¸ظ¹ط¸â€‍ ط¸â€‍ط¸â€‍ط·آ£ط·آ±ط·آ´ط¸ظ¾ط·آ© ط¸ظ¾ط¸â€ڑط·آ·.') }}</p>
              </div>
              <Button type="button" variant="ghost" @click="removeAdditionalArchiveItem(index)">{{ localize('Remove', 'ط·آ¥ط·آ²ط·آ§ط¸â€‍ط·آ©') }}</Button>
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
              <div>
                <Label :for="`archive-document-type-${index}`">{{ localize('Document Type', 'ط¸â€ ط¸ث†ط·آ¹ ط·آ§ط¸â€‍ط¸â€¦ط·آ³ط·ع¾ط¸â€ ط·آ¯') }}</Label>
                <select :id="`archive-document-type-${index}`" v-model="item.document_type" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2">
                  <option v-for="option in additionalArchiveDocumentTypeOptions" :key="`${index}-${option.value || 'empty'}`" :value="option.value">{{ option.label }}</option>
                </select>
                <InputError :message="form.errors[`additional_archive.${index}.document_type`]" class="mt-1" />
              </div>
              <div>
                <Label :for="`archive-owner-${index}`">{{ localize('Belongs To', 'ط¸ظ¹ط·ع¾ط·آ¨ط·آ¹ ط·آ¥ط¸â€‍ط¸â€°') }}</Label>
                <select :id="`archive-owner-${index}`" v-model="item.owner_key" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2">
                  <option v-for="option in additionalArchiveOwnerOptions" :key="`${index}-${option.value || 'none'}`" :value="option.value">{{ option.label }}</option>
                </select>
                <InputError :message="form.errors[`additional_archive.${index}.owner_key`]" class="mt-1" />
              </div>
              <div>
                <Label :for="`archive-title-${index}`">{{ localize('Title', 'ط·آ§ط¸â€‍ط·آ¹ط¸â€ ط¸ث†ط·آ§ط¸â€ ') }}</Label>
                <Input :id="`archive-title-${index}`" v-model="item.title" />
                <InputError :message="form.errors[`additional_archive.${index}.title`]" class="mt-1" />
              </div>
              <div>
                <Label :for="`archive-notes-${index}`">{{ localize('Notes', 'ط·آ§ط¸â€‍ط¸â€¦ط¸â€‍ط·آ§ط·آ­ط·آ¸ط·آ§ط·ع¾') }}</Label>
                <Input :id="`archive-notes-${index}`" v-model="item.notes" />
                <InputError :message="form.errors[`additional_archive.${index}.notes`]" class="mt-1" />
              </div>
            </div>
            <div>
              <Label class="mb-2 block">{{ localize('Archive File', 'ط¸â€¦ط¸â€‍ط¸ظ¾ ط·آ§ط¸â€‍ط·آ£ط·آ±ط·آ´ط¸ظ¹ط¸ظ¾') }}</Label>
              <FileUpload v-model="item.temp_folders" :initial-files="item.existing_files || []" :allowed-file-types="documentAllowedFileTypes" :allow-multiple="false" :max-files="1" collection="contract_additional_archive" theme="light" width="100%" @file-removed="(data: { type: string; fileId?: number }) => onAdditionalArchiveFileRemoved(index, data)" />
              <InputError :message="form.errors[`additional_archive.${index}.temp_folders`]" class="mt-1" />
            </div>
          </div>
        </section>

        <section class="space-y-4 rounded-lg border bg-white p-5 shadow-sm">
          <div>
            <h2 class="text-lg font-semibold">{{ localize('Car Data', 'ط·آ¨ط¸ظ¹ط·آ§ط¸â€ ط·آ§ط·ع¾ ط·آ§ط¸â€‍ط·آ³ط¸ظ¹ط·آ§ط·آ±ط·آ©') }}</h2>
            <p class="text-sm text-muted-foreground">{{ localize('Reservation and vehicle details for this contract.', 'ط·آ¨ط¸ظ¹ط·آ§ط¸â€ ط·آ§ط·ع¾ ط·آ§ط¸â€‍ط·آ­ط·آ¬ط·آ² ط¸ث†ط·آ§ط¸â€‍ط·آ³ط¸ظ¹ط·آ§ط·آ±ط·آ© ط·آ§ط¸â€‍ط·آ®ط·آ§ط·آµط·آ© ط·آ¨ط¸â€،ط·آ°ط·آ§ ط·آ§ط¸â€‍ط·آ¹ط¸â€ڑط·آ¯.') }}</p>
          </div>
          <div v-if="hasLinkedReservation" class="rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800">
            {{ localize('Car details are linked to the selected reservation and cannot be edited here.', 'ط·آ¨ط¸ظ¹ط·آ§ط¸â€ ط·آ§ط·ع¾ ط·آ§ط¸â€‍ط·آ³ط¸ظ¹ط·آ§ط·آ±ط·آ© ط¸â€¦ط·آ±ط·ع¾ط·آ¨ط·آ·ط·آ© ط·آ¨ط·آ§ط¸â€‍ط·آ­ط·آ¬ط·آ² ط·آ§ط¸â€‍ط¸â€¦ط·آ­ط·آ¯ط·آ¯ ط¸ث†ط¸â€‍ط·آ§ ط¸ظ¹ط¸â€¦ط¸ئ’ط¸â€  ط·ع¾ط·آ¹ط·آ¯ط¸ظ¹ط¸â€‍ط¸â€،ط·آ§ ط¸â€،ط¸â€ ط·آ§.') }}
          </div>
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div><Label for="car-details">{{ localize('Car Details', 'ط·ع¾ط¸ظ¾ط·آ§ط·آµط¸ظ¹ط¸â€‍ ط·آ§ط¸â€‍ط·آ³ط¸ظ¹ط·آ§ط·آ±ط·آ©') }}</Label><Input id="car-details" v-model="form.car_data.car_details" :disabled="hasLinkedReservation" /><InputError :message="form.errors['car_data.car_details']" class="mt-1" /></div>
            <div><Label for="plate-number">{{ localize('Plate Number', 'ط·آ±ط¸â€ڑط¸â€¦ ط·آ§ط¸â€‍ط¸â€‍ط¸ث†ط·آ­ط·آ©') }}</Label><Input id="plate-number" v-model="form.car_data.plate_number" :disabled="hasLinkedReservation" /><InputError :message="form.errors['car_data.plate_number']" class="mt-1" /></div>
            <div><Label for="vehicle-odometer">{{ localize('Vehicle Odometer', 'ط¹ط¯ط§ط¯ ط§ظ„ط³ظٹط§ط±ط©') }}</Label><Input id="vehicle-odometer" v-model="form.car_data.vehicle_odometer" type="number" min="0" /><InputError :message="form.errors['car_data.vehicle_odometer'] || form.errors.vehicle_odometer" class="mt-1" /></div>
            <div>
              <Label for="vehicle-fuel-level">{{ localize('Fuel In Vehicle', 'ط§ظ„ط¨ظ†ط²ظٹظ† ط§ظ„ظ…ظˆط¬ظˆط¯ ظپظٹ ط§ظ„ط³ظٹط§ط±ط©') }}</Label>
              <select id="vehicle-fuel-level" v-model="form.car_data.vehicle_fuel_level" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2">
                <option v-for="option in fuelLevelOptions" :key="option.value || 'fuel-empty'" :value="option.value">{{ option.label }}</option>
              </select>
              <InputError :message="form.errors['car_data.vehicle_fuel_level'] || form.errors.vehicle_fuel_level" class="mt-1" />
            </div>
          </div>
          <div v-if="selectedCarDamages.length" class="rounded-md border p-4">
            <div class="mb-2 text-sm font-medium">{{ localize('Current Car Damages', 'ط·آ£ط·آ¶ط·آ±ط·آ§ط·آ± ط·آ§ط¸â€‍ط·آ³ط¸ظ¹ط·آ§ط·آ±ط·آ© ط·آ§ط¸â€‍ط·آ­ط·آ§ط¸â€‍ط¸ظ¹ط·آ©') }}</div>
            <div class="overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead>
                  <tr class="border-b text-left text-muted-foreground">
                    <th class="px-2 py-2">{{ localize('Zone', 'ط·آ§ط¸â€‍ط¸â€¦ط¸â€ ط·آ·ط¸â€ڑط·آ©') }}</th>
                    <th class="px-2 py-2">{{ localize('View', 'ط·آ§ط¸â€‍ط·آ¬ط¸â€،ط·آ©') }}</th>
                    <th class="px-2 py-2">{{ localize('Type', 'ط·آ§ط¸â€‍ط¸â€ ط¸ث†ط·آ¹') }}</th>
                    <th class="px-2 py-2">{{ localize('Severity', 'ط·آ§ط¸â€‍ط·آ´ط·آ¯ط¸â€کط·آ©') }}</th>
                    <th class="px-2 py-2">{{ localize('Qty', 'ط·آ§ط¸â€‍ط¸ئ’ط¸â€¦ط¸ظ¹ط·آ©') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="damage in selectedCarDamages" :key="damage.id" class="border-b">
                    <td class="px-2 py-2">{{ damage.zone_label }}</td>
                    <td class="px-2 py-2">{{ damage.view_side_label }}</td>
                    <td class="px-2 py-2">{{ damage.damage_type_label }}</td>
                    <td class="px-2 py-2">{{ damage.severity_label }}</td>
                    <td class="px-2 py-2">{{ damage.quantity }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <section class="space-y-4 rounded-lg border bg-white p-5 shadow-sm">
          <div>
            <h2 class="text-lg font-semibold">{{ localize('Rental Data', 'ط·آ¨ط¸ظ¹ط·آ§ط¸â€ ط·آ§ط·ع¾ ط·آ§ط¸â€‍ط·ع¾ط·آ£ط·آ¬ط¸ظ¹ط·آ±') }}</h2>
            <p class="text-sm text-muted-foreground">{{ localize('Contract lifecycle, rental period, amount, and notes.', 'ط·آ¯ط¸ث†ط·آ±ط·آ© ط·آ­ط¸ظ¹ط·آ§ط·آ© ط·آ§ط¸â€‍ط·آ¹ط¸â€ڑط·آ¯ ط¸ث†ط¸ظ¾ط·ع¾ط·آ±ط·آ© ط·آ§ط¸â€‍ط·ع¾ط·آ£ط·آ¬ط¸ظ¹ط·آ± ط¸ث†ط·آ§ط¸â€‍ط¸â€¦ط·آ¨ط¸â€‍ط·ط› ط¸ث†ط·آ§ط¸â€‍ط¸â€¦ط¸â€‍ط·آ§ط·آ­ط·آ¸ط·آ§ط·ع¾.') }}</p>
          </div>
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div class="xl:col-span-2">
              <Label for="reservation_id">{{ localize('Linked Reservation', 'ط·آ§ط¸â€‍ط·آ­ط·آ¬ط·آ² ط·آ§ط¸â€‍ط¸â€¦ط·آ±ط·ع¾ط·آ¨ط·آ·') }}</Label>
              <div class="mt-1 flex gap-2">
                <div class="relative flex-1">
                  <Input
                    id="reservation_id"
                    v-model="reservationSearch"
                    :placeholder="localize('Search reservation...', 'ط·آ§ط·آ¨ط·آ­ط·آ« ط·آ¹ط¸â€  ط·آ§ط¸â€‍ط·آ­ط·آ¬ط·آ²...')"
                    autocomplete="off"
                    :required="mode === 'create'"
                    @focus="reservationMenuOpen = true"
                    @blur="handleReservationBlur"
                    @input="
                      reservationMenuOpen = true;
                      form.reservation_id = '';
                    "
                  />

                  <button
                    v-if="form.reservation_id"
                    class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-muted-foreground hover:text-foreground"
                    type="button"
                    @mousedown.prevent
                    @click="clearReservation"
                  >
                    {{ localize('Clear', 'ط¸â€¦ط·آ³ط·آ­') }}
                  </button>

                  <div
                    v-if="reservationMenuOpen"
                    class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-md border bg-background shadow-lg"
                  >
                    <button
                      class="flex w-full items-start px-3 py-2 text-left text-sm hover:bg-muted"
                      type="button"
                      @mousedown.prevent="clearReservation"
                    >
                      {{ localize('No linked reservation', 'ط¸â€‍ط·آ§ ط¸ظ¹ط¸ث†ط·آ¬ط·آ¯ ط·آ­ط·آ¬ط·آ² ط¸â€¦ط·آ±ط·ع¾ط·آ¨ط·آ·') }}
                    </button>

                    <button
                      v-for="reservation in filteredReservationsBySearch"
                      :key="reservation.id"
                      class="flex w-full flex-col items-start px-3 py-2 text-left text-sm hover:bg-muted disabled:cursor-not-allowed disabled:opacity-60"
                      :disabled="reservation.has_contract && Number(reservation.id) !== Number(form.reservation_id)"
                      type="button"
                      @mousedown.prevent="selectReservation(reservation)"
                    >
                      <span class="font-medium">
                        {{ reservation.label }}
                        <span v-if="reservation.has_contract && Number(reservation.id) !== Number(form.reservation_id)">
                          {{ localize(' (has contract)', ' (ط¸â€‍ط·آ¯ط¸ظ¹ط¸â€، ط·آ¹ط¸â€ڑط·آ¯)') }}
                        </span>
                      </span>
                      <span v-if="reservation.car_details || reservation.plate_number" class="text-xs text-muted-foreground">
                        {{ reservation.car_details || localize('No car details', 'ط¸â€‍ط·آ§ ط·ع¾ط¸ث†ط·آ¬ط·آ¯ ط·ع¾ط¸ظ¾ط·آ§ط·آµط¸ظ¹ط¸â€‍ ط·آ³ط¸ظ¹ط·آ§ط·آ±ط·آ©') }}
                        <span v-if="reservation.plate_number"> أ¢â‚¬آ¢ {{ reservation.plate_number }}</span>
                      </span>
                    </button>

                    <div
                      v-if="filteredReservationsBySearch.length === 0"
                      class="px-3 py-2 text-sm text-muted-foreground"
                    >
                      {{ localize('No reservations found.', 'ط¸â€‍ط·آ§ ط·ع¾ط¸ث†ط·آ¬ط·آ¯ ط·آ­ط·آ¬ط¸ث†ط·آ²ط·آ§ط·ع¾ ط¸â€¦ط·آ·ط·آ§ط·آ¨ط¸â€ڑط·آ©.') }}
                    </div>
                  </div>
                </div>
                <Button type="button" variant="outline" @click="showReservationModal = true">{{ localize('New Reservation', 'ط·آ­ط·آ¬ط·آ² ط·آ¬ط·آ¯ط¸ظ¹ط·آ¯') }}</Button>
              </div>
              <InputError :message="form.errors.reservation_id" class="mt-1" />
              <div v-if="selectedReservation" class="mt-3 rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
                <div><span class="font-medium text-foreground">{{ localize('Reservation', 'ط·آ§ط¸â€‍ط·آ­ط·آ¬ط·آ²') }}:</span> {{ selectedReservation.reservation_number }}</div>
                <div><span class="font-medium text-foreground">{{ localize('Client', 'ط·آ§ط¸â€‍ط·آ¹ط¸â€¦ط¸ظ¹ط¸â€‍') }}:</span> {{ selectedReservation.user_name || localize('N/A', 'ط·ط›ط¸ظ¹ط·آ± ط¸â€¦ط·ع¾ط¸ث†ط¸ظ¾ط·آ±') }}</div>
                <div><span class="font-medium text-foreground">{{ localize('Car', 'ط·آ§ط¸â€‍ط·آ³ط¸ظ¹ط·آ§ط·آ±ط·آ©') }}:</span> {{ selectedReservation.car_details || selectedReservation.car || localize('N/A', 'ط·ط›ط¸ظ¹ط·آ± ط¸â€¦ط·ع¾ط¸ث†ط¸ظ¾ط·آ±') }}</div>
                <div>
                  <span class="font-medium text-foreground">{{ localize('Status', 'ط·آ§ط¸â€‍ط·آ­ط·آ§ط¸â€‍ط·آ©') }}:</span>
                  <span
                    class="ml-1 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="{
                      'bg-red-100 text-red-700': selectedReservationNotice?.tone === 'danger',
                      'bg-amber-100 text-amber-700': selectedReservationNotice?.tone === 'warning',
                      'bg-blue-100 text-blue-700': selectedReservationNotice?.tone === 'info',
                    }"
                  >
                    {{ selectedReservation.status_label || selectedReservation.status }}
                  </span>
                </div>
                <div
                  v-if="selectedReservationNotice"
                  class="mt-3 rounded-md border p-3 text-sm"
                  :class="{
                    'border-red-200 bg-red-50 text-red-700': selectedReservationNotice.tone === 'danger',
                    'border-amber-200 bg-amber-50 text-amber-700': selectedReservationNotice.tone === 'warning',
                    'border-blue-200 bg-blue-50 text-blue-700': selectedReservationNotice.tone === 'info',
                  }"
                >
                  <div class="font-medium">{{ selectedReservationNotice.title }}</div>
                  <div class="mt-1">{{ selectedReservationNotice.message }}</div>
                </div>
              </div>
            </div>
            <div><Label for="contract_number">{{ localize('Contract Number', 'ط·آ±ط¸â€ڑط¸â€¦ ط·آ§ط¸â€‍ط·آ¹ط¸â€ڑط·آ¯') }}</Label><Input id="contract_number" v-model="form.contract_number" readonly /><InputError :message="form.errors.contract_number" class="mt-1" /></div>
            <div>
              <Label for="status">{{ localize('Status', 'ط·آ§ط¸â€‍ط·آ­ط·آ§ط¸â€‍ط·آ©') }}</Label>
              <select id="status" v-model="form.status" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2">
                <option value="draft">{{ localize('Draft', 'ط¸â€¦ط·آ³ط¸ث†ط·آ¯ط·آ©') }}</option>
                <option value="active">{{ localize('Active', 'ط¸â€ ط·آ´ط·آ·') }}</option>
                <option value="completed">{{ localize('Completed', 'ط¸â€¦ط¸ئ’ط·ع¾ط¸â€¦ط¸â€‍') }}</option>
                <option value="cancelled">{{ localize('Cancelled', 'ط¸â€¦ط¸â€‍ط·ط›ط¸ظ¹') }}</option>
              </select>
              <InputError :message="form.errors.status" class="mt-1" />
            </div>
            <div><Label for="contract_date">{{ localize('Contract Date', 'ط·ع¾ط·آ§ط·آ±ط¸ظ¹ط·آ® ط·آ§ط¸â€‍ط·آ¹ط¸â€ڑط·آ¯') }}</Label><Input id="contract_date" v-model="form.contract_date" type="date" :min="contractDateMin" :required="mode === 'create'" /><InputError :message="form.errors.contract_date" class="mt-1" /></div>
            <div><Label for="start_date">{{ localize('Rental Start Date', 'ط·ع¾ط·آ§ط·آ±ط¸ظ¹ط·آ® ط·آ¨ط·آ¯ط·طŒ ط·آ§ط¸â€‍ط·ع¾ط·آ£ط·آ¬ط¸ظ¹ط·آ±') }}</Label><Input id="start_date" v-model="form.start_date" type="date" :min="contractStartDateMin" :disabled="hasLinkedReservation" :required="mode === 'create'" /><InputError :message="form.errors.start_date" class="mt-1" /></div>
            <div><Label for="end_date">{{ localize('Rental End Date', 'ط·ع¾ط·آ§ط·آ±ط¸ظ¹ط·آ® ط·آ§ط¸â€ ط·ع¾ط¸â€،ط·آ§ط·طŒ ط·آ§ط¸â€‍ط·ع¾ط·آ£ط·آ¬ط¸ظ¹ط·آ±') }}</Label><Input id="end_date" v-model="form.end_date" type="date" :min="contractRentalEndDateMin" :disabled="hasLinkedReservation" :required="mode === 'create'" /><InputError :message="form.errors.end_date" class="mt-1" /></div>
            <div><Label for="total_amount">{{ localize('Total Amount', 'ط·آ§ط¸â€‍ط¸â€¦ط·آ¨ط¸â€‍ط·ط› ط·آ§ط¸â€‍ط·آ¥ط·آ¬ط¸â€¦ط·آ§ط¸â€‍ط¸ظ¹') }}</Label><Input id="total_amount" v-model="form.total_amount" type="number" min="0" step="0.01" :disabled="hasLinkedReservation" /><InputError :message="form.errors.total_amount" class="mt-1" /></div>
            <div><Label for="currency">{{ localize('Currency', 'ط·آ§ط¸â€‍ط·آ¹ط¸â€¦ط¸â€‍ط·آ©') }}</Label><Input id="currency" v-model="form.currency" maxlength="3" inputmode="text" @input="form.currency = String(form.currency || '').toUpperCase()" /><InputError :message="form.errors.currency" class="mt-1" /></div>
            <div><Label for="return-odometer">{{ localize('Return Mileage', 'عداد العودة') }}</Label><Input id="return-odometer" v-model="form.return_odometer" type="number" min="0" /><InputError :message="form.errors.return_odometer" class="mt-1" /></div>
            <div>
              <Label for="return-fuel-level">{{ localize('Return Fuel', 'الوقود عند العودة') }}</Label>
              <select id="return-fuel-level" v-model="form.return_fuel_level" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2">
                <option v-for="option in fuelLevelOptions" :key="`return-${option.value || 'fuel-empty'}`" :value="option.value">{{ option.label }}</option>
              </select>
              <InputError :message="form.errors.return_fuel_level" class="mt-1" />
            </div>
            <div><Label for="actual-return-time">{{ localize('Return Date / Actual Return Time', 'تاريخ ووقت العودة الفعلي') }}</Label><Input id="actual-return-time" v-model="form.actual_return_time" type="datetime-local" /><InputError :message="form.errors.actual_return_time" class="mt-1" /></div>
            <div>
              <Label for="vehicle-condition-before">{{ localize('Vehicle Condition Before Delivery', 'حالة المركبة قبل التسليم') }}</Label>
              <select id="vehicle-condition-before" v-model="form.vehicle_condition_before" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2">
                <option v-for="option in vehicleConditionOptions" :key="`before-${option.value || 'empty'}`" :value="option.value">{{ option.label }}</option>
              </select>
              <InputError :message="form.errors.vehicle_condition_before" class="mt-1" />
            </div>
            <div>
              <Label for="vehicle-condition-after">{{ localize('Vehicle Condition After Return', 'حالة المركبة بعد العودة') }}</Label>
              <select id="vehicle-condition-after" v-model="form.vehicle_condition_after" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2">
                <option v-for="option in vehicleConditionOptions" :key="`after-${option.value || 'empty'}`" :value="option.value">{{ option.label }}</option>
              </select>
              <InputError :message="form.errors.vehicle_condition_after" class="mt-1" />
            </div>
            <div class="md:col-span-2 xl:col-span-3">
              <Label for="notes">{{ localize('Notes', 'ط·آ§ط¸â€‍ط¸â€¦ط¸â€‍ط·آ§ط·آ­ط·آ¸ط·آ§ط·ع¾') }}</Label>
              <textarea id="notes" v-model="form.notes" rows="3" class="w-full rounded-md border border-input bg-transparent px-3 py-2" />
              <InputError :message="form.errors.notes" class="mt-1" />
            </div>
          </div>
        </section>

        <section class="space-y-4 rounded-lg border bg-white p-5 shadow-sm">
          <div>
            <h2 class="text-lg font-semibold">{{ localize('Contract Archive', 'ط·آ£ط·آ±ط·آ´ط¸ظ¹ط¸ظ¾ ط·آ§ط¸â€‍ط·آ¹ط¸â€ڑط·آ¯') }}</h2>
            <p class="text-sm text-muted-foreground">{{ localize('Keep contract scans and supporting files here as archive attachments.', 'ط·آ§ط·آ­ط·ع¾ط¸ظ¾ط¸ع¯ط·آ¸ ط¸â€،ط¸â€ ط·آ§ ط·آ¨ط¸â€ ط·آ³ط·آ® ط·آ§ط¸â€‍ط·آ¹ط¸â€ڑط·آ¯ ط¸ث†ط·آ§ط¸â€‍ط¸â€¦ط¸â€‍ط¸ظ¾ط·آ§ط·ع¾ ط·آ§ط¸â€‍ط·آ¯ط·آ§ط·آ¹ط¸â€¦ط·آ© ط¸ئ’ط¸â€¦ط·آ±ط¸ظ¾ط¸â€ڑط·آ§ط·ع¾ ط·آ£ط·آ±ط·آ´ط¸ظ¹ط¸ظ¾ط¸ظ¹ط·آ©.') }}</p>
          </div>
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
              <Label class="mb-2 block">{{ localize('Start Rental Contract File', 'ط¸â€¦ط¸â€‍ط¸ظ¾ ط·آ¹ط¸â€ڑط·آ¯ ط·آ¨ط·آ¯ط·آ§ط¸ظ¹ط·آ© ط·آ§ط¸â€‍ط·ع¾ط·آ£ط·آ¬ط¸ظ¹ط·آ±') }}</Label>
              <FileUpload v-model="form.start_contract_temp_folders" :initial-files="startContractFiles || []" :allowed-file-types="documentAllowedFileTypes" :allow-multiple="false" :max-files="1" collection="start_contract" theme="light" width="100%" @file-removed="(data: { type: string; fileId?: number }) => onArchiveFileRemoved('start', data)" />
              <InputError :message="form.errors.start_contract_temp_folders" class="mt-1" />
            </div>
            <div>
              <Label class="mb-2 block">{{ localize('End Rental Contract File', 'ط¸â€¦ط¸â€‍ط¸ظ¾ ط·آ¹ط¸â€ڑط·آ¯ ط¸â€ ط¸â€،ط·آ§ط¸ظ¹ط·آ© ط·آ§ط¸â€‍ط·ع¾ط·آ£ط·آ¬ط¸ظ¹ط·آ±') }}</Label>
              <FileUpload v-model="form.end_contract_temp_folders" :initial-files="endContractFiles || []" :allowed-file-types="documentAllowedFileTypes" :allow-multiple="false" :max-files="1" collection="end_contract" theme="light" width="100%" @file-removed="(data: { type: string; fileId?: number }) => onArchiveFileRemoved('end', data)" />
              <InputError :message="form.errors.end_contract_temp_folders" class="mt-1" />
            </div>
          </div>
        </section>

        <div class="flex gap-3">
          <Button type="submit" :disabled="form.processing || isLocked">{{ form.processing ? localize('Saving...', 'ط·آ¬ط·آ§ط·آ±ط¸ع† ط·آ§ط¸â€‍ط·آ­ط¸ظ¾ط·آ¸...') : localize('Save Contract', 'ط·آ­ط¸ظ¾ط·آ¸ ط·آ§ط¸â€‍ط·آ¹ط¸â€ڑط·آ¯') }}</Button>
          <Link :href="actions.index"><Button type="button" variant="outline">{{ localize('Cancel', 'ط·آ¥ط¸â€‍ط·ط›ط·آ§ط·طŒ') }}</Button></Link>
        </div>
      </form>

      <Dialog v-model:open="showReservationModal">
        <DialogContent class="sm:max-w-2xl">
          <DialogHeader>
            <DialogTitle>{{ localize('Create Reservation', 'ط·آ¥ط¸â€ ط·آ´ط·آ§ط·طŒ ط·آ­ط·آ¬ط·آ²') }}</DialogTitle>
            <DialogDescription>{{ localize('Create a reservation here, then link it directly to this contract.', 'ط·آ£ط¸â€ ط·آ´ط·آ¦ ط·آ§ط¸â€‍ط·آ­ط·آ¬ط·آ² ط¸â€،ط¸â€ ط·آ§ ط·آ«ط¸â€¦ ط·آ§ط·آ±ط·آ¨ط·آ·ط¸â€، ط¸â€¦ط·آ¨ط·آ§ط·آ´ط·آ±ط·آ© ط·آ¨ط¸â€،ط·آ°ط·آ§ ط·آ§ط¸â€‍ط·آ¹ط¸â€ڑط·آ¯.') }}</DialogDescription>
          </DialogHeader>

          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
              <Label for="reservation-user-id">{{ localize('Client', 'ط·آ§ط¸â€‍ط·آ¹ط¸â€¦ط¸ظ¹ط¸â€‍') }}</Label>
              <select id="reservation-user-id" v-model="reservationForm.user_id" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2">
                <option value="" disabled>{{ localize('Select client', 'ط·آ§ط·آ®ط·ع¾ط·آ± ط·آ§ط¸â€‍ط·آ¹ط¸â€¦ط¸ظ¹ط¸â€‍') }}</option>
                <option v-for="client in reservationClients" :key="client.id" :value="client.id">{{ client.name }} ({{ client.email }})</option>
              </select>
              <InputError :message="reservationForm.errors.user_id" class="mt-1" />
            </div>
            <div>
              <Label for="reservation-car-id">{{ localize('Car', 'ط·آ§ط¸â€‍ط·آ³ط¸ظ¹ط·آ§ط·آ±ط·آ©') }}</Label>
              <select id="reservation-car-id" v-model="reservationForm.car_id" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2">
                <option value="" disabled>{{ localize('Select car', 'ط·آ§ط·آ®ط·ع¾ط·آ± ط·آ§ط¸â€‍ط·آ³ط¸ظ¹ط·آ§ط·آ±ط·آ©') }}</option>
                <option v-for="car in reservationCars" :key="car.id" :value="car.id">{{ car.label }} | {{ car.license_plate }}{{ car.branch_name ? ` | ${car.branch_name}` : '' }}</option>
              </select>
              <InputError :message="reservationForm.errors.car_id" class="mt-1" />
            </div>
            <div>
              <Label for="reservation-start-date">{{ localize('Start Date', 'ط·ع¾ط·آ§ط·آ±ط¸ظ¹ط·آ® ط·آ§ط¸â€‍ط·آ¨ط·آ¯ط·طŒ') }}</Label>
              <Input id="reservation-start-date" v-model="reservationForm.start_date" type="date" />
              <InputError :message="reservationForm.errors.start_date" class="mt-1" />
            </div>
            <div>
              <Label for="reservation-end-date">{{ localize('End Date', 'ط·ع¾ط·آ§ط·آ±ط¸ظ¹ط·آ® ط·آ§ط¸â€‍ط·آ§ط¸â€ ط·ع¾ط¸â€،ط·آ§ط·طŒ') }}</Label>
              <Input id="reservation-end-date" v-model="reservationForm.end_date" type="date" />
              <InputError :message="reservationForm.errors.end_date" class="mt-1" />
            </div>
            <div>
              <Label for="reservation-pickup-time">{{ localize('Pickup Time', 'ط¸ث†ط¸â€ڑط·ع¾ ط·آ§ط¸â€‍ط·آ§ط·آ³ط·ع¾ط¸â€‍ط·آ§ط¸â€¦') }}</Label>
              <Input id="reservation-pickup-time" v-model="reservationForm.pickup_time" type="time" />
              <InputError :message="reservationForm.errors.pickup_time" class="mt-1" />
            </div>
            <div>
              <Label for="reservation-return-time">{{ localize('Return Time', 'ط¸ث†ط¸â€ڑط·ع¾ ط·آ§ط¸â€‍ط·آ¥ط·آ±ط·آ¬ط·آ§ط·آ¹') }}</Label>
              <Input id="reservation-return-time" v-model="reservationForm.return_time" type="time" />
              <InputError :message="reservationForm.errors.return_time" class="mt-1" />
            </div>
            <div>
              <Label for="reservation-pickup-location">{{ localize('Pickup Location', 'ط¸â€¦ط¸ئ’ط·آ§ط¸â€  ط·آ§ط¸â€‍ط·آ§ط·آ³ط·ع¾ط¸â€‍ط·آ§ط¸â€¦') }}</Label>
              <Input id="reservation-pickup-location" v-model="reservationForm.pickup_location" />
              <InputError :message="reservationForm.errors.pickup_location" class="mt-1" />
            </div>
            <div>
              <Label for="reservation-return-location">{{ localize('Return Location', 'ط¸â€¦ط¸ئ’ط·آ§ط¸â€  ط·آ§ط¸â€‍ط·آ¥ط·آ±ط·آ¬ط·آ§ط·آ¹') }}</Label>
              <Input id="reservation-return-location" v-model="reservationForm.return_location" />
              <InputError :message="reservationForm.errors.return_location" class="mt-1" />
            </div>
            <div>
              <Label for="reservation-discount-amount">{{ localize('Discount', 'ط·آ§ط¸â€‍ط·آ®ط·آµط¸â€¦') }}</Label>
              <Input id="reservation-discount-amount" v-model="reservationForm.discount_amount" type="number" min="0" step="0.01" />
              <InputError :message="reservationForm.errors.discount_amount" class="mt-1" />
            </div>
            <div>
              <Label for="reservation-status">{{ localize('Status', 'ط·آ§ط¸â€‍ط·آ­ط·آ§ط¸â€‍ط·آ©') }}</Label>
              <select id="reservation-status" v-model="reservationForm.status" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2">
                <option v-for="status in reservationStatusOptions" :key="status.value" :value="status.value">{{ status.label }}</option>
              </select>
              <InputError :message="reservationForm.errors.status" class="mt-1" />
            </div>
            <div class="md:col-span-2">
              <Label for="reservation-notes">{{ localize('Notes', 'ط·آ§ط¸â€‍ط¸â€¦ط¸â€‍ط·آ§ط·آ­ط·آ¸ط·آ§ط·ع¾') }}</Label>
              <textarea id="reservation-notes" v-model="reservationForm.notes" rows="3" class="w-full rounded-md border border-input bg-transparent px-3 py-2" />
              <InputError :message="reservationForm.errors.notes" class="mt-1" />
            </div>
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" @click="showReservationModal = false">{{ localize('Cancel', 'ط·آ¥ط¸â€‍ط·ط›ط·آ§ط·طŒ') }}</Button>
            <Button type="button" :disabled="reservationSubmitting" @click="submitReservationFromModal">
              {{ reservationSubmitting ? localize('Creating...', 'ط·آ¬ط·آ§ط·آ±ط¸ع† ط·آ§ط¸â€‍ط·آ¥ط¸â€ ط·آ´ط·آ§ط·طŒ...') : localize('Create Reservation', 'ط·آ¥ط¸â€ ط·آ´ط·آ§ط·طŒ ط·آ­ط·آ¬ط·آ²') }}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </main>
  </AdminLayout>
</template>






















