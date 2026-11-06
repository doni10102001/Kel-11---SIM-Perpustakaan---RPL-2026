/**
 * Tes konsistensi data mock (Phase 2). Jalankan: npm run test:data
 * Memakai test runner bawaan Node — tanpa dependency tambahan.
 */
import assert from 'node:assert/strict';
import { describe, test } from 'node:test';

import { AKUN_DEMO, TANGGAL_DEMO, cariAkunDemo } from '../../resources/js/config/demo.js';
import {
    DATA_MOCK,
    bookingAnggota,
    detailBooking,
    detailPeminjaman,
    ketersediaanBuku,
    peminjamanAnggota,
    periksaKonsistensi,
    ringkasanAdmin,
    ringkasanAnggota,
    salin,
    statusEksemplarDariTransaksi,
} from '../../resources/js/data/mock/index.js';
import { STATUS } from '../../resources/js/utils/status.js';

const D = DATA_MOCK;
const hitung = (daftar, cocok) => daftar.filter(cocok).length;
const periksa = (data) => periksaKonsistensi(data, { tanggalAcuan: TANGGAL_DEMO });

describe('Validator konsistensi', () => {
    test('dataset utama konsisten (tanpa pelanggaran)', () => {
        assert.deepEqual(periksa(D), []);
    });

    test('data awal dibekukan agar tidak berubah tanpa sengaja', () => {
        assert.ok(Object.isFrozen(D));
        assert.ok(Object.isFrozen(D.eksemplar));
        assert.ok(Object.isFrozen(D.eksemplar[0]));
        assert.throws(() => {
            'use strict';
            D.eksemplar[0].status = 'Dipinjam';
        }, TypeError);
    });

    // Tes negatif: validator harus menangkap setiap pelanggaran yang disengaja.
    const kasusRusak = [
        ['kode anggota duplikat', (d) => d.anggota.push({ ...d.anggota[0] }), /duplikat/],
        ['eksemplar menunjuk buku yang tidak ada', (d) => (d.eksemplar[0].kodeBuku = 'BK-999'), /BK-999 tidak ditemukan/],
        ['peminjaman menunjuk anggota yang tidak ada', (d) => (d.peminjaman[0].kodeAnggota = 'AG-999'), /AG-999 tidak ditemukan/],
        ['pengembalian menunjuk peminjaman yang tidak ada', (d) => (d.pengembalian[0].idPeminjaman = 'PJ-999'), /PJ-999 tidak ditemukan/],
        ['booking menunjuk eksemplar yang tidak ada', (d) => (d.booking[0].kodeEksemplar = 'EK-999'), /EK-999 tidak ditemukan/],
        ['dua peminjaman Aktif pada eksemplar yang sama', (d) => d.peminjaman.push({ ...d.peminjaman.find((p) => p.status === 'Aktif'), idPeminjaman: 'PJ-099' }), /lebih dari satu peminjaman Aktif/],
        ['peminjaman Aktif memiliki pengembalian', (d) => d.pengembalian.push({ idPengembalian: 'KB-099', idPeminjaman: 'PJ-006', tanggalKembali: '2026-11-06', diprosesOleh: 'USR-001' }), /Aktif tetapi punya pengembalian/],
        ['peminjaman Selesai tanpa pengembalian', (d) => (d.pengembalian = d.pengembalian.filter((k) => k.idPeminjaman !== 'PJ-001')), /Selesai tanpa data pengembalian/],
        ['status eksemplar tidak sesuai transaksi', (d) => (d.eksemplar.find((e) => e.kodeEksemplar === 'EK-003').status = 'Tersedia'), /seharusnya "Dipinjam"/],
        ['booking Disetujui tetapi eksemplar Tersedia', (d) => (d.eksemplar.find((e) => e.kodeEksemplar === 'EK-002').status = 'Tersedia'), /seharusnya "Dipesan"/],
        ['booking Direalisasikan tanpa peminjaman', (d) => (d.booking.find((b) => b.idBooking === 'BP-004').idPeminjaman = null), /wajib\) untuk status Direalisasikan/],
        ['realisasi ke peminjaman anggota lain', (d) => (d.booking.find((b) => b.idBooking === 'BP-004').idPeminjaman = 'PJ-006'), /berbeda dengan peminjaman/],
        ['booking Ditolak tanpa alasan', (d) => (d.booking.find((b) => b.status === 'Ditolak').alasanPenolakan = null), /tanpa alasan penolakan/],
        ['status di luar kosakata (Menunggu)', (d) => (d.booking.find((b) => b.status === 'Diajukan').status = 'Menunggu'), /bukan status booking yang valid/],
        ['peminjaman Aktif untuk anggota Nonaktif', (d) => (d.anggota.find((a) => a.kodeAnggota === 'AG-002').status = 'Nonaktif'), /anggota yang tidak Aktif/],
        ['tanggal transaksi melewati tanggal demo', (d) => (d.peminjaman[0].tanggalPinjam = '2026-12-01'), /melewati tanggal acuan/],
        ['tanggal tidak valid', (d) => (d.booking[0].tanggalBooking = '2026-02-30'), /tidak valid/],
        ['dikembalikan sebelum dipinjam', (d) => (d.pengembalian[0].tanggalKembali = '2026-10-01'), /dikembalikan sebelum dipinjam/],
        ['buku tanpa eksemplar', (d) => d.buku.push({ kodeBuku: 'BK-099', judul: 'X', penulis: 'X', penerbit: 'X', tahunTerbit: 2020, kategori: 'Umum' }), /tidak memiliki eksemplar/],
    ];

    for (const [nama, rusak, pola] of kasusRusak) {
        test(`menangkap: ${nama}`, () => {
            const d = salin(D);
            rusak(d);
            const galat = periksa(d);
            assert.ok(galat.some((g) => pola.test(g)), `Pelanggaran tidak terdeteksi. Hasil: ${JSON.stringify(galat)}`);
        });
    }
});

