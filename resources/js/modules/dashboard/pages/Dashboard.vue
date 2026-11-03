<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/shared/layouts/AppLayout.vue';
import { longDay, money, percent } from '@/shared/lib/format';
import type { SharedProps } from '@/types';

interface Summary {
    totals: {
        orders: number; delivered: number; returned: number; in_progress: number; incidents_reported: number;
        delivery_rate: number | null; delivery_fees: number; collected: number; collected_cash: number;
        by_status: Record<string, number>;
    };
    collections_by_method: Record<string, number>;
    incidents_by_reason: Record<string, number>;
}

const props = defineProps<{
    today: string;
    summary: Summary | null;
    statuses: Record<string, { label: string; color: string }>;
    incidentReasons: Record<string, string>;
    paymentMethods: Record<string, string>;
}>();

const page = usePage<SharedProps>();
const firstName = computed(() => page.props.auth.user?.name.split(' ')[0] ?? '');
const t = computed(() => props.summary?.totals);
</script>

<template>
    <AppLayout title="Resumen">
        <p class="intro">Hola {{ firstName }}. Así va la operación del {{ longDay(today) }}.</p>

        <template v-if="t">
            <div class="kpis">
                <div class="kpi navy"><span>Pedidos de hoy</span><b>{{ t.orders }}</b><small>{{ t.in_progress }} en proceso</small></div>
                <div class="kpi mint"><span>Entregados</span><b>{{ t.delivered }}</b><small>efectividad {{ percent(t.delivery_rate) }}</small></div>
                <div class="kpi amber"><span>Novedades</span><b>{{ t.incidents_reported }}</b><small>{{ t.returned }} devueltos</small></div>
                <div class="kpi"><span>Recaudado</span><b>{{ money(t.collected) }}</b><small>{{ money(t.collected_cash) }} en efectivo</small></div>
                <div class="kpi amber"><span>Domicilios</span><b>{{ money(t.delivery_fees) }}</b><small>según tarifa por zona</small></div>
            </div>
            <div class="grid2">
                <section class="panel">
                    <header><h3>Pedidos por estado</h3></header>
                    <ul class="list">
                        <li v-for="(n, s) in t.by_status" :key="s">
                            <span class="pill" :style="{ '--c': statuses[s]?.color ?? '#64748b' }">{{ statuses[s]?.label ?? s }}</span><b class="num">{{ n }}</b>
                        </li>
                        <li v-if="!Object.keys(t.by_status).length" class="empty">Aún no hay pedidos hoy.</li>
                    </ul>
                </section>
                <section class="panel">
                    <header><h3>Recaudo por medio de pago</h3></header>
                    <ul class="list">
                        <li v-for="(v, m) in summary!.collections_by_method" :key="m"><span>{{ paymentMethods[m] ?? m }}</span><b class="num">{{ money(v) }}</b></li>
                        <li v-if="!Object.keys(summary!.collections_by_method).length" class="empty">Sin recaudos hoy.</li>
                    </ul>
                </section>
            </div>
        </template>
        <p v-else class="panel pad muted">Tu usuario no tiene el permiso de ver reportes, por eso no se muestran cifras.</p>

        <p class="alert">Este panel se está construyendo por etapas. Ya funcionan <b>Transportadores</b> y <b>Zonas y tarifas</b>; las secciones marcadas "Pronto" siguen en el prototipo (<a href="/prototipo">/prototipo</a>).</p>
    </AppLayout>
</template>

<style scoped>
.list { list-style: none; margin: 0; padding: 0; }
.list li { display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; border-bottom: 1px solid var(--line); }
.list li:last-child { border-bottom: 0; }
</style>
