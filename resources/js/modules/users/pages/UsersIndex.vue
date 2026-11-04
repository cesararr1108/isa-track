<script setup lang="ts">
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/shared/layouts/AppLayout.vue';
import UserForm from '@/modules/users/components/UserForm.vue';
import { dateTime, initials } from '@/shared/lib/format';
import type { SharedProps } from '@/types';
import type { AuditEntry, PanelUser, RoleInfo } from '@/modules/users/types';

const props = defineProps<{ users: PanelUser[]; permissions: { key: string; label: string }[]; roles: RoleInfo[]; carriers: { id: string; name: string }[]; activity: AuditEntry[] }>();
const page = usePage<SharedProps>();

const editing = ref<PanelUser | 'new' | null>(null);
const roleLabel = (k: string) => props.roles.find((r) => r.key === k)?.label ?? k;
const carrierName = (id: string | null) => props.carriers.find((c) => c.id === id)?.name ?? null;
const taken = computed(() => props.users.map((u) => u.carrier_id).filter((x): x is string => !!x));

const ACTIONS: Record<string, string> = {
    'user.created': 'creó el usuario', 'user.updated': 'modificó el usuario', 'cash.closed': 'cerró la caja de',
    'login.success': 'inició sesión', 'login.failed': 'intento fallido de inicio de sesión',
};
const FIELDS: Record<string, string> = {
    permissions: 'permisos extra', revoked_permissions: 'permisos quitados', role: 'rol', active: 'estado', name: 'nombre',
    email: 'correo', phone: 'teléfono', carrier_id: 'transportador', contraseña: 'contraseña',
};
function describe(a: AuditEntry): string {
    const d = a.data ?? {};
    if (a.action.startsWith('user.')) return `${ACTIONS[a.action]} ${String(d.email ?? '')}${Array.isArray(d.changed) ? ` (${(d.changed as string[]).map((c) => FIELDS[c] ?? c).join(', ')})` : ''}`;
    if (a.action === 'cash.closed') return `${ACTIONS[a.action]} ${carrierName(a.subject_id) ?? 'un mensajero'} del ${String(d.date ?? '')}`;
    if (a.action === 'login.failed') return `${ACTIONS[a.action]} (${String(d.email ?? '')})`;
    return ACTIONS[a.action] ?? a.action;
}
</script>

<template>
    <AppLayout title="Usuarios y permisos">
        <template #actions><button class="btn" type="button" @click="editing = 'new'">+ Nuevo usuario</button></template>
        <p class="intro">Cada usuario tiene un rol con sus permisos. Además puedes darle permisos extra o quitarle alguno del rol. Los cambios aplican de inmediato y quedan en la bitácora.</p>

        <div class="grid-side">
            <section class="panel">
                <header><h3>Usuarios</h3><span class="small muted">{{ users.filter((u) => u.active).length }} activos</span></header>
                <ul class="rows">
                    <li v-for="u in users" :key="u.id">
                        <button class="row user-row" type="button" :aria-current="editing !== 'new' && editing?.id === u.id" @click="editing = u">
                            <img v-if="u.carrier?.photo_url" :src="u.carrier.photo_url" class="avatar sm" alt="" />
                            <span v-else class="avatar sm" aria-hidden="true">{{ initials(u.name) }}</span>
                            <span><span class="name">{{ u.name }}</span><span class="meta">{{ u.email }}<template v-if="carrierName(u.carrier_id)"> · {{ carrierName(u.carrier_id) }}</template></span></span>
                            <span class="right">
                                <span class="chip" :class="u.active ? 'ok' : 'bad'">{{ u.active ? roleLabel(u.role) : 'Inactivo' }}</span>
                                <span v-if="u.extra_permissions.length || u.revoked_permissions.length" class="small muted">
                                    <template v-if="u.extra_permissions.length">+{{ u.extra_permissions.length }}</template>
                                    <template v-if="u.revoked_permissions.length"> −{{ u.revoked_permissions.length }}</template>
                                </span>
                            </span>
                        </button>
                    </li>
                </ul>
            </section>
            <UserForm v-if="editing" :key="editing === 'new' ? 'new' : editing.id" :user="editing === 'new' ? null : editing" :roles="roles" :permissions="permissions"
                :carriers="carriers" :taken-carriers="taken" :is-self="editing !== 'new' && editing.id === page.props.auth.user?.id" @close="editing = null" />
            <p v-else class="panel empty">Elige un usuario para ver y cambiar sus permisos.</p>
        </div>

        <section class="panel">
            <header><h3>Actividad reciente</h3><span class="small muted">Bitácora de auditoría</span></header>
            <ul class="log">
                <li v-for="(a, i) in activity" :key="i" :class="{ warn: a.action === 'login.failed' }">
                    <span class="small muted num">{{ dateTime(a.at) }}</span>
                    <span><b>{{ a.user_name ?? 'Alguien' }}</b> {{ describe(a) }}</span>
                    <span class="small muted mono">{{ a.ip }}</span>
                </li>
                <li v-if="!activity.length" class="empty">Aún no hay actividad registrada.</li>
            </ul>
        </section>
    </AppLayout>
</template>

<style scoped>
.user-row { grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; }
.log { list-style: none; margin: 0; padding: 0; }
.log li { display: grid; grid-template-columns: 130px minmax(0, 1fr) auto; gap: 12px; padding: 9px 14px; border-bottom: 1px solid var(--line); font-size: .88rem; align-items: center; }
.log li:last-child { border-bottom: 0; }
.log li.warn { background: var(--amber-soft); }
@media (max-width: 600px) { .log li { grid-template-columns: minmax(0, 1fr); gap: 2px; } }
</style>
