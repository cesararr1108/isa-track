<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/shared/layouts/AppLayout.vue';
import RoutePlanner from '@/modules/routes/components/RoutePlanner.vue';
import StatusPill from '@/shared/ui/StatusPill.vue';
import { useMap } from '@/shared/map/useMap';
import { hourMinute, initials, longDay, money } from '@/shared/lib/format';
import { usePermissions } from '@/shared/lib/permissions';
import type { CarrierOption, DeliveryRoute, LatLng, RoutableOrder, StatusMap } from '@/types';

const props = defineProps<{
    date: string;
    routes: DeliveryRoute[];
    selected: DeliveryRoute | null;
    routable: RoutableOrder[];
    carriers: CarrierOption[];
    statuses: StatusMap;
    vehicleTypes: Record<string, string>;
}>();
const { can } = usePermissions();
const ROUTE_STATUS: Record<string, { label: string; cls: string }> = {
    planned: { label: 'Planeada', cls: 'warn' }, in_progress: { label: 'En curso', cls: 'ok' },
    completed: { label: 'Completada', cls: '' }, cancelled: { label: 'Cancelada', cls: 'bad' },
};
const picked = ref<string[]>([]);
const r = computed(() => props.selected);
const editable = computed(() => can('routes.manage') && r.value?.status === 'planned');

/* Hora estimada de llegada: 22 km/h promedio en ciudad + 7 min por entrega. */
const eta = computed(() => {
    if (!r.value) return {} as Record<string, string>;
    let t = r.value.started_at ? new Date(r.value.started_at).getTime() : new Date(`${r.value.date}T08:30:00-05:00`).getTime();
    return Object.fromEntries(r.value.stops.map((s) => { t += (s.leg_km / 22) * 3_600_000 + 7 * 60_000; return [s.order_id, hourMinute(new Date(t))]; }));
});

const opts = { preserveScroll: true };
function changeDate(e: Event) { router.get('/rutas', { date: (e.target as HTMLInputElement).value }, { preserveState: false }); }
function move(i: number, dir: -1 | 1) {
    if (!r.value) return;
    const ids = r.value.stops.map((s) => s.order_id);
    const j = i + dir;
    [ids[i], ids[j]] = [ids[j]!, ids[i]!];
    router.put(`/rutas/${r.value.id}/paradas`, { order_ids: ids }, opts);
}
const status = (s: string) => r.value && router.post(`/rutas/${r.value.id}/estado`, { status: s }, opts);
const optimize = () => r.value && router.post(`/rutas/${r.value.id}/optimizar`, {}, opts);
const confirmCancel = ref(false);

/* ---- mapa ---- */
const mapEl = ref<HTMLElement | null>(null);
const { redraw } = useMap(mapEl, ({ map, layers, lib }) => {
    const { L, numberIcon, depotIcon, dotIcon, escapeHtml } = lib;
    const route = r.value;
    if (route) {
        const start: LatLng = [route.start.latitude, route.start.longitude];
        const pts: LatLng[] = [start, ...route.stops.map((s) => [s.latitude, s.longitude] as LatLng)];
        L.polyline(pts, { className: 'route-line', weight: 4, opacity: 0.9, dashArray: route.status === 'planned' ? '8 6' : undefined }).addTo(layers);
        L.marker(start, { icon: depotIcon() }).addTo(layers).bindTooltip('Bodega (punto de partida)');
        route.stops.forEach((s, i) => {
            const color = s.status === 'delivered' ? 'var(--s-delivered)' : s.status === 'incident' ? 'var(--amber)' : 'var(--navy)';
            L.marker([s.latitude, s.longitude], { icon: numberIcon(i + 1, color, s.status === 'incident') })
                .addTo(layers)
                .bindPopup(`<b>${i + 1}. ${escapeHtml(s.recipient_name)}</b><br>${escapeHtml(s.address)}<br>${escapeHtml(s.neighborhood)}`);
        });
        map.fitBounds(pts, { padding: [36, 36], maxZoom: 15 });
    }
    // Pedidos por programar: gris; los elegidos para la ruta nueva, en ámbar.
    for (const o of props.routable) {
        const on = picked.value.includes(o.id);
        L.marker([o.latitude, o.longitude], { icon: dotIcon(on ? 'var(--amber)' : 'var(--s-created)', on ? 22 : 16), zIndexOffset: on ? 400 : 0 })
            .addTo(layers)
            .bindTooltip(`${on ? 'En la ruta nueva: ' : 'Por programar: '}${escapeHtml(o.recipient_name)}`);
    }
    if (!route && props.routable.length > 1) map.fitBounds(props.routable.map((o) => [o.latitude, o.longitude] as LatLng), { padding: [36, 36], maxZoom: 15 });
});
watch(() => [props.selected, picked.value], redraw, { deep: true });
</script>

