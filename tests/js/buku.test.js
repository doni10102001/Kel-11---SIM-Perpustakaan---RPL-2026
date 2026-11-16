/**
 * Phase 9B (Batch 1) — frontend Buku & eksemplar: simpan ke server lewat
 * Inertia, tanpa simulasi di browser; pencarian & pagination dijalankan
 * server (query string); ketersediaan dihitung dari eksemplar yang dikirim
 * server (bentuk prop DataPerpustakaan). Jalankan: npm run test:data
 */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, test } from 'node:test';

import { AKSI_TERTUNDA } from '../../resources/js/utils/aksiTertunda.js';
import { ketersediaanBuku } from '../../resources/js/utils/olahData.js';
import { nomorHalaman, parameterPencarian, urlPencarian } from '../../resources/js/utils/pencarianServer.js';
import { validasiBuku } from '../../resources/js/utils/validasiMaster.js';

const baca = (relatif) => readFileSync(new URL(`../../resources/js/${relatif}`, import.meta.url), 'utf8');

// Bentuk sama dengan prop `data` dari App\Queries\DataPerpustakaan (tanpa data peminjam).
const DATA_SERVER = Object.freeze({
    buku: [
        { kodeBuku: 'BK-901', judul: 'Rekayasa Perangkat Lunak', penulis: 'A', penerbit: 'P', tahunTerbit: 2024, kategori: 'Informatika' },
        { kodeBuku: 'BK-902', judul: 'Kimia Dasar', penulis: 'B', penerbit: 'Q', tahunTerbit: 2020, kategori: 'Sains' },
        { kodeBuku: 'BK-903', judul: 'Basis Data', penulis: 'C', penerbit: 'R', tahunTerbit: 2022, kategori: 'Informatika' },
    ],
    eksemplar: [
        { kodeEksemplar: 'EK-1', kodeBuku: 'BK-901', status: 'Tersedia' },
        { kodeEksemplar: 'EK-2', kodeBuku: 'BK-901', status: 'Dipesan' },
        { kodeEksemplar: 'EK-3', kodeBuku: 'BK-901', status: 'Dipinjam' },
        { kodeEksemplar: 'EK-4', kodeBuku: 'BK-901', status: 'Tersedia' },
        { kodeEksemplar: 'EK-5', kodeBuku: 'BK-902', status: 'Dipinjam' },
    ],
});

