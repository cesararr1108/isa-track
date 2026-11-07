<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import type { OrderDetail, ProofRequired, StatusMap } from '@/types';
import { dateTime, money } from '@/shared/lib/format';
import StatusPill from '@/shared/ui/StatusPill.vue';
import DriverLayout from '../layouts/DriverLayout.vue';
import DeliveryForm from '../components/DeliveryForm.vue';
import { currentPosition, startSharing } from '../device/location';

const props = defineProps<{
    order: OrderDetail;
    statuses: StatusMap;
    incidentReasons: Record<string, string>;
    paymentMethods: Record<string, string>;
    proofRequired: ProofRequired;
}>();

const o = computed(() => props.order);
const mode = ref<'none' | 'deliver' | 'incident'>('none');
const busy = ref(false);
const reason = ref('customer_absent');
const note = ref('');

watch(() => o.value.status, (s) => {
    mode.value = 'none';
    if (s === 'in_route') startSharing();
}, { immediate: true });

/** Celular en formato para WhatsApp (Colombia: 57 + 10 dígitos). */
const phoneDigits = computed(() => o.value.recipient.phone.replace(/\D/g, ''));
const waNumber = computed(() => (phoneDigits.value.length === 10 ? `57${phoneDigits.value}` : phoneDigits.value));
const hasPoint = computed(() => o.value.address.latitude !== null && o.value.address.longitude !== null);
const destination = computed(() => (hasPoint.value ? `${o.value.address.latitude},${o.value.address.longitude}` : `${o.value.address.line}, ${o.value.address.city}`));
const googleMaps = computed(() => `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(destination.value)}`);
const waze = computed(() => (hasPoint.value
    ? `https://waze.com/ul?ll=${o.value.address.latitude},${o.value.address.longitude}&navigate=yes`
    : `https://waze.com/ul?q=${encodeURIComponent(destination.value)}&navigate=yes`));

async function move(status: 'in_route' | 'incident', extra: Record<string, string> = {}) {
    busy.value = true;
    const pos = await currentPosition(4000);
    router.post(`/mensajero/pedidos/${o.value.id}/estado`, {
        status,
        ...extra,
        ...(pos ? { latitude: pos.latitude, longitude: pos.longitude } : {}),
    }, { preserveScroll: true, onFinish: () => (busy.value = false) });
}
const reportIncident = () => move('incident', { incident_reason: reason.value, note: note.value });

const lastIncident = computed(() => [...o.value.history].reverse().find((h) => h.status === 'incident') ?? null);
</script>

