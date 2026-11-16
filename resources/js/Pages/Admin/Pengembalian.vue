<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import BaseButton from '@/Components/BaseButton.vue';
import BaseTable from '@/Components/BaseTable.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormFeedback from '@/Components/FormFeedback.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SearchInput from '@/Components/SearchInput.vue';
import SegmentedTabs from '@/Components/SegmentedTabs.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useDataPerpustakaan } from '@/composables/useDataPerpustakaan.js';
import { galatPertama, useTransaksiServer } from '@/composables/useTransaksiServer.js';
import { useDemo } from '@/composables/useDemo.js';
import { detailPeminjaman } from '@/utils/olahData.js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatTanggal } from '@/utils/format.js';
import { cocokKataKunci } from '@/utils/pencarian.js';

const { data, tanggalHariIni } = useDataPerpustakaan();
const { memproses, kirim } = useTransaksiServer();
const { akun, rute } = useDemo();

const tab = ref('aktif');
const kataKunci = ref('');
const pesan = ref(null);

const semua = computed(() => data.peminjaman.map((p) => detailPeminjaman(data, p)));
const cocok = (p) => cocokKataKunci([p.idPeminjaman, p.anggota.nama, p.anggota.kodeAnggota, p.buku.judul, p.kodeEksemplar], kataKunci.value);

const aktif = computed(() =>
    semua.value.filter((p) => p.status === 'Aktif').sort((a, b) => a.tanggalJatuhTempo.localeCompare(b.tanggalJatuhTempo) || a.idPeminjaman.localeCompare(b.idPeminjaman)),
);
const selesai = computed(() =>
    semua.value.filter((p) => p.status === 'Selesai').sort((a, b) => b.tanggalKembali.localeCompare(a.tanggalKembali) || b.idPeminjaman.localeCompare(a.idPeminjaman)),
);
const baris = computed(() => (tab.value === 'aktif' ? aktif.value : selesai.value).filter(cocok));

const opsiTab = computed(() => [
    { value: 'aktif', label: 'Peminjaman Aktif', count: aktif.value.length },
    { value: 'selesai', label: 'Riwayat Pengembalian', count: selesai.value.length },
]);

const kolomAktif = [
    { key: 'idPeminjaman', label: 'ID Peminjaman' },
    { key: 'anggota', label: 'Anggota' },
    { key: 'buku', label: 'Buku & Eksemplar' },
    { key: 'tanggalPinjam', label: 'Tanggal Pinjam' },
    { key: 'tanggalJatuhTempo', label: 'Tanggal Jatuh Tempo' },
    { key: 'status', label: 'Status' },
    { key: 'aksi', label: 'Aksi', align: 'end' },
];
const kolomSelesai = [
    { key: 'idPeminjaman', label: 'ID Peminjaman' },
    { key: 'anggota', label: 'Anggota' },
    { key: 'buku', label: 'Buku & Eksemplar' },
    { key: 'tanggalPinjam', label: 'Tanggal Pinjam' },
    { key: 'tanggalKembali', label: 'Tanggal Kembali' },
    { key: 'pemroses', label: 'Admin Pemroses' },
    { key: 'status', label: 'Status' },
];

// ---- Proses pengembalian ---------------------------------------------------
const idDipilih = ref(null);
const dialog = ref(false);
const galatDialog = ref(null);
const dipilih = computed(() => semua.value.find((p) => p.idPeminjaman === idDipilih.value) ?? null);

function buka(p) {
    idDipilih.value = p.idPeminjaman;
    galatDialog.value = null;
    dialog.value = true;
}

/**
 * Batch 2 — POST /admin/pengembalian. Anggota Aktif maupun Nonaktif dapat
 * dikembalikan (kebijakan disetujui); Admin penerima dari sesi.
 */
function proses() {
    kirim(rute('pengembalian.store'), { idPeminjaman: idDipilih.value }, {
        onSukses: (kode) => {
            const k = data.pengembalian.find((x) => x.idPengembalian === kode);
            const p = k ? semua.value.find((x) => x.idPeminjaman === k.idPeminjaman) : null;
            dialog.value = false;
            pesan.value = p
                ? {
                      tone: 'success',
                      title: 'Konfirmasi pengembalian',
                      message: `${p.idPeminjaman} — “${p.buku.judul}” dari ${p.anggota.nama} dikembalikan pada ${formatTanggal(k.tanggalKembali, 'panjang')} (${k.idPengembalian}). Eksemplar ${p.kodeEksemplar} kembali Tersedia.`,
                  }
                : null;
        },
        onGalat: (e) => {
            galatDialog.value = galatPertama(e);
        },
    });
}

const tanggal = (nilai) => formatTanggal(nilai, 'pendek');
</script>

