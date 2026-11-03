<script setup lang="ts">
import { reactive, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/shared/layouts/AppLayout.vue';
import ZoneMap from '@/modules/zones/components/ZoneMap.vue';
import FieldError from '@/shared/ui/FieldError.vue';
import { money } from '@/shared/lib/format';
import { usePermissions } from '@/shared/lib/permissions';
import type { LatLng, Zone } from '@/types';

const props = defineProps<{ zones: Zone[] }>();
const { can } = usePermissions();

/* ---- dibujar una zona nueva ---- */
const drawing = ref(false);
const form = useForm({ name: '', color: '#00C896', delivery_fee: '', estimated_minutes: '', polygon: [] as LatLng[] });
function startDrawing() { drawing.value = true; quote.value = null; form.reset(); form.clearErrors(); }
function saveZone() {
    form.post('/zonas', { preserveScroll: true, onSuccess: () => { drawing.value = false; form.reset(); } });
}

/* ---- tarifas y estado ---- */
const fees = reactive<Record<string, string>>(Object.fromEntries(props.zones.map((z) => [z.id, String(z.delivery_fee)])));
const confirmDelete = ref<string | null>(null);
function payload(z: Zone, changes: Partial<{ delivery_fee: number; active: boolean }>) {
    return { name: z.name, color: z.color, delivery_fee: z.delivery_fee, estimated_minutes: z.estimated_minutes, active: z.active, polygon: z.polygon, ...changes };
}
function saveFee(z: Zone) {
    router.put(`/zonas/${z.id}`, payload(z, { delivery_fee: Number(String(fees[z.id] ?? '').replace(/\D/g, '')) }), { preserveScroll: true });
}
function toggle(z: Zone) { router.put(`/zonas/${z.id}`, payload(z, { active: !z.active }), { preserveScroll: true }); }
function remove(z: Zone) { router.delete(`/zonas/${z.id}`, { preserveScroll: true, onFinish: () => (confirmDelete.value = null) }); }

/* ---- cotizar un punto (lo calcula el servidor) ---- */
const quote = ref<{ point: LatLng; zone: { zone_name: string; delivery_fee: number; estimated_minutes: number | null } | null } | null>(null);
async function pick(point: LatLng) {
    const res = await fetch(`/zonas/cotizar?latitude=${point[0]}&longitude=${point[1]}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    quote.value = res.ok ? { point, zone: (await res.json()).data } : null;
}
</script>

<template>
    <AppLayout title="Zonas y tarifas">
        <template #actions>
            <button v-if="can('zones.manage') && !drawing" class="btn" type="button" @click="startDrawing">+ Dibujar nueva zona</button>
        </template>
        <p class="intro">Cada zona es un área en el mapa con su precio de domicilio. Al crear un pedido, la dirección cae en una zona y toma su tarifa; si dos zonas se cruzan gana la más pequeña. Haz clic en el mapa para cotizar un punto.</p>

        <div class="grid-side">
            <div class="stack">
                <section v-if="drawing" class="panel">
                    <header><h3>Nueva zona</h3><button class="link" type="button" @click="drawing = false">Cancelar</button></header>
                    <form class="fgrid" novalidate @submit.prevent="saveZone">
                        <p class="full alert">Haz clic en el mapa para marcar los vértices ({{ form.polygon.length }} marcados, mínimo 3).</p>
                        <label class="field">Nombre<input id="zn-name" v-model="form.name" placeholder="Ej.: Sabaneta" /><FieldError :message="form.errors.name" /></label>
                        <label class="field">Color<input id="zn-color" v-model="form.color" type="color" /></label>
                        <label class="field">Tarifa del domicilio (pesos)<input id="zn-fee" v-model="form.delivery_fee" inputmode="numeric" placeholder="9000" /><FieldError :message="form.errors.delivery_fee" /></label>
                        <label class="field">Tiempo estimado (min)<input id="zn-min" v-model="form.estimated_minutes" inputmode="numeric" placeholder="45" /><FieldError :message="form.errors.estimated_minutes" /></label>
                        <FieldError class="full" :message="form.errors.polygon" />
                        <div class="full actions">
                            <button class="btn" type="submit" :disabled="form.polygon.length < 3 || form.processing">Guardar zona</button>
                            <button class="btn secondary" type="button" :disabled="!form.polygon.length" @click="form.polygon.pop()">Deshacer punto</button>
                        </div>
                    </form>
                </section>

                <section v-if="quote" class="panel pad stack" style="gap: 6px" aria-live="polite">
                    <span class="section-label" style="margin: 0">Cotización del punto</span>
                    <template v-if="quote.zone"><b>{{ quote.zone.zone_name }}</b><span><span class="fee">{{ money(quote.zone.delivery_fee) }}</span><template v-if="quote.zone.estimated_minutes"> · ≈ {{ quote.zone.estimated_minutes }} min</template></span></template>
                    <p v-else class="alert">Fuera de cobertura: no hay tarifa para ese punto.</p>
                </section>

                <section class="panel">
                    <header><h3>Zonas</h3><span class="small muted">{{ zones.filter((z) => z.active).length }} activas</span></header>
                    <div class="tablewrap">
                        <table class="t">
                            <thead><tr><th>Zona</th><th class="r">Tarifa</th></tr></thead>
                            <tbody>
                                <tr v-for="z in zones" :key="z.id">
                                    <td>
                                        <span class="pill" :style="{ '--c': z.color }">{{ z.name }}</span> <span v-if="!z.active" class="chip">pausada</span>
                                        <span v-if="z.estimated_minutes" class="small muted"> · ≈ {{ z.estimated_minutes }} min</span>
                                        <div v-if="can('zones.manage')" class="actions" style="margin-top: 6px">
                                            <button class="btn secondary small" type="button" @click="toggle(z)">{{ z.active ? 'Pausar' : 'Activar' }}</button>
                                            <button v-if="confirmDelete === z.id" class="btn danger small" type="button" @click="remove(z)">¿Eliminar?</button>
                                            <button v-else class="link small" type="button" @click="confirmDelete = z.id">Eliminar</button>
                                        </div>
                                    </td>
                                    <td class="r">
                                        <form v-if="can('zones.manage')" class="row-wrap" style="justify-content: flex-end; flex-wrap: nowrap" @submit.prevent="saveFee(z)">
                                            <label class="sr-only" :for="`fee-${z.id}`">Tarifa de {{ z.name }}</label>
                                            <input :id="`fee-${z.id}`" v-model="fees[z.id]" inputmode="numeric" class="fee-input" />
                                            <button class="btn secondary small" type="submit">Guardar</button>
                                        </form>
                                        <span v-else class="fee">{{ money(z.delivery_fee) }}</span>
                                    </td>
                                </tr>
                                <tr v-if="!zones.length"><td colspan="2" class="empty">Aún no hay zonas. Dibuja la primera en el mapa.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <section class="panel">
                <header><h3>Mapa de cobertura</h3><span class="small muted">{{ drawing ? 'Modo dibujo: clic para agregar vértices' : 'Clic en el mapa para cotizar' }}</span></header>
                <ZoneMap :zones="zones" :draft="drawing ? form.polygon : null" :draft-color="form.color" :quote-point="quote?.point ?? null"
                    @add-vertex="(p) => form.polygon.push(p)" @pick="pick" />
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.fee-input { width: 96px; text-align: right; padding: 5px 8px; border: 1px solid var(--line); border-radius: 7px; background: var(--surface); }
</style>
