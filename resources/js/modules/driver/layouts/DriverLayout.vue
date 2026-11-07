<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import type { SharedProps } from '@/types';
import { initials } from '@/shared/lib/format';
import FlashToast from '@/shared/ui/FlashToast.vue';
import { setupPwa } from '../device/pwa';
import { sharing } from '../device/location';

/** Marco de la app del mensajero: pensado para una mano y pantalla de celular. */
defineProps<{ title: string; back?: string }>();

const page = usePage<SharedProps>();
const user = computed(() => page.props.auth.user);
onMounted(setupPwa);

const gps = computed(() => ({
    off: null,
    starting: { cls: 'wait', text: 'Buscando GPS…' },
    on: { cls: 'ok', text: 'Compartiendo ubicación' },
    denied: { cls: 'bad', text: 'GPS bloqueado: actívalo en el navegador' },
    unavailable: { cls: 'bad', text: 'Sin GPS' },
})[sharing.state.value]);
</script>

<template>
    <Head :title="title" />
    <div class="app">
        <header class="bar">
            <Link v-if="back" :href="back" class="back" aria-label="Volver">‹</Link>
            <div v-else class="logo" aria-hidden="true">
                <svg width="30" height="30" viewBox="0 0 34 34"><rect width="34" height="34" rx="9" fill="#00C896" /><path d="M9 22c3.4-6.8 6.2-10.2 7.9-10.2s2.8 2.3 2.8 4.5-1.7 4.5-1.7 4.5 4-1.1 6.8-6.8" fill="none" stroke="#0B1F3A" stroke-width="2.6" stroke-linecap="round" /><circle cx="25.5" cy="12.5" r="2.5" fill="#FFB800" /></svg>
            </div>
            <div class="ttl">
                <b>{{ title }}</b>
                <small>{{ page.props.company?.name }}</small>
            </div>
            <details v-if="user" class="me">
                <summary aria-label="Mi cuenta">
                    <img v-if="user.carrier?.photo_url" :src="user.carrier.photo_url" class="avatar sm" alt="" />
                    <span v-else class="avatar sm">{{ initials(user.name) }}</span>
                </summary>
                <div class="menu">
                    <p><b>{{ user.name }}</b><br /><small>{{ user.role_label }}</small></p>
                    <Link href="/logout" method="post" as="button" class="btn secondary block">Cerrar sesión</Link>
                </div>
            </details>
        </header>
        <p v-if="gps" class="gps" :class="gps.cls" role="status"><i aria-hidden="true" />{{ gps.text }}</p>
        <main>
            <p v-if="page.props.errors.general" class="alert bad" role="alert">{{ page.props.errors.general }}</p>
            <slot />
        </main>
    </div>
    <FlashToast />
</template>

<style scoped>
.app { min-height: 100%; max-width: 560px; margin: 0 auto; display: flex; flex-direction: column; }
.bar { position: sticky; top: 0; z-index: 30; display: flex; align-items: center; gap: 10px; padding: calc(10px + env(safe-area-inset-top)) 14px 10px; background: var(--navy); color: #fff; }
.back { color: #fff; text-decoration: none; font-size: 2rem; line-height: 1; width: 34px; height: 34px; display: grid; place-items: center; margin-left: -6px; }
.logo { display: grid; }
.ttl { flex: 1; min-width: 0; }
.ttl b { display: block; font-family: var(--display); font-size: 1.12rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ttl small { color: #9fb2cc; font-size: .75rem; }
.me { position: relative; }
.me summary { list-style: none; cursor: pointer; }
.me summary::-webkit-details-marker { display: none; }
.menu { position: absolute; right: 0; top: 44px; width: 220px; background: var(--surface); color: var(--fg); border: 1px solid var(--line); border-radius: 12px; padding: 12px; box-shadow: 0 10px 30px rgb(0 0 0 / .25); display: grid; gap: 10px; }
.menu p { margin: 0; }
.menu small { color: var(--muted); }
.gps { margin: 0; display: flex; align-items: center; gap: 8px; padding: 6px 14px; font-size: .8rem; font-weight: 700; }
.gps i { width: 8px; height: 8px; border-radius: 50%; background: currentColor; }
.gps.ok { background: var(--accent-soft); color: var(--accent-text); }
.gps.ok i { animation: pulse 1.6s infinite; }
.gps.wait { background: var(--sunken); color: var(--muted); }
.gps.bad { background: var(--amber-soft); color: var(--amber-text); }
main { padding: 14px 14px calc(40px + env(safe-area-inset-bottom)); display: grid; gap: 14px; align-content: start; }
@keyframes pulse { 50% { opacity: .3; } }
</style>
