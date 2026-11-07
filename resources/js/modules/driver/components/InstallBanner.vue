<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { install, isIos, isStandalone, pwa } from '../device/pwa';

/** Invita a instalar la app en la pantalla de inicio (una vez; se puede cerrar). */
const KEY = 'isa-install-dismissed';
const hidden = ref(true);
const ios = ref(false);

onMounted(() => {
    let dismissed = false;
    try {
        dismissed = localStorage.getItem(KEY) === '1';
    } catch {
        dismissed = false;
    }
    ios.value = isIos();
    hidden.value = dismissed || isStandalone();
});

function close() {
    hidden.value = true;
    try {
        localStorage.setItem(KEY, '1');
    } catch {
        /* sin almacenamiento: solo se oculta en esta visita */
    }
}
</script>

<template>
    <aside v-if="!hidden && (pwa.canInstall.value || ios)" class="install">
        <div>
            <b>Instala la app en tu celular</b>
            <small v-if="ios">En Safari toca <b>Compartir</b> y luego <b>“Agregar a inicio”</b>.</small>
            <small v-else>Ábrela como una app, sin buscar el enlace cada día.</small>
        </div>
        <button v-if="!ios" class="btn small" type="button" @click="install">Instalar</button>
        <button class="close" type="button" aria-label="Cerrar" @click="close">×</button>
    </aside>
</template>

<style scoped>
.install { display: flex; align-items: center; gap: 10px; background: var(--navy); color: #fff; border-radius: 14px; padding: 12px 12px 12px 14px; }
.install div { flex: 1; display: grid; gap: 2px; }
.install small { color: #c3d1e4; }
.close { background: none; border: 0; color: #9fb2cc; font-size: 1.4rem; cursor: pointer; padding: 0 4px; }
</style>
