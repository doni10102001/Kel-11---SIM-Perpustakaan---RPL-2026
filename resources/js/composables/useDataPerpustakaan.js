/**
 * Store data pratinjau: salinan REAKTIF dari data mock terpusat.
 *
 * - Data awal (resources/js/data/mock/) tidak pernah diubah.
 * - Perubahan hanya berlaku selama sesi halaman; memuat ulang browser
 *   mengembalikan data awal. Tidak ada server, database, API, maupun localStorage.
 * - Setiap aksi berjalan ATOMIK (SRS AC-16, NFR-006, NFR-020):
 *     1. validasi aksi (utils/validasiMaster.js, utils/validasiTransaksi.js);
 *     2. perubahan diterapkan pada salinan draf;
 *     3. draf diperiksa periksaKonsistensi();
 *     4. hanya bila lolos, draf menggantikan state reaktif.
 *   Gagal di langkah mana pun → tidak ada perubahan sebagian.
 *
 * Memakai impor relatif agar dapat diuji langsung dengan Node (tests/js/).
 */
import { reactive } from 'vue';

import { TANGGAL_DEMO } from '../config/demo.js';
import { DATA_MOCK, ID_ADMIN_DEMO, periksaKonsistensi, salin } from '../data/mock/index.js';
import { kodeBerikutnya } from '../utils/pencarian.js';
import { normalKode, validasiAnggota, validasiBuku } from '../utils/validasiMaster.js';
import {
    validasiPeminjaman,
    validasiPengembalian,
    validasiRealisasi,
    validasiSetujuiBooking,
    validasiTolakBooking,
} from '../utils/validasiTransaksi.js';

const data = reactive(salin(DATA_MOCK));
let pesanTertunda = null;

const TAHUN_MAKS = Number(TANGGAL_DEMO.slice(0, 4));

/** Galat validasi yang dilempar ke halaman; `galat` berisi pesan per field. */
export class GalatValidasi extends Error {
    constructor(galat) {
        super(Object.values(galat)[0] ?? 'Data tidak valid.');
        this.name = 'GalatValidasi';
        this.galat = galat;
    }
}

function gagalBila(galat) {
    if (Object.keys(galat).length > 0) throw new GalatValidasi(galat);
}

/**
 * Jalankan perubahan secara atomik pada draf. `ubah(draf)` boleh melempar galat;
 * state reaktif hanya diganti bila draf konsisten.
 */
function jalankanTransaksi(aksi, ubah) {
    const draf = salin(data);
    const hasil = ubah(draf);

    const galat = periksaKonsistensi(draf, { tanggalAcuan: TANGGAL_DEMO });
    if (galat.length > 0) {
        console.error(`[data] ${aksi} dibatalkan: data tidak konsisten.`, galat);
        throw new GalatValidasi({ umum: 'Transaksi dibatalkan karena data tidak konsisten. Tidak ada perubahan yang diterapkan.' });
    }

    Object.keys(draf).forEach((kunci) => data[kunci].splice(0, data[kunci].length, ...draf[kunci]));

    return hasil;
}

// ---------------------------------------------------------------- Master data (Phase 3)

/** Tambah anggota baru. Akun login belum dibuat (members.user_id opsional, SRS 6.1). */
export function tambahAnggota(isian) {
    gagalBila(validasiAnggota(isian, data, { mode: 'tambah' }));

    return jalankanTransaksi('Tambah anggota', (d) => {
        const anggota = {
            kodeAnggota: normalKode(isian.kodeAnggota),
            idPengguna: null,
            nama: isian.nama.trim(),
            kontak: isian.kontak.trim(),
            status: isian.status,
        };
        d.anggota.push(anggota);

        return anggota;
    });
}

/** Ubah data anggota. Kode Anggota tidak diubah (keputusan prototipe). */
export function ubahAnggota(kodeAnggota, isian) {
    if (!data.anggota.some((a) => a.kodeAnggota === kodeAnggota)) throw new GalatValidasi({ umum: `Anggota ${kodeAnggota} tidak ditemukan.` });
    gagalBila(validasiAnggota(isian, data, { mode: 'ubah' }));

    return jalankanTransaksi('Ubah anggota', (d) => {
        const anggota = d.anggota.find((a) => a.kodeAnggota === kodeAnggota);
        anggota.nama = isian.nama.trim();
        anggota.kontak = isian.kontak.trim();
        anggota.status = isian.status;

        // Akun login anggota mengikuti nama dan status keanggotaan.
        const akun = d.pengguna.find((p) => p.idPengguna === anggota.idPengguna);
        if (akun) {
            akun.nama = anggota.nama;
            akun.statusAkun = anggota.status;
        }

        return anggota;
    });
}