describe('1. Identitas unik', () => {
    const unik = (daftar, kunci) => assert.equal(new Set(daftar.map((r) => r[kunci])).size, daftar.length, `${kunci} harus unik`);

    test('Kode Anggota, Kode Buku, Kode Eksemplar unik', () => {
        unik(D.anggota, 'kodeAnggota');
        unik(D.buku, 'kodeBuku');
        unik(D.eksemplar, 'kodeEksemplar');
    });

    test('ID peminjaman, pengembalian, booking, dan pengguna unik', () => {
        unik(D.peminjaman, 'idPeminjaman');
        unik(D.pengembalian, 'idPengembalian');
        unik(D.booking, 'idBooking');
        unik(D.pengguna, 'idPengguna');
        unik(D.pengguna, 'username');
    });
});

describe('2. Relasi antar data', () => {
    test('setiap eksemplar terhubung ke buku yang valid; setiap buku punya ≥ 1 eksemplar', () => {
        D.eksemplar.forEach((e) => assert.ok(D.buku.some((b) => b.kodeBuku === e.kodeBuku), e.kodeEksemplar));
        D.buku.forEach((b) => assert.ok(ketersediaanBuku(D, b.kodeBuku).total >= 1, b.kodeBuku));
    });

    test('detail peminjaman dan booking selalu menemukan anggota, eksemplar, dan buku', () => {
        D.peminjaman.map((p) => detailPeminjaman(D, p)).forEach((r) => {
            assert.ok(r.anggota && r.eksemplar && r.buku && r.pemroses, r.idPeminjaman);
        });
        D.booking.map((b) => detailBooking(D, b)).forEach((r) => {
            assert.ok(r.anggota && r.eksemplar && r.buku, r.idBooking);
        });
    });

    test('setiap pengembalian menunjuk tepat satu peminjaman (1 → 0..1)', () => {
        const id = D.pengembalian.map((k) => k.idPeminjaman);
        assert.equal(new Set(id).size, id.length);
        id.forEach((i) => assert.ok(D.peminjaman.some((p) => p.idPeminjaman === i), i));
    });

    test('contoh dari Object Diagram dipertahankan', () => {
        const pj001 = detailPeminjaman(D, D.peminjaman.find((p) => p.idPeminjaman === 'PJ-001'));
        assert.equal(pj001.kodeAnggota, 'AG-001');
        assert.equal(pj001.kodeEksemplar, 'EK-001');
        assert.equal(pj001.buku.kodeBuku, 'BK-001');
        assert.equal(pj001.buku.judul, 'Algoritma Dasar');
        assert.equal(pj001.tanggalPinjam, '2026-10-15');
        assert.equal(pj001.pengembalian.idPengembalian, 'KB-001');
        assert.equal(pj001.tanggalKembali, '2026-10-18');

        const bp001 = detailBooking(D, D.booking.find((b) => b.idBooking === 'BP-001'));
        assert.equal(bp001.kodeAnggota, 'AG-002');
        assert.equal(bp001.buku.judul, 'Basis Data');
        assert.equal(bp001.tanggalBooking, '2026-10-16');
        assert.equal(bp001.status, 'Disetujui');
        assert.equal(bp001.eksemplar.status, 'Dipesan');
    });
});

