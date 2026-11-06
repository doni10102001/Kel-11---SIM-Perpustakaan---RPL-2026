<script setup>
import { Link } from '@inertiajs/vue3';

/** Kartu ringkasan (KPI) dashboard. Nilai selalu berasal dari data. */
defineProps({
    label: { type: String, required: true },
    value: { type: [Number, String], required: true },
    icon: { type: String, required: true },
    /** primary | success | warning | info | indigo */
    tone: { type: String, default: 'primary' },
    caption: { type: String, default: null },
    /** Tautan ke halaman terkait (opsional). */
    href: { type: String, default: null },
    linkLabel: { type: String, default: null },
});
</script>

<template>
    <article :class="['sim-stat', `sim-stat--${tone}`]">
        <div class="sim-stat__top">
            <span class="sim-stat__label">{{ label }}</span>
            <span class="sim-stat__icon" aria-hidden="true"><i :class="['bi', icon]"></i></span>
        </div>
        <p class="sim-stat__value tabular-nums">{{ value }}</p>
        <p v-if="caption" class="sim-stat__caption">{{ caption }}</p>
        <Link v-if="href" :href="href" class="sim-stat__link">
            {{ linkLabel ?? 'Lihat detail' }}
            <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </Link>
    </article>
</template>
