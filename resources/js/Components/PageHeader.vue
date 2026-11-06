<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import { useDemo } from '@/composables/useDemo.js';

/**
 * Judul halaman. Bila props kosong, memakai meta halaman terpusat dari
 * Laravel (App\Support\NavigasiDemo) agar judul & breadcrumb konsisten.
 */
const props = defineProps({
    title: { type: String, default: null },
    description: { type: String, default: null },
    /** [{ label, url }] — url null = halaman aktif. */
    breadcrumbs: { type: Array, default: null },
});

const { halaman } = useDemo();

const judul = computed(() => props.title ?? halaman.value?.judul ?? '');
const deskripsi = computed(() => props.description ?? halaman.value?.deskripsi ?? null);
const remah = computed(() => props.breadcrumbs ?? halaman.value?.remah ?? []);
</script>

<template>
    <header class="sim-page-header">
        <div class="sim-page-header__text">
            <nav v-if="remah.length > 1" aria-label="Breadcrumb">
                <ol class="sim-breadcrumb">
                    <li v-for="(item, i) in remah" :key="i">
                        <Link v-if="item.url" :href="item.url">{{ item.label }}</Link>
                        <span v-else aria-current="page">{{ item.label }}</span>
                    </li>
                </ol>
            </nav>
            <h1 class="sim-page-header__title">{{ judul }}</h1>
            <p v-if="deskripsi" class="sim-page-header__description">{{ deskripsi }}</p>
        </div>
        <div v-if="$slots.actions" class="sim-page-header__actions">
            <slot name="actions" />
        </div>
    </header>
</template>
