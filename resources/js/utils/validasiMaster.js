/**
 * Validasi formulir master data (Anggota, Buku, Eksemplar) — fungsi murni.
 *
 * Aturan:
 * - Field wajib diisi (SRS 3.1: field wajib ditandai).
 * - Kode Anggota unik (FR-023), Kode Buku & Kode Eksemplar unik (FR-021).
 * - Buku memiliki minimal satu eksemplar (SRS 6.2: Buku 1 → 1..* Eksemplar).
 * - Eksemplar baru selalu berstatus Tersedia; Dipesan/Dipinjam hanya berubah
 *   melalui transaksi booking/peminjaman (State Machine Eksemplar).
 * - Anggota boleh dinonaktifkan kapan pun tanpa menghapus riwayat (FR-022);
 *   transaksi yang sedang berjalan tidak dibatalkan.
 */

const MAKS_KODE = 20;

export const normalKode = (nilai) => String(nilai ?? '').trim();

const kosong = (nilai) => String(nilai ?? '').trim() === '';
const samaKode = (a, b) => normalKode(a).toLowerCase() === normalKode(b).toLowerCase();

function galatKode(kode, label) {
    if (kosong(kode)) return `${label} wajib diisi.`;
    if (/\s/.test(normalKode(kode))) return `${label} tidak boleh mengandung spasi.`;
    if (normalKode(kode).length > MAKS_KODE) return `${label} maksimal ${MAKS_KODE} karakter.`;

    return null;
}

const tanpaKosong = (galat) => Object.fromEntries(Object.entries(galat).filter(([, v]) => v));

/** Peminjaman Aktif dan booking yang masih berjalan milik seorang anggota. */
export function transaksiBerjalanAnggota(data, kodeAnggota) {
    return {
        peminjamanAktif: data.peminjaman.filter((p) => p.kodeAnggota === kodeAnggota && p.status === 'Aktif').length,
        bookingBerjalan: data.booking.filter(
            (b) => b.kodeAnggota === kodeAnggota && (b.status === 'Diajukan' || b.status === 'Disetujui'),
        ).length,
    };
}

/**
 * @param {{kodeAnggota, nama, kontak, status}} isian
 * @param {object} data   dataset aktif
 * @param {{mode: 'tambah'|'ubah', kodeAsal?: string}} opsi
 * @returns {Record<string, string>} galat per field (kosong = valid)
 */
export function validasiAnggota(isian, data, { mode }) {
    const galat = {};

    if (mode === 'tambah') {
        galat.kodeAnggota = galatKode(isian.kodeAnggota, 'Kode Anggota');
        if (!galat.kodeAnggota && data.anggota.some((a) => samaKode(a.kodeAnggota, isian.kodeAnggota))) {
            galat.kodeAnggota = `Kode Anggota ${normalKode(isian.kodeAnggota)} sudah digunakan anggota lain.`;
        }
    }

    if (kosong(isian.nama)) galat.nama = 'Nama wajib diisi.';
    else if (isian.nama.trim().length > 100) galat.nama = 'Nama maksimal 100 karakter.';

    if (kosong(isian.kontak)) galat.kontak = 'Kontak wajib diisi.';
    else if (isian.kontak.trim().length > 50) galat.kontak = 'Kontak maksimal 50 karakter.';

    if (!['Aktif', 'Nonaktif'].includes(isian.status)) {
        galat.status = 'Pilih Status Anggota.';
    }

    return tanpaKosong(galat);
}

/**
 * Validasi kode eksemplar baru sebelum ditambahkan ke daftar formulir.
 * @param {string} kode
 * @param {object} data            dataset aktif
 * @param {Array}  eksemplarBaru   eksemplar yang sudah ditambahkan di formulir (belum disimpan)
 * @returns {string|null}
 */
export function validasiKodeEksemplar(kode, data, eksemplarBaru = []) {
    const galat = galatKode(kode, 'Kode Eksemplar');
    if (galat) return galat;

    if (data.eksemplar.some((e) => samaKode(e.kodeEksemplar, kode)) || eksemplarBaru.some((e) => samaKode(e.kodeEksemplar, kode))) {
        return `Kode Eksemplar ${normalKode(kode)} sudah digunakan.`;
    }

    return null;
}

/** Status yang boleh dipilih untuk eksemplar baru. */
export function statusEksemplarBaruValid(status) {
    return status === 'Tersedia';
}

/**
 * @param {{kodeBuku, judul, penulis, penerbit, tahunTerbit, kategori}} isian
 * @param {object} data
 * @param {{mode: 'tambah'|'ubah', kodeAsal?: string, eksemplarBaru?: Array, tahunMaks: number}} opsi
 */
export function validasiBuku(isian, data, { mode, kodeAsal = null, eksemplarBaru = [], tahunMaks }) {
    const galat = {};

    if (mode === 'tambah') {
        galat.kodeBuku = galatKode(isian.kodeBuku, 'Kode Buku');
        if (!galat.kodeBuku && data.buku.some((b) => samaKode(b.kodeBuku, isian.kodeBuku))) {
            galat.kodeBuku = `Kode Buku ${normalKode(isian.kodeBuku)} sudah digunakan buku lain.`;
        }
    }

    if (kosong(isian.judul)) galat.judul = 'Judul wajib diisi.';
    if (kosong(isian.penulis)) galat.penulis = 'Penulis wajib diisi.';
    if (kosong(isian.penerbit)) galat.penerbit = 'Penerbit wajib diisi.';
    if (kosong(isian.kategori)) galat.kategori = 'Kategori wajib diisi.';

    const teksTahun = String(isian.tahunTerbit ?? '').trim();
    if (teksTahun === '') {
        galat.tahunTerbit = 'Tahun Terbit wajib diisi.';
    } else if (!/^\d{4}$/.test(teksTahun) || Number(teksTahun) > tahunMaks) {
        galat.tahunTerbit = `Tahun Terbit harus berupa 4 digit angka dan tidak melebihi ${tahunMaks}.`;
    }

    const kodeBuku = mode === 'ubah' ? kodeAsal : normalKode(isian.kodeBuku);
    const eksemplarLama = mode === 'ubah' ? data.eksemplar.filter((e) => e.kodeBuku === kodeBuku).length : 0;
    if (eksemplarLama + eksemplarBaru.length === 0) {
        galat.eksemplar = 'Tambahkan minimal satu eksemplar untuk buku ini.';
    }

    // Pemeriksaan ulang eksemplar baru (unik & status awal).
    const terlihat = [];
    for (const e of eksemplarBaru) {
        const g = validasiKodeEksemplar(e.kodeEksemplar, data, terlihat);
        if (g) {
            galat.eksemplar = g;
            break;
        }
        if (!statusEksemplarBaruValid(e.status)) {
            galat.eksemplar = 'Eksemplar baru harus berstatus Tersedia.';
            break;
        }
        terlihat.push(e);
    }

    return tanpaKosong(galat);
}
