/**
 * Batch 2 — kirim aksi transaksi ke server (Laravel/MySQL) lewat Inertia.
 *
 * - `router.post` dipanggil sebagai method (tidak dilepas dari objeknya; pelajaran Phase 9A).
 * - Kirim ganda dicegah: permintaan berikutnya diabaikan selama `memproses` true
 *   (tombol konfirmasi menampilkan status memuat).
 * - Sukses = server sudah COMMIT dan mengalihkan kembali dengan flash
 *   `transaksi` (kode transaksi baru) & `status`. Halaman membangun bukti dari
 *   data MySQL terbaru, bukan dari simulasi di browser.
 * - Gagal = galat validasi per field (atau `umum`) dari server; tidak ada pesan sukses.
 */
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

export function useTransaksiServer() {
    const memproses = ref(false);

    /**
     * @param {string} url
     * @param {object} payload
     * @param {{ onSukses?: (kode: string|null, pesan: string|null) => void, onGalat?: (galat: Record<string, string>) => void }} opsi
     */
    function kirim(url, payload = {}, { onSukses, onGalat } = {}) {
        if (memproses.value) return false;
        memproses.value = true;

        router.post(url, payload, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => onSukses?.(page.props.flash?.transaksi ?? null, page.props.flash?.status ?? null),
            onError: (galat) => onGalat?.(galat),
            onFinish: () => {
                memproses.value = false;
            },
        });

        return true;
    }

    return { memproses, kirim };
}

/** Pesan galat pertama dari objek galat server. */
export const galatPertama = (galat) => Object.values(galat ?? {}).find(Boolean) ?? null;
