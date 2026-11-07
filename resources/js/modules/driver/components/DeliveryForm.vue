<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import type { OrderDetail, ProofRequired } from '@/types';
import { money } from '@/shared/lib/format';
import FieldError from '@/shared/ui/FieldError.vue';
import SignaturePad from './SignaturePad.vue';
import { shrinkPhoto } from '../device/camera';
import { currentPosition } from '../device/location';

/**
 * Registrar la entrega: quién recibe, foto, firma y cobro. Lo obligatorio lo dice la
 * configuración (proofRequired) y el servidor lo vuelve a validar; si los cobros no
 * suman lo que hay que cobrar, el servidor responde con un mensaje claro.
 */
const props = defineProps<{ order: OrderDetail; paymentMethods: Record<string, string>; required: ProofRequired }>();
const emit = defineEmits<{ close: [] }>();

const due = props.order.cash_on_delivery ?? 0;
const form = useForm({
    receiver_name: '',
    receiver_document: '',
    note: '',
    photo: null as File | null,
    signature: null as File | null,
    latitude: null as number | null,
    longitude: null as number | null,
    payments: due > 0 ? [{ method: 'cash', amount: String(due), reference: '' }] : ([] as { method: string; amount: string; reference: string }[]),
});

const pad = ref<InstanceType<typeof SignaturePad> | null>(null);
const signed = ref(false);
const preview = ref<string | null>(null);
const working = ref(false);
const localError = ref<string | null>(null);

async function pickPhoto(e: Event) {
    const raw = (e.target as HTMLInputElement).files?.[0] ?? null;
    if (!raw) return;
    working.value = true;
    form.photo = await shrinkPhoto(raw);
    working.value = false;
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = URL.createObjectURL(form.photo);
}
onBeforeUnmount(() => preview.value && URL.revokeObjectURL(preview.value));

const toNumber = (v: string) => Number(String(v).replace(/\D/g, '')) || 0;
const paid = computed(() => form.payments.reduce((t, p) => t + toNumber(p.amount), 0));
const diff = computed(() => due - paid.value);
const addPayment = () => form.payments.push({ method: 'nequi', amount: diff.value > 0 ? String(diff.value) : '', reference: '' });

async function submit() {
    localError.value = null;
    if (props.required.receiver_name && !form.receiver_name.trim()) return (localError.value = 'Escribe el nombre de quien recibe.');
    if (props.required.photo && !form.photo) return (localError.value = 'Toma la foto del paquete entregado.');
    if (props.required.signature && !signed.value) return (localError.value = 'Falta la firma de quien recibe.');
    if (due > 0 && diff.value !== 0) return (localError.value = `Los cobros deben sumar ${money(due)}. Llevas ${money(paid.value)}.`);

    working.value = true;
    form.signature = (await pad.value?.toFile()) ?? null;
    const pos = await currentPosition(5000);
    form.latitude = pos?.latitude ?? null;
    form.longitude = pos?.longitude ?? null;
    working.value = false;

    form
        .transform((d) => ({
            ...d,
            latitude: d.latitude ?? '',
            longitude: d.longitude ?? '',
            payments: d.payments.map((p) => ({ method: p.method, amount: toNumber(p.amount), reference: p.reference })),
        }))
        .post(`/mensajero/pedidos/${props.order.id}/entregar`, { forceFormData: true, preserveScroll: true });
}

const firstError = computed(() => Object.values(form.errors)[0] ?? null);
</script>

