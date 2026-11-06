/**
 * Validasi aksi transaksi (fungsi murni). Mengembalikan objek galat per field;
 * galat umum memakai kunci `umum`. Objek kosong = valid.
 *
 * Aturan (SRS v1.1):
 * - Peminjaman: anggota valid & Aktif (BR-01), eksemplar Tersedia tanpa peminjaman
 *   Aktif lain (BR-01, BR-05, FR-010, FR-025); eksemplar Dipesan hanya lewat
 *   realisasi booking (BR-07); jatuh tempo diisi manual (FR-024, TBD-05).
 * - Pengembalian: hanya peminjaman Aktif yang belum punya pengembalian (BR-03, FR-027).
 * - Setujui booking: booking Diajukan dan eksemplar Tersedia
 *   (State Machine Booking: setujui [eksemplarTersedia]; FR-034).
 * - Tolak booking: booking Diajukan dan alasan penolakan wajib (FR-033).
 * - Realisasi: booking Disetujui, anggota Aktif, eksemplar Dipesan untuk booking
 *   tersebut dan tanpa peminjaman Aktif (FR-026, FR-035, BR-07).
 */

const ISO = /^\d{4}-\d{2}-\d{2}$/;
const tanpaKosong = (galat) => Object.fromEntries(Object.entries(galat).filter(([, v]) => v));

function tanggalValid(nilai) {
    if (typeof nilai !== 'string' || !ISO.test(nilai)) return false;
    const [t, b, h] = nilai.split('-').map(Number);
    const d = new Date(Date.UTC(t, b - 1, h));

    return d.getUTCFullYear() === t && d.getUTCMonth() === b - 1 && d.getUTCDate() === h;
}

/** Galat Tanggal Jatuh Tempo: wajib, tanggal valid, setelah tanggal pinjam. */
export function galatJatuhTempo(tanggalJatuhTempo, tanggalPinjam) {
    if (!tanggalJatuhTempo) return 'Tanggal Jatuh Tempo wajib diisi.';
    if (!tanggalValid(tanggalJatuhTempo)) return 'Tanggal Jatuh Tempo tidak valid.';
    if (tanggalJatuhTempo <= tanggalPinjam) return 'Tanggal Jatuh Tempo harus setelah Tanggal Pinjam.';

    return null;
}

const peminjamanAktifEksemplar = (data, kode) => data.peminjaman.find((p) => p.kodeEksemplar === kode && p.status === 'Aktif');
const bookingDisetujuiEksemplar = (data, kode) => data.booking.find((b) => b.kodeEksemplar === kode && b.status === 'Disetujui');

/**
 * @param {{kodeAnggota, kodeBuku, kodeEksemplar, tanggalJatuhTempo}} isian
 * @param {object} data
 * @param {{tanggalPinjam: string}} opsi
 */
export function validasiPeminjaman(isian, data, { tanggalPinjam }) {
    const galat = {};

    const anggota = data.anggota.find((a) => a.kodeAnggota === isian.kodeAnggota);
    if (!isian.kodeAnggota) galat.kodeAnggota = 'Pilih anggota.';
    else if (!anggota) galat.kodeAnggota = 'Anggota tidak valid: kode anggota tidak terdaftar.';
    else if (anggota.status !== 'Aktif') galat.kodeAnggota = 'Anggota tidak valid: hanya anggota berstatus Aktif yang dapat meminjam.';

    const buku = data.buku.find((b) => b.kodeBuku === isian.kodeBuku);
    if (!isian.kodeBuku) galat.kodeBuku = 'Pilih buku.';
    else if (!buku) galat.kodeBuku = 'Buku tidak ditemukan.';

    const eksemplar = data.eksemplar.find((e) => e.kodeEksemplar === isian.kodeEksemplar);
    const adaTersedia = buku && data.eksemplar.some((e) => e.kodeBuku === buku.kodeBuku && e.status === 'Tersedia');
    if (!isian.kodeEksemplar) {
        galat.kodeEksemplar = buku && !adaTersedia
            ? 'Buku tidak tersedia: seluruh eksemplar sedang Dipinjam atau Dipesan.'
            : 'Pilih Kode Eksemplar.';
    } else if (!eksemplar || (buku && eksemplar.kodeBuku !== buku.kodeBuku)) {
        galat.kodeEksemplar = 'Kode Eksemplar tidak terdaftar untuk buku ini.';
    } else if (peminjamanAktifEksemplar(data, eksemplar.kodeEksemplar)) {
        galat.kodeEksemplar = `Buku tidak tersedia: eksemplar ${eksemplar.kodeEksemplar} masih dalam peminjaman Aktif.`;
    } else if (eksemplar.status === 'Dipesan' || bookingDisetujuiEksemplar(data, eksemplar.kodeEksemplar)) {
        galat.kodeEksemplar = `Buku tidak tersedia: eksemplar ${eksemplar.kodeEksemplar} sedang dipesan dan hanya dapat dipinjam melalui Realisasi Booking.`;
    } else if (eksemplar.status !== 'Tersedia') {
        galat.kodeEksemplar = `Buku tidak tersedia: eksemplar ${eksemplar.kodeEksemplar} berstatus ${eksemplar.status}.`;
    }

    galat.tanggalJatuhTempo = galatJatuhTempo(isian.tanggalJatuhTempo, tanggalPinjam);

    return tanpaKosong(galat);
}

