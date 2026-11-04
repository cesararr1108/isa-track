<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/shared/layouts/AppLayout.vue';
import OrderForm from '@/modules/orders/components/OrderForm.vue';
import OrderDetailPanel from '@/modules/orders/components/OrderDetail.vue';
import StatusPill from '@/shared/ui/StatusPill.vue';
import { useMap } from '@/shared/map/useMap';
import { money } from '@/shared/lib/format';
import { usePermissions } from '@/shared/lib/permissions';
import type { CarrierOption, LatLng, OrderDetail, OrderRow, StatusMap } from '@/types';

const props = defineProps<{
    orders: OrderRow[];
    total: number;
    filters: { status: string | null; search: string | null };
    selected: OrderDetail | null;
    carriers: CarrierOption[];
    statuses: StatusMap;
    transitions: Record<string, string[]>;
    incidentReasons: Record<string, string>;
    paymentMethods: Record<string, string>;
}>();
const { can } = usePermissions();

const creating = ref(false);
const picking = ref(false);
const point = ref<LatLng | null>(null);
const search = ref(props.filters.search ?? '');
const carrierName = (id: string | null) => props.carriers.find((c) => c.id === id)?.name.split(' ')[0] ?? null;
const orderUrl = (id: string) => `/pedidos?${new URLSearchParams({ ...(props.filters.status ? { status: props.filters.status } : {}), order: id })}`;
const counts = computed(() => props.orders.reduce<Record<string, number>>((acc, o) => ((acc[o.status] = (acc[o.status] ?? 0) + 1), acc), {}));

function visit(params: Record<string, string | null>) {
    const query = { status: props.filters.status, search: props.filters.search || null, order: props.selected?.id ?? null, ...params };
    router.get('/pedidos', Object.fromEntries(Object.entries(query).filter(([, v]) => v)), { preserveState: true, preserveScroll: true, replace: true });
}
let timer: ReturnType<typeof setTimeout> | undefined;
watch(search, (v) => { clearTimeout(timer); timer = setTimeout(() => visit({ search: v || null }), 350); });

/* ---- mapa ---- */
const mapEl = ref<HTMLElement | null>(null);
let fitted = false;
const { map, redraw } = useMap(mapEl, ({ map, layers, lib }) => {
    const { L, dotIcon, escapeHtml } = lib;
    for (const o of props.orders) {
        if (o.latitude == null || o.longitude == null) continue;
        const sel = o.id === props.selected?.id;
        L.marker([o.latitude, o.longitude], { icon: dotIcon(props.statuses[o.status]?.color ?? '#64748b', sel ? 24 : 18), zIndexOffset: sel ? 500 : 0 })
            .addTo(layers)
            .bindTooltip(escapeHtml(o.recipient_name))
            .on('click', () => visit({ order: o.id }));
    }
    if (point.value) L.marker(point.value, { icon: dotIcon('var(--amber)', 22) }).addTo(layers).bindTooltip('Nuevo pedido');
    map.getContainer().classList.toggle('leaflet-crosshair', picking.value);
    const sel = props.selected?.address;
    if (sel?.latitude != null && sel.longitude != null) {
        map.setView([sel.latitude, sel.longitude], Math.max(map.getZoom(), 14));
    } else if (!fitted) {
        const pts = props.orders.filter((o) => o.latitude != null).map((o) => [o.latitude!, o.longitude!] as LatLng);
        if (pts.length > 1) map.fitBounds(pts, { padding: [24, 24], maxZoom: 15 });
    }
    fitted = true;
});
watch(map, (m) => m?.on('click', (e) => {
    if (!picking.value) return;
    point.value = [Number(e.latlng.lat.toFixed(6)), Number(e.latlng.lng.toFixed(6))];
    picking.value = false;
}));
watch(() => [props.orders, props.selected, point.value, picking.value], redraw);
</script>

<template>
    <AppLayout title="Pedidos">
        <template #actions>
            <button v-if="can('orders.create') && !creating" class="btn" type="button" @click="creating = true; point = null">+ Nuevo pedido</button>
        </template>

        <div class="filters" role="group" aria-label="Filtrar por estado">
            <button class="fbtn" type="button" :aria-pressed="!filters.status" @click="visit({ status: null })">Todos <b class="num">{{ filters.status ? '' : total }}</b></button>
            <button v-for="(st, k) in statuses" v-show="counts[k] || filters.status === k" :key="k" class="fbtn" type="button" :aria-pressed="filters.status === k" @click="visit({ status: k as string })">
                <StatusPill :status="k as string" :statuses="statuses" /> <b class="num">{{ counts[k] ?? 0 }}</b>
            </button>
            <label class="sr-only" for="q">Buscar</label>
            <input id="q" v-model="search" class="search" type="search" placeholder="Buscar por código, referencia o nombre" />
        </div>

        <div class="grid2">
            <section class="panel">
                <header><h3>Pedidos del día y abiertos</h3><span class="small muted">{{ total }} en total</span></header>
                <ul class="rows">
                    <li v-for="o in orders" :key="o.id">
                        <Link class="row" :href="orderUrl(o.id)" preserve-scroll preserve-state :aria-current="o.id === selected?.id">
                            <span>
                                <span class="name">{{ o.recipient_name }}</span>
                                <span class="meta"><span class="mono">{{ o.tracking_code }}</span>· {{ o.neighborhood ?? o.city }}<template v-if="carrierName(o.carrier_id)"> · {{ carrierName(o.carrier_id) }}</template></span>
                            </span>
                            <span class="right">
                                <StatusPill :status="o.status" :statuses="statuses" />
                                <span class="row-wrap">
                                    <span v-if="o.delivery_fee != null" class="fee">{{ money(o.delivery_fee) }}</span>
                                    <span v-else class="chip warn">sin zona</span>
                                    <span v-if="o.cash_on_delivery" class="cod num">Cobrar {{ money(o.cash_on_delivery) }}</span>
                                </span>
                            </span>
                        </Link>
                    </li>
                    <li v-if="!orders.length" class="empty">No hay pedidos con este filtro.</li>
                </ul>
            </section>

            <div class="stack">
                <section class="panel">
                    <header><h3>Mapa</h3><span class="small muted">{{ picking ? 'Haz clic donde va el pedido' : 'Clic en un punto para ver el pedido' }}</span></header>
                    <div ref="mapEl" class="map short" role="region" aria-label="Mapa de pedidos" />
                </section>
                <OrderForm v-if="creating" :point="point" @close="creating = false; point = null" @pick-on-map="picking = true" />
                <OrderDetailPanel v-else-if="selected" :key="selected.id" :order="selected" :carriers="carriers" :statuses="statuses" :transitions="transitions" :incident-reasons="incidentReasons" :payment-methods="paymentMethods" />
                <p v-else class="panel empty">Elige un pedido de la lista o del mapa para ver su detalle.</p>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.search { flex: 1 1 240px; min-width: 0; padding: 7px 12px; border: 1px solid var(--line); border-radius: 999px; background: var(--surface); }
</style>
