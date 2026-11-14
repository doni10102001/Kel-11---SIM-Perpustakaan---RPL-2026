<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import BaseButton from '@/Components/BaseButton.vue';
import BaseSelect from '@/Components/BaseSelect.vue';
import BaseTable from '@/Components/BaseTable.vue';
import DetailPanel from '@/Components/DetailPanel.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormFeedback from '@/Components/FormFeedback.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SearchInput from '@/Components/SearchInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useDataPerpustakaan } from '@/composables/useDataPerpustakaan.js';
import { useDemo } from '@/composables/useDemo.js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { inisial } from '@/utils/format.js';
import { filterAnggota } from '@/utils/pencarian.js';
import { daftarStatus } from '@/utils/status.js';

const { data } = useDataPerpustakaan();
const { rute } = useDemo();
const page = usePage();

// Phase 9A: pesan sukses dari server (flash) — hanya setelah data tersimpan di MySQL.
const pesan = ref(page.props.flash?.status ? { tone: 'success', title: 'Data anggota tersimpan', message: page.props.flash.status } : null);
const kataKunci = ref('');
const status = ref('');

const semua = computed(() => [...data.anggota].sort((a, b) => a.kodeAnggota.localeCompare(b.kodeAnggota)));
const hasil = computed(() => filterAnggota(semua.value, { kataKunci: kataKunci.value, status: status.value }));
const adaFilter = computed(() => kataKunci.value.trim() !== '' || status.value !== '');

function aturUlang() {
    kataKunci.value = '';
    status.value = '';
}

const kolom = [
    { key: 'kodeAnggota', label: 'Kode Anggota', width: '9.5rem' },
    { key: 'nama', label: 'Nama' },
    { key: 'kontak', label: 'Kontak' },
    { key: 'status', label: 'Status', width: '8.5rem' },
    { key: 'aksi', label: 'Aksi', align: 'end', width: '6.5rem' },
];

const dipilih = ref(null);
const panelTerbuka = ref(false);

function lihat(anggota) {
    dipilih.value = anggota;
    panelTerbuka.value = true;
}
</script>

<template>
    <AdminLayout>
        <Head title="Data Anggota" />

        <PageHeader>
            <template #actions>
                <BaseButton icon="bi-person-plus" :href="rute('anggota.tambah')">Tambah Anggota</BaseButton>
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
                    <SearchInput v-model="kataKunci" label="Cari anggota" placeholder="Cari kode, nama, atau kontak" />
                </div>
                <div class="sim-toolbar__filter">
                    <BaseSelect
                        v-model="status"
                        aria-label="Filter status anggota"
                        placeholder="Semua status"
                        placeholder-selectable
                        :options="daftarStatus('anggota')"
                    />
                </div>
                <BaseButton v-if="adaFilter" variant="ghost" size="sm" icon="bi-arrow-counterclockwise" @click="aturUlang">
                    Atur ulang
                </BaseButton>
                <p class="sim-toolbar__meta mb-0" aria-live="polite">
                    Menampilkan {{ hasil.length }} dari {{ semua.length }} anggota
                </p>
            </div>

            <BaseTable :columns="kolom" :rows="hasil" row-key="kodeAnggota" caption="Daftar anggota">
                <template #cell-kodeAnggota="{ value }"><span class="sim-code">{{ value }}</span></template>
                <template #cell-nama="{ row }">
                    <span class="sim-identity">
                        <span :class="['sim-avatar', 'is-sm', { 'is-muted': row.status === 'Nonaktif' }]" aria-hidden="true">
                            {{ inisial(row.nama) }}
                        </span>
                        <span class="sim-cell-title">{{ row.nama }}</span>
                    </span>
                </template>
                <template #cell-kontak="{ value }"><span class="text-nowrap">{{ value }}</span></template>
                <template #cell-status="{ value }"><StatusBadge type="anggota" :status="value" /></template>
                <template #cell-aksi="{ row }">
                    <span class="sim-table-actions">
                        <BaseButton variant="secondary" size="sm" icon="bi-eye" icon-only :label="`Lihat ${row.kodeAnggota}`" @click="lihat(row)" />
                        <BaseButton
                            variant="secondary"
                            size="sm"
                            icon="bi-pencil"
                            icon-only
                            :label="`Ubah ${row.kodeAnggota}`"
                            :href="rute('anggota.ubah', { kodeAnggota: row.kodeAnggota })"
                        />
                    </span>
                </template>
                <template #empty>
                    <EmptyState
                        compact
                        icon="bi-person-x"
                        title="Anggota tidak ditemukan"
                        description="Tidak ada anggota yang cocok dengan kata kunci atau filter status. Ubah pencarian, lalu coba lagi."
                    >
                        <template #actions>
                            <BaseButton variant="secondary" size="sm" icon="bi-arrow-counterclockwise" @click="aturUlang">
                                Atur ulang pencarian
                            </BaseButton>
                        </template>
                    </EmptyState>
                </template>
            </BaseTable>
        </section>

        <DetailPanel v-model:show="panelTerbuka" title="Detail Anggota" :subtitle="dipilih?.kodeAnggota">
            <template v-if="dipilih">
                <div class="sim-identity mb-4">
                    <span :class="['sim-avatar', { 'is-muted': dipilih.status === 'Nonaktif' }]" aria-hidden="true">
                        {{ inisial(dipilih.nama) }}
                    </span>
                    <span>
                        <span class="sim-cell-title">{{ dipilih.nama }}</span>
                        <span class="sim-cell-sub">{{ dipilih.kodeAnggota }}</span>
                    </span>
                </div>
                <dl class="sim-detail-list">
                    <dt>Kode Anggota</dt>
                    <dd class="sim-code">{{ dipilih.kodeAnggota }}</dd>
                    <dt>Nama</dt>
                    <dd>{{ dipilih.nama }}</dd>
                    <dt>Kontak</dt>
                    <dd>{{ dipilih.kontak }}</dd>
                    <dt>Status Anggota</dt>
                    <dd><StatusBadge type="anggota" :status="dipilih.status" /></dd>
                </dl>
            </template>
            <template #footer>
                <BaseButton variant="secondary" @click="panelTerbuka = false">Tutup</BaseButton>
                <BaseButton v-if="dipilih" icon="bi-pencil" :href="rute('anggota.ubah', { kodeAnggota: dipilih.kodeAnggota })">
                    Ubah Anggota
                </BaseButton>
            </template>
        </DetailPanel>
    </AdminLayout>
</template>
