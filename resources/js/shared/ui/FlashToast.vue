<script setup lang="ts">
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { SharedProps } from '@/types';

/** Muestra el mensaje de confirmación que envía el servidor después de guardar. */
const page = usePage<SharedProps>();
const text = ref<string | null>(null);
let timer: ReturnType<typeof setTimeout> | undefined;

watch(
    () => page.props.flash.success,
    (msg) => {
        if (!msg) return;
        text.value = msg;
        clearTimeout(timer);
        timer = setTimeout(() => (text.value = null), 3200);
    },
    { immediate: true },
);
</script>

<template>
    <div v-if="text" class="toast" role="status">{{ text }}</div>
</template>

<style scoped>
.toast { position: fixed; left: 50%; bottom: 18px; transform: translateX(-50%); z-index: 2000; max-width: min(560px, calc(100% - 32px)); background: var(--navy); color: #fff; padding: 12px 16px; border-radius: 12px; border-left: 4px solid var(--mint); box-shadow: 0 8px 24px rgb(0 0 0 / .3); font-size: .9rem; }
</style>