<template>
    <DriverLayout :title="o.recipient.name" back="/mensajero">
        <section class="panel head">
            <div class="row-wrap between">
                <span class="mono muted">{{ o.tracking_code }}<template v-if="o.external_reference"> · {{ o.external_reference }}</template></span>
                <StatusPill :status="o.status" :statuses="statuses" />
            </div>
            <h1>{{ o.recipient.name }}</h1>
            <p class="addr">{{ o.address.line }}<br /><span class="muted">{{ [o.address.neighborhood, o.address.city].filter(Boolean).join(' · ') }}</span></p>
            <p v-if="o.address.reference" class="ref">📝 {{ o.address.reference }}</p>
            <p v-if="o.promised_at" class="small muted">Entregar antes de: {{ dateTime(o.promised_at) }}</p>

            <div class="quick">
                <a :href="`tel:${o.recipient.phone}`" class="q">📞<span>Llamar</span></a>
                <a :href="`https://wa.me/${waNumber}`" target="_blank" rel="noopener" class="q">💬<span>WhatsApp</span></a>
                <a :href="googleMaps" target="_blank" rel="noopener" class="q">🗺️<span>Maps</span></a>
                <a :href="waze" target="_blank" rel="noopener" class="q">🚗<span>Waze</span></a>
            </div>
        </section>

        <section class="collect" :class="{ none: !o.cash_on_delivery }">
            <template v-if="o.cash_on_delivery && !o.delivery"><span>Cobrar al entregar</span><b class="num">{{ money(o.cash_on_delivery) }}</b></template>
            <template v-else-if="o.delivery"><span>Cobrado</span><b class="num">{{ money(o.delivery.collected_total) }}</b></template>
            <template v-else><span>Ya está pagado</span><b>No cobres nada</b></template>
        </section>

        <p v-if="o.status === 'incident' && lastIncident" class="alert">
            Novedad: {{ incidentReasons[lastIncident.incident_reason ?? ''] ?? 'registrada' }}<template v-if="lastIncident.note"> — {{ lastIncident.note }}</template>.
            Puedes reintentar la entrega o esperar instrucciones de despacho.
        </p>

        <!-- Acciones según el estado -->
        <template v-if="mode === 'none'">
            <section v-if="['assigned', 'incident'].includes(o.status)" class="actions">
                <button class="btn block big" type="button" :disabled="busy" @click="move('in_route')">
                    {{ o.status === 'incident' ? 'Reintentar: salir a entregar' : 'Salir a entregar' }}
                </button>
                <p class="small muted center">Le avisamos al cliente que vas en camino.</p>
            </section>
            <section v-else-if="o.status === 'in_route'" class="actions">
                <button class="btn block big" type="button" @click="mode = 'deliver'">Entregar</button>
                <button class="btn secondary block" type="button" @click="mode = 'incident'">Reportar novedad</button>
            </section>
        </template>

        <DeliveryForm v-if="mode === 'deliver'" :order="o" :payment-methods="paymentMethods" :required="proofRequired" @close="mode = 'none'" />

        <section v-if="mode === 'incident'" class="panel">
            <header><h3>Reportar novedad</h3><button class="link" type="button" @click="mode = 'none'">Cancelar</button></header>
            <form class="pad" @submit.prevent="reportIncident">
                <fieldset class="reasons">
                    <legend class="sr-only">Motivo</legend>
                    <label v-for="(label, key) in incidentReasons" :key="key" class="reason" :class="{ on: reason === key }">
                        <input v-model="reason" type="radio" name="reason" :value="key" /> {{ label }}
                    </label>
                </fieldset>
                <label class="field">Detalle (opcional)
                    <textarea v-model="note" rows="2" placeholder="Ej.: nadie abre, el celular está apagado" />
                </label>
                <button class="btn navy block big" type="submit" :disabled="busy">Guardar novedad</button>
            </form>
        </section>

        <!-- Entregado: prueba -->
        <section v-if="o.delivery" class="panel">
            <header><h3>Entrega registrada</h3><span class="small muted">{{ dateTime(o.delivery.delivered_at) }}</span></header>
            <div class="pad">
                <p class="line"><span class="muted">Recibió</span><b>{{ o.delivery.receiver_name ?? '—' }}</b></p>
                <p v-for="p in o.delivery.payments" :key="p.method + p.amount" class="line">
                    <span class="muted">{{ paymentMethods[p.method] ?? p.method }}<template v-if="p.reference"> · {{ p.reference }}</template></span><b class="num">{{ money(p.amount) }}</b>
                </p>
                <div class="evidence">
                    <img v-if="o.delivery.photo_url" :src="o.delivery.photo_url" alt="Foto de la entrega" />
                    <img v-if="o.delivery.signature_url" :src="o.delivery.signature_url" alt="Firma" class="sig" />
                </div>
            </div>
        </section>

        <section class="panel">
            <header><h3>Paquetes ({{ o.packages.length }})</h3></header>
            <ul class="pk">
                <li v-for="p in o.packages" :key="p.id"><span>{{ p.description }}<br /><small class="mono muted">{{ p.code }}</small></span><span v-if="p.weight_kg" class="muted">{{ p.weight_kg }} kg</span></li>
            </ul>
        </section>

        <section class="panel">
            <header><h3>Historial</h3></header>
            <ol class="hist">
                <li v-for="(h, i) in [...o.history].reverse()" :key="i">
                    <StatusPill :status="h.status" :statuses="statuses" />
                    <small class="muted">{{ dateTime(h.occurred_at) }}<template v-if="h.note"> · {{ h.note }}</template></small>
                </li>
            </ol>
        </section>
    </DriverLayout>
</template>

<style scoped>
.head { padding: 14px; display: grid; gap: 8px; }
.between { justify-content: space-between; }
.head h1 { font-size: 1.45rem; }
.addr { margin: 0; font-size: 1.02rem; font-weight: 600; }
.ref { margin: 0; background: var(--sunken); border-radius: 8px; padding: 8px 10px; font-size: .9rem; }
.quick { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-top: 4px; }
.q { display: grid; justify-items: center; gap: 2px; padding: 10px 4px; border-radius: 12px; background: var(--sunken); border: 1px solid var(--line); text-decoration: none; color: var(--fg); font-size: 1.3rem; }
.q span { font-size: .74rem; font-weight: 700; }
.collect { display: flex; justify-content: space-between; align-items: center; padding: 14px; border-radius: 14px; background: var(--amber); color: var(--amber-ink); font-weight: 700; }
.collect b { font-family: var(--display); font-size: 1.4rem; }
.collect.none { background: var(--accent-soft); color: var(--accent-text); }
.collect.none b { font-size: 1rem; }
.actions { display: grid; gap: 10px; }
.btn.big { font-size: 1.08rem; padding: 16px; }
.center { text-align: center; margin: 0; }
.pad { padding: 14px; display: grid; gap: 12px; }
.reasons { border: 0; padding: 0; margin: 0; display: grid; gap: 8px; }
.reason { display: flex; gap: 10px; align-items: center; padding: 12px; border: 1px solid var(--line); border-radius: 10px; font-weight: 600; cursor: pointer; }
.reason.on { border-color: var(--amber); background: var(--amber-soft); }
.line { margin: 0; display: flex; justify-content: space-between; gap: 10px; }
.evidence { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.evidence img { border-radius: 10px; border: 1px solid var(--line); width: 100%; height: 140px; object-fit: cover; }
.evidence img.sig { object-fit: contain; background: #fff; }
.pk, .hist { list-style: none; margin: 0; padding: 0; }
.pk li { display: flex; justify-content: space-between; gap: 10px; padding: 10px 14px; border-top: 1px solid var(--line); }
.pk li:first-child, .hist li:first-child { border-top: 0; }
.hist li { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 9px 14px; border-top: 1px solid var(--line); }
</style>
