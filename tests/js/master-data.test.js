/**
 * Tes logika Phase 3: pencarian/filter, validasi formulir master data,
 * dan store pratinjau (mutasi pada salinan reaktif). Jalankan: npm run test:data
 */
import assert from 'node:assert/strict';
import { beforeEach, describe, test } from 'node:test';

import {
    aturUlangData,
    ambilPesan,
    setPesan,
    tambahAnggota,
    tambahBuku,
    ubahAnggota,
    ubahBuku,
    useDataPerpustakaan,
} from './fixtures/storeMock.js';
import { TANGGAL_DEMO } from './fixtures/demo.js';
import { DATA_MOCK, ketersediaanBuku, periksaKonsistensi, rekapStatus } from './fixtures/mock/index.js';
import { filterAnggota, filterBuku, kodeBerikutnya } from '../../resources/js/utils/pencarian.js';
import { daftarStatus } from '../../resources/js/utils/status.js';
import { transaksiBerjalanAnggota, validasiAnggota, validasiBuku, validasiKodeEksemplar } from '../../resources/js/utils/validasiMaster.js';

const D = DATA_MOCK;
const kode = (daftar, kunci) => daftar.map((r) => r[kunci]);

describe('Pencarian & filter Data Anggota', () => {
    test('tanpa filter menampilkan semua anggota', () => {
        assert.equal(filterAnggota(D.anggota).length, D.anggota.length);
    });

    test('cari berdasarkan kode, nama, atau kontak (tidak peka huruf besar/kecil)', () => {
        assert.deepEqual(kode(filterAnggota(D.anggota, { kataKunci: 'ag-001' }), 'kodeAnggota'), ['AG-001']);
        assert.deepEqual(kode(filterAnggota(D.anggota, { kataKunci: 'NADIA' }), 'kodeAnggota'), ['AG-001']);
        assert.deepEqual(kode(filterAnggota(D.anggota, { kataKunci: '0857-1120' }), 'kodeAnggota'), ['AG-003']);
    });

    test('filter status dan gabungan dengan kata kunci', () => {
        assert.deepEqual(kode(filterAnggota(D.anggota, { status: 'Nonaktif' }), 'kodeAnggota'), ['AG-007', 'AG-009']);
        assert.equal(filterAnggota(D.anggota, { status: 'Aktif' }).length, 8);
        assert.deepEqual(filterAnggota(D.anggota, { kataKunci: 'Nadia', status: 'Nonaktif' }), []);
    });

    test('kata kunci yang tidak cocok menghasilkan daftar kosong', () => {
        assert.deepEqual(filterAnggota(D.anggota, { kataKunci: 'zzz-tidak-ada' }), []);
    });
});

describe('Pencarian Data Buku (kode, judul, kategori)', () => {
    test('cari berdasarkan kode buku, judul, dan kategori', () => {
        assert.deepEqual(kode(filterBuku(D.buku, { kataKunci: 'bk-005' }), 'kodeBuku'), ['BK-005']);
        assert.deepEqual(kode(filterBuku(D.buku, { kataKunci: 'basis data' }), 'kodeBuku'), ['BK-002']);
        assert.deepEqual(kode(filterBuku(D.buku, { kataKunci: 'matematika' }), 'kodeBuku'), ['BK-006', 'BK-007']);
    });

    test('penulis/penerbit tidak termasuk kriteria pencarian', () => {
        assert.deepEqual(filterBuku(D.buku, { kataKunci: 'Arief Hidayat' }), []);
    });

    test('kata kunci yang tidak cocok menghasilkan daftar kosong', () => {
        assert.deepEqual(filterBuku(D.buku, { kataKunci: 'Fisika Kuantum' }), []);
    });

    test('jumlah eksemplar dihitung dari relasi eksemplar', () => {
        assert.equal(ketersediaanBuku(D, 'BK-001').total, D.eksemplar.filter((e) => e.kodeBuku === 'BK-001').length);
        assert.deepEqual(ketersediaanBuku(D, 'BK-001'), { total: 3, Tersedia: 2, Dipesan: 0, Dipinjam: 1 });
    });

    test('usulan kode berikutnya untuk placeholder', () => {
        assert.equal(kodeBerikutnya(D.anggota, 'kodeAnggota', 'AG'), 'AG-011');
        assert.equal(kodeBerikutnya(D.buku, 'kodeBuku', 'BK'), 'BK-011');
        assert.equal(kodeBerikutnya(D.eksemplar, 'kodeEksemplar', 'EK'), 'EK-019');
    });
});