/** @param {string} idPeminjaman */
export function validasiPengembalian(idPeminjaman, data) {
    const peminjaman = data.peminjaman.find((p) => p.idPeminjaman === idPeminjaman);

    if (!peminjaman) return { umum: `Transaksi ${idPeminjaman} tidak ditemukan.` };
    if (data.pengembalian.some((k) => k.idPeminjaman === idPeminjaman)) {
        return { umum: `Pengembalian ditolak: transaksi ${idPeminjaman} sudah dikembalikan.` };
    }
    if (peminjaman.status !== 'Aktif') return { umum: `Pengembalian ditolak: transaksi ${idPeminjaman} tidak aktif.` };

    return {};
}

/** Eksemplar dapat dipesan bila Tersedia, tanpa peminjaman Aktif, dan tanpa booking Disetujui lain. */
export function eksemplarDapatDipesan(data, kodeEksemplar) {
    const eksemplar = data.eksemplar.find((e) => e.kodeEksemplar === kodeEksemplar);

    return Boolean(eksemplar) && eksemplar.status === 'Tersedia' && !peminjamanAktifEksemplar(data, kodeEksemplar) && !bookingDisetujuiEksemplar(data, kodeEksemplar);
}

export function validasiSetujuiBooking(idBooking, data) {
    const booking = data.booking.find((b) => b.idBooking === idBooking);

    if (!booking) return { umum: `Booking ${idBooking} tidak ditemukan.` };
    if (booking.status !== 'Diajukan') return { umum: `Booking ${idBooking} berstatus ${booking.status}; hanya booking Diajukan yang dapat disetujui.` };
    if (!eksemplarDapatDipesan(data, booking.kodeEksemplar)) {
        const status = data.eksemplar.find((e) => e.kodeEksemplar === booking.kodeEksemplar)?.status ?? 'tidak ditemukan';

        return { umum: `Eksemplar ${booking.kodeEksemplar} tidak tersedia (status ${status}); booking hanya dapat ditolak.` };
    }

    return {};
}

export function validasiTolakBooking(idBooking, alasanPenolakan, data) {
    const booking = data.booking.find((b) => b.idBooking === idBooking);

    if (!booking) return { umum: `Booking ${idBooking} tidak ditemukan.` };
    if (booking.status !== 'Diajukan') return { umum: `Booking ${idBooking} berstatus ${booking.status}; hanya booking Diajukan yang dapat ditolak.` };

    const alasan = String(alasanPenolakan ?? '').trim();
    if (alasan === '') return { alasanPenolakan: 'Alasan Penolakan wajib diisi.' };
    if (alasan.length > 200) return { alasanPenolakan: 'Alasan Penolakan maksimal 200 karakter.' };

    return {};
}

export function validasiRealisasi(idBooking, isian, data, { tanggalPinjam }) {
    const booking = data.booking.find((b) => b.idBooking === idBooking);

    if (!booking) return { umum: `Booking ${idBooking} tidak ditemukan.` };
    if (booking.status !== 'Disetujui') {
        return { umum: `Booking ${idBooking} berstatus ${booking.status}; hanya booking Disetujui yang dapat direalisasikan.` };
    }

    const galat = {};
    const anggota = data.anggota.find((a) => a.kodeAnggota === booking.kodeAnggota);
    const eksemplar = data.eksemplar.find((e) => e.kodeEksemplar === booking.kodeEksemplar);

    if (!anggota || anggota.status !== 'Aktif') {
        galat.umum = `Anggota ${booking.kodeAnggota} tidak berstatus Aktif; booking tidak dapat direalisasikan.`;
    } else if (!eksemplar || eksemplar.status !== 'Dipesan' || bookingDisetujuiEksemplar(data, booking.kodeEksemplar)?.idBooking !== idBooking) {
        galat.umum = `Eksemplar ${booking.kodeEksemplar} tidak dipesan untuk booking ini.`;
    } else if (peminjamanAktifEksemplar(data, booking.kodeEksemplar)) {
        galat.umum = `Eksemplar ${booking.kodeEksemplar} masih dalam peminjaman Aktif.`;
    } else if (booking.tanggalKeputusan && tanggalPinjam < booking.tanggalKeputusan) {
        galat.umum = 'Tanggal Pinjam tidak boleh sebelum tanggal persetujuan booking.';
    }

    galat.tanggalJatuhTempo = galatJatuhTempo(isian.tanggalJatuhTempo, tanggalPinjam);

    return tanpaKosong(galat);
}
