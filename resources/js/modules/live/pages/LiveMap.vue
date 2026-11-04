<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { usePoll } from '@inertiajs/vue3';
import AppLayout from '@/shared/layouts/AppLayout.vue';
import StatusPill from '@/shared/ui/StatusPill.vue';
import { useMap } from '@/shared/map/useMap';
import { dateTime, initials } from '@/shared/lib/format';
import type { LatLng, OrderRow, StatusMap, Zone } from '@/types';

interface RiderPosition {
    id: string;
    name: string;
    vehicle_plate: string | null;
    location: { latitude: number; longitude: number; at: string };
    minutes_ago: number;
}

const props = defineProps<{ orders: OrderRow[]; carriers: RiderPosition[]; zones: Zone[]; depot: LatLng; statuses: StatusMap; updatedAt: string }>();

// Cada 15 s pide al servidor solo pedidos y mensajeros (no recarga la página).
usePoll(15_000, { only: ['orders', 'carriers', 'updatedAt'] });

const counts = computed(() => props.orders.reduce<Record<string, number>>((a, o) => ((a[o.status] = (a[o.status] ?? 0) + 1), a), {}));
const mapEl = ref<HTMLElement | null>(null);
let fitted = false;
const { redraw } = useMap(mapEl, ({ map, layers, lib }) => {
    const { L, dotIcon, riderIcon, depotIcon, escapeHtml } = lib;
    for (const z of props.zones) L.polygon(z.polygon, { color: z.color, weight: 1, fillOpacity: 0.06, interactive: false }).addTo(layers);
    L.marker(props.depot, { icon: depotIcon() }).addTo(layers).bindTooltip('Bodega');
    for (const o of props.orders) {
        L.marker([o.latitude!, o.longitude!], { icon: dotIcon(props.statuses[o.status]?.color ?? '#64748b') })
            .addTo(layers)
            .bindPopup(`<b>${escapeHtml(o.recipient_name)}</b> · ${escapeHtml(o.tracking_code)}<br>${escapeHtml(o.neighborhood)}<br>${escapeHtml(props.statuses[o.status]?.label)}`);
    }
    for (const c of props.carriers) {
        L.marker([c.location.latitude, c.location.longitude], { icon: riderIcon(initials(c.name)), zIndexOffset: 800 })
            .addTo(layers)
            .bindTooltip(`${escapeHtml(c.name)}${c.vehicle_plate ? ' · ' + escapeHtml(c.vehicle_plate) : ''} · hace ${c.minutes_ago} min`, { direction: 'top' });
    }
    if (!fitted) {
        const pts: LatLng[] = [props.depot, ...props.orders.map((o) => [o.latitude!, o.longitude!] as LatLng), ...props.carriers.map((c) => [c.location.latitude, c.location.longitude] as LatLng)];
        if (pts.length > 1) map.fitBounds(pts, { padding: [30, 30], maxZoom: 15 });
        fitted = true;
    }
});
watch(() => [props.orders, props.carriers], redraw);
</script>

<template>
    <AppLayout title="Mapa en vivo">
        <template #actions><span class="small muted">Actualizado {{ dateTime(updatedAt) }} · cada 15 s</span></template>
        <div class="filters">
            <span v-for="(n, k) in counts" :key="k" class="fbtn"><StatusPill :status="k as string" :statuses="statuses" /> <b class="num">{{ n }}</b></span>
        </div>
        <section class="panel">
            <div ref="mapEl" class="map tall" style="border-radius: var(--radius) var(--radius) 0 0" role="region" aria-label="Mapa en vivo de pedidos y mensajeros" />
            <div class="pad row-wrap small">
                <span v-for="c in carriers" :key="c.id" class="rider"><span class="avatar sm">{{ initials(c.name) }}</span> {{ c.name }} <span v-if="c.vehicle_plate" class="plate">{{ c.vehicle_plate }}</span> <span class="muted">hace {{ c.minutes_ago }} min</span></span>
                <span v-if="!carriers.length" class="muted">Ningún mensajero ha reportado su ubicación en las últimas 3 horas. La ubicación la envía la app del mensajero.</span>
            </div>
        </section>
    </AppLayout>
</template>

<style scoped>
.rider { display: inline-flex; align-items: center; gap: 6px; margin-right: 14px; }
</style>