<template>
    <AppLayout title="Rutas">
        <template #actions>
            <label class="sr-only" for="day">Día</label>
            <input id="day" type="date" class="day" :value="date" @change="changeDate" />
        </template>
        <p class="intro">Programa el recorrido de cada mensajero: el sistema revisa la capacidad del vehículo, ordena las paradas para recorrer menos y asigna los pedidos.</p>

        <div class="grid-side">
            <div class="stack">
                <RoutePlanner v-if="can('routes.manage')" v-model:selected="picked" :date="date" :carriers="carriers" :orders="routable" :statuses="statuses" :vehicle-types="vehicleTypes" />
                <section class="panel">
                    <header><h3>Rutas del {{ longDay(date) }}</h3></header>
                    <ul class="rows">
                        <li v-for="x in routes" :key="x.id">
                            <Link class="row" :href="`/rutas?date=${date}&route=${x.id}`" preserve-scroll :aria-current="x.id === r?.id">
                                <span><span class="name">{{ x.name }}</span><span class="meta">{{ x.carrier.name }} · {{ x.stops_count }} paradas · {{ x.distance_km.toFixed(1) }} km</span></span>
                                <span class="right"><span class="chip" :class="ROUTE_STATUS[x.status]?.cls">{{ ROUTE_STATUS[x.status]?.label }}</span><span class="small muted num">{{ x.delivered_count }}/{{ x.stops_count }}</span></span>
                            </Link>
                        </li>
                        <li v-if="!routes.length" class="empty">Sin rutas este día.</li>
                    </ul>
                </section>
            </div>

            <div class="stack">
                <section class="panel">
                    <header v-if="r">
                        <div>
                            <h3>{{ r.name }}</h3>
                            <span class="small muted">{{ r.carrier.name }} <span v-if="r.carrier.vehicle_plate" class="plate">{{ r.carrier.vehicle_plate }}</span> · {{ r.distance_km.toFixed(1) }} km en línea recta · {{ r.total_weight_kg }} kg</span>
                        </div>
                        <span class="chip" :class="ROUTE_STATUS[r.status]?.cls">{{ ROUTE_STATUS[r.status]?.label }}</span>
                    </header>
                    <header v-else><h3>Mapa de la ruta</h3></header>
                    <div ref="mapEl" class="map" role="region" aria-label="Mapa de la ruta" />
                </section>

                <section v-if="r" class="panel">
                    <header>
                        <h3>Paradas</h3>
                        <div class="actions">
                            <button v-if="editable" class="btn secondary small" type="button" @click="optimize">Optimizar orden</button>
                            <button v-if="can('routes.drive') && r.status === 'planned'" class="btn small" type="button" @click="status('in_progress')">Iniciar ruta</button>
                            <button v-if="can('routes.drive') && r.status === 'in_progress'" class="btn small" type="button" @click="status('completed')">Completar</button>
                            <template v-if="can('routes.manage') && ['planned', 'in_progress'].includes(r.status)">
                                <button v-if="confirmCancel" class="btn danger small" type="button" @click="status('cancelled'); confirmCancel = false">¿Cancelar la ruta?</button>
                                <button v-else class="link small" type="button" @click="confirmCancel = true">Cancelar</button>
                            </template>
                        </div>
                    </header>
                    <ol class="stops">
                        <li v-for="(s, i) in r.stops" :key="s.order_id">
                            <span class="n" :class="{ done: s.status === 'delivered' }">{{ i + 1 }}</span>
                            <span>
                                <b>{{ s.recipient_name }}</b> <StatusPill v-if="s.status" :status="s.status" :statuses="statuses" /><br />
                                <span class="small muted">{{ s.address }}<template v-if="s.neighborhood"> · {{ s.neighborhood }}</template> · llega ≈ {{ eta[s.order_id] }}<template v-if="s.cash_on_delivery"> · cobrar {{ money(s.cash_on_delivery) }}</template></span>
                            </span>
                            <span v-if="editable" class="move">
                                <button type="button" :aria-label="`Subir parada ${i + 1}`" :disabled="i === 0" @click="move(i, -1)">↑</button>
                                <button type="button" :aria-label="`Bajar parada ${i + 1}`" :disabled="i === r.stops.length - 1" @click="move(i, 1)">↓</button>
                            </span>
                        </li>
                    </ol>
                </section>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.day { padding: 7px 10px; border: 1px solid var(--line); border-radius: 8px; background: var(--surface); }
.stops { list-style: none; margin: 0; padding: 0; }
.stops li { display: grid; grid-template-columns: 30px minmax(0, 1fr) auto; gap: 10px; align-items: center; padding: 10px 14px; border-bottom: 1px solid var(--line); }
.stops li:last-child { border-bottom: 0; }
.n { width: 26px; height: 26px; border-radius: 50%; background: var(--navy); color: #fff; display: grid; place-items: center; font-weight: 800; font-size: .8rem; }
.n.done { background: var(--s-delivered); }
.move { display: flex; gap: 2px; }
.move button { border: 1px solid var(--line); background: var(--surface); border-radius: 6px; width: 28px; height: 28px; cursor: pointer; color: var(--fg); }
.move button:disabled { opacity: .4; cursor: default; }
</style>
