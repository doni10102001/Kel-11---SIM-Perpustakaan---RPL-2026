<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

import BaseButton from '@/Components/BaseButton.vue';
import BaseTable from '@/Components/BaseTable.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormFeedback from '@/Components/FormFeedback.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatCard from '@/Components/StatCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useDataPerpustakaan } from '@/composables/useDataPerpustakaan.js';
import { useDemo } from '@/composables/useDemo.js';
import { cariAnggota, bookingAnggota, detailBooking, detailPeminjaman, peminjamanAnggota, ringkasanAnggota } from '@/data/mock/index.js';
import AnggotaLayout from '@/Layouts/AnggotaLayout.vue';
import { formatTanggal } from '@/utils/format.js';

const { data, tanggalHariIni } = useDataPerpustakaan();
const { akun, rute } = useDemo();
const kodeAnggota = akun.value.kodeAnggota;

// Semua angka dihitung dari data milik anggota aktif (tidak ada nilai tetap).
const anggota = computed(() => cariAnggota(data, kodeAnggota));
const ringkasan = computed(() => ringkasanAnggota(data, kodeAnggota));

const peminjamanAktif = computed(() =>
    peminjamanAnggota(data, kodeAnggota)
        .filter((p) => p.status === 'Aktif')
        .map((p) => detailPeminjaman(data, p))
        .sort((a, b) => a.tanggalJatuhTempo.localeCompare(b.tanggalJatuhTempo)),
);
const bookingBerjalan = computed(() =>
    bookingAnggota(data, kodeAnggota)
        .filter((b) => b.status === 'Diajukan' || b.status === 'Disetujui')
        .map((b) => detailBooking(data, b))
        .sort((a, b) => b.tanggalBooking.localeCompare(a.tanggalBooking)),
);

const kolomPinjam = [
    { key: 'idPeminjaman', label: 'ID Peminjaman' },
    { key: 'buku', label: 'Judul Buku' },
    { key: 'kodeEksemplar', label: 'Kode Eksemplar' },
    { key: 'tanggalPinjam', label: 'Tanggal Pinjam' },
    { key: 'tanggalJatuhTempo', label: 'Tanggal Jatuh Tempo' },
];
const kolomBooking = [
    { key: 'idBooking', label: 'ID Booking' },
    { key: 'buku', label: 'Judul Buku' },
    { key: 'kodeEksemplar', label: 'Kode Eksemplar' },
    { key: 'tanggalBooking', label: 'Tanggal Pengajuan' },
    { key: 'status', label: 'Status' },
];

const deskripsi = `Selamat datang, ${akun.value.nama}. Ringkasan booking dan peminjaman Anda per ${formatTanggal(tanggalHariIni, 'panjang')}.`;
const tanggal = (nilai) => formatTanggal(nilai, 'pendek');
</script>

<template>
    <AnggotaLayout>
        <Head title="Dashboard" />

        <PageHeader :description="deskripsi">
            <template #actions>
                <BaseButton icon="bi-search" :href="rute('cari-buku')">Cari Buku</BaseButton>
            </template>
        </PageHeader>

        <FormFeedback
            v-if="anggota?.status === 'Nonaktif'"
            class="mb-4"
            tone="warning"
            title="Keanggotaan Anda berstatus Nonaktif"
            message="Anda tidak dapat mengajukan booking baru. Hubungi Admin perpustakaan untuk informasi lebih lanjut."
        />

        <section class="sim-stat-grid is-3" aria-label="Ringkasan">
            <StatCard
                label="Booking Aktif"
                :value="ringkasan.bookingAktif"
                icon="bi-bookmark-plus"
                tone="warning"
                caption="Diajukan atau disetujui"
                :href="rute('booking.status')"
                link-label="Status Booking"
            />
            <StatCard
                label="Peminjaman Aktif"
                :value="ringkasan.peminjamanAktif"
                icon="bi-journal-arrow-up"
                tone="info"
                caption="Buku yang sedang Anda pinjam"
                :href="rute('history')"
                link-label="History Peminjaman"
            />
            <StatCard
                label="Riwayat Selesai"
                :value="ringkasan.riwayatSelesai"
                icon="bi-check2-circle"
                tone="success"
                caption="Peminjaman yang telah dikembalikan"
                :href="rute('history')"
                link-label="Lihat riwayat"
            />
        </section>

        <div class="row g-4 mt-0">
            <div class="col-12">
                <section class="sim-card">
                    <div class="sim-card-header">
                        <div>
                            <h2 class="sim-card-title">Peminjaman Aktif</h2>
                            <p class="sim-card-subtitle">Kembalikan buku ke perpustakaan sebelum tanggal jatuh tempo.</p>
                        </div>
                        <BaseButton variant="secondary" size="sm" icon-end="bi-arrow-right" :href="rute('history')">History Peminjaman</BaseButton>
                    </div>
                    <BaseTable :columns="kolomPinjam" :rows="peminjamanAktif" row-key="idPeminjaman" caption="Peminjaman aktif saya">
                        <template #cell-idPeminjaman="{ value }"><span class="sim-code">{{ value }}</span></template>
                        <template #cell-buku="{ row }"><span class="sim-cell-title">{{ row.buku.judul }}</span></template>
                        <template #cell-kodeEksemplar="{ value }"><span class="sim-code">{{ value }}</span></template>
                        <template #cell-tanggalPinjam="{ value }"><span class="text-nowrap tabular-nums">{{ tanggal(value) }}</span></template>
                        <template #cell-tanggalJatuhTempo="{ value }"><span class="text-nowrap tabular-nums fw-semibold">{{ tanggal(value) }}</span></template>
                        <template #empty>
                            <EmptyState compact icon="bi-journal-check" title="Tidak ada peminjaman aktif" description="Buku yang sedang Anda pinjam akan tampil di sini." />
                        </template>
                    </BaseTable>
                </section>
            </div>

            <div class="col-12">
                <section class="sim-card">
                    <div class="sim-card-header">
                        <div>
                            <h2 class="sim-card-title">Booking Berjalan</h2>
                            <p class="sim-card-subtitle">Booking yang menunggu verifikasi atau realisasi peminjaman.</p>
                        </div>
                        <BaseButton variant="secondary" size="sm" icon-end="bi-arrow-right" :href="rute('booking.status')">Status Booking</BaseButton>
                    </div>
                    <BaseTable :columns="kolomBooking" :rows="bookingBerjalan" row-key="idBooking" caption="Booking berjalan saya">
                        <template #cell-idBooking="{ value }"><span class="sim-code">{{ value }}</span></template>
                        <template #cell-buku="{ row }"><span class="sim-cell-title">{{ row.buku.judul }}</span></template>
                        <template #cell-kodeEksemplar="{ value }"><span class="sim-code">{{ value }}</span></template>
                        <template #cell-tanggalBooking="{ value }"><span class="text-nowrap tabular-nums">{{ tanggal(value) }}</span></template>
                        <template #cell-status="{ value }"><StatusBadge type="booking" :status="value" /></template>
                        <template #empty>
                            <EmptyState compact icon="bi-bookmark" title="Tidak ada booking berjalan" description="Cari buku yang tersedia, lalu ajukan booking.">
                                <template #actions>
                                    <BaseButton variant="secondary" size="sm" icon="bi-bookmark-plus" :href="rute('booking.ajukan')">Ajukan Booking</BaseButton>
                                </template>
                            </EmptyState>
                        </template>
                    </BaseTable>
                </section>
            </div>
        </div>
    </AnggotaLayout>
</template>
