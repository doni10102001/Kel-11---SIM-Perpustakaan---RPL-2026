/**
 * Store data pratinjau: salinan REAKTIF dari data mock terpusat.
 *
 * - Data awal (resources/js/data/mock/) tidak pernah diubah.
 * - Perubahan hanya berlaku selama sesi halaman; memuat ulang browser
 *   mengembalikan data awal. Tidak ada server, database, API, maupun localStorage.
 * - Akses login Anggota (pasca-Final Audit) juga hanya di memori; kata sandi
 *   disimpan sebagai hash bergaram, BUKAN autentikasi aman.
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

import { AKUN_DEMO, TANGGAL_DEMO } from '../config/demo.js';
import { DATA_MOCK, ID_ADMIN_DEMO, periksaKonsistensi, salin } from '../data/mock/index.js';
import { kodeBerikutnya } from '../utils/pencarian.js';
import { cocokKataSandi, hashKataSandi } from '../utils/kredensial.js';
import { normalKode, relasiAkunValid, samaUsername, validasiAksesLogin, validasiAnggota, validasiBuku } from '../utils/validasiMaster.js';
import {
    validasiAjukanBooking,
    validasiBatalkanBooking,
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

/**
 * Akun login baru untuk anggota (peran anggota, kata sandi disimpan sebagai hash).
 * `statusAkun` dipertahankan untuk kompatibilitas (Class Diagram Pengguna.statusAkun)
 * tetapi SELALU diturunkan dari Status Anggota — tidak ada Status Akses terpisah.
 */
function buatAkunAnggota(d, anggota, akses) {
    const akun = {
        idPengguna: kodeBerikutnya(d.pengguna, 'idPengguna', 'USR'),
        username: akses.username.trim(),
        nama: anggota.nama,
        peran: 'anggota',
        statusAkun: anggota.status,
        passwordHash: hashKataSandi(akses.kataSandi),
    };
    d.pengguna.push(akun);
    anggota.idPengguna = akun.idPengguna;

    return akun;
}

/**
 * Tambah anggota baru (FR-005). Akses login opsional (members.user_id?, SRS 6.1):
 * bila `akses.buatAkses`, anggota + akun dibuat dan dihubungkan dalam SATU transaksi.
 * @param {{kodeAnggota, nama, kontak, status}} isian
 * @param {{buatAkses, username, kataSandi, konfirmasiKataSandi}|null} akses
 */
export function tambahAnggota(isian, akses = null) {
    gagalBila({ ...validasiAnggota(isian, data, { mode: 'tambah' }), ...validasiAksesLogin(akses, data) });

    return jalankanTransaksi('Tambah anggota', (d) => {
        const anggota = {
            kodeAnggota: normalKode(isian.kodeAnggota),
            idPengguna: null,
            nama: isian.nama.trim(),
            kontak: isian.kontak.trim(),
            status: isian.status,
        };
        d.anggota.push(anggota);
        if (akses?.buatAkses) buatAkunAnggota(d, anggota, akses);

        return anggota;
    });
}

/**
 * Ubah data anggota (FR-006). Kode Anggota tidak diubah (keputusan prototipe).
 * Akses login (opsional):
 *  - anggota tanpa akun + `akses.buatAkses` → akun baru dibuat & dihubungkan;
 *  - anggota dengan akun → kata sandi hanya berubah bila `akses.resetKataSandi`
 *    (kata sandi lama tidak pernah dibaca/ditampilkan).
 * Status Anggota adalah SATU-SATUNYA sumber status: `statusAkun` akun anggota
 * selalu disalin dari Status Anggota (nilai `akses.statusAkun` diabaikan).
 * Akun dan riwayat transaksi tidak pernah dihapus karena perubahan status.
 */
