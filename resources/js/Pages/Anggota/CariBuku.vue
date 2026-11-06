<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import BaseButton from '@/Components/BaseButton.vue';
import BaseSelect from '@/Components/BaseSelect.vue';
import BaseTable from '@/Components/BaseTable.vue';
import DetailPanel from '@/Components/DetailPanel.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SearchInput from '@/Components/SearchInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useDataPerpustakaan } from '@/composables/useDataPerpustakaan.js';
import { useDemo } from '@/composables/useDemo.js';
import { eksemplarBuku, ketersediaanBuku } from '@/data/mock/index.js';
import AnggotaLayout from '@/Layouts/AnggotaLayout.vue';
import { filterKatalog } from '@/utils/pencarian.js';

const { data } = useDataPerpustakaan();
const { rute } = useDemo();

const kataKunci = ref('');
const kategori = ref('');

// Ketersediaan dihitung dari status eksemplar saat ini.
const katalog = computed(() =>
    [...data.buku]
        .sort((a, b) => a.kodeBuku.localeCompare(b.kodeBuku))
        .map((b) => ({ ...b, ketersediaan: ketersediaanBuku(data, b.kodeBuku) })),
);
const daftarKategori = computed(() => [...new Set(data.buku.map((b) => b.kategori))].sort());
const hasil = computed(() => filterKatalog(katalog.value, { kataKunci: kataKunci.value, kategori: kategori.value }));
const adaFilter = computed(() => kataKunci.value.trim() !== '' || kategori.value !== '');

function aturUlang() {
    kataKunci.value = '';
    kategori.value = '';
}

const kolom = [
    { key: 'kodeBuku', label: 'Kode Buku', width: '7.5rem' },
    { key: 'judul', label: 'Judul' },
    { key: 'penulis', label: 'Penulis', class: 'text-nowrap' },
    { key: 'kategori', label: 'Kategori', class: 'text-nowrap' },
    { key: 'ketersediaan', label: 'Ketersediaan' },
    { key: 'aksi', label: 'Aksi', align: 'end' },
];

const kodeDipilih = ref(null);
const panel = ref(false);
const dipilih = computed(() => katalog.value.find((b) => b.kodeBuku === kodeDipilih.value) ?? null);
const eksemplarDipilih = computed(() =>
    dipilih.value ? [...eksemplarBuku(data, dipilih.value.kodeBuku)].sort((a, b) => a.kodeEksemplar.localeCompare(b.kodeEksemplar)) : [],
);

function lihat(b) {
    kodeDipilih.value = b.kodeBuku;
    panel.value = true;
}

const urlAjukanBuku = (kodeBuku) => `${rute('booking.ajukan')}?buku=${encodeURIComponent(kodeBuku)}`;
const urlAjukanEksemplar = (kodeEksemplar) => `${rute('booking.ajukan')}?eksemplar=${encodeURIComponent(kodeEksemplar)}`;
</script>

