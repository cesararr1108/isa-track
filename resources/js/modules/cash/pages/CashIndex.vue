<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/shared/layouts/AppLayout.vue';
import FieldError from '@/shared/ui/FieldError.vue';
import { dateTime, longDay, money } from '@/shared/lib/format';
import { usePermissions } from '@/shared/lib/permissions';

interface Summary { orders_count: number; totals_by_method: Record<string, number>; total: number; expected_cash: number }
interface CashRow {
    carrier: { id: string; name: string; active: boolean };
    date: string;
    collected: Summary;
    closing: null | { cash_received: number; difference: number; result: 'balanced' | 'short' | 'over'; notes: string | null; closed_at: string; expected: Summary };
}

const props = defineProps<{ date: string; today: string; rows: CashRow[]; paymentMethods: Record<string, string>; requireNote: boolean }>();
const { can } = usePermissions();
const RESULT = { balanced: { label: 'Cuadra exacto', cls: 'ok' }, short: { label: 'Faltante', cls: 'bad' }, over: { label: 'Sobrante', cls: 'warn' } } as const;

const totals = computed(() => props.rows.reduce((a, r) => ({ total: a.total + r.collected.total, cash: a.cash + r.collected.expected_cash, open: a.open + (r.closing ? 0 : r.collected.total > 0 ? 1 : 0) }), { total: 0, cash: 0, open: 0 }));
const closingFor = ref<string | null>(null);
const form = useForm({ carrier_id: '', date: props.date, cash_received: '', notes: '' });
const expectedCash = computed(() => props.rows.find((r) => r.carrier.id === form.carrier_id)?.collected.expected_cash ?? 0);
const diff = computed(() => (form.cash_received === '' ? null : Number(String(form.cash_received).replace(/\D/g, '')) - expectedCash.value));

function openClose(r: CashRow) {
    closingFor.value = r.carrier.id;
    form.reset();
    form.clearErrors();
    form.carrier_id = r.carrier.id;
    form.date = props.date;
    form.cash_received = String(r.collected.expected_cash);
}
function submit() {
    form.transform((d) => ({ ...d, cash_received: Number(String(d.cash_received).replace(/\D/g, '')) }))
        .post('/caja/cierres', { preserveScroll: true, onSuccess: () => (closingFor.value = null) });
}
const changeDate = (e: Event) => router.get('/caja', { date: (e.target as HTMLInputElement).value });
</script>

<template>
    <AppLayout title="Caja">
        <template #actions>
            <label class="sr-only" for="cash-day">Día</label>
            <input id="cash-day" type="date" class="day" :value="date" :max="today" @change="changeDate" />
        </template>
        <p class="intro">Recaudo contra entrega del {{ longDay(date) }}. Cada mensajero entrega el efectivo; Nequi, Daviplata y datáfono se reportan aparte porque no pasan por su mano.</p>

        <div class="kpis">
            <div class="kpi navy"><span>Recaudado</span><b>{{ money(totals.total) }}</b><small>todos los medios</small></div>
            <div class="kpi mint"><span>Efectivo por recibir</span><b>{{ money(totals.cash) }}</b><small>en mano de los mensajeros</small></div>
            <div class="kpi amber"><span>Cajas sin cerrar</span><b>{{ totals.open }}</b><small>con recaudo pendiente</small></div>
        </div>

        <div class="cards">
            <article v-for="r in rows" :key="r.carrier.id" class="ccard">
                <div class="row-wrap" style="justify-content: space-between">
                    <h3 style="font-size: 1.05rem">{{ r.carrier.name }}</h3>
                    <span v-if="r.closing" class="chip" :class="RESULT[r.closing.result].cls">{{ RESULT[r.closing.result].label }}</span>
                    <span v-else-if="r.collected.total" class="chip warn">Caja abierta</span>
                    <span v-else class="chip">Sin recaudo</span>
                </div>
                <table v-if="r.collected.orders_count" class="t">
                    <tbody>
                        <tr v-for="(v, m) in r.collected.totals_by_method" :key="m"><td>{{ paymentMethods[m] ?? m }}</td><td class="r">{{ money(v) }}</td></tr>
                    </tbody>
                    <tfoot><tr><td><b>Total · {{ r.collected.orders_count }} pedido{{ r.collected.orders_count === 1 ? '' : 's' }}</b></td><td class="r"><b>{{ money(r.collected.total) }}</b></td></tr></tfoot>
                </table>
                <div class="row-wrap" style="justify-content: space-between"><span class="muted">Efectivo que debe entregar</span><b class="big num">{{ money(r.collected.expected_cash) }}</b></div>

                <dl v-if="r.closing" class="kv">
                    <dt>Entregó</dt><dd class="num">{{ money(r.closing.cash_received) }}</dd>
                    <dt>Diferencia</dt><dd class="num">{{ money(r.closing.difference) }}</dd>
                    <template v-if="r.closing.notes"><dt>Nota</dt><dd>{{ r.closing.notes }}</dd></template>
                    <dt>Cerrada</dt><dd>{{ dateTime(r.closing.closed_at) }}</dd>
                </dl>
                <template v-else-if="can('cash.close')">
                    <form v-if="closingFor === r.carrier.id" class="stack" style="gap: 8px" novalidate @submit.prevent="submit">
                        <label class="field">Efectivo que entregó (pesos)<input :id="`rec-${r.carrier.id}`" v-model="form.cash_received" inputmode="numeric" /><FieldError :message="form.errors.cash_received" /></label>
                        <p v-if="diff !== null && diff !== 0" class="alert" :class="{ bad: diff < 0 }">{{ diff < 0 ? 'Faltan' : 'Sobran' }} {{ money(Math.abs(diff)) }}.{{ requireNote ? ' Escribe una nota explicándolo.' : '' }}</p>
                        <label class="field">Nota<textarea :id="`note-${r.carrier.id}`" v-model="form.notes" rows="2" placeholder="Ej.: faltan $5.000, se descuentan de la liquidación" /><FieldError :message="form.errors.notes" /></label>
                        <div class="actions"><button class="btn" type="submit" :disabled="form.processing">Cerrar caja del día</button><button class="link" type="button" @click="closingFor = null">Cancelar</button></div>
                    </form>
                    <button v-else class="btn secondary small" type="button" style="justify-self: start" @click="openClose(r)">Cerrar caja</button>
                </template>
            </article>
        </div>
        <p v-if="!rows.length" class="panel empty">No hay mensajeros registrados.</p>
    </AppLayout>
</template>

<style scoped>
.day { padding: 7px 10px; border: 1px solid var(--line); border-radius: 8px; background: var(--surface); }
.big { font: 800 1.4rem var(--display); }
.ccard table.t td { padding: 6px 0; }
</style>
