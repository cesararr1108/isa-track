<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { LatLng, SharedProps, Zone } from '@/types';
import { money } from '@/shared/lib/format';

type MapLib = typeof import('@/shared/map/createMap');

const props = defineProps<{ zones: Zone[]; draft: LatLng[] | null; draftColor: string; quotePoint: LatLng | null }>();
const emit = defineEmits<{ addVertex: [LatLng]; pick: [LatLng] }>();

const el = ref<HTMLElement | null>(null);
const lib = shallowRef<MapLib | null>(null);
let map: import('leaflet').Map | null = null;
let layers: import('leaflet').LayerGroup | null = null;
const page = usePage<SharedProps>();

function draw() {
    if (!map || !layers || !lib.value) return;
    const { L, dotIcon } = lib.value;
    layers.clearLayers();
    for (const z of props.zones) {
        L.polygon(z.polygon, { color: z.color, weight: 2, fillOpacity: z.active ? 0.16 : 0.04, dashArray: z.active ? undefined : '4 4', interactive: false }).addTo(layers);
        L.marker(z.center, {
            interactive: false,
            icon: L.divIcon({ className: 'pin-icon', html: `<div class="zlabel">${escapeHtml(z.name)}<br><span class="fee">${money(z.delivery_fee)}</span></div>`, iconSize: [0, 0], iconAnchor: [0, 10] }),
        }).addTo(layers);
    }
    if (props.draft?.length) {
        L.polygon(props.draft, { color: props.draftColor, weight: 3, dashArray: '6 4', fillOpacity: 0.2, interactive: false }).addTo(layers);
        props.draft.forEach((p) => L.marker(p, { icon: dotIcon('var(--amber)'), interactive: false }).addTo(layers!));
    }
    if (props.quotePoint) L.marker(props.quotePoint, { icon: dotIcon('var(--amber)'), interactive: false }).addTo(layers);
    map.getContainer().classList.toggle('leaflet-crosshair', props.draft !== null);
}

function escapeHtml(s: string): string {
    return s.replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch] ?? ch);
}

onMounted(async () => {
    lib.value = await import('@/shared/map/createMap');
    if (!el.value) return;
    map = lib.value.createMap(el.value, { style: page.props.map.style, maptilerKey: page.props.map.maptilerKey });
    layers = lib.value.L.layerGroup().addTo(map);
    map.on('click', (e) => {
        const p: LatLng = [Number(e.latlng.lat.toFixed(5)), Number(e.latlng.lng.toFixed(5))];
        if (props.draft !== null) emit('addVertex', p); else emit('pick', p);
    });
    const all = props.zones.flatMap((z) => z.polygon);
    if (all.length) map.fitBounds(all, { padding: [20, 20] });
    draw();
});
watch(() => [props.zones, props.draft, props.draftColor, props.quotePoint], draw, { deep: true });
onBeforeUnmount(() => map?.remove());
</script>

<template>
    <div ref="el" class="map" role="region" aria-label="Mapa de zonas de cobertura" />
</template>
