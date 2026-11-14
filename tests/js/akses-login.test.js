/**
 * Tes fitur pasca-Final Audit: Akses Login Anggota (dikelola Admin) dan masuk
 * SIMULASI. Bukan pengujian autentikasi aman — tidak ada server.
 * Jalankan: npm run test:data
 */
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { beforeEach, describe, test } from 'node:test';

import {
    GalatValidasi,
    PESAN_MASUK,
    _jalankanTransaksiUntukTes,
    ajukanBooking,
    akunAktif,
    aturUlangData,
    catatPeminjaman,
    keluar,
    masuk,
    prosesPengembalian,
    realisasikanBooking,
    setujuiBooking,
    tambahAnggota,
    ubahAnggota,
    useDataPerpustakaan,
} from './fixtures/storeMock.js';
import { AKUN_DEMO, TANGGAL_DEMO } from './fixtures/demo.js';
import { DATA_MOCK, bookingAnggota, peminjamanAnggota, periksaKonsistensi, ringkasanAnggota } from './fixtures/mock/index.js';
import { POLA_HASH } from '../../resources/js/utils/kredensial.js';
import { validasiAksesLogin } from '../../resources/js/utils/validasiMaster.js';

const { data } = useDataPerpustakaan();
// Kata sandi uji dibangkitkan acak saat tes berjalan: tidak ada kredensial literal di repositori.
const acak = () => `Uji-${randomUUID()}`;
const SANDI = acak();
const SANDI_RESET = acak();
const potret = () => JSON.stringify(data);
const konsisten = () => assert.deepEqual(periksaKonsistensi(data, { tanggalAcuan: TANGGAL_DEMO }), []);
const anggota = (kode) => data.anggota.find((a) => a.kodeAnggota === kode);
const akunDari = (kode) => data.pengguna.find((p) => p.idPengguna === anggota(kode)?.idPengguna);

const isianBaru = (ubah = {}) => ({ kodeAnggota: 'AG-011', nama: 'Dimas Ramadhan', kontak: '0812-0000-1111', status: 'Aktif', ...ubah });
const aksesBaru = (ubah = {}) => ({
    buatAkses: true,
    username: 'dimas.r',
    kataSandi: SANDI,
    konfirmasiKataSandi: SANDI,
    ...ubah,
});

function ditolak(aksi, kunci, pola) {
    const sebelum = potret();
    assert.throws(aksi, (e) => e instanceof GalatValidasi && (!kunci || pola.test(e.galat[kunci] ?? '')));
    assert.equal(potret(), sebelum, 'data berubah walau aksi ditolak');
}

function gagalMasuk(identitas, sandi, pesan) {
    assert.throws(() => masuk(identitas, sandi), (e) => e instanceof GalatValidasi && e.galat.umum === pesan);
}

beforeEach(() => aturUlangData());

