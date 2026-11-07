/**
 * Konfigurasi pratinjau statis (khusus pengembang).
 *
 * Prototipe memakai DUA peran: Admin dan Anggota.
 * TIDAK ADA autentikasi server. Halaman Masuk memakai `masuk()` di
 * composables/useDataPerpustakaan.js (simulasi di browser):
 *  - akun pratinjau di bawah menerima kata sandi apa pun yang tidak kosong
 *    SELAMA kata sandinya belum diatur Admin;
 *  - akun lain hanya dapat masuk dengan kata sandi yang ditetapkan Admin
 *    (Akses Login Anggota) dan hanya selama sesi browser yang sama.
 */
import { ANGGOTA, KODE_ANGGOTA_DEMO } from '../data/mock/members.js';
import { ID_ADMIN_DEMO, PENGGUNA } from '../data/mock/users.js';

/**
 * "Hari ini" yang tetap untuk pratinjau, agar tampilan, status, dan screenshot
 * deterministik. Format ISO (YYYY-MM-DD), zona waktu Asia/Jakarta.
 * Seluruh tanggal transaksi di data mock tidak melewati tanggal ini.
 */
export const TANGGAL_DEMO = '2026-11-07';

const admin = PENGGUNA.find((p) => p.idPengguna === ID_ADMIN_DEMO);
const anggota = ANGGOTA.find((a) => a.kodeAnggota === KODE_ANGGOTA_DEMO);

/**
 * Akun pratinjau per peran. Kunci = nilai `peran` dari rute /demo/{peran}/...
 * Nama dan kode diambil dari dataset terpusat (resources/js/data/mock/).
 */
export const AKUN_DEMO = Object.freeze({
    admin: Object.freeze({
        peran: 'admin',
        identitas: admin.username,
        idPengguna: admin.idPengguna,
        nama: admin.nama,
        labelPeran: 'Admin',
        keterangan: 'Admin Perpustakaan',
    }),
    anggota: Object.freeze({
        peran: 'anggota',
        identitas: anggota.kodeAnggota,
        idPengguna: anggota.idPengguna,
        kodeAnggota: anggota.kodeAnggota,
        nama: anggota.nama,
        labelPeran: 'Anggota',
        keterangan: anggota.kodeAnggota,
    }),
});

/** Cari akun pratinjau dari isian "Identitas Pengguna" (tidak peka huruf besar/kecil). */
export function cariAkunDemo(identitas) {
    const kunci = String(identitas ?? '').trim().toLowerCase();

    return Object.values(AKUN_DEMO).find((akun) => akun.identitas.toLowerCase() === kunci) ?? null;
}
