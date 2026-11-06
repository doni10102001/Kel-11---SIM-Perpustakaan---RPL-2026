<script setup>
import { computed } from 'vue';

const props = defineProps({
    /** success | danger | warning | info */
    tone: { type: String, default: 'info' },
    title: { type: String, default: null },
    message: { type: String, default: null },
    dismissible: { type: Boolean, default: false },
});

defineEmits(['dismiss']);

const IKON = {
    success: 'bi-check-circle-fill',
    danger: 'bi-exclamation-circle-fill',
    warning: 'bi-exclamation-triangle-fill',
    info: 'bi-info-circle-fill',
};

const ikon = computed(() => IKON[props.tone] ?? IKON.info);
// Kesalahan dan peringatan diumumkan segera; info/sukses secara sopan.
const role = computed(() => (props.tone === 'danger' || props.tone === 'warning' ? 'alert' : 'status'));
</script>

<template>
    <div :class="['sim-feedback', `sim-feedback--${tone}`]" :role="role">
        <i :class="['bi', ikon, 'sim-feedback__icon']" aria-hidden="true"></i>
        <div class="sim-feedback__body">
            <p v-if="title" class="sim-feedback__title">{{ title }}</p>
            <p v-if="message || $slots.default" class="sim-feedback__message">
                <slot>{{ message }}</slot>
            </p>
        </div>
        <button
            v-if="dismissible"
            type="button"
            class="btn btn-ghost btn-icon btn-sm sim-feedback__close"
            aria-label="Tutup pesan"
            @click="$emit('dismiss')"
        >
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>
</template>
