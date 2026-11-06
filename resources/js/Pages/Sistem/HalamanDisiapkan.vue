<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { useDemo } from '@/composables/useDemo.js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AnggotaLayout from '@/Layouts/AnggotaLayout.vue';

/**
 * Kerangka sementara untuk rute yang isinya dibangun pada phase berikutnya
 * (Phase 3–5). Judul, deskripsi, dan breadcrumb berasal dari NavigasiDemo.
 */
const { adalahAdmin, halaman } = useDemo();

const layout = computed(() => (adalahAdmin.value ? AdminLayout : AnggotaLayout));
</script>

<template>
    <component :is="layout">
        <Head :title="halaman?.judul" />

        <PageHeader />

        <section class="sim-card">
            <EmptyState
                icon="bi-layout-text-window-reverse"
                title="Konten halaman sedang disiapkan"
                description="Tata letak dan navigasi halaman ini sudah tersedia. Isi halaman akan dilengkapi pada tahap pengembangan berikutnya."
            />
        </section>
    </component>
</template>
