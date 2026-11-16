/**
 * Phase 8B — aksi TULIS yang belum terhubung ke database.
 *
 * Riwayat: sejak Phase 8B halaman membaca MySQL; aksi tulis yang belum
 * terhubung ke database DITOLAK secara eksplisit: tidak ada perubahan data di browser, tidak
 * ada pesan "berhasil", dan tidak ada data MySQL yang berubah. Halaman
 * menampilkan pesan ini lewat penanganan GalatValidasi yang sudah ada.
 */
import { GalatValidasi } from './galat.js';

export const PESAN_AKSI_BELUM_TERSEDIA =
    'Belum tersedia: penyimpanan aksi ini ke database dikerjakan pada tahap implementasi berikutnya. Tidak ada data yang diubah.';

/** Daftar aksi tulis yang menunggu implementasi server (nama fungsi lama → label). */
// Phase 9A: tambah/ubah Anggota (termasuk status & akses login saat tambah) kini tersimpan ke
// MySQL lewat Laravel; kredensial Anggota sejak Phase 8C. Tidak lagi ada di daftar ini.
// Phase 9B: tambah/ubah Buku beserta eksemplar baru kini tersimpan ke MySQL. Tidak lagi ada di daftar ini.
// Batch 2: peminjaman, pengembalian, setujui/tolak/realisasi booking, serta ajukan/batalkan
// booking kini tersimpan ke MySQL lewat Laravel. Tidak ada lagi aksi tulis yang tertunda;
// daftar ini sengaja kosong dan modul dipertahankan sebagai penjaga regresi.
export const AKSI_TERTUNDA = Object.freeze({});

/** @throws {GalatValidasi} selalu */
function tolak() {
    throw new GalatValidasi({ umum: PESAN_AKSI_BELUM_TERSEDIA });
}

/** Fungsi pengganti dengan nama yang sama; semuanya melempar GalatValidasi. */
export const aksiTulis = Object.freeze(Object.fromEntries(Object.keys(AKSI_TERTUNDA).map((nama) => [nama, tolak])));
