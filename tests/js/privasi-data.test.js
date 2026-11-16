/**
 * Phase 8B — privasi aset frontend & aksi tulis yang jujur.
 *
 * 1. Kode yang dibundel (resources/js) tidak memuat data Anggota/transaksi mock;
 *    data halaman datang dari server (prop Inertia `data`).
 * 2. Bila build produksi ada (public/build), isinya tidak memuat data seed.
 * 3. Aksi tulis yang belum terhubung ke database selalu ditolak dengan pesan
 *    eksplisit — tidak ada simulasi "berhasil disimpan".
 * Jalankan: npm run test:data (jalankan `npm run build` dulu untuk butir 2).
 */
import assert from 'node:assert/strict';
import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { describe, test } from 'node:test';
import { fileURLToPath } from 'node:url';

import { AKSI_TERTUNDA, PESAN_AKSI_BELUM_TERSEDIA, aksiTulis } from '../../resources/js/utils/aksiTertunda.js';
import { GalatValidasi } from '../../resources/js/utils/galat.js';

const AKAR = fileURLToPath(new URL('../../', import.meta.url));
const JS = join(AKAR, 'resources/js');
const BUILD = join(AKAR, 'public/build');
const seed = JSON.parse(readFileSync(join(AKAR, 'database/seeders/data/perpustakaan-demo.json'), 'utf8'));

function semuaBerkas(dir) {
    return readdirSync(dir).flatMap((nama) => {
        const jalur = join(dir, nama);

        return statSync(jalur).isDirectory() ? semuaBerkas(jalur) : [jalur];
    });
}

/** Nilai sensitif dari data seed: kontak & nama Anggota, nama akun, kode transaksi, id akun mock. */
const SENSITIF = [
    ...seed.anggota.flatMap((a) => [a.kontak, a.nama]),
    ...seed.pengguna.map((p) => p.nama),
    ...seed.peminjaman.map((p) => p.idPeminjaman),
    ...seed.booking.map((b) => b.idBooking),
    ...seed.pengembalian.map((k) => k.idPengembalian),
    ...seed.pengguna.map((p) => p.idPengguna),
];

/** Kode tanpa komentar (blok, baris, HTML): contoh di dokumentasi bukan data. */
const tanpaKomentar = (kode) =>
    kode.replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*\/\/.*$/gm, '').replace(/<!--[\s\S]*?-->/g, '');

const temukanSensitif = (berkas, bersihkan = tanpaKomentar) =>
    berkas.flatMap((jalur) => {
        const isi = bersihkan(readFileSync(jalur, 'utf8'));

        return SENSITIF.filter((nilai) => isi.includes(nilai)).map((nilai) => `${jalur.slice(AKAR.length)}: ${nilai}`);
    });

describe('Privasi aset frontend (Phase 8B)', () => {
    test('kode frontend tidak memuat data Anggota, akun, atau transaksi mock', () => {
        const berkas = semuaBerkas(JS).filter((j) => /\.(js|vue)$/.test(j));

        assert.deepEqual(temukanSensitif(berkas), []);
    });

    test('frontend tidak mengimpor fixture pengujian atau store mock', () => {
        const pelanggar = semuaBerkas(JS)
            .filter((j) => /\.(js|vue)$/.test(j))
            .filter((j) => /from\s+['"][^'"]*(tests\/|fixtures\/|data\/mock|storeMock|config\/demo)/.test(readFileSync(j, 'utf8')))
            .map((j) => j.slice(AKAR.length));

        assert.deepEqual(pelanggar, []);
        assert.equal(existsSync(join(JS, 'data/mock')), false, 'resources/js/data/mock sudah dipindah ke tests/js/fixtures');
    });

    test('build produksi (bila ada) tidak memuat data seed dan tidak menyimpan artefak usang', (t) => {
        if (!existsSync(join(BUILD, 'manifest.json'))) {
            t.skip('public/build belum ada — jalankan npm run build');

            return;
        }
        const aset = semuaBerkas(join(BUILD, 'assets')).filter((j) => /\.(js|css|map)$/.test(j));
        // Build: dipindai apa adanya (termasuk komentar yang mungkin tersisa).
        assert.deepEqual(temukanSensitif(aset, (x) => x), []);

        const manifest = JSON.parse(readFileSync(join(BUILD, 'manifest.json'), 'utf8'));
        const dirujuk = new Set(Object.values(manifest).flatMap((e) => [e.file, ...(e.css ?? []), ...(e.assets ?? [])]));
        const usang = readdirSync(join(BUILD, 'assets')).map((f) => `assets/${f}`).filter((f) => !dirujuk.has(f));
        assert.deepEqual(usang, [], 'aset yang tidak dirujuk manifest (sisa build lama)');
    });
});

describe('Aksi tulis belum terhubung database (Phase 8B)', () => {
    test('setiap aksi tulis ditolak dengan GalatValidasi berpesan eksplisit', () => {
        assert.deepEqual(Object.keys(aksiTulis).sort(), Object.keys(AKSI_TERTUNDA).sort());

        for (const [nama, aksi] of Object.entries(aksiTulis)) {
            assert.throws(
                () => aksi({ kodeAnggota: 'X' }, []),
                (e) => e instanceof GalatValidasi && e.galat.umum === PESAN_AKSI_BELUM_TERSEDIA,
                nama,
            );
        }
        assert.match(PESAN_AKSI_BELUM_TERSEDIA, /Tidak ada data yang diubah/);
    });

    test('composable data halaman hanya membaca prop server dan mengekspor aksi penolak', () => {
        const isi = readFileSync(join(JS, 'composables/useDataPerpustakaan.js'), 'utf8');

        assert.match(isi, /page\.props\.data/);
        assert.match(isi, /\.\.\.aksiTulis/);
        assert.doesNotMatch(isi, /DATA_MOCK|salin\(|reactive\(/);
    });

    test('halaman tidak lagi mengaku menyimpan: tidak ada fungsi tulis yang melewati aksiTertunda', () => {
        const halaman = semuaBerkas(join(JS, 'Pages')).filter((j) => j.endsWith('.vue'));
        // Batch 2: daftar aksi tertunda kosong; halaman TIDAK boleh lagi memanggil fungsi tulis
        // simulasi lama sama sekali — semua aksi tulis lewat endpoint server.
        const FUNGSI_SIMULASI = ['tambahAnggota', 'ubahAnggota', 'tambahBuku', 'ubahBuku', 'catatPeminjaman', 'prosesPengembalian',
            'setujuiBooking', 'tolakBooking', 'realisasikanBooking', 'ajukanBooking', 'batalkanBooking', ...Object.keys(AKSI_TERTUNDA)];
        const pelanggar = halaman
            .filter((j) => new RegExp(`\\b(${FUNGSI_SIMULASI.join('|')})\\(`).test(readFileSync(j, 'utf8')))
            .map((j) => j.slice(AKAR.length));

        assert.deepEqual(pelanggar, [], 'tidak ada simulasi tulis di halaman');
    });
});
