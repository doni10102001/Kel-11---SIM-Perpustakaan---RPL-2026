/**
 * Phase 8B — data halaman dari SERVER (MySQL → Laravel → prop Inertia `data`).
 *
 * Menggantikan store mock di browser. API dipertahankan agar halaman tidak
 * perlu didesain ulang:
 * - `data.<koleksi>` membaca prop `data` halaman yang disiapkan
 *   App\Queries\DataPerpustakaan — hanya koleksi yang boleh dilihat akun
 *   (Anggota: katalog + data miliknya). Koleksi yang tidak dikirim = [].
 * - `tanggalHariIni` = kalender WIB dari server (prop bersama `hariIni`).
 * - Aksi tulis (tambahAnggota, catatPeminjaman, ...) DITOLAK eksplisit sampai
 *   endpoint server tersedia (utils/aksiTertunda.js). Tidak ada simulasi sukses.
 *
 * Tidak ada data mock, kontak Anggota lain, atau hash di modul ini.
 */
import { usePage } from '@inertiajs/vue3';

import { aksiTulis } from '../utils/aksiTertunda.js';

export { GalatValidasi } from '../utils/galat.js';

let pesanTertunda = null;

export function useDataPerpustakaan() {
    const page = usePage();

    // Selalu membaca prop halaman terkini (reaktif) — aman bila halaman dimuat ulang oleh Inertia.
    const data = new Proxy(
        {},
        {
            get: (_, kunci) => (typeof kunci === 'string' ? (page.props.data?.[kunci] ?? []) : undefined),
            set: () => {
                throw new Error('Data halaman bersifat baca-saja (sumber: server).');
            },
        },
    );

    const tanggalHariIni = page.props.hariIni;

    return {
        data,
        tanggalHariIni,
        tahunMaks: Number(String(tanggalHariIni).slice(0, 4)),
        ...aksiTulis,
        setPesan,
        ambilPesan,
    };
}

/** Pesan satu kali untuk halaman tujuan (tidak dipakai untuk klaim penyimpanan). */
export function setPesan(pesan) {
    pesanTertunda = pesan;
}

export function ambilPesan() {
    const pesan = pesanTertunda;
    pesanTertunda = null;

    return pesan;
}
