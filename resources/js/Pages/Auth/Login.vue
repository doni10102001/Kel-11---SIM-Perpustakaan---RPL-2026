<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

import BaseButton from '@/Components/BaseButton.vue';
import BaseInput from '@/Components/BaseInput.vue';
import BrandMark from '@/Components/BrandMark.vue';
import FormFeedback from '@/Components/FormFeedback.vue';
import { useDemo } from '@/composables/useDemo.js';

/**
 * Halaman Masuk bersama (Admin dan Anggota) — Phase 8A: autentikasi SERVER.
 * Username dan kata sandi dikirim (POST, token CSRF otomatis lewat cookie
 * XSRF-TOKEN) ke Laravel, yang memverifikasi hash di MySQL, membuat sesi
 * server, lalu mengalihkan ke dashboard sesuai peran. Tidak ada verifikasi
 * kredensial di browser. Kata sandi dikosongkan setelah setiap percobaan dan
 * tidak pernah disimpan, dicatat, atau dimasukkan ke URL.
 */
const { rute } = useDemo();
const page = usePage();

const form = useForm({ username: '', password: '' });
const galat = reactive({ username: null, password: null });
const tampilkanSandi = ref(false);

// Pesan dari server: gagal masuk (generik) / status akun / terlalu banyak percobaan.
const pesanGagal = computed(() => form.errors.username ?? form.errors.password ?? null);
// Pesan satu kali, mis. setelah keluar atau sesi diakhiri karena status akun berubah.
const pesanStatus = computed(() => page.props.flash?.status ?? null);

watch(
    () => form.username,
    () => {
        galat.username = null;
        form.clearErrors();
    },
);

watch(
    () => form.password,
    (baru) => {
        galat.password = null;
        // form.reset('password') setelah percobaan gagal mengosongkan isian; jangan
        // ikut menghapus pesan gagal dari server. Hapus hanya saat pengguna mengetik.
        if (baru) form.clearErrors();
    },
);

function masuk() {
    galat.username = form.username.trim() ? null : 'Identitas Pengguna wajib diisi.';
    galat.password = form.password ? null : 'Kata Sandi wajib diisi.';

    if (galat.username || galat.password) {
        return;
    }

    form.post(rute('login'), {
        // Inertia v3 menjadikan isian sukses sebagai default; jangan simpan kata sandi sebagai default.
        onSuccess: () => form.defaults({ username: '', password: '' }),
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Masuk" />

    <div class="sim-auth">
        <aside class="sim-auth__aside">
            <div class="sim-auth__brand">
                <BrandMark large />
                <div>
                    <span class="sim-auth__brand-name">SIM Perpustakaan</span>
                    <span class="sim-auth__brand-caption">Sistem Informasi Manajemen Perpustakaan</span>
                </div>
            </div>

            <div>
                <p class="sim-auth__headline">Layanan perpustakaan yang tertata dalam satu sistem.</p>
                <p class="sim-auth__lead">
                    Kelola koleksi buku dan eksemplar, peminjaman, pengembalian, booking, serta laporan perpustakaan
                    secara terpadu.
                </p>
                <ul class="sim-auth__features">
                    <li>
                        <span class="sim-auth__feature-icon" aria-hidden="true"><i class="bi bi-journal-bookmark"></i></span>
                        Katalog buku dengan status setiap eksemplar
                    </li>
                    <li>
                        <span class="sim-auth__feature-icon" aria-hidden="true"><i class="bi bi-arrow-left-right"></i></span>
                        Peminjaman, pengembalian, dan booking buku
                    </li>
                    <li>
                        <span class="sim-auth__feature-icon" aria-hidden="true"><i class="bi bi-file-earmark-text"></i></span>
                        Laporan perpustakaan yang siap dicetak
                    </li>
                </ul>
            </div>

            <p class="sim-auth__copyright">© 2026 Sistem Informasi Manajemen Perpustakaan</p>
        </aside>

        <main class="sim-auth__main">
            <div class="sim-auth__panel">
                <div class="sim-auth__mobile-brand">
                    <BrandMark />
                    <div>
                        <strong>SIM Perpustakaan</strong>
                        <span>Sistem Informasi Manajemen Perpustakaan</span>
                    </div>
                </div>

                <h1 class="sim-auth__title">Masuk</h1>
                <p class="sim-auth__subtitle">Gunakan akun Anda untuk mengakses layanan perpustakaan.</p>

                <form class="sim-auth__form" novalidate @submit.prevent="masuk">
                    <FormFeedback v-if="pesanGagal" tone="danger" title="Gagal masuk" :message="pesanGagal" />
                    <FormFeedback v-else-if="pesanStatus" tone="info" :message="pesanStatus" />

                    <BaseInput
                        v-model="form.username"
                        label="Identitas Pengguna"
                        placeholder="Nama pengguna atau kode anggota"
                        icon="bi-person"
                        size="lg"
                        autocomplete="username"
                        autocapitalize="off"
                        spellcheck="false"
                        required
                        :error="galat.username"
                    />

                    <BaseInput
                        v-model="form.password"
                        label="Kata Sandi"
                        :type="tampilkanSandi ? 'text' : 'password'"
                        placeholder="Masukkan kata sandi"
                        icon="bi-lock"
                        size="lg"
                        autocomplete="current-password"
                        required
                        :error="galat.password"
                    >
                        <template #action>
                            <button
                                type="button"
                                class="btn btn-ghost btn-icon btn-sm"
                                :aria-label="tampilkanSandi ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                                :aria-pressed="tampilkanSandi ? 'true' : 'false'"
                                @click="tampilkanSandi = !tampilkanSandi"
                            >
                                <i :class="['bi', tampilkanSandi ? 'bi-eye-slash' : 'bi-eye']" aria-hidden="true"></i>
                            </button>
                        </template>
                    </BaseInput>

                    <BaseButton type="submit" size="lg" block icon="bi-box-arrow-in-right" :loading="form.processing">
                        Masuk
                    </BaseButton>
                </form>

            </div>
        </main>
    </div>
</template>
