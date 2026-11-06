<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import BaseButton from '@/Components/BaseButton.vue';
import BaseTable from '@/Components/BaseTable.vue';
import DetailPanel from '@/Components/DetailPanel.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormFeedback from '@/Components/FormFeedback.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SearchInput from '@/Components/SearchInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useDataPerpustakaan } from '@/composables/useDataPerpustakaan.js';
import { useDemo } from '@/composables/useDemo.js';
import { eksemplarBuku, ketersediaanBuku } from '@/data/mock/index.js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { filterBuku } from '@/utils/pencarian.js';

const { data, ambilPesan } = useDataPerpustakaan();
const { rute } = useDemo();

const pesan = ref(ambilPesan());
const kataKunci = ref('');

// Jumlah Eksemplar dihitung dari relasi eksemplar (bookCopies), bukan disimpan di buku.
const semua = computed(() =>
    [...data.buku]
        .sort((a, b) => a.kodeBuku.localeCompare(b.kodeBuku))
        .map((b) => ({ ...b, ketersediaan: ketersediaanBuku(data, b.kodeBuku) })),
);
const hasil = computed(() => filterBuku(semua.value, { kataKunci: kataKunci.value }));

const kolom = [
    { key: 'kodeBuku', label: 'Kode Buku', width: '7.5rem' },
    { key: 'judul', label: 'Judul' },
    { key: 'penulis', label: 'Penulis', class: 'text-nowrap' },
    { key: 'penerbit', label: 'Penerbit', class: 'text-nowrap' },
    { key: 'tahunTerbit', label: 'Tahun Terbit', align: 'center', width: '7rem' },
    { key: 'kategori', label: 'Kategori', class: 'text-nowrap' },
    { key: 'jumlahEksemplar', label: 'Jumlah Eksemplar' },
    { key: 'aksi', label: 'Aksi', align: 'end', width: '6.5rem' },
];

const kodeDipilih = ref(null);
const panelTerbuka = ref(false);
const dipilih = computed(() => semua.value.find((b) => b.kodeBuku === kodeDipilih.value) ?? null);
const eksemplarDipilih = computed(() =>
    dipilih.value ? [...eksemplarBuku(data, dipilih.value.kodeBuku)].sort((a, b) => a.kodeEksemplar.localeCompare(b.kodeEksemplar)) : [],
);

function lihat(buku) {
    kodeDipilih.value = buku.kodeBuku;
    panelTerbuka.value = true;
}

const urlTambahEksemplar = (kodeBuku) => `${rute('buku.ubah', { kodeBuku })}?eksemplar=tambah`;
</script>

<template>
    <AdminLayout>
        <Head title="Data Buku & Eksemplar" />

        <PageHeader>
            <template #actions>
                <BaseButton icon="bi-journal-plus" :href="rute('buku.tambah')">Tambah Buku</BaseButton>
            </template>
        </PageHeader>

        <FormFeedback
            v-if="pesan"
            class="mb-4"
            :tone="pesan.tone"
            :title="pesan.title"
            :message="pesan.message"
            dismissible
            @dismiss="pesan = null"
        />

        <section class="sim-card">
            <div class="sim-toolbar">
                <div class="sim-toolbar__search">
                    <SearchInput v-model="kataKunci" label="Cari buku" placeholder="Cari kode buku, judul, atau kategori" />
                </div>
                <BaseButton v-if="kataKunci.trim()" variant="ghost" size="sm" icon="bi-arrow-counterclockwise" @click="kataKunci = ''">
                    Atur ulang
                </BaseButton>
                <p class="sim-toolbar__meta mb-0" aria-live="polite">
                    Menampilkan {{ hasil.length }} dari {{ semua.length }} buku
                </p>
            </div>

            <BaseTable :columns="kolom" :rows="hasil" row-key="kodeBuku" caption="Daftar buku">
                <template #cell-kodeBuku="{ value }"><span class="sim-code">{{ value }}</span></template>
                <template #cell-judul="{ value }"><span class="sim-cell-title">{{ value }}</span></template>
                <template #cell-tahunTerbit="{ value }"><span class="tabular-nums">{{ value }}</span></template>
                <template #cell-jumlahEksemplar="{ row }">
                    <span class="sim-count">
                        <span class="sim-count__total tabular-nums">{{ row.ketersediaan.total }}</span>
                        <span class="sim-count__note">{{ row.ketersediaan.Tersedia }} tersedia</span>
                    </span>
                </template>
                <template #cell-aksi="{ row }">
                    <span class="sim-table-actions">
                        <BaseButton variant="secondary" size="sm" icon="bi-eye" icon-only :label="`Lihat ${row.kodeBuku}`" @click="lihat(row)" />
                        <BaseButton
                            variant="secondary"
                            size="sm"
                            icon="bi-pencil"
                            icon-only
                            :label="`Ubah ${row.kodeBuku}`"
                            :href="rute('buku.ubah', { kodeBuku: row.kodeBuku })"
                        />
                    </span>
                </template>
                <template #empty>
                    <EmptyState
                        compact
                        icon="bi-search"
                        title="Buku tidak ditemukan"
                        description="Tidak ada buku dengan kode, judul, atau kategori yang cocok. Ubah kata kunci, lalu coba lagi."
                    >
                        <template #actions>
                            <BaseButton variant="secondary" size="sm" icon="bi-arrow-counterclockwise" @click="kataKunci = ''">
                                Atur ulang pencarian
                            </BaseButton>
                        </template>
                    </EmptyState>
                </template>
            </BaseTable>
        </section>

        <DetailPanel v-model:show="panelTerbuka" title="Detail Buku" :subtitle="dipilih?.kodeBuku">
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
                        <dt>Jumlah Eksemplar</dt>
                        <dd class="tabular-nums">{{ dipilih.ketersediaan.total }}</dd>
                    </dl>
                </section>

                <section class="sim-drawer__section" aria-labelledby="judul-daftar-eksemplar">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <div>
                            <h3 id="judul-daftar-eksemplar" class="sim-card-title">Daftar Eksemplar</h3>
                            <p class="sim-card-subtitle">
                                {{ dipilih.ketersediaan.Tersedia }} Tersedia · {{ dipilih.ketersediaan.Dipesan }} Dipesan ·
                                {{ dipilih.ketersediaan.Dipinjam }} Dipinjam
                            </p>
                        </div>
                        <BaseButton variant="outline-primary" size="sm" icon="bi-plus-lg" :href="urlTambahEksemplar(dipilih.kodeBuku)">
                            Tambah Eksemplar
                        </BaseButton>
                    </div>
                    <table class="sim-mini-table">
                        <caption class="visually-hidden">Daftar eksemplar {{ dipilih.judul }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">Kode Eksemplar</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="e in eksemplarDipilih" :key="e.kodeEksemplar">
                                <td><span class="sim-code">{{ e.kodeEksemplar }}</span></td>
                                <td><StatusBadge type="eksemplar" :status="e.status" /></td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </template>
            <template #footer>
                <BaseButton variant="secondary" @click="panelTerbuka = false">Tutup</BaseButton>
                <BaseButton v-if="dipilih" icon="bi-pencil" :href="rute('buku.ubah', { kodeBuku: dipilih.kodeBuku })">Ubah Buku</BaseButton>
            </template>
        </DetailPanel>
    </AdminLayout>
</template>
