<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

import BaseButton from '@/Components/BaseButton.vue';
import BaseInput from '@/Components/BaseInput.vue';
import BaseSelect from '@/Components/BaseSelect.vue';
import BaseTable from '@/Components/BaseTable.vue';
import BrandMark from '@/Components/BrandMark.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useDataPerpustakaan } from '@/composables/useDataPerpustakaan.js';
import { useDemo } from '@/composables/useDemo.js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatTanggal } from '@/utils/format.js';
import { JENIS_LAPORAN, buatLaporan, jenisLaporan, mendukungPeriode, validasiFilterLaporan } from '@/utils/laporan.js';

const { data, tanggalHariIni } = useDataPerpustakaan();
const { akun, rute } = useDemo();
const page = usePage();

const filter = reactive({ jenis: '', awal: '', akhir: '' });
const galat = ref({});
/** Filter yang sedang ditampilkan (diterapkan lewat "Tampilkan Laporan"). */
const diterapkan = ref(null);

const periodeAktif = computed(() => mendukungPeriode(filter.jenis));

// Periode tidak berlaku untuk laporan tanpa kolom tanggal.
watch(
    () => filter.jenis,
    () => {
        if (!periodeAktif.value) {
            filter.awal = '';
            filter.akhir = '';
        }
        galat.value = {};
    },
);

/**
 * Phase 8B: data laporan dimuat SERVER sesuai filter (periode = batas hari WIB
 * yang dikonversi ke UTC di Laravel). "Tampilkan Laporan" meminta ulang halaman
 * dengan query string; data yang tiba sudah tersaring dan diotorisasi.
 */
function tampilkan() {
    galat.value = validasiFilterLaporan(filter);
    if (Object.keys(galat.value).length > 0) return;

    const query = { jenis: filter.jenis };
    if (periodeAktif.value && filter.awal) query.awal = filter.awal;
    if (periodeAktif.value && filter.akhir) query.akhir = filter.akhir;

    router.get(rute('laporan'), query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onSuccess: () => terapkanDariServer(),
    });
}

/** Filter yang diterapkan = filter yang divalidasi dan dipakai server (prop filterLaporan). */
function terapkanDariServer() {
    const f = page.props.filterLaporan;
    diterapkan.value = f ? { jenis: f.jenis, awal: f.awal ?? '', akhir: f.akhir ?? '' } : null;
}

// Filter awal dari URL, mis. ?jenis=peminjaman&awal=2026-11-01&akhir=2026-11-07 (sudah dimuat server).
const query = new URLSearchParams(page.url.split('?')[1] ?? '');
if (jenisLaporan(query.get('jenis'))) {
    filter.jenis = query.get('jenis');
    if (mendukungPeriode(filter.jenis)) {
        filter.awal = query.get('awal') ?? '';
        filter.akhir = query.get('akhir') ?? '';
    }
    galat.value = validasiFilterLaporan(filter);
    terapkanDariServer();
}

const laporan = computed(() => (diterapkan.value ? buatLaporan(data, diterapkan.value) : null));
const teksPeriode = computed(() => {
    if (!laporan.value?.kolomTanggal) return 'Seluruh data';
    const { awal, akhir } = diterapkan.value;
    if (!awal && !akhir) return 'Seluruh periode';
    if (awal && akhir) return `${formatTanggal(awal, 'panjang')} – ${formatTanggal(akhir, 'panjang')}`;

    return awal ? `Sejak ${formatTanggal(awal, 'panjang')}` : `Sampai ${formatTanggal(akhir, 'panjang')}`;
});

function cetak() {
    window.print();
}

const opsiJenis = JENIS_LAPORAN.map((j) => ({ value: j.value, label: j.label }));
const tanggal = (nilai) => formatTanggal(nilai, 'pendek');
</script>

