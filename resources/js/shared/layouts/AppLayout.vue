<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import type { SharedProps } from '@/types';
import { ICONS } from '@/shared/lib/icons';
import { initials } from '@/shared/lib/format';
import FlashToast from '@/shared/ui/FlashToast.vue';

defineProps<{ title: string }>();

const page = usePage<SharedProps>();
const user = computed(() => page.props.auth.user);
const menuOpen = ref(false);
watch(() => page.url, () => (menuOpen.value = false));
</script>

<template>
    <Head :title="title" />
    <div class="shell">
        <aside id="side" class="side" :class="{ open: menuOpen }" aria-label="Menú principal">
            <div class="brand">
                <svg width="34" height="34" viewBox="0 0 34 34" aria-hidden="true">
                    <rect width="34" height="34" rx="9" fill="#00C896" />
                    <path d="M9 22c3.4-6.8 6.2-10.2 7.9-10.2s2.8 2.3 2.8 4.5-1.7 4.5-1.7 4.5 4-1.1 6.8-6.8" fill="none" stroke="#0B1F3A" stroke-width="2.6" stroke-linecap="round" />
                    <circle cx="25.5" cy="12.5" r="2.5" fill="#FFB800" />
                </svg>
                <div>
                    <b>ISA Track</b>
                    <small>{{ page.props.company?.name }}</small>
                </div>
            </div>
            <nav class="nav">
                <div v-for="g in page.props.navigation" :key="g.group">
                    <p>{{ g.group }}</p>
                    <ul>
                        <li v-for="item in g.items" :key="item.key">
                            <Link v-if="item.href" :href="item.href" :aria-current="item.active ? 'page' : undefined">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" v-html="ICONS[item.icon]" />
                                {{ item.label }}
                            </Link>
                            <span v-else class="soon" title="Esta sección se está construyendo">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" v-html="ICONS[item.icon]" />
                                {{ item.label }} <em>Pronto</em>
                            </span>
                        </li>
                    </ul>
                </div>
            </nav>
            <div v-if="user" class="foot">
                <div class="me">
                    <img v-if="user.carrier?.photo_url" :src="user.carrier.photo_url" class="avatar sm" alt="" />
                    <span v-else class="avatar sm" aria-hidden="true">{{ initials(user.name) }}</span>
                    <span><b>{{ user.name }}</b><br /><small>{{ user.role_label }}</small></span>
                </div>
                <Link href="/logout" method="post" as="button" class="out">Cerrar sesión</Link>
            </div>
        </aside>
        <div class="scrim" :class="{ open: menuOpen }" @click="menuOpen = false" />
        <div class="content">
            <header class="top">
                <button class="menu-btn" type="button" aria-controls="side" :aria-expanded="menuOpen" @click="menuOpen = !menuOpen">☰ Menú</button>
                <h1>{{ title }}</h1>
                <div class="spacer" />
                <slot name="actions" />
            </header>
            <main>
                <p v-if="page.props.errors.general" class="alert bad" role="alert">{{ page.props.errors.general }}</p>
                <slot />
            </main>
        </div>
    </div>
    <FlashToast />
</template>

<style scoped>
.shell { display: grid; grid-template-columns: 248px minmax(0, 1fr); min-height: 100%; }
.side { background: var(--side-bg); color: var(--side-fg); position: sticky; top: 0; height: 100vh; overflow-y: auto; display: flex; flex-direction: column; gap: 18px; padding: 18px 12px; z-index: 40; }
.brand { display: flex; align-items: center; gap: 10px; padding: 0 8px; color: #fff; }
.brand b { font-family: var(--display); font-size: 1.25rem; }
.brand small { display: block; color: var(--side-muted); font-size: .74rem; }
.nav { display: grid; gap: 14px; }
.nav p { margin: 0 0 4px; padding: 0 10px; font-size: .68rem; text-transform: uppercase; letter-spacing: .1em; color: var(--side-muted); font-weight: 700; }
.nav ul { list-style: none; margin: 0; padding: 0; display: grid; gap: 2px; }
.nav a, .nav .soon { display: flex; align-items: center; gap: 10px; color: var(--side-fg); padding: 8px 10px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: .92rem; position: relative; }
.nav a:hover, .nav a[aria-current="page"] { background: var(--side-hover); color: #fff; }
.nav a[aria-current="page"]::before { content: ""; position: absolute; left: -12px; top: 6px; bottom: 6px; width: 4px; border-radius: 0 4px 4px 0; background: var(--mint); }
.nav .soon { opacity: .55; cursor: default; }
.nav .soon em { margin-left: auto; font-style: normal; font-size: .66rem; border: 1px solid var(--side-muted); border-radius: 999px; padding: 0 6px; }
.foot { margin-top: auto; display: grid; gap: 10px; padding: 0 8px; font-size: .82rem; }
.me { display: flex; gap: 10px; align-items: center; color: #fff; }
.me small { color: var(--side-muted); }
.out { background: none; border: 1px solid var(--side-hover); color: var(--side-fg); border-radius: 8px; padding: 7px 10px; cursor: pointer; }
.content { min-width: 0; display: flex; flex-direction: column; }
.top { position: sticky; top: 0; z-index: 30; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; padding: 12px 20px; background: color-mix(in srgb, var(--bg) 90%, transparent); backdrop-filter: blur(8px); border-bottom: 1px solid var(--line); }
.top h1 { font-size: 1.3rem; }
.spacer { flex: 1; }
.menu-btn { display: none; border: 1px solid var(--line); background: var(--surface); border-radius: 8px; padding: 6px 10px; cursor: pointer; font-weight: 700; }
main { padding: 18px 20px 56px; display: grid; gap: 16px; align-content: start; }
.scrim { display: none; }
@media (max-width: 860px) {
    .shell { grid-template-columns: minmax(0, 1fr); }
    .side { position: fixed; inset: 0 auto 0 0; width: min(280px, 86vw); transform: translateX(-102%); transition: transform .2s ease; }
    .side.open { transform: none; box-shadow: 0 0 40px rgb(0 0 0 / .4); }
    .scrim.open { display: block; position: fixed; inset: 0; background: rgb(4 12 23 / .5); z-index: 35; }
    .menu-btn { display: inline-block; }
    .top, main { padding-left: 16px; padding-right: 16px; }
}
</style>
