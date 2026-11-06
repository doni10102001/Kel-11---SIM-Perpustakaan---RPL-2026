<script setup>
defineProps({
    large: { type: Boolean, default: false },
});
</script>

<template>
    <span :class="['sim-brand-mark', { 'is-lg': large }]" aria-hidden="true">
        <i class="bi bi-book-half"></i>
    </span>
</template>
