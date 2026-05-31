<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/composables/useTrans';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    contracts: Array<{
        id: number;
        label: string;
        contract_number: string | null;
        reservation_number: string | null;
        renter_name: string | null;
        car: string;
    }>;
    indexUrl: string;
    submitUrl: string;
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const photoFiles = ref<File[]>([]);
const photoTypes = ref<string[]>([]);
const photoNotes = ref<string[]>([]);

const form = useForm({
    contract_id: '',
    accident_at: '',
    location: '',
    latitude: '',
    longitude: '',
    description: '',
    police_report_number: '',
    has_injuries: false,
    third_party_involved: false,
    third_party_name: '',
    third_party_phone: '',
    third_party_plate_number: '',
    notes: '',
    photos: [] as File[],
    photo_types: [] as string[],
    photo_notes: [] as string[],
});

const selectedContract = computed(() => props.contracts.find((contract) => String(contract.id) === form.contract_id) ?? null);

function onPhotosSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    photoFiles.value = Array.from(input.files || []);
    photoTypes.value = photoFiles.value.map((_, index) => photoTypes.value[index] || 'scene');
    photoNotes.value = photoFiles.value.map((_, index) => photoNotes.value[index] || '');
}

function submit() {
    form.photos = photoFiles.value;
    form.photo_types = photoTypes.value;
    form.photo_notes = photoNotes.value;
    form.post(props.submitUrl, { forceFormData: true });
}
</script>

