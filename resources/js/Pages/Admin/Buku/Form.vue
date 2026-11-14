<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, reactive, ref } from 'vue';

import BaseButton from '@/Components/BaseButton.vue';
import BaseInput from '@/Components/BaseInput.vue';
import BaseSelect from '@/Components/BaseSelect.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormFeedback from '@/Components/FormFeedback.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { GalatValidasi, useDataPerpustakaan } from '@/composables/useDataPerpustakaan.js';
import { useDemo } from '@/composables/useDemo.js';
import { eksemplarBuku } from '@/utils/olahData.js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { kodeBerikutnya } from '@/utils/pencarian.js';
import { normalKode, validasiBuku, validasiKodeEksemplar } from '@/utils/validasiMaster.js';

const { data, tahunMaks, tambahBuku, ubahBuku, setPesan } = useDataPerpustakaan();
const { rute, parameter, halaman } = useDemo();
const page = usePage();

const kodeAsal = parameter.value.kodeBuku ?? null;
const mode = kodeAsal ? 'ubah' : 'tambah';
const buku = mode === 'ubah' ? data.buku.find((b) => b.kodeBuku === kodeAsal) : null;
const tidakDitemukan = mode === 'ubah' && !buku;

const form = reactive({
    kodeBuku: buku?.kodeBuku ?? '',
    judul: buku?.judul ?? '',
    penulis: buku?.penulis ?? '',
    penerbit: buku?.penerbit ?? '',
    tahunTerbit: buku ? String(buku.tahunTerbit) : '',
    kategori: buku?.kategori ?? '',
});

const galat = ref({});
const sudahDikirim = ref(false);

// ---- Eksemplar -------------------------------------------------------------
const eksemplarLama = computed(() =>
    buku ? [...eksemplarBuku(data, buku.kodeBuku)].sort((a, b) => a.kodeEksemplar.localeCompare(b.kodeEksemplar)) : [],
);
const eksemplarBaru = ref([]);
const daftarEksemplar = computed(() => [
    ...eksemplarLama.value.map((e) => ({ ...e, baru: false })),
    ...eksemplarBaru.value.map((e) => ({ ...e, baru: true })),
]);

const isianEksemplar = reactive({ kodeEksemplar: '', status: 'Tersedia' });
const galatEksemplar = ref(null);
const inputEksemplar = ref(null);
const bagianEksemplar = ref(null);

// Status Dipesan/Dipinjam hanya berubah melalui transaksi booking/peminjaman.
const opsiStatusEksemplar = [
    { value: 'Tersedia', label: 'Tersedia' },
    { value: 'Dipesan', label: 'Dipesan — melalui booking', disabled: true },
    { value: 'Dipinjam', label: 'Dipinjam — melalui peminjaman', disabled: true },
];

const contohKodeEksemplar = computed(() => kodeBerikutnya([...data.eksemplar, ...eksemplarBaru.value], 'kodeEksemplar', 'EK'));
const contohKodeBuku = kodeBerikutnya(data.buku, 'kodeBuku', 'BK');
const daftarKategori = computed(() => [...new Set(data.buku.map((b) => b.kategori))].sort());

function tambahEksemplar() {
    galatEksemplar.value = validasiKodeEksemplar(isianEksemplar.kodeEksemplar, data, eksemplarBaru.value);

    if (galatEksemplar.value) {
        inputEksemplar.value?.focus();

        return;
    }

    eksemplarBaru.value.push({ kodeEksemplar: normalKode(isianEksemplar.kodeEksemplar), status: 'Tersedia' });
    isianEksemplar.kodeEksemplar = '';
    if (sudahDikirim.value) validasi();
    inputEksemplar.value?.focus();
}

function lepasEksemplar(kode) {
    eksemplarBaru.value = eksemplarBaru.value.filter((e) => e.kodeEksemplar !== kode);
    if (sudahDikirim.value) validasi();
}

// ---- Simpan ----------------------------------------------------------------
function validasi() {
    galat.value = validasiBuku(form, data, { mode, kodeAsal, eksemplarBaru: eksemplarBaru.value, tahunMaks });

    return Object.keys(galat.value).length === 0;
}

function perbaruiGalat(event) {
    // Isian tambah-eksemplar divalidasi terpisah.
    if (event?.target?.closest?.('.sim-add-row')) return;
    if (sudahDikirim.value) validasi();
}

