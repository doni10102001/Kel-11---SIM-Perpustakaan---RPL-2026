import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import { akunAktif } from '@/composables/useDataPerpustakaan.js';

/**
 * Akses konteks pratinjau yang dibagikan Laravel (HandleInertiaRequests):
 * peran dari URL, menu sidebar, peta rute, dan meta halaman aktif.
 */
export function useDemo() {
    const page = usePage();

    const demo = computed(() => page.props.demo ?? {});
    const peran = computed(() => demo.value.peran ?? null);
    // Akun yang masuk pada sesi SPA ini; tanpa sesi → akun pratinjau bawaan.
    const akun = computed(() => (peran.value ? akunAktif(peran.value) : null));
    const halaman = computed(() => demo.value.halaman ?? null);
    const menu = computed(() => demo.value.menu ?? []);
    const adalahAdmin = computed(() => peran.value === 'admin');

    /** Parameter rute aktif, mis. { kodeAnggota: 'AG-001' }. */
    const parameter = computed(() => demo.value.parameter ?? {});

    /**
     * URL dari peta rute terpusat. Rute berparameter berupa templat
     * (mis. ".../anggota/{kodeAnggota}/ubah") dan diisi dari `isian`.
     * Kunci atau parameter yang tidak ada dicatat sebagai error di console
     * agar tautan patah langsung terdeteksi saat pengujian.
     */
    function rute(kunci, isian = {}) {
        const templat = demo.value.rute?.[kunci];

        if (!templat) {
            console.error(`[navigasi] Rute "${kunci}" tidak terdaftar untuk peran "${peran.value}".`);

            return '#';
        }

        let gagal = false;
        const url = templat.replace(/\{(\w+)\}/g, (_, nama) => {
            if (isian[nama] === undefined || isian[nama] === null || isian[nama] === '') {
                gagal = true;

                return '';
            }

            return encodeURIComponent(isian[nama]);
        });

        if (gagal) {
            console.error(`[navigasi] Rute "${kunci}" membutuhkan parameter yang belum diisi.`);

            return '#';
        }

        return url;
    }

    return { demo, peran, akun, halaman, menu, adalahAdmin, parameter, rute };
}
