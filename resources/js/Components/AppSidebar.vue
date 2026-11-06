<script setup>
import { Link } from '@inertiajs/vue3';
import { reactive } from 'vue';

import BrandMark from '@/Components/BrandMark.vue';

const props = defineProps({
    /** Struktur menu dari Laravel: [{ judul, item: [{ label, ikon, url, aktif, anak }] }] */
    menu: { type: Array, default: () => [] },
    caption: { type: String, default: null },
    /** Status laci navigasi pada layar kecil. */
    open: { type: Boolean, default: false },
});

defineEmits(['close', 'logout']);

// Sub-menu terbuka otomatis bila salah satu anaknya aktif.
const terbuka = reactive(
    Object.fromEntries(
        props.menu.flatMap((grup) => grup.item).filter((item) => item.anak?.length).map((item) => [item.label, true]),
    ),
);

function alihkan(label) {
    terbuka[label] = !terbuka[label];
}
</script>

<template>
    <aside id="sidebar-navigasi" :class="['sim-sidebar', { 'is-open': open }]" aria-label="Navigasi utama">
        <div class="sim-sidebar__brand">
            <BrandMark />
            <div class="sim-sidebar__brand-text">
                <span class="sim-sidebar__brand-name">SIM Perpustakaan</span>
                <span v-if="caption" class="sim-sidebar__brand-caption">{{ caption }}</span>
            </div>
            <button
                type="button"
                class="btn btn-icon btn-sm sim-sidebar__close d-lg-none"
                aria-label="Tutup navigasi"
                @click="$emit('close')"
            >
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </div>

        <nav class="sim-sidebar__nav">
            <div v-for="grup in menu" :key="grup.judul" class="sim-nav-group">
                <p class="sim-nav-group__title">{{ grup.judul }}</p>
                <ul class="sim-nav-list">
                    <li v-for="item in grup.item" :key="item.label">
                        <template v-if="item.anak?.length">
                            <button
                                type="button"
                                :class="['sim-nav-link', { 'is-parent-active': item.aktif }]"
                                :aria-expanded="terbuka[item.label] ? 'true' : 'false'"
                                :aria-controls="`submenu-${item.label.replace(/\s+/g, '-').toLowerCase()}`"
                                @click="alihkan(item.label)"
                            >
                                <i :class="['bi', item.ikon]" aria-hidden="true"></i>
                                <span>{{ item.label }}</span>
                                <i class="bi bi-chevron-down sim-nav-link__chevron" aria-hidden="true"></i>
                            </button>
                            <ul
                                v-show="terbuka[item.label]"
                                :id="`submenu-${item.label.replace(/\s+/g, '-').toLowerCase()}`"
                                class="sim-nav-sub"
                            >
                                <li v-for="anak in item.anak" :key="anak.label">
                                    <Link
                                        :href="anak.url"
                                        :class="['sim-nav-sublink', { 'is-active': anak.aktif }]"
                                        :aria-current="anak.aktif ? 'page' : undefined"
                                    >
                                        {{ anak.label }}
                                    </Link>
                                </li>
                            </ul>
                        </template>
                        <Link
                            v-else
                            :href="item.url"
                            :class="['sim-nav-link', { 'is-active': item.aktif }]"
                            :aria-current="item.aktif ? 'page' : undefined"
                        >
                            <i :class="['bi', item.ikon]" aria-hidden="true"></i>
                            <span>{{ item.label }}</span>
                        </Link>
                    </li>
                </ul>
            </div>
        </nav>

        <div class="sim-sidebar__footer">
            <button type="button" class="sim-nav-link" @click="$emit('logout')">
                <i class="bi bi-box-arrow-left" aria-hidden="true"></i>
                <span>Keluar</span>
            </button>
        </div>
    </aside>
</template>
