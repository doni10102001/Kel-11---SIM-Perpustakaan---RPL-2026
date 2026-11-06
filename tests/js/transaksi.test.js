/**
 * Tes Phase 4: transaksi peminjaman, pengembalian, verifikasi & realisasi booking,
 * laporan, dan atomisitas store. Jalankan: npm run test:data
 */
import assert from 'node:assert/strict';
import { beforeEach, describe, test } from 'node:test';

import {
    GalatValidasi,
    _jalankanTransaksiUntukTes,
    aturUlangData,
    catatPeminjaman,
    prosesPengembalian,
    realisasikanBooking,
    setujuiBooking,
    tolakBooking,
    ubahAnggota,
    useDataPerpustakaan,
} from '../../resources/js/composables/useDataPerpustakaan.js';
import { TANGGAL_DEMO } from '../../resources/js/config/demo.js';
import { DATA_MOCK, periksaKonsistensi, ringkasanAdmin, ringkasanAnggota } from '../../resources/js/data/mock/index.js';
import { buatLaporan, validasiFilterLaporan } from '../../resources/js/utils/laporan.js';
import { validasiPeminjaman } from '../../resources/js/utils/validasiTransaksi.js';

const { data } = useDataPerpustakaan();
const potret = () => JSON.stringify(data);
const konsisten = () => assert.deepEqual(periksaKonsistensi(data, { tanggalAcuan: TANGGAL_DEMO }), []);
const eks = (kode) => data.eksemplar.find((e) => e.kodeEksemplar === kode);
const pinjam = (id) => data.peminjaman.find((p) => p.idPeminjaman === id);
const booking = (id) => data.booking.find((b) => b.idBooking === id);

/** Pastikan aksi ditolak dengan galat yang cocok DAN data tidak berubah sama sekali. */
function ditolakTanpaPerubahan(aksi, pola) {
    const sebelum = potret();
    assert.throws(aksi, (e) => e instanceof GalatValidasi && pola.test(Object.values(e.galat).join(' | ')));
    assert.equal(potret(), sebelum, 'data berubah walau aksi ditolak');
}

beforeEach(() => aturUlangData());