<template>
    <AdminLayout>
        <Head title="Laporan" />

        <PageHeader />

        <form class="sim-card mb-4 no-print" novalidate @submit.prevent="tampilkan">
            <div class="sim-card-header">
                <div>
                    <h2 class="sim-card-title">Filter Laporan</h2>
                    <p class="sim-card-subtitle">Pilih jenis laporan dan periode, lalu tampilkan pratinjau sebelum dicetak.</p>
                </div>
            </div>
            <div class="sim-card-body">
                <div class="sim-filter-grid">
                    <BaseSelect v-model="filter.jenis" label="Jenis Laporan" placeholder="Pilih jenis laporan" :options="opsiJenis" required :error="galat.jenis" />
                    <BaseInput
                        v-model="filter.awal"
                        label="Periode Awal"
                        type="date"
                        :max="tanggalHariIni"
                        :disabled="!periodeAktif"
                        :hint="filter.jenis && !periodeAktif ? 'Tidak berlaku untuk jenis laporan ini.' : jenisLaporan(filter.jenis)?.kolomTanggal ? `Berdasarkan ${jenisLaporan(filter.jenis).kolomTanggal}.` : null"
                    />
                    <BaseInput v-model="filter.akhir" label="Periode Akhir" type="date" :max="tanggalHariIni" :disabled="!periodeAktif" :error="galat.akhir" />
                    <div class="sim-filter-grid__actions">
                        <BaseButton type="submit" icon="bi-funnel">Tampilkan Laporan</BaseButton>
                        <BaseButton variant="outline-primary" icon="bi-printer" :disabled="!laporan" @click="cetak">Cetak Laporan</BaseButton>
                    </div>
                </div>
            </div>
        </form>

        <section v-if="!laporan" class="sim-card no-print">
            <EmptyState
                icon="bi-file-earmark-text"
                title="Belum ada laporan ditampilkan"
                description="Pilih Jenis Laporan dan periode bila diperlukan, lalu klik Tampilkan Laporan."
            />
        </section>

        <article v-else class="sim-card sim-report" aria-labelledby="judul-laporan">
            <header class="sim-report__head">
                <div class="sim-report__brand">
                    <BrandMark />
                    <div>
                        <span class="sim-report__org">SIM Perpustakaan</span>
                        <h2 id="judul-laporan" class="sim-report__title">{{ laporan.judul }}</h2>
                    </div>
                </div>
                <dl class="sim-report__meta">
                    <dt>Periode</dt>
                    <dd>{{ teksPeriode }}<template v-if="laporan.kolomTanggal && (diterapkan.awal || diterapkan.akhir)"> ({{ laporan.kolomTanggal }})</template></dd>
                    <dt>Tanggal laporan</dt>
                    <dd>{{ formatTanggal(tanggalHariIni, 'panjang') }}</dd>
                    <dt>Disusun oleh</dt>
                    <dd>{{ akun?.nama }} (Admin)</dd>
                </dl>
            </header>

            <div class="sim-report__summary">
                <span v-for="r in laporan.ringkasan" :key="r.label" class="sim-report__chip">
                    {{ r.label }} <strong>{{ r.nilai }}</strong>
                </span>
            </div>

            <BaseTable :columns="laporan.kolom" :rows="laporan.baris" row-key="no" :hoverable="false" :caption="laporan.judul">
                <template #cell-no="{ value }"><span class="tabular-nums text-secondary">{{ value }}</span></template>
                <template #cell-kodeBuku="{ value }"><span class="sim-code">{{ value }}</span></template>
                <template #cell-kodeAnggota="{ value }"><span class="sim-code">{{ value }}</span></template>
                <template #cell-idPeminjaman="{ value }"><span class="sim-code">{{ value }}</span></template>
                <template #cell-idPengembalian="{ value }"><span class="sim-code">{{ value }}</span></template>
                <template #cell-kodeEksemplar="{ value }"><span class="sim-code">{{ value }}</span></template>
                <template #cell-judul="{ value }"><span class="sim-cell-title">{{ value }}</span></template>
                <template #cell-penulis="{ value }"><span class="text-nowrap">{{ value }}</span></template>
                <template #cell-penerbit="{ value }"><span class="text-nowrap">{{ value }}</span></template>
                <template #cell-anggota="{ row }">
                    <span class="sim-cell-title text-nowrap">{{ row.anggota.nama }}</span>
                    <span class="sim-cell-sub">{{ row.anggota.kodeAnggota }}</span>
                </template>
                <template #cell-buku="{ row }"><span class="sim-cell-title">{{ row.buku.judul }}</span></template>
                <template #cell-tanggalPinjam="{ value }"><span class="text-nowrap tabular-nums">{{ tanggal(value) }}</span></template>
                <template #cell-tanggalJatuhTempo="{ value }"><span class="text-nowrap tabular-nums">{{ tanggal(value) }}</span></template>
                <template #cell-tanggalKembali="{ value }"><span class="text-nowrap tabular-nums">{{ tanggal(value) }}</span></template>
                <template #cell-pemroses="{ row }"><span class="text-nowrap">{{ row.pemroses?.nama }}</span></template>
                <template #cell-status="{ row, value }">
                    <StatusBadge :type="laporan.jenis === 'anggota' ? 'anggota' : 'peminjaman'" :status="value" />
                </template>
                <template #empty>
                    <EmptyState compact icon="bi-calendar-x" title="Tidak ada data pada periode ini" description="Ubah Periode Awal atau Periode Akhir, lalu tampilkan ulang laporan." />
                </template>
            </BaseTable>

            <footer class="sim-report__footnote">
                {{ laporan.baris.length }} baris · Laporan disusun dari data perpustakaan pada {{ formatTanggal(tanggalHariIni, 'lengkap') }}.
            </footer>
        </article>
    </AdminLayout>
</template>
