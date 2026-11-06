<script setup>
defineProps({
    icon: { type: String, default: 'bi-inbox' },
    title: { type: String, required: true },
    description: { type: String, default: null },
    compact: { type: Boolean, default: false },
});
</script>

<template>
    <div :class="['sim-empty', { 'is-compact': compact }]">
        <div class="sim-empty__icon" aria-hidden="true">
            <i :class="['bi', icon]"></i>
        </div>
        <p class="sim-empty__title">{{ title }}</p>
        <p v-if="description" class="sim-empty__description">{{ description }}</p>
        <div v-if="$slots.actions" class="sim-empty__actions">
            <slot name="actions" />
        </div>
    </div>
</template>
