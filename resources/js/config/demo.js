/**
 * Konfigurasi pratinjau statis (khusus pengembang).
 *
 * Prototipe memakai DUA peran: Admin dan Anggota.
 * TIDAK ADA autentikasi: halaman Masuk hanya mencocokkan identitas di bawah
 * lalu membuka dashboard sesuai peran. Kata sandi tidak diverifikasi.
 */

/**
 * "Hari ini" yang tetap untuk pratinjau, agar tampilan dan screenshot
 * deterministik. Format ISO (YYYY-MM-DD), zona waktu Asia/Jakarta.
 */
export const TANGGAL_DEMO = '2026-11-07';

/**
 * Akun pratinjau per peran. Kunci = nilai `peran` dari rute /demo/{peran}/...
 *
 * Catatan Phase 2: identitas Anggota (AG-001) harus diselaraskan dengan
 * dataset terpusat resources/js/data/mock/members.js.
 */
export const AKUN_DEMO = {
    admin: {
        peran: 'admin',
        identitas: 'admin',
        nama: 'Rina Wulandari',
        labelPeran: 'Admin',
        keterangan: 'Admin Perpustakaan',
    },
    anggota: {
        peran: 'anggota',
        identitas: 'AG-001',
        nama: 'Nadia Rahmawati',
        labelPeran: 'Anggota',
        keterangan: 'AG-001',
    },
};

/** Cari akun pratinjau dari isian "Identitas Pengguna" (tidak peka huruf besar/kecil). */
export function cariAkunDemo(identitas) {
    const kunci = String(identitas ?? '').trim().toLowerCase();

    return Object.values(AKUN_DEMO).find((akun) => akun.identitas.toLowerCase() === kunci) ?? null;
}
