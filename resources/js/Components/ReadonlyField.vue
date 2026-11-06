<script setup>
import { useId } from 'vue';

/** Field tampilan (tidak dapat diubah), mis. Tanggal Pinjam, Admin Pemroses, status. */
defineProps({
    label: { type: String, required: true },
    hint: { type: String, default: null },
});

const id = `ro-${useId()}`;
</script>

<template>
    <div class="sim-field">
        <span :id="id" class="form-label d-block">{{ label }}</span>
        <div class="sim-readonly" role="group" :aria-labelledby="id">
            <slot />
        </div>
        <p v-if="hint" class="sim-field-hint mb-0">{{ hint }}</p>
    </div>
</template>
