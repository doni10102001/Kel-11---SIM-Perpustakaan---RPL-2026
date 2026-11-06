/**
 * Fungsi relasi antar-entitas (setara JOIN sederhana). Semua fungsi murni:
 * menerima `data` = { pengguna, anggota, buku, eksemplar, peminjaman, pengembalian, booking }.
 */

export const cariPengguna = (data, idPengguna) => data.pengguna.find((p) => p.idPengguna === idPengguna) ?? null;
export const cariAnggota = (data, kodeAnggota) => data.anggota.find((a) => a.kodeAnggota === kodeAnggota) ?? null;
export const cariBuku = (data, kodeBuku) => data.buku.find((b) => b.kodeBuku === kodeBuku) ?? null;
export const cariEksemplar = (data, kodeEksemplar) => data.eksemplar.find((e) => e.kodeEksemplar === kodeEksemplar) ?? null;
export const cariPeminjaman = (data, idPeminjaman) => data.peminjaman.find((p) => p.idPeminjaman === idPeminjaman) ?? null;
export const cariBooking = (data, idBooking) => data.booking.find((b) => b.idBooking === idBooking) ?? null;

/** Semua eksemplar milik satu buku. */
export const eksemplarBuku = (data, kodeBuku) => data.eksemplar.filter((e) => e.kodeBuku === kodeBuku);

/** Buku induk dari sebuah eksemplar. */
export function bukuDariEksemplar(data, kodeEksemplar) {
    const eksemplar = cariEksemplar(data, kodeEksemplar);

    return eksemplar ? cariBuku(data, eksemplar.kodeBuku) : null;
}

/** Jumlah eksemplar per status untuk satu buku — dasar kolom "Jumlah Eksemplar". */
export function ketersediaanBuku(data, kodeBuku) {
    const daftar = eksemplarBuku(data, kodeBuku);

    return {
        total: daftar.length,
        Tersedia: daftar.filter((e) => e.status === 'Tersedia').length,
        Dipesan: daftar.filter((e) => e.status === 'Dipesan').length,
        Dipinjam: daftar.filter((e) => e.status === 'Dipinjam').length,
    };
}

/** Pengembalian (0..1) untuk satu peminjaman. */
export const pengembalianPeminjaman = (data, idPeminjaman) =>
    data.pengembalian.find((k) => k.idPeminjaman === idPeminjaman) ?? null;

/** Booking yang direalisasikan menjadi peminjaman ini (bila ada). */
export const bookingAsalPeminjaman = (data, idPeminjaman) =>
    data.booking.find((b) => b.idPeminjaman === idPeminjaman) ?? null;

export const peminjamanAnggota = (data, kodeAnggota) => data.peminjaman.filter((p) => p.kodeAnggota === kodeAnggota);
export const bookingAnggota = (data, kodeAnggota) => data.booking.filter((b) => b.kodeAnggota === kodeAnggota);

/**
 * Status eksemplar yang SEHARUSNYA berdasarkan transaksi saat ini
 * (SRS 6.3 + State Machine Eksemplar):
 *   peminjaman Aktif                       → Dipinjam
 *   booking Disetujui belum direalisasikan → Dipesan
 *   selain itu                             → Tersedia
 */
export function statusEksemplarDariTransaksi(data, kodeEksemplar) {
    if (data.peminjaman.some((p) => p.kodeEksemplar === kodeEksemplar && p.status === 'Aktif')) {
        return 'Dipinjam';
    }

    if (data.booking.some((b) => b.kodeEksemplar === kodeEksemplar && b.status === 'Disetujui')) {
        return 'Dipesan';
    }

    return 'Tersedia';
}

/** Baris peminjaman lengkap dengan anggota, eksemplar, buku, pengembalian, dan pemroses. */
export function detailPeminjaman(data, peminjaman) {
    const pengembalian = pengembalianPeminjaman(data, peminjaman.idPeminjaman);

    return {
        ...peminjaman,
        anggota: cariAnggota(data, peminjaman.kodeAnggota),
        eksemplar: cariEksemplar(data, peminjaman.kodeEksemplar),
        buku: bukuDariEksemplar(data, peminjaman.kodeEksemplar),
        pemroses: cariPengguna(data, peminjaman.diprosesOleh),
        pengembalian,
        // Peminjaman Aktif tidak memiliki tanggal kembali.
        tanggalKembali: pengembalian?.tanggalKembali ?? null,
    };
}

/** Baris booking lengkap dengan anggota, eksemplar, buku, pemutus, dan peminjaman hasil realisasi. */
export function detailBooking(data, booking) {
    return {
        ...booking,
        anggota: cariAnggota(data, booking.kodeAnggota),
        eksemplar: cariEksemplar(data, booking.kodeEksemplar),
        buku: bukuDariEksemplar(data, booking.kodeEksemplar),
        pemutus: booking.diputuskanOleh ? cariPengguna(data, booking.diputuskanOleh) : null,
        peminjaman: booking.idPeminjaman ? cariPeminjaman(data, booking.idPeminjaman) : null,
    };
}
