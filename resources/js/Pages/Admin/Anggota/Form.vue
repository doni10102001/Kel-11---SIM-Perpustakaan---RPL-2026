<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, nextTick, reactive, ref } from 'vue';

import BaseButton from '@/Components/BaseButton.vue';
import BaseInput from '@/Components/BaseInput.vue';
import BaseSelect from '@/Components/BaseSelect.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormFeedback from '@/Components/FormFeedback.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useDataPerpustakaan } from '@/composables/useDataPerpustakaan.js';
import { useDemo } from '@/composables/useDemo.js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { kodeBerikutnya } from '@/utils/pencarian.js';
import { daftarStatus } from '@/utils/status.js';
import { transaksiBerjalanAnggota, validasiAnggota } from '@/utils/validasiMaster.js';

const { data, tambahAnggota, ubahAnggota, setPesan } = useDataPerpustakaan();
const { rute, parameter, halaman } = useDemo();

const kodeAsal = parameter.value.kodeAnggota ?? null;
const mode = kodeAsal ? 'ubah' : 'tambah';
const anggota = mode === 'ubah' ? data.anggota.find((a) => a.kodeAnggota === kodeAsal) : null;
const tidakDitemukan = mode === 'ubah' && !anggota;

const form = reactive({
    kodeAnggota: anggota?.kodeAnggota ?? '',
    nama: anggota?.nama ?? '',
    kontak: anggota?.kontak ?? '',
    status: anggota?.status ?? 'Aktif',
});

const galat = ref({});
const sudahDikirim = ref(false);
const konfirmasiNonaktif = ref(false);

const contohKode = kodeBerikutnya(data.anggota, 'kodeAnggota', 'AG');
const transaksi = computed(() => (anggota ? transaksiBerjalanAnggota(data, anggota.kodeAnggota) : null));
const totalRiwayat = computed(() => (anggota ? data.peminjaman.filter((p) => p.kodeAnggota === anggota.kodeAnggota).length : 0));

function validasi() {
    galat.value = validasiAnggota(form, data, { mode });

    return Object.keys(galat.value).length === 0;
}

// Setelah percobaan simpan pertama, pesan galat diperbarui saat pengguna mengetik.
function perbaruiGalat() {
    if (sudahDikirim.value) validasi();
}

async function simpan() {
    sudahDikirim.value = true;

    if (!validasi()) {
        await nextTick();
        document.querySelector('[aria-invalid="true"]')?.focus();

        return;
    }

    if (mode === 'ubah' && anggota.status === 'Aktif' && form.status === 'Nonaktif') {
        konfirmasiNonaktif.value = true;

        return;
    }

    terapkan();
}

function terapkan() {
    konfirmasiNonaktif.value = false;

    const hasil = mode === 'tambah' ? tambahAnggota(form) : ubahAnggota(kodeAsal, form);

    setPesan({
        tone: 'success',
        title: mode === 'tambah' ? 'Anggota berhasil ditambahkan' : 'Perubahan data anggota diterapkan',
        message: `${hasil.kodeAnggota} · ${hasil.nama}. Perubahan pada mode pratinjau tidak disimpan setelah halaman dimuat ulang.`,
    });
    router.visit(rute('anggota.index'));
}

const judulKartu = mode === 'tambah' ? 'Data Anggota Baru' : 'Data Anggota';

const pesanNonaktif = computed(() => {
    if (!anggota) return '';
    const berjalan = transaksi.value.peminjamanAktif + transaksi.value.bookingBerjalan;
    const dasar = `${anggota.nama} (${anggota.kodeAnggota}) tidak akan dapat membuat peminjaman maupun booking baru. Riwayat transaksinya tetap tersimpan.`;

    return berjalan > 0
        ? `${dasar} Peminjaman Aktif dan booking yang sedang berjalan tidak dibatalkan.`
        : dasar;
});
</script>

