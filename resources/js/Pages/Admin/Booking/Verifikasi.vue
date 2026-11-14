<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import BaseButton from '@/Components/BaseButton.vue';
import BaseInput from '@/Components/BaseInput.vue';
import BaseTable from '@/Components/BaseTable.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import DetailPanel from '@/Components/DetailPanel.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormFeedback from '@/Components/FormFeedback.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SearchInput from '@/Components/SearchInput.vue';
import SegmentedTabs from '@/Components/SegmentedTabs.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { GalatValidasi, useDataPerpustakaan } from '@/composables/useDataPerpustakaan.js';
import { useDemo } from '@/composables/useDemo.js';
import { detailBooking } from '@/utils/olahData.js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatTanggal } from '@/utils/format.js';
import { cocokKataKunci } from '@/utils/pencarian.js';
import { daftarStatus } from '@/utils/status.js';
import { eksemplarDapatDipesan, validasiTolakBooking } from '@/utils/validasiTransaksi.js';

const { data, setujuiBooking, tolakBooking } = useDataPerpustakaan();
const { rute } = useDemo();

const tab = ref('Diajukan');
const kataKunci = ref('');
const pesan = ref(null);

const semua = computed(() =>
    data.booking
        .map((b) => detailBooking(data, b))
        .sort((a, b) => b.tanggalBooking.localeCompare(a.tanggalBooking) || b.idBooking.localeCompare(a.idBooking)),
);
const opsiTab = computed(() => [
    ...daftarStatus('booking').map((s) => ({ value: s, label: s, count: semua.value.filter((b) => b.status === s).length })),
    { value: 'semua', label: 'Semua', count: semua.value.length },
]);
const baris = computed(() =>
    semua.value
        .filter((b) => tab.value === 'semua' || b.status === tab.value)
        .filter((b) => cocokKataKunci([b.idBooking, b.anggota.nama, b.anggota.kodeAnggota, b.buku.judul, b.kodeEksemplar], kataKunci.value)),
);

const kolom = [
    { key: 'idBooking', label: 'ID Booking' },
    { key: 'anggota', label: 'Anggota' },
    { key: 'buku', label: 'Buku & Eksemplar' },
    { key: 'tanggalBooking', label: 'Tanggal Pengajuan' },
    { key: 'status', label: 'Status Booking' },
    { key: 'aksi', label: 'Aksi', align: 'end' },
];

const dapatDisetujui = (b) => b.status === 'Diajukan' && eksemplarDapatDipesan(data, b.kodeEksemplar);
const namaPengguna = (id) => data.pengguna.find((u) => u.idPengguna === id)?.nama ?? '—';

// ---- Detail ------------------------------------------------------------------
const idDipilih = ref(null);
const panel = ref(false);
const dipilih = computed(() => semua.value.find((b) => b.idBooking === idDipilih.value) ?? null);

function lihat(b) {
    idDipilih.value = b.idBooking;
    panel.value = true;
}

// ---- Setujui -----------------------------------------------------------------
const dialogSetujui = ref(false);
const galatSetujui = ref(null);

function mintaSetujui(b) {
    idDipilih.value = b.idBooking;
    galatSetujui.value = null;
    panel.value = false;
    dialogSetujui.value = true;
}

function setujui() {
    try {
        const b = setujuiBooking(idDipilih.value);
        const d = detailBooking(data, data.booking.find((x) => x.idBooking === b.idBooking));
        dialogSetujui.value = false;
        pesan.value = {
            tone: 'success',
            title: `Booking ${d.idBooking} disetujui`,
            message: `Eksemplar ${d.kodeEksemplar} (“${d.buku.judul}”) kini Dipesan untuk ${d.anggota.nama} hingga direalisasikan atau dibatalkan. Perubahan pada mode pratinjau tidak disimpan setelah halaman dimuat ulang.`,
            realisasi: d.idBooking,
        };
    } catch (error) {
        if (!(error instanceof GalatValidasi)) throw error;
        galatSetujui.value = Object.values(error.galat)[0];
    }
}

// ---- Tolak -------------------------------------------------------------------
const dialogTolak = ref(false);
const alasan = ref('');
const galatTolak = ref({});

// Pesan galat alasan hilang begitu pengguna mulai mengetik.
watch(alasan, () => {
    if (galatTolak.value.alasanPenolakan) galatTolak.value = { ...galatTolak.value, alasanPenolakan: null };
});

function mintaTolak(b) {
    idDipilih.value = b.idBooking;
    alasan.value = '';
    galatTolak.value = {};
    panel.value = false;
    dialogTolak.value = true;
}

