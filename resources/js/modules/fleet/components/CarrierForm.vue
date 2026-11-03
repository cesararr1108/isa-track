<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import type { Carrier } from '@/types';
import { initials } from '@/shared/lib/format';
import FieldError from '@/shared/ui/FieldError.vue';

const props = defineProps<{ carrier: Carrier | null; vehicleTypes: Record<string, string> }>();
const emit = defineEmits<{ close: [] }>();

const c = props.carrier;
const form = useForm({
    name: c?.name ?? '',
    type: c?.type ?? 'own_driver',
    document_number: c?.document_number ?? '',
    phone: c?.phone ?? '',
    email: c?.email ?? '',
    vehicle_type: c?.vehicle_type ?? 'motorcycle',
    vehicle_plate: c?.vehicle_plate ?? '',
    capacity_kg: c?.capacity_kg?.toString() ?? '',
    capacity_packages: c?.capacity_packages?.toString() ?? '',
    photo: null as File | null,
});

const preview = ref<string | null>(null);
const photoSrc = computed(() => preview.value ?? c?.photo_url ?? null);
function pickPhoto(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null;
    form.photo = file;
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = file ? URL.createObjectURL(file) : null;
}
onBeforeUnmount(() => preview.value && URL.revokeObjectURL(preview.value));

function submit() {
    const url = c ? `/transportadores/${c.id}` : '/transportadores';
    form.post(url, { forceFormData: true, preserveScroll: true, onSuccess: () => emit('close') });
}
</script>

<template>
    <section class="panel">
        <header>
            <h3>{{ c ? `Editar ${c.name}` : 'Nuevo transportador' }}</h3>
            <button class="link" type="button" @click="emit('close')">Cerrar</button>
        </header>
        <form class="fgrid" novalidate @submit.prevent="submit">
            <div class="full row-wrap" style="gap: 14px">
                <img v-if="photoSrc" :src="photoSrc" class="avatar lg" alt="Foto del mensajero" />
                <span v-else class="avatar lg" aria-hidden="true">{{ initials(form.name) }}</span>
                <div class="stack" style="gap: 6px">
                    <span class="section-label" style="margin: 0">Foto del mensajero</span>
                    <label class="btn secondary small">Elegir foto<input id="cr-photo" type="file" accept="image/jpeg,image/png,image/webp" hidden @change="pickPhoto" /></label>
                    <span class="small muted">JPG, PNG o WebP. El cliente la ve mientras su pedido va en camino.</span>
                    <FieldError :message="form.errors.photo" />
                </div>
            </div>
            <label class="field">Nombre completo
                <input id="cr-name" v-model="form.name" required :aria-invalid="!!form.errors.name" /><FieldError :message="form.errors.name" />
            </label>
            <label class="field">Tipo
                <select id="cr-type" v-model="form.type"><option value="own_driver">Mensajero propio</option><option value="external_company">Transportadora externa</option></select>
            </label>
            <label class="field">Cédula
                <input id="cr-doc" v-model="form.document_number" inputmode="numeric" placeholder="1037654321" :aria-invalid="!!form.errors.document_number" /><FieldError :message="form.errors.document_number" />
            </label>
            <label class="field">Teléfono
                <input id="cr-phone" v-model="form.phone" type="tel" placeholder="+57 300 000 0000" :aria-invalid="!!form.errors.phone" /><FieldError :message="form.errors.phone" />
            </label>
            <label class="field">Correo
                <input id="cr-email" v-model="form.email" type="email" :aria-invalid="!!form.errors.email" /><FieldError :message="form.errors.email" />
            </label>
            <label class="field">Vehículo
                <select id="cr-vehicle" v-model="form.vehicle_type"><option v-for="(label, k) in vehicleTypes" :key="k" :value="k">{{ label }}</option></select>
            </label>
            <label class="field">Placa
                <input id="cr-plate" v-model="form.vehicle_plate" placeholder="ABC12D" style="text-transform: uppercase" :aria-invalid="!!form.errors.vehicle_plate" /><FieldError :message="form.errors.vehicle_plate" />
            </label>
            <label class="field">Capacidad (kg)
                <input id="cr-capkg" v-model="form.capacity_kg" inputmode="decimal" placeholder="vacío = sin límite" :aria-invalid="!!form.errors.capacity_kg" /><FieldError :message="form.errors.capacity_kg" />
            </label>
            <label class="field">Capacidad (paquetes)
                <input id="cr-cappk" v-model="form.capacity_packages" inputmode="numeric" placeholder="vacío = sin límite" :aria-invalid="!!form.errors.capacity_packages" /><FieldError :message="form.errors.capacity_packages" />
            </label>
            <div class="full actions">
                <button class="btn" type="submit" :disabled="form.processing">{{ c ? 'Guardar cambios' : 'Registrar transportador' }}</button>
            </div>
        </form>
    </section>
</template>
