<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

import FormUbahKataSandi from '@/Components/FormUbahKataSandi.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { useDemo } from '@/composables/useDemo.js';
import AnggotaLayout from '@/Layouts/AnggotaLayout.vue';

/**
 * Phase 8D — Anggota mengganti kata sandinya sendiri (PUT /anggota/kata-sandi).
 * Wajib setelah Admin membuat akses login atau mereset kata sandi: selama itu,
 * server (middleware wajib.ganti.sandi) hanya mengizinkan halaman ini dan Keluar.
 * Berhasil → server mengarahkan ke Dashboard Anggota.
 */
const { rute, akun } = useDemo();
const wajib = computed(() => Boolean(akun.value?.wajibGantiKataSandi));
</script>

<template>
    <AnggotaLayout>
        <Head title="Ubah Kata Sandi" />

        <PageHeader />

        <FormUbahKataSandi :wajib="wajib" :batal-href="wajib ? null : rute('dashboard')" />
    </AnggotaLayout>
</template>
