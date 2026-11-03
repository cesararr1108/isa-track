<script setup lang="ts">
defineProps<{ message?: string }>();
</script>

<template>
    <span v-if="message" class="err" role="alert">{{ message }}</span>
</template>
