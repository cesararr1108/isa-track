<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/shared/layouts/AppLayout.vue';
import CarrierForm from '@/modules/fleet/components/CarrierForm.vue';
import { initials } from '@/shared/lib/format';
import { usePermissions } from '@/shared/lib/permissions';
import type { Carrier } from '@/types';

const props = defineProps<{ carriers: Carrier[]; vehicleTypes: Record<string, string> }>();
const { can } = usePermissions();

const editing = ref<Carrier | 'new' | null>(null);
const formKey = computed(() => (editing.value === 'new' ? 'new' : editing.value?.id ?? 'none'));
const active = computed(() => props.carriers.filter((c) => c.active).length);

function toggle(c: Carrier) {
    router.post(`/transportadores/${c.id}/estado`, { active: !c.active }, { preserveScroll: true });
}
function edit(c: Carrier) {
    editing.value = c;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<template>
    <AppLayout title="Transportadores">
        <template #actions>
            <button v-if="can('carriers.create')" class="btn" type="button" @click="editing = 'new'">+ Nuevo transportador</button>
        </template>
        <p class="intro">Mensajeros propios y transportadoras externas: cédula, teléfono, placa, vehículo, capacidad y foto. La capacidad limita lo que se puede cargar en una ruta. {{ active }} activos de {{ carriers.length }}.</p>

        <CarrierForm v-if="editing" :key="formKey" :carrier="editing === 'new' ? null : editing" :vehicle-types="vehicleTypes" @close="editing = null" />

        <div v-if="carriers.length" class="cards">
            <article v-for="c in carriers" :key="c.id" class="ccard" :class="{ off: !c.active }">
                <div class="row-wrap" style="gap: 12px; flex-wrap: nowrap">
                    <img v-if="c.photo_url" :src="c.photo_url" class="avatar" :alt="`Foto de ${c.name}`" />
                    <span v-else class="avatar" aria-hidden="true">{{ initials(c.name) }}</span>
                    <div style="min-width: 0">
                        <h3 style="font-size: 1.05rem">{{ c.name }}</h3>
                        <span class="small muted">{{ c.type === 'own_driver' ? 'Mensajero propio' : 'Transportadora externa' }}</span><br />
                        <span class="chip" :class="c.active ? 'ok' : 'bad'">{{ c.active ? 'Activo' : 'Inactivo' }}</span>
                    </div>
                </div>
                <dl class="kv">
                    <dt>Cédula</dt><dd class="num">{{ c.document_number ? Number(c.document_number).toLocaleString('es-CO') : '—' }}</dd>
                    <dt>Teléfono</dt><dd class="num">{{ c.phone ?? '—' }}</dd>
                    <dt>Vehículo</dt><dd>{{ c.vehicle_type ? vehicleTypes[c.vehicle_type] : '—' }} <span v-if="c.vehicle_plate" class="plate">{{ c.vehicle_plate }}</span></dd>
                    <dt>Capacidad</dt><dd class="num">{{ c.capacity_kg != null ? `${c.capacity_kg} kg` : 'sin límite' }}<template v-if="c.capacity_packages != null"> · {{ c.capacity_packages }} paquetes</template></dd>
                    <dt>Usuario</dt><dd>{{ c.user_email ?? 'Sin usuario' }}</dd>
                </dl>
                <div v-if="can('carriers.update')" class="actions">
                    <button class="btn secondary small" type="button" @click="edit(c)">Editar</button>
                    <button class="btn secondary small" type="button" @click="toggle(c)">{{ c.active ? 'Desactivar' : 'Activar' }}</button>
                </div>
            </article>
        </div>
        <p v-else class="panel empty">Aún no hay transportadores. Registra el primero con “Nuevo transportador”.</p>
    </AppLayout>
</template>