describe('1–2. Tambah Anggota dengan/tanpa akses login', () => {
    test('1. tanpa akses login: anggota dibuat, tidak ada akun baru (idPengguna null)', () => {
        const jumlahAkun = data.pengguna.length;
        tambahAnggota(isianBaru(), { ...aksesBaru(), buatAkses: false });
        assert.equal(anggota('AG-011').idPengguna, null);
        assert.equal(data.pengguna.length, jumlahAkun);
        // Kompatibel dengan pemanggilan lama tanpa argumen akses.
        tambahAnggota(isianBaru({ kodeAnggota: 'AG-012' }));
        assert.equal(anggota('AG-012').idPengguna, null);
        konsisten();
    });

    test('2. dengan akses login: anggota + akun peran anggota dibuat dan terhubung', () => {
        tambahAnggota(isianBaru(), aksesBaru());
        const a = anggota('AG-011');
        const akun = akunDari('AG-011');
        assert.equal(a.idPengguna, 'USR-012');
        assert.deepEqual(
            { idPengguna: akun.idPengguna, username: akun.username, nama: akun.nama, peran: akun.peran, statusAkun: akun.statusAkun },
            { idPengguna: 'USR-012', username: 'dimas.r', nama: 'Dimas Ramadhan', peran: 'anggota', statusAkun: 'Aktif' },
        );
        konsisten();
    });

    test('kata sandi hanya tersimpan sebagai hash bergaram; tidak ada di data anggota maupun store', () => {
        tambahAnggota(isianBaru(), aksesBaru());
        const akun = akunDari('AG-011');
        assert.match(akun.passwordHash, POLA_HASH);
        assert.ok(!potret().includes(SANDI), 'plaintext kata sandi ditemukan di store');
        assert.deepEqual(Object.keys(anggota('AG-011')).sort(), ['idPengguna', 'kodeAnggota', 'kontak', 'nama', 'status']);
        // Data awal (repositori) tidak memuat kata sandi atau hash.
        assert.ok(DATA_MOCK.pengguna.every((p) => p.passwordHash === null));
    });

    test('anggota Nonaktif dengan akses login: akun tercatat, statusAkun diturunkan = Nonaktif', () => {
        tambahAnggota(isianBaru({ status: 'Nonaktif' }), aksesBaru());
        assert.equal(akunDari('AG-011').username, 'dimas.r');
        assert.equal(akunDari('AG-011').statusAkun, 'Nonaktif');
        konsisten();
    });

    test('tidak ada Status Akses: nilai statusAkun tersembunyi pada isian diabaikan', () => {
        tambahAnggota(isianBaru(), aksesBaru({ statusAkun: 'Nonaktif' }));
        assert.equal(akunDari('AG-011').statusAkun, 'Aktif');
        assert.equal(masuk('dimas.r', SANDI).peran, 'anggota');
        ubahAnggota('AG-011', { nama: 'Dimas Ramadhan', kontak: '0812-0000-1111', status: 'Aktif' }, { statusAkun: 'Nonaktif', resetKataSandi: false });
        assert.equal(akunDari('AG-011').statusAkun, 'Aktif');
        assert.equal(masuk('dimas.r', SANDI).peran, 'anggota');
        konsisten();
    });
});

describe('3–4. Validasi kredensial', () => {
    test('3. username duplikat ditolak (tidak peka huruf besar/kecil), termasuk username Admin', () => {
        for (const username of ['ADMIN', 'admin', ' Admin ', 'ag-001', 'AG-005']) {
            ditolak(() => tambahAnggota(isianBaru(), aksesBaru({ username })), 'username', /sudah digunakan/);
        }
        assert.equal(anggota('AG-011'), undefined);
    });

    test('3b. username duplikat dengan akun yang dibuat pada sesi yang sama ditolak', () => {
        tambahAnggota(isianBaru(), aksesBaru());
        ditolak(() => tambahAnggota(isianBaru({ kodeAnggota: 'AG-012' }), aksesBaru({ username: 'DIMAS.R' })), 'username', /sudah digunakan/);
    });

    test('username kosong / berspasi ditolak', () => {
        ditolak(() => tambahAnggota(isianBaru(), aksesBaru({ username: '  ' })), 'username', /wajib diisi/);
        ditolak(() => tambahAnggota(isianBaru(), aksesBaru({ username: 'dimas r' })), 'username', /spasi/);
    });

    test('kata sandi kosong saat membuat akun ditolak', () => {
        ditolak(() => tambahAnggota(isianBaru(), aksesBaru({ kataSandi: '', konfirmasiKataSandi: '' })), 'kataSandi', /wajib diisi/);
    });

    test('4. konfirmasi kata sandi yang tidak sesuai ditolak', () => {
        ditolak(() => tambahAnggota(isianBaru(), aksesBaru({ konfirmasiKataSandi: acak() })), 'konfirmasiKataSandi', /tidak sama/);
    });

    test('pesan galat tidak pernah memuat kata sandi', () => {
        const galat = validasiAksesLogin(aksesBaru({ username: 'admin', konfirmasiKataSandi: 'beda-sekali' }), data);
        assert.ok(Object.values(galat).every((pesan) => !pesan.includes(SANDI) && !pesan.includes('beda-sekali')));
    });

    test('validator tidak lagi mengenal field statusAkun', () => {
        assert.deepEqual(validasiAksesLogin(aksesBaru({ statusAkun: 'Diblokir' }), data), {});
    });
});

