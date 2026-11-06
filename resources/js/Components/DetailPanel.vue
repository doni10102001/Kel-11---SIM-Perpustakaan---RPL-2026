<script setup>
import { nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';

/**
 * Panel detail dari sisi kanan (aksi "Lihat"). Tertutup dengan tombol Tutup,
 * klik latar, atau Escape; fokus dijaga di dalam panel selama terbuka.
 */
const show = defineModel('show', { type: Boolean, default: false });

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
});

const emit = defineEmits(['close']);

const idJudul = `panel-${useId()}`;
const panel = ref(null);
let fokusSebelumnya = null;

function tutup() {
    show.value = false;
    emit('close');
}

function tanganiTombol(event) {
    if (event.key === 'Escape') {
        event.preventDefault();
        tutup();

        return;
    }

    if (event.key === 'Tab' && panel.value) {
        const fokus = panel.value.querySelectorAll('button:not([disabled]), [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (fokus.length === 0) return;
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

watch(show, async (terbuka) => {
    if (terbuka) {
        fokusSebelumnya = document.activeElement;
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', tanganiTombol);
        await nextTick();
        panel.value?.querySelector('[data-autofocus]')?.focus();
    } else {
        document.body.style.overflow = '';
        document.removeEventListener('keydown', tanganiTombol);
        fokusSebelumnya?.focus?.();
    }
});

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    document.removeEventListener('keydown', tanganiTombol);
});
</script>

<template>
    <Teleport to="body">
        <Transition name="sim-drawer">
            <div v-if="show" class="sim-drawer">
                <div class="sim-drawer__backdrop" aria-hidden="true" @click="tutup"></div>
                <section ref="panel" class="sim-drawer__panel" role="dialog" aria-modal="true" :aria-labelledby="idJudul">
                    <header class="sim-drawer__header">
                        <div class="min-w-0">
                            <h2 :id="idJudul" class="sim-drawer__title">{{ title }}</h2>
                            <p v-if="subtitle" class="sim-drawer__subtitle">{{ subtitle }}</p>
                        </div>
                        <button type="button" class="btn btn-ghost btn-icon btn-sm" aria-label="Tutup panel" data-autofocus @click="tutup">
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                        </button>
                    </header>
                    <div class="sim-drawer__body">
                        <slot />
                    </div>
                    <footer v-if="$slots.footer" class="sim-drawer__footer">
                        <slot name="footer" />
                    </footer>
                </section>
            </div>
        </Transition>
    </Teleport>
</template>
