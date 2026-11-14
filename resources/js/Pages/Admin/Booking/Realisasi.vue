<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';

import BaseButton from '@/Components/BaseButton.vue';
import BaseInput from '@/Components/BaseInput.vue';
import BaseTable from '@/Components/BaseTable.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormFeedback from '@/Components/FormFeedback.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ReadonlyField from '@/Components/ReadonlyField.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { GalatValidasi, useDataPerpustakaan } from '@/composables/useDataPerpustakaan.js';
import { useDemo } from '@/composables/useDemo.js';
import { detailBooking, detailPeminjaman } from '@/utils/olahData.js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatTanggal } from '@/utils/format.js';
import { validasiRealisasi } from '@/utils/validasiTransaksi.js';

const { data, tanggalHariIni, realisasikanBooking } = useDataPerpustakaan();
const { akun, rute } = useDemo();
const page = usePage();

const disetujui = computed(() =>
    data.booking
        .filter((b) => b.status === 'Disetujui')
        .map((b) => detailBooking(data, b))
        .sort((a, b) => a.tanggalKeputusan.localeCompare(b.tanggalKeputusan) || a.idBooking.localeCompare(b.idBooking)),
);

// Booking dapat dipilih langsung dari Verifikasi Booking (?booking=BP-001).
const dariQuery = new URLSearchParams(page.url.split('?')[1] ?? '').get('booking');
const idDipilih = ref(disetujui.value.some((b) => b.idBooking === dariQuery) ? dariQuery : null);
const dipilih = computed(() => disetujui.value.find((b) => b.idBooking === idDipilih.value) ?? null);

const tanggalJatuhTempo = ref('');
const sudahDikirim = ref(false);
const konfirmasi = ref(false);
const bukti = ref(null);
const formulir = ref(null);

const galatSemua = computed(() =>
    dipilih.value ? validasiRealisasi(dipilih.value.idBooking, { tanggalJatuhTempo: tanggalJatuhTempo.value }, data, { tanggalPinjam: tanggalHariIni }) : {},
);
// Syarat anggota & eksemplar ditampilkan langsung; jatuh tempo setelah percobaan simpan.
const galat = computed(() => ({
    umum: galatSemua.value.umum,
    tanggalJatuhTempo: sudahDikirim.value ? galatSemua.value.tanggalJatuhTempo : null,
}));

