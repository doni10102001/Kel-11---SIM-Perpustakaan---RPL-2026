<script setup>
import { computed, nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';

import BaseButton from '@/Components/BaseButton.vue';

/**
 * Dialog konfirmasi untuk aksi penting (SRS: konfirmasi perubahan data penting).
 * Tanpa JavaScript Bootstrap; dikelola penuh oleh Vue.
 */
const show = defineModel('show', { type: Boolean, default: false });

const props = defineProps({
    title: { type: String, required: true },
    message: { type: String, default: null },
    /** primary | danger | warning | success */
    tone: { type: String, default: 'primary' },
    icon: { type: String, default: null },
    confirmLabel: { type: String, default: 'Konfirmasi' },
    cancelLabel: { type: String, default: 'Batal' },
    loading: { type: Boolean, default: false },
    /** sm | md */
    size: { type: String, default: 'sm' },
});

const emit = defineEmits(['confirm', 'cancel']);

const IKON = {
    primary: 'bi-question-circle',
    danger: 'bi-exclamation-triangle',
    warning: 'bi-exclamation-circle',
    success: 'bi-check-circle',
};

const VARIAN = { primary: 'primary', danger: 'danger', warning: 'primary', success: 'success' };

const ikon = computed(() => props.icon ?? IKON[props.tone] ?? IKON.primary);

// Kode seperti EK-018 atau BP-008 tidak boleh terpotong di tanda hubung saat teks dibungkus.
const pesanTampil = computed(() => props.message?.replace(/\b([A-Z]{2,3})-(\d+)\b/g, '$1\u2011$2') ?? null);
const varian = computed(() => VARIAN[props.tone] ?? 'primary');

const id = useId();
const idJudul = `modal-${id}-judul`;
const idPesan = `modal-${id}-pesan`;

const dialog = ref(null);
let fokusSebelumnya = null;

function batal() {
    if (props.loading) {
        return;
    }

    emit('cancel');
    show.value = false;
}

function tanganiTombol(event) {
    if (event.key === 'Escape') {
        event.preventDefault();
        batal();

        return;
    }

    // Jaga fokus tetap di dalam dialog.
    if (event.key === 'Tab' && dialog.value) {
        const fokus = dialog.value.querySelectorAll(
            'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
        );

        if (fokus.length === 0) {
            return;
        }

        const pertama = fokus[0];
        const terakhir = fokus[fokus.length - 1];

        if (event.shiftKey && document.activeElement === pertama) {
            event.preventDefault();
            terakhir.focus();
        } else if (!event.shiftKey && document.activeElement === terakhir) {
            event.preventDefault();
            pertama.focus();
        }
    }
}

watch(
    show,
    async (terbuka) => {
        if (typeof document === 'undefined') {
            return;
        }

        if (terbuka) {
            fokusSebelumnya = document.activeElement;
            document.body.style.overflow = 'hidden';
            document.addEventListener('keydown', tanganiTombol);
            await nextTick();
            // Fokus awal ke elemen isian pertama, atau ke tombol Batal.
            const target =
                dialog.value?.querySelector('input, textarea, select') ??
                dialog.value?.querySelector('[data-autofocus]');
            target?.focus();
        } else {
            document.body.style.overflow = '';
            document.removeEventListener('keydown', tanganiTombol);
            fokusSebelumnya?.focus?.();
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    document.removeEventListener('keydown', tanganiTombol);
});
</script>

<template>
    <Teleport to="body">
        <Transition name="sim-modal">
            <div v-if="show" class="sim-modal">
                <div class="sim-modal__backdrop" aria-hidden="true" @click="batal"></div>
                <div
                    ref="dialog"
                    :class="['sim-modal__dialog', { 'is-md': size === 'md' }]"
                    role="dialog"
                    aria-modal="true"
                    :aria-labelledby="idJudul"
                    :aria-describedby="message ? idPesan : undefined"
                >
                    <div class="sim-modal__body">
                        <div :class="['sim-modal__icon', `sim-modal__icon--${tone}`]" aria-hidden="true">
                            <i :class="['bi', ikon]"></i>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <h2 :id="idJudul" class="sim-modal__title">{{ title }}</h2>
                            <p v-if="message" :id="idPesan" class="sim-modal__message">{{ pesanTampil }}</p>
                            <div v-if="$slots.default" class="sim-modal__extra">
                                <slot />
                            </div>
                        </div>
                    </div>
                    <div class="sim-modal__footer">
                        <BaseButton variant="secondary" :disabled="loading" data-autofocus @click="batal">
                            {{ cancelLabel }}
                        </BaseButton>
                        <BaseButton :variant="varian" :loading="loading" @click="emit('confirm')">
                            {{ confirmLabel }}
                        </BaseButton>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