<template>
    <section class="panel sheet">
        <header>
            <h3>Registrar entrega</h3>
            <button class="link" type="button" @click="emit('close')">Cancelar</button>
        </header>
        <form class="pad" novalidate @submit.prevent="submit">
            <label class="field"><span>Nombre de quien recibe<span v-if="required.receiver_name" class="req">*</span></span>
                <input v-model="form.receiver_name" autocomplete="off" autocapitalize="words" :aria-invalid="!!form.errors.receiver_name" />
                <FieldError :message="form.errors.receiver_name" />
            </label>
            <label class="field">Documento (opcional)
                <input v-model="form.receiver_document" inputmode="numeric" autocomplete="off" />
            </label>

            <div class="field">
                <span>Foto de la entrega<span v-if="required.photo" class="req">*</span></span>
                <label class="photo" :class="{ has: preview }">
                    <img v-if="preview" :src="preview" alt="Foto tomada" />
                    <span v-else>📷 Tomar foto</span>
                    <input type="file" accept="image/*" capture="environment" hidden @change="pickPhoto" />
                </label>
                <FieldError :message="form.errors.photo" />
            </div>

            <div class="field">
                <span>Firma<span v-if="required.signature" class="req">*</span></span>
                <SignaturePad ref="pad" @change="(empty) => (signed = !empty)" />
                <FieldError :message="form.errors.signature" />
            </div>

            <div v-if="due > 0" class="field">
                <span>Cobro contra entrega · <b class="due">{{ money(due) }}</b></span>
                <div v-for="(p, i) in form.payments" :key="i" class="pay">
                    <select v-model="p.method" aria-label="Medio de pago">
                        <option v-for="(label, key) in paymentMethods" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <input v-model="p.amount" inputmode="numeric" aria-label="Valor" placeholder="Valor" />
                    <input v-if="p.method !== 'cash'" v-model="p.reference" class="ref" placeholder="Referencia / comprobante" aria-label="Referencia" />
                    <button v-if="form.payments.length > 1" class="link" type="button" aria-label="Quitar" @click="form.payments.splice(i, 1)">Quitar</button>
                </div>
                <div class="row-wrap between">
                    <button v-if="form.payments.length < 5" class="link" type="button" @click="addPayment">+ Pagó con otro medio</button>
                    <span class="sum" :class="{ ok: diff === 0 }">{{ diff === 0 ? '✓ Cuadra' : diff > 0 ? `Faltan ${money(diff)}` : `Sobran ${money(-diff)}` }}</span>
                </div>
            </div>
            <p v-else class="paid">Este pedido ya está pagado: no cobres nada.</p>

            <label class="field">Nota (opcional)
                <textarea v-model="form.note" rows="2" placeholder="Ej.: lo recibió el portero" />
            </label>

            <p v-if="localError || firstError" class="alert bad" role="alert">{{ localError ?? firstError }}</p>
            <button class="btn block big" type="submit" :disabled="form.processing || working">
                {{ form.processing ? `Enviando… ${form.progress?.percentage ?? ''}${form.progress ? '%' : ''}` : working ? 'Preparando…' : 'Confirmar entrega' }}
            </button>
        </form>
    </section>
</template>

<style scoped>
.pad { padding: 14px; display: grid; gap: 14px; }
.req { color: var(--danger); margin-left: 2px; }
.field > span { font-size: .78rem; color: var(--muted); font-weight: 700; }
.field input, .field select, .field textarea { font-size: 1rem; }
.photo { display: grid; place-items: center; min-height: 120px; border: 2px dashed var(--line); border-radius: 12px; background: var(--sunken); cursor: pointer; font-weight: 700; color: var(--fg); font-size: 1rem; overflow: hidden; }
.photo.has { border-style: solid; padding: 0; }
.photo img { width: 100%; max-height: 260px; object-fit: cover; }
.pay { display: grid; grid-template-columns: 1fr 1fr auto; gap: 8px; align-items: center; }
.pay .ref { grid-column: 1 / 3; }
.pay select, .pay input { padding: 10px; border: 1px solid var(--line); border-radius: 8px; background: var(--surface); min-width: 0; }
.between { justify-content: space-between; }
.due { color: var(--amber-text); }
.sum { font-weight: 800; font-size: .85rem; color: var(--amber-text); }
.sum.ok { color: var(--accent-text); }
.paid { margin: 0; padding: 10px 12px; border-radius: 10px; background: var(--accent-soft); color: var(--accent-text); font-weight: 700; }
.btn.big { font-size: 1.05rem; padding: 15px; }
</style>