export function ubahAnggota(kodeAnggota, isian, akses = null) {
    const lama = data.anggota.find((a) => a.kodeAnggota === kodeAnggota);
    if (!lama) throw new GalatValidasi({ umum: `Anggota ${kodeAnggota} tidak ditemukan.` });
    gagalBila({ ...validasiAnggota(isian, data, { mode: 'ubah' }), ...validasiAksesLogin(akses, data, { anggota: lama }) });

    return jalankanTransaksi('Ubah anggota', (d) => {
        const anggota = d.anggota.find((a) => a.kodeAnggota === kodeAnggota);
        anggota.nama = isian.nama.trim();
        anggota.kontak = isian.kontak.trim();
        anggota.status = isian.status;

        const akun = d.pengguna.find((p) => p.idPengguna === anggota.idPengguna);
        if (akun) {
            // Nama dan status akun mengikuti data anggota.
            akun.nama = anggota.nama;
            akun.statusAkun = anggota.status;
            if (akses?.resetKataSandi) akun.passwordHash = hashKataSandi(akses.kataSandi);
        } else if (akses?.buatAkses) {
            buatAkunAnggota(d, anggota, akses);
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

// ---------------------------------------------------------------- Booking oleh Anggota (Phase 5)

/**
 * Ajukan booking (FR-031): booking baru berstatus Diajukan. Eksemplar TIDAK
 * dikunci — status Dipesan baru diberikan saat Admin menyetujui (FR-034).
 * @param {{kodeBuku?: string, kodeEksemplar: string}} isian
 */
export function ajukanBooking(isian, { kodeAnggota, tanggalBooking = TANGGAL_DEMO }) {
    gagalBila(validasiAjukanBooking(isian, data, { kodeAnggota }));

    return jalankanTransaksi('Pengajuan booking', (d) => {
        const booking = {
            idBooking: kodeBerikutnya(d.booking, 'idBooking', 'BP'),
            kodeAnggota,
            kodeEksemplar: isian.kodeEksemplar,
            tanggalBooking,
            status: 'Diajukan',
            tanggalKeputusan: null,
            diputuskanOleh: null,
            alasanPenolakan: null,
            idPeminjaman: null,
        };
        d.booking.push(booking);

        return booking;
    });
}

/**
 * Batalkan booking milik anggota (FR-032): Diajukan/Disetujui → Dibatalkan.
 * Bila sudah Disetujui, reservasi eksemplar dilepas (Dipesan → Tersedia, FR-035).
 * Tanggal pembatalan tidak dicatat karena tidak ada field-nya di SRS.
 */
export function batalkanBooking(idBooking, { kodeAnggota }) {
    gagalBila(validasiBatalkanBooking(idBooking, data, { kodeAnggota }));

    return jalankanTransaksi('Pembatalan booking', (d) => {
        const booking = d.booking.find((b) => b.idBooking === idBooking);
        const melepasReservasi = booking.status === 'Disetujui';
        booking.status = 'Dibatalkan';
        if (melepasReservasi) setStatusEksemplar(d, booking.kodeEksemplar, 'Tersedia');

        return booking;
    });
}

// ---------------------------------------------------------------- Masuk SIMULASI (pasca-Final Audit)
//
// PHASE 8A: fungsi di bagian ini TIDAK lagi dipakai untuk autentikasi. Halaman
// Masuk, kerangka aplikasi, dan useDemo() memakai sesi server Laravel
// (POST /login, POST /logout, prop `auth.akun`); setiap rute /admin/* dan
// /anggota/* ditolak server tanpa sesi yang sah. Fungsi ini dipertahankan hanya
// sebagai aturan data mock untuk fitur "Akses Login Anggota" (diganti Phase 8C)
// dan diuji oleh tests/js/akses-login.test.js. tests/js/autentikasi-server.test.js
// memastikan tidak ada halaman/layout yang memanggilnya lagi.

/**
 * Akun yang sedang "masuk" pada sesi SPA ini. Hanya `idPengguna` yang disimpan,
 * di memori — tidak ada token, cookie, localStorage, maupun sessionStorage.
 * Muat ulang browser menghapusnya (halaman kembali memakai akun pratinjau).
 */
const sesi = reactive({ idPengguna: null });

/** Akun pratinjau bawaan: menerima kata sandi apa pun SELAMA Admin belum mengatur kata sandinya. */
const ID_AKUN_PRATINJAU = new Set(Object.values(AKUN_DEMO).map((a) => a.idPengguna));

export const PESAN_MASUK = Object.freeze({
    // Generik (FR-003): tidak menyebut apakah username atau kata sandi yang salah.
    gagal: 'Identitas pengguna atau kata sandi tidak sesuai. Periksa kembali, lalu coba lagi.',
    // Hanya untuk akun Admin; status login Anggota mengikuti Status Anggota.
    akunNonaktif: 'Akun ini sedang nonaktif. Hubungi Admin perpustakaan.',
    anggotaNonaktif: 'Keanggotaan Anda sedang nonaktif sehingga tidak dapat masuk. Hubungi Admin perpustakaan.',
    relasiTidakValid: 'Akun ini belum terhubung dengan data anggota yang valid. Hubungi Admin perpustakaan.',
});

/**
 * Verifikasi masuk SIMULASI. Urutan: (1) username ditemukan, (2) kata sandi cocok,
 * lalu untuk Anggota (3) akun terhubung ke tepat satu anggota yang valid dan
 * (4) Status Anggota = Aktif — satu-satunya sumber izin login Anggota.
 * Akun Admin tidak terpengaruh aturan Status Anggota. Status baru diungkap setelah
 * kredensial cocok agar pesan tidak membocorkan keberadaan akun.
 * Berhasil → sesi diisi; gagal → GalatValidasi { umum } tanpa mengubah sesi.
 * BUKAN autentikasi aman: berjalan sepenuhnya di browser (lihat utils/kredensial.js).
 * @returns {{idPengguna: string, peran: 'admin'|'anggota'}}
 */
export function masuk(identitas, kataSandi) {
    const akun = data.pengguna.find((p) => samaUsername(p.username, identitas));
    const sandi = String(kataSandi ?? '');

    const cocok =
        Boolean(akun) &&
        sandi !== '' &&
        (akun.passwordHash ? cocokKataSandi(sandi, akun.passwordHash) : ID_AKUN_PRATINJAU.has(akun.idPengguna));
    if (!cocok) throw new GalatValidasi({ umum: PESAN_MASUK.gagal });

    if (akun.peran === 'anggota') {
        const terhubung = data.anggota.filter((a) => a.idPengguna === akun.idPengguna);
        if (terhubung.length !== 1 || !relasiAkunValid(data, terhubung[0])) throw new GalatValidasi({ umum: PESAN_MASUK.relasiTidakValid });
        if (terhubung[0].status !== 'Aktif') throw new GalatValidasi({ umum: PESAN_MASUK.anggotaNonaktif });
    } else if (akun.peran === 'admin') {
        if (akun.statusAkun !== 'Aktif') throw new GalatValidasi({ umum: PESAN_MASUK.akunNonaktif });
    } else {
        throw new GalatValidasi({ umum: PESAN_MASUK.gagal });
    }

    sesi.idPengguna = akun.idPengguna;

    return { idPengguna: akun.idPengguna, peran: akun.peran };
}

/** Keluar: hapus akun aktif dari sesi SPA. */
export function keluar() {
    sesi.idPengguna = null;
}

/**
 * Akun aktif untuk halaman berperan `peran` (header, data milik Anggota).
 * Bila belum masuk pada sesi ini (mis. URL dibuka langsung atau setelah muat
 * ulang), dipakai akun pratinjau bawaan AKUN_DEMO[peran] seperti sebelumnya.
 */
export function akunAktif(peran) {
    const akun = sesi.idPengguna ? data.pengguna.find((p) => p.idPengguna === sesi.idPengguna) : null;

    if (akun && akun.peran === peran) {
        if (peran === 'anggota') {
            const anggota = data.anggota.find((a) => a.idPengguna === akun.idPengguna);
            if (anggota) {
                return {
                    peran: 'anggota',
                    identitas: akun.username,
                    idPengguna: akun.idPengguna,
                    kodeAnggota: anggota.kodeAnggota,
                    nama: anggota.nama,
                    labelPeran: 'Anggota',
                    keterangan: anggota.kodeAnggota,
                };
            }
        } else if (peran === 'admin') {
            return { ...AKUN_DEMO.admin, idPengguna: akun.idPengguna, identitas: akun.username, nama: akun.nama };
        }
    }

    return AKUN_DEMO[peran] ?? null;
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
    sesi.idPengguna = null;
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
        ajukanBooking,
        batalkanBooking,
        masuk,
        keluar,
        akunAktif,
        setPesan,
        ambilPesan,
    };
}
