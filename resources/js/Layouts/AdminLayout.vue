<script setup>
import AppShell from '@/Layouts/AppShell.vue';

/**
 * Layout halaman pengelolaan perpustakaan untuk Admin. Menu berasal dari App\Support\NavigasiDemo
 * dan setiap item menyimpan atribut `peran` untuk otorisasi di tahap lanjut.
 */
</script>

<template>
    <AppShell caption="Panel Admin">
        <slot />
    </AppShell>
</template>