<template>
    <AdminLayout>
        <Head title="Pengembalian Buku" />

        <PageHeader>
            <template #actions>
                <BaseButton variant="secondary" icon="bi-journal-arrow-up" :href="rute('peminjaman')">Peminjaman Buku</BaseButton>
            </template>
        </PageHeader>

        <FormFeedback v-if="pesan" class="mb-4" :tone="pesan.tone" :title="pesan.title" :message="pesan.message" dismissible @dismiss="pesan = null" />

        <section class="sim-card">
            <div class="sim-toolbar">
                <SegmentedTabs v-model="tab" :options="opsiTab" label="Status peminjaman" />
                <div class="sim-toolbar__search">
                    <SearchInput v-model="kataKunci" label="Cari transaksi" placeholder="Cari ID, anggota, judul, atau eksemplar" />
                </div>
            </div>

            <BaseTable
                v-if="tab === 'aktif'"
                :columns="kolomAktif"
                :rows="baris"
                row-key="idPeminjaman"
                caption="Peminjaman aktif"
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
                <template #cell-tanggalPinjam="{ value }"><span class="text-nowrap tabular-nums">{{ tanggal(value) }}</span></template>
                <template #cell-tanggalJatuhTempo="{ value }"><span class="text-nowrap tabular-nums">{{ tanggal(value) }}</span></template>
                <template #cell-status="{ value }"><StatusBadge type="peminjaman" :status="value" /></template>
                <template #cell-aksi="{ row }">
                    <BaseButton size="sm" icon="bi-journal-arrow-down" @click="buka(row)">Proses Pengembalian</BaseButton>
                </template>
                <template #empty>
                    <EmptyState
                        compact
                        :icon="kataKunci ? 'bi-search' : 'bi-journal-check'"
                        :title="kataKunci ? 'Transaksi tidak ditemukan' : 'Tidak ada peminjaman aktif'"
                        :description="kataKunci ? 'Ubah kata kunci pencarian, lalu coba lagi.' : 'Semua peminjaman telah dikembalikan.'"
                    />
                </template>
            </BaseTable>

            <BaseTable v-else :columns="kolomSelesai" :rows="baris" row-key="idPeminjaman" caption="Riwayat pengembalian">
                <template #cell-idPeminjaman="{ row }">
                    <span class="sim-code">{{ row.idPeminjaman }}</span>
                    <span class="sim-cell-sub">{{ row.pengembalian.idPengembalian }}</span>
                </template>
                <template #cell-anggota="{ row }">
                    <span class="sim-cell-title">{{ row.anggota.nama }}</span>
                    <span class="sim-cell-sub">{{ row.anggota.kodeAnggota }}</span>
                </template>
                <template #cell-buku="{ row }">
                    <span class="sim-cell-title">{{ row.buku.judul }}</span>
                    <span class="sim-cell-sub">{{ row.kodeEksemplar }}</span>
                </template>
                <template #cell-tanggalPinjam="{ value }"><span class="text-nowrap tabular-nums">{{ tanggal(value) }}</span></template>
                <template #cell-tanggalKembali="{ value }"><span class="text-nowrap tabular-nums">{{ tanggal(value) }}</span></template>
                <template #cell-pemroses="{ row }"><span class="text-nowrap">{{ data.pengguna.find((u) => u.idPengguna === row.pengembalian.diprosesOleh)?.nama }}</span></template>
                <template #cell-status="{ value }"><StatusBadge type="peminjaman" :status="value" /></template>
                <template #empty>
                    <EmptyState compact icon="bi-search" title="Transaksi tidak ditemukan" description="Ubah kata kunci pencarian, lalu coba lagi." />
                </template>
            </BaseTable>
        </section>

        <ConfirmationModal
            v-model:show="dialog"
            title="Proses pengembalian?"
            message="Peminjaman akan ditandai Selesai dan eksemplar kembali Tersedia."
            icon="bi-journal-arrow-down"
            confirm-label="Proses Pengembalian"
            size="md"
            :loading="memproses"
            @confirm="proses"
        >
            <FormFeedback v-if="galatDialog" class="mb-3" tone="danger" title="Pengembalian ditolak" :message="galatDialog" />
            <dl v-if="dipilih" class="sim-detail-list">
                <dt>ID Peminjaman</dt>
                <dd class="sim-code">{{ dipilih.idPeminjaman }}</dd>
                <dt>Anggota</dt>
                <dd>{{ dipilih.anggota.nama }} · {{ dipilih.anggota.kodeAnggota }}</dd>
                <dt>Buku</dt>
                <dd>{{ dipilih.buku.judul }}</dd>
                <dt>Kode Eksemplar</dt>
                <dd class="sim-code">{{ dipilih.kodeEksemplar }}</dd>
                <dt>Tanggal Pinjam</dt>
                <dd>{{ formatTanggal(dipilih.tanggalPinjam, 'panjang') }}</dd>
                <dt>Tanggal Jatuh Tempo</dt>
                <dd>{{ formatTanggal(dipilih.tanggalJatuhTempo, 'panjang') }}</dd>
                <dt>Status</dt>
                <dd><StatusBadge type="peminjaman" :status="dipilih.status" /></dd>
                <dt>Tanggal Kembali</dt>
                <dd>{{ formatTanggal(tanggalHariIni, 'panjang') }}</dd>
                <dt>Admin Pemroses</dt>
                <dd>{{ akun?.nama }}</dd>
            </dl>
        </ConfirmationModal>
    </AdminLayout>
</template>
