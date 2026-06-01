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
const locationError = ref('');
const locationStatus = ref('');
const isResolvingLocation = ref(false);
const mapZoom = 15;
const tileSize = 256;
const mapCenterLatitude = ref(31.5017);
const mapCenterLongitude = ref(34.4668);
const mapDragState = ref<{
    pointerId: number;
    startX: number;
    startY: number;
    startCenterX: number;
    startCenterY: number;
    moved: boolean;
} | null>(null);

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
const locationQuery = computed(() => {
    const latitude = Number(form.latitude);
    const longitude = Number(form.longitude);

    if (Number.isFinite(latitude) && Number.isFinite(longitude)) {
        return `${latitude},${longitude}`;
    }

    return form.location.trim() || 'Gaza';
});
const mapsLink = computed(() => `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(locationQuery.value)}`);
const mapCenter = computed(() => {
    return { latitude: mapCenterLatitude.value, longitude: mapCenterLongitude.value };
});
const mapCenterWorld = computed(() => latLngToWorldPixel(mapCenter.value.latitude, mapCenter.value.longitude, mapZoom));
const mapTiles = computed(() => {
    const tileCount = 2 ** mapZoom;
    const center = mapCenterWorld.value;
    const centerTileX = Math.floor(center.x / tileSize);
    const centerTileY = Math.floor(center.y / tileSize);
    const tiles = [];

    for (let row = -2; row <= 2; row += 1) {
        for (let column = -3; column <= 3; column += 1) {
            const tileX = centerTileX + column;
            const tileY = Math.min(Math.max(centerTileY + row, 0), tileCount - 1);
            const wrappedTileX = ((tileX % tileCount) + tileCount) % tileCount;

            tiles.push({
                key: `${tileX}-${tileY}`,
                url: `https://tile.openstreetmap.org/${mapZoom}/${wrappedTileX}/${tileY}.png`,
                style: {
                    left: `calc(50% + ${tileX * tileSize - center.x}px)`,
                    top: `calc(50% + ${tileY * tileSize - center.y}px)`,
                },
            });
        }
    }

    return tiles;
});
const selectedMarkerStyle = computed(() => {
    const latitude = Number(form.latitude);
    const longitude = Number(form.longitude);

    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
        return null;
    }

    const selected = latLngToWorldPixel(latitude, longitude, mapZoom);
    const center = mapCenterWorld.value;

    return {
        left: `calc(50% + ${selected.x - center.x}px)`,
        top: `calc(50% + ${selected.y - center.y}px)`,
    };
});

function onPhotosSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    photoFiles.value = Array.from(input.files || []);
    photoTypes.value = photoFiles.value.map((_, index) => photoTypes.value[index] || 'scene');
    photoNotes.value = photoFiles.value.map((_, index) => photoNotes.value[index] || '');
}

async function useCurrentLocation() {
    locationError.value = '';
    locationStatus.value = '';

    if (!('geolocation' in navigator)) {
        locationError.value = localize('Geolocation is not supported by this browser.', '\u0627\u0644\u0645\u062a\u0635\u0641\u062d \u0644\u0627 \u064a\u062f\u0639\u0645 \u062a\u062d\u062f\u064a\u062f \u0627\u0644\u0645\u0648\u0642\u0639.');
        return;
    }

    isResolvingLocation.value = true;
    navigator.geolocation.getCurrentPosition(
        async (position) => {
            form.latitude = position.coords.latitude.toFixed(7);
            form.longitude = position.coords.longitude.toFixed(7);
            setMapCenter(position.coords.latitude, position.coords.longitude);
            locationStatus.value = localize('Coordinates captured.', '\u062a\u0645 \u0627\u0644\u062a\u0642\u0627\u0637 \u0627\u0644\u0625\u062d\u062f\u0627\u062b\u064a\u0627\u062a.');

            await reverseGeocodeLocation(position.coords.latitude, position.coords.longitude);
            isResolvingLocation.value = false;
        },
        (error) => {
            locationError.value = error.message || localize('Unable to read current location.', '\u062a\u0639\u0630\u0631 \u0642\u0631\u0627\u0621\u0629 \u0627\u0644\u0645\u0648\u0642\u0639 \u0627\u0644\u062d\u0627\u0644\u064a.');
            isResolvingLocation.value = false;
        },
        {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 60000,
        },
    );
}

