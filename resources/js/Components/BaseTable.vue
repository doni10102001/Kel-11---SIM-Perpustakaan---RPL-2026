<script setup>
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({
    /** [{ key, label, align: 'start'|'center'|'end', width, class }] */
    columns: { type: Array, required: true },
    rows: { type: Array, default: () => [] },
    /** Nama properti unik baris, atau fungsi (baris) => kunci. */
    rowKey: { type: [String, Function], default: 'id' },
    /** Kunci baris yang sedang dipilih (disorot). */
    selectedKey: { type: [String, Number], default: null },
    caption: { type: String, default: null },
    hoverable: { type: Boolean, default: true },
    loading: { type: Boolean, default: false },
    emptyIcon: { type: String, default: 'bi-inbox' },
    emptyTitle: { type: String, default: 'Belum ada data' },
    emptyDescription: { type: String, default: null },
});

defineEmits(['row-click']);

function kunci(baris, indeks) {
    if (typeof props.rowKey === 'function') {
        return props.rowKey(baris);
    }

    return baris?.[props.rowKey] ?? indeks;
}

function rata(kolom) {
    return { 'text-center': kolom.align === 'center', 'text-end': kolom.align === 'end' };
}
</script>

<template>
    <div class="sim-table-wrap">
        <table :class="['sim-table', { 'is-hoverable': hoverable }]" :aria-busy="loading || undefined">
            <caption v-if="caption" class="visually-hidden">{{ caption }}</caption>
            <thead>
                <tr>
                    <th
                        v-for="kolom in columns"
                        :key="kolom.key"
                        scope="col"
                        :class="[rata(kolom), kolom.class]"
                        :style="kolom.width ? { width: kolom.width } : undefined"
                    >
                        {{ kolom.label }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="loading" class="sim-table-loading">
                    <td :colspan="columns.length">
                        <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                        Memuat data…
                    </td>
                </tr>
                <tr v-else-if="rows.length === 0">
                    <td :colspan="columns.length" class="p-0">
                        <slot name="empty">
                            <EmptyState compact :icon="emptyIcon" :title="emptyTitle" :description="emptyDescription" />
                        </slot>
                    </td>
                </tr>
                <template v-else>
                    <tr
                        v-for="(baris, indeks) in rows"
                        :key="kunci(baris, indeks)"
                        :class="{ 'is-selected': selectedKey !== null && kunci(baris, indeks) === selectedKey }"
                        @click="$emit('row-click', baris)"
                    >
                        <td v-for="kolom in columns" :key="kolom.key" :class="[rata(kolom), kolom.class]">
                            <slot :name="`cell-${kolom.key}`" :row="baris" :value="baris?.[kolom.key]" :index="indeks">
                                {{ baris?.[kolom.key] ?? '—' }}
                            </slot>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</template>
