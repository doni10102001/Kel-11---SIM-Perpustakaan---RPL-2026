<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed, nextTick, reactive, ref, watch } from 'vue';

import BaseButton from '@/Components/BaseButton.vue';
import BaseSelect from '@/Components/BaseSelect.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormFeedback from '@/Components/FormFeedback.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ReadonlyField from '@/Components/ReadonlyField.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { GalatValidasi, useDataPerpustakaan } from '@/composables/useDataPerpustakaan.js';
import { useDemo } from '@/composables/useDemo.js';
import { cariAnggota, cariBuku, detailBooking, eksemplarBuku, ketersediaanBuku } from '@/data/mock/index.js';
import AnggotaLayout from '@/Layouts/AnggotaLayout.vue';
import { formatTanggal } from '@/utils/format.js';
import { validasiAjukanBooking } from '@/utils/validasiTransaksi.js';

const { data, tanggalHariIni, ajukanBooking } = useDataPerpustakaan();
const { akun, rute } = useDemo();
const page = usePage();
const kodeAnggota = akun.value.kodeAnggota;

const anggota = computed(() => cariAnggota(data, kodeAnggota));
const anggotaNonaktif = computed(() => anggota.value?.status !== 'Aktif');

// Buku/eksemplar dapat dipilih dari Cari Buku (?buku=BK-001 atau ?eksemplar=EK-001).
const query = new URLSearchParams(page.url.split('?')[1] ?? '');
const eksemplarAwal = data.eksemplar.find((e) => e.kodeEksemplar === query.get('eksemplar'));
const bukuAwal = eksemplarAwal?.kodeBuku ?? (cariBuku(data, query.get('buku')) ? query.get('buku') : '');

const form = reactive({
    kodeBuku: bukuAwal,
    kodeEksemplar: eksemplarAwal?.status === 'Tersedia' ? eksemplarAwal.kodeEksemplar : '',
});
const sudahDikirim = ref(false);
const konfirmasi = ref(false);
const galatAksi = ref(null);
const bukti = ref(null);

watch(
    () => form.kodeBuku,
    () => {
        form.kodeEksemplar = '';
        galatAksi.value = null;
    },
);

const buku = computed(() => (form.kodeBuku ? cariBuku(data, form.kodeBuku) : null));
const ketersediaan = computed(() => (buku.value ? ketersediaanBuku(data, buku.value.kodeBuku) : null));
const eksemplar = computed(() => data.eksemplar.find((e) => e.kodeEksemplar === form.kodeEksemplar) ?? null);

const opsiBuku = computed(() =>
    [...data.buku]
        .sort((a, b) => a.kodeBuku.localeCompare(b.kodeBuku))
        .map((b) => {
            const k = ketersediaanBuku(data, b.kodeBuku);

            return { value: b.kodeBuku, label: `${b.kodeBuku} · ${b.judul} (${k.Tersedia} tersedia)` };
        }),
);
const opsiEksemplar = computed(() =>
    buku.value
        ? [...eksemplarBuku(data, buku.value.kodeBuku)]
              .sort((a, b) => a.kodeEksemplar.localeCompare(b.kodeEksemplar))
              .map((e) => ({ value: e.kodeEksemplar, label: `${e.kodeEksemplar} — ${e.status}`, disabled: e.status !== 'Tersedia' }))
        : [],
);
const tanpaTersedia = computed(() => ketersediaan.value && ketersediaan.value.Tersedia === 0);

const galat = computed(() => {
    if (!sudahDikirim.value) return {};
    const g = validasiAjukanBooking(form, data, { kodeAnggota });

    return { kodeBuku: form.kodeBuku ? null : 'Pilih buku.', ...g };
});

async function ajukan() {
    sudahDikirim.value = true;
    galatAksi.value = null;
    const g = validasiAjukanBooking(form, data, { kodeAnggota });

    if (!form.kodeBuku || Object.keys(g).length > 0) {
        await nextTick();
        document.querySelector('form [aria-invalid="true"]')?.focus();

        return;
    }

    konfirmasi.value = true;
}

