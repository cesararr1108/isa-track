<script setup lang="ts">
import { computed, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/shared/layouts/AppLayout.vue';
import DayChart from '@/modules/reports/components/DayChart.vue';
import { longDay, money, percent } from '@/shared/lib/format';
import type { StatusMap } from '@/types';

interface Report {
    range: { from: string; to: string; days: number };
    totals: { orders: number; delivered: number; returned: number; incidents_reported: number; delivery_rate: number | null; on_time_rate: number | null; delivery_fees: number; collected: number; collected_cash: number };
    by_day: { date: string; created: number; delivered: number; delivery_fees: number; collected: number }[];
    by_carrier: { carrier_id: string | null; name: string; orders: number; delivered: number; incidents: number; delivery_fees: number; collected: number; delivery_rate: number | null }[];
    by_zone: { zone_id: string | null; name: string; orders: number; delivered: number; delivery_fees: number }[];
    collections_by_method: Record<string, number>;
    incidents_by_reason: Record<string, number>;
}

const props = defineProps<{
    report: Report;
    filters: { from: string; to: string; carrier_id: string | null; zone_id: string | null };
    today: string;
    carriers: { id: string; name: string }[];
    zones: { id: string; name: string; color: string }[];
    statuses: StatusMap;
    paymentMethods: Record<string, string>;
    incidentReasons: Record<string, string>;
}>();

const f = reactive({ ...props.filters });
const t = computed(() => props.report.totals);
const addDays = (ymd: string, n: number) => { const d = new Date(`${ymd}T12:00:00Z`); d.setUTCDate(d.getUTCDate() + n); return d.toISOString().slice(0, 10); };
const presets = computed(() => [
    ['Hoy', props.today, props.today], ['7 días', addDays(props.today, -6), props.today],
    ['30 días', addDays(props.today, -29), props.today], ['Este mes', `${props.today.slice(0, 8)}01`, props.today],
] as const);
const query = () => Object.fromEntries(Object.entries(f).filter(([, v]) => v));
function apply(from?: string, to?: string) {
    if (from && to) Object.assign(f, { from, to });
    router.get('/reportes', query(), { preserveScroll: true, preserveState: true });
}
const csvUrl = computed(() => `/reportes/pedidos.csv?${new URLSearchParams(query() as Record<string, string>)}`);
const zoneColor = (id: string | null) => props.zones.find((z) => z.id === id)?.color ?? '#94a3b8';
const max = (vals: number[]) => Math.max(1, ...vals);
</script>

<template>
    <AppLayout title="Reportes">
        <section class="panel">
            <form class="fgrid filters4" @submit.prevent="apply()">
                <label class="field">Desde<input id="rp-from" v-model="f.from" type="date" :max="f.to" /></label>
                <label class="field">Hasta<input id="rp-to" v-model="f.to" type="date" :min="f.from" :max="today" /></label>
                <label class="field">Mensajero<select id="rp-carrier" v-model="f.carrier_id"><option :value="null">Todos</option><option v-for="c in carriers" :key="c.id" :value="c.id">{{ c.name }}</option></select></label>
                <label class="field">Zona<select id="rp-zone" v-model="f.zone_id"><option :value="null">Todas</option><option v-for="z in zones" :key="z.id" :value="z.id">{{ z.name }}</option></select></label>
                <div class="full actions">
                    <button v-for="[label, a, b] in presets" :key="label" class="btn secondary small" type="button" :aria-pressed="filters.from === a && filters.to === b" @click="apply(a, b)">{{ label }}</button>
                    <button class="btn small" type="submit">Ver reporte</button>
                    <span style="flex: 1" />
                    <a class="btn navy small" :href="csvUrl">Descargar Excel (CSV)</a>
                </div>
            </form>
        </section>

        <div class="kpis">
            <div class="kpi navy"><span>Pedidos</span><b>{{ t.orders }}</b><small>{{ longDay(report.range.from) }} – {{ longDay(report.range.to) }}</small></div>
            <div class="kpi mint"><span>Entregados</span><b>{{ t.delivered }}</b><small>efectividad {{ percent(t.delivery_rate) }}</small></div>
            <div class="kpi"><span>A tiempo</span><b>{{ percent(t.on_time_rate) }}</b><small>antes de la hora prometida</small></div>
            <div class="kpi amber"><span>Novedades</span><b>{{ t.incidents_reported }}</b><small>{{ t.returned }} devueltos</small></div>
            <div class="kpi amber"><span>Domicilios</span><b>{{ money(t.delivery_fees) }}</b><small>tarifas por zona</small></div>
            <div class="kpi"><span>Recaudado</span><b>{{ money(t.collected) }}</b><small>{{ money(t.collected_cash) }} en efectivo</small></div>
        </div>

        <section class="panel">
            <header><h3>Pedidos por día</h3><span class="legend small"><i class="sw b1" />Creados <i class="sw b2" />Entregados</span></header>
            <div class="pad"><DayChart :days="report.by_day" /></div>
        </section>

        <section class="panel">
            <header><h3>Por mensajero</h3></header>
            <div class="tablewrap">
                <table class="t">
                    <thead><tr><th>Mensajero</th><th class="r">Pedidos</th><th class="r">Entregados</th><th class="r">Efectividad</th><th class="r">Novedades</th><th class="r">Domicilios</th><th class="r">Recaudado</th></tr></thead>
                    <tbody>
                        <tr v-for="c in report.by_carrier" :key="c.carrier_id ?? 'none'"><td>{{ c.name }}</td><td class="r">{{ c.orders }}</td><td class="r">{{ c.delivered }}</td><td class="r">{{ percent(c.delivery_rate) }}</td><td class="r">{{ c.incidents }}</td><td class="r">{{ money(c.delivery_fees) }}</td><td class="r">{{ money(c.collected) }}</td></tr>
                        <tr v-if="!report.by_carrier.length"><td colspan="7" class="empty">Sin datos en el rango.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="grid2">
            <section class="panel">
                <header><h3>Domicilios por zona</h3></header>
                <div class="bars">
                    <div v-for="z in report.by_zone" :key="z.zone_id ?? 'none'" class="bar-h">
                        <span>{{ z.name }} <span class="small muted">· {{ z.orders }}</span></span>
                        <span class="track"><span :style="{ '--c': zoneColor(z.zone_id), width: `${(z.delivery_fees / max(report.by_zone.map((x) => x.delivery_fees))) * 100}%` }" /></span>
                        <b class="num">{{ money(z.delivery_fees) }}</b>
                    </div>
                    <p v-if="!report.by_zone.length" class="empty">Sin datos.</p>
                </div>
            </section>
            <div class="stack">
                <section class="panel">
                    <header><h3>Recaudo por medio de pago</h3></header>
                    <div class="bars">
                        <div v-for="(v, m) in report.collections_by_method" :key="m" class="bar-h">
                            <span>{{ paymentMethods[m] ?? m }}</span>
                            <span class="track"><span :style="{ width: `${(v / max(Object.values(report.collections_by_method))) * 100}%` }" /></span>
                            <b class="num">{{ money(v) }}</b>
                        </div>
                        <p v-if="!Object.keys(report.collections_by_method).length" class="empty">Sin recaudos.</p>
                    </div>
                </section>
                <section class="panel">
                    <header><h3>Novedades por motivo</h3></header>
                    <div class="bars">
                        <div v-for="(n, k) in report.incidents_by_reason" :key="k" class="bar-h">
                            <span>{{ incidentReasons[k] ?? k }}</span>
                            <span class="track"><span :style="{ '--c': 'var(--amber)', width: `${(n / max(Object.values(report.incidents_by_reason))) * 100}%` }" /></span>
                            <b class="num">{{ n }}</b>
                        </div>
                        <p v-if="!Object.keys(report.incidents_by_reason).length" class="empty">Sin novedades en el rango.</p>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.filters4 { grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); }
.actions [aria-pressed="true"] { border-color: var(--fg); }
.legend { display: flex; gap: 8px; align-items: center; color: var(--muted); }
.sw { display: inline-block; width: 10px; height: 10px; border-radius: 3px; }
.sw.b1 { background: color-mix(in srgb, var(--navy) 35%, var(--sunken)); }
.sw.b2 { background: var(--mint); }
.bars { padding-block: 8px; }
.bar-h { display: grid; grid-template-columns: minmax(90px, 170px) minmax(0, 1fr) auto; gap: 10px; align-items: center; padding: 6px 14px; font-size: .88rem; }
.track { height: 10px; background: var(--sunken); border-radius: 99px; overflow: hidden; }
.track span { display: block; height: 100%; background: var(--c, var(--mint)); border-radius: 99px; }
</style>