async function reverseGeocodeLocation(latitude: number, longitude: number) {
    try {
        const response = await fetch(
            `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${latitude}&lon=${longitude}&accept-language=${locale.value}`,
        );

        if (!response.ok) return;

        const data = await response.json();
        const address = data?.address || {};
        const readableLocation =
            address.city ||
            address.town ||
            address.village ||
            address.suburb ||
            address.state ||
            address.country ||
            data?.display_name;

        if (readableLocation) {
            form.location = readableLocation;
        }
    } catch {
        // Coordinates are still valid even if reverse geocoding fails.
    }
}

async function selectMapPoint(event: MouseEvent) {
    const element = event.currentTarget as HTMLElement;
    const rect = element.getBoundingClientRect();
    const center = mapCenterWorld.value;
    const worldX = center.x + event.clientX - rect.left - rect.width / 2;
    const worldY = center.y + event.clientY - rect.top - rect.height / 2;
    const point = worldPixelToLatLng(worldX, worldY, mapZoom);

    form.latitude = point.latitude.toFixed(7);
    form.longitude = point.longitude.toFixed(7);
    locationStatus.value = localize('Map point selected.', '\u062a\u0645 \u062a\u062d\u062f\u064a\u062f \u0627\u0644\u0646\u0642\u0637\u0629 \u0645\u0646 \u0627\u0644\u062e\u0631\u064a\u0637\u0629.');
    locationError.value = '';

    await reverseGeocodeLocation(point.latitude, point.longitude);
}

function startMapDrag(event: PointerEvent) {
    if (event.button !== 0) return;

    const element = event.currentTarget as HTMLElement;
    const center = mapCenterWorld.value;

    element.setPointerCapture(event.pointerId);
    mapDragState.value = {
        pointerId: event.pointerId,
        startX: event.clientX,
        startY: event.clientY,
        startCenterX: center.x,
        startCenterY: center.y,
        moved: false,
    };
}

function moveMapDrag(event: PointerEvent) {
    const state = mapDragState.value;

    if (!state || state.pointerId !== event.pointerId) return;

    const deltaX = event.clientX - state.startX;
    const deltaY = event.clientY - state.startY;

    if (Math.abs(deltaX) > 3 || Math.abs(deltaY) > 3) {
        state.moved = true;
    }

    if (!state.moved) return;

    const point = worldPixelToLatLng(state.startCenterX - deltaX, state.startCenterY - deltaY, mapZoom);
    setMapCenter(point.latitude, point.longitude);
}

async function endMapDrag(event: PointerEvent) {
    const state = mapDragState.value;

    if (!state || state.pointerId !== event.pointerId) return;

    const element = event.currentTarget as HTMLElement;

    if (element.hasPointerCapture(event.pointerId)) {
        element.releasePointerCapture(event.pointerId);
    }

    mapDragState.value = null;

    if (!state.moved) {
        await selectMapPoint(event);
    }
}

function cancelMapDrag(event: PointerEvent) {
    const state = mapDragState.value;

    if (!state || state.pointerId !== event.pointerId) return;

    const element = event.currentTarget as HTMLElement;

    if (element.hasPointerCapture(event.pointerId)) {
        element.releasePointerCapture(event.pointerId);
    }

    mapDragState.value = null;
}

function setMapCenter(latitude: number, longitude: number) {
    mapCenterLatitude.value = Math.min(Math.max(latitude, -85.05112878), 85.05112878);
    mapCenterLongitude.value = Math.min(Math.max(longitude, -180), 180);
}

function latLngToWorldPixel(latitude: number, longitude: number, zoom: number) {
    const sinLatitude = Math.sin((Math.min(Math.max(latitude, -85.05112878), 85.05112878) * Math.PI) / 180);
    const scale = tileSize * 2 ** zoom;

    return {
        x: ((longitude + 180) / 360) * scale,
        y: (0.5 - Math.log((1 + sinLatitude) / (1 - sinLatitude)) / (4 * Math.PI)) * scale,
    };
}

