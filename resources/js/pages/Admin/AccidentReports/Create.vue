<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/composables/useTrans';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Building2, CarFront, UserRound } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    contracts: Array<{
        id: number;
        label: string;
        contract_number: string | null;
        reservation_number: string | null;
        renter_name: string | null;
        renter_phone: string | null;
        renter_id_number: string | null;
        car_license_plate: string | null;
        car: string;
    }>;
    cars: Array<{ id: number; branch_id: number | null; label: string; license_plate: string | null; branch_name: string | null }>;
    branches: Array<{ id: number; name: string }>;
    employees: Array<{ id: number; name: string; email: string; branch_id: number | null; branch_name: string | null }>;
    responsibilities: Array<{ value: string; label: string }>;
    locationTypes: Array<{ value: string; label: string }>;
    indexUrl: string;
    submitUrl: string;
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

type AccidentContext = 'contract' | 'employee' | 'branch';
type PartyDetails = {
    vehicle_no: string;
    driver_name: string;
    address_tel: string;
    driving_license_no_category: string;
    sex_nationality: string;
    insurance_company: string;
    insurance_type: string;
    insurance_policy_no: string;
};

const accidentContext = ref<AccidentContext>('contract');
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
    accident_context: 'contract',
    contract_id: '',
    car_id: '',
    branch_id: '',
    employee_id: '',
    responsibility: 'customer',
    location_type: 'road',
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
    mrta_accident_types: [] as string[],
    mrta_first_party: {
        vehicle_no: '',
        driver_name: '',
        address_tel: '',
        driving_license_no_category: '',
        sex_nationality: '',
        insurance_company: '',
        insurance_type: '',
        insurance_policy_no: '',
    } as PartyDetails,
    mrta_second_party: {
        vehicle_no: '',
        driver_name: '',
        address_tel: '',
        driving_license_no_category: '',
        sex_nationality: '',
        insurance_company: '',
        insurance_type: '',
        insurance_policy_no: '',
    } as PartyDetails,
    mrta_witnesses: [
        { name: '', address: '', phone: '' },
        { name: '', address: '', phone: '' },
    ],
    mrta_accident_causes: [] as string[],
    mrta_vehicle_damages: {
        first_party_notes: '',
        second_party_notes: '',
    },
    mrta_insurance: {
        policy_no: '',
        type: '',
        claim_no: '',
        company_will_repair: false,
        technical_opinion_required: false,
        signatory_name: '',
    },
    mrta_signatures: {
        first_party_name: '',
        second_party_name: '',
    },
    photos: [] as File[],
    photo_types: [] as string[],
    photo_notes: [] as string[],
});