<template>
    <AdminLayout>
        <Head :title="halaman?.judul" />

        <PageHeader
            :description="mode === 'ubah' && anggota ? `Perbarui data dan status keanggotaan ${anggota.nama}.` : undefined"
        />

        <section v-if="tidakDitemukan" class="sim-card">
            <EmptyState icon="bi-person-x" title="Anggota tidak ditemukan" :description="`Kode Anggota ${kodeAsal} tidak terdaftar.`">
                <template #actions>
                    <BaseButton variant="secondary" icon="bi-arrow-left" :href="rute('anggota.index')">Kembali ke Data Anggota</BaseButton>
                </template>
            </EmptyState>
        </section>

        <div v-else class="sim-form-layout">
            <form class="sim-card" novalidate @submit.prevent="simpan" @input="perbaruiGalat" @change="perbaruiGalat">
                <div class="sim-card-header">
                    <div>
                        <h2 class="sim-card-title">{{ judulKartu }}</h2>
                        <p class="sim-card-subtitle">Field bertanda <span class="sim-required">*</span> wajib diisi.</p>
                    </div>
                </div>

                <div class="sim-card-body">
                    <FormFeedback
                        v-if="sudahDikirim && Object.keys(galat).length"
                        class="mb-4"
                        tone="danger"
                        title="Data anggota belum valid"
                        message="Periksa kembali isian yang ditandai, lalu simpan ulang."
                    />

                    <div class="sim-form-grid">
                        <BaseInput
                            v-model="form.kodeAnggota"
                            label="Kode Anggota"
                            :placeholder="mode === 'tambah' ? `Contoh: ${contohKode}` : null"
                            :readonly="mode === 'ubah'"
                            :hint="mode === 'ubah' ? 'Kode Anggota tidak dapat diubah karena dipakai pada riwayat transaksi.' : 'Harus unik; tidak dapat diubah setelah disimpan.'"
                            required
                            autocomplete="off"
                            :error="galat.kodeAnggota"
                        />
                        <BaseSelect
                            v-model="form.status"
                            label="Status Anggota"
                            :options="daftarStatus('anggota')"
                            required
                            :error="galat.status"
                        />
                        <div class="is-full">
                            <BaseInput
                                v-model="form.nama"
                                label="Nama"
                                placeholder="Nama lengkap anggota"
                                required
                                autocomplete="off"
                                :error="galat.nama"
                            />
                        </div>
                        <div class="is-full">
                            <BaseInput
                                v-model="form.kontak"
                                label="Kontak"
                                placeholder="Contoh: 0812-3456-7890"
                                required
                                autocomplete="off"
                                :error="galat.kontak"
                            />
                        </div>
                    </div>
                </div>

                <div class="sim-card-footer">
                    <div class="sim-form-actions w-100">
                        <BaseButton variant="secondary" :href="rute('anggota.index')">Batal</BaseButton>
                        <BaseButton type="submit" icon="bi-check2">Simpan Anggota</BaseButton>
                    </div>
                </div>
            </form>

            <aside class="sim-card">
                <template v-if="mode === 'ubah' && anggota">
                    <div class="sim-card-header">
                        <h2 class="sim-card-title">Ringkasan Keanggotaan</h2>
                    </div>
                    <div class="sim-card-body">
                        <dl class="sim-detail-list">
                            <dt>Status saat ini</dt>
                            <dd><StatusBadge type="anggota" :status="anggota.status" /></dd>
                            <dt>Peminjaman Aktif</dt>
                            <dd class="tabular-nums">{{ transaksi.peminjamanAktif }}</dd>
                            <dt>Booking berjalan</dt>
                            <dd class="tabular-nums">{{ transaksi.bookingBerjalan }}</dd>
                            <dt>Total peminjaman</dt>
                            <dd class="tabular-nums">{{ totalRiwayat }}</dd>
                        </dl>
                        <FormFeedback
                            v-if="transaksi.peminjamanAktif + transaksi.bookingBerjalan > 0"
                            class="mt-4"
                            tone="info"
                            message="Menonaktifkan anggota tidak membatalkan peminjaman atau booking yang sedang berjalan. Anggota Nonaktif tidak dapat membuat peminjaman atau booking baru."
                        />
                    </div>
                </template>
                <template v-else>
                    <div class="sim-card-header">
                        <h2 class="sim-card-title">Ketentuan Pengisian</h2>
                    </div>
                    <div class="sim-card-body">
                        <ul class="sim-aside-list">
                            <li><i class="bi bi-check2-circle" aria-hidden="true"></i>Seluruh field bertanda * wajib diisi.</li>
                            <li><i class="bi bi-check2-circle" aria-hidden="true"></i>Kode Anggota harus unik dan tidak dapat diubah setelah disimpan.</li>
                            <li><i class="bi bi-check2-circle" aria-hidden="true"></i>Hanya anggota berstatus Aktif yang dapat meminjam dan mengajukan booking.</li>
                        </ul>
                    </div>
                </template>
            </aside>
        </div>

        <ConfirmationModal
            v-model:show="konfirmasiNonaktif"
            title="Nonaktifkan anggota?"
            :message="pesanNonaktif"
            tone="warning"
            icon="bi-person-slash"
            confirm-label="Nonaktifkan"
            @confirm="terapkan"
        />
    </AdminLayout>
</template>
