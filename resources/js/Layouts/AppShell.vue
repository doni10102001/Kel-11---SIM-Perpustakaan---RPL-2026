<script setup>
import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';

import AppHeader from '@/Components/AppHeader.vue';
import AppSidebar from '@/Components/AppSidebar.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import { useDemo } from '@/composables/useDemo.js';

/** Kerangka aplikasi bersama: sidebar, header, area konten, dan dialog Keluar. */
defineProps({
    caption: { type: String, default: null },
});

const { akun, menu, rute } = useDemo();

const sidebarTerbuka = ref(false);
const konfirmasiKeluar = ref(false);

function tutupSidebar() {
    sidebarTerbuka.value = false;
}

function tanganiEscape(event) {
    if (event.key === 'Escape' && sidebarTerbuka.value && !konfirmasiKeluar.value) {
        tutupSidebar();
    }
}

watch(sidebarTerbuka, (terbuka) => {
    // Kunci gulir halaman saat laci navigasi terbuka di layar kecil.
    document.body.style.overflow = terbuka ? 'hidden' : '';

    if (terbuka) {
        document.addEventListener('keydown', tanganiEscape);
    } else {
        document.removeEventListener('keydown', tanganiEscape);
    }
});

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    document.removeEventListener('keydown', tanganiEscape);
});

function mintaKeluar() {
    tutupSidebar();
    konfirmasiKeluar.value = true;
}

function keluar() {
    // Pratinjau statis: tidak ada sesi server yang diakhiri, hanya kembali ke halaman Masuk.
    konfirmasiKeluar.value = false;
    router.visit(rute('login'));
}
</script>

<template>
    <div class="sim-shell">
        <a href="#konten-utama" class="sim-skip-link">Lewati ke konten utama</a>

        <AppSidebar :menu="menu" :caption="caption" :open="sidebarTerbuka" @close="tutupSidebar" @logout="mintaKeluar" />
        <div v-if="sidebarTerbuka" class="sim-sidebar-backdrop d-lg-none" aria-hidden="true" @click="tutupSidebar"></div>

        <div class="sim-main">
            <AppHeader :akun="akun" :sidebar-open="sidebarTerbuka" @toggle-sidebar="sidebarTerbuka = !sidebarTerbuka" />

            <main id="konten-utama" class="sim-content" tabindex="-1">
                <slot />
            </main>

            <footer class="sim-footer">© 2026 Sistem Informasi Manajemen Perpustakaan</footer>
        </div>

        <ConfirmationModal
            v-model:show="konfirmasiKeluar"
            title="Keluar dari aplikasi?"
            message="Anda akan mengakhiri sesi ini dan kembali ke halaman Masuk."
            tone="danger"
            icon="bi-box-arrow-left"
            confirm-label="Keluar"
            @confirm="keluar"
        />
    </div>
</template>
