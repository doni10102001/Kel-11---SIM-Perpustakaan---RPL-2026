<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    /** primary | secondary | outline-primary | danger | success | ghost */
    variant: { type: String, default: 'primary' },
    /** sm | md | lg */
    size: { type: String, default: 'md' },
    /** Ikon Bootstrap Icons di kiri, mis. "bi-plus-lg". */
    icon: { type: String, default: null },
    /** Ikon di kanan teks. */
    iconEnd: { type: String, default: null },
    /** Bila diisi, tombol dirender sebagai tautan Inertia. */
    href: { type: String, default: null },
    type: { type: String, default: 'button' },
    loading: { type: Boolean, default: false },
    loadingText: { type: String, default: 'Memproses…' },
    disabled: { type: Boolean, default: false },
    block: { type: Boolean, default: false },
    /** Tombol ikon saja; wajib isi `label` untuk pembaca layar. */
    iconOnly: { type: Boolean, default: false },
    label: { type: String, default: null },
});

const kelas = computed(() => [
    'btn',
    `btn-${props.variant}`,
    { 'btn-sm': props.size === 'sm', 'btn-lg': props.size === 'lg' },
    { 'w-100': props.block, 'btn-icon': props.iconOnly },
]);

const nonaktif = computed(() => props.disabled || props.loading);
</script>

<template>
    <Link
        v-if="href && !nonaktif"
        :href="href"
        :class="kelas"
        :aria-label="iconOnly ? label : undefined"
        :title="iconOnly ? label : undefined"
    >
        <i v-if="icon" :class="['bi', icon]" aria-hidden="true"></i>
        <span v-if="!iconOnly"><slot>{{ label }}</slot></span>
        <i v-if="iconEnd" :class="['bi', iconEnd]" aria-hidden="true"></i>
    </Link>
    <button
        v-else
        :type="type"
        :class="kelas"
        :disabled="nonaktif"
        :aria-busy="loading || undefined"
        :aria-label="iconOnly ? label : undefined"
        :title="iconOnly ? label : undefined"
    >
        <span v-if="loading" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
        <i v-else-if="icon" :class="['bi', icon]" aria-hidden="true"></i>
        <span v-if="!iconOnly">
            <template v-if="loading">{{ loadingText }}</template>
            <slot v-else>{{ label }}</slot>
        </span>
        <i v-if="iconEnd && !loading" :class="['bi', iconEnd]" aria-hidden="true"></i>
    </button>
</template>
