/**
 * Tes Phase 5: Cari Buku, ajukan & batalkan booking oleh Anggota, serta alur
 * lintas peran Anggota ↔ Admin pada store yang sama. Jalankan: npm run test:data
 */
import assert from 'node:assert/strict';
import { beforeEach, describe, test } from 'node:test';

import {
    GalatValidasi,
    ajukanBooking,
    aturUlangData,
    batalkanBooking,
    catatPeminjaman,
    prosesPengembalian,
    realisasikanBooking,
    setujuiBooking,
    tolakBooking,
    ubahAnggota,
    useDataPerpustakaan,
} from '../../resources/js/composables/useDataPerpustakaan.js';
import { AKUN_DEMO, TANGGAL_DEMO } from '../../resources/js/config/demo.js';
import {
    DATA_MOCK,
    bookingAnggota,
    detailPeminjaman,
    ketersediaanBuku,
    peminjamanAnggota,
    periksaKonsistensi,
    ringkasanAdmin,
    ringkasanAnggota,
} from '../../resources/js/data/mock/index.js';
import { filterKatalog } from '../../resources/js/utils/pencarian.js';

const { data } = useDataPerpustakaan();
const SAYA = AKUN_DEMO.anggota.kodeAnggota; // AG-001
const potret = () => JSON.stringify(data);
const konsisten = () => assert.deepEqual(periksaKonsistensi(data, { tanggalAcuan: TANGGAL_DEMO }), []);
const eks = (kode) => data.eksemplar.find((e) => e.kodeEksemplar === kode);
const booking = (id) => data.booking.find((b) => b.idBooking === id);
const kode = (daftar, kunci) => daftar.map((r) => r[kunci]);

function ditolakTanpaPerubahan(aksi, pola) {
    const sebelum = potret();
    assert.throws(aksi, (e) => e instanceof GalatValidasi && pola.test(Object.values(e.galat).join(' | ')));
    assert.equal(potret(), sebelum, 'data berubah walau aksi ditolak');
}

beforeEach(() => aturUlangData());

describe('Cari Buku', () => {
    test('akun demo Anggota = AG-001 Nadia Rahmawati', () => {
        assert.equal(SAYA, 'AG-001');
        assert.equal(AKUN_DEMO.anggota.nama, 'Nadia Rahmawati');
    });

    test('cari berdasarkan judul, kode buku, dan kategori', () => {
        assert.deepEqual(kode(filterKatalog(DATA_MOCK.buku, { kataKunci: 'kalkulus' }), 'kodeBuku'), ['BK-006']);
        assert.deepEqual(kode(filterKatalog(DATA_MOCK.buku, { kataKunci: 'BK-009' }), 'kodeBuku'), ['BK-009']);
        assert.deepEqual(kode(filterKatalog(DATA_MOCK.buku, { kataKunci: 'manajemen' }), 'kodeBuku'), ['BK-008', 'BK-010']);
    });

    test('filter kategori digabung dengan kata kunci', () => {
        assert.deepEqual(kode(filterKatalog(DATA_MOCK.buku, { kategori: 'Matematika' }), 'kodeBuku'), ['BK-006', 'BK-007']);
        assert.deepEqual(kode(filterKatalog(DATA_MOCK.buku, { kataKunci: 'manajemen', kategori: 'Informatika' }), 'kodeBuku'), ['BK-010']);
    });

    test('tidak ditemukan → daftar kosong', () => {
        assert.deepEqual(filterKatalog(DATA_MOCK.buku, { kataKunci: 'Fisika Kuantum' }), []);
    });

    test('ketersediaan dihitung dari status eksemplar sebenarnya', () => {
        assert.deepEqual(ketersediaanBuku(DATA_MOCK, 'BK-002'), { total: 2, Tersedia: 1, Dipesan: 1, Dipinjam: 0 });
        assert.deepEqual(ketersediaanBuku(DATA_MOCK, 'BK-005'), { total: 1, Tersedia: 0, Dipesan: 0, Dipinjam: 1 });
        setujuiBooking('BP-007'); // EK-005 → Dipesan
        assert.deepEqual(ketersediaanBuku(data, 'BK-002'), { total: 2, Tersedia: 0, Dipesan: 2, Dipinjam: 0 });
    });
});

