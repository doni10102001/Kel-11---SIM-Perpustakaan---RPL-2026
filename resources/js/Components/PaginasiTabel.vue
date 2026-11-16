<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import { nomorHalaman } from '@/utils/pencarianServer.js';

/**
 * Phase 9B — navigasi halaman hasil dari server. Tautan membawa filter aktif
 * (lihat usePencarianBuku.urlHalaman) dan mempertahankan state/scroll.
 */
const props = defineProps({
    halaman: { type: Number, required: true },
    halamanTerakhir: { type: Number, required: true },
    total: { type: Number, required: true },
    perHalaman: { type: Number, required: true },
    /** (nomor) => URL halaman tersebut. */
    url: { type: Function, required: true },
    label: { type: String, default: 'Navigasi halaman' },
});

const nomor = computed(() => nomorHalaman(props.halaman, props.halamanTerakhir));
const dari = computed(() => (props.total === 0 ? 0 : (props.halaman - 1) * props.perHalaman + 1));
const sampai = computed(() => Math.min(props.halaman * props.perHalaman, props.total));
const opsi = { preserveState: true, preserveScroll: true, only: ['data', 'pencarianBuku'] };
</script>

<template>
    <nav v-if="halamanTerakhir > 1" class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-3 border-top" :aria-label="label">
        <p class="sim-toolbar__meta mb-0 tabular-nums" aria-live="polite">Halaman {{ halaman }} dari {{ halamanTerakhir }} · baris {{ dari }}–{{ sampai }}</p>
        <ul class="pagination pagination-sm mb-0">
            <li class="page-item" :class="{ disabled: halaman <= 1 }">
                <Link v-if="halaman > 1" class="page-link" :href="url(halaman - 1)" v-bind="opsi" rel="prev">Sebelumnya</Link>
                <span v-else class="page-link" aria-disabled="true">Sebelumnya</span>
            </li>
            <li v-for="n in nomor" :key="n" class="page-item" :class="{ active: n === halaman }">
                <span v-if="n === halaman" class="page-link" aria-current="page">{{ n }}</span>
                <Link v-else class="page-link" :href="url(n)" v-bind="opsi" :aria-label="`Halaman ${n}`">{{ n }}</Link>
            </li>
            <li class="page-item" :class="{ disabled: halaman >= halamanTerakhir }">
                <Link v-if="halaman < halamanTerakhir" class="page-link" :href="url(halaman + 1)" v-bind="opsi" rel="next">Berikutnya</Link>
                <span v-else class="page-link" aria-disabled="true">Berikutnya</span>
            </li>
        </ul>
    </nav>
</template>
