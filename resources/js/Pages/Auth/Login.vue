<script setup>
import { Head, router } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';

import BaseButton from '@/Components/BaseButton.vue';
import BaseInput from '@/Components/BaseInput.vue';
import BrandMark from '@/Components/BrandMark.vue';
import FormFeedback from '@/Components/FormFeedback.vue';
import { GalatValidasi, masuk as verifikasiMasuk } from '@/composables/useDataPerpustakaan.js';
import { useDemo } from '@/composables/useDemo.js';
import { AKUN_DEMO } from '@/config/demo.js';

/**
 * Halaman Masuk bersama (Admin dan Anggota).
 * PRATINJAU STATIS: tidak ada autentikasi server maupun sesi yang tahan muat ulang.
 * Kredensial dicocokkan di browser (simulasi) lalu membuka dashboard sesuai peran.
 * Kata sandi tidak pernah disimpan, dicatat, atau dimasukkan ke URL.
 */
const { rute } = useDemo();

const form = reactive({ identitas: '', kataSandi: '' });
const galat = reactive({ identitas: null, kataSandi: null });
const pesanGagal = ref(null);
const tampilkanSandi = ref(false);
const memproses = ref(false);
const inputSandi = ref(null);

watch(
    () => form.identitas,
    () => {
        galat.identitas = null;
        pesanGagal.value = null;
    },
);

watch(
    () => form.kataSandi,
    () => {
        galat.kataSandi = null;
        pesanGagal.value = null;
    },
);

const daftarAkun = Object.values(AKUN_DEMO);

function pakaiAkun(akun) {
    form.identitas = akun.identitas;
    inputSandi.value?.focus();
}

function masuk() {
    galat.identitas = form.identitas.trim() ? null : 'Identitas Pengguna wajib diisi.';
    galat.kataSandi = form.kataSandi ? null : 'Kata Sandi wajib diisi.';

    if (galat.identitas || galat.kataSandi) {
        return;
    }

    let akun;
    try {
        akun = verifikasiMasuk(form.identitas, form.kataSandi);
    } catch (e) {
        if (!(e instanceof GalatValidasi)) throw e;
        // Pesan kredensial generik: tidak menyebutkan bagian mana yang salah.
        pesanGagal.value = e.galat.umum;

        return;
    }

    form.kataSandi = '';
    memproses.value = true;
    router.visit(rute(`beranda.${akun.peran}`), {
        onFinish: () => {
            memproses.value = false;
        },
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

                    <BaseInput
                        v-model="form.identitas"
                        label="Identitas Pengguna"
                        placeholder="Nama pengguna atau kode anggota"
                        icon="bi-person"
                        size="lg"
                        autocomplete="username"
                        autocapitalize="off"
                        spellcheck="false"
                        required
                        :error="galat.identitas"
                    />

                    <BaseInput
                        ref="inputSandi"
                        v-model="form.kataSandi"
                        label="Kata Sandi"
                        :type="tampilkanSandi ? 'text' : 'password'"
                        placeholder="Masukkan kata sandi"
                        icon="bi-lock"
                        size="lg"
                        autocomplete="current-password"
                        required
                        :error="galat.kataSandi"
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

                    <BaseButton type="submit" size="lg" block icon="bi-box-arrow-in-right" :loading="memproses">
                        Masuk
                    </BaseButton>
                </form>

                <section class="sim-demo-note" aria-labelledby="judul-mode-pratinjau">
                    <p id="judul-mode-pratinjau" class="sim-demo-note__title">
                        <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                        Mode pratinjau
                    </p>
                    <p class="sim-demo-note__text">
                        Autentikasi server belum diaktifkan. Pilih akun pratinjau, lalu isi Kata Sandi dengan teks apa pun.
                        Akun anggota yang dibuat Admin memakai kata sandi dari Admin dan hanya berlaku sampai halaman dimuat ulang.
                    </p>
                    <div class="sim-demo-note__accounts">
                        <button
                            v-for="akun in daftarAkun"
                            :key="akun.peran"
                            type="button"
                            class="sim-chip-button"
                            @click="pakaiAkun(akun)"
                        >
                            <i class="bi bi-person-circle" aria-hidden="true"></i>
                            {{ akun.labelPeran }}
                            <code>{{ akun.identitas }}</code>
                        </button>
                    </div>
                </section>
            </div>
        </main>
    </div>
</template>
