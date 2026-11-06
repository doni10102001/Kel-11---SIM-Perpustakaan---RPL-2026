<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, nextTick, reactive, ref, watch } from 'vue';

import BaseButton from '@/Components/BaseButton.vue';
import BaseInput from '@/Components/BaseInput.vue';
import BaseSelect from '@/Components/BaseSelect.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormFeedback from '@/Components/FormFeedback.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ReadonlyField from '@/Components/ReadonlyField.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { GalatValidasi, useDataPerpustakaan } from '@/composables/useDataPerpustakaan.js';
import { useDemo } from '@/composables/useDemo.js';
import { cariAnggota, cariBuku, detailPeminjaman, eksemplarBuku, ketersediaanBuku } from '@/data/mock/index.js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatTanggal } from '@/utils/format.js';
import { validasiPeminjaman } from '@/utils/validasiTransaksi.js';

const { data, tanggalHariIni, catatPeminjaman } = useDataPerpustakaan();
const { akun, rute } = useDemo();

const form = reactive({ kodeAnggota: '', kodeBuku: '', kodeEksemplar: '', tanggalJatuhTempo: '' });
const disentuh = reactive({ kodeAnggota: false, kodeBuku: false, kodeEksemplar: false, tanggalJatuhTempo: false });
const sudahDikirim = ref(false);
const galatUmum = ref(null);
const konfirmasi = ref(false);
const bukti = ref(null);

// Eksemplar bergantung pada buku yang dipilih.
watch(
    () => form.kodeBuku,
    () => {
        form.kodeEksemplar = '';
    },
);

const anggota = computed(() => (form.kodeAnggota ? cariAnggota(data, form.kodeAnggota) : null));
const buku = computed(() => (form.kodeBuku ? cariBuku(data, form.kodeBuku) : null));
const ketersediaan = computed(() => (buku.value ? ketersediaanBuku(data, buku.value.kodeBuku) : null));
const eksemplar = computed(() => data.eksemplar.find((e) => e.kodeEksemplar === form.kodeEksemplar) ?? null);
const peminjamanAktifAnggota = computed(() =>
    anggota.value ? data.peminjaman.filter((p) => p.kodeAnggota === anggota.value.kodeAnggota && p.status === 'Aktif').length : 0,
);
// Booking Diajukan tidak mengunci eksemplar (hanya Disetujui → Dipesan), tetapi perlu diketahui Admin.
const bookingDiajukan = computed(() =>
    form.kodeEksemplar ? data.booking.find((b) => b.kodeEksemplar === form.kodeEksemplar && b.status === 'Diajukan') ?? null : null,
);

const opsiAnggota = computed(() =>
    [...data.anggota]
        .sort((a, b) => a.kodeAnggota.localeCompare(b.kodeAnggota))
        .map((a) => ({ value: a.kodeAnggota, label: `${a.kodeAnggota} · ${a.nama}${a.status === 'Nonaktif' ? ' (Nonaktif)' : ''}` })),
);
const opsiBuku = computed(() =>
    [...data.buku]
        .sort((a, b) => a.kodeBuku.localeCompare(b.kodeBuku))
        .map((b) => ({ value: b.kodeBuku, label: `${b.kodeBuku} · ${b.judul} (${ketersediaanBuku(data, b.kodeBuku).Tersedia} tersedia)` })),
);
const opsiEksemplar = computed(() =>
    buku.value
        ? [...eksemplarBuku(data, buku.value.kodeBuku)]
              .sort((a, b) => a.kodeEksemplar.localeCompare(b.kodeEksemplar))
              .map((e) => ({ value: e.kodeEksemplar, label: `${e.kodeEksemplar} — ${e.status}`, disabled: e.status !== 'Tersedia' }))
        : [],
);

const galatSemua = computed(() => validasiPeminjaman(form, data, { tanggalPinjam: tanggalHariIni }));
// Pesan tampil untuk field yang sudah disentuh, atau semuanya setelah percobaan simpan.
const galat = computed(() =>
    Object.fromEntries(Object.entries(galatSemua.value).filter(([k]) => sudahDikirim.value || disentuh[k])),
);
const tanpaEksemplarTersedia = computed(() => ketersediaan.value && ketersediaan.value.Tersedia === 0);

async function simpan() {
    sudahDikirim.value = true;
    galatUmum.value = null;

    if (Object.keys(galatSemua.value).length > 0) {
        await nextTick();
        document.querySelector('form [aria-invalid="true"]')?.focus();

        return;
    }

    konfirmasi.value = true;
}

