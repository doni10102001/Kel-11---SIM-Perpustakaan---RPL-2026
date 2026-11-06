<script setup>
import { computed } from 'vue';

import { TANGGAL_DEMO } from '@/config/demo.js';
import { formatTanggal, inisial } from '@/utils/format.js';

const props = defineProps({
    /** Akun pratinjau aktif: { nama, labelPeran, keterangan, peran } */
    akun: { type: Object, default: null },
    sidebarOpen: { type: Boolean, default: false },
});

defineEmits(['toggle-sidebar']);

const IKON_PERAN = {
    admin: 'bi-shield-check',
    anggota: 'bi-person-vcard',
};

const tanggal = formatTanggal(TANGGAL_DEMO, 'lengkap');
const ikonPeran = computed(() => IKON_PERAN[props.akun?.peran] ?? 'bi-person');
</script>

<template>
    <header class="sim-header">
        <button
            type="button"
            class="btn btn-ghost btn-icon d-lg-none"
            aria-label="Buka navigasi"
            aria-controls="sidebar-navigasi"
            :aria-expanded="sidebarOpen ? 'true' : 'false'"
            @click="$emit('toggle-sidebar')"
        >
            <i class="bi bi-list fs-5" aria-hidden="true"></i>
        </button>

        <span class="sim-header__date d-none d-md-inline-flex">
            <i class="bi bi-calendar3" aria-hidden="true"></i>
            <time :datetime="TANGGAL_DEMO">{{ tanggal }}</time>
        </span>

        <div v-if="akun" class="sim-header__end">
            <span class="sim-role-chip">
                <i :class="['bi', ikonPeran]" aria-hidden="true"></i>
                {{ akun.labelPeran }}
            </span>
            <span class="sim-header__divider d-none d-sm-block" aria-hidden="true"></span>
            <div class="sim-user">
                <span class="sim-avatar" aria-hidden="true">{{ inisial(akun.nama) }}</span>
                <span class="sim-user__text d-none d-sm-block">
                    <span class="sim-user__name">{{ akun.nama }}</span>
                    <span class="sim-user__meta">{{ akun.keterangan }}</span>
                </span>
                <span class="visually-hidden d-sm-none">{{ akun.nama }}</span>
            </div>
        </div>
    </header>
</template>