const selectedContract = computed(() => props.contracts.find((contract) => String(contract.id) === form.contract_id) ?? null);
const accidentTypeOptions = computed(() => [
    { value: 'stationary_object', label: localize('Collision against a stationary object', '\u0627\u0635\u0637\u062f\u0627\u0645 \u0628\u062c\u0633\u0645 \u062b\u0627\u0628\u062a') },
    { value: 'vehicle_collision', label: localize('Collision between vehicles', '\u0627\u0635\u0637\u062f\u0627\u0645 \u0628\u064a\u0646 \u0645\u0631\u0643\u0628\u0627\u062a') },
    { value: 'roll_over', label: localize('Roll-over', '\u062a\u062f\u0647\u0648\u0631') },
]);
const causeOptions = computed(() => [
    { value: 'over_speed', label: localize('Over-speed', '\u0627\u0644\u0633\u0631\u0639\u0629') },
    { value: 'negligence', label: localize('Negligence', '\u0627\u0644\u0625\u0647\u0645\u0627\u0644') },
    { value: 'fatigue', label: localize('Fatigue', '\u0627\u0644\u0625\u0631\u0647\u0627\u0642') },
    { value: 'overtaking', label: localize('Overtaking', '\u0627\u0644\u062a\u062c\u0627\u0648\u0632') },
    { value: 'weather_conditions', label: localize('Weather Conditions', '\u0627\u0644\u0637\u0642\u0633') },
    { value: 'sudden_halt', label: localize('Sudden halt', '\u0627\u0644\u0648\u0642\u0648\u0641 \u0627\u0644\u0645\u0641\u0627\u062c\u0626') },
    { value: 'no_safety_distance', label: localize('No safety distance', '\u0639\u062f\u0645 \u062a\u0631\u0643 \u0645\u0633\u0627\u0641\u0629 \u0627\u0644\u0623\u0645\u0627\u0646') },
    { value: 'wrong_action', label: localize('Wrong action', '\u0633\u0648\u0621 \u0627\u0644\u062a\u0635\u0631\u0641') },
    { value: 'vehicle_defects', label: localize('Vehicle defects', '\u0639\u064a\u0648\u0628 \u0627\u0644\u0645\u0631\u0643\u0628\u0629') },
    { value: 'road_defects', label: localize('Road defects', '\u0639\u064a\u0648\u0628 \u0627\u0644\u0637\u0631\u064a\u0642') },
    { value: 'using_gsm', label: localize('Using GSM', '\u0627\u0633\u062a\u062e\u062f\u0627\u0645 \u0627\u0644\u0647\u0627\u062a\u0641') },
]);
const partyFields = computed<Array<{ key: keyof PartyDetails; label: string }>>(() => [
    { key: 'vehicle_no', label: localize('Vehicle No.', '\u0631\u0642\u0645 \u0627\u0644\u0645\u0631\u0643\u0628\u0629') },
    { key: 'driver_name', label: localize("Driver's Name", '\u0627\u0633\u0645 \u0627\u0644\u0633\u0627\u0626\u0642') },
    { key: 'address_tel', label: localize('Address / Tel. No.', '\u0627\u0644\u0639\u0646\u0648\u0627\u0646 / \u0627\u0644\u0647\u0627\u062a\u0641') },
    { key: 'driving_license_no_category', label: localize('Driving License No. / Category', '\u0631\u0642\u0645 \u0627\u0644\u0631\u062e\u0635\u0629 / \u0627\u0644\u0641\u0626\u0629') },
    { key: 'sex_nationality', label: localize('Sex / Nationality', '\u0627\u0644\u062c\u0646\u0633 / \u0627\u0644\u062c\u0646\u0633\u064a\u0629') },
    { key: 'insurance_company', label: localize('Insurance Company', '\u0634\u0631\u0643\u0629 \u0627\u0644\u062a\u0623\u0645\u064a\u0646') },
    { key: 'insurance_type', label: localize('Type of Insurance', '\u0646\u0648\u0639 \u0627\u0644\u062a\u0623\u0645\u064a\u0646') },
    { key: 'insurance_policy_no', label: localize('Insurance Policy No.', '\u0631\u0642\u0645 \u0627\u0644\u0648\u062b\u064a\u0642\u0629') },
]);
const contextOptions = computed(() => [
    {
        key: 'contract' as const,
        icon: UserRound,
        title: localize('With customer', '\u0645\u0639 \u0627\u0644\u0639\u0645\u064a\u0644'),
        description: localize(
            'Use when the car is under an active rental contract.',
            '\u0644\u0644\u062d\u0648\u0627\u062f\u062b \u0623\u062b\u0646\u0627\u0621 \u0641\u062a\u0631\u0629 \u0639\u0642\u062f \u0625\u064a\u062c\u0627\u0631 \u0646\u0634\u0637.',
        ),
        state: localize('Active workflow', '\u0645\u0633\u0627\u0631 \u0645\u0641\u0639\u0644'),
    },
    {
        key: 'employee' as const,
        icon: CarFront,
        title: localize('With employee', '\u0645\u0639 \u0645\u0648\u0638\u0641'),
        description: localize(
            'For transfers, refueling, inspections, or staff custody.',
            '\u0644\u0644\u0646\u0642\u0644 \u0628\u064a\u0646 \u0627\u0644\u0641\u0631\u0648\u0639\u060c \u0627\u0644\u062a\u0639\u0628\u0626\u0629\u060c \u0627\u0644\u0641\u062d\u0635\u060c \u0623\u0648 \u0639\u0647\u062f\u0629 \u0627\u0644\u0645\u0648\u0638\u0641.',
        ),
        state: localize('Active workflow', '\u0645\u0633\u0627\u0631 \u0645\u0641\u0639\u0644'),
    },
    {
        key: 'branch' as const,
        icon: Building2,
        title: localize('At office or gate', '\u0639\u0646\u062f \u0627\u0644\u0645\u0643\u062a\u0628 \u0623\u0648 \u0627\u0644\u0628\u0648\u0627\u0628\u0629'),
        description: localize(
            'For parking, branch entrance, or handover-area incidents.',
            '\u0644\u062d\u0648\u0627\u062f\u062b \u0627\u0644\u0645\u0648\u0627\u0642\u0641\u060c \u0645\u062f\u062e\u0644 \u0627\u0644\u0641\u0631\u0639\u060c \u0623\u0648 \u0645\u0646\u0637\u0642\u0629 \u0627\u0644\u062a\u0633\u0644\u064a\u0645.',
        ),
        state: localize('Active workflow', '\u0645\u0633\u0627\u0631 \u0645\u0641\u0639\u0644'),
    },
]);
const selectedContext = computed(() => contextOptions.value.find((option) => option.key === accidentContext.value) ?? contextOptions.value[0]);
const filteredCars = computed(() => {
    if (!form.branch_id) return props.cars;

    return props.cars.filter((car) => String(car.branch_id ?? '') === form.branch_id);
});
const filteredEmployees = computed(() => {
    if (!form.branch_id) return props.employees;

    return props.employees.filter((employee) => String(employee.branch_id ?? '') === form.branch_id);
});
const selectedCar = computed(() => props.cars.find((car) => String(car.id) === form.car_id) ?? null);
const selectedBranch = computed(() => props.branches.find((branch) => String(branch.id) === form.branch_id) ?? null);
const selectedEmployee = computed(() => props.employees.find((employee) => String(employee.id) === form.employee_id) ?? null);
const canSubmit = computed(() => true);
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