describe('Rekap status dashboard', () => {
    test('rekap booking mengikuti urutan status resmi dan totalnya sama dengan jumlah booking', () => {
        const rekap = rekapStatus(D.booking, daftarStatus('booking'));
        assert.deepEqual(rekap.map((r) => r.status), ['Diajukan', 'Disetujui', 'Ditolak', 'Dibatalkan', 'Direalisasikan', 'Kedaluwarsa']);
        assert.equal(rekap.reduce((n, r) => n + r.jumlah, 0), D.booking.length);
    });
});

describe('Validasi Form Anggota', () => {
    const valid = { kodeAnggota: 'AG-011', nama: 'Dimas Ramadhan', kontak: '0812-0000-1111', status: 'Aktif' };

    test('isian lengkap dan unik valid', () => {
        assert.deepEqual(validasiAnggota(valid, D, { mode: 'tambah' }), {});
    });

    test('field wajib', () => {
        const g = validasiAnggota({ kodeAnggota: '', nama: ' ', kontak: '', status: '' }, D, { mode: 'tambah' });
        assert.deepEqual(Object.keys(g).sort(), ['kodeAnggota', 'kontak', 'nama', 'status']);
        assert.equal(g.kodeAnggota, 'Kode Anggota wajib diisi.');
    });

    test('Kode Anggota duplikat ditolak (tidak peka huruf besar/kecil)', () => {
        assert.match(validasiAnggota({ ...valid, kodeAnggota: 'ag-001' }, D, { mode: 'tambah' }).kodeAnggota, /sudah digunakan/);
    });

    test('kode dengan spasi ditolak', () => {
        assert.match(validasiAnggota({ ...valid, kodeAnggota: 'AG 011' }, D, { mode: 'tambah' }).kodeAnggota, /spasi/);
    });

    test('anggota boleh dinonaktifkan walau masih punya transaksi berjalan (FR-022)', () => {
        assert.deepEqual(transaksiBerjalanAnggota(D, 'AG-001'), { peminjamanAktif: 1, bookingBerjalan: 1 });
        assert.deepEqual(validasiAnggota({ ...valid, kodeAnggota: 'AG-001', status: 'Nonaktif' }, D, { mode: 'ubah' }), {});
    });
});

describe('Validasi Form Buku & Eksemplar', () => {
    const valid = { kodeBuku: 'BK-011', judul: 'Etika Profesi', penulis: 'Rudi Hartono', penerbit: 'Cakrawala Ilmu', tahunTerbit: '2024', kategori: 'Umum' };
    const satuEksemplar = [{ kodeEksemplar: 'EK-019', status: 'Tersedia' }];
    const opsi = (o = {}) => ({ mode: 'tambah', eksemplarBaru: satuEksemplar, tahunMaks: 2026, ...o });

    test('isian lengkap valid', () => {
        assert.deepEqual(validasiBuku(valid, D, opsi()), {});
    });

    test('field wajib dan minimal satu eksemplar', () => {
        const g = validasiBuku({ kodeBuku: '', judul: '', penulis: '', penerbit: '', tahunTerbit: '', kategori: '' }, D, opsi({ eksemplarBaru: [] }));
        assert.deepEqual(Object.keys(g).sort(), ['eksemplar', 'judul', 'kategori', 'kodeBuku', 'penerbit', 'penulis', 'tahunTerbit']);
    });

    test('Kode Buku duplikat ditolak', () => {
        assert.match(validasiBuku({ ...valid, kodeBuku: 'BK-001' }, D, opsi()).kodeBuku, /sudah digunakan/);
    });

    test('Tahun Terbit harus 4 digit dan tidak melebihi tahun demo', () => {
        assert.ok(validasiBuku({ ...valid, tahunTerbit: '20a4' }, D, opsi()).tahunTerbit);
        assert.ok(validasiBuku({ ...valid, tahunTerbit: '2027' }, D, opsi()).tahunTerbit);
        assert.equal(validasiBuku({ ...valid, tahunTerbit: '2026' }, D, opsi()).tahunTerbit, undefined);
    });

    test('Kode Eksemplar duplikat ditolak — terhadap data dan terhadap daftar formulir', () => {
        assert.match(validasiKodeEksemplar('EK-001', D), /sudah digunakan/);
        assert.match(validasiKodeEksemplar('ek-019', D, satuEksemplar), /sudah digunakan/);
        assert.equal(validasiKodeEksemplar('EK-020', D, satuEksemplar), null);
    });

    test('eksemplar baru selain Tersedia ditolak', () => {
        const g = validasiBuku(valid, D, opsi({ eksemplarBaru: [{ kodeEksemplar: 'EK-019', status: 'Dipinjam' }] }));
        assert.match(g.eksemplar, /harus berstatus Tersedia/);
    });

    test('ubah buku yang sudah punya eksemplar tidak wajib menambah eksemplar', () => {
        assert.deepEqual(validasiBuku({ ...valid, kodeBuku: 'BK-001' }, D, opsi({ mode: 'ubah', kodeAsal: 'BK-001', eksemplarBaru: [] })), {});
    });
});

