/**
 * Batch 3: halaman Laporan memakai `laporanDariServer` (baris & ringkasan dari
 * agregat SQL di server). `buatLaporan` dipertahankan sebagai spesifikasi
 * perilaku teruji (fixture) yang diikuti implementasi server.
 *
 * Penyusun laporan (fungsi murni) — SRS 4.6: laporan daftar buku, anggota,
 * peminjaman, dan pengembalian (FR-014), filter periode bila data periode
 * tersedia (FR-015), dan cetak (FR-029). Laporan adalah hasil olahan dataset,
 * bukan data terpisah (SRS 6.1 "reports [virtual]").
 */
import { detailPeminjaman, ketersediaanBuku } from './relasi.js';

export const JENIS_LAPORAN = [
    { value: 'buku', label: 'Daftar Buku', kolomTanggal: null },
    { value: 'anggota', label: 'Daftar Anggota', kolomTanggal: null },
    { value: 'peminjaman', label: 'Peminjaman', kolomTanggal: 'Tanggal Pinjam' },
    { value: 'pengembalian', label: 'Pengembalian', kolomTanggal: 'Tanggal Kembali' },
];

export const jenisLaporan = (value) => JENIS_LAPORAN.find((j) => j.value === value) ?? null;

/** Laporan dengan kolom tanggal mendukung filter periode. */
export const mendukungPeriode = (value) => Boolean(jenisLaporan(value)?.kolomTanggal);

const dalamPeriode = (tanggal, awal, akhir) => (!awal || tanggal >= awal) && (!akhir || tanggal <= akhir);

const NO = { key: 'no', label: 'No', align: 'center', width: '3.5rem' };

/** Batch 3 — definisi tampilan laporan (judul & kolom); data & ringkasan disusun SERVER. */
export const DEFINISI_LAPORAN = Object.freeze({
    buku: {
        judul: 'Laporan Daftar Buku',
        kolomTanggal: null,
        kolom: [
            NO,
            { key: 'kodeBuku', label: 'Kode Buku' },
            { key: 'judul', label: 'Judul' },
            { key: 'penulis', label: 'Penulis' },
            { key: 'penerbit', label: 'Penerbit' },
            { key: 'tahunTerbit', label: 'Tahun Terbit', align: 'center' },
            { key: 'kategori', label: 'Kategori' },
            { key: 'jumlahEksemplar', label: 'Jumlah Eksemplar', align: 'center' },
            { key: 'tersedia', label: 'Tersedia', align: 'center' },
        ],
    },
    anggota: {
        judul: 'Laporan Daftar Anggota',
        kolomTanggal: null,
        kolom: [NO, { key: 'kodeAnggota', label: 'Kode Anggota' }, { key: 'nama', label: 'Nama' }, { key: 'kontak', label: 'Kontak' }, { key: 'status', label: 'Status' }],
    },
    peminjaman: {
        judul: 'Laporan Peminjaman',
        kolomTanggal: 'Tanggal Pinjam',
        kolom: [
            NO,
            { key: 'idPeminjaman', label: 'ID Peminjaman' },
            { key: 'anggota', label: 'Anggota' },
            { key: 'buku', label: 'Buku' },
            { key: 'kodeEksemplar', label: 'Kode Eksemplar' },
            { key: 'tanggalPinjam', label: 'Tanggal Pinjam' },
            { key: 'tanggalJatuhTempo', label: 'Jatuh Tempo' },
            { key: 'status', label: 'Status' },
        ],
    },
    pengembalian: {
        judul: 'Laporan Pengembalian',
        kolomTanggal: 'Tanggal Kembali',
        kolom: [
            NO,
            { key: 'idPengembalian', label: 'ID Pengembalian' },
            { key: 'idPeminjaman', label: 'ID Peminjaman' },
            { key: 'anggota', label: 'Anggota' },
            { key: 'buku', label: 'Buku' },
            { key: 'kodeEksemplar', label: 'Kode Eksemplar' },
            { key: 'tanggalPinjam', label: 'Tanggal Pinjam' },
            { key: 'tanggalKembali', label: 'Tanggal Kembali' },
            { key: 'pemroses', label: 'Admin Pemroses' },
        ],
    },
});

/**
 * Batch 3 — laporan siap tampil dari prop server `data.laporan`
 * ({ jenis, baris, ringkasan }) + definisi tampilan. Null bila belum ada laporan.
 */
export function laporanDariServer(laporan) {
    const def = laporan ? DEFINISI_LAPORAN[laporan.jenis] : null;
    if (!def) return null;

    return { jenis: laporan.jenis, judul: def.judul, kolomTanggal: def.kolomTanggal, kolom: def.kolom, baris: laporan.baris ?? [], ringkasan: laporan.ringkasan ?? [] };
}

/** Galat filter laporan (objek kosong = valid). */
export function validasiFilterLaporan({ jenis, awal, akhir }) {
    const galat = {};
    if (!jenisLaporan(jenis)) galat.jenis = 'Pilih Jenis Laporan.';
    if (mendukungPeriode(jenis) && awal && akhir && awal > akhir) {
        galat.akhir = 'Periode Akhir tidak boleh sebelum Periode Awal.';
    }

    return galat;
}

/**
 * @returns {{ jenis, judul, kolomTanggal, kolom: Array, baris: Array, ringkasan: Array<{label, nilai}> }}
 */
