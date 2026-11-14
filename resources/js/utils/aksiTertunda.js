/**
 * Phase 8B — aksi TULIS yang belum terhubung ke database.
 *
 * Halaman kini membaca data dari MySQL. Aksi simpan/ubah (anggota, buku,
 * peminjaman, pengembalian, booking) BELUM disimpan ke database, jadi setiap
 * aksi DITOLAK secara eksplisit: tidak ada perubahan data di browser, tidak
 * ada pesan "berhasil", dan tidak ada data MySQL yang berubah. Halaman
 * menampilkan pesan ini lewat penanganan GalatValidasi yang sudah ada.
 */
import { GalatValidasi } from './galat.js';

export const PESAN_AKSI_BELUM_TERSEDIA =
    'Belum tersedia: penyimpanan aksi ini ke database dikerjakan pada tahap implementasi berikutnya. Tidak ada data yang diubah.';

/** Daftar aksi tulis yang menunggu implementasi server (nama fungsi lama → label). */
export const AKSI_TERTUNDA = Object.freeze({
    tambahAnggota: 'Tambah Anggota (termasuk Akses Login Anggota)',
    ubahAnggota: 'Ubah Anggota (termasuk status, akses login, reset kata sandi)',
    tambahBuku: 'Tambah Buku dan eksemplar',
    ubahBuku: 'Ubah Buku dan tambah eksemplar',
    catatPeminjaman: 'Catat Peminjaman',
    prosesPengembalian: 'Proses Pengembalian',
    setujuiBooking: 'Setujui Booking',
    tolakBooking: 'Tolak Booking',
    realisasikanBooking: 'Realisasi Booking',
    ajukanBooking: 'Ajukan Booking',
    batalkanBooking: 'Batalkan Booking',
});

/** @throws {GalatValidasi} selalu */
function tolak() {
    throw new GalatValidasi({ umum: PESAN_AKSI_BELUM_TERSEDIA });
}

/** Fungsi pengganti dengan nama yang sama; semuanya melempar GalatValidasi. */
export const aksiTulis = Object.freeze(Object.fromEntries(Object.keys(AKSI_TERTUNDA).map((nama) => [nama, tolak])));