function syncMrtaFirstPartyFromWorkflow() {
    if (accidentContext.value === 'contract') {
        form.mrta_first_party.vehicle_no = selectedContract.value?.car_license_plate || '';
        form.mrta_first_party.driver_name = selectedContract.value?.renter_name || '';
        form.mrta_first_party.address_tel = selectedContract.value?.renter_phone || '';
        form.mrta_first_party.driving_license_no_category = selectedContract.value?.renter_id_number || '';
        return;
    }

    form.mrta_first_party.vehicle_no = selectedCar.value?.license_plate || '';

    if (accidentContext.value === 'employee') {
        form.mrta_first_party.driver_name = selectedEmployee.value?.name || '';
        form.mrta_first_party.address_tel = selectedEmployee.value?.email || '';
        form.mrta_first_party.driving_license_no_category = '';
        return;
    }

    form.mrta_first_party.driver_name = selectedBranch.value?.name || '';
    form.mrta_first_party.address_tel = '';
    form.mrta_first_party.driving_license_no_category = '';
}

function syncMrtaSecondPartyFromThirdParty() {
    form.mrta_second_party.vehicle_no = form.third_party_plate_number || '';
    form.mrta_second_party.driver_name = form.third_party_name || '';
    form.mrta_second_party.address_tel = form.third_party_phone || '';
}

function submit() {
    form.accident_context = accidentContext.value;
    form.photos = photoFiles.value;
    form.photo_types = photoTypes.value;
    form.photo_notes = photoNotes.value;
    form.post(props.submitUrl, { forceFormData: true });
}

watch(accidentContext, (context) => {
    form.accident_context = context;

    if (context === 'contract') {
        form.car_id = '';
        form.branch_id = '';
        form.employee_id = '';
        form.responsibility = 'customer';
        form.location_type = 'road';
        return;
    }

    form.contract_id = '';
    form.responsibility = context === 'employee' ? 'employee' : 'unknown';
    form.location_type = context === 'branch' ? 'branch_gate' : 'road';

    if (context === 'branch') {
        form.employee_id = '';
    }
});

watch(() => form.branch_id, () => {
    if (form.car_id && !filteredCars.value.some((car) => String(car.id) === form.car_id)) {
        form.car_id = '';
    }

    if (form.employee_id && !filteredEmployees.value.some((employee) => String(employee.id) === form.employee_id)) {
        form.employee_id = '';
    }
});

watch(
    () => [
        accidentContext.value,
        form.contract_id,
        form.car_id,
        form.branch_id,
        form.employee_id,
        selectedContract.value?.car_license_plate,
        selectedContract.value?.renter_name,
        selectedContract.value?.renter_phone,
        selectedContract.value?.renter_id_number,
        selectedCar.value?.license_plate,
        selectedBranch.value?.name,
        selectedEmployee.value?.name,
        selectedEmployee.value?.email,
    ],
    syncMrtaFirstPartyFromWorkflow,
    { immediate: true },
);

