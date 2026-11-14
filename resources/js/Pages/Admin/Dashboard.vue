<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import BaseTable from '@/Components/BaseTable.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatCard from '@/Components/StatCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useDataPerpustakaan } from '@/composables/useDataPerpustakaan.js';
import { useDemo } from '@/composables/useDemo.js';
import { detailBooking, detailPeminjaman, rekapStatus, ringkasanAdmin } from '@/utils/olahData.js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatTanggal } from '@/utils/format.js';
import { daftarStatus } from '@/utils/status.js';

const { data, tanggalHariIni } = useDataPerpustakaan();
const { rute } = useDemo();

// Semua angka dihitung dari dataset (tidak ada nilai tetap).
const ringkasan = computed(() => ringkasanAdmin(data));
const jumlahKategori = computed(() => new Set(data.buku.map((b) => b.kategori)).size);

const rekapEksemplar = computed(() => rekapStatus(data.eksemplar, daftarStatus('eksemplar')));
const rekapPeminjaman = computed(() => rekapStatus(data.peminjaman, daftarStatus('peminjaman')));
const rekapBooking = computed(() => rekapStatus(data.booking, daftarStatus('booking')));

const NADA_SEGMEN = { Tersedia: 'is-success', Dipesan: 'is-warning', Dipinjam: 'is-info' };
const persen = (jumlah, total) => (total === 0 ? 0 : Math.round((jumlah / total) * 100));

const peminjamanAktif = computed(() =>
    data.peminjaman
        .filter((p) => p.status === 'Aktif')
        .map((p) => detailPeminjaman(data, p))
        .sort((a, b) => a.tanggalJatuhTempo.localeCompare(b.tanggalJatuhTempo) || a.idPeminjaman.localeCompare(b.idPeminjaman)),
);

const URUTAN_BOOKING = { Diajukan: 0, Disetujui: 1 };
const bookingBerjalan = computed(() =>
    data.booking
        .filter((b) => b.status === 'Diajukan' || b.status === 'Disetujui')
        .map((b) => detailBooking(data, b))
        .sort((a, b) => URUTAN_BOOKING[a.status] - URUTAN_BOOKING[b.status] || a.tanggalBooking.localeCompare(b.tanggalBooking)),
);

const kolomPeminjaman = [
    { key: 'idPeminjaman', label: 'ID Peminjaman' },
    { key: 'anggota', label: 'Anggota' },
    { key: 'buku', label: 'Buku' },
    { key: 'tanggalPinjam', label: 'Tanggal Pinjam' },
    { key: 'tanggalJatuhTempo', label: 'Jatuh Tempo' },
];

const kolomBooking = [
    { key: 'idBooking', label: 'ID Booking' },
    { key: 'anggota', label: 'Anggota' },
    { key: 'buku', label: 'Buku' },
    { key: 'status', label: 'Status' },
];

const deskripsi = `Ringkasan data dan layanan perpustakaan per ${formatTanggal(tanggalHariIni, 'panjang')}.`;
</script>

