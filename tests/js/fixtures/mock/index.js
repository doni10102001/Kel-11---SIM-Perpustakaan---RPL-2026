/**
 * FIXTURE PENGUJIAN (Phase 8B): data mock prototipe, dipindah dari
 * resources/js/data/mock/ agar TIDAK ikut dibundel ke aset publik. Hanya dipakai
 * test Node (tests/js/). Halaman aplikasi membaca data dari MySQL lewat Laravel.
 * Isinya identik dengan database/seeders/data/perpustakaan-demo.json.
 *
 * Titik masuk tunggal data mock SIM Perpustakaan.
 *
 * Semua halaman mengambil data dari sini — jangan menyalin rekaman ke file Vue.
 * Fungsi relasi/ringkasan menerima `data` sebagai parameter pertama
 * (mis. `ringkasanAdmin(DATA_MOCK)`) agar bisa dipakai juga pada salinan
 * reaktif (`salin(DATA_MOCK)`) saat simulasi transaksi di Phase 4–5.
 *
 * Konvensi: nama file mengikuti SRS 6.1 (members, books, ...); nama field
 * mengikuti Class Diagram (kodeBuku, judul, tahunTerbit, ...). Pemetaan lengkap:
 * docs/analysis/mock-data.md.
 */
import { BOOKING } from './bookings.js';
import { BUKU } from './books.js';
import { EKSEMPLAR } from './bookCopies.js';
import { PEMINJAMAN } from './loans.js';
import { ANGGOTA, KODE_ANGGOTA_DEMO } from './members.js';
import { PENGEMBALIAN } from './returns.js';
import { ID_ADMIN_DEMO, PENGGUNA } from './users.js';
import { bekukan, salin } from './util.js';

export { BOOKING, BUKU, EKSEMPLAR, PEMINJAMAN, ANGGOTA, PENGEMBALIAN, PENGGUNA, ID_ADMIN_DEMO, KODE_ANGGOTA_DEMO, salin };

/** Dataset lengkap (dibekukan). */
export const DATA_MOCK = bekukan({
    pengguna: PENGGUNA,
    anggota: ANGGOTA,
    buku: BUKU,
    eksemplar: EKSEMPLAR,
    peminjaman: PEMINJAMAN,
    pengembalian: PENGEMBALIAN,
    booking: BOOKING,
});

export * from '../../../../resources/js/utils/relasi.js';
export * from '../../../../resources/js/utils/ringkasan.js';
export { periksaKonsistensi } from './validasi.js';
