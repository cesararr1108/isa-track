<script setup lang="ts">
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import type { LatLng } from '@/types';
import { money } from '@/shared/lib/format';
import { usePermissions } from '@/shared/lib/permissions';
import FieldError from '@/shared/ui/FieldError.vue';

const props = defineProps<{ point: LatLng | null }>();
const emit = defineEmits<{ close: []; pickOnMap: [] }>();
const { can } = usePermissions();

const form = useForm({
    external_reference: '',
    cash_on_delivery: '',
    recipient: { name: '', phone: '', email: '' },
    address: { line: '', neighborhood: '', reference: '', latitude: null as number | null, longitude: null as number | null },
    packages: [{ description: '', weight_kg: '' }],
});

/* Tarifa de la zona donde cae el punto (la calcula el servidor). */
const quote = ref<{ zone_name: string; delivery_fee: number } | null | 'out'>(null);
watch(() => props.point, async (p) => {
    form.address.latitude = p?.[0] ?? null;
    form.address.longitude = p?.[1] ?? null;
    quote.value = null;
    if (!p || !can('zones.view')) return;
    const res = await fetch(`/zonas/cotizar?latitude=${p[0]}&longitude=${p[1]}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    if (res.ok) quote.value = (await res.json()).data ?? 'out';
}, { immediate: true });

function submit() {
    form
        .transform((d) => ({
            ...d,
            cash_on_delivery: d.cash_on_delivery === '' ? null : Number(String(d.cash_on_delivery).replace(/\D/g, '')),
            packages: d.packages.map((p) => ({ description: p.description, weight_kg: p.weight_kg === '' ? null : Number(p.weight_kg) })),
        }))
        .post('/pedidos', { preserveScroll: true, onSuccess: () => emit('close') });
}
</script>

<template>
    <section class="panel">
        <header><h3>Nuevo pedido</h3><button class="link" type="button" @click="emit('close')">Cerrar</button></header>
        <form class="fgrid" novalidate @submit.prevent="submit">
            <label class="field">Destinatario<input id="no-name" v-model="form.recipient.name" autocomplete="off" /><FieldError :message="form.errors['recipient.name']" /></label>
            <label class="field">Celular (WhatsApp)<input id="no-phone" v-model="form.recipient.phone" type="tel" placeholder="+57 300 000 0000" /><FieldError :message="form.errors['recipient.phone']" /></label>
            <label class="field full">Dirección<input id="no-line" v-model="form.address.line" placeholder="Cra 43A # 1-50, apto 302" /><FieldError :message="form.errors['address.line']" /></label>
            <label class="field">Barrio<input id="no-barrio" v-model="form.address.neighborhood" /></label>
            <label class="field">Indicaciones<input id="no-ref" v-model="form.address.reference" placeholder="Portería, torre 2" /></label>
            <div class="full stack" style="gap: 6px">
                <span class="section-label" style="margin: 0">Ubicación en el mapa</span>
                <div class="row-wrap">
                    <button class="btn secondary small" type="button" @click="emit('pickOnMap')">{{ point ? 'Cambiar punto' : 'Marcar en el mapa' }}</button>
                    <span v-if="point" class="small muted num">{{ point[0].toFixed(5) }}, {{ point[1].toFixed(5) }}</span>
                    <span v-else class="small muted">Sin ubicación no se calcula la tarifa ni se puede poner en una ruta.</span>
                </div>
                <span v-if="quote && quote !== 'out'"><span class="fee">{{ money(quote.delivery_fee) }}</span> zona {{ quote.zone_name }}</span>
                <span v-else-if="quote === 'out'" class="chip warn">Fuera de cobertura: sin tarifa</span>
            </div>
            <div class="full stack" style="gap: 6px">
                <span class="section-label" style="margin: 0">Paquetes</span>
                <div v-for="(p, i) in form.packages" :key="i" class="row-wrap" style="flex-wrap: nowrap">
                    <input v-model="p.description" :aria-label="`Paquete ${i + 1}`" placeholder="Descripción" class="grow" />
                    <input v-model="p.weight_kg" inputmode="decimal" aria-label="Peso en kg" placeholder="kg" style="width: 80px" />
                    <button v-if="form.packages.length > 1" class="link" type="button" aria-label="Quitar paquete" @click="form.packages.splice(i, 1)">×</button>
                </div>
                <button class="link" type="button" style="justify-self: start" @click="form.packages.push({ description: '', weight_kg: '' })">+ Agregar paquete</button>
                <FieldError :message="form.errors.packages ?? form.errors['packages.0.description']" />
            </div>
            <label class="field">Cobrar contra entrega (vacío = ya pagado)<input id="no-cod" v-model="form.cash_on_delivery" inputmode="numeric" placeholder="95000" /><FieldError :message="form.errors.cash_on_delivery" /></label>
            <label class="field">Referencia de la tienda<input id="no-extref" v-model="form.external_reference" placeholder="WEB-2041" /></label>
            <div class="full actions"><button class="btn" type="submit" :disabled="form.processing">Crear pedido</button><span class="small muted">Se genera el código de rastreo y se avisa al cliente.</span></div>
        </form>
    </section>
</template>

<style scoped>
.row-wrap input { padding: 9px 10px; border: 1px solid var(--line); border-radius: 8px; background: var(--surface); min-width: 0; }
.grow { flex: 1; }
</style>