describe('Store pratinjau (salinan reaktif)', () => {
    beforeEach(() => aturUlangData());

    const { data } = useDataPerpustakaan();
    const konsisten = () => assert.deepEqual(periksaKonsistensi(data, { tanggalAcuan: TANGGAL_DEMO }), []);

    test('store dimulai dari salinan data mock, bukan objek yang sama', () => {
        assert.notEqual(data.anggota, D.anggota);
        assert.deepEqual(JSON.parse(JSON.stringify(data)), JSON.parse(JSON.stringify(D)));
    });

    test('tambah anggota tidak mengubah data awal dan tetap konsisten', () => {
        tambahAnggota({ kodeAnggota: 'AG-011', nama: ' Dimas Ramadhan ', kontak: '0812-0000-1111', status: 'Aktif' });
        assert.equal(data.anggota.length, D.anggota.length + 1);
        assert.equal(data.anggota.at(-1).nama, 'Dimas Ramadhan');
        assert.equal(data.anggota.at(-1).idPengguna, null);
        assert.equal(D.anggota.length, 10);
        konsisten();
    });

    test('tambah anggota dengan kode duplikat ditolak tanpa mengubah data', () => {
        assert.throws(() => tambahAnggota({ kodeAnggota: 'AG-001', nama: 'X', kontak: 'Y', status: 'Aktif' }), (e) => Boolean(e.galat?.kodeAnggota));
        assert.equal(data.anggota.length, D.anggota.length);
    });

    test('ubah anggota menyinkronkan nama dan status akun login', () => {
        ubahAnggota('AG-004', { nama: 'Bagas Saputra Wijaya', kontak: '0821-7765-4404', status: 'Nonaktif' });
        const akun = data.pengguna.find((p) => p.idPengguna === 'USR-005');
        assert.equal(akun.nama, 'Bagas Saputra Wijaya');
        assert.equal(akun.statusAkun, 'Nonaktif');
        konsisten();
    });

    test('menonaktifkan anggota tidak membatalkan transaksi berjalan dan data tetap konsisten', () => {
        ubahAnggota('AG-001', { nama: 'Nadia Rahmawati', kontak: '0812-3456-7801', status: 'Nonaktif' });
        assert.equal(data.anggota.find((a) => a.kodeAnggota === 'AG-001').status, 'Nonaktif');
        assert.equal(data.peminjaman.find((p) => p.idPeminjaman === 'PJ-007').status, 'Aktif');
        assert.equal(data.booking.find((b) => b.idBooking === 'BP-007').status, 'Diajukan');
        konsisten();
    });

    test('tambah buku beserta eksemplar Tersedia; jumlah eksemplar ikut bertambah', () => {
        tambahBuku(
            { kodeBuku: 'BK-011', judul: 'Etika Profesi', penulis: 'Rudi Hartono', penerbit: 'Cakrawala Ilmu', tahunTerbit: '2024', kategori: 'Umum' },
            [{ kodeEksemplar: 'EK-019', status: 'Tersedia' }, { kodeEksemplar: 'EK-020', status: 'Tersedia' }],
        );
        assert.deepEqual(ketersediaanBuku(data, 'BK-011'), { total: 2, Tersedia: 2, Dipesan: 0, Dipinjam: 0 });
        assert.equal(data.buku.at(-1).tahunTerbit, 2024);
        konsisten();
    });

    test('ubah buku menambah eksemplar tanpa mengubah status eksemplar lama', () => {
        const sebelum = data.eksemplar.filter((e) => e.kodeBuku === 'BK-001').map((e) => `${e.kodeEksemplar}:${e.status}`);
        ubahBuku('BK-001', { judul: 'Algoritma Dasar', penulis: 'Arief Hidayat', penerbit: 'Cakrawala Ilmu', tahunTerbit: '2021', kategori: 'Informatika' }, [
            { kodeEksemplar: 'EK-019', status: 'Tersedia' },
        ]);
        const sesudah = data.eksemplar.filter((e) => e.kodeBuku === 'BK-001').map((e) => `${e.kodeEksemplar}:${e.status}`);
        assert.deepEqual(sesudah, [...sebelum, 'EK-019:Tersedia']);
        konsisten();
    });

    test('pesan satu kali: diambil sekali lalu kosong', () => {
        setPesan({ tone: 'success', title: 'X' });
        assert.equal(ambilPesan().title, 'X');
        assert.equal(ambilPesan(), null);
    });
});