describe('3. Status eksemplar dan peminjaman', () => {
    test('status eksemplar sama dengan status yang diturunkan dari transaksi saat ini', () => {
        D.eksemplar.forEach((e) => assert.equal(e.status, statusEksemplarDariTransaksi(D, e.kodeEksemplar), e.kodeEksemplar));
    });

    test('tidak ada dua peminjaman Aktif pada eksemplar yang sama', () => {
        const aktif = D.peminjaman.filter((p) => p.status === 'Aktif').map((p) => p.kodeEksemplar);
        assert.equal(new Set(aktif).size, aktif.length);
    });

    test('peminjaman Aktif tidak punya tanggal kembali; Selesai punya', () => {
        D.peminjaman.map((p) => detailPeminjaman(D, p)).forEach((r) => {
            if (r.status === 'Aktif') assert.equal(r.tanggalKembali, null, r.idPeminjaman);
            else assert.match(r.tanggalKembali, /^\d{4}-\d{2}-\d{2}$/, r.idPeminjaman);
        });
    });

    test('eksemplar Dipinjam ⇔ tepat satu peminjaman Aktif', () => {
        D.eksemplar.forEach((e) => {
            const n = hitung(D.peminjaman, (p) => p.kodeEksemplar === e.kodeEksemplar && p.status === 'Aktif');
            assert.equal(n, e.status === 'Dipinjam' ? 1 : 0, e.kodeEksemplar);
        });
    });
});

describe('4. Booking dan realisasi', () => {
    test('booking Disetujui yang belum direalisasikan memakai eksemplar Dipesan', () => {
        D.booking.filter((b) => b.status === 'Disetujui').forEach((b) => {
            assert.equal(D.eksemplar.find((e) => e.kodeEksemplar === b.kodeEksemplar).status, 'Dipesan', b.idBooking);
        });
    });

    test('booking Direalisasikan terhubung ke peminjaman anggota & eksemplar yang sama', () => {
        const realisasi = D.booking.filter((b) => b.status === 'Direalisasikan');
        assert.ok(realisasi.length >= 1);
        realisasi.map((b) => detailBooking(D, b)).forEach((r) => {
            assert.ok(r.peminjaman, r.idBooking);
            assert.equal(r.peminjaman.kodeAnggota, r.kodeAnggota);
            assert.equal(r.peminjaman.kodeEksemplar, r.kodeEksemplar);
            assert.ok(r.peminjaman.tanggalPinjam >= r.tanggalKeputusan);
        });
    });

    test('booking baru berstatus Diajukan — tidak ada status "Menunggu"', () => {
        assert.ok(D.booking.some((b) => b.status === 'Diajukan'));
        assert.ok(!D.booking.some((b) => b.status === 'Menunggu'));
    });

    test('alasan penolakan hanya pada booking Ditolak', () => {
        D.booking.forEach((b) => assert.equal(Boolean(b.alasanPenolakan), b.status === 'Ditolak', b.idBooking));
    });
});