function terapkan() {
    try {
        const b = ajukanBooking(form, { kodeAnggota });
        bukti.value = detailBooking(data, data.booking.find((x) => x.idBooking === b.idBooking));
        konfirmasi.value = false;
        form.kodeBuku = '';
        sudahDikirim.value = false;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (error) {
        konfirmasi.value = false;
        if (!(error instanceof GalatValidasi)) throw error;
        // Alasan penolakan langsung (bukan status Ditolak dari Admin).
        galatAksi.value = Object.values(error.galat)[0];
    }
}

const tanggalPengajuan = formatTanggal(tanggalHariIni, 'panjang');
</script>

<template>
    <AnggotaLayout>
        <Head title="Ajukan Booking" />

        <PageHeader>
            <template #actions>
                <BaseButton variant="secondary" icon="bi-search" :href="rute('cari-buku')">Cari Buku</BaseButton>
            </template>
        </PageHeader>

        <section v-if="bukti" class="sim-receipt mb-4" aria-labelledby="judul-konfirmasi-booking" role="status">
            <div class="sim-receipt__header">
                <span class="sim-receipt__icon" aria-hidden="true"><i class="bi bi-send-check"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <h2 id="judul-konfirmasi-booking" class="sim-receipt__title">Booking {{ bukti.idBooking }} berhasil diajukan</h2>
                    <p class="sim-receipt__subtitle">
                        Booking menunggu verifikasi Admin. Eksemplar akan dipesan untuk Anda setelah booking disetujui.
                    </p>
                </div>
                <BaseButton variant="ghost" size="sm" icon="bi-x-lg" icon-only label="Tutup konfirmasi booking" @click="bukti = null" />
            </div>
            <dl class="sim-receipt__body mb-0">
                <div class="sim-receipt__item"><dt>ID Booking</dt><dd class="sim-code">{{ bukti.idBooking }}</dd></div>
                <div class="sim-receipt__item"><dt>Buku</dt><dd>{{ bukti.buku.judul }}</dd></div>
                <div class="sim-receipt__item"><dt>Kode Eksemplar</dt><dd class="sim-code">{{ bukti.kodeEksemplar }}</dd></div>
                <div class="sim-receipt__item"><dt>Status</dt><dd><StatusBadge type="booking" :status="bukti.status" /></dd></div>
                <div class="sim-receipt__item"><dt>Anggota</dt><dd>{{ bukti.anggota.nama }}</dd></div>
                <div class="sim-receipt__item"><dt>Kode Anggota</dt><dd class="sim-code">{{ bukti.anggota.kodeAnggota }}</dd></div>
                <div class="sim-receipt__item"><dt>Tanggal Pengajuan</dt><dd>{{ formatTanggal(bukti.tanggalBooking, 'panjang') }}</dd></div>
            </dl>
            <div class="sim-receipt__footer">
                <span>Perubahan pada mode pratinjau tidak disimpan setelah halaman dimuat ulang.</span>
                <BaseButton variant="secondary" size="sm" icon-end="bi-arrow-right" :href="rute('booking.status')">Lihat Status Booking</BaseButton>
            </div>
        </section>

        <FormFeedback
            v-if="anggotaNonaktif"
            class="mb-4"
            tone="warning"
            title="Booking tidak dapat diajukan"
            message="Keanggotaan Anda berstatus Nonaktif. Hubungi Admin perpustakaan untuk informasi lebih lanjut."
        />

        <form class="sim-form-layout" novalidate @submit.prevent="ajukan">
            <section class="sim-card">
                <div class="sim-card-header">
                    <div>
                        <h2 class="sim-card-title">Formulir Booking</h2>
                        <p class="sim-card-subtitle">Field bertanda <span class="sim-required">*</span> wajib diisi.</p>
                    </div>
                </div>

                <div class="sim-card-body">
                    <FormFeedback v-if="galatAksi" class="mb-4" tone="danger" title="Booking tidak dapat diajukan" :message="galatAksi" />

                    <div class="sim-form-grid">
                        <BaseSelect
                            v-model="form.kodeBuku"
                            label="Buku"
                            placeholder="Pilih buku"
                            :options="opsiBuku"
                            required
                            :disabled="anggotaNonaktif"
                            :error="galat.kodeBuku"
                        />
                        <BaseSelect
                            v-model="form.kodeEksemplar"
                            label="Kode Eksemplar"
                            :placeholder="buku ? (tanpaTersedia ? 'Tidak ada eksemplar Tersedia' : 'Pilih eksemplar') : 'Pilih buku terlebih dahulu'"
                            :options="opsiEksemplar"
                            :disabled="!buku || anggotaNonaktif"
                            required
                            :hint="buku && !galat.kodeEksemplar ? 'Hanya eksemplar berstatus Tersedia yang dapat dibooking.' : null"
                            :error="galat.kodeEksemplar ?? (tanpaTersedia ? 'Buku ini tidak memiliki eksemplar Tersedia untuk dibooking.' : null)"
                        />
                        <ReadonlyField label="Identitas Anggota">
                            <i class="bi bi-person-vcard text-secondary" aria-hidden="true"></i>{{ anggota?.nama }}
                            <StatusBadge v-if="anggota" type="anggota" :status="anggota.status" />
                        </ReadonlyField>
                        <ReadonlyField label="Kode Anggota"><span class="sim-code">{{ kodeAnggota }}</span></ReadonlyField>
                        <ReadonlyField label="Tanggal Pengajuan" hint="Tanggal pengajuan dicatat otomatis.">
                            <i class="bi bi-calendar3 text-secondary" aria-hidden="true"></i>{{ tanggalPengajuan }}
                        </ReadonlyField>
                    </div>

                    <FormFeedback
                        class="mt-4"
                        tone="info"
                        message="Booking yang diajukan berstatus Diajukan dan menunggu verifikasi Admin. Eksemplar baru dipesan untuk Anda setelah booking disetujui."
                    />
                </div>

                <div class="sim-card-footer">
                    <div class="sim-form-actions w-100">
                        <BaseButton variant="secondary" :href="rute('cari-buku')">Batal</BaseButton>
                        <BaseButton type="submit" icon="bi-bookmark-plus" :disabled="anggotaNonaktif">Ajukan Booking</BaseButton>
                    </div>
                </div>
            </section>

            <aside class="sim-card">
                <div class="sim-card-header">
                    <h2 class="sim-card-title">Detail Buku Dipilih</h2>
                </div>
                <div class="sim-card-body">
                    <EmptyState
                        v-if="!buku"
                        compact
                        icon="bi-book"
                        title="Belum ada buku dipilih"
                        description="Pilih buku pada formulir, atau cari buku terlebih dahulu."
                    />
                    <template v-else>
                        <dl class="sim-detail-list">
                            <dt>Kode Buku</dt>
                            <dd class="sim-code">{{ buku.kodeBuku }}</dd>
                            <dt>Judul</dt>
                            <dd>{{ buku.judul }}</dd>
                            <dt>Penulis</dt>
                            <dd>{{ buku.penulis }}</dd>
                            <dt>Penerbit</dt>
                            <dd>{{ buku.penerbit }}</dd>
                            <dt>Tahun Terbit</dt>
                            <dd class="tabular-nums">{{ buku.tahunTerbit }}</dd>
                            <dt>Kategori</dt>
                            <dd>{{ buku.kategori }}</dd>
                            <dt>Ketersediaan</dt>
                            <dd>{{ ketersediaan.Tersedia }} dari {{ ketersediaan.total }} eksemplar tersedia</dd>
                            <template v-if="eksemplar">
                                <dt>Eksemplar dipilih</dt>
                                <dd>
                                    <span class="sim-code me-2">{{ eksemplar.kodeEksemplar }}</span>{{ ' ' }}<StatusBadge type="eksemplar" :status="eksemplar.status" />
                                </dd>
                            </template>
                        </dl>
                    </template>
                </div>
            </aside>
        </form>

        <ConfirmationModal
            v-model:show="konfirmasi"
            title="Ajukan booking?"
            :message="buku && eksemplar ? `Booking “${buku.judul}” eksemplar ${eksemplar.kodeEksemplar} akan diajukan atas nama ${anggota?.nama} (${kodeAnggota}) dan menunggu verifikasi Admin.` : null"
            icon="bi-bookmark-plus"
            confirm-label="Ajukan Booking"
            @confirm="terapkan"
        />
    </AnggotaLayout>
</template>
