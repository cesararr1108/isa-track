<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import type { CarrierOption, OrderDetail, StatusMap } from '@/types';
import { dateTime, money } from '@/shared/lib/format';
import { usePermissions } from '@/shared/lib/permissions';
import StatusPill from '@/shared/ui/StatusPill.vue';

const props = defineProps<{
    order: OrderDetail;
    carriers: CarrierOption[];
    statuses: StatusMap;
    transitions: Record<string, string[]>;
    incidentReasons: Record<string, string>;
    paymentMethods: Record<string, string>;
}>();
const { can } = usePermissions();

const allowed = computed(() => props.transitions[props.order.status] ?? []);
const canAssign = computed(() => can('orders.assign') && (allowed.value.includes('assigned') || props.order.status === 'assigned'));
const carrierId = ref(props.order.carrier_id ?? props.carriers[0]?.id ?? '');
const reason = ref('customer_absent');
const carrierName = computed(() => props.carriers.find((c) => c.id === props.order.carrier_id)?.name ?? null);

const opts = { preserveScroll: true };
const assign = () => router.post(`/pedidos/${props.order.id}/asignar`, { carrier_id: carrierId.value }, opts);
const move = (status: string, extra: Record<string, string> = {}) => router.post(`/pedidos/${props.order.id}/estado`, { status, ...extra }, opts);
</script>

<template>
    <section class="panel">
        <div class="pad stack">
            <div class="row-wrap" style="justify-content: space-between">
                <div>
                    <p class="section-label">Pedido <span class="mono">{{ order.tracking_code }}</span><template v-if="order.external_reference"> · {{ order.external_reference }}</template></p>
                    <h3>{{ order.recipient.name }}</h3>
                </div>
                <StatusPill :status="order.status" :statuses="statuses" />
            </div>
            <dl class="kv">
                <dt>Dirección</dt><dd>{{ order.address.line }}<template v-if="order.address.neighborhood">, {{ order.address.neighborhood }}</template>, {{ order.address.city }}<template v-if="order.address.reference"> · {{ order.address.reference }}</template></dd>
                <dt>Celular</dt><dd class="num">{{ order.recipient.phone }}</dd>
                <dt>Paquetes</dt><dd><span v-for="p in order.packages" :key="p.id" style="display: block"><span class="mono">{{ p.code }}</span> {{ p.description }}<template v-if="p.weight_kg"> · {{ p.weight_kg }} kg</template></span></dd>
                <dt>Domicilio</dt><dd><template v-if="order.delivery_fee != null"><span class="fee">{{ money(order.delivery_fee) }}</span> {{ order.zone?.name }}</template><span v-else class="chip warn">Fuera de zona</span></dd>
                <dt>Cobro</dt><dd><b v-if="order.cash_on_delivery" class="num">{{ money(order.cash_on_delivery) }} contra entrega</b><template v-else>Ya pagado</template></dd>
                <dt>Mensajero</dt><dd>{{ carrierName ?? 'Sin asignar' }}</dd>
                <dt>Rastreo</dt><dd><a :href="`/rastreo/${order.tracking_code}`" target="_blank" rel="noopener">Ver como cliente ↗</a></dd>
            </dl>

            <div v-if="order.delivery">
                <p class="section-label">Prueba de entrega</p>
                <dl class="kv">
                    <dt>Recibió</dt><dd>{{ order.delivery.receiver_name }}<template v-if="order.delivery.receiver_document"> · CC {{ order.delivery.receiver_document }}</template></dd>
                    <dt>Fecha</dt><dd>{{ dateTime(order.delivery.delivered_at) }}</dd>
                    <template v-if="order.delivery.payments.length"><dt>Recaudo</dt><dd><span v-for="(p, i) in order.delivery.payments" :key="i" style="display: block">{{ paymentMethods[p.method] ?? p.method }} <b class="num">{{ money(p.amount) }}</b><template v-if="p.reference"> · {{ p.reference }}</template></span></dd></template>
                </dl>
                <div class="evidence" style="margin-top: 8px">
                    <img v-if="order.delivery.photo_url" :src="order.delivery.photo_url" alt="Foto del paquete entregado" />
                    <img v-if="order.delivery.signature_url" :src="order.delivery.signature_url" alt="Firma de quien recibió" />
                </div>
            </div>

            <div v-if="canAssign || can('orders.change_status')" class="actions">
                <template v-if="canAssign && carriers.length">
                    <label class="sr-only" for="assign-to">Mensajero</label>
                    <select id="assign-to" v-model="carrierId" class="sel"><option v-for="c in carriers" :key="c.id" :value="c.id">{{ c.name }}</option></select>
                    <button class="btn" type="button" @click="assign">{{ order.status === 'assigned' ? 'Reasignar' : 'Asignar' }}</button>
                </template>
                <template v-if="can('orders.change_status')">
                    <button v-if="allowed.includes('prepared')" class="btn secondary" type="button" @click="move('prepared')">{{ order.status === 'assigned' ? 'Volver a alistado' : 'Marcar alistado' }}</button>
                    <template v-if="allowed.includes('incident')">
                        <select v-model="reason" class="sel" aria-label="Motivo de la novedad"><option v-for="(label, k) in incidentReasons" :key="k" :value="k">{{ label }}</option></select>
                        <button class="btn secondary" type="button" @click="move('incident', { incident_reason: reason })">Registrar novedad</button>
                    </template>
                    <button v-if="allowed.includes('returned')" class="btn danger" type="button" @click="move('returned')">Marcar devuelto</button>
                </template>
            </div>

            <div>
                <p class="section-label">Historial</p>
                <ol class="timeline">
                    <li v-for="(h, i) in [...order.history].reverse()" :key="i" :style="{ '--c': statuses[h.status]?.color }">
                        <strong>{{ statuses[h.status]?.label ?? h.status }}<template v-if="h.incident_reason"> · {{ incidentReasons[h.incident_reason] }}</template></strong>
                        <template v-if="h.note">{{ h.note }}<br /></template>
                        <small>{{ dateTime(h.occurred_at) }}<template v-if="h.latitude != null"> · GPS {{ h.latitude.toFixed(4) }}, {{ h.longitude?.toFixed(4) }}</template></small>
                    </li>
                </ol>
            </div>
        </div>
    </section>
</template>

<style scoped>
.sel { padding: 8px; border-radius: 8px; border: 1px solid var(--line); background: var(--surface); max-width: 100%; }
</style>
