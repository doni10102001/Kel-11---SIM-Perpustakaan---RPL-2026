<script setup>
import { ref, useId } from 'vue';

/** Kotak pencarian dengan ikon dan tombol hapus kata kunci. */
const model = defineModel({ type: String, default: '' });

defineProps({
    label: { type: String, default: 'Cari' },
    placeholder: { type: String, default: 'Cari…' },
    /** Tampilkan label secara visual (bawaan: hanya untuk pembaca layar). */
    showLabel: { type: Boolean, default: false },
});

const id = `cari-${useId()}`;
const input = ref(null);

function hapus() {
    model.value = '';
    input.value?.focus();
}
</script>

<template>
    <div class="sim-search">
        <label :for="id" :class="showLabel ? 'form-label' : 'visually-hidden'">{{ label }}</label>
        <div class="sim-input-group has-icon has-action">
            <i class="bi bi-search sim-input-icon" aria-hidden="true"></i>
            <input
                :id="id"
                ref="input"
                v-model="model"
                type="search"
                class="form-control"
                :placeholder="placeholder"
                autocomplete="off"
                spellcheck="false"
            />
            <div v-if="model" class="sim-input-action">
                <button type="button" class="btn btn-ghost btn-icon btn-sm" aria-label="Hapus kata kunci" @click="hapus">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </div>
</template>