async function pilih(b) {
    idDipilih.value = b.idBooking;
    tanggalJatuhTempo.value = '';
    sudahDikirim.value = false;
    await nextTick();
    formulir.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function batal() {
    idDipilih.value = null;
    tanggalJatuhTempo.value = '';
    sudahDikirim.value = false;
}

async function simpan() {
    sudahDikirim.value = true;
    if (Object.keys(galatSemua.value).length > 0) {
        await nextTick();
        document.querySelector('form [aria-invalid="true"]')?.focus();

        return;
    }
    konfirmasi.value = true;
}

function terapkan() {
    try {
        const { peminjaman } = realisasikanBooking(idDipilih.value, { tanggalJatuhTempo: tanggalJatuhTempo.value });
        const p = detailPeminjaman(data, data.peminjaman.find((x) => x.idPeminjaman === peminjaman.idPeminjaman));
        bukti.value = { ...p, idBooking: idDipilih.value };
        konfirmasi.value = false;
        batal();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (error) {
        konfirmasi.value = false;
        if (!(error instanceof GalatValidasi)) throw error;
    }
}

const kolom = [
    { key: 'idBooking', label: 'ID Booking' },
    { key: 'anggota', label: 'Anggota' },
    { key: 'buku', label: 'Buku' },
    { key: 'kodeEksemplar', label: 'Kode Eksemplar' },
    { key: 'statusEksemplar', label: 'Status Eksemplar' },
    { key: 'tanggalKeputusan', label: 'Tanggal Disetujui' },
    { key: 'aksi', label: 'Aksi', align: 'end' },
];

const tanggal = (nilai, gaya = 'pendek') => formatTanggal(nilai, gaya);
</script>

<template>
    <AdminLayout>
        <Head title="Realisasi Booking" />

        <PageHeader>
            <template #actions>
                <BaseButton variant="secondary" icon="bi-arrow-left" :href="rute('booking.verifikasi')">Verifikasi Booking</BaseButton>
            </template>
        </PageHeader>

        <section v-if="bukti" class="sim-receipt mb-4" aria-labelledby="judul-bukti-realisasi" role="status">
            <div class="sim-receipt__header">
                <span class="sim-receipt__icon" aria-hidden="true"><i class="bi bi-bookmark-check"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <h2 id="judul-bukti-realisasi" class="sim-receipt__title">Booking {{ bukti.idBooking }} direalisasikan menjadi peminjaman {{ bukti.idPeminjaman }}</h2>
                    <p class="sim-receipt__subtitle">Status booking kini Direalisasikan dan eksemplar {{ bukti.kodeEksemplar }} berstatus Dipinjam.</p>
                </div>
                <BaseButton variant="ghost" size="sm" icon="bi-x-lg" icon-only label="Tutup bukti realisasi" @click="bukti = null" />
            </div>
            <dl class="sim-receipt__body mb-0">
                <div class="sim-receipt__item"><dt>ID Peminjaman</dt><dd class="sim-code">{{ bukti.idPeminjaman }}</dd></div>
                <div class="sim-receipt__item"><dt>Anggota</dt><dd>{{ bukti.anggota.nama }} <span class="sim-cell-sub d-inline">· {{ bukti.anggota.kodeAnggota }}</span></dd></div>
                <div class="sim-receipt__item"><dt>Buku</dt><dd>{{ bukti.buku.judul }}</dd></div>
                <div class="sim-receipt__item"><dt>Kode Eksemplar</dt><dd class="sim-code">{{ bukti.kodeEksemplar }}</dd></div>
                <div class="sim-receipt__item"><dt>Tanggal Pinjam</dt><dd>{{ tanggal(bukti.tanggalPinjam, 'panjang') }}</dd></div>
                <div class="sim-receipt__item"><dt>Tanggal Jatuh Tempo</dt><dd>{{ tanggal(bukti.tanggalJatuhTempo, 'panjang') }}</dd></div>
                <div class="sim-receipt__item"><dt>Admin Pemroses</dt><dd>{{ bukti.pemroses.nama }}</dd></div>
                <div class="sim-receipt__item"><dt>Status</dt><dd><StatusBadge type="peminjaman" :status="bukti.status" /></dd></div>
            </dl>
            <div class="sim-receipt__footer">
                <span>Perubahan pada mode pratinjau tidak disimpan setelah halaman dimuat ulang.</span>
            </div>
        </section>

        <section class="sim-card mb-4">
            <div class="sim-card-header">
                <div>
                    <h2 class="sim-card-title">Booking Disetujui</h2>
                    <p class="sim-card-subtitle">Booking yang menunggu realisasi menjadi peminjaman.</p>
                </div>
            </div>
            <BaseTable :columns="kolom" :rows="disetujui" row-key="idBooking" :selected-key="idDipilih" caption="Booking yang menunggu realisasi">
                <template #cell-idBooking="{ value }"><span class="sim-code">{{ value }}</span></template>
                <template #cell-anggota="{ row }">
                    <span class="sim-cell-title">{{ row.anggota.nama }}</span>
                    <span class="sim-cell-sub">{{ row.anggota.kodeAnggota }} · {{ row.anggota.status }}</span>
                </template>
                <template #cell-buku="{ row }"><span class="sim-cell-title">{{ row.buku.judul }}</span></template>
                <template #cell-kodeEksemplar="{ value }"><span class="sim-code">{{ value }}</span></template>
                <template #cell-statusEksemplar="{ row }"><StatusBadge type="eksemplar" :status="row.eksemplar.status" /></template>
                <template #cell-tanggalKeputusan="{ value }"><span class="text-nowrap tabular-nums">{{ tanggal(value) }}</span></template>
                <template #cell-aksi="{ row }">
                    <BaseButton
                        :variant="row.idBooking === idDipilih ? 'primary' : 'secondary'"
                        size="sm"
                        :icon="row.idBooking === idDipilih ? 'bi-check2' : 'bi-hand-index'"
                        @click="pilih(row)"
                    >
                        {{ row.idBooking === idDipilih ? 'Dipilih' : 'Pilih' }}
                    </BaseButton>
                </template>
                <template #empty>
                    <EmptyState compact icon="bi-bookmark-check" title="Tidak ada booking yang menunggu realisasi" description="Booking yang disetujui pada Verifikasi Booking akan tampil di sini." />
                </template>
            </BaseTable>
        </section>

        <form ref="formulir" class="sim-card" novalidate @submit.prevent="simpan">
            <div class="sim-card-header">
                <div>
                    <h2 class="sim-card-title">Formulir Realisasi</h2>
                    <p class="sim-card-subtitle">Periksa keabsahan anggota dan status eksemplar sebelum merealisasikan booking.</p>
                </div>
            </div>

            <div v-if="!dipilih" class="sim-card-body">
                <EmptyState compact icon="bi-hand-index" title="Belum ada booking dipilih" description="Pilih booking pada tabel di atas untuk merealisasikannya menjadi peminjaman." />
            </div>

            <template v-else>
                <div class="sim-card-body">
                    <FormFeedback v-if="galat.umum" class="mb-4" tone="danger" title="Booking tidak dapat direalisasikan" :message="galat.umum" />

                    <div class="sim-form-grid">
                        <ReadonlyField label="ID Booking"><span class="sim-code">{{ dipilih.idBooking }}</span></ReadonlyField>
                        <ReadonlyField label="Status Booking"><StatusBadge type="booking" :status="dipilih.status" /></ReadonlyField>
                        <ReadonlyField label="Anggota">
                            {{ dipilih.anggota.nama }} <span class="sim-cell-sub">· {{ dipilih.anggota.kodeAnggota }}</span>
                        </ReadonlyField>
                        <ReadonlyField label="Status Anggota"><StatusBadge type="anggota" :status="dipilih.anggota.status" /></ReadonlyField>
                        <ReadonlyField label="Buku">{{ dipilih.buku.judul }}</ReadonlyField>
                        <ReadonlyField label="Kode Eksemplar">
                            <span class="sim-code">{{ dipilih.kodeEksemplar }}</span>
                            <StatusBadge type="eksemplar" :status="dipilih.eksemplar.status" />
                        </ReadonlyField>
                        <ReadonlyField label="Tanggal Pinjam" hint="Tanggal transaksi dicatat otomatis.">
                            <i class="bi bi-calendar3 text-secondary" aria-hidden="true"></i>{{ tanggal(tanggalHariIni, 'panjang') }}
                        </ReadonlyField>
                        <BaseInput
                            v-model="tanggalJatuhTempo"
                            label="Tanggal Jatuh Tempo"
                            type="date"
                            :min="tanggalHariIni"
                            required
                            hint="Diisi manual sesuai kebijakan perpustakaan."
                            :error="galat.tanggalJatuhTempo"
                        />
                        <ReadonlyField label="Admin Pemroses">
                            <i class="bi bi-shield-check text-secondary" aria-hidden="true"></i>{{ akun?.nama }}
                        </ReadonlyField>
                    </div>
                </div>
                <div class="sim-card-footer">
                    <div class="sim-form-actions w-100">
                        <BaseButton variant="secondary" @click="batal">Batal</BaseButton>
                        <BaseButton type="submit" icon="bi-bookmark-check" :disabled="Boolean(galatSemua.umum)">Realisasikan Menjadi Peminjaman</BaseButton>
                    </div>
                </div>
            </template>
        </form>

        <ConfirmationModal
            v-model:show="konfirmasi"
            :title="`Realisasikan booking ${dipilih?.idBooking ?? ''}?`"
            :message="dipilih ? `Peminjaman Aktif akan dibuat untuk ${dipilih.anggota.nama}: “${dipilih.buku.judul}” (${dipilih.kodeEksemplar}), jatuh tempo ${tanggal(tanggalJatuhTempo, 'panjang')}. Booking menjadi Direalisasikan dan eksemplar menjadi Dipinjam.` : null"
            icon="bi-bookmark-check"
            confirm-label="Realisasikan"
            @confirm="terapkan"
        />
    </AdminLayout>
</template>
