<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import BaseButton from '@/Components/BaseButton.vue';
import BaseTable from '@/Components/BaseTable.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import DetailPanel from '@/Components/DetailPanel.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormFeedback from '@/Components/FormFeedback.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SegmentedTabs from '@/Components/SegmentedTabs.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useDataPerpustakaan } from '@/composables/useDataPerpustakaan.js';
import { galatPertama, useTransaksiServer } from '@/composables/useTransaksiServer.js';
import { useDemo } from '@/composables/useDemo.js';
import { bookingAnggota, detailBooking } from '@/utils/olahData.js';
import AnggotaLayout from '@/Layouts/AnggotaLayout.vue';
import { formatTanggal, formatWaktu } from '@/utils/format.js';
import { daftarStatus } from '@/utils/status.js';

const { data } = useDataPerpustakaan();
const { memproses, kirim } = useTransaksiServer();
const { akun, rute } = useDemo();
const kodeAnggota = akun.value.kodeAnggota;

const tab = ref('semua');
const pesan = ref(null);

// Hanya booking milik anggota aktif (FR-018, FR-036).
const milikSaya = computed(() =>
    bookingAnggota(data, kodeAnggota)
        .map((b) => detailBooking(data, b))
        .sort((a, b) => b.tanggalBooking.localeCompare(a.tanggalBooking) || b.idBooking.localeCompare(a.idBooking)),
);
const opsiTab = computed(() => [
    { value: 'semua', label: 'Semua', count: milikSaya.value.length },
    ...daftarStatus('booking').map((s) => ({ value: s, label: s, count: milikSaya.value.filter((b) => b.status === s).length })),
]);
const baris = computed(() => milikSaya.value.filter((b) => tab.value === 'semua' || b.status === tab.value));

const dapatDibatalkan = (b) => b.status === 'Diajukan' || b.status === 'Disetujui';
const tanggal = (nilai, gaya = 'pendek') => formatTanggal(nilai, gaya);

function keterangan(b) {
    switch (b.status) {
        case 'Diajukan':
            return 'Menunggu verifikasi Admin.';
        case 'Disetujui':
            return `Disetujui ${tanggal(b.tanggalKeputusan)}. Eksemplar dipesan untuk Anda; ambil sebelum Batas Pengambilan ${formatWaktu(b.batasPengambilan)}.`;
        case 'Ditolak':
            return b.alasanPenolakan ? `Alasan: ${b.alasanPenolakan}` : 'Booking ditolak.';
        case 'Dibatalkan':
            return 'Booking dibatalkan.';
        case 'Direalisasikan':
            return `Direalisasikan menjadi peminjaman ${b.idPeminjaman}.`;
        case 'Kedaluwarsa':
            return `Tidak diambil sebelum Batas Pengambilan ${formatWaktu(b.batasPengambilan)}; reservasi eksemplar dilepas.`;
        default:
            return '—';
    }
}

const kolom = [
    { key: 'idBooking', label: 'ID Booking' },
    { key: 'buku', label: 'Buku & Eksemplar' },
    { key: 'tanggalBooking', label: 'Tanggal Pengajuan' },
    { key: 'status', label: 'Status' },
    { key: 'keterangan', label: 'Keterangan' },
    { key: 'aksi', label: 'Aksi', align: 'end' },
];

// ---- Detail & pembatalan -----------------------------------------------------
const idDipilih = ref(null);
const panel = ref(false);
const dialog = ref(false);
const galatBatal = ref(null);
const dipilih = computed(() => milikSaya.value.find((b) => b.idBooking === idDipilih.value) ?? null);

function lihat(b) {
    idDipilih.value = b.idBooking;
    panel.value = true;
}

function mintaBatal(b) {
    idDipilih.value = b.idBooking;
    galatBatal.value = null;
    panel.value = false;
    dialog.value = true;
}

const pesanBatal = computed(() => {
    if (!dipilih.value) return null;
    const dasar = `Booking “${dipilih.value.buku.judul}” (${dipilih.value.kodeEksemplar}) akan dibatalkan dan tidak dapat diaktifkan kembali.`;

    return dipilih.value.status === 'Disetujui'
        ? `${dasar} Reservasi eksemplar dilepas sehingga eksemplar dapat dipinjam anggota lain.`
        : dasar;
});

/** Batch 2 — POST /anggota/booking/{kode}/batalkan; hanya booking milik akun ini (diperiksa server). */
function batal() {
    kirim(rute('booking.batalkan', { kodeBooking: idDipilih.value }), {}, {
        onSukses: (kode) => {
            dialog.value = false;
            pesan.value = { tone: 'success', title: `Booking ${kode} dibatalkan`, message: 'Status booking kini Dibatalkan.' };
        },
        onGalat: (e) => {
            galatBatal.value = galatPertama(e);
        },
    });
}
</script>