watch(
    () => [form.third_party_name, form.third_party_phone, form.third_party_plate_number],
    syncMrtaSecondPartyFromThirdParty,
    { immediate: true },
);
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

            <section class="space-y-4 rounded-lg border bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">{{ localize('Accident workflow', '\u0645\u0633\u0627\u0631 \u0627\u0644\u062d\u0627\u062f\u062b') }}</h2>
                        <p class="text-sm text-muted-foreground">
                            {{ localize('Choose who had custody of the car when the accident happened.', '\u062d\u062f\u062f \u0645\u0646 \u0643\u0627\u0646\u062a \u0627\u0644\u0633\u064a\u0627\u0631\u0629 \u0628\u0639\u0647\u062f\u062a\u0647 \u0648\u0642\u062a \u0627\u0644\u062d\u0627\u062f\u062b.') }}
                        </p>
                    </div>
                    <span class="rounded-md border px-3 py-1 text-sm font-medium text-muted-foreground">
                        {{ selectedContext.state }}
                    </span>
                </div>

                <div class="grid grid-cols-1 gap-3 lg:grid-cols-3">
                    <button
                        v-for="option in contextOptions"
                        :key="option.key"
                        type="button"
                        class="flex h-full min-h-32 gap-3 rounded-md border p-4 text-start transition hover:border-primary/60 hover:bg-muted/30"
                        :class="{
                            'border-primary bg-primary/5 ring-1 ring-primary/30': accidentContext === option.key,
                        }"
                        @click="accidentContext = option.key"
                    >
                        <component :is="option.icon" class="mt-0.5 h-5 w-5 shrink-0 text-primary" />
                        <span>
                            <span class="block font-semibold">{{ option.title }}</span>
                            <span class="mt-1 block text-sm leading-6 text-muted-foreground">{{ option.description }}</span>
                        </span>
                    </button>
                </div>

                <div class="rounded-md border bg-muted/20 p-4 text-sm">
                    <template v-if="accidentContext === 'contract'">
                        <strong>{{ localize('Current form:', '\u0627\u0644\u0646\u0645\u0648\u0630\u062c \u0627\u0644\u062d\u0627\u0644\u064a:') }}</strong>
                        {{ localize('select a contract, then record the accident details, location, police report, third party, and photos.', '\u0627\u062e\u062a\u0631 \u0627\u0644\u0639\u0642\u062f\u060c \u062b\u0645 \u0633\u062c\u0644 \u062a\u0641\u0627\u0635\u064a\u0644 \u0627\u0644\u062d\u0627\u062f\u062b \u0648\u0627\u0644\u0645\u0648\u0642\u0639 \u0648\u062a\u0642\u0631\u064a\u0631 \u0627\u0644\u0634\u0631\u0637\u0629 \u0648\u0627\u0644\u0637\u0631\u0641 \u0627\u0644\u062b\u0627\u0644\u062b \u0648\u0627\u0644\u0635\u0648\u0631.') }}
                    </template>
                    <template v-else-if="accidentContext === 'employee'">
                        <strong>{{ localize('Next workflow:', '\u0627\u0644\u0645\u0633\u0627\u0631 \u0627\u0644\u0642\u0627\u062f\u0645:') }}</strong>
                        {{ localize('this will ask for car, branch, responsible employee, custody reason, responsibility, and accident evidence.', '\u0633\u064a\u0637\u0644\u0628 \u0627\u0644\u0633\u064a\u0627\u0631\u0629 \u0648\u0627\u0644\u0641\u0631\u0639 \u0648\u0627\u0644\u0645\u0648\u0638\u0641 \u0627\u0644\u0645\u0633\u0624\u0648\u0644 \u0648\u0633\u0628\u0628 \u0627\u0644\u0639\u0647\u062f\u0629 \u0648\u0627\u0644\u0645\u0633\u0624\u0648\u0644\u064a\u0629 \u0648\u0623\u062f\u0644\u0629 \u0627\u0644\u062d\u0627\u062f\u062b.') }}
                    </template>
                    <template v-else>
                        <strong>{{ localize('Next workflow:', '\u0627\u0644\u0645\u0633\u0627\u0631 \u0627\u0644\u0642\u0627\u062f\u0645:') }}</strong>
                        {{ localize('this will ask for car, branch area, office/gate location, third party details, responsibility, and accident evidence.', '\u0633\u064a\u0637\u0644\u0628 \u0627\u0644\u0633\u064a\u0627\u0631\u0629 \u0648\u0645\u0646\u0637\u0642\u0629 \u0627\u0644\u0641\u0631\u0639 \u0648\u0645\u0648\u0642\u0639 \u0627\u0644\u0645\u0643\u062a\u0628 \u0623\u0648 \u0627\u0644\u0628\u0648\u0627\u0628\u0629 \u0648\u0628\u064a\u0627\u0646\u0627\u062a \u0627\u0644\u0637\u0631\u0641 \u0627\u0644\u062b\u0627\u0644\u062b \u0648\u0627\u0644\u0645\u0633\u0624\u0648\u0644\u064a\u0629 \u0648\u0623\u062f\u0644\u0629 \u0627\u0644\u062d\u0627\u062f\u062b.') }}
                    </template>
                </div>
            </section>

            <form class="space-y-6 rounded-lg border bg-white p-6 shadow-sm" @submit.prevent="submit">
                <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div v-if="accidentContext === 'contract'" class="md:col-span-2">
                        <Label for="contract_id">{{ localize('Contract', 'العقد') }}</Label>
                        <select id="contract_id" v-model="form.contract_id" class="mt-1 block h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                            <option value="">{{ localize('Select contract', 'اختر العقد') }}</option>
                            <option v-for="contract in contracts" :key="contract.id" :value="String(contract.id)">
                                {{ contract.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.contract_id" />
                    </div>

                    <div v-if="accidentContext === 'contract' && selectedContract" class="md:col-span-2 rounded-md border bg-muted/30 p-4 text-sm">
                        <div><strong>{{ localize('Contract:', 'العقد:') }}</strong> {{ selectedContract.contract_number || '-' }}</div>
                        <div><strong>{{ localize('Reservation:', 'الحجز:') }}</strong> {{ selectedContract.reservation_number || '-' }}</div>
                        <div><strong>{{ localize('Client:', 'العميل:') }}</strong> {{ selectedContract.renter_name || '-' }}</div>
                        <div><strong>{{ localize('Car:', 'السيارة:') }}</strong> {{ selectedContract.car }}</div>
                    </div>

                    <section v-if="accidentContext !== 'contract'" class="md:col-span-2 grid grid-cols-1 gap-4 rounded-md border p-4 md:grid-cols-2">
                        <div>
                            <Label for="branch_id">{{ localize('Branch', '\u0627\u0644\u0641\u0631\u0639') }}</Label>
                            <select id="branch_id" v-model="form.branch_id" class="mt-1 block h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                <option value="">{{ localize('Select branch', '\u0627\u062e\u062a\u0631 \u0627\u0644\u0641\u0631\u0639') }}</option>
                                <option v-for="branch in branches" :key="branch.id" :value="String(branch.id)">
                                    {{ branch.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors.branch_id" />
                        </div>

                        <div>
                            <Label for="car_id">{{ localize('Car', '\u0627\u0644\u0633\u064a\u0627\u0631\u0629') }}</Label>
                            <select id="car_id" v-model="form.car_id" class="mt-1 block h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                <option value="">{{ localize('Select car', '\u0627\u062e\u062a\u0631 \u0627\u0644\u0633\u064a\u0627\u0631\u0629') }}</option>
                                <option v-for="car in filteredCars" :key="car.id" :value="String(car.id)">
                                    {{ car.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.car_id" />
                        </div>

                        <div v-if="accidentContext === 'employee'">
                            <Label for="employee_id">{{ localize('Responsible employee', '\u0627\u0644\u0645\u0648\u0638\u0641 \u0627\u0644\u0645\u0633\u0624\u0648\u0644') }}</Label>
                            <select id="employee_id" v-model="form.employee_id" class="mt-1 block h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                <option value="">{{ localize('Select employee', '\u0627\u062e\u062a\u0631 \u0627\u0644\u0645\u0648\u0638\u0641') }}</option>
                                <option v-for="employee in filteredEmployees" :key="employee.id" :value="String(employee.id)">
                                    {{ employee.name }} - {{ employee.email }}
                                </option>
                            </select>
                            <InputError :message="form.errors.employee_id" />
                        </div>

                        <div>
                            <Label for="responsibility">{{ localize('Responsibility', '\u0627\u0644\u0645\u0633\u0624\u0648\u0644\u064a\u0629') }}</Label>
                            <select id="responsibility" v-model="form.responsibility" class="mt-1 block h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                <option v-for="item in responsibilities" :key="item.value" :value="item.value">
                                    {{ item.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.responsibility" />
                        </div>

                        <div>
                            <Label for="location_type">{{ localize('Location type', '\u0646\u0648\u0639 \u0627\u0644\u0645\u0648\u0642\u0639') }}</Label>
                            <select id="location_type" v-model="form.location_type" class="mt-1 block h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                <option v-for="item in locationTypes" :key="item.value" :value="item.value">
                                    {{ item.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.location_type" />
                        </div>

                        <div class="rounded-md bg-muted/30 p-3 text-sm">
                            <div><strong>{{ localize('Selected branch:', '\u0627\u0644\u0641\u0631\u0639 \u0627\u0644\u0645\u062d\u062f\u062f:') }}</strong> {{ selectedBranch?.name || '-' }}</div>
                            <div><strong>{{ localize('Selected car:', '\u0627\u0644\u0633\u064a\u0627\u0631\u0629 \u0627\u0644\u0645\u062d\u062f\u062f\u0629:') }}</strong> {{ selectedCar?.label || '-' }}</div>
                            <div v-if="accidentContext === 'employee'"><strong>{{ localize('Employee:', '\u0627\u0644\u0645\u0648\u0638\u0641:') }}</strong> {{ selectedEmployee?.name || '-' }}</div>
                        </div>
                    </section>

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

                <section class="space-y-5 rounded-md border p-4">
                    <div>
                        <h2 class="font-semibold">{{ localize('MRTA / Liva form details', 'بيانات نموذج MRTA / Liva') }}</h2>
                        <p class="text-sm text-muted-foreground">
                            {{ localize('These fields are used to generate the minor road traffic accident PDF.', 'هذه الحقول تستخدم لإنتاج ملف حادث المرور البسيط.') }}
                        </p>
                    </div>

                    <div>
                        <h3 class="mb-3 text-sm font-semibold">{{ localize('Type of accident', 'نوع الحادث') }}</h3>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                            <label v-for="option in accidentTypeOptions" :key="option.value" class="flex items-center gap-2 rounded-md border p-3 text-sm">
                                <input v-model="form.mrta_accident_types" type="checkbox" :value="option.value" />
                                {{ option.label }}
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                        <div class="rounded-md border p-4">
                            <h3 class="mb-3 text-sm font-semibold">{{ localize('First party', 'الطرف الأول') }}</h3>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div v-for="field in partyFields" :key="`first-${field.key}`">
                                    <Label :for="`mrta_first_${field.key}`">{{ field.label }}</Label>
                                    <Input :id="`mrta_first_${field.key}`" v-model="form.mrta_first_party[field.key]" />
                                </div>
                            </div>
                        </div>

                        <div class="rounded-md border p-4">
                            <h3 class="mb-3 text-sm font-semibold">{{ localize('Second party / faulty party', 'الطرف الثاني / المتسبب') }}</h3>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div v-for="field in partyFields" :key="`second-${field.key}`">
                                    <Label :for="`mrta_second_${field.key}`">{{ field.label }}</Label>
                                    <Input :id="`mrta_second_${field.key}`" v-model="form.mrta_second_party[field.key]" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-md border p-4">
                        <h3 class="mb-3 text-sm font-semibold">{{ localize('Witnesses', 'الشهود') }}</h3>
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                            <div v-for="(_, index) in form.mrta_witnesses" :key="index" class="grid grid-cols-1 gap-3 rounded-md bg-muted/20 p-3 md:grid-cols-3">
                                <div>
                                    <Label :for="`witness_${index}_name`">{{ localize('Name', 'الاسم') }}</Label>
                                    <Input :id="`witness_${index}_name`" v-model="form.mrta_witnesses[index].name" />
                                </div>
                                <div>
                                    <Label :for="`witness_${index}_address`">{{ localize('Address', 'العنوان') }}</Label>
                                    <Input :id="`witness_${index}_address`" v-model="form.mrta_witnesses[index].address" />
                                </div>
                                <div>
                                    <Label :for="`witness_${index}_phone`">{{ localize('Phone', 'الهاتف') }}</Label>
                                    <Input :id="`witness_${index}_phone`" v-model="form.mrta_witnesses[index].phone" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="mb-3 text-sm font-semibold">{{ localize('Causes of accident', 'أسباب الحادث') }}</h3>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                            <label v-for="option in causeOptions" :key="option.value" class="flex items-center gap-2 rounded-md border p-3 text-sm">
                                <input v-model="form.mrta_accident_causes" type="checkbox" :value="option.value" />
                                {{ option.label }}
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <Label for="mrta_first_party_damage">{{ localize('First vehicle damages', 'أضرار المركبة الأولى') }}</Label>
                            <textarea id="mrta_first_party_damage" v-model="form.mrta_vehicle_damages.first_party_notes" rows="3" class="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <Label for="mrta_second_party_damage">{{ localize('Second vehicle damages', 'أضرار المركبة الثانية') }}</Label>
                            <textarea id="mrta_second_party_damage" v-model="form.mrta_vehicle_damages.second_party_notes" rows="3" class="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 rounded-md border p-4 md:grid-cols-3">
                        <div class="md:col-span-3">
                            <h3 class="text-sm font-semibold">{{ localize('Insurance use', 'لاستعمال التأمين') }}</h3>
                        </div>
                        <div>
                            <Label for="mrta_policy_no">{{ localize('Policy No.', 'رقم الوثيقة') }}</Label>
                            <Input id="mrta_policy_no" v-model="form.mrta_insurance.policy_no" />
                        </div>
                        <div>
                            <Label for="mrta_insurance_type">{{ localize('Insurance type', 'نوع التأمين') }}</Label>
                            <Input id="mrta_insurance_type" v-model="form.mrta_insurance.type" />
                        </div>
                        <div>
                            <Label for="mrta_claim_no">{{ localize('Claim No.', 'رقم المطالبة') }}</Label>
                            <Input id="mrta_claim_no" v-model="form.mrta_insurance.claim_no" />
                        </div>
                        <div>
                            <Label for="mrta_signatory_name">{{ localize('Signatory name', 'اسم المخول بالتوقيع') }}</Label>
                            <Input id="mrta_signatory_name" v-model="form.mrta_insurance.signatory_name" />
                        </div>
                        <div class="flex items-center gap-6 pt-6 md:col-span-2">
                            <label class="flex items-center gap-2 text-sm">
                                <input v-model="form.mrta_insurance.company_will_repair" type="checkbox" />
                                {{ localize('Company will repair damages', 'الشركة ستصلح الأضرار') }}
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input v-model="form.mrta_insurance.technical_opinion_required" type="checkbox" />
                                {{ localize('Technical opinion required', 'مطلوب رأي فني') }}
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <Label for="mrta_first_signature">{{ localize('First party signature name', 'اسم توقيع الطرف الأول') }}</Label>
                            <Input id="mrta_first_signature" v-model="form.mrta_signatures.first_party_name" />
                        </div>
                        <div>
                            <Label for="mrta_second_signature">{{ localize('Second party signature name', 'اسم توقيع الطرف الثاني') }}</Label>
                            <Input id="mrta_second_signature" v-model="form.mrta_signatures.second_party_name" />
                        </div>
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

                <div class="flex flex-wrap items-center gap-3">
                    <Button type="submit" :disabled="form.processing || !canSubmit">
                        {{ form.processing ? localize('Saving...', 'جاري الحفظ...') : localize('Save Accident Report', 'حفظ بلاغ الحادث') }}
                    </Button>
                    <span v-if="!canSubmit" class="text-sm text-muted-foreground">
                        {{ localize('Saving this workflow needs the next backend step.', '\u062d\u0641\u0638 \u0647\u0630\u0627 \u0627\u0644\u0645\u0633\u0627\u0631 \u064a\u062d\u062a\u0627\u062c \u062e\u0637\u0648\u0629 \u0627\u0644\u0628\u0627\u0643\u0646\u062f \u0627\u0644\u0642\u0627\u062f\u0645\u0629.') }}
                    </span>
                    <Link :href="indexUrl">
                        <Button type="button" variant="outline">{{ localize('Cancel', 'إلغاء') }}</Button>
                    </Link>
                </div>
            </form>
        </main>
    </AdminLayout>
</template>