async function simpan() {
    sudahDikirim.value = true;

    if (!validasi()) {
        await nextTick();
        document.querySelector('form [aria-invalid="true"]')?.focus();
        if (!document.querySelector('form [aria-invalid="true"]') && galat.value.eksemplar) {
            bagianEksemplar.value?.scrollIntoView({ block: 'center' });
            inputEksemplar.value?.focus();
        }

        return;
    }

    const jumlahBaru = eksemplarBaru.value.length;
    let hasil;
    try {
        hasil = mode === 'tambah' ? tambahBuku(form, eksemplarBaru.value) : ubahBuku(kodeAsal, form, eksemplarBaru.value);
    } catch (e) {
        if (!(e instanceof GalatValidasi)) throw e;
        galat.value = e.galat;

        return;
    }

    setPesan({
        tone: 'success',
        title: mode === 'tambah' ? 'Buku berhasil ditambahkan' : 'Perubahan data buku diterapkan',
        message: `${hasil.kodeBuku} · ${hasil.judul}${jumlahBaru ? ` · ${jumlahBaru} eksemplar baru` : ''}. Perubahan pada mode pratinjau tidak disimpan setelah halaman dimuat ulang.`,
    });
    router.visit(rute('buku.index'));
}

// Dibuka dari aksi "Tambah Eksemplar": langsung arahkan ke isian eksemplar.
onMounted(async () => {
    const query = new URLSearchParams(page.url.split('?')[1] ?? '');

    if (!tidakDitemukan && query.get('eksemplar') === 'tambah') {
        await nextTick();
        bagianEksemplar.value?.scrollIntoView({ block: 'start' });
        inputEksemplar.value?.focus();
    }
});

const ringkasan = computed(() => ({
    total: daftarEksemplar.value.length,
    Tersedia: daftarEksemplar.value.filter((e) => e.status === 'Tersedia').length,
    Dipesan: daftarEksemplar.value.filter((e) => e.status === 'Dipesan').length,
    Dipinjam: daftarEksemplar.value.filter((e) => e.status === 'Dipinjam').length,
}));
</script>

