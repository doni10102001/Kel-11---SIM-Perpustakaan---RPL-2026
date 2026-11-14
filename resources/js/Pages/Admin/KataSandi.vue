<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import BaseButton from '@/Components/BaseButton.vue';
import BaseInput from '@/Components/BaseInput.vue';
import FormFeedback from '@/Components/FormFeedback.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { useDemo } from '@/composables/useDemo.js';
import AdminLayout from '@/Layouts/AdminLayout.vue';

/**
 * Phase 8C — Admin mengganti kata sandinya sendiri (PUT /admin/kata-sandi).
 * Server memverifikasi kata sandi saat ini, menyimpan hash baru, mengakhiri
 * sesi lain, dan mempertahankan sesi ini (ID sesi baru). Semua isian kata sandi
 * dikosongkan setelah setiap percobaan; tidak ada nilai yang disimpan di browser.
 */
const { rute } = useDemo();
const page = usePage();

const KOSONG = Object.freeze({ current_password: '', password: '', password_confirmation: '' });
const form = useForm({ ...KOSONG });
const tampilkanSandi = ref(false);
const pesanSukses = computed(() => page.props.flash?.status ?? null);
const adaGalat = computed(() => Object.keys(form.errors).length > 0);

function simpan() {
    form.put(rute('kata-sandi.update'), {
        preserveScroll: true,
        // PENTING: Inertia v3 menjadikan isian saat ini sebagai default baru setelah
        // sukses (termasuk kata sandi!). Tetapkan default kosong agar reset() tidak
        // mengisi ulang kata sandi lama/baru ke formulir.
        onSuccess: () => form.defaults({ ...KOSONG }),
        onFinish: () => {
            form.reset();
            tampilkanSandi.value = false;
        },
    });
}
</script>

<template>
    <AdminLayout>
        <Head title="Ubah Kata Sandi" />

        <PageHeader />

        <div class="sim-form-layout">
            <form class="sim-card" novalidate @submit.prevent="simpan">
                <div class="sim-card-header">
                    <div>
                        <h2 class="sim-card-title">Kata Sandi Akun</h2>
                        <p class="sim-card-subtitle">Field bertanda <span class="sim-required">*</span> wajib diisi.</p>
                    </div>
                </div>

                <div class="sim-card-body">
                    <FormFeedback v-if="pesanSukses && !adaGalat" class="mb-4" tone="success" title="Kata sandi diperbarui" :message="pesanSukses" />
                    <FormFeedback v-if="adaGalat" class="mb-4" tone="danger" title="Kata sandi belum diubah" message="Periksa kembali isian yang ditandai." />

                    <div class="sim-form-grid">
                        <div class="is-full">
                            <BaseInput
                                v-model="form.current_password"
                                label="Kata Sandi Saat Ini"
                                :type="tampilkanSandi ? 'text' : 'password'"
                                placeholder="Masukkan kata sandi saat ini"
                                required
                                autocomplete="current-password"
                                :error="form.errors.current_password"
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
                        </div>
                        <BaseInput
                            v-model="form.password"
                            label="Kata Sandi Baru"
                            :type="tampilkanSandi ? 'text' : 'password'"
                            placeholder="6–64 karakter"
                            required
                            autocomplete="new-password"
                            :error="form.errors.password"
                        />
                        <BaseInput
                            v-model="form.password_confirmation"
                            label="Konfirmasi Kata Sandi Baru"
                            :type="tampilkanSandi ? 'text' : 'password'"
                            placeholder="Ulangi kata sandi baru"
                            required
                            autocomplete="new-password"
                            :error="form.errors.password_confirmation"
                        />
                    </div>
                </div>

                <div class="sim-card-footer">
                    <div class="sim-form-actions w-100">
                        <BaseButton variant="secondary" :href="rute('dashboard')">Batal</BaseButton>
                        <BaseButton type="submit" icon="bi-check2" :loading="form.processing">Simpan Kata Sandi</BaseButton>
                    </div>
                </div>
            </form>

            <aside class="sim-card">
                <div class="sim-card-header">
                    <h2 class="sim-card-title">Keamanan Akun</h2>
                </div>
                <div class="sim-card-body">
                    <ul class="sim-field-hint ps-3 mb-0">
                        <li>Kata sandi lama dan baru tidak pernah ditampilkan atau disimpan di browser.</li>
                        <li>Setelah diubah, sesi Anda di perangkat ini tetap aktif; sesi di perangkat lain otomatis berakhir.</li>
                        <li>Panjang kata sandi 6–64 karakter dan harus berbeda dari kata sandi saat ini.</li>
                    </ul>
                </div>
            </aside>
        </div>
    </AdminLayout>
</template>
