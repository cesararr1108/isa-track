<script setup lang="ts">
import { computed } from 'vue';

interface Day { date: string; created: number; delivered: number }
const props = defineProps<{ days: Day[] }>();

// Barras de creados y entregados por día, en una sola escala.
const W = 680, H = 210, L0 = 34, B = 26, T = 10;
const chart = computed(() => {
    const max = Math.max(4, ...props.days.map((d) => Math.max(d.created, d.delivered)));
    const step = max <= 8 ? 2 : max <= 20 ? 5 : max <= 50 ? 10 : 25;
    const top = Math.ceil(max / step) * step;
    const y = (v: number) => H - B - (v / top) * (H - B - T);
    const bw = (W - L0 - 8) / Math.max(1, props.days.length);
    const every = Math.ceil(props.days.length / 10);
    const ticks: number[] = [];
    for (let v = 0; v <= top; v += step) ticks.push(v);
    const bars = props.days.map((d, i) => {
        const x = L0 + i * bw + bw * 0.12, w = Math.max(2, bw * 0.36);
        return { d, x, w, yc: y(d.created), yd: y(d.delivered), label: i % every === 0 ? `${d.date.slice(8)}/${d.date.slice(5, 7)}` : null };
    });
    return { ticks, y, bars, base: H - B };
});
</script>

<template>
    <svg :viewBox="`0 0 ${W} ${H}`" role="img" aria-label="Pedidos creados y entregados por día" class="chart">
        <g v-for="v in chart.ticks" :key="v">
            <line class="grid" :x1="L0" :x2="W - 4" :y1="chart.y(v)" :y2="chart.y(v)" />
            <text class="ax" :x="L0 - 6" :y="chart.y(v) + 3" text-anchor="end">{{ v }}</text>
        </g>
        <g v-for="b in chart.bars" :key="b.d.date">
            <rect class="b1" :x="b.x" :y="b.yc" :width="b.w" :height="chart.base - b.yc" rx="2"><title>{{ b.d.date }}: {{ b.d.created }} creados</title></rect>
            <rect class="b2" :x="b.x + b.w + 1" :y="b.yd" :width="b.w" :height="chart.base - b.yd" rx="2"><title>{{ b.d.date }}: {{ b.d.delivered }} entregados</title></rect>
            <text v-if="b.label" class="ax" :x="b.x + b.w" :y="H - 8" text-anchor="middle">{{ b.label }}</text>
        </g>
    </svg>
</template>

<style scoped>
.chart { display: block; width: 100%; height: auto; }
.grid { stroke: var(--line); }
.ax { fill: var(--muted); font: 500 10px var(--body); }
.b1 { fill: color-mix(in srgb, var(--navy) 35%, var(--sunken)); }
.b2 { fill: var(--mint); }
</style>
