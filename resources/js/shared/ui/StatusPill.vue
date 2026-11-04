<script setup lang="ts">
import type { StatusMap } from '@/types';

defineProps<{ status: string; statuses: StatusMap }>();
</script>

<template>
    <span class="pill" :style="{ '--c': statuses[status]?.color ?? '#64748b' }">{{ statuses[status]?.label ?? status }}</span>
</template>