function tolak() {
    galatTolak.value = validasiTolakBooking(idDipilih.value, alasan.value, data);
    if (Object.keys(galatTolak.value).length > 0) return;

    try {
        const b = tolakBooking(idDipilih.value, alasan.value);
        dialogTolak.value = false;
        pesan.value = {
            tone: 'success',
            title: `Booking ${b.idBooking} ditolak`,
            message: `Alasan: ${b.alasanPenolakan} Perubahan pada mode pratinjau tidak disimpan setelah halaman dimuat ulang.`,
        };
    } catch (error) {
        if (!(error instanceof GalatValidasi)) throw error;
        galatTolak.value = error.galat;
    }
}

const tanggal = (nilai, gaya = 'pendek') => formatTanggal(nilai, gaya);
</script>

<template>
    <AdminLayout>
        <Head title="Verifikasi Booking" />

        <PageHeader>
            <template #actions>
                <BaseButton variant="secondary" icon="bi-bookmark-check" :href="rute('booking.realisasi')">Realisasi Booking</BaseButton>
            </template>
        </PageHeader>

        <FormFeedback v-if="pesan" class="mb-4" :tone="pesan.tone" :title="pesan.title" dismissible @dismiss="pesan = null">
            {{ pesan.message }}
            <template v-if="pesan.realisasi">
                <br />
                <Link :href="`${rute('booking.realisasi')}?booking=${pesan.realisasi}`" class="fw-semibold">Lanjutkan ke Realisasi Booking</Link>
            </template>
        </FormFeedback>

        <section class="sim-card">
            <div class="sim-toolbar">
                <SegmentedTabs v-model="tab" :options="opsiTab" label="Status booking" />
                <div class="sim-toolbar__search">
                    <SearchInput v-model="kataKunci" label="Cari booking" placeholder="Cari ID, anggota, atau judul" />
                </div>
            </div>

            <BaseTable :columns="kolom" :rows="baris" row-key="idBooking" caption="Daftar booking">
                <template #cell-idBooking="{ value }"><span class="sim-code">{{ value }}</span></template>
                <template #cell-anggota="{ row }">
                    <span class="sim-cell-title">{{ row.anggota.nama }}</span>
                    <span class="sim-cell-sub">{{ row.anggota.kodeAnggota }}</span>
                </template>
                <template #cell-buku="{ row }">
                    <span class="sim-cell-title">{{ row.buku.judul }}</span>
                    <span class="sim-cell-sub">{{ row.kodeEksemplar }} · {{ row.eksemplar.status }}</span>
                </template>
                <template #cell-tanggalBooking="{ value }"><span class="text-nowrap tabular-nums">{{ tanggal(value) }}</span></template>
                <template #cell-status="{ value }"><StatusBadge type="booking" :status="value" /></template>
                <template #cell-aksi="{ row }">
                    <span class="sim-table-actions">
                        <BaseButton variant="secondary" size="sm" icon="bi-eye" icon-only :label="`Detail ${row.idBooking}`" @click="lihat(row)" />
                        <template v-if="row.status === 'Diajukan'">
                            <BaseButton
                                variant="success"
                                size="sm"
                                icon="bi-check-lg"
                                :disabled="!dapatDisetujui(row)"
                                :title="dapatDisetujui(row) ? null : 'Eksemplar tidak tersedia; booking hanya dapat ditolak.'"
                                @click="mintaSetujui(row)"
                            >
                                Setujui
                            </BaseButton>
                            <BaseButton variant="outline-danger" size="sm" icon="bi-x-lg" @click="mintaTolak(row)">Tolak</BaseButton>
                        </template>
                        <BaseButton
                            v-else-if="row.status === 'Disetujui'"
                            variant="outline-primary"
                            size="sm"
                            icon="bi-bookmark-check"
                            :href="`${rute('booking.realisasi')}?booking=${row.idBooking}`"
                        >
                            Realisasikan
                        </BaseButton>
                    </span>
                </template>
                <template #empty>
                    <EmptyState
                        compact
                        :icon="kataKunci ? 'bi-search' : 'bi-clipboard-check'"
                        :title="kataKunci ? 'Booking tidak ditemukan' : `Tidak ada booking berstatus ${tab === 'semua' ? 'apa pun' : tab}`"
                        :description="kataKunci ? 'Ubah kata kunci pencarian, lalu coba lagi.' : null"
                    />
                </template>
            </BaseTable>
        </section>

        <DetailPanel v-model:show="panel" title="Detail Booking" :subtitle="dipilih?.idBooking">
            <template v-if="dipilih">
                <FormFeedback
                    v-if="dipilih.status === 'Diajukan' && !dapatDisetujui(dipilih)"
                    class="mb-4"
                    tone="warning"
                    title="Eksemplar tidak tersedia"
                    :message="`Eksemplar ${dipilih.kodeEksemplar} berstatus ${dipilih.eksemplar.status}. Booking ini hanya dapat ditolak.`"
                />
                <section class="sim-drawer__section">
                    <dl class="sim-detail-list">
                        <dt>ID Booking</dt>
                        <dd class="sim-code">{{ dipilih.idBooking }}</dd>
                        <dt>Status Booking</dt>
                        <dd><StatusBadge type="booking" :status="dipilih.status" /></dd>
                        <dt>Tanggal Pengajuan</dt>
                        <dd>{{ tanggal(dipilih.tanggalBooking, 'panjang') }}</dd>
                        <dt>Anggota</dt>
                        <dd>{{ dipilih.anggota.nama }} · {{ dipilih.anggota.kodeAnggota }}</dd>
                        <dt>Status Anggota</dt>
                        <dd><StatusBadge type="anggota" :status="dipilih.anggota.status" /></dd>
                        <dt>Buku</dt>
                        <dd>{{ dipilih.buku.judul }}</dd>
                        <dt>Kode Eksemplar</dt>
                        <dd class="sim-code">{{ dipilih.kodeEksemplar }}</dd>
                        <dt>Status Eksemplar</dt>
                        <dd><StatusBadge type="eksemplar" :status="dipilih.eksemplar.status" /></dd>
                    </dl>
                </section>
                <section v-if="dipilih.tanggalKeputusan || dipilih.peminjaman" class="sim-drawer__section">
                    <p class="sim-subheading">Keputusan</p>
                    <dl class="sim-detail-list">
                        <template v-if="dipilih.tanggalKeputusan">
                            <dt>Tanggal Keputusan</dt>
                            <dd>{{ tanggal(dipilih.tanggalKeputusan, 'panjang') }}</dd>
                            <dt>Diputuskan oleh</dt>
                            <dd>{{ namaPengguna(dipilih.diputuskanOleh) }}</dd>
                        </template>
                        <template v-if="dipilih.alasanPenolakan">
                            <dt>Alasan Penolakan</dt>
                            <dd>{{ dipilih.alasanPenolakan }}</dd>
                        </template>
                        <template v-if="dipilih.peminjaman">
                            <dt>Peminjaman</dt>
                            <dd><span class="sim-code">{{ dipilih.peminjaman.idPeminjaman }}</span> · {{ tanggal(dipilih.peminjaman.tanggalPinjam, 'panjang') }}</dd>
                        </template>
                    </dl>
                </section>
            </template>
            <template #footer>
                <BaseButton variant="secondary" @click="panel = false">Tutup</BaseButton>
                <template v-if="dipilih?.status === 'Diajukan'">
                    <BaseButton variant="outline-danger" icon="bi-x-lg" @click="mintaTolak(dipilih)">Tolak</BaseButton>
                    <BaseButton variant="success" icon="bi-check-lg" :disabled="!dapatDisetujui(dipilih)" @click="mintaSetujui(dipilih)">Setujui</BaseButton>
                </template>
                <BaseButton
                    v-else-if="dipilih?.status === 'Disetujui'"
                    icon="bi-bookmark-check"
                    :href="`${rute('booking.realisasi')}?booking=${dipilih.idBooking}`"
                >
                    Realisasikan
                </BaseButton>
            </template>
        </DetailPanel>

        <ConfirmationModal
            v-model:show="dialogSetujui"
            :title="`Setujui booking ${dipilih?.idBooking ?? ''}?`"
            :message="dipilih ? `Eksemplar ${dipilih.kodeEksemplar} (“${dipilih.buku.judul}”) akan dipesan untuk ${dipilih.anggota.nama} dan tidak dapat dipinjam anggota lain sampai booking direalisasikan atau dibatalkan.` : null"
            tone="success"
            icon="bi-check2-circle"
            confirm-label="Setujui Booking"
            @confirm="setujui"
        >
            <FormFeedback v-if="galatSetujui" tone="danger" title="Persetujuan ditolak" :message="galatSetujui" />
        </ConfirmationModal>

        <ConfirmationModal
            v-model:show="dialogTolak"
            :title="`Tolak booking ${dipilih?.idBooking ?? ''}?`"
            :message="dipilih ? `Pengajuan ${dipilih.anggota.nama} untuk “${dipilih.buku.judul}” (${dipilih.kodeEksemplar}) akan ditolak.` : null"
            tone="danger"
            icon="bi-x-circle"
            confirm-label="Tolak Booking"
            size="md"
            @confirm="tolak"
        >
            <FormFeedback v-if="galatTolak.umum" class="mb-3" tone="danger" :message="galatTolak.umum" />
            <FormFeedback
                v-if="dipilih && !dapatDisetujui(dipilih) && dipilih.status === 'Diajukan'"
                class="mb-3"
                tone="warning"
                :message="`Eksemplar ${dipilih.kodeEksemplar} saat ini berstatus ${dipilih.eksemplar.status}.`"
            />
            <BaseInput
                v-model="alasan"
                label="Alasan Penolakan"
                placeholder="Tuliskan alasan penolakan yang akan dilihat anggota"
                multiline
                :rows="3"
                maxlength="200"
                required
                :error="galatTolak.alasanPenolakan"
            />
        </ConfirmationModal>
    </AdminLayout>
</template>
