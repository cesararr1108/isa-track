<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

/** Firma con el dedo. Se guarda como PNG con fondo blanco (así se ve igual en el panel). */
const emit = defineEmits<{ change: [empty: boolean] }>();

const canvas = ref<HTMLCanvasElement | null>(null);
const empty = ref(true);
let ctx: CanvasRenderingContext2D | null = null;
let drawing = false;
let last: { x: number; y: number } | null = null;

function setup() {
    const c = canvas.value;
    if (!c) return;
    const ratio = Math.max(1, window.devicePixelRatio || 1);
    const { width, height } = c.getBoundingClientRect();
    c.width = Math.round(width * ratio);
    c.height = Math.round(height * ratio);
    ctx = c.getContext('2d');
    if (!ctx) return;
    ctx.scale(ratio, ratio);
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, width, height);
    ctx.lineWidth = 2.4;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#0B1F3A';
    empty.value = true;
    emit('change', true);
}

function point(e: PointerEvent) {
    const r = canvas.value!.getBoundingClientRect();
    return { x: e.clientX - r.left, y: e.clientY - r.top };
}

function down(e: PointerEvent) {
    drawing = true;
    last = point(e);
    canvas.value?.setPointerCapture(e.pointerId);
}

function move(e: PointerEvent) {
    if (!drawing || !ctx || !last) return;
    const p = point(e);
    ctx.beginPath();
    ctx.moveTo(last.x, last.y);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    last = p;
    if (empty.value) {
        empty.value = false;
        emit('change', false);
    }
}

function up() {
    drawing = false;
    last = null;
}

/** Archivo PNG de la firma, o null si no firmaron. */
function toFile(): Promise<File | null> {
    if (empty.value || !canvas.value) return Promise.resolve(null);
    return new Promise((resolve) => canvas.value!.toBlob((b) => resolve(b ? new File([b], 'firma.png', { type: 'image/png' }) : null), 'image/png'));
}

defineExpose({ toFile, clear: setup });

let observer: ResizeObserver | undefined;
onMounted(() => {
    setup();
    // Si gira el celular, el lienzo cambia de tamaño y se limpia (firmar de nuevo).
    let w = canvas.value?.clientWidth ?? 0;
    observer = new ResizeObserver(() => {
        const nw = canvas.value?.clientWidth ?? 0;
        if (Math.abs(nw - w) > 4) {
            w = nw;
            setup();
        }
    });
    if (canvas.value) observer.observe(canvas.value);
});
onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <div class="sig">
        <canvas ref="canvas" aria-label="Espacio para firmar con el dedo" @pointerdown.prevent="down" @pointermove.prevent="move" @pointerup="up" @pointercancel="up" @pointerleave="up" />
        <span v-if="empty" class="hint">Firma aquí con el dedo</span>
        <button class="link clear" type="button" @click="setup">Borrar</button>
    </div>
</template>

<style scoped>
.sig { position: relative; border: 2px dashed var(--line); border-radius: 12px; overflow: hidden; background: #fff; }
canvas { display: block; width: 100%; height: 180px; touch-action: none; }
.hint { position: absolute; inset: 0; display: grid; place-items: center; color: #94a3b8; pointer-events: none; font-weight: 600; }
.clear { position: absolute; right: 10px; top: 6px; }
</style>