describe('Peminjaman Buku', () => {
    const valid = { kodeAnggota: 'AG-001', kodeBuku: 'BK-001', kodeEksemplar: 'EK-001', tanggalJatuhTempo: '2026-11-21' };

    test('peminjaman valid: record Aktif, eksemplar Dipinjam, pemroses Admin, tanggal hari ini', () => {
        const p = catatPeminjaman(valid);
        assert.deepEqual(p, { idPeminjaman: 'PJ-011', kodeAnggota: 'AG-001', kodeEksemplar: 'EK-001', tanggalPinjam: '2026-11-07', tanggalJatuhTempo: '2026-11-21', diprosesOleh: 'USR-001', status: 'Aktif' });
        assert.equal(eks('EK-001').status, 'Dipinjam');
        konsisten();
    });

    test('dashboard ikut berubah setelah peminjaman', () => {
        const sebelum = ringkasanAdmin(data);
        catatPeminjaman(valid);
        const sesudah = ringkasanAdmin(data);
        assert.equal(sesudah.peminjaman.Aktif, sebelum.peminjaman.Aktif + 1);
        assert.equal(sesudah.eksemplar.Tersedia, sebelum.eksemplar.Tersedia - 1);
        assert.equal(sesudah.eksemplar.Dipinjam, sebelum.eksemplar.Dipinjam + 1);
        assert.equal(ringkasanAnggota(data, 'AG-001').peminjamanAktif, 2);
    });

    test('anggota Nonaktif ditolak (BR-01)', () => {
        ditolakTanpaPerubahan(() => catatPeminjaman({ ...valid, kodeAnggota: 'AG-007' }), /Anggota tidak valid/);
    });

    test('anggota tidak terdaftar ditolak', () => {
        ditolakTanpaPerubahan(() => catatPeminjaman({ ...valid, kodeAnggota: 'AG-999' }), /tidak terdaftar/);
    });

    test('eksemplar Dipinjam ditolak (tidak ada peminjaman Aktif ganda, FR-025)', () => {
        ditolakTanpaPerubahan(() => catatPeminjaman({ ...valid, kodeEksemplar: 'EK-003' }), /peminjaman Aktif/);
    });

    test('eksemplar Dipesan ditolak — hanya melalui realisasi booking (BR-07)', () => {
        ditolakTanpaPerubahan(() => catatPeminjaman({ ...valid, kodeBuku: 'BK-002', kodeEksemplar: 'EK-002' }), /Realisasi Booking/);
    });

    test('eksemplar milik buku lain ditolak', () => {
        ditolakTanpaPerubahan(() => catatPeminjaman({ ...valid, kodeEksemplar: 'EK-005' }), /tidak terdaftar untuk buku ini/);
    });

    test('jatuh tempo wajib, valid, dan setelah tanggal pinjam (tanpa durasi baku)', () => {
        ditolakTanpaPerubahan(() => catatPeminjaman({ ...valid, tanggalJatuhTempo: '' }), /wajib diisi/);
        ditolakTanpaPerubahan(() => catatPeminjaman({ ...valid, tanggalJatuhTempo: '2026-11-07' }), /harus setelah/);
        ditolakTanpaPerubahan(() => catatPeminjaman({ ...valid, tanggalJatuhTempo: '2026-02-30' }), /tidak valid/);
        assert.equal(catatPeminjaman({ ...valid, tanggalJatuhTempo: '2026-11-08' }).tanggalJatuhTempo, '2026-11-08');
    });

    test('eksemplar dengan booking Diajukan tetap boleh dipinjam langsung (hanya Dipesan yang dilindungi)', () => {
        assert.equal(booking('BP-007').status, 'Diajukan');
        catatPeminjaman({ ...valid, kodeBuku: 'BK-002', kodeEksemplar: 'EK-005' });
        assert.equal(eks('EK-005').status, 'Dipinjam');
        konsisten();
    });

    test('buku tanpa eksemplar Tersedia → "Buku tidak tersedia" (A04)', () => {
        const g = validasiPeminjaman({ kodeAnggota: 'AG-001', kodeBuku: 'BK-005', kodeEksemplar: '', tanggalJatuhTempo: '2026-11-21' }, DATA_MOCK, { tanggalPinjam: TANGGAL_DEMO });
        assert.match(g.kodeEksemplar, /Buku tidak tersedia/);
    });

    test('semua galat field dikumpulkan sekaligus', () => {
        const g = validasiPeminjaman({ kodeAnggota: '', kodeBuku: '', kodeEksemplar: '', tanggalJatuhTempo: '' }, DATA_MOCK, { tanggalPinjam: TANGGAL_DEMO });
        assert.deepEqual(Object.keys(g).sort(), ['kodeAnggota', 'kodeBuku', 'kodeEksemplar', 'tanggalJatuhTempo']);
    });
});