<template>
    <AdminLayout>
        <Head :title="halaman?.judul" />

        <PageHeader :description="mode === 'ubah' && buku ? `Perbarui data buku “${buku.judul}” dan tambahkan eksemplar.` : undefined" />

        <section v-if="tidakDitemukan" class="sim-card">
            <EmptyState icon="bi-journal-x" title="Buku tidak ditemukan" :description="`Kode Buku ${kodeAsal} tidak terdaftar.`">
                <template #actions>
                    <BaseButton variant="secondary" icon="bi-arrow-left" :href="rute('buku.index')">Kembali ke Data Buku</BaseButton>
                </template>
            </EmptyState>
        </section>

        <form v-else class="sim-form-layout" novalidate @submit.prevent="simpan" @input="perbaruiGalat" @change="perbaruiGalat">
            <div class="sim-form-stack">
                <FormFeedback
                    v-if="sudahDikirim && Object.keys(galat).length"
                    tone="danger"
                    :title="galat.umum ? 'Data buku tidak disimpan' : 'Data buku belum valid'"
                    :message="galat.umum ?? 'Periksa kembali isian yang ditandai, lalu simpan ulang.'"
                />

                <section class="sim-card">
                    <div class="sim-card-header">
                        <div>
                            <h2 class="sim-card-title">Data Buku</h2>
                            <p class="sim-card-subtitle">Field bertanda <span class="sim-required">*</span> wajib diisi.</p>
                        </div>
                    </div>
                    <div class="sim-card-body">
                        <div class="sim-form-grid">
                            <BaseInput
                                v-model="form.kodeBuku"
                                label="Kode Buku"
                                :placeholder="mode === 'tambah' ? `Contoh: ${contohKodeBuku}` : null"
                                :readonly="mode === 'ubah'"
                                :hint="mode === 'ubah' ? 'Kode Buku tidak dapat diubah karena dipakai oleh eksemplar.' : 'Harus unik; tidak dapat diubah setelah disimpan.'"
                                required
                                autocomplete="off"
                                :error="galat.kodeBuku"
                            />
                            <BaseInput
                                v-model="form.kategori"
                                label="Kategori"
                                placeholder="Contoh: Informatika"
                                list="daftar-kategori"
                                required
                                autocomplete="off"
                                :error="galat.kategori"
                            />
                            <datalist id="daftar-kategori">
                                <option v-for="k in daftarKategori" :key="k" :value="k"></option>
                            </datalist>
                            <div class="is-full">
                                <BaseInput v-model="form.judul" label="Judul" placeholder="Judul buku" required autocomplete="off" :error="galat.judul" />
                            </div>
                            <BaseInput v-model="form.penulis" label="Penulis" placeholder="Nama penulis" required autocomplete="off" :error="galat.penulis" />
                            <BaseInput v-model="form.penerbit" label="Penerbit" placeholder="Nama penerbit" required autocomplete="off" :error="galat.penerbit" />
                            <BaseInput
                                v-model="form.tahunTerbit"
                                label="Tahun Terbit"
                                :placeholder="`Contoh: ${tahunMaks}`"
                                inputmode="numeric"
                                maxlength="4"
                                required
                                autocomplete="off"
                                :error="galat.tahunTerbit"
                            />
                        </div>
                    </div>
                </section>

                <section ref="bagianEksemplar" class="sim-card" aria-labelledby="judul-eksemplar">
                    <div class="sim-card-header">
                        <div>
                            <h2 id="judul-eksemplar" class="sim-card-title">Eksemplar</h2>
                            <p class="sim-card-subtitle">Salinan fisik buku. Setiap eksemplar memiliki kode unik dan status tersendiri.</p>
                        </div>
                    </div>
                    <div class="sim-card-body d-grid gap-4">
                        <div class="sim-add-row">
                            <BaseInput
                                ref="inputEksemplar"
                                v-model="isianEksemplar.kodeEksemplar"
                                label="Kode Eksemplar"
                                :placeholder="`Contoh: ${contohKodeEksemplar}`"
                                autocomplete="off"
                                :error="galatEksemplar"
                                @keydown.enter.prevent="tambahEksemplar"
                                @input="galatEksemplar = null"
                            />
                            <BaseSelect v-model="isianEksemplar.status" label="Status" :options="opsiStatusEksemplar" />
                            <div class="sim-add-row__action">
                                <BaseButton variant="outline-primary" icon="bi-plus-lg" @click="tambahEksemplar">Tambah Eksemplar</BaseButton>
                            </div>
                        </div>

                        <p class="sim-field-hint mt-n2 mb-0">
                            <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                            Eksemplar baru berstatus Tersedia. Status Dipesan dan Dipinjam berubah melalui transaksi booking dan peminjaman.
                        </p>

                        <div>
                            <div class="d-flex align-items-baseline justify-content-between gap-2 mb-2">
                                <h3 class="sim-subheading mb-0">Daftar Eksemplar</h3>
                                <span class="sim-cell-sub">{{ ringkasan.total }} eksemplar</span>
                            </div>

                            <table v-if="daftarEksemplar.length" class="sim-mini-table">
                                <caption class="visually-hidden">Daftar eksemplar</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Kode Eksemplar</th>
                                        <th scope="col">Status</th>
                                        <th scope="col" class="text-end">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="e in daftarEksemplar" :key="e.kodeEksemplar" :class="{ 'is-new': e.baru }">
                                        <td>
                                            <span class="sim-code">{{ e.kodeEksemplar }}</span>{{ ' ' }}<span v-if="e.baru" class="sim-tag">Baru</span>
                                        </td>
                                        <td><StatusBadge type="eksemplar" :status="e.status" /></td>
                                        <td class="text-end">
                                            <BaseButton
                                                v-if="e.baru"
                                                variant="ghost"
                                                size="sm"
                                                icon="bi-x-lg"
                                                icon-only
                                                :label="`Lepas ${e.kodeEksemplar} dari daftar`"
                                                @click="lepasEksemplar(e.kodeEksemplar)"
                                            />
                                            <span v-else class="sim-cell-sub">—</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <div v-else class="sim-card">
                                <EmptyState compact icon="bi-stack" title="Belum ada eksemplar" description="Tambahkan minimal satu eksemplar untuk buku ini." />
                            </div>

                            <p v-if="galat.eksemplar" class="sim-field-error mb-0" role="alert">
                                <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
                                <span>{{ galat.eksemplar }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="sim-card-footer">
                        <div class="sim-form-actions w-100">
                            <BaseButton variant="secondary" :href="rute('buku.index')">Batal</BaseButton>
                            <BaseButton type="submit" icon="bi-check2">Simpan Buku</BaseButton>
                        </div>
                    </div>
                </section>
            </div>

            <aside class="sim-card">
                <div class="sim-card-header">
                    <h2 class="sim-card-title">Ringkasan Eksemplar</h2>
                </div>
                <div class="sim-card-body">
                    <dl class="sim-detail-list">
                        <dt>Jumlah Eksemplar</dt>
                        <dd class="tabular-nums">{{ ringkasan.total }}</dd>
                        <dt><StatusBadge type="eksemplar" status="Tersedia" /></dt>
                        <dd class="tabular-nums">{{ ringkasan.Tersedia }}</dd>
                        <dt><StatusBadge type="eksemplar" status="Dipesan" /></dt>
                        <dd class="tabular-nums">{{ ringkasan.Dipesan }}</dd>
                        <dt><StatusBadge type="eksemplar" status="Dipinjam" /></dt>
                        <dd class="tabular-nums">{{ ringkasan.Dipinjam }}</dd>
                    </dl>
                    <ul class="sim-aside-list mt-4 pt-4 border-top">
                        <li><i class="bi bi-check2-circle" aria-hidden="true"></i>Kode Buku dan Kode Eksemplar harus unik.</li>
                        <li><i class="bi bi-check2-circle" aria-hidden="true"></i>Setiap buku memiliki minimal satu eksemplar.</li>
                        <li><i class="bi bi-check2-circle" aria-hidden="true"></i>Eksemplar yang sudah tersimpan tidak dihapus agar riwayat transaksi tetap utuh.</li>
                    </ul>
                </div>
            </aside>
        </form>
    </AdminLayout>
</template>