export function buatLaporan(data, { jenis, awal = '', akhir = '' }) {
    const info = jenisLaporan(jenis);
    if (!info) throw new Error(`Jenis laporan "${jenis}" tidak dikenal.`);

    const urut = (a, b, kunci) => String(a[kunci]).localeCompare(String(b[kunci]));
    const nomori = (daftar) => daftar.map((r, i) => ({ no: i + 1, ...r }));

    if (jenis === 'buku') {
        const baris = [...data.buku]
            .sort((a, b) => urut(a, b, 'kodeBuku'))
            .map((b) => {
                const k = ketersediaanBuku(data, b.kodeBuku);

                return { ...b, jumlahEksemplar: k.total, tersedia: k.Tersedia, dipesan: k.Dipesan, dipinjam: k.Dipinjam };
            });

        return {
            jenis,
            judul: 'Laporan Daftar Buku',
            kolomTanggal: null,
            kolom: [
                { key: 'no', label: 'No', align: 'center', width: '3.5rem' },
                { key: 'kodeBuku', label: 'Kode Buku' },
                { key: 'judul', label: 'Judul' },
                { key: 'penulis', label: 'Penulis' },
                { key: 'penerbit', label: 'Penerbit' },
                { key: 'tahunTerbit', label: 'Tahun Terbit', align: 'center' },
                { key: 'kategori', label: 'Kategori' },
                { key: 'jumlahEksemplar', label: 'Jumlah Eksemplar', align: 'center' },
                { key: 'tersedia', label: 'Tersedia', align: 'center' },
            ],
            baris: nomori(baris),
            ringkasan: [
                { label: 'Jumlah buku', nilai: baris.length },
                { label: 'Jumlah eksemplar', nilai: baris.reduce((n, b) => n + b.jumlahEksemplar, 0) },
                { label: 'Eksemplar Tersedia', nilai: baris.reduce((n, b) => n + b.tersedia, 0) },
                { label: 'Eksemplar Dipesan', nilai: baris.reduce((n, b) => n + b.dipesan, 0) },
                { label: 'Eksemplar Dipinjam', nilai: baris.reduce((n, b) => n + b.dipinjam, 0) },
            ],
        };
    }

    if (jenis === 'anggota') {
        const baris = [...data.anggota].sort((a, b) => urut(a, b, 'kodeAnggota'));

        return {
            jenis,
            judul: 'Laporan Daftar Anggota',
            kolomTanggal: null,
            kolom: [
                { key: 'no', label: 'No', align: 'center', width: '3.5rem' },
                { key: 'kodeAnggota', label: 'Kode Anggota' },
                { key: 'nama', label: 'Nama' },
                { key: 'kontak', label: 'Kontak' },
                { key: 'status', label: 'Status' },
            ],
            baris: nomori(baris),
            ringkasan: [
                { label: 'Jumlah anggota', nilai: baris.length },
                { label: 'Aktif', nilai: baris.filter((a) => a.status === 'Aktif').length },
                { label: 'Nonaktif', nilai: baris.filter((a) => a.status === 'Nonaktif').length },
            ],
        };
    }

    const semua = data.peminjaman.map((p) => detailPeminjaman(data, p));

    if (jenis === 'peminjaman') {
        // Periode berdasarkan Tanggal Pinjam.
        const baris = semua
            .filter((p) => dalamPeriode(p.tanggalPinjam, awal, akhir))
            .sort((a, b) => urut(a, b, 'tanggalPinjam') || urut(a, b, 'idPeminjaman'));

        return {
            jenis,
            judul: 'Laporan Peminjaman',
            kolomTanggal: 'Tanggal Pinjam',
            kolom: [
                { key: 'no', label: 'No', align: 'center', width: '3.5rem' },
                { key: 'idPeminjaman', label: 'ID Peminjaman' },
                { key: 'anggota', label: 'Anggota' },
                { key: 'buku', label: 'Buku' },
                { key: 'kodeEksemplar', label: 'Kode Eksemplar' },
                { key: 'tanggalPinjam', label: 'Tanggal Pinjam' },
                { key: 'tanggalJatuhTempo', label: 'Jatuh Tempo' },
                { key: 'status', label: 'Status' },
            ],
            baris: nomori(baris),
            ringkasan: [
                { label: 'Jumlah peminjaman', nilai: baris.length },
                { label: 'Aktif', nilai: baris.filter((p) => p.status === 'Aktif').length },
                { label: 'Selesai', nilai: baris.filter((p) => p.status === 'Selesai').length },
            ],
        };
    }

    // Pengembalian — periode berdasarkan Tanggal Kembali.
    const pemroses = new Map(data.pengguna.map((u) => [u.idPengguna, u]));
    const baris = data.pengembalian
        .map((k) => {
            const p = semua.find((x) => x.idPeminjaman === k.idPeminjaman);

            return { ...k, anggota: p.anggota, buku: p.buku, kodeEksemplar: p.kodeEksemplar, tanggalPinjam: p.tanggalPinjam, pemroses: pemroses.get(k.diprosesOleh) };
        })
        .filter((k) => dalamPeriode(k.tanggalKembali, awal, akhir))
        .sort((a, b) => urut(a, b, 'tanggalKembali') || urut(a, b, 'idPengembalian'));

    return {
        jenis,
        judul: 'Laporan Pengembalian',
        kolomTanggal: 'Tanggal Kembali',
        kolom: [
            { key: 'no', label: 'No', align: 'center', width: '3.5rem' },
            { key: 'idPengembalian', label: 'ID Pengembalian' },
            { key: 'idPeminjaman', label: 'ID Peminjaman' },
            { key: 'anggota', label: 'Anggota' },
            { key: 'buku', label: 'Buku' },
            { key: 'kodeEksemplar', label: 'Kode Eksemplar' },
            { key: 'tanggalPinjam', label: 'Tanggal Pinjam' },
            { key: 'tanggalKembali', label: 'Tanggal Kembali' },
            { key: 'pemroses', label: 'Admin Pemroses' },
        ],
        baris: nomori(baris),
        ringkasan: [{ label: 'Jumlah pengembalian', nilai: baris.length }],
    };
}
