<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import type { CashSummary, DeliveryRoute, OrderRow, SharedProps, StatusMap } from '@/types';
import { longDay, money } from '@/shared/lib/format';
import StatusPill from '@/shared/ui/StatusPill.vue';
import DriverLayout from '../layouts/DriverLayout.vue';
import InstallBanner from '../components/InstallBanner.vue';
import { startSharing, stopSharing } from '../device/location';

const props = defineProps<{
    today: string;
    linked: boolean;
    routes: DeliveryRoute[];
    orders: OrderRow[];
    cash: CashSummary | null;
    statuses: StatusMap;
    paymentMethods: Record<string, string>;
}>();

const page = usePage<SharedProps>();
const firstName = computed(() => page.props.auth.user?.name.split(' ')[0] ?? '');

const OPEN = ['created', 'prepared', 'assigned', 'in_route', 'incident'];
const byId = computed(() => new Map(props.orders.map((o) => [o.id, o])));

/** La ruta que importa hoy: la que está en curso, si no la planeada, si no la última. */
const route = computed(() =>
    props.routes.find((r) => r.status === 'in_progress') ?? props.routes.find((r) => r.status === 'planned') ?? props.routes[0] ?? null,
);
const routeIds = computed(() => new Set(route.value?.stops.map((s) => s.order_id) ?? []));

interface Item { id: string; seq: number | null; name: string; place: string; status: string; collect: number | null; code: string }

/** Por entregar: primero las paradas de la ruta en su orden, luego los pedidos sueltos. */
const pending = computed<Item[]>(() => {
    const items: Item[] = [];
    for (const s of route.value?.stops ?? []) {
        const o = byId.value.get(s.order_id);
        const status = o?.status ?? s.status ?? 'assigned';
        if (!OPEN.includes(status)) continue;
        items.push({ id: s.order_id, seq: s.sequence, name: s.recipient_name ?? o?.recipient_name ?? '', place: [s.address, s.neighborhood].filter(Boolean).join(' · '), status, collect: o?.cash_on_delivery ?? s.cash_on_delivery, code: s.tracking_code ?? o?.tracking_code ?? '' });
    }
    for (const o of props.orders) {
        if (routeIds.value.has(o.id) || !OPEN.includes(o.status)) continue;
        items.push({ id: o.id, seq: null, name: o.recipient_name, place: [o.neighborhood, o.city].filter(Boolean).join(' · '), status: o.status, collect: o.cash_on_delivery, code: o.tracking_code });
    }
    // Lo que está en camino o con novedad va arriba: es lo que hay que resolver ya.
    const weight = (s: string) => (s === 'in_route' ? 0 : s === 'incident' ? 1 : 2);
    return items.sort((a, b) => weight(a.status) - weight(b.status) || (a.seq ?? 999) - (b.seq ?? 999));
});
const done = computed(() => props.orders.filter((o) => !OPEN.includes(o.status)));
const toCollect = computed(() => pending.value.reduce((t, i) => t + (i.collect ?? 0), 0));

/** Se comparte la ubicación mientras haya ruta en curso o algún pedido en camino. */
const active = computed(() => route.value?.status === 'in_progress' || props.orders.some((o) => o.status === 'in_route'));
watch(active, (on) => (on ? startSharing() : stopSharing()), { immediate: true });

const opts = { preserveScroll: true };
const startRoute = () => route.value && router.post(`/rutas/${route.value.id}/estado`, { status: 'in_progress' }, opts);
function finishRoute() {
    if (!route.value) return;
    const left = pending.value.filter((i) => i.seq !== null).length;
    if (left > 0 && !confirm(`Aún tienes ${left} parada(s) sin cerrar. ¿Terminar la ruta de todos modos?`)) return;
    router.post(`/rutas/${route.value.id}/estado`, { status: 'completed' }, opts);
}

// Nuevos pedidos asignados por despacho aparecen solos cada minuto.
let timer: ReturnType<typeof setInterval> | undefined;
const refresh = () => router.reload({ only: ['routes', 'orders', 'cash'] });
onMounted(() => (timer = setInterval(() => document.visibilityState === 'visible' && refresh(), 60_000)));
onBeforeUnmount(() => clearInterval(timer));

const methodsCollected = computed(() =>
    Object.entries(props.cash?.collected.totals_by_method ?? {}).filter(([m, v]) => m !== 'cash' && v > 0),
);
</script>