describe('Simpan Buku ke server (Phase 9B)', () => {
    const form = baca('Pages/Admin/Buku/Form.vue');
    const index = baca('Pages/Admin/Buku/Index.vue');

    test('POST /admin/buku atau PUT /admin/buku/{kode} lewat router Inertia yang terikat', () => {
        assert.match(form, /router\[mode === 'tambah' \? 'post' : 'put'\]\(mode === 'tambah' \? rute\('buku\.store'\) : rute\('buku\.update', \{ kodeBuku: kodeAsal \}\), payload/);
        // Regresi Phase 9A: method router tidak boleh dilepas dari objeknya.
        assert.doesNotMatch(form, /=\s*(mode === 'tambah' \? )?router\.(post|put)\b/);
    });

    test('payload: Kode Buku hanya saat Tambah; hanya eksemplar BARU yang dikirim', () => {
        assert.match(form, /if \(mode === 'tambah'\) payload\.kodeBuku = form\.kodeBuku;/);
        assert.match(form, /eksemplar: eksemplarBaru\.value\.map\(\(e\) => \(\{ kodeEksemplar: e\.kodeEksemplar, status: e\.status \}\)\)/);
        assert.doesNotMatch(form, /eksemplarLama\.value\.map\(\(e\) => \(\{ kodeEksemplar/, 'status eksemplar lama tidak pernah dikirim');
    });

    test('tidak ada simulasi simpan di browser dan kirim ganda dicegah', () => {
        assert.doesNotMatch(form, /tambahBuku\(|ubahBuku\(|setPesan\(|mode pratinjau/i);
        assert.match(form, /if \(memprosesSimpan\.value\) return;/);
        assert.match(form, /:loading="memprosesSimpan"/);
        assert.match(form, /onFinish: \(\) => \{\s*memprosesSimpan\.value = false;/);
    });

    test('galat eksemplar dari server (eksemplar.N.kodeEksemplar) ditampilkan di bagian Eksemplar', () => {
        assert.match(form, /k === 'eksemplar' \|\| k\.startsWith\('eksemplar\.'\)/);
    });

    test('Dipesan/Dipinjam tetap tidak dapat dipilih untuk eksemplar baru', () => {
        assert.match(form, /\{ value: 'Dipesan', label: 'Dipesan — melalui booking', disabled: true \}/);
        assert.match(form, /\{ value: 'Dipinjam', label: 'Dipinjam — melalui peminjaman', disabled: true \}/);
    });

    test('Data Buku menampilkan pesan sukses dari flash server (setelah commit)', () => {
        assert.match(index, /page\.props\.flash\?\.status \? \{ tone: 'success', title: 'Data buku tersimpan', message: page\.props\.flash\.status \}/);
        assert.doesNotMatch(index, /ambilPesan/);
    });

    test('aksi Buku tidak lagi ditolak "Belum tersedia" (Batch 2: tidak ada lagi aksi tertunda)', () => {
        assert.equal('tambahBuku' in AKSI_TERTUNDA, false);
        assert.equal('ubahBuku' in AKSI_TERTUNDA, false);
        assert.deepEqual(Object.keys(AKSI_TERTUNDA), []);
    });
});

describe('Validasi Buku di browser selaras dengan server (Phase 9B)', () => {
    const dasar = { kodeBuku: 'BK-999', judul: 'J', penulis: 'P', penerbit: 'Q', tahunTerbit: '2024', kategori: 'K' };
    const opsi = { mode: 'tambah', eksemplarBaru: [{ kodeEksemplar: 'EK-999', status: 'Tersedia' }], tahunMaks: 2026 };

    test('panjang maksimum mengikuti kolom MySQL', () => {
        assert.deepEqual(validasiBuku(dasar, DATA_SERVER, opsi), {});
        assert.equal(validasiBuku({ ...dasar, judul: 'j'.repeat(201) }, DATA_SERVER, opsi).judul, 'Judul maksimal 200 karakter.');
        assert.equal(validasiBuku({ ...dasar, judul: 'j'.repeat(200) }, DATA_SERVER, opsi).judul, undefined);
        assert.equal(validasiBuku({ ...dasar, penulis: 'p'.repeat(101) }, DATA_SERVER, opsi).penulis, 'Penulis maksimal 100 karakter.');
        assert.equal(validasiBuku({ ...dasar, penerbit: 'p'.repeat(101) }, DATA_SERVER, opsi).penerbit, 'Penerbit maksimal 100 karakter.');
        assert.equal(validasiBuku({ ...dasar, kategori: 'k'.repeat(51) }, DATA_SERVER, opsi).kategori, 'Kategori maksimal 50 karakter.');
    });

    test('eksemplar baru selain Tersedia ditolak', () => {
        const g = validasiBuku(dasar, DATA_SERVER, { ...opsi, eksemplarBaru: [{ kodeEksemplar: 'EK-999', status: 'Dipinjam' }] });
        assert.equal(g.eksemplar, 'Eksemplar baru harus berstatus Tersedia.');
    });
});

describe('Pencarian buku di server (Phase 9B)', () => {
    const index = baca('Pages/Admin/Buku/Index.vue');
    const katalog = baca('Pages/Anggota/CariBuku.vue');
    const composable = baca('composables/usePencarianBuku.js');
    const paginasi = baca('Components/PaginasiTabel.vue');

    test('Data Buku & Cari Buku tidak lagi memfilter di browser', () => {
        for (const isi of [index, katalog]) {
            assert.doesNotMatch(isi, /filterBuku|filterKatalog|utils\/pencarian\.js/);
            assert.match(isi, /usePencarianBuku\(rute\('(buku\.index|cari-buku)'\)\)/);
            assert.match(isi, /Menampilkan \{\{ meta\.total \}\} dari \{\{ meta\.totalSemua \}\} buku/);
            assert.match(isi, /<PaginasiTabel[\s\S]*:url="urlHalaman"/);
        }
        assert.match(katalog, /const daftarKategori = computed\(\(\) => data\.kategori\);/);
    });

    test('kata kunci dikirim lewat Inertia GET (query string), dengan jeda ketik dan tanpa request ganda', () => {
        assert.match(composable, /router\.get\(urlDasar, tujuan, \{ preserveState: true, preserveScroll: true, replace, only: \['data', 'pencarianBuku'\] \}\)/);
        assert.match(composable, /setTimeout\(\(\) => kunjungi\(\), JEDA_KETIK_MS\)/);
        assert.match(composable, /if \(JSON\.stringify\(tujuan\) === JSON\.stringify\(kini\)\) return;/);
        assert.match(composable, /onBeforeUnmount\(\(\) => clearTimeout\(jeda\)\)/);
    });

    test('tautan pagination membawa filter aktif dan mempertahankan state', () => {
        assert.match(composable, /urlHalaman: \(n\) => urlPencarian\(urlDasar, \{ q: meta\.value\.q, kategori: meta\.value\.kategori, halaman: n \}\)/);
        assert.match(paginasi, /:href="url\(n\)"/);
        assert.match(paginasi, /preserveState: true, preserveScroll: true/);
    });

    test('parameter query: tanpa nilai kosong, halaman 1 tidak ditulis, spasi tepi dirapikan', () => {
        assert.deepEqual(parameterPencarian({}), {});
        assert.deepEqual(parameterPencarian({ q: '  algo  ', kategori: '', halaman: 1 }), { q: 'algo' });
        assert.deepEqual(parameterPencarian({ q: '', kategori: 'Sains', halaman: 3 }), { kategori: 'Sains', page: 3 });
        assert.equal(urlPencarian('/admin/buku', { q: 'data & sistem', kategori: 'Ilmu Komputer', halaman: 2 }), '/admin/buku?q=data+%26+sistem&kategori=Ilmu+Komputer&page=2');
        assert.equal(urlPencarian('/anggota/cari-buku', { q: ' ' }), '/anggota/cari-buku');
    });

    test('nomor halaman maks. 5, berpusat pada halaman aktif', () => {
        assert.deepEqual(nomorHalaman(1, 1), [1]);
        assert.deepEqual(nomorHalaman(1, 3), [1, 2, 3]);
        assert.deepEqual(nomorHalaman(1, 10), [1, 2, 3, 4, 5]);
        assert.deepEqual(nomorHalaman(6, 10), [4, 5, 6, 7, 8]);
        assert.deepEqual(nomorHalaman(10, 10), [6, 7, 8, 9, 10]);
    });

    test('ketersediaan tetap dihitung dari seluruh eksemplar buku yang dikirim server', () => {
        assert.deepEqual(ketersediaanBuku(DATA_SERVER, 'BK-901'), { total: 4, Tersedia: 2, Dipesan: 1, Dipinjam: 1 });
        assert.deepEqual(ketersediaanBuku(DATA_SERVER, 'BK-902'), { total: 1, Tersedia: 0, Dipesan: 0, Dipinjam: 1 });
        assert.deepEqual(ketersediaanBuku(DATA_SERVER, 'BK-903'), { total: 0, Tersedia: 0, Dipesan: 0, Dipinjam: 0 });
    });
});