describe('5–8, 14. Masuk simulasi', () => {
    test('5. kredensial valid → sesi berisi akun anggota baru (tidak peka huruf besar/kecil pada username)', () => {
        tambahAnggota(isianBaru(), aksesBaru());
        assert.deepEqual(masuk(' DIMAS.R ', SANDI), { idPengguna: 'USR-012', peran: 'anggota' });
        assert.equal(akunAktif('anggota').kodeAnggota, 'AG-011');
        assert.equal(akunAktif('anggota').nama, 'Dimas Ramadhan');
    });

    test('6. kata sandi salah ditolak dengan pesan generik; sesi tidak berubah', () => {
        tambahAnggota(isianBaru(), aksesBaru());
        gagalMasuk('dimas.r', 'salah-total', PESAN_MASUK.gagal);
        gagalMasuk('dimas.r', SANDI.toLowerCase(), PESAN_MASUK.gagal);
        gagalMasuk('tidak-ada', SANDI, PESAN_MASUK.gagal);
        gagalMasuk('dimas.r', '', PESAN_MASUK.gagal);
        assert.equal(akunAktif('anggota').kodeAnggota, AKUN_DEMO.anggota.kodeAnggota);
    });

    test('akun anggota bawaan tanpa kata sandi dari Admin tidak dapat masuk', () => {
        gagalMasuk('AG-002', 'apa-saja', PESAN_MASUK.gagal);
    });

    test('Anggota Nonaktif + akun valid → ditolak walau username & kata sandi benar', () => {
        tambahAnggota(isianBaru({ status: 'Nonaktif' }), aksesBaru());
        gagalMasuk('dimas.r', SANDI, PESAN_MASUK.anggotaNonaktif);
        // Kata sandi salah tetap pesan generik (status tidak dibocorkan).
        gagalMasuk('dimas.r', 'salah-total', PESAN_MASUK.gagal);
        assert.equal(akunAktif('anggota').kodeAnggota, 'AG-001', 'sesi tidak boleh terisi');
    });

    test('Anggota Nonaktif → Aktif kembali: kredensial lama berlaku lagi tanpa akun/kata sandi baru', () => {
        tambahAnggota(isianBaru(), aksesBaru());
        const { idPengguna, passwordHash } = akunDari('AG-011');
        const jumlahAkun = data.pengguna.length;
        ubahAnggota('AG-011', { nama: 'Dimas Ramadhan', kontak: '0812-0000-1111', status: 'Nonaktif' });
        assert.equal(akunDari('AG-011').statusAkun, 'Nonaktif');
        gagalMasuk('dimas.r', SANDI, PESAN_MASUK.anggotaNonaktif);
        ubahAnggota('AG-011', { nama: 'Dimas Ramadhan', kontak: '0812-0000-1111', status: 'Aktif' });
        assert.deepEqual([akunDari('AG-011').idPengguna, akunDari('AG-011').passwordHash, data.pengguna.length], [idPengguna, passwordHash, jumlahAkun]);
        assert.equal(masuk('dimas.r', SANDI).idPengguna, idPengguna);
        konsisten();
    });

    test('Anggota Aktif tanpa akun → tidak dapat masuk', () => {
        tambahAnggota(isianBaru());
        assert.equal(anggota('AG-011').idPengguna, null);
        gagalMasuk('AG-011', 'apa-saja', PESAN_MASUK.gagal);
        gagalMasuk('Dimas Ramadhan', 'apa-saja', PESAN_MASUK.gagal);
    });

    test('8b. menonaktifkan anggota yang punya akun → tidak dapat masuk; riwayat tetap ada', () => {
        ubahAnggota('AG-001', { nama: 'Nadia Rahmawati', kontak: '0812-3456-7801', status: 'Nonaktif' });
        gagalMasuk('AG-001', 'apa-saja', PESAN_MASUK.anggotaNonaktif);
        assert.equal(peminjamanAnggota(data, 'AG-001').length, 3);
    });

    test('relasi akun ↔ anggota tidak valid ditolak saat masuk', () => {
        tambahAnggota(isianBaru(), aksesBaru());
        // Simulasikan data rusak secara langsung (tanpa jalur transaksi).
        anggota('AG-011').idPengguna = null;
        gagalMasuk('dimas.r', SANDI, PESAN_MASUK.relasiTidakValid);
    });

    test('14. akun Admin existing tetap berfungsi (kata sandi apa pun yang tidak kosong)', () => {
        assert.deepEqual(masuk('admin', 'apa-saja'), { idPengguna: 'USR-001', peran: 'admin' });
        assert.equal(akunAktif('admin').nama, 'Rina Wulandari');
        assert.deepEqual(masuk('ADMIN', 'x'), { idPengguna: 'USR-001', peran: 'admin' });
        gagalMasuk('admin', '', PESAN_MASUK.gagal);
    });

    test('akun Admin tidak terpengaruh perubahan status anggota mana pun', () => {
        const admin = JSON.stringify(data.pengguna.find((p) => p.peran === 'admin'));
        for (const a of [...data.anggota]) ubahAnggota(a.kodeAnggota, { nama: a.nama, kontak: a.kontak, status: 'Nonaktif' });
        assert.equal(JSON.stringify(data.pengguna.find((p) => p.peran === 'admin')), admin);
        assert.equal(masuk('admin', 'x').peran, 'admin');
        konsisten();
    });

    test('akun pratinjau AG-001 tetap dapat masuk seperti sebelumnya', () => {
        assert.deepEqual(masuk('ag-001', 'apa-saja'), { idPengguna: 'USR-002', peran: 'anggota' });
        assert.equal(akunAktif('anggota').kodeAnggota, 'AG-001');
    });

    test('keluar menghapus sesi; tanpa sesi halaman memakai akun pratinjau', () => {
        tambahAnggota(isianBaru(), aksesBaru());
        masuk('dimas.r', SANDI);
        keluar();
        assert.equal(akunAktif('anggota').kodeAnggota, 'AG-001');
        // Masuk lagi setelah keluar dalam sesi SPA yang sama.
        masuk('dimas.r', SANDI);
        assert.equal(akunAktif('anggota').kodeAnggota, 'AG-011');
    });

    test('sesi Anggota tidak dipakai untuk halaman Admin (dan sebaliknya)', () => {
        tambahAnggota(isianBaru(), aksesBaru());
        masuk('dimas.r', SANDI);
        assert.equal(akunAktif('admin').idPengguna, 'USR-001');
        masuk('admin', 'x');
        assert.equal(akunAktif('anggota').kodeAnggota, 'AG-001');
    });
});

