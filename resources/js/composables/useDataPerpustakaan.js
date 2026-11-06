/**
 * Store data pratinjau: salinan REAKTIF dari data mock terpusat.
 *
 * - Data awal (resources/js/data/mock/) tidak pernah diubah.
 * - Perubahan (tambah/ubah anggota, buku, eksemplar) hanya berlaku selama sesi
 *   halaman; memuat ulang browser mengembalikan data awal. Tidak ada server,
 *   database, API, maupun localStorage.
 * - Setiap mutasi divalidasi ulang dan, di mode pengembangan, dicek dengan
 *   periksaKonsistensi() agar relasi dan status tetap sah.
 *
 * Memakai impor relatif agar dapat diuji langsung dengan Node (tests/js/).
 */
import { reactive } from 'vue';

import { TANGGAL_DEMO } from '../config/demo.js';
import { DATA_MOCK, periksaKonsistensi, salin } from '../data/mock/index.js';
import { normalKode, validasiAnggota, validasiBuku } from '../utils/validasiMaster.js';

const data = reactive(salin(DATA_MOCK));
let pesanTertunda = null;

const TAHUN_MAKS = Number(TANGGAL_DEMO.slice(0, 4));

function pastikanKonsisten(aksi) {
    const galat = periksaKonsistensi(data, { tanggalAcuan: TANGGAL_DEMO });

    if (galat.length > 0) {
        // Seharusnya tidak terjadi: validasi formulir mencegah data tidak konsisten.
        console.error(`[data] Data tidak konsisten setelah ${aksi}:`, galat);
    }

    return galat;
}

function gagalBila(galat) {
    if (Object.keys(galat).length > 0) {
        const error = new Error('Data tidak valid.');
        error.galat = galat;
        throw error;
    }
}

/** Tambah anggota baru. Akun login belum dibuat (members.user_id opsional, SRS 6.1). */
export function tambahAnggota(isian) {
    gagalBila(validasiAnggota(isian, data, { mode: 'tambah' }));

    const anggota = {
        kodeAnggota: normalKode(isian.kodeAnggota),
        idPengguna: null,
        nama: isian.nama.trim(),
        kontak: isian.kontak.trim(),
        status: isian.status,
    };
    data.anggota.push(anggota);
    pastikanKonsisten('tambah anggota');

    return anggota;
}

/** Ubah data anggota. Kode Anggota tidak dapat diubah karena dirujuk transaksi. */
export function ubahAnggota(kodeAnggota, isian) {
    const anggota = data.anggota.find((a) => a.kodeAnggota === kodeAnggota);
    if (!anggota) throw new Error(`Anggota ${kodeAnggota} tidak ditemukan.`);

    gagalBila(validasiAnggota(isian, data, { mode: 'ubah' }));

    anggota.nama = isian.nama.trim();
    anggota.kontak = isian.kontak.trim();
    anggota.status = isian.status;

    // Akun login anggota mengikuti nama dan status keanggotaan.
    const akun = data.pengguna.find((p) => p.idPengguna === anggota.idPengguna);
    if (akun) {
        akun.nama = anggota.nama;
        akun.statusAkun = anggota.status;
    }

    pastikanKonsisten('ubah anggota');

    return anggota;
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

    const buku = { kodeBuku: normalKode(isian.kodeBuku), ...isianBuku(isian) };
    data.buku.push(buku);
    eksemplarBaru.forEach((e) => data.eksemplar.push({ kodeEksemplar: normalKode(e.kodeEksemplar), kodeBuku: buku.kodeBuku, status: 'Tersedia' }));
    pastikanKonsisten('tambah buku');

    return buku;
}

/** Ubah data buku dan tambahkan eksemplar baru. Status eksemplar lama tidak diubah di sini. */
export function ubahBuku(kodeBuku, isian, eksemplarBaru) {
    const buku = data.buku.find((b) => b.kodeBuku === kodeBuku);
    if (!buku) throw new Error(`Buku ${kodeBuku} tidak ditemukan.`);

    gagalBila(validasiBuku(isian, data, { mode: 'ubah', kodeAsal: kodeBuku, eksemplarBaru, tahunMaks: TAHUN_MAKS }));

    Object.assign(buku, isianBuku(isian));
    eksemplarBaru.forEach((e) => data.eksemplar.push({ kodeEksemplar: normalKode(e.kodeEksemplar), kodeBuku, status: 'Tersedia' }));
    pastikanKonsisten('ubah buku');

    return buku;
}

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

export function useDataPerpustakaan() {
    return { data, tahunMaks: TAHUN_MAKS, tambahAnggota, ubahAnggota, tambahBuku, ubahBuku, setPesan, ambilPesan };
}
