<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import type { CarrierOption, RoutableOrder, StatusMap } from '@/types';
import StatusPill from '@/shared/ui/StatusPill.vue';
import FieldError from '@/shared/ui/FieldError.vue';

const props = defineProps<{ date: string; carriers: CarrierOption[]; orders: RoutableOrder[]; statuses: StatusMap; vehicleTypes: Record<string, string> }>();
const selected = defineModel<string[]>('selected', { required: true });

const form = useForm({ carrier_id: props.carriers[0]?.id ?? '', date: props.date, name: '', optimize: true, order_ids: [] as string[] });
const carrier = computed(() => props.carriers.find((c) => c.id === form.carrier_id));
const picked = computed(() => props.orders.filter((o) => selected.value.includes(o.id)));
const kg = computed(() => picked.value.reduce((a, o) => a + o.weight_kg, 0));
const pk = computed(() => picked.value.reduce((a, o) => a + o.packages, 0));

function meter(value: number, max: number | null | undefined) {
    if (max == null) return { pct: 0, cls: '', text: 'sin límite' };
    const pct = Math.min(100, (value / max) * 100);
    return { pct, cls: value > max ? 'over' : pct > 80 ? 'near' : '', text: `de ${max}` };
}
const kgMeter = computed(() => meter(kg.value, carrier.value?.capacity_kg));
const pkMeter = computed(() => meter(pk.value, carrier.value?.capacity_packages));

function submit() {
    form.order_ids = [...selected.value];
    form.post('/rutas', { preserveScroll: true, onSuccess: () => { selected.value = []; form.reset('name'); } });
}
</script>

<template>
    <section class="panel">
        <header><h3>Programar ruta</h3></header>
        <div class="fgrid">
            <label class="field">Día<input id="rt-date" v-model="form.date" type="date" /><FieldError :message="form.errors.date" /></label>
            <label class="field">Mensajero
                <select id="rt-carrier" v-model="form.carrier_id">
                    <option v-for="c in carriers" :key="c.id" :value="c.id">{{ c.name }}<template v-if="c.vehicle_type"> · {{ vehicleTypes[c.vehicle_type] }}</template></option>
                </select>
                <FieldError :message="form.errors.carrier_id" />
            </label>
            <label class="field full">Nombre (opcional)<input id="rt-name" v-model="form.name" placeholder="Ej.: Ruta Laureles tarde" /></label>
            <div><span class="small"><b class="num">{{ kg.toFixed(1) }}</b> kg {{ kgMeter.text }}</span><div class="cap"><span :class="kgMeter.cls" :style="{ width: `${kgMeter.pct}%` }" /></div></div>
            <div><span class="small"><b class="num">{{ pk }}</b> paquetes {{ pkMeter.text }}</span><div class="cap"><span :class="pkMeter.cls" :style="{ width: `${pkMeter.pct}%` }" /></div></div>
        </div>
        <div class="pick" role="group" aria-label="Pedidos disponibles">
            <label v-for="o in orders" :key="o.id">
                <input v-model="selected" type="checkbox" :value="o.id" />
                <span><b>{{ o.recipient_name }}</b><br /><span class="small muted">{{ o.neighborhood ?? o.address }} · {{ o.packages }} paq · {{ o.weight_kg }} kg</span></span>
                <StatusPill :status="o.status" :statuses="statuses" />
            </label>
            <p v-if="!orders.length" class="empty">No hay pedidos con ubicación pendientes por programar.</p>
        </div>
        <FieldError :message="form.errors.order_ids" />
        <div class="pad actions">
            <label class="check"><input id="rt-opt" v-model="form.optimize" type="checkbox" /> Ordenar paradas automáticamente</label>
            <button class="btn" type="button" :disabled="!selected.length || !carriers.length || form.processing" @click="submit">
                Programar {{ selected.length || '' }} parada{{ selected.length === 1 ? '' : 's' }}
            </button>
        </div>
    </section>
</template>

<style scoped>
.pick { max-height: 340px; overflow-y: auto; border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }
.pick label { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: 10px; align-items: center; padding: 9px 14px; border-bottom: 1px solid var(--line); cursor: pointer; }
.pick label:last-child { border-bottom: 0; }
.pick input { width: 18px; height: 18px; accent-color: var(--mint); }
</style>