describe('Ubah Anggota: kelola akses login', () => {
    const dataAG = (kode) => {
        const a = anggota(kode);

        return { nama: a.nama, kontak: a.kontak, status: a.status };
    };

    test('9–10. reset kata sandi → kata sandi lama ditolak, kata sandi baru diterima', () => {
        tambahAnggota(isianBaru(), aksesBaru());
        const hashLama = akunDari('AG-011').passwordHash;
        ubahAnggota('AG-011', dataAG('AG-011'), { resetKataSandi: true, kataSandi: SANDI_RESET, konfirmasiKataSandi: SANDI_RESET });
        assert.notEqual(akunDari('AG-011').passwordHash, hashLama);
        gagalMasuk('dimas.r', SANDI, PESAN_MASUK.gagal);
        assert.equal(masuk('dimas.r', SANDI_RESET).idPengguna, 'USR-012');
        konsisten();
    });

    test('tanpa reset, kata sandi tidak berubah', () => {
        tambahAnggota(isianBaru(), aksesBaru());
        const hashLama = akunDari('AG-011').passwordHash;
        ubahAnggota('AG-011', { ...dataAG('AG-011'), kontak: '0812-9999-0000' }, { resetKataSandi: false, kataSandi: 'abaikan123', konfirmasiKataSandi: 'abaikan123' });
        assert.equal(akunDari('AG-011').passwordHash, hashLama);
        assert.equal(masuk('dimas.r', SANDI).idPengguna, 'USR-012');
    });

    test('reset kata sandi: konfirmasi berbeda / kosong ditolak tanpa perubahan', () => {
        tambahAnggota(isianBaru(), aksesBaru());
        const akses = { resetKataSandi: true };
        ditolak(() => ubahAnggota('AG-011', dataAG('AG-011'), { ...akses, kataSandi: SANDI_RESET, konfirmasiKataSandi: acak() }), 'konfirmasiKataSandi', /tidak sama/);
        ditolak(() => ubahAnggota('AG-011', dataAG('AG-011'), { ...akses, kataSandi: '', konfirmasiKataSandi: '' }), 'kataSandi', /wajib diisi/);
    });

    test('reset kata sandi akun pratinjau AG-001 → hanya kata sandi baru yang diterima', () => {
        ubahAnggota('AG-001', dataAG('AG-001'), { resetKataSandi: true, kataSandi: SANDI_RESET, konfirmasiKataSandi: SANDI_RESET });
        gagalMasuk('AG-001', 'apa-saja', PESAN_MASUK.gagal);
        assert.equal(masuk('AG-001', SANDI_RESET).idPengguna, 'USR-002');
    });

    test('reset kata sandi akun anggota bawaan (AG-002) membuatnya dapat dipakai masuk', () => {
        ubahAnggota('AG-002', dataAG('AG-002'), { resetKataSandi: true, kataSandi: SANDI_RESET, konfirmasiKataSandi: SANDI_RESET });
        masuk('ag-002', SANDI_RESET);
        assert.equal(akunAktif('anggota').kodeAnggota, 'AG-002');
    });

    test('menonaktifkan anggota: akun tetap ada, status akun ikut Nonaktif, riwayat booking & peminjaman tetap', () => {
        const sebelum = { b: bookingAnggota(data, 'AG-001').length, p: peminjamanAnggota(data, 'AG-001').length };
        const jumlahAkun = data.pengguna.length;
        ubahAnggota('AG-001', { ...dataAG('AG-001'), status: 'Nonaktif' }, { resetKataSandi: false });
        assert.equal(akunDari('AG-001').statusAkun, 'Nonaktif');
        assert.equal(akunDari('AG-001').username, 'AG-001');
        assert.equal(data.pengguna.length, jumlahAkun);
        assert.deepEqual({ b: bookingAnggota(data, 'AG-001').length, p: peminjamanAnggota(data, 'AG-001').length }, sebelum);
        gagalMasuk('AG-001', 'apa-saja', PESAN_MASUK.anggotaNonaktif);
        konsisten();
    });

    test('ubah status tanpa reset tidak mengubah kata sandi; reset tidak mengubah transaksi', () => {
        tambahAnggota(isianBaru(), aksesBaru());
        const hash = akunDari('AG-011').passwordHash;
        ubahAnggota('AG-011', { ...dataAG('AG-011'), status: 'Nonaktif' });
        ubahAnggota('AG-011', { ...dataAG('AG-011'), status: 'Aktif' });
        assert.equal(akunDari('AG-011').passwordHash, hash);
        const transaksi = JSON.stringify([data.peminjaman, data.pengembalian, data.booking, data.eksemplar]);
        ubahAnggota('AG-011', dataAG('AG-011'), { resetKataSandi: true, kataSandi: SANDI_RESET, konfirmasiKataSandi: SANDI_RESET });
        assert.equal(JSON.stringify([data.peminjaman, data.pengembalian, data.booking, data.eksemplar]), transaksi);
    });

    test('anggota tanpa akun: Buat Akses Login dari Ubah Anggota tanpa membuat anggota baru', () => {
        tambahAnggota(isianBaru());
        const jumlahAnggota = data.anggota.length;
        ubahAnggota('AG-011', dataAG('AG-011'), aksesBaru());
        assert.equal(data.anggota.length, jumlahAnggota);
        assert.equal(akunDari('AG-011').username, 'dimas.r');
        assert.equal(masuk('dimas.r', SANDI).peran, 'anggota');
        konsisten();
    });

    test('anggota yang sudah punya akun tidak dapat dibuatkan akun kedua', () => {
        const jumlahAkun = data.pengguna.length;
        ditolak(() => ubahAnggota('AG-001', dataAG('AG-001'), aksesBaru({ username: 'nadia2' })), 'akses', /sudah memiliki akses login/);
        assert.equal(data.pengguna.length, jumlahAkun);
    });

    test('relasi akun rusak menolak perubahan akses login', () => {
        anggota('AG-003').idPengguna = 'USR-999';
        ditolak(() => ubahAnggota('AG-003', dataAG('AG-003'), { resetKataSandi: false }), 'akses', /Relasi akun login/);
    });

    test('periksaKonsistensi mendeteksi satu akun yang terhubung ke dua anggota', () => {
        const draf = JSON.parse(potret());
        draf.anggota.find((a) => a.kodeAnggota === 'AG-002').idPengguna = 'USR-002';
        const galat = periksaKonsistensi(draf, { tanggalAcuan: TANGGAL_DEMO });
        assert.ok(galat.some((g) => /lebih dari satu anggota/.test(g)));
    });

    test('periksaKonsistensi menolak status akun anggota yang bertentangan dengan Status Anggota', () => {
        const draf = JSON.parse(potret());
        draf.pengguna.find((p) => p.idPengguna === 'USR-002').statusAkun = 'Nonaktif';
        assert.ok(periksaKonsistensi(draf, { tanggalAcuan: TANGGAL_DEMO }).some((g) => /berbeda dengan Status Anggota/.test(g)));
    });

    test('periksaKonsistensi menolak kata sandi plaintext pada passwordHash', () => {
        const draf = JSON.parse(potret());
        draf.pengguna[1].passwordHash = 'rahasia';
        assert.ok(periksaKonsistensi(draf, { tanggalAcuan: TANGGAL_DEMO }).some((g) => /passwordHash/.test(g)));
    });
});

