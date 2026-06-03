<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import { computed } from 'vue';

type ViewSide = 'front' | 'rear' | 'left' | 'right' | 'top';

interface ZoneView {
    code: string;
    label: string;
    view_side: ViewSide;
    x: number;
    y: number;
    width: number;
    height: number;
}

interface Item {
    zone_code: string;
    quantity: number;
}

const props = defineProps<{
    zoneViews: ZoneView[];
    items: Item[];
    selectedZoneCode?: string | null;
    currentView: ViewSide;
}>();

const emit = defineEmits<{
    (event: 'update:currentView', value: ViewSide): void;
    (
        event: 'select-zone',
        payload: { zoneCode: string; viewSide: ViewSide; x: number; y: number },
    ): void;
}>();

const { t } = useTrans();

const views = computed<Array<{ value: ViewSide; label: string }>>(() => [
    {
        value: 'left',
        label: t('dashboard.admin.damage_reports.view_sides.left'),
    },
    {
        value: 'front',
        label: t('dashboard.admin.damage_reports.view_sides.front'),
    },
    {
        value: 'right',
        label: t('dashboard.admin.damage_reports.view_sides.right'),
    },
    {
        value: 'rear',
        label: t('dashboard.admin.damage_reports.view_sides.rear'),
    },
    {
        value: 'top',
        label: t('dashboard.admin.damage_reports.view_sides.top'),
    },
]);

const assetVersion = '20260324-3';

const viewAssets: Record<
    ViewSide,
    {
        src: string;
        x: number;
        y: number;
        width: number;
        height: number;
        preserveAspectRatio?: string;
    }
> = {
    left: {
        src: `/images/car-damage-views/left.svg?v=${assetVersion}`,
        x: 8,
        y: 34,
        width: 304,
        height: 104,
    },
    front: {
        src: `/images/car-damage-views/front.svg?v=${assetVersion}`,
        x: 92,
        y: 6,
        width: 136,
        height: 168,
    },
    right: {
        src: `/images/car-damage-views/right.svg?v=${assetVersion}`,
        x: 8,
        y: 34,
        width: 304,
        height: 104,
    },
    rear: {
        src: `/images/car-damage-views/rear.svg?v=${assetVersion}`,
        x: 68,
        y: 8,
        width: 184,
        height: 158,
    },
    top: {
        src: `/images/car-damage-views/top.svg?v=${assetVersion}`,
        x: 20,
        y: 10,
        width: 280,
        height: 144,
    },
};

const visibleZones = computed(() =>
    props.zoneViews.filter((zone) => zone.view_side === props.currentView),
);

const currentViewAsset = computed(() => viewAssets[props.currentView]);

const quantityByZone = computed<Record<string, number>>(() =>
    props.items.reduce<Record<string, number>>((acc, item) => {
        const key = item.zone_code;
        acc[key] = (acc[key] ?? 0) + Number(item.quantity || 0);
        return acc;
    }, {}),
);

function selectZone(zone: ZoneView) {
    emit('select-zone', {
        zoneCode: zone.code,
        viewSide: zone.view_side,
        x: Number((zone.x + zone.width / 2).toFixed(2)),
        y: Number((zone.y + zone.height / 2).toFixed(2)),
    });
}
</script>

<template>
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="mb-4 flex flex-wrap gap-2">
            <button
                v-for="view in views"
                :key="view.value"
                type="button"
                class="rounded-full px-3 py-1.5 text-sm transition"
                :class="
                    currentView === view.value
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                "
                @click="emit('update:currentView', view.value)"
            >
                {{ view.label }}
            </button>
        </div>

        <div
            class="overflow-hidden rounded-2xl border border-slate-200 bg-[radial-gradient(circle_at_top,#f8fafc,white_55%)]"
        >
            <svg viewBox="0 0 320 180" class="h-[260px] w-full">
                <image
                    :href="currentViewAsset.src"
                    :x="currentViewAsset.x"
                    :y="currentViewAsset.y"
                    :width="currentViewAsset.width"
                    :height="currentViewAsset.height"
                    preserveAspectRatio="xMidYMid meet"
                    class="pointer-events-none"
                />

                <g
                    v-for="zone in visibleZones"
                    :key="`${zone.view_side}:${zone.code}:${zone.x}:${zone.y}`"
                >
                    <rect
                        :x="zone.x"
                        :y="zone.y"
                        :width="zone.width"
                        :height="zone.height"
                        rx="8"
                        class="cursor-pointer transition"
                        :fill="
                            selectedZoneCode === zone.code
                                ? '#0f172a'
                                : quantityByZone[zone.code]
                                  ? '#f97316'
                                  : '#dbeafe'
                        "
                        :stroke="
                            selectedZoneCode === zone.code
                                ? '#0f172a'
                                : '#64748b'
                        "
                        stroke-width="1.5"
                        fill-opacity="0.62"
                        @click="selectZone(zone)"
                    />
                    <text
                        :x="zone.x + zone.width / 2"
                        :y="zone.y + zone.height / 2 + 4"
                        text-anchor="middle"
                        font-size="8"
                        :fill="
                            selectedZoneCode === zone.code ||
                            quantityByZone[zone.code]
                                ? 'white'
                                : '#1e293b'
                        "
                        class="pointer-events-none select-none"
                    >
                        {{ zone.label }}
                    </text>
                    <g v-if="quantityByZone[zone.code]">
                        <circle
                            :cx="zone.x + zone.width - 4"
                            :cy="zone.y + 4"
                            r="10"
                            fill="#dc2626"
                        />
                        <text
                            :x="zone.x + zone.width - 4"
                            :y="zone.y + 7"
                            text-anchor="middle"
                            font-size="10"
                            fill="white"
                            class="pointer-events-none select-none"
                        >
                            {{ quantityByZone[zone.code] }}
                        </text>
                    </g>
                </g>
            </svg>
        </div>
    </div>
</template>
