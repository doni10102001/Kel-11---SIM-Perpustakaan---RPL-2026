<script setup>
/** Tab segmen (mis. filter status) dengan jumlah opsional. */
const model = defineModel({ type: String, required: true });

defineProps({
    /** [{ value, label, count? }] */
    options: { type: Array, required: true },
    label: { type: String, required: true },
});
</script>

<template>
    <div class="sim-tabs" role="tablist" :aria-label="label">
        <button
            v-for="o in options"
            :key="o.value"
            type="button"
            role="tab"
            :aria-selected="model === o.value ? 'true' : 'false'"
            :class="['sim-tabs__item', { 'is-active': model === o.value }]"
            @click="model = o.value"
        >
            {{ o.label }}
            <span v-if="o.count !== undefined" class="sim-tabs__count tabular-nums">{{ o.count }}</span>
        </button>
    </div>
</template>
