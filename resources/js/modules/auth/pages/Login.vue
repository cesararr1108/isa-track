<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import FieldError from '@/shared/ui/FieldError.vue';

const form = useForm({ email: '', password: '', remember: false });
const submit = () => form.post('/login', { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Iniciar sesión" />
    <div class="wrap">
        <form class="card" novalidate @submit.prevent="submit">
            <div class="brand">
                <svg width="40" height="40" viewBox="0 0 34 34" aria-hidden="true">
                    <rect width="34" height="34" rx="9" fill="#00C896" />
                    <path d="M9 22c3.4-6.8 6.2-10.2 7.9-10.2s2.8 2.3 2.8 4.5-1.7 4.5-1.7 4.5 4-1.1 6.8-6.8" fill="none" stroke="#0B1F3A" stroke-width="2.6" stroke-linecap="round" />
                    <circle cx="25.5" cy="12.5" r="2.5" fill="#FFB800" />
                </svg>
                <div><h1>ISA Track</h1><p class="muted">Entra con tu correo de la empresa</p></div>
            </div>
            <label class="field">Correo
                <input id="email" v-model="form.email" type="email" autocomplete="username" required autofocus :aria-invalid="!!form.errors.email" />
                <FieldError :message="form.errors.email" />
            </label>
            <label class="field">Contraseña
                <input id="password" v-model="form.password" type="password" autocomplete="current-password" required :aria-invalid="!!form.errors.password" />
                <FieldError :message="form.errors.password" />
            </label>
            <label class="check"><input id="remember" v-model="form.remember" type="checkbox" /> Mantener la sesión en este equipo</label>
            <button class="btn block" type="submit" :disabled="form.processing">{{ form.processing ? 'Entrando…' : 'Entrar' }}</button>
        </form>
    </div>
</template>

<style scoped>
.wrap { min-height: 100%; display: grid; place-items: center; padding: 24px 16px; background: linear-gradient(160deg, var(--navy) 0 42%, var(--bg) 42%); }
.card { width: min(400px, 100%); background: var(--surface); border: 1px solid var(--line); border-radius: 16px; padding: 24px; display: grid; gap: 14px; box-shadow: 0 20px 50px rgb(11 31 58 / .25); }
.brand { display: flex; gap: 12px; align-items: center; }
.brand h1 { font-size: 1.5rem; }
.brand p { margin: 0; font-size: .88rem; }
</style>
