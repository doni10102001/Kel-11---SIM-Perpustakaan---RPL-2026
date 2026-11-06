/**
 * Ringkasan dashboard — SELALU dihitung dari dataset, tidak ada angka tetap.
 * Fungsi murni: menerima `data` agar tetap benar setelah simulasi transaksi.
 */
import { bookingAnggota, peminjamanAnggota } from './relasi.js';

const hitung = (daftar, cocok) => daftar.filter(cocok).length;

/** Ringkasan untuk Dashboard Admin. */
export function ringkasanAdmin(data) {
    return {
        anggota: {
            total: data.anggota.length,
            Aktif: hitung(data.anggota, (a) => a.status === 'Aktif'),
            Nonaktif: hitung(data.anggota, (a) => a.status === 'Nonaktif'),
        },
        buku: {
            total: data.buku.length,
        },
        eksemplar: {
            total: data.eksemplar.length,
            Tersedia: hitung(data.eksemplar, (e) => e.status === 'Tersedia'),
            Dipesan: hitung(data.eksemplar, (e) => e.status === 'Dipesan'),
            Dipinjam: hitung(data.eksemplar, (e) => e.status === 'Dipinjam'),
        },
        peminjaman: {
            Aktif: hitung(data.peminjaman, (p) => p.status === 'Aktif'),
            Selesai: hitung(data.peminjaman, (p) => p.status === 'Selesai'),
        },
        booking: {
            // Menunggu verifikasi Admin.
            Diajukan: hitung(data.booking, (b) => b.status === 'Diajukan'),
            // Sudah disetujui, menunggu realisasi menjadi peminjaman.
            Disetujui: hitung(data.booking, (b) => b.status === 'Disetujui'),
        },
    };
}

/**
 * Rekap jumlah per status untuk satu daftar, berurutan sesuai kosakata resmi.
 * @param {Array<{status: string}>} daftar
 * @param {string[]} urutanStatus  mis. daftarStatus('booking')
 * @returns {Array<{status: string, jumlah: number}>}
 */
export function rekapStatus(daftar, urutanStatus) {
    return urutanStatus.map((status) => ({ status, jumlah: hitung(daftar, (r) => r.status === status) }));
}

/** Ringkasan untuk Dashboard Anggota — hanya data milik anggota tersebut. */
export function ringkasanAnggota(data, kodeAnggota) {
    const booking = bookingAnggota(data, kodeAnggota);
    const peminjaman = peminjamanAnggota(data, kodeAnggota);

    return {
        // Booking yang masih berjalan: menunggu keputusan atau menunggu realisasi.
        bookingAktif: hitung(booking, (b) => b.status === 'Diajukan' || b.status === 'Disetujui'),
        peminjamanAktif: hitung(peminjaman, (p) => p.status === 'Aktif'),
        riwayatSelesai: hitung(peminjaman, (p) => p.status === 'Selesai'),
    };
}
