import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Akses konteks yang dibagikan Laravel (HandleInertiaRequests): peran dari
 * rute aktif, menu sidebar, peta rute, meta halaman aktif, dan — sejak
 * Phase 8A — akun yang masuk dari SESI SERVER (`auth.akun`). Tidak ada lagi
 * akun pratinjau bawaan (AG-001) di browser.
 */
export function useDemo() {
    const page = usePage();

    const demo = computed(() => page.props.demo ?? {});
    const peran = computed(() => demo.value.peran ?? null);
    // Akun yang masuk menurut sesi server: { peran, identitas, nama, labelPeran, keterangan, kodeAnggota? }.
    const akun = computed(() => page.props.auth?.akun ?? null);
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
