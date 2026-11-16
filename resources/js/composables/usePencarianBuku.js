/**
 * Phase 9B — pencarian buku di SERVER (Laravel/MySQL) untuk Data Buku (Admin)
 * dan Cari Buku (Anggota).
 *
 * Kata kunci & kategori dikirim sebagai query string lewat Inertia GET
 * (`?q=…&kategori=…&page=…`), sehingga hasil, pagination, dan status filter
 * tersimpan di URL (bisa dimuat ulang / kembali). Browser TIDAK lagi memfilter
 * data; prop `pencarianBuku` berisi meta hasil dari server.
 */
import { router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

import { JEDA_KETIK_MS, META_KOSONG, parameterPencarian, urlPencarian } from '../utils/pencarianServer.js';

export { JEDA_KETIK_MS, nomorHalaman, parameterPencarian, urlPencarian } from '../utils/pencarianServer.js';

export function usePencarianBuku(urlDasar) {
    const page = usePage();
    const meta = computed(() => ({ ...META_KOSONG, ...(page.props.pencarianBuku ?? {}) }));

    const kataKunci = ref(meta.value.q);
    const kategori = ref(meta.value.kategori);
    let jeda = null;

    function kunjungi({ halaman = 1, replace = true } = {}) {
        clearTimeout(jeda);
        const tujuan = parameterPencarian({ q: kataKunci.value, kategori: kategori.value, halaman });
        const kini = parameterPencarian(meta.value);
        if (JSON.stringify(tujuan) === JSON.stringify(kini)) return; // tidak ada perubahan → tanpa request

        router.get(urlDasar, tujuan, { preserveState: true, preserveScroll: true, replace, only: ['data', 'pencarianBuku'] });
    }

    // Ketik → tunggu jeda singkat, lalu cari di server (halaman kembali ke 1).
    watch(kataKunci, () => {
        clearTimeout(jeda);
        jeda = setTimeout(() => kunjungi(), JEDA_KETIK_MS);
    });
    watch(kategori, () => kunjungi());

    // Sinkronkan isian bila URL berubah dari luar (kembali/maju di riwayat browser).
    watch(
        () => meta.value.q,
        (q) => {
            if (q !== kataKunci.value.trim()) kataKunci.value = q;
        },
    );
    watch(
        () => meta.value.kategori,
        (k) => {
            if (k !== kategori.value) kategori.value = k;
        },
    );

    onBeforeUnmount(() => clearTimeout(jeda));

    return {
        meta,
        kataKunci,
        kategori,
        adaFilter: computed(() => kataKunci.value.trim() !== '' || kategori.value !== ''),
        urlHalaman: (n) => urlPencarian(urlDasar, { q: meta.value.q, kategori: meta.value.kategori, halaman: n }),
        aturUlang() {
            kataKunci.value = '';
            kategori.value = '';
            kunjungi();
        },
    };
}
