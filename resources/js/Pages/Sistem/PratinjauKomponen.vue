<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import BaseButton from '@/Components/BaseButton.vue';
import BaseInput from '@/Components/BaseInput.vue';
import BaseSelect from '@/Components/BaseSelect.vue';
import BaseTable from '@/Components/BaseTable.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormFeedback from '@/Components/FormFeedback.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { STATUS } from '@/utils/status.js';

/**
 * Halaman pengembang: katalog komponen UI bersama.
 * Tidak ditautkan dari navigasi aplikasi dan tidak memuat data domain.
 */
const contohTeks = ref('');
const contohWajib = ref('');
const contohPilihan = ref('');
const contohCatatan = ref('');
const tampilkanUmpanBalik = ref(true);
const modalUtama = ref(false);
const modalBahaya = ref(false);
const hasilModal = ref(null);

const LABEL_JENIS = { anggota: 'Anggota', eksemplar: 'Eksemplar', peminjaman: 'Peminjaman', booking: 'Booking' };

// Tabel contoh berisi kosakata status, bukan rekaman data.
const barisStatus = computed(() =>
    Object.entries(STATUS).flatMap(([jenis, daftar]) =>
        Object.entries(daftar).map(([status, tampilan]) => ({
            id: `${jenis}-${status}`,
            jenis,
            labelJenis: LABEL_JENIS[jenis],
            status,
            nada: tampilan.nada,
        })),
    ),
);

const kolomStatus = [
    { key: 'labelJenis', label: 'Objek', width: '28%' },
    { key: 'status', label: 'Status', width: '28%' },
    { key: 'badge', label: 'Tampilan Badge' },
    { key: 'nada', label: 'Nada Warna', align: 'end' },
];
</script>