<template>
    <Head :title="localize('New Accident Report', 'بلاغ حادث جديد')" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ localize('New Accident Report', 'بلاغ حادث جديد') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ localize('Create an accident report linked to an existing contract.', 'إنشاء بلاغ حادث مرتبط بعقد موجود.') }}
                    </p>
                </div>
                <Link :href="indexUrl">
                    <Button variant="outline">{{ localize('Back', 'رجوع') }}</Button>
                </Link>
            </div>

            <form class="space-y-6 rounded-lg border bg-white p-6 shadow-sm" @submit.prevent="submit">
                <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <Label for="contract_id">{{ localize('Contract', 'العقد') }}</Label>
                        <select id="contract_id" v-model="form.contract_id" class="mt-1 block h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                            <option value="">{{ localize('Select contract', 'اختر العقد') }}</option>
                            <option v-for="contract in contracts" :key="contract.id" :value="String(contract.id)">
                                {{ contract.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.contract_id" />
                    </div>

                    <div v-if="selectedContract" class="md:col-span-2 rounded-md border bg-muted/30 p-4 text-sm">
                        <div><strong>{{ localize('Contract:', 'العقد:') }}</strong> {{ selectedContract.contract_number || '-' }}</div>
                        <div><strong>{{ localize('Reservation:', 'الحجز:') }}</strong> {{ selectedContract.reservation_number || '-' }}</div>
                        <div><strong>{{ localize('Client:', 'العميل:') }}</strong> {{ selectedContract.renter_name || '-' }}</div>
                        <div><strong>{{ localize('Car:', 'السيارة:') }}</strong> {{ selectedContract.car }}</div>
                    </div>

                    <div>
                        <Label for="accident_at">{{ localize('Accident date/time', 'وقت وتاريخ الحادث') }}</Label>
                        <Input id="accident_at" v-model="form.accident_at" type="datetime-local" />
                        <InputError :message="form.errors.accident_at" />
                    </div>

                    <div>
                        <Label for="location">{{ localize('Location', 'الموقع') }}</Label>
                        <Input id="location" v-model="form.location" />
                        <InputError :message="form.errors.location" />
                    </div>

                    <div>
                        <Label for="latitude">{{ localize('Latitude', 'خط العرض') }}</Label>
                        <Input id="latitude" v-model="form.latitude" type="number" step="0.0000001" />
                        <InputError :message="form.errors.latitude" />
                    </div>

                    <div>
                        <Label for="longitude">{{ localize('Longitude', 'خط الطول') }}</Label>
                        <Input id="longitude" v-model="form.longitude" type="number" step="0.0000001" />
                        <InputError :message="form.errors.longitude" />
                    </div>

                    <div>
                        <Label for="police_report_number">{{ localize('Police report number', 'رقم تقرير الشرطة') }}</Label>
                        <Input id="police_report_number" v-model="form.police_report_number" />
                        <InputError :message="form.errors.police_report_number" />
                    </div>

                    <div class="flex items-center gap-6 pt-6">
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="form.has_injuries" type="checkbox" />
                            {{ localize('Has injuries', 'يوجد إصابات') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="form.third_party_involved" type="checkbox" />
                            {{ localize('Third party involved', 'يوجد طرف ثالث') }}
                        </label>
                    </div>

                    <div class="md:col-span-2">
                        <Label for="description">{{ localize('Description', 'وصف الحادث') }}</Label>
                        <textarea id="description" v-model="form.description" rows="4" class="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                        <InputError :message="form.errors.description" />
                    </div>
                </section>

                <section class="grid grid-cols-1 gap-4 rounded-md border p-4 md:grid-cols-3">
                    <div class="md:col-span-3">
                        <h2 class="font-semibold">{{ localize('Third party details', 'بيانات الطرف الثالث') }}</h2>
                    </div>
                    <div>
                        <Label for="third_party_name">{{ localize('Name', 'الاسم') }}</Label>
                        <Input id="third_party_name" v-model="form.third_party_name" />
                    </div>
                    <div>
                        <Label for="third_party_phone">{{ localize('Phone', 'الهاتف') }}</Label>
                        <Input id="third_party_phone" v-model="form.third_party_phone" />
                    </div>
                    <div>
                        <Label for="third_party_plate_number">{{ localize('Plate number', 'رقم اللوحة') }}</Label>
                        <Input id="third_party_plate_number" v-model="form.third_party_plate_number" />
                    </div>
                </section>

                <section class="space-y-4 rounded-md border p-4">
                    <div>
                        <h2 class="font-semibold">{{ localize('Photos', 'الصور') }}</h2>
                        <p class="text-sm text-muted-foreground">
                            {{ localize('You can upload up to 10 files: JPG, PNG, WEBP, or PDF.', 'يمكن رفع حتى 10 ملفات: JPG أو PNG أو WEBP أو PDF.') }}
                        </p>
                    </div>
                    <Input type="file" multiple accept="image/*,.pdf" @change="onPhotosSelected" />
                    <InputError :message="form.errors.photos" />

                    <div v-if="photoFiles.length" class="space-y-3">
                        <div v-for="(file, index) in photoFiles" :key="`${file.name}-${index}`" class="grid grid-cols-1 gap-3 rounded-md border p-3 md:grid-cols-3">
                            <div class="text-sm">
                                <div class="font-medium">{{ file.name }}</div>
                                <div class="text-xs text-muted-foreground">{{ Math.round(file.size / 1024) }} KB</div>
                            </div>
                            <select v-model="photoTypes[index]" class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm">
                                <option value="scene">{{ localize('Scene', 'مكان الحادث') }}</option>
                                <option value="damage">{{ localize('Damage', 'الضرر') }}</option>
                                <option value="police_report">{{ localize('Police report', 'تقرير الشرطة') }}</option>
                                <option value="other">{{ localize('Other', 'أخرى') }}</option>
                            </select>
                            <Input v-model="photoNotes[index]" :placeholder="localize('Photo note', 'ملاحظة الصورة')" />
                        </div>
                    </div>
                </section>

                <section>
                    <Label for="notes">{{ localize('Notes', 'ملاحظات') }}</Label>
                    <textarea id="notes" v-model="form.notes" rows="3" class="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                    <InputError :message="form.errors.notes" />
                </section>

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? localize('Saving...', 'جاري الحفظ...') : localize('Save Accident Report', 'حفظ بلاغ الحادث') }}
                    </Button>
                    <Link :href="indexUrl">
                        <Button type="button" variant="outline">{{ localize('Cancel', 'إلغاء') }}</Button>
                    </Link>
                </div>
            </form>
        </main>
    </AdminLayout>
</template>