describe('Ajukan Booking', () => {
    test('booking valid: ID unik BP-009, Diajukan, tanggal pengajuan hari ini, eksemplar TIDAK dikunci', () => {
        const b = ajukanBooking({ kodeBuku: 'BK-001', kodeEksemplar: 'EK-001' }, { kodeAnggota: SAYA });
        assert.deepEqual(b, {
            idBooking: 'BP-009', kodeAnggota: 'AG-001', kodeEksemplar: 'EK-001', tanggalBooking: '2026-11-07',
            status: 'Diajukan', tanggalKeputusan: null, diputuskanOleh: null, alasanPenolakan: null, idPeminjaman: null,
        });
        assert.equal(eks('EK-001').status, 'Tersedia');
        assert.equal(data.peminjaman.length, DATA_MOCK.peminjaman.length, 'tidak ada peminjaman otomatis');
        konsisten();
    });

    test('dashboard Anggota & Admin ikut berubah; tampil di Status Booking & Verifikasi', () => {
        const sebelumAdmin = ringkasanAdmin(data).booking.Diajukan;
        ajukanBooking({ kodeEksemplar: 'EK-001' }, { kodeAnggota: SAYA });
        assert.equal(ringkasanAnggota(data, SAYA).bookingAktif, 2);
        assert.equal(ringkasanAdmin(data).booking.Diajukan, sebelumAdmin + 1);
        assert.ok(bookingAnggota(data, SAYA).some((b) => b.idBooking === 'BP-009'));
        assert.ok(data.booking.some((b) => b.idBooking === 'BP-009' && b.status === 'Diajukan'));
    });

    test('eksemplar Dipinjam atau Dipesan ditolak (hanya Tersedia)', () => {
        ditolakTanpaPerubahan(() => ajukanBooking({ kodeEksemplar: 'EK-003' }, { kodeAnggota: SAYA }), /berstatus Dipinjam/);
        ditolakTanpaPerubahan(() => ajukanBooking({ kodeEksemplar: 'EK-002' }, { kodeAnggota: SAYA }), /berstatus Dipesan/);
    });

    test('eksemplar tidak terdaftar / tidak dipilih / bukan milik buku ditolak', () => {
        ditolakTanpaPerubahan(() => ajukanBooking({ kodeEksemplar: 'EK-999' }, { kodeAnggota: SAYA }), /tidak terdaftar/);
        ditolakTanpaPerubahan(() => ajukanBooking({ kodeEksemplar: '' }, { kodeAnggota: SAYA }), /Pilih Kode Eksemplar/);
        ditolakTanpaPerubahan(() => ajukanBooking({ kodeBuku: 'BK-002', kodeEksemplar: 'EK-001' }, { kodeAnggota: SAYA }), /tidak terdaftar untuk buku ini/);
    });

    test('buku tanpa eksemplar Tersedia → pesan yang menjelaskan', () => {
        ditolakTanpaPerubahan(() => ajukanBooking({ kodeBuku: 'BK-005', kodeEksemplar: '' }, { kodeAnggota: SAYA }), /tidak memiliki eksemplar Tersedia/);
    });

    test('anggota Nonaktif ditolak (FR-031)', () => {
        ubahAnggota(SAYA, { nama: 'Nadia Rahmawati', kontak: '0812-3456-7801', status: 'Nonaktif' });
        ditolakTanpaPerubahan(() => ajukanBooking({ kodeEksemplar: 'EK-001' }, { kodeAnggota: SAYA }), /Nonaktif/);
    });

    test('pengajuan ganda untuk eksemplar yang sama ditolak (pencegahan transaksi ganda)', () => {
        ajukanBooking({ kodeEksemplar: 'EK-001' }, { kodeAnggota: SAYA });
        ditolakTanpaPerubahan(() => ajukanBooking({ kodeEksemplar: 'EK-001' }, { kodeAnggota: SAYA }), /sudah memiliki booking BP-009/);
        // BP-007 milik AG-001 untuk EK-005 masih Diajukan
        ditolakTanpaPerubahan(() => ajukanBooking({ kodeEksemplar: 'EK-005' }, { kodeAnggota: SAYA }), /sudah memiliki booking BP-007/);
    });

    test('anggota lain boleh mengajukan eksemplar yang sama selama belum Dipesan (antrean belum diatur)', () => {
        ajukanBooking({ kodeEksemplar: 'EK-005' }, { kodeAnggota: 'AG-003' });
        assert.equal(data.booking.filter((b) => b.kodeEksemplar === 'EK-005' && b.status === 'Diajukan').length, 2);
        konsisten();
    });
});

