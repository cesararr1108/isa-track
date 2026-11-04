<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import FieldError from '@/shared/ui/FieldError.vue';
import type { PanelUser, RoleInfo } from '@/modules/users/types';

const props = defineProps<{ user: PanelUser | null; roles: RoleInfo[]; permissions: { key: string; label: string }[]; carriers: { id: string; name: string }[]; takenCarriers: string[]; isSelf: boolean }>();
const emit = defineEmits<{ close: [] }>();

const u = props.user;
const form = useForm({
    name: u?.name ?? '',
    email: u?.email ?? '',
    phone: u?.phone ?? '',
    password: '',
    role: u?.role ?? 'dispatcher',
    carrier_id: u?.carrier_id ?? null as string | null,
    active: u?.active ?? true,
    extra_permissions: [...(u?.extra_permissions ?? [])],
    revoked_permissions: [...(u?.revoked_permissions ?? [])],
});

const roleDefaults = computed(() => props.roles.find((r) => r.key === form.role)?.permissions ?? []);
const has = (p: string) => (roleDefaults.value.includes(p) || form.extra_permissions.includes(p)) && !form.revoked_permissions.includes(p);
function toggle(p: string, on: boolean) {
    form.extra_permissions = form.extra_permissions.filter((x) => x !== p);
    form.revoked_permissions = form.revoked_permissions.filter((x) => x !== p);
    const fromRole = roleDefaults.value.includes(p);
    if (on && !fromRole) form.extra_permissions.push(p);
    if (!on && fromRole) form.revoked_permissions.push(p);
}
function changeRole() { form.extra_permissions = []; form.revoked_permissions = []; if (form.role !== 'driver') form.carrier_id = null; }
function submit() {
    const opts = { preserveScroll: true, onSuccess: () => emit('close') };
    form.transform((d) => ({ ...d, password: d.password || null }));
    if (u) form.put(`/usuarios/${u.id}`, opts); else form.post('/usuarios', opts);
}
</script>

<template>
    <section class="panel">
        <header><h3>{{ u ? u.name : 'Nuevo usuario' }}</h3><button class="link" type="button" @click="emit('close')">Cerrar</button></header>
        <form novalidate @submit.prevent="submit">
            <div class="fgrid">
                <label class="field">Nombre<input id="ue-name" v-model="form.name" /><FieldError :message="form.errors.name" /></label>
                <label class="field">Correo<input id="ue-email" v-model="form.email" type="email" autocomplete="off" /><FieldError :message="form.errors.email" /></label>
                <label class="field">Teléfono<input id="ue-phone" v-model="form.phone" type="tel" /></label>
                <label class="field">{{ u ? 'Nueva contraseña (vacío = no cambia)' : 'Contraseña' }}
                    <input id="ue-pass" v-model="form.password" type="password" autocomplete="new-password" />
                    <span class="hint">Mínimo 8 caracteres, con letras y números.</span>
                    <FieldError :message="form.errors.password" />
                </label>
                <label class="field">Rol<select id="ue-role" v-model="form.role" @change="changeRole"><option v-for="r in roles" :key="r.key" :value="r.key">{{ r.label }}</option></select><FieldError :message="form.errors.role" /></label>
                <label v-if="form.role === 'driver'" class="field">Transportador vinculado
                    <select id="ue-carrier" v-model="form.carrier_id">
                        <option :value="null">Elige…</option>
                        <option v-for="c in carriers" :key="c.id" :value="c.id" :disabled="takenCarriers.includes(c.id) && c.id !== u?.carrier_id">{{ c.name }}</option>
                    </select>
                    <FieldError :message="form.errors.carrier_id" />
                </label>
                <label class="check full"><input id="ue-active" v-model="form.active" type="checkbox" :disabled="isSelf" /> Usuario activo (si lo desactivas, se cierran sus sesiones y no puede entrar)</label>
                <FieldError class="full" :message="form.errors.active" />
            </div>
            <div class="tablewrap">
                <table class="t perms">
                    <thead><tr><th>Permiso</th><th>Origen</th><th class="r">Tiene</th></tr></thead>
                    <tbody>
                        <tr v-for="p in permissions" :key="p.key">
                            <td>{{ p.label }}<br /><span class="mono small muted">{{ p.key }}</span></td>
                            <td>
                                <span v-if="roleDefaults.includes(p.key) && form.revoked_permissions.includes(p.key)" class="chip bad">quitado</span>
                                <span v-else-if="roleDefaults.includes(p.key)" class="chip">del rol</span>
                                <span v-else-if="form.extra_permissions.includes(p.key)" class="chip ok">extra</span>
                            </td>
                            <td class="r"><input type="checkbox" class="cb" :checked="has(p.key)" :aria-label="p.label" @change="toggle(p.key, ($event.target as HTMLInputElement).checked)" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="pad actions">
                <button class="btn" type="submit" :disabled="form.processing">{{ u ? 'Guardar usuario' : 'Crear usuario' }}</button>
                <button class="link" type="button" @click="form.extra_permissions = []; form.revoked_permissions = []">Volver a los permisos del rol</button>
            </div>
        </form>
    </section>
</template>

<style scoped>
.perms td:first-child { white-space: normal; }
.cb { width: 18px; height: 18px; accent-color: var(--mint); }
.hint { font-weight: 500; }
</style>