describe('Pengembalian Buku', () => {
    test('pengembalian valid: KB baru, peminjaman Selesai, eksemplar Tersedia', () => {
        const k = prosesPengembalian('PJ-006');
        assert.deepEqual(k, { idPengembalian: 'KB-006', idPeminjaman: 'PJ-006', tanggalKembali: '2026-11-07', diprosesOleh: 'USR-001' });
        assert.equal(pinjam('PJ-006').status, 'Selesai');
        assert.equal(eks('EK-003').status, 'Tersedia');
        konsisten();
    });

    test('pengembalian ganda ditolak (FR-027)', () => {
        prosesPengembalian('PJ-006');
        ditolakTanpaPerubahan(() => prosesPengembalian('PJ-006'), /sudah dikembalikan/);
    });

    test('transaksi yang sudah Selesai sejak awal ditolak', () => {
        ditolakTanpaPerubahan(() => prosesPengembalian('PJ-001'), /sudah dikembalikan/);
    });

    test('transaksi tidak ditemukan ditolak', () => {
        ditolakTanpaPerubahan(() => prosesPengembalian('PJ-999'), /tidak ditemukan/);
    });

    test('peminjaman hasil realisasi booking dapat dikembalikan; booking tetap Direalisasikan', () => {
        prosesPengembalian('PJ-007');
        assert.equal(booking('BP-004').status, 'Direalisasikan');
        assert.equal(eks('EK-017').status, 'Tersedia');
        konsisten();
    });

    test('dashboard & riwayat anggota ikut berubah', () => {
        prosesPengembalian('PJ-007');
        assert.equal(ringkasanAdmin(data).peminjaman.Aktif, 4);
        assert.deepEqual(ringkasanAnggota(data, 'AG-001'), { bookingAktif: 1, peminjamanAktif: 0, riwayatSelesai: 3 });
    });
});

describe('Verifikasi Booking', () => {
    test('setujui: Disetujui, eksemplar Dipesan, waktu & pemutus tercatat', () => {
        setujuiBooking('BP-007');
        assert.equal(booking('BP-007').status, 'Disetujui');
        assert.equal(booking('BP-007').tanggalKeputusan, '2026-11-07');
        assert.equal(booking('BP-007').diputuskanOleh, 'USR-001');
        assert.equal(eks('EK-005').status, 'Dipesan');
        assert.equal(ringkasanAdmin(data).booking.Diajukan, 1);
        konsisten();
    });

    test('setujui ditolak bila booking bukan Diajukan', () => {
        ditolakTanpaPerubahan(() => setujuiBooking('BP-001'), /hanya booking Diajukan/);
        ditolakTanpaPerubahan(() => setujuiBooking('BP-002'), /hanya booking Diajukan/);
    });

    test('setujui ditolak bila eksemplar tidak tersedia (guard eksemplarTersedia)', () => {
        catatPeminjaman({ kodeAnggota: 'AG-003', kodeBuku: 'BK-002', kodeEksemplar: 'EK-005', tanggalJatuhTempo: '2026-11-14' });
        ditolakTanpaPerubahan(() => setujuiBooking('BP-007'), /tidak tersedia .* hanya dapat ditolak/);
    });

    test('tolak: wajib alasan; Ditolak, alasan & keputusan tercatat, eksemplar tetap Tersedia', () => {
        ditolakTanpaPerubahan(() => tolakBooking('BP-008', '   '), /Alasan Penolakan wajib/);
        tolakBooking('BP-008', ' Eksemplar sedang dalam perbaikan sampul. ');
        assert.equal(booking('BP-008').status, 'Ditolak');
        assert.equal(booking('BP-008').alasanPenolakan, 'Eksemplar sedang dalam perbaikan sampul.');
        assert.equal(booking('BP-008').tanggalKeputusan, '2026-11-07');
        assert.equal(eks('EK-018').status, 'Tersedia');
        konsisten();
    });

    test('tolak ditolak bila booking bukan Diajukan', () => {
        ditolakTanpaPerubahan(() => tolakBooking('BP-001', 'Alasan'), /hanya booking Diajukan/);
    });

    test('booking dengan eksemplar tidak tersedia tetap dapat ditolak', () => {
        catatPeminjaman({ kodeAnggota: 'AG-003', kodeBuku: 'BK-002', kodeEksemplar: 'EK-005', tanggalJatuhTempo: '2026-11-14' });
        tolakBooking('BP-007', 'Eksemplar sudah dipinjam anggota lain.');
        assert.equal(booking('BP-007').status, 'Ditolak');
        konsisten();
    });
});