<template>
    <AdminLayout>
        <Head title="Dashboard" />

        <PageHeader :description="deskripsi" />

        <section class="sim-stat-grid" aria-label="Ringkasan">
            <StatCard
                label="Jumlah Anggota"
                :value="ringkasan.anggota.total"
                icon="bi-people"
                :caption="`${ringkasan.anggota.Aktif} Aktif · ${ringkasan.anggota.Nonaktif} Nonaktif`"
                :href="rute('anggota.index')"
                link-label="Data Anggota"
            />
            <StatCard
                label="Jumlah Buku"
                :value="ringkasan.buku.total"
                icon="bi-journal-bookmark"
                tone="indigo"
                :caption="`${jumlahKategori} kategori koleksi`"
                :href="rute('buku.index')"
                link-label="Data Buku"
            />
            <StatCard
                label="Jumlah Eksemplar"
                :value="ringkasan.eksemplar.total"
                icon="bi-stack"
                tone="success"
                :caption="`${ringkasan.eksemplar.Tersedia} eksemplar Tersedia`"
                :href="rute('buku.index')"
                link-label="Lihat eksemplar"
            />
            <StatCard
                label="Peminjaman Aktif"
                :value="ringkasan.peminjaman.Aktif"
                icon="bi-journal-arrow-up"
                tone="info"
                caption="Belum dikembalikan"
                :href="rute('pengembalian')"
                link-label="Pengembalian Buku"
            />
            <StatCard
                label="Booking Diajukan"
                :value="ringkasan.booking.Diajukan"
                icon="bi-hourglass-split"
                tone="warning"
                caption="Menunggu verifikasi"
                :href="rute('booking.verifikasi')"
                link-label="Verifikasi Booking"
            />
        </section>

        <div class="row g-4 mt-0">
            <div class="col-12 col-lg-5">
                <section class="sim-card h-100">
                    <div class="sim-card-header">
                        <div>
                            <h2 class="sim-card-title">Status Eksemplar</h2>
                            <p class="sim-card-subtitle">Ketersediaan {{ ringkasan.eksemplar.total }} eksemplar saat ini.</p>
                        </div>
                    </div>
                    <div class="sim-card-body">
                        <div class="sim-segbar" role="img" :aria-label="rekapEksemplar.map((r) => `${r.status} ${r.jumlah}`).join(', ')">
                            <span
                                v-for="r in rekapEksemplar"
                                :key="r.status"
                                :class="NADA_SEGMEN[r.status]"
                                :style="{ width: `${persen(r.jumlah, ringkasan.eksemplar.total)}%` }"
                            ></span>
                        </div>
                        <ul class="sim-breakdown">
                            <li v-for="r in rekapEksemplar" :key="r.status">
                                <StatusBadge type="eksemplar" :status="r.status" />
                                <span class="sim-breakdown__value">
                                    {{ r.jumlah }} <small>{{ persen(r.jumlah, ringkasan.eksemplar.total) }}%</small>
                                </span>
                            </li>
                        </ul>
                    </div>
                </section>
            </div>

            <div class="col-12 col-lg-7">
                <section class="sim-card h-100">
                    <div class="sim-card-header">
                        <div>
                            <h2 class="sim-card-title">Status Transaksi</h2>
                            <p class="sim-card-subtitle">Jumlah peminjaman dan booking menurut status.</p>
                        </div>
                    </div>
                    <div class="sim-card-body">
                        <div class="row g-4">
                            <div class="col-12 col-sm-5">
                                <p class="sim-subheading">Peminjaman</p>
                                <ul class="sim-breakdown">
                                    <li v-for="r in rekapPeminjaman" :key="r.status">
                                        <StatusBadge type="peminjaman" :status="r.status" />
                                        <span class="sim-breakdown__value">{{ r.jumlah }}</span>
                                    </li>
                                </ul>
                            </div>
                            <div class="col-12 col-sm-7">
                                <p class="sim-subheading">Booking</p>
                                <ul class="sim-breakdown">
                                    <li v-for="r in rekapBooking" :key="r.status">
                                        <StatusBadge type="booking" :status="r.status" />
                                        <span class="sim-breakdown__value">{{ r.jumlah }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-12">
                <section class="sim-card h-100">
                    <div class="sim-card-header">
                        <div>
                            <h2 class="sim-card-title">Peminjaman Aktif</h2>
                            <p class="sim-card-subtitle">Diurutkan berdasarkan tanggal jatuh tempo terdekat.</p>
                        </div>
                        <Link :href="rute('pengembalian')" class="btn btn-secondary btn-sm">
                            Proses Pengembalian
                            <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </Link>
                    </div>
                    <BaseTable
                        :columns="kolomPeminjaman"
                        :rows="peminjamanAktif"
                        row-key="idPeminjaman"
                        caption="Peminjaman aktif"
                        empty-icon="bi-journal-check"
                        empty-title="Tidak ada peminjaman aktif"
                    >
                        <template #cell-idPeminjaman="{ value }"><span class="sim-code">{{ value }}</span></template>
                        <template #cell-anggota="{ row }">
                            <span class="sim-cell-title">{{ row.anggota.nama }}</span>
                            <span class="sim-cell-sub">{{ row.anggota.kodeAnggota }}</span>
                        </template>
                        <template #cell-buku="{ row }">
                            <span class="sim-cell-title">{{ row.buku.judul }}</span>
                            <span class="sim-cell-sub">{{ row.kodeEksemplar }}</span>
                        </template>
                        <template #cell-tanggalPinjam="{ value }">
                            <span class="text-nowrap tabular-nums">{{ formatTanggal(value, 'pendek') }}</span>
                        </template>
                        <template #cell-tanggalJatuhTempo="{ value }">
                            <span class="text-nowrap tabular-nums">{{ formatTanggal(value, 'pendek') }}</span>
                        </template>
                    </BaseTable>
                </section>
            </div>

            <div class="col-12">
                <section class="sim-card h-100">
                    <div class="sim-card-header">
                        <div>
                            <h2 class="sim-card-title">Booking Perlu Tindak Lanjut</h2>
                            <p class="sim-card-subtitle">Menunggu verifikasi atau realisasi.</p>
                        </div>
                        <Link :href="rute('booking.verifikasi')" class="btn btn-secondary btn-sm">
                            Verifikasi
                            <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </Link>
                    </div>
                    <BaseTable
                        :columns="kolomBooking"
                        :rows="bookingBerjalan"
                        row-key="idBooking"
                        caption="Booking yang perlu ditindaklanjuti"
                        empty-icon="bi-clipboard-check"
                        empty-title="Tidak ada booking yang perlu ditindaklanjuti"
                    >
                        <template #cell-idBooking="{ value }"><span class="sim-code">{{ value }}</span></template>
                        <template #cell-anggota="{ row }">
                            <span class="sim-cell-title">{{ row.anggota.nama }}</span>
                            <span class="sim-cell-sub">{{ row.anggota.kodeAnggota }}</span>
                        </template>
                        <template #cell-buku="{ row }">
                            <span class="sim-cell-title">{{ row.buku.judul }}</span>
                            <span class="sim-cell-sub">{{ row.kodeEksemplar }}</span>
                        </template>
                        <template #cell-status="{ value }"><StatusBadge type="booking" :status="value" /></template>
                    </BaseTable>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>