function terapkan() {
    try {
        const p = catatPeminjaman(form);
        bukti.value = detailPeminjaman(data, data.peminjaman.find((x) => x.idPeminjaman === p.idPeminjaman));
        konfirmasi.value = false;
        aturUlang();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (error) {
        konfirmasi.value = false;
        if (error instanceof GalatValidasi) galatUmum.value = Object.values(error.galat)[0];
        else throw error;
    }
}

function aturUlang() {
    Object.assign(form, { kodeAnggota: '', kodeBuku: '', kodeEksemplar: '', tanggalJatuhTempo: '' });
    Object.keys(disentuh).forEach((k) => (disentuh[k] = false));
    sudahDikirim.value = false;
    galatUmum.value = null;
}

const tanggalPinjamTeks = formatTanggal(tanggalHariIni, 'panjang');
const ringkasKonfirmasi = computed(() =>
    anggota.value && buku.value && eksemplar.value
        ? `${anggota.value.nama} (${anggota.value.kodeAnggota}) meminjam “${buku.value.judul}” eksemplar ${eksemplar.value.kodeEksemplar}, jatuh tempo ${formatTanggal(form.tanggalJatuhTempo, 'panjang')}.`
        : '',
);
</script>

<template>
    <AdminLayout>
        <Head title="Peminjaman Buku" />

        <PageHeader />

        <section v-if="bukti" class="sim-receipt mb-4" aria-labelledby="judul-bukti" role="status">
            <div class="sim-receipt__header">
                <span class="sim-receipt__icon" aria-hidden="true"><i class="bi bi-check2-circle"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <h2 id="judul-bukti" class="sim-receipt__title">Bukti Peminjaman {{ bukti.idPeminjaman }}</h2>
                    <p class="sim-receipt__subtitle">Peminjaman berhasil dicatat. Eksemplar {{ bukti.kodeEksemplar }} kini berstatus Dipinjam.</p>
                </div>
                <BaseButton variant="ghost" size="sm" icon="bi-x-lg" icon-only label="Tutup bukti peminjaman" @click="bukti = null" />
            </div>
            <dl class="sim-receipt__body mb-0">
                <div class="sim-receipt__item"><dt>ID Peminjaman</dt><dd class="sim-code">{{ bukti.idPeminjaman }}</dd></div>
                <div class="sim-receipt__item"><dt>Anggota</dt><dd>{{ bukti.anggota.nama }} <span class="sim-cell-sub d-inline">· {{ bukti.anggota.kodeAnggota }}</span></dd></div>
                <div class="sim-receipt__item"><dt>Buku</dt><dd>{{ bukti.buku.judul }}</dd></div>
                <div class="sim-receipt__item"><dt>Kode Eksemplar</dt><dd class="sim-code">{{ bukti.kodeEksemplar }}</dd></div>
                <div class="sim-receipt__item"><dt>Tanggal Pinjam</dt><dd>{{ formatTanggal(bukti.tanggalPinjam, 'panjang') }}</dd></div>
                <div class="sim-receipt__item"><dt>Tanggal Jatuh Tempo</dt><dd>{{ formatTanggal(bukti.tanggalJatuhTempo, 'panjang') }}</dd></div>
                <div class="sim-receipt__item"><dt>Admin Pemroses</dt><dd>{{ bukti.pemroses.nama }}</dd></div>
                <div class="sim-receipt__item"><dt>Status</dt><dd><StatusBadge type="peminjaman" :status="bukti.status" /></dd></div>
            </dl>
            <div class="sim-receipt__footer">
                <span>Perubahan pada mode pratinjau tidak disimpan setelah halaman dimuat ulang.</span>
                <BaseButton variant="secondary" size="sm" icon-end="bi-arrow-right" :href="rute('pengembalian')">Lihat di Pengembalian Buku</BaseButton>
            </div>
        </section>

        <form class="sim-form-layout" novalidate @submit.prevent="simpan">
            <section class="sim-card">
                <div class="sim-card-header">
                    <div>
                        <h2 class="sim-card-title">Formulir Peminjaman</h2>
                        <p class="sim-card-subtitle">Field bertanda <span class="sim-required">*</span> wajib diisi.</p>
                    </div>
                </div>

                <div class="sim-card-body">
                    <FormFeedback v-if="galatUmum" class="mb-4" tone="danger" title="Transaksi ditolak" :message="galatUmum" />
                    <FormFeedback
                        v-else-if="sudahDikirim && Object.keys(galat).length"
                        class="mb-4"
                        tone="danger"
                        title="Peminjaman belum dapat disimpan"
                        message="Periksa kembali isian yang ditandai."
                    />

                    <div class="sim-form-grid">
                        <BaseSelect
                            v-model="form.kodeAnggota"
                            label="Anggota"
                            placeholder="Pilih anggota"
                            :options="opsiAnggota"
                            required
                            :error="galat.kodeAnggota"
                            @change="disentuh.kodeAnggota = true"
                        />
                        <ReadonlyField label="Status Anggota">
                            <StatusBadge v-if="anggota" type="anggota" :status="anggota.status" />
                            <span v-else class="text-secondary">—</span>
                        </ReadonlyField>

                        <BaseSelect
                            v-model="form.kodeBuku"
                            label="Buku"
                            placeholder="Pilih buku"
                            :options="opsiBuku"
                            required
                            :error="galat.kodeBuku"
                            @change="disentuh.kodeBuku = true"
                        />
                        <BaseSelect
                            v-model="form.kodeEksemplar"
                            label="Kode Eksemplar"
                            :placeholder="buku ? (tanpaEksemplarTersedia ? 'Tidak ada eksemplar Tersedia' : 'Pilih eksemplar') : 'Pilih buku terlebih dahulu'"
                            :options="opsiEksemplar"
                            :disabled="!buku"
                            required
                            :hint="buku && !galat.kodeEksemplar ? 'Hanya eksemplar berstatus Tersedia yang dapat dipilih.' : null"
                            :error="galat.kodeEksemplar ?? (tanpaEksemplarTersedia ? galatSemua.kodeEksemplar : null)"
                            @change="disentuh.kodeEksemplar = true"
                        />

                        <ReadonlyField label="Tanggal Pinjam" hint="Tanggal transaksi dicatat otomatis.">
                            <i class="bi bi-calendar3 text-secondary" aria-hidden="true"></i>{{ tanggalPinjamTeks }}
                        </ReadonlyField>
                        <BaseInput
                            v-model="form.tanggalJatuhTempo"
                            label="Tanggal Jatuh Tempo"
                            type="date"
                            :min="tanggalHariIni"
                            required
                            hint="Diisi manual sesuai kebijakan perpustakaan."
                            :error="galat.tanggalJatuhTempo"
                            @change="disentuh.tanggalJatuhTempo = true"
                        />

                        <ReadonlyField label="Admin Pemroses">
                            <i class="bi bi-shield-check text-secondary" aria-hidden="true"></i>{{ akun?.nama }}
                        </ReadonlyField>
                    </div>

                    <FormFeedback
                        v-if="bookingDiajukan"
                        class="mt-4"
                        tone="info"
                        title="Eksemplar memiliki pengajuan booking"
                        :message="`Booking ${bookingDiajukan.idBooking} untuk eksemplar ini masih Diajukan dan belum mengunci eksemplar. Peminjaman tetap dapat dicatat; pengajuan tersebut perlu diputuskan pada Verifikasi Booking.`"
                    />
                </div>

                <div class="sim-card-footer">
                    <div class="sim-form-actions w-100">
                        <BaseButton variant="secondary" @click="aturUlang">Batal</BaseButton>
                        <BaseButton type="submit" icon="bi-journal-arrow-up">Simpan Peminjaman</BaseButton>
                    </div>
                </div>
            </section>

            <aside class="sim-card">
                <div class="sim-card-header">
                    <h2 class="sim-card-title">Ringkasan Pilihan</h2>
                </div>
                <div class="sim-card-body">
                    <EmptyState
                        v-if="!anggota && !buku"
                        compact
                        icon="bi-ui-checks"
                        title="Belum ada pilihan"
                        description="Pilih anggota dan buku untuk melihat keabsahan anggota serta ketersediaan eksemplar."
                    />
                    <template v-else>
                        <section v-if="anggota" class="mb-4">
                            <p class="sim-subheading">Anggota</p>
                            <dl class="sim-detail-list">
                                <dt>Nama</dt>
                                <dd>{{ anggota.nama }}</dd>
                                <dt>Kode Anggota</dt>
                                <dd class="sim-code">{{ anggota.kodeAnggota }}</dd>
                                <dt>Status</dt>
                                <dd><StatusBadge type="anggota" :status="anggota.status" /></dd>
                                <dt>Peminjaman Aktif</dt>
                                <dd class="tabular-nums">{{ peminjamanAktifAnggota }}</dd>
                            </dl>
                        </section>
                        <section v-if="buku">
                            <p class="sim-subheading">Buku</p>
                            <dl class="sim-detail-list">
                                <dt>Judul</dt>
                                <dd>{{ buku.judul }}</dd>
                                <dt>Penulis</dt>
                                <dd>{{ buku.penulis }}</dd>
                                <dt>Eksemplar</dt>
                                <dd class="tabular-nums">{{ ketersediaan.total }}</dd>
                                <dt><StatusBadge type="eksemplar" status="Tersedia" /></dt>
                                <dd class="tabular-nums">{{ ketersediaan.Tersedia }}</dd>
                                <dt><StatusBadge type="eksemplar" status="Dipesan" /></dt>
                                <dd class="tabular-nums">{{ ketersediaan.Dipesan }}</dd>
                                <dt><StatusBadge type="eksemplar" status="Dipinjam" /></dt>
                                <dd class="tabular-nums">{{ ketersediaan.Dipinjam }}</dd>
                            </dl>
                        </section>
                    </template>
                </div>
            </aside>
        </form>

        <ConfirmationModal
            v-model:show="konfirmasi"
            title="Simpan peminjaman?"
            :message="ringkasKonfirmasi"
            icon="bi-journal-arrow-up"
            confirm-label="Simpan Peminjaman"
            @confirm="terapkan"
        />
    </AdminLayout>
</template>
