/**
 * Batch 3 — Dashboard & Laporan memakai hasil server (agregat SQL), bukan
 * perhitungan di browser; tautan kartu, filter, dan cetak dipertahankan.
 * Jalankan: npm run test:data
 */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, test } from 'node:test';

import { DEFINISI_LAPORAN, JENIS_LAPORAN, laporanDariServer } from '../../resources/js/utils/laporan.js';

const baca = (relatif) => readFileSync(new URL(`../../resources/js/${relatif}`, import.meta.url), 'utf8');

describe('Dashboard Admin (Batch 3)', () => {
    const isi = baca('Pages/Admin/Dashboard.vue');

    test('angka dari agregat server, tanpa perhitungan dataset di browser', () => {
        assert.match(isi, /data\.ringkasan/);
        assert.match(isi, /const peminjamanAktif = computed\(\(\) => data\.peminjamanAktif\);/);
        assert.match(isi, /const bookingBerjalan = computed\(\(\) => data\.bookingBerjalan\);/);
        assert.doesNotMatch(isi, /ringkasanAdmin\(|rekapStatus\(|data\.(anggota|buku|eksemplar|peminjaman|booking)\b(?!Aktif|Berjalan)/);
    });

    test('kartu ringkasan tetap menaut ke halaman yang sama', () => {
        for (const kunci of ['anggota.index', 'buku.index', 'pengembalian', 'booking.verifikasi']) {
            assert.ok(isi.includes(`:href="rute('${kunci}')"`), kunci);
        }
        assert.match(isi, /rekap\('booking', ringkasan\.value\.booking\)/, 'rekap booking mengikuti kosakata resmi (termasuk Kedaluwarsa)');
        assert.match(isi, /Hari ini: \{\{ ringkasan\.hariIni\.peminjaman \}\} peminjaman/);
    });
});

describe('Dashboard Anggota (Batch 3)', () => {
    const isi = baca('Pages/Anggota/Dashboard.vue');

    test('ringkasan & daftar dari server (data milik sesi), tanpa fungsi relasi di browser', () => {
        assert.match(isi, /data\.ringkasan/);
        assert.doesNotMatch(isi, /ringkasanAnggota\(|bookingAnggota\(|peminjamanAnggota\(|detailBooking\(|detailPeminjaman\(/);
        assert.match(isi, /ringkasan\.bookingDiajukan\} diajukan · \$\{ringkasan\.bookingDisetujui\} disetujui/);
        assert.match(isi, /Ambil sebelum \{\{ formatWaktu\(row\.batasPengambilan\) \}\}/);
    });
});

describe('Laporan dari server (Batch 3)', () => {
    const halaman = baca('Pages/Admin/Laporan.vue');

    test('baris & ringkasan dari server; filter URL, galat server, dan cetak dipertahankan', () => {
        assert.match(halaman, /laporanDariServer\(data\.laporan\)/);
        assert.doesNotMatch(halaman, /buatLaporan\(/);
        assert.match(halaman, /router\.get\(rute\('laporan'\), query/);
        assert.match(halaman, /page\.props\.galatLaporan/);
        assert.match(halaman, /window\.print\(\)/);
        assert.match(halaman, /dari \{\{ row\.idBooking \}\}/, 'asal booking peminjaman hasil realisasi');
        // Regresi: filter dari URL dimuat SEBELUM watch jenis (jika sesudahnya, galat validasi URL terhapus).
        assert.ok(halaman.indexOf("const query = new URLSearchParams") < halaman.indexOf('watch(\n    () => filter.jenis'));
        assert.match(halaman, /:error="galat\.awal"/, 'galat Periode Awal ditampilkan');
    });

    test('definisi tampilan untuk keempat jenis laporan SRS (tanpa format ekspor)', () => {
        assert.deepEqual(Object.keys(DEFINISI_LAPORAN), JENIS_LAPORAN.map((j) => j.value));
        assert.equal(DEFINISI_LAPORAN.peminjaman.kolomTanggal, 'Tanggal Pinjam');
        assert.equal(DEFINISI_LAPORAN.pengembalian.kolomTanggal, 'Tanggal Kembali');
        assert.doesNotMatch(halaman, /csv|xlsx|pdf/i);
    });

    test('laporanDariServer menggabungkan data server dengan definisi tampilan', () => {
        const l = laporanDariServer({ jenis: 'anggota', baris: [{ no: 1, kodeAnggota: 'AG-1' }], ringkasan: [{ label: 'Jumlah anggota', nilai: 1 }] });
        assert.equal(l.judul, 'Laporan Daftar Anggota');
        assert.equal(l.kolom.length, 5);
        assert.deepEqual(l.baris, [{ no: 1, kodeAnggota: 'AG-1' }]);
        assert.equal(laporanDariServer(null), null);
        assert.equal(laporanDariServer({ jenis: 'rahasia', baris: [] }), null);
    });
});