describe('Realisasi Booking', () => {
    test('realisasi: peminjaman Aktif baru, booking Direalisasikan & terhubung, eksemplar Dipinjam', () => {
        const { booking: b, peminjaman: p } = realisasikanBooking('BP-001', { tanggalJatuhTempo: '2026-11-21' });
        assert.equal(p.idPeminjaman, 'PJ-011');
        assert.equal(p.kodeAnggota, 'AG-002');
        assert.equal(p.kodeEksemplar, 'EK-002');
        assert.equal(p.status, 'Aktif');
        assert.equal(b.status, 'Direalisasikan');
        assert.equal(booking('BP-001').idPeminjaman, 'PJ-011');
        assert.equal(eks('EK-002').status, 'Dipinjam');
        konsisten();
    });

    test('realisasi ganda dicegah', () => {
        realisasikanBooking('BP-001', { tanggalJatuhTempo: '2026-11-21' });
        ditolakTanpaPerubahan(() => realisasikanBooking('BP-001', { tanggalJatuhTempo: '2026-11-21' }), /hanya booking Disetujui/);
        assert.equal(data.peminjaman.filter((p) => p.kodeEksemplar === 'EK-002' && p.status === 'Aktif').length, 1);
    });

    test('booking Diajukan/Ditolak tidak dapat direalisasikan', () => {
        ditolakTanpaPerubahan(() => realisasikanBooking('BP-007', { tanggalJatuhTempo: '2026-11-21' }), /hanya booking Disetujui/);
        ditolakTanpaPerubahan(() => realisasikanBooking('BP-002', { tanggalJatuhTempo: '2026-11-21' }), /hanya booking Disetujui/);
    });

    test('anggota Nonaktif tidak dapat merealisasikan booking (FR-026)', () => {
        ubahAnggota('AG-002', { nama: 'Rizky Pratama', kontak: '0813-2214-5502', status: 'Nonaktif' });
        ditolakTanpaPerubahan(() => realisasikanBooking('BP-001', { tanggalJatuhTempo: '2026-11-21' }), /tidak berstatus Aktif/);
    });

    test('jatuh tempo wajib saat realisasi', () => {
        ditolakTanpaPerubahan(() => realisasikanBooking('BP-006', { tanggalJatuhTempo: '' }), /wajib diisi/);
    });

    test('alur penuh: ajukan → setujui → realisasikan → kembalikan', () => {
        setujuiBooking('BP-007');
        const { peminjaman } = realisasikanBooking('BP-007', { tanggalJatuhTempo: '2026-11-20' });
        prosesPengembalian(peminjaman.idPeminjaman);
        assert.equal(booking('BP-007').status, 'Direalisasikan');
        assert.equal(pinjam(peminjaman.idPeminjaman).status, 'Selesai');
        assert.equal(eks('EK-005').status, 'Tersedia');
        konsisten();
    });
});

describe('Atomisitas & rollback', () => {
    test('perubahan yang membuat data tidak konsisten dibatalkan seluruhnya', () => {
        const sebelum = potret();
        const asliError = console.error;
        console.error = () => {};
        try {
            assert.throws(
                () =>
                    _jalankanTransaksiUntukTes('Uji', (d) => {
                        d.peminjaman.push({ ...d.peminjaman.find((p) => p.status === 'Aktif'), idPeminjaman: 'PJ-099' }); // peminjaman Aktif ganda
                        d.eksemplar[0].status = 'Dipinjam';
                    }),
                GalatValidasi,
            );
        } finally {
            console.error = asliError;
        }
        assert.equal(potret(), sebelum);
    });

    test('galat di tengah perubahan tidak meninggalkan perubahan sebagian', () => {
        const sebelum = potret();
        assert.throws(() =>
            _jalankanTransaksiUntukTes('Uji', (d) => {
                d.anggota[0].nama = 'Berubah';
                throw new Error('gagal di tengah');
            }),
        );
        assert.equal(potret(), sebelum);
    });

    test('data awal (DATA_MOCK) tidak pernah berubah oleh transaksi', () => {
        catatPeminjaman({ kodeAnggota: 'AG-001', kodeBuku: 'BK-001', kodeEksemplar: 'EK-001', tanggalJatuhTempo: '2026-11-21' });
        assert.equal(DATA_MOCK.peminjaman.length, 10);
        assert.equal(DATA_MOCK.eksemplar.find((e) => e.kodeEksemplar === 'EK-001').status, 'Tersedia');
    });
});