const isianBuku = (isian) => ({
    judul: isian.judul.trim(),
    penulis: isian.penulis.trim(),
    penerbit: isian.penerbit.trim(),
    tahunTerbit: Number(String(isian.tahunTerbit).trim()),
    kategori: isian.kategori.trim(),
});

/** Tambah buku beserta eksemplar awalnya (minimal satu, status Tersedia). */
export function tambahBuku(isian, eksemplarBaru) {
    gagalBila(validasiBuku(isian, data, { mode: 'tambah', eksemplarBaru, tahunMaks: TAHUN_MAKS }));

    return jalankanTransaksi('Tambah buku', (d) => {
        const buku = { kodeBuku: normalKode(isian.kodeBuku), ...isianBuku(isian) };
        d.buku.push(buku);
        eksemplarBaru.forEach((e) => d.eksemplar.push({ kodeEksemplar: normalKode(e.kodeEksemplar), kodeBuku: buku.kodeBuku, status: 'Tersedia' }));

        return buku;
    });
}

/** Ubah data buku dan tambahkan eksemplar baru. Status eksemplar lama tidak diubah di sini. */
export function ubahBuku(kodeBuku, isian, eksemplarBaru) {
    if (!data.buku.some((b) => b.kodeBuku === kodeBuku)) throw new GalatValidasi({ umum: `Buku ${kodeBuku} tidak ditemukan.` });
    gagalBila(validasiBuku(isian, data, { mode: 'ubah', kodeAsal: kodeBuku, eksemplarBaru, tahunMaks: TAHUN_MAKS }));

    return jalankanTransaksi('Ubah buku', (d) => {
        const buku = d.buku.find((b) => b.kodeBuku === kodeBuku);
        Object.assign(buku, isianBuku(isian));
        eksemplarBaru.forEach((e) => d.eksemplar.push({ kodeEksemplar: normalKode(e.kodeEksemplar), kodeBuku, status: 'Tersedia' }));

        return buku;
    });
}

// ---------------------------------------------------------------- Transaksi (Phase 4)

const setStatusEksemplar = (d, kodeEksemplar, status) => {
    d.eksemplar.find((e) => e.kodeEksemplar === kodeEksemplar).status = status;
};

/**
 * Catat peminjaman (FR-009, FR-010, FR-024): peminjaman Aktif + eksemplar Dipinjam.
 * @param {{kodeAnggota, kodeBuku, kodeEksemplar, tanggalJatuhTempo}} isian
 */
export function catatPeminjaman(isian, { tanggalPinjam = TANGGAL_DEMO, diprosesOleh = ID_ADMIN_DEMO } = {}) {
    gagalBila(validasiPeminjaman(isian, data, { tanggalPinjam }));

    return jalankanTransaksi('Peminjaman', (d) => {
        const peminjaman = {
            idPeminjaman: kodeBerikutnya(d.peminjaman, 'idPeminjaman', 'PJ'),
            kodeAnggota: isian.kodeAnggota,
            kodeEksemplar: isian.kodeEksemplar,
            tanggalPinjam,
            tanggalJatuhTempo: isian.tanggalJatuhTempo,
            diprosesOleh,
            status: 'Aktif',
        };
        d.peminjaman.push(peminjaman);
        setStatusEksemplar(d, isian.kodeEksemplar, 'Dipinjam');

        return peminjaman;
    });
}

/**
 * Proses pengembalian (FR-011, FR-012, FR-027, FR-028): catat pengembalian,
 * peminjaman → Selesai, eksemplar → Tersedia.
 */
export function prosesPengembalian(idPeminjaman, { tanggalKembali = TANGGAL_DEMO, diprosesOleh = ID_ADMIN_DEMO } = {}) {
    gagalBila(validasiPengembalian(idPeminjaman, data));

    return jalankanTransaksi('Pengembalian', (d) => {
        const peminjaman = d.peminjaman.find((p) => p.idPeminjaman === idPeminjaman);
        const pengembalian = {
            idPengembalian: kodeBerikutnya(d.pengembalian, 'idPengembalian', 'KB'),
            idPeminjaman,
            tanggalKembali,
            diprosesOleh,
        };
        d.pengembalian.push(pengembalian);
        peminjaman.status = 'Selesai';
        setStatusEksemplar(d, peminjaman.kodeEksemplar, 'Tersedia');

        return pengembalian;
    });
}

