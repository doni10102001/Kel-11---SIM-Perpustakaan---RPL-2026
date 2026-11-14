<script setup>
import { Head } from '@inertiajs/vue3';

import FormUbahKataSandi from '@/Components/FormUbahKataSandi.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { useDemo } from '@/composables/useDemo.js';
import AdminLayout from '@/Layouts/AdminLayout.vue';

/** Phase 8C — Admin mengganti kata sandinya sendiri (PUT /admin/kata-sandi). */
const { rute } = useDemo();
</script>

<template>
    <AdminLayout>
        <Head title="Ubah Kata Sandi" />

        <PageHeader />

        <FormUbahKataSandi :batal-href="rute('dashboard')" />
    </AdminLayout>
</template>