describe('Kelola Laporan', () => {
    test('validasi filter: jenis wajib; periode akhir tidak sebelum awal', () => {
        assert.deepEqual(Object.keys(validasiFilterLaporan({ jenis: '' })), ['jenis']);
        assert.ok(validasiFilterLaporan({ jenis: 'peminjaman', awal: '2026-11-05', akhir: '2026-11-01' }).akhir);
        assert.deepEqual(validasiFilterLaporan({ jenis: 'buku', awal: '2026-11-05', akhir: '2026-11-01' }), {}, 'periode diabaikan untuk daftar buku');
    });

    test('Daftar Buku & Daftar Anggota sesuai dataset', () => {
        const buku = buatLaporan(DATA_MOCK, { jenis: 'buku' });
        assert.equal(buku.baris.length, DATA_MOCK.buku.length);
        assert.equal(buku.ringkasan.find((r) => r.label === 'Jumlah eksemplar').nilai, DATA_MOCK.eksemplar.length);
        assert.equal(buku.baris.find((b) => b.kodeBuku === 'BK-001').jumlahEksemplar, 3);
        const anggota = buatLaporan(DATA_MOCK, { jenis: 'anggota' });
        assert.deepEqual(anggota.ringkasan.map((r) => r.nilai), [10, 8, 2]);
    });

    test('Peminjaman difilter berdasarkan Tanggal Pinjam', () => {
        assert.equal(buatLaporan(DATA_MOCK, { jenis: 'peminjaman' }).baris.length, 10);
        const nov = buatLaporan(DATA_MOCK, { jenis: 'peminjaman', awal: '2026-11-01', akhir: '2026-11-30' });
        assert.deepEqual(nov.baris.map((b) => b.idPeminjaman), ['PJ-006', 'PJ-007', 'PJ-008', 'PJ-009', 'PJ-010']);
        assert.ok(nov.baris.every((b) => b.tanggalPinjam >= '2026-11-01'));
    });

    test('Pengembalian difilter berdasarkan Tanggal Kembali, bukan tanggal pinjam', () => {
        const r = buatLaporan(DATA_MOCK, { jenis: 'pengembalian', awal: '2026-11-01', akhir: '2026-11-07' });
        // PJ-005 dipinjam 24 Okt tetapi kembali 1 Nov → masuk; PJ-003 dipinjam 20 Okt, kembali 2 Nov → masuk.
        assert.deepEqual(r.baris.map((b) => b.idPengembalian), ['KB-004', 'KB-005']);
        assert.equal(r.baris[0].pemroses.nama, 'Rina Wulandari');
    });

    test('periode tanpa transaksi menghasilkan laporan kosong', () => {
        assert.equal(buatLaporan(DATA_MOCK, { jenis: 'peminjaman', awal: '2026-01-01', akhir: '2026-01-31' }).baris.length, 0);
    });

    test('laporan mengikuti data sesi (bukan data terpisah)', () => {
        prosesPengembalian('PJ-006');
        const r = buatLaporan(data, { jenis: 'pengembalian', awal: TANGGAL_DEMO, akhir: TANGGAL_DEMO });
        assert.deepEqual(r.baris.map((b) => b.idPeminjaman), ['PJ-006']);
        assert.equal(buatLaporan(data, { jenis: 'peminjaman' }).ringkasan.find((x) => x.label === 'Selesai').nilai, 6);
    });
});