<template>
    <DriverLayout title="Mi ruta de hoy">
        <section class="hello">
            <h1>Hola, {{ firstName }}</h1>
            <p class="muted">{{ longDay(today) }}</p>
        </section>

        <InstallBanner />

        <p v-if="!linked" class="alert">Tu usuario no está vinculado a un transportador. Pídele a despacho que te vincule en “Transportadores”.</p>

        <template v-else>
            <section class="kpis">
                <div><b class="num">{{ pending.length }}</b><span>por entregar</span></div>
                <div><b class="num">{{ done.filter((o) => o.status === 'delivered').length }}</b><span>entregados</span></div>
                <div class="amber"><b class="num">{{ money(toCollect) }}</b><span>por cobrar</span></div>
            </section>

            <section v-if="route" class="panel route">
                <header>
                    <div>
                        <h3>{{ route.name }}</h3>
                        <span class="small muted">{{ route.delivered_count }}/{{ route.stops_count }} paradas · {{ route.distance_km.toFixed(1) }} km</span>
                    </div>
                    <span class="tag" :class="route.status">{{ { planned: 'Por iniciar', in_progress: 'En curso', completed: 'Terminada', cancelled: 'Cancelada' }[route.status] }}</span>
                </header>
                <div class="pad">
                    <div class="progress" aria-hidden="true"><i :style="{ width: `${route.stops_count ? (route.delivered_count / route.stops_count) * 100 : 0}%` }" /></div>
                    <button v-if="route.status === 'planned'" class="btn block big" type="button" @click="startRoute">Iniciar ruta</button>
                    <button v-else-if="route.status === 'in_progress'" class="btn secondary block" type="button" @click="finishRoute">Terminar ruta</button>
                </div>
            </section>

            <section class="stack">
                <div class="row-head">
                    <h2>Por entregar</h2>
                    <button class="link" type="button" @click="refresh">Actualizar</button>
                </div>
                <p v-if="!pending.length" class="panel empty">No tienes entregas pendientes. Cuando despacho te asigne pedidos aparecen aquí.</p>
                <Link v-for="i in pending" :key="i.id" :href="`/mensajero/pedidos/${i.id}`" class="stop" :class="i.status">
                    <span class="seq">{{ i.seq ?? '•' }}</span>
                    <span class="body">
                        <b>{{ i.name }}</b>
                        <small class="muted">{{ i.place || i.code }}</small>
                        <span class="row-wrap">
                            <StatusPill :status="i.status" :statuses="statuses" />
                            <span v-if="i.collect" class="collect">Cobrar {{ money(i.collect) }}</span>
                        </span>
                    </span>
                    <span class="go" aria-hidden="true">›</span>
                </Link>
            </section>

            <details v-if="done.length" class="panel">
                <summary class="pad"><b>Terminados hoy ({{ done.length }})</b></summary>
                <Link v-for="o in done" :key="o.id" :href="`/mensajero/pedidos/${o.id}`" class="done">
                    <span>{{ o.recipient_name }} <small class="muted mono">{{ o.tracking_code }}</small></span>
                    <StatusPill :status="o.status" :statuses="statuses" />
                </Link>
            </details>

            <section v-if="cash" class="panel cash">
                <header><h3>Mi caja de hoy</h3><span v-if="cash.closing" class="tag completed">Cerrada</span></header>
                <div class="pad">
                    <p class="cash-big"><span class="muted small">Efectivo que debes entregar</span><b class="num">{{ money(cash.collected.expected_cash) }}</b></p>
                    <p v-for="[m, v] in methodsCollected" :key="m" class="line"><span>{{ paymentMethods[m] ?? m }}</span><b class="num">{{ money(v) }}</b></p>
                    <p class="small muted">{{ cash.collected.orders_count }} pedido(s) cobrados hoy. Nequi, Daviplata y datáfono no los entregas en efectivo.</p>
                </div>
            </section>
        </template>
    </DriverLayout>
</template>

<style scoped>
.hello h1 { font-size: 1.6rem; }
.hello p { margin: 2px 0 0; }
.hello p::first-letter { text-transform: uppercase; }
.kpis { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.kpis div { background: var(--surface); border: 1px solid var(--line); border-radius: 12px; padding: 10px; display: grid; }
.kpis b { font-family: var(--display); font-size: 1.25rem; }
.kpis span { font-size: .74rem; color: var(--muted); font-weight: 700; }
.kpis .amber { background: var(--amber-soft); border-color: transparent; }
.kpis .amber b { color: var(--amber-text); font-size: 1.05rem; }
.pad { padding: 12px 14px; display: grid; gap: 10px; }
.route header h3 { font-size: 1.05rem; }
.tag { font-size: .72rem; font-weight: 800; border-radius: 999px; padding: 3px 10px; background: var(--sunken); color: var(--muted); }
.tag.in_progress { background: var(--accent-soft); color: var(--accent-text); }
.tag.planned { background: var(--amber-soft); color: var(--amber-text); }
.progress { height: 8px; border-radius: 99px; background: var(--sunken); overflow: hidden; }
.progress i { display: block; height: 100%; background: var(--mint); border-radius: 99px; }
.btn.big { font-size: 1.05rem; padding: 15px; }
.row-head { display: flex; align-items: baseline; justify-content: space-between; }
.row-head h2 { font-size: 1.15rem; }
.stop { display: flex; align-items: center; gap: 12px; background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 12px; text-decoration: none; color: var(--fg); min-height: 64px; }
.stop.in_route { border-color: var(--mint); box-shadow: 0 0 0 1px var(--mint) inset; }
.stop.incident { border-color: var(--amber); }
.seq { flex: none; width: 34px; height: 34px; border-radius: 50%; background: var(--navy); color: #fff; display: grid; place-items: center; font-weight: 800; }
.body { flex: 1; min-width: 0; display: grid; gap: 4px; }
.body b { font-size: 1rem; }
.body small { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.collect { font-size: .76rem; font-weight: 800; color: var(--amber-text); background: var(--amber-soft); border-radius: 6px; padding: 1px 7px; }
.go { font-size: 1.6rem; color: var(--muted); }
details summary { cursor: pointer; list-style: none; }
.done { display: flex; justify-content: space-between; gap: 10px; align-items: center; padding: 10px 14px; border-top: 1px solid var(--line); text-decoration: none; color: var(--fg); }
.cash-big { margin: 0; display: grid; }
.cash-big b { font-family: var(--display); font-size: 1.6rem; }
.line { margin: 0; display: flex; justify-content: space-between; }
</style>