<template>
    <Head title="Pratinjau Komponen" />

    <div class="container-xl py-4 py-lg-5">
        <PageHeader
            title="Pratinjau Komponen"
            description="Katalog komponen antarmuka bersama untuk pengembang. Halaman ini tidak ditautkan dari navigasi aplikasi."
            :breadcrumbs="[]"
        />

        <div class="row g-4">
            <div class="col-12 col-xl-6">
                <section class="sim-card h-100">
                    <div class="sim-card-header">
                        <div>
                            <h2 class="sim-card-title">Tombol</h2>
                            <p class="sim-card-subtitle">BaseButton — varian, ukuran, dan keadaan.</p>
                        </div>
                    </div>
                    <div class="sim-card-body d-grid gap-3">
                        <div class="d-flex flex-wrap gap-2">
                            <BaseButton icon="bi-plus-lg">Utama</BaseButton>
                            <BaseButton variant="secondary">Sekunder</BaseButton>
                            <BaseButton variant="outline-primary" icon="bi-printer">Garis</BaseButton>
                            <BaseButton variant="ghost">Ringan</BaseButton>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <BaseButton variant="success" icon="bi-check-lg">Sukses</BaseButton>
                            <BaseButton variant="danger" icon="bi-x-lg">Bahaya</BaseButton>
                            <BaseButton loading>Simpan</BaseButton>
                            <BaseButton disabled>Nonaktif</BaseButton>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <BaseButton size="sm">Kecil</BaseButton>
                            <BaseButton>Sedang</BaseButton>
                            <BaseButton size="lg">Besar</BaseButton>
                            <BaseButton variant="secondary" icon="bi-pencil" icon-only label="Ubah" />
                            <BaseButton variant="ghost" size="sm" icon="bi-three-dots" icon-only label="Aksi lain" />
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-12 col-xl-6">
                <section class="sim-card h-100">
                    <div class="sim-card-header">
                        <div>
                            <h2 class="sim-card-title">Isian Formulir</h2>
                            <p class="sim-card-subtitle">BaseInput dan BaseSelect — label, wajib, petunjuk, galat.</p>
                        </div>
                    </div>
                    <div class="sim-card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <BaseInput v-model="contohTeks" label="Isian biasa" placeholder="Ketik di sini" hint="Teks petunjuk singkat." />
                            </div>
                            <div class="col-md-6">
                                <BaseInput
                                    v-model="contohWajib"
                                    label="Isian wajib"
                                    placeholder="Wajib diisi"
                                    required
                                    error="Isian ini wajib diisi."
                                />
                            </div>
                            <div class="col-md-6">
                                <BaseInput label="Dengan ikon" placeholder="Cari…" icon="bi-search" model-value="" />
                            </div>
                            <div class="col-md-6">
                                <BaseInput label="Tidak dapat diubah" model-value="Nilai terkunci" disabled />
                            </div>
                            <div class="col-md-6">
                                <BaseSelect
                                    v-model="contohPilihan"
                                    label="Pilihan"
                                    placeholder="Pilih salah satu"
                                    :options="['Opsi pertama', 'Opsi kedua', 'Opsi ketiga']"
                                    required
                                />
                            </div>
                            <div class="col-md-6">
                                <BaseSelect
                                    label="Pilihan bergalat"
                                    placeholder="Pilih salah satu"
                                    :options="['Opsi pertama']"
                                    model-value=""
                                    error="Pilih salah satu opsi."
                                />
                            </div>
                            <div class="col-12">
                                <BaseInput v-model="contohCatatan" label="Isian panjang" placeholder="Tulis keterangan" multiline :rows="3" />
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-12 col-xl-6">
                <section class="sim-card h-100">
                    <div class="sim-card-header">
                        <div>
                            <h2 class="sim-card-title">Umpan Balik</h2>
                            <p class="sim-card-subtitle">FormFeedback — sukses, galat, peringatan, informasi.</p>
                        </div>
                    </div>
                    <div class="sim-card-body d-grid gap-3">
                        <FormFeedback
                            v-if="tampilkanUmpanBalik"
                            tone="success"
                            title="Berhasil"
                            message="Perubahan berhasil diterapkan."
                            dismissible
                            @dismiss="tampilkanUmpanBalik = false"
                        />
                        <FormFeedback tone="danger" title="Gagal" message="Periksa kembali isian yang ditandai." />
                        <FormFeedback tone="warning" title="Perhatian" message="Tindakan ini tidak dapat dibatalkan." />
                        <FormFeedback tone="info" message="Informasi tambahan untuk pengguna." />
                    </div>
                </section>
            </div>

            <div class="col-12 col-xl-6">
                <section class="sim-card h-100">
                    <div class="sim-card-header">
                        <div>
                            <h2 class="sim-card-title">Dialog &amp; Keadaan Kosong</h2>
                            <p class="sim-card-subtitle">ConfirmationModal dan EmptyState.</p>
                        </div>
                    </div>
                    <div class="sim-card-body">
                        <div class="d-flex flex-wrap gap-2">
                            <BaseButton variant="secondary" icon="bi-question-circle" @click="modalUtama = true">
                                Dialog konfirmasi
                            </BaseButton>
                            <BaseButton variant="secondary" icon="bi-exclamation-triangle" @click="modalBahaya = true">
                                Dialog berisiko
                            </BaseButton>
                        </div>
                        <p v-if="hasilModal" class="sim-field-hint" role="status">Pilihan terakhir: {{ hasilModal }}</p>
                        <EmptyState
                            compact
                            icon="bi-search"
                            title="Data tidak ditemukan"
                            description="Ubah kata kunci atau filter pencarian, lalu coba lagi."
                        />
                    </div>
                </section>
            </div>

            <div class="col-12">
                <section class="sim-card">
                    <div class="sim-card-header">
                        <div>
                            <h2 class="sim-card-title">Tabel &amp; Badge Status</h2>
                            <p class="sim-card-subtitle">BaseTable dengan StatusBadge untuk seluruh kosakata status.</p>
                        </div>
                    </div>
                    <BaseTable :columns="kolomStatus" :rows="barisStatus" caption="Kosakata status">
                        <template #cell-status="{ row }">
                            <span class="fw-semibold">{{ row.status }}</span>
                        </template>
                        <template #cell-badge="{ row }">
                            <StatusBadge :type="row.jenis" :status="row.status" />
                        </template>
                        <template #cell-nada="{ value }">
                            <code class="text-secondary">{{ value }}</code>
                        </template>
                    </BaseTable>
                </section>
            </div>

            <div class="col-12 col-lg-6">
                <section class="sim-card">
                    <div class="sim-card-header">
                        <h2 class="sim-card-title">Tabel kosong</h2>
                    </div>
                    <BaseTable
                        :columns="[
                            { key: 'a', label: 'Kolom A' },
                            { key: 'b', label: 'Kolom B' },
                        ]"
                        :rows="[]"
                        empty-title="Belum ada data"
                        empty-description="Data akan tampil di sini setelah tersedia."
                    />
                </section>
            </div>

            <div class="col-12 col-lg-6">
                <section class="sim-card">
                    <div class="sim-card-header">
                        <h2 class="sim-card-title">Tabel memuat</h2>
                    </div>
                    <BaseTable
                        :columns="[
                            { key: 'a', label: 'Kolom A' },
                            { key: 'b', label: 'Kolom B' },
                        ]"
                        loading
                    />
                </section>
            </div>
        </div>
    </div>

    <ConfirmationModal
        v-model:show="modalUtama"
        title="Simpan perubahan?"
        message="Pastikan data yang Anda isi sudah benar sebelum menyimpan."
        confirm-label="Simpan"
        @confirm="
            hasilModal = 'Simpan';
            modalUtama = false;
        "
        @cancel="hasilModal = 'Batal'"
    />

    <ConfirmationModal
        v-model:show="modalBahaya"
        title="Lanjutkan tindakan ini?"
        message="Tindakan ini mengubah status data dan perlu alasan."
        tone="danger"
        confirm-label="Lanjutkan"
        size="md"
        @confirm="
            hasilModal = 'Lanjutkan';
            modalBahaya = false;
        "
        @cancel="hasilModal = 'Batal'"
    >
        <BaseInput v-model="contohCatatan" label="Keterangan" placeholder="Tulis keterangan" multiline :rows="3" />
    </ConfirmationModal>
</template>
