<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import BaseButton from '@/Components/BaseButton.vue';
import BaseTable from '@/Components/BaseTable.vue';
import DetailPanel from '@/Components/DetailPanel.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SegmentedTabs from '@/Components/SegmentedTabs.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useDataPerpustakaan } from '@/composables/useDataPerpustakaan.js';
import { useDemo } from '@/composables/useDemo.js';
import { bookingAsalPeminjaman, detailPeminjaman, peminjamanAnggota } from '@/utils/olahData.js';
import AnggotaLayout from '@/Layouts/AnggotaLayout.vue';
import { formatTanggal } from '@/utils/format.js';
import { daftarStatus } from '@/utils/status.js';

const { data } = useDataPerpustakaan();
const { akun, rute } = useDemo();
const kodeAnggota = akun.value.kodeAnggota;

const tab = ref('semua');

// Hanya riwayat milik anggota aktif (FR-036); terbaru di atas.
const riwayat = computed(() =>
    peminjamanAnggota(data, kodeAnggota)
        .map((p) => ({ ...detailPeminjaman(data, p), booking: bookingAsalPeminjaman(data, p.idPeminjaman) }))
        .sort((a, b) => b.tanggalPinjam.localeCompare(a.tanggalPinjam) || b.idPeminjaman.localeCompare(a.idPeminjaman)),
);
const opsiTab = computed(() => [
    { value: 'semua', label: 'Semua', count: riwayat.value.length },
    ...daftarStatus('peminjaman').map((s) => ({ value: s, label: s, count: riwayat.value.filter((p) => p.status === s).length })),
]);
const baris = computed(() => riwayat.value.filter((p) => tab.value === 'semua' || p.status === tab.value));

const kolom = [
    { key: 'idPeminjaman', label: 'ID Peminjaman' },
    { key: 'judul', label: 'Judul Buku' },
    { key: 'kodeEksemplar', label: 'Kode Eksemplar' },
    { key: 'tanggalPinjam', label: 'Tanggal Pinjam' },
    { key: 'tanggalKembali', label: 'Tanggal Kembali' },
    { key: 'status', label: 'Status' },
    { key: 'aksi', label: 'Aksi', align: 'end' },
];

const idDipilih = ref(null);
const panel = ref(false);
const dipilih = computed(() => riwayat.value.find((p) => p.idPeminjaman === idDipilih.value) ?? null);

function lihat(p) {
    idDipilih.value = p.idPeminjaman;
    panel.value = true;
}

const tanggal = (nilai, gaya = 'pendek') => formatTanggal(nilai, gaya);
</script>

<template>
    <AnggotaLayout>
        <Head title="History Peminjaman" />

        <PageHeader />

        <section class="sim-card">
            <div class="sim-toolbar">
                <SegmentedTabs v-model="tab" :options="opsiTab" label="Status peminjaman" />
            </div>

            <BaseTable :columns="kolom" :rows="baris" row-key="idPeminjaman" caption="Riwayat peminjaman saya">
                <template #cell-idPeminjaman="{ value }"><span class="sim-code">{{ value }}</span></template>
                <template #cell-judul="{ row }"><span class="sim-cell-title">{{ row.buku.judul }}</span></template>
                <template #cell-kodeEksemplar="{ value }"><span class="sim-code">{{ value }}</span></template>
                <template #cell-tanggalPinjam="{ value }"><span class="text-nowrap tabular-nums">{{ tanggal(value) }}</span></template>
                <template #cell-tanggalKembali="{ value }">
                    <span v-if="value" class="text-nowrap tabular-nums">{{ tanggal(value) }}</span>
                    <span v-else class="text-secondary" aria-label="Belum dikembalikan">—</span>
                </template>
                <template #cell-status="{ value }"><StatusBadge type="peminjaman" :status="value" /></template>
                <template #cell-aksi="{ row }">
                    <BaseButton variant="secondary" size="sm" icon="bi-eye" icon-only :label="`Detail ${row.idPeminjaman}`" @click="lihat(row)" />
                </template>
                <template #empty>
                    <EmptyState
                        compact
                        icon="bi-clock-history"
                        :title="tab === 'semua' ? 'Belum ada riwayat peminjaman' : `Tidak ada peminjaman berstatus ${tab}`"
                        :description="tab === 'semua' ? 'Riwayat akan tampil setelah Anda meminjam buku di perpustakaan.' : null"
                    >
                        <template v-if="tab === 'semua'" #actions>
                            <BaseButton variant="secondary" size="sm" icon="bi-search" :href="rute('cari-buku')">Cari Buku</BaseButton>
                        </template>
                    </EmptyState>
                </template>
            </BaseTable>
        </section>

        <DetailPanel v-model:show="panel" title="Detail Riwayat" :subtitle="dipilih?.idPeminjaman">
            <template v-if="dipilih">
                <section class="sim-drawer__section">
                    <dl class="sim-detail-list">
                        <dt>ID Peminjaman</dt>
                        <dd class="sim-code">{{ dipilih.idPeminjaman }}</dd>
                        <dt>Status</dt>
                        <dd><StatusBadge type="peminjaman" :status="dipilih.status" /></dd>
                        <dt>Judul Buku</dt>
                        <dd>{{ dipilih.buku.judul }}</dd>
                        <dt>Penulis</dt>
                        <dd>{{ dipilih.buku.penulis }}</dd>
                        <dt>Kode Eksemplar</dt>
                        <dd class="sim-code">{{ dipilih.kodeEksemplar }}</dd>
                        <dt>Tanggal Pinjam</dt>
                        <dd>{{ tanggal(dipilih.tanggalPinjam, 'panjang') }}</dd>
                        <dt>Tanggal Jatuh Tempo</dt>
                        <dd>{{ tanggal(dipilih.tanggalJatuhTempo, 'panjang') }}</dd>
                        <dt>Tanggal Kembali</dt>
                        <dd>{{ dipilih.tanggalKembali ? tanggal(dipilih.tanggalKembali, 'panjang') : 'Belum dikembalikan' }}</dd>
                        <dt>Asal Transaksi</dt>
                        <dd>
                            <template v-if="dipilih.booking">Realisasi booking <span class="sim-code">{{ dipilih.booking.idBooking }}</span></template>
                            <template v-else>Peminjaman langsung di perpustakaan</template>
                        </dd>
                    </dl>
                </section>
            </template>
            <template #footer>
                <BaseButton variant="secondary" @click="panel = false">Tutup</BaseButton>
            </template>
        </DetailPanel>
    </AnggotaLayout>
</template>