describe('11. Anggota baru melihat data miliknya sendiri', () => {
    test('booking diajukan oleh anggota yang masuk tercatat atas namanya, bukan AG-001', () => {
        tambahAnggota(isianBaru(), aksesBaru());
        masuk('dimas.r', SANDI);
        const saya = akunAktif('anggota').kodeAnggota;
        assert.equal(saya, 'AG-011');

        assert.deepEqual(ringkasanAnggota(data, saya), { bookingAktif: 0, peminjamanAktif: 0, riwayatSelesai: 0 });
        assert.equal(bookingAnggota(data, saya).length, 0);
        assert.equal(peminjamanAnggota(data, saya).length, 0);

        const b = ajukanBooking({ kodeEksemplar: 'EK-001' }, { kodeAnggota: saya });
        assert.equal(b.kodeAnggota, 'AG-011');
        assert.deepEqual(bookingAnggota(data, saya).map((x) => x.idBooking), [b.idBooking]);
        assert.ok(!bookingAnggota(data, 'AG-001').some((x) => x.idBooking === b.idBooking));
        konsisten();
    });
});

describe('12. Atomisitas', () => {
    test('validasi akses gagal → anggota juga tidak dibuat', () => {
        ditolak(() => tambahAnggota(isianBaru(), aksesBaru({ username: 'admin' })), 'username', /sudah digunakan/);
        assert.equal(anggota('AG-011'), undefined);
    });

    test('draf tidak konsisten di tengah transaksi → anggota & akun tidak tersimpan sebagian', () => {
        const sebelum = potret();
        assert.throws(
            () =>
                _jalankanTransaksiUntukTes('Uji atomik akses', (d) => {
                    d.anggota.push({ kodeAnggota: 'AG-011', idPengguna: 'USR-012', nama: 'Dimas Ramadhan', kontak: '-', status: 'Aktif' });
                    // Akun dengan username duplikat (tidak peka huruf besar/kecil) → periksaKonsistensi gagal.
                    d.pengguna.push({ idPengguna: 'USR-012', username: 'ADMIN', nama: 'Dimas Ramadhan', peran: 'anggota', statusAkun: 'Aktif', passwordHash: null });
                }),
            GalatValidasi,
        );
        assert.equal(potret(), sebelum);
    });
});

describe('13. Regresi booking & peminjaman', () => {
    test('alur lengkap tetap berjalan untuk anggota baru yang masuk dengan akun buatan Admin', () => {
        tambahAnggota(isianBaru(), aksesBaru());
        masuk('dimas.r', SANDI);
        const saya = akunAktif('anggota').kodeAnggota;

        const b = ajukanBooking({ kodeEksemplar: 'EK-004' }, { kodeAnggota: saya });
        setujuiBooking(b.idBooking);
        const { peminjaman } = realisasikanBooking(b.idBooking, { tanggalJatuhTempo: '2026-11-14' });
        assert.equal(peminjaman.kodeAnggota, 'AG-011');
        prosesPengembalian(peminjaman.idPeminjaman);
        catatPeminjaman({ kodeAnggota: saya, kodeBuku: 'BK-002', kodeEksemplar: 'EK-005', tanggalJatuhTempo: '2026-11-21' });

        assert.deepEqual(ringkasanAnggota(data, saya), { bookingAktif: 0, peminjamanAktif: 1, riwayatSelesai: 1 });
        konsisten();
    });
});