describe('Batalkan Booking', () => {
    test('Diajukan → Dibatalkan; eksemplar tetap Tersedia; dashboard ikut berubah', () => {
        batalkanBooking('BP-007', { kodeAnggota: SAYA });
        assert.equal(booking('BP-007').status, 'Dibatalkan');
        assert.equal(eks('EK-005').status, 'Tersedia');
        assert.equal(ringkasanAnggota(data, SAYA).bookingAktif, 0);
        assert.equal(booking('BP-007').tanggalKeputusan, null, 'tidak ada tanggal baru yang dicatat');
        konsisten();
    });

    test('Disetujui → Dibatalkan melepas reservasi (Dipesan → Tersedia)', () => {
        setujuiBooking('BP-007');
        assert.equal(eks('EK-005').status, 'Dipesan');
        batalkanBooking('BP-007', { kodeAnggota: SAYA });
        assert.equal(booking('BP-007').status, 'Dibatalkan');
        assert.equal(eks('EK-005').status, 'Tersedia');
        konsisten();
    });

    test('Direalisasikan, Dibatalkan, Ditolak tidak dapat dibatalkan', () => {
        ditolakTanpaPerubahan(() => batalkanBooking('BP-004', { kodeAnggota: SAYA }), /Direalisasikan dan tidak dapat dibatalkan/);
        ditolakTanpaPerubahan(() => batalkanBooking('BP-003', { kodeAnggota: SAYA }), /Dibatalkan dan tidak dapat dibatalkan/);
        tolakBooking('BP-007', 'Eksemplar dalam perbaikan.');
        ditolakTanpaPerubahan(() => batalkanBooking('BP-007', { kodeAnggota: SAYA }), /Ditolak dan tidak dapat dibatalkan/);
    });

    test('booking milik anggota lain tidak dapat dibatalkan', () => {
        ditolakTanpaPerubahan(() => batalkanBooking('BP-001', { kodeAnggota: SAYA }), /tidak ditemukan pada akun Anda/);
    });

    test('pembatalan ganda ditolak', () => {
        batalkanBooking('BP-007', { kodeAnggota: SAYA });
        ditolakTanpaPerubahan(() => batalkanBooking('BP-007', { kodeAnggota: SAYA }), /tidak dapat dibatalkan/);
    });
});

describe('History & privasi', () => {
    test('riwayat hanya milik AG-001; Aktif tanpa tanggal kembali, Selesai dengan tanggal kembali', () => {
        const riwayat = peminjamanAnggota(data, SAYA).map((p) => detailPeminjaman(data, p));
        assert.ok(riwayat.every((p) => p.kodeAnggota === SAYA));
        assert.deepEqual(riwayat.map((p) => `${p.idPeminjaman}:${p.status}:${p.tanggalKembali ?? '-'}`), ['PJ-001:Selesai:2026-10-18', 'PJ-005:Selesai:2026-11-01', 'PJ-007:Aktif:-']);
    });

    test('booking yang Direalisasikan muncul sebagai peminjaman di riwayat', () => {
        const pj = booking('BP-004').idPeminjaman;
        assert.ok(peminjamanAnggota(data, SAYA).some((p) => p.idPeminjaman === pj));
    });

    test('Status Booking hanya berisi booking milik AG-001', () => {
        assert.deepEqual(kode(bookingAnggota(data, SAYA), 'idBooking'), ['BP-003', 'BP-004', 'BP-007']);
    });
});