function worldPixelToLatLng(x: number, y: number, zoom: number) {
    const scale = tileSize * 2 ** zoom;
    const longitude = (x / scale) * 360 - 180;
    const n = Math.PI - (2 * Math.PI * y) / scale;
    const latitude = (180 / Math.PI) * Math.atan(0.5 * (Math.exp(n) - Math.exp(-n)));

    return { latitude, longitude };
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

                    <div class="md:col-span-2 rounded-md border p-4">
                        <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <h2 class="font-semibold">{{ localize('Accident location', '\u0645\u0648\u0642\u0639 \u0627\u0644\u062d\u0627\u062f\u062b') }}</h2>
                                <p class="text-sm text-muted-foreground">
                                    {{ localize('Use current location to fill latitude, longitude, and a readable place name.', '\u0627\u0633\u062a\u062e\u062f\u0645 \u0627\u0644\u0645\u0648\u0642\u0639 \u0627\u0644\u062d\u0627\u0644\u064a \u0644\u062a\u0639\u0628\u0626\u0629 \u062e\u0637 \u0627\u0644\u0639\u0631\u0636 \u0648\u062e\u0637 \u0627\u0644\u0637\u0648\u0644 \u0648\u0627\u0633\u0645 \u0627\u0644\u0645\u0643\u0627\u0646.') }}
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <Button type="button" variant="outline" :disabled="isResolvingLocation" @click="useCurrentLocation">
                                    {{ isResolvingLocation ? localize('Reading...', '\u062c\u0627\u0631\u064a \u0627\u0644\u0642\u0631\u0627\u0621\u0629...') : localize('Use current location', '\u0627\u0633\u062a\u062e\u062f\u0645 \u0645\u0648\u0642\u0639\u064a \u0627\u0644\u062d\u0627\u0644\u064a') }}
                                </Button>
                                <a :href="mapsLink" target="_blank" class="inline-flex h-10 items-center rounded-md border px-3 text-sm hover:bg-muted">
                                    {{ localize('Open map', '\u0641\u062a\u062d \u0627\u0644\u062e\u0631\u064a\u0637\u0629') }}
                                </a>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div>
                                <Label for="location">{{ localize('Location name', '\u0627\u0633\u0645 \u0627\u0644\u0645\u0648\u0642\u0639') }}</Label>
                                <Input id="location" v-model="form.location" placeholder="Gaza" />
                                <InputError :message="form.errors.location" />
                            </div>

                            <div>
                                <Label for="latitude">{{ localize('Latitude', '\u062e\u0637 \u0627\u0644\u0639\u0631\u0636') }}</Label>
                                <Input id="latitude" v-model="form.latitude" type="number" step="0.0000001" />
                                <InputError :message="form.errors.latitude" />
                            </div>

                            <div>
                                <Label for="longitude">{{ localize('Longitude', '\u062e\u0637 \u0627\u0644\u0637\u0648\u0644') }}</Label>
                                <Input id="longitude" v-model="form.longitude" type="number" step="0.0000001" />
                                <InputError :message="form.errors.longitude" />
                            </div>
                        </div>

                        <p v-if="locationStatus" class="mt-3 text-sm text-green-700">{{ locationStatus }}</p>
                        <p v-if="locationError" class="mt-3 text-sm text-red-600">{{ locationError }}</p>

                        <div
                            class="relative mt-4 h-72 w-full cursor-grab touch-none overflow-hidden rounded-md border bg-slate-100 active:cursor-grabbing"
                            role="button"
                            tabindex="0"
                            @pointercancel="cancelMapDrag"
                            @pointerdown="startMapDrag"
                            @pointermove="moveMapDrag"
                            @pointerup="endMapDrag"
                        >
                            <img
                                v-for="tile in mapTiles"
                                :key="tile.key"
                                :src="tile.url"
                                alt=""
                                class="absolute h-64 w-64 select-none"
                                draggable="false"
                                :style="tile.style"
                            />
                            <div
                                v-if="selectedMarkerStyle"
                                class="absolute z-10 h-5 w-5 -translate-x-1/2 -translate-y-full rounded-full border-2 border-white bg-red-600 shadow-lg"
                                :style="selectedMarkerStyle"
                            >
                                <div class="absolute left-1/2 top-full h-2 w-2 -translate-x-1/2 -translate-y-1 rotate-45 bg-red-600" />
                            </div>
                            <div class="pointer-events-none absolute inset-x-0 bottom-0 bg-white/80 px-3 py-2 text-xs text-muted-foreground">
                                {{ localize('Drag the map to move around, then click to place the red point and update coordinates.', '\u0627\u0633\u062d\u0628 \u0627\u0644\u062e\u0631\u064a\u0637\u0629 \u0644\u0644\u062a\u0646\u0642\u0644\u060c \u062b\u0645 \u0627\u0636\u063a\u0637 \u0644\u0648\u0636\u0639 \u0627\u0644\u0646\u0642\u0637\u0629 \u0627\u0644\u062d\u0645\u0631\u0627\u0621 \u0648\u062a\u062d\u062f\u064a\u062b \u0627\u0644\u0625\u062d\u062f\u0627\u062b\u064a\u0627\u062a.') }}
                            </div>
                        </div>
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