describe('5. Ringkasan dashboard dihitung dari dataset', () => {
    test('ringkasan Admin sama dengan hitungan langsung dari tabel', () => {
        const r = ringkasanAdmin(D);
        assert.equal(r.anggota.total, D.anggota.length);
        assert.equal(r.anggota.Aktif + r.anggota.Nonaktif, r.anggota.total);
        assert.equal(r.buku.total, D.buku.length);
        assert.equal(r.eksemplar.total, D.eksemplar.length);
        assert.equal(r.eksemplar.Tersedia + r.eksemplar.Dipesan + r.eksemplar.Dipinjam, r.eksemplar.total);
        assert.equal(r.peminjaman.Aktif, hitung(D.peminjaman, (p) => p.status === 'Aktif'));
        assert.equal(r.peminjaman.Aktif, r.eksemplar.Dipinjam);
        assert.equal(r.booking.Diajukan, hitung(D.booking, (b) => b.status === 'Diajukan'));
        assert.equal(r.booking.Disetujui, r.eksemplar.Dipesan);
    });

    test('ringkasan berubah bila data berubah (bukan angka tetap)', () => {
        const d = salin(D);
        d.anggota.push({ ...d.anggota[0], kodeAnggota: 'AG-099' });
        assert.equal(ringkasanAdmin(d).anggota.total, ringkasanAdmin(D).anggota.total + 1);
    });

    test('nilai ringkasan Admin pada tanggal demo', () => {
        assert.deepEqual(ringkasanAdmin(D), {
            anggota: { total: 10, Aktif: 8, Nonaktif: 2 },
            buku: { total: 10 },
            eksemplar: { total: 18, Tersedia: 11, Dipesan: 2, Dipinjam: 5 },
            peminjaman: { Aktif: 5, Selesai: 5 },
            booking: { Diajukan: 2, Disetujui: 2 },
        });
    });
});

describe('6. Riwayat milik anggota demo', () => {
    test('akun pratinjau berasal dari dataset (Admin Rina Wulandari, Anggota AG-001 Nadia Rahmawati)', () => {
        assert.equal(AKUN_DEMO.admin.identitas, 'admin');
        assert.equal(AKUN_DEMO.admin.nama, 'Rina Wulandari');
        assert.equal(AKUN_DEMO.anggota.identitas, 'AG-001');
        assert.equal(AKUN_DEMO.anggota.nama, 'Nadia Rahmawati');
        assert.equal(cariAkunDemo(' ag-001 ')?.peran, 'anggota');
        assert.equal(cariAkunDemo('petugas'), null);
        const ag001 = D.anggota.find((a) => a.kodeAnggota === 'AG-001');
        assert.equal(ag001.status, 'Aktif');
    });

    test('AG-001 punya peminjaman Aktif dan Selesai, hanya miliknya sendiri', () => {
        const pinjam = peminjamanAnggota(D, 'AG-001');
        assert.ok(pinjam.every((p) => p.kodeAnggota === 'AG-001'));
        assert.deepEqual(pinjam.map((p) => `${p.idPeminjaman}:${p.status}`), ['PJ-001:Selesai', 'PJ-005:Selesai', 'PJ-007:Aktif']);
    });

    test('AG-001 punya booking dengan beberapa status, hanya miliknya sendiri', () => {
        const booking = bookingAnggota(D, 'AG-001');
        assert.ok(booking.every((b) => b.kodeAnggota === 'AG-001'));
        assert.deepEqual(booking.map((b) => `${b.idBooking}:${b.status}`), ['BP-003:Dibatalkan', 'BP-004:Direalisasikan', 'BP-007:Diajukan']);
    });

    test('ringkasan Dashboard Anggota AG-001', () => {
        assert.deepEqual(ringkasanAnggota(D, 'AG-001'), { bookingAktif: 1, peminjamanAktif: 1, riwayatSelesai: 2 });
    });

    test('anggota tanpa transaksi menghasilkan daftar kosong (kondisi tabel kosong)', () => {
        assert.deepEqual(peminjamanAnggota(D, 'AG-009'), []);
        assert.deepEqual(bookingAnggota(D, 'AG-009'), []);
    });
});

