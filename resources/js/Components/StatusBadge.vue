<script setup>
import { computed } from 'vue';

import { tampilanStatus } from '@/utils/status.js';

const props = defineProps({
    /** anggota | eksemplar | peminjaman | booking */
    type: { type: String, required: true },
    status: { type: String, required: true },
});

const tampilan = computed(() => {
    const hasil = tampilanStatus(props.type, props.status);

    if (!hasil) {
        // Status di luar kosakata resmi dianggap kesalahan data.
        console.error(`[status] "${props.status}" bukan status ${props.type} yang valid.`);

        return { nada: 'neutral', ikon: 'bi-question-circle' };
    }

    return hasil;
});
</script>

<template>
    <span :class="['sim-badge', `sim-badge--${tampilan.nada}`]">
        <i :class="['bi', tampilan.ikon]" aria-hidden="true"></i>
        {{ status }}
    </span>
</template>