<template>
    <AnggotaLayout>
        <Head title="Cari Buku" />

        <PageHeader />

        <section class="sim-card">
            <div class="sim-toolbar">
                <div class="sim-toolbar__search">
                    <SearchInput v-model="kataKunci" label="Cari buku" placeholder="Cari judul, kode, atau kategori" />
                </div>
                <div class="sim-toolbar__filter">
                    <BaseSelect v-model="kategori" aria-label="Filter kategori" placeholder="Semua kategori" placeholder-selectable :options="daftarKategori" />
                </div>
                <BaseButton v-if="adaFilter" variant="ghost" size="sm" icon="bi-arrow-counterclockwise" @click="aturUlang">Atur ulang</BaseButton>
                <p class="sim-toolbar__meta mb-0" aria-live="polite">Menampilkan {{ hasil.length }} dari {{ katalog.length }} buku</p>
            </div>

            <BaseTable :columns="kolom" :rows="hasil" row-key="kodeBuku" caption="Katalog buku">
                <template #cell-kodeBuku="{ value }"><span class="sim-code">{{ value }}</span></template>
                <template #cell-judul="{ value }"><span class="sim-cell-title">{{ value }}</span></template>
                <template #cell-ketersediaan="{ row }">
                    <span v-if="row.ketersediaan.Tersedia > 0" class="sim-avail is-yes">
                        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>Tersedia
                    </span>
                    <span v-else class="sim-avail is-no">
                        <i class="bi bi-slash-circle" aria-hidden="true"></i>Tidak tersedia
                    </span>
                    <span class="sim-avail__note">{{ row.ketersediaan.Tersedia }} dari {{ row.ketersediaan.total }} eksemplar tersedia</span>
                </template>
                <template #cell-aksi="{ row }">
                    <span class="sim-table-actions">
                        <BaseButton variant="secondary" size="sm" icon="bi-eye" icon-only :label="`Detail ${row.kodeBuku}`" @click="lihat(row)" />
                        <BaseButton
                            variant="outline-primary"
                            size="sm"
                            icon="bi-bookmark-plus"
                            :disabled="row.ketersediaan.Tersedia === 0"
                            :href="row.ketersediaan.Tersedia > 0 ? urlAjukanBuku(row.kodeBuku) : null"
                        >
                            Ajukan Booking
                        </BaseButton>
                    </span>
                </template>
                <template #empty>
                    <EmptyState compact icon="bi-search" title="Buku tidak ditemukan" description="Tidak ada buku dengan judul, kode, atau kategori yang cocok. Ubah kata kunci, lalu coba lagi.">
                        <template #actions>
                            <BaseButton variant="secondary" size="sm" icon="bi-arrow-counterclockwise" @click="aturUlang">Atur ulang pencarian</BaseButton>
                        </template>
                    </EmptyState>
                </template>
            </BaseTable>
        </section>

        <DetailPanel v-model:show="panel" title="Detail Buku" :subtitle="dipilih?.kodeBuku">
            <template v-if="dipilih">
                <section class="sim-drawer__section">
                    <dl class="sim-detail-list">
                        <dt>Kode Buku</dt>
                        <dd class="sim-code">{{ dipilih.kodeBuku }}</dd>
                        <dt>Judul</dt>
                        <dd>{{ dipilih.judul }}</dd>
                        <dt>Penulis</dt>
                        <dd>{{ dipilih.penulis }}</dd>
                        <dt>Penerbit</dt>
                        <dd>{{ dipilih.penerbit }}</dd>
                        <dt>Tahun Terbit</dt>
                        <dd class="tabular-nums">{{ dipilih.tahunTerbit }}</dd>
                        <dt>Kategori</dt>
                        <dd>{{ dipilih.kategori }}</dd>
                        <dt>Ketersediaan</dt>
                        <dd>{{ dipilih.ketersediaan.Tersedia }} dari {{ dipilih.ketersediaan.total }} eksemplar tersedia</dd>
                    </dl>
                </section>
                <section class="sim-drawer__section" aria-labelledby="judul-eksemplar-katalog">
                    <h3 id="judul-eksemplar-katalog" class="sim-card-title mb-1">Daftar Eksemplar</h3>
                    <p class="sim-card-subtitle mb-3">Eksemplar Tersedia dapat diajukan untuk booking.</p>
                    <table class="sim-mini-table">
                        <caption class="visually-hidden">Eksemplar {{ dipilih.judul }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">Kode Eksemplar</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="e in eksemplarDipilih" :key="e.kodeEksemplar">
                                <td><span class="sim-code">{{ e.kodeEksemplar }}</span></td>
                                <td><StatusBadge type="eksemplar" :status="e.status" /></td>
                                <td class="text-end">
                                    <Link v-if="e.status === 'Tersedia'" :href="urlAjukanEksemplar(e.kodeEksemplar)" class="btn btn-outline-primary btn-sm">
                                        Booking
                                    </Link>
                                    <span v-else class="sim-cell-sub">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </template>
            <template #footer>
                <BaseButton variant="secondary" @click="panel = false">Tutup</BaseButton>
                <BaseButton v-if="dipilih && dipilih.ketersediaan.Tersedia > 0" icon="bi-bookmark-plus" :href="urlAjukanBuku(dipilih.kodeBuku)">
                    Ajukan Booking
                </BaseButton>
            </template>
        </DetailPanel>
    </AnggotaLayout>
</template>