describe('7. Tanggal transaksi', () => {
    const iso = /^\d{4}-\d{2}-\d{2}$/;

    test('semua tanggal kejadian valid dan tidak melewati tanggal demo', () => {
        const kejadian = [
            ...D.peminjaman.map((p) => p.tanggalPinjam),
            ...D.pengembalian.map((k) => k.tanggalKembali),
            ...D.booking.map((b) => b.tanggalBooking),
            ...D.booking.map((b) => b.tanggalKeputusan).filter(Boolean),
        ];
        kejadian.forEach((t) => {
            assert.match(t, iso);
            assert.ok(t <= TANGGAL_DEMO, `${t} > ${TANGGAL_DEMO}`);
        });
    });

    test('urutan tanggal masuk akal', () => {
        D.peminjaman.map((p) => detailPeminjaman(D, p)).forEach((r) => {
            assert.ok(r.tanggalJatuhTempo > r.tanggalPinjam, r.idPeminjaman);
            if (r.tanggalKembali) assert.ok(r.tanggalKembali >= r.tanggalPinjam, r.idPeminjaman);
        });
        D.booking.filter((b) => b.tanggalKeputusan).forEach((b) => assert.ok(b.tanggalKeputusan >= b.tanggalBooking, b.idBooking));
    });

    test('peminjaman Aktif belum melewati jatuh tempo (tidak ada keterlambatan/denda di prototipe)', () => {
        D.peminjaman.filter((p) => p.status === 'Aktif').forEach((p) => assert.ok(p.tanggalJatuhTempo >= TANGGAL_DEMO, p.idPeminjaman));
    });

    test('durasi pinjam bervariasi — tidak ada durasi baku (TBD-05)', () => {
        const hari = (a, b) => (new Date(b) - new Date(a)) / 86400000;
        const durasi = new Set(D.peminjaman.map((p) => hari(p.tanggalPinjam, p.tanggalJatuhTempo)));
        assert.ok(durasi.size > 1, 'contoh jatuh tempo sengaja tidak seragam');
    });
});

describe('Cakupan data demonstrasi', () => {
    test('setiap status resmi muncul minimal sekali', () => {
        const ada = {
            anggota: new Set(D.anggota.map((a) => a.status)),
            eksemplar: new Set(D.eksemplar.map((e) => e.status)),
            peminjaman: new Set(D.peminjaman.map((p) => p.status)),
            booking: new Set(D.booking.map((b) => b.status)),
        };
        for (const [jenis, daftar] of Object.entries(STATUS)) {
            assert.deepEqual([...ada[jenis]].sort(), Object.keys(daftar).sort(), jenis);
        }
    });

    test('ada buku dengan satu eksemplar dan dengan beberapa eksemplar', () => {
        const jumlah = D.buku.map((b) => ketersediaanBuku(D, b.kodeBuku).total);
        assert.ok(jumlah.includes(1));
        assert.ok(jumlah.some((n) => n > 1));
    });

    test('ada buku tanpa eksemplar Tersedia (seluruhnya Dipinjam/Dipesan)', () => {
        assert.ok(D.buku.some((b) => ketersediaanBuku(D, b.kodeBuku).Tersedia === 0));
    });
});
