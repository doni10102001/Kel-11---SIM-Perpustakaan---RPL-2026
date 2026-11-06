import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import { AKUN_DEMO } from '@/config/demo.js';

/**
 * Akses konteks pratinjau yang dibagikan Laravel (HandleInertiaRequests):
 * peran dari URL, menu sidebar, peta rute, dan meta halaman aktif.
 */
export function useDemo() {
    const page = usePage();

    const demo = computed(() => page.props.demo ?? {});
    const peran = computed(() => demo.value.peran ?? null);
    const akun = computed(() => (peran.value ? AKUN_DEMO[peran.value] : null));
    const halaman = computed(() => demo.value.halaman ?? null);
    const menu = computed(() => demo.value.menu ?? []);
    const adalahAdmin = computed(() => peran.value === 'admin');

    /**
     * URL dari peta rute terpusat. Kunci yang tidak terdaftar dicatat sebagai
     * error di console agar tautan patah langsung terdeteksi saat pengujian.
     */
    function rute(kunci) {
        const url = demo.value.rute?.[kunci];

        if (!url) {
            console.error(`[navigasi] Rute "${kunci}" tidak terdaftar untuk peran "${peran.value}".`);

            return '#';
        }

        return url;
    }

    return { demo, peran, akun, halaman, menu, adalahAdmin, rute };
}