<template>
    <AnggotaLayout>
        <Head title="Status Booking" />

        <PageHeader>
            <template #actions>
                <BaseButton icon="bi-bookmark-plus" :href="rute('booking.ajukan')">Ajukan Booking</BaseButton>
            </template>
        </PageHeader>

        <FormFeedback v-if="pesan" class="mb-4" :tone="pesan.tone" :title="pesan.title" :message="pesan.message" dismissible @dismiss="pesan = null" />

        <section class="sim-card">
            <div class="sim-toolbar">
                <SegmentedTabs v-model="tab" :options="opsiTab" label="Status booking" />
            </div>

            <BaseTable :columns="kolom" :rows="baris" row-key="idBooking" caption="Booking saya">
                <template #cell-idBooking="{ value }"><span class="sim-code">{{ value }}</span></template>
                <template #cell-buku="{ row }">
                    <span class="sim-cell-title">{{ row.buku.judul }}</span>
                    <span class="sim-cell-sub">{{ row.kodeEksemplar }}</span>
                </template>
                <template #cell-tanggalBooking="{ value }"><span class="text-nowrap tabular-nums">{{ tanggal(value) }}</span></template>
                <template #cell-status="{ value }"><StatusBadge type="booking" :status="value" /></template>
                <template #cell-keterangan="{ row }">
                    <span :class="['sim-note', { 'is-danger': row.status === 'Ditolak' }]">{{ keterangan(row) }}</span>
                </template>
                <template #cell-aksi="{ row }">
                    <span class="sim-table-actions">
                        <BaseButton variant="secondary" size="sm" icon="bi-eye" icon-only :label="`Detail ${row.idBooking}`" @click="lihat(row)" />
                        <BaseButton v-if="dapatDibatalkan(row)" variant="outline-danger" size="sm" icon="bi-x-lg" @click="mintaBatal(row)">Batalkan</BaseButton>
                    </span>
                </template>
                <template #empty>
                    <EmptyState
                        compact
                        icon="bi-bookmark"
                        :title="tab === 'semua' ? 'Belum ada booking' : `Tidak ada booking berstatus ${tab}`"
                        :description="tab === 'semua' ? 'Cari buku yang tersedia, lalu ajukan booking.' : null"
                    >
                        <template v-if="tab === 'semua'" #actions>
                            <BaseButton variant="secondary" size="sm" icon="bi-search" :href="rute('cari-buku')">Cari Buku</BaseButton>
                        </template>
                    </EmptyState>
                </template>
            </BaseTable>
        </section>

        <DetailPanel v-model:show="panel" title="Detail Booking" :subtitle="dipilih?.idBooking">
            <template v-if="dipilih">
                <section class="sim-drawer__section">
                    <dl class="sim-detail-list">
                        <dt>ID Booking</dt>
                        <dd class="sim-code">{{ dipilih.idBooking }}</dd>
                        <dt>Status</dt>
                        <dd><StatusBadge type="booking" :status="dipilih.status" /></dd>
                        <dt>Tanggal Pengajuan</dt>
                        <dd>{{ tanggal(dipilih.tanggalBooking, 'panjang') }}</dd>
                        <dt>Judul Buku</dt>
                        <dd>{{ dipilih.buku.judul }}</dd>
                        <dt>Penulis</dt>
                        <dd>{{ dipilih.buku.penulis }}</dd>
                        <dt>Kode Eksemplar</dt>
                        <dd class="sim-code">{{ dipilih.kodeEksemplar }}</dd>
                    </dl>
                </section>
                <section class="sim-drawer__section">
                    <p class="sim-subheading">Keputusan</p>
                    <div v-if="dipilih.status === 'Ditolak' && dipilih.alasanPenolakan" class="sim-reason mb-3">
                        <span class="sim-reason__label">Alasan Penolakan</span>
                        {{ dipilih.alasanPenolakan }}
                    </div>
                    <dl class="sim-detail-list">
                        <dt>Tanggal Keputusan</dt>
                        <dd>{{ dipilih.tanggalKeputusan ? tanggal(dipilih.tanggalKeputusan, 'panjang') : '—' }}</dd>
                        <template v-if="dipilih.batasPengambilan">
                            <dt>Batas Pengambilan</dt>
                            <dd>{{ formatWaktu(dipilih.batasPengambilan) }}</dd>
                        </template>
                        <template v-if="dipilih.peminjaman">
                            <dt>Peminjaman</dt>
                            <dd><span class="sim-code">{{ dipilih.peminjaman.idPeminjaman }}</span></dd>
                            <dt>Tanggal Pinjam</dt>
                            <dd>{{ tanggal(dipilih.peminjaman.tanggalPinjam, 'panjang') }}</dd>
                            <dt>Tanggal Jatuh Tempo</dt>
                            <dd>{{ tanggal(dipilih.peminjaman.tanggalJatuhTempo, 'panjang') }}</dd>
                        </template>
                    </dl>
                    <p class="sim-note mt-3 mb-0">{{ keterangan(dipilih) }}</p>
                </section>
            </template>
            <template #footer>
                <BaseButton variant="secondary" @click="panel = false">Tutup</BaseButton>
                <BaseButton v-if="dipilih && dapatDibatalkan(dipilih)" variant="outline-danger" icon="bi-x-lg" @click="mintaBatal(dipilih)">
                    Batalkan Booking
                </BaseButton>
            </template>
        </DetailPanel>

        <ConfirmationModal
            v-model:show="dialog"
            :title="`Batalkan booking ${dipilih?.idBooking ?? ''}?`"
            :message="pesanBatal"
            tone="danger"
            icon="bi-x-circle"
            confirm-label="Batalkan Booking"
            cancel-label="Kembali"
            :loading="memproses"
            @confirm="batal"
        >
            <FormFeedback v-if="galatBatal" tone="danger" title="Pembatalan ditolak" :message="galatBatal" />
        </ConfirmationModal>
    </AnggotaLayout>
</template>