/** Setujui booking (FR-033, FR-034): booking → Disetujui, eksemplar → Dipesan. */
export function setujuiBooking(idBooking, { tanggalKeputusan = TANGGAL_DEMO, diputuskanOleh = ID_ADMIN_DEMO } = {}) {
    gagalBila(validasiSetujuiBooking(idBooking, data));

    return jalankanTransaksi('Persetujuan booking', (d) => {
        const booking = d.booking.find((b) => b.idBooking === idBooking);
        Object.assign(booking, { status: 'Disetujui', tanggalKeputusan, diputuskanOleh });
        setStatusEksemplar(d, booking.kodeEksemplar, 'Dipesan');

        return booking;
    });
}

/** Tolak booking (FR-033): booking → Ditolak dengan alasan; eksemplar tidak dikunci. */
export function tolakBooking(idBooking, alasanPenolakan, { tanggalKeputusan = TANGGAL_DEMO, diputuskanOleh = ID_ADMIN_DEMO } = {}) {
    gagalBila(validasiTolakBooking(idBooking, alasanPenolakan, data));

    return jalankanTransaksi('Penolakan booking', (d) => {
        const booking = d.booking.find((b) => b.idBooking === idBooking);
        Object.assign(booking, { status: 'Ditolak', tanggalKeputusan, diputuskanOleh, alasanPenolakan: alasanPenolakan.trim() });

        return booking;
    });
}

/**
 * Realisasi booking (FR-026, FR-035): peminjaman Aktif baru, booking →
 * Direalisasikan (terhubung ke peminjaman), eksemplar Dipesan → Dipinjam.
 */
export function realisasikanBooking(idBooking, isian, { tanggalPinjam = TANGGAL_DEMO, diprosesOleh = ID_ADMIN_DEMO } = {}) {
    gagalBila(validasiRealisasi(idBooking, isian, data, { tanggalPinjam }));

    return jalankanTransaksi('Realisasi booking', (d) => {
        const booking = d.booking.find((b) => b.idBooking === idBooking);
        const peminjaman = {
            idPeminjaman: kodeBerikutnya(d.peminjaman, 'idPeminjaman', 'PJ'),
            kodeAnggota: booking.kodeAnggota,
            kodeEksemplar: booking.kodeEksemplar,
            tanggalPinjam,
            tanggalJatuhTempo: isian.tanggalJatuhTempo,
            diprosesOleh,
            status: 'Aktif',
        };
        d.peminjaman.push(peminjaman);
        booking.status = 'Direalisasikan';
        booking.idPeminjaman = peminjaman.idPeminjaman;
        setStatusEksemplar(d, booking.kodeEksemplar, 'Dipinjam');

        return { booking, peminjaman };
    });
}

// ---------------------------------------------------------------- Pesan & utilitas

/** Pesan satu kali untuk ditampilkan di halaman tujuan setelah penyimpanan. */
export function setPesan(pesan) {
    pesanTertunda = pesan;
}

export function ambilPesan() {
    const pesan = pesanTertunda;
    pesanTertunda = null;

    return pesan;
}

/** Kembalikan ke data awal (dipakai tes). */
export function aturUlangData() {
    const awal = salin(DATA_MOCK);
    Object.keys(awal).forEach((kunci) => data[kunci].splice(0, data[kunci].length, ...awal[kunci]));
    pesanTertunda = null;
}

/** Uji atomisitas: jalankan perubahan sembarang lewat jalur transaksi (khusus tes). */
export const _jalankanTransaksiUntukTes = jalankanTransaksi;

export function useDataPerpustakaan() {
    return {
        data,
        tahunMaks: TAHUN_MAKS,
        tanggalHariIni: TANGGAL_DEMO,
        tambahAnggota,
        ubahAnggota,
        tambahBuku,
        ubahBuku,
        catatPeminjaman,
        prosesPengembalian,
        setujuiBooking,
        tolakBooking,
        realisasikanBooking,
        setPesan,
        ambilPesan,
    };
}