describe('Alur lintas peran (satu store)', () => {
    test('A–E: ajukan → setujui → realisasi → kembalikan; dan ajukan → batalkan', () => {
        // A. Anggota mengajukan booking EK-004
        const { idBooking } = ajukanBooking({ kodeBuku: 'BK-001', kodeEksemplar: 'EK-004' }, { kodeAnggota: SAYA });
        assert.equal(booking(idBooking).status, 'Diajukan');
        assert.ok(data.booking.some((b) => b.idBooking === idBooking), 'terlihat di Verifikasi Admin');
        assert.deepEqual(ringkasanAnggota(data, SAYA), { bookingAktif: 2, peminjamanAktif: 1, riwayatSelesai: 2 });

        // B. Admin menyetujui → Disetujui, eksemplar Dipesan; Anggota melihat status terbaru
        setujuiBooking(idBooking);
        assert.equal(bookingAnggota(data, SAYA).find((b) => b.idBooking === idBooking).status, 'Disetujui');
        assert.equal(eks('EK-004').status, 'Dipesan');
        konsisten();

        // C. Admin merealisasikan → peminjaman Aktif baru di History Anggota
        const { peminjaman } = realisasikanBooking(idBooking, { tanggalJatuhTempo: '2026-11-21' });
        assert.equal(booking(idBooking).status, 'Direalisasikan');
        assert.equal(eks('EK-004').status, 'Dipinjam');
        assert.ok(peminjamanAnggota(data, SAYA).some((p) => p.idPeminjaman === peminjaman.idPeminjaman && p.status === 'Aktif'));
        assert.deepEqual(ringkasanAnggota(data, SAYA), { bookingAktif: 1, peminjamanAktif: 2, riwayatSelesai: 2 });
        konsisten();

        // D. Admin memproses pengembalian → Selesai di History, eksemplar Tersedia
        prosesPengembalian(peminjaman.idPeminjaman);
        const riwayat = detailPeminjaman(data, data.peminjaman.find((p) => p.idPeminjaman === peminjaman.idPeminjaman));
        assert.equal(riwayat.status, 'Selesai');
        assert.equal(riwayat.tanggalKembali, TANGGAL_DEMO);
        assert.equal(eks('EK-004').status, 'Tersedia');
        assert.deepEqual(ringkasanAnggota(data, SAYA), { bookingAktif: 1, peminjamanAktif: 1, riwayatSelesai: 3 });

        // E. Anggota membatalkan booking Diajukan lain (BP-007)
        batalkanBooking('BP-007', { kodeAnggota: SAYA });
        assert.equal(booking('BP-007').status, 'Dibatalkan');
        assert.equal(ringkasanAdmin(data).booking.Diajukan, 1, 'Admin hanya melihat BP-008 Diajukan');
        assert.deepEqual(ringkasanAnggota(data, SAYA), { bookingAktif: 0, peminjamanAktif: 1, riwayatSelesai: 3 });
        konsisten();
    });

    test('booking yang dibatalkan Anggota tidak dapat lagi disetujui/direalisasikan Admin', () => {
        batalkanBooking('BP-007', { kodeAnggota: SAYA });
        ditolakTanpaPerubahan(() => setujuiBooking('BP-007'), /hanya booking Diajukan/);
        ditolakTanpaPerubahan(() => realisasikanBooking('BP-007', { tanggalJatuhTempo: '2026-11-21' }), /hanya booking Disetujui/);
    });

    test('eksemplar yang dipinjam langsung Admin tidak dapat dibooking Anggota', () => {
        catatPeminjaman({ kodeAnggota: 'AG-003', kodeBuku: 'BK-001', kodeEksemplar: 'EK-004', tanggalJatuhTempo: '2026-11-14' });
        ditolakTanpaPerubahan(() => ajukanBooking({ kodeEksemplar: 'EK-004' }, { kodeAnggota: SAYA }), /berstatus Dipinjam/);
    });
});
