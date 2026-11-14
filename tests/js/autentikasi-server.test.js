/**
 * Phase 8A — UI tidak lagi memakai autentikasi mock di browser.
 * Halaman Masuk mengirim kredensial ke server, Keluar mengakhiri sesi server,
 * dan identitas akun hanya dibaca dari prop `auth.akun` (sesi Laravel).
 * Jalankan: npm run test:data
 */
import assert from 'node:assert/strict';
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { describe, test } from 'node:test';
import { fileURLToPath } from 'node:url';

const AKAR = fileURLToPath(new URL('../../resources/js/', import.meta.url));
const baca = (relatif) => readFileSync(join(AKAR, relatif), 'utf8');
/** Kode tanpa komentar JS (blok dan baris), agar dokumentasi tidak memicu kecocokan palsu. */
const tanpaKomentar = (kode) => kode.replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*\/\/.*$/gm, '');

function semuaBerkas(dir) {
    return readdirSync(dir).flatMap((nama) => {
        const jalur = join(dir, nama);

        return statSync(jalur).isDirectory() ? semuaBerkas(jalur) : [jalur];
    });
}

const IMPOR_MOCK_AUTH = /import\s*\{[^}]*\b(masuk|keluar|akunAktif)\b[^}]*\}\s*from\s*['"][^'"]*useDataPerpustakaan(\.js)?['"]/;

describe('Autentikasi server (Phase 8A)', () => {
    test('halaman Masuk mengirim kredensial ke server, bukan verifikasi mock', () => {
        const login = baca('Pages/Auth/Login.vue');

        assert.doesNotMatch(login, IMPOR_MOCK_AUTH);
        assert.doesNotMatch(login, /AKUN_DEMO|cariAkunDemo/);
        assert.match(login, /useForm\(\{ username: '', password: '' \}\)/);
        assert.match(login, /form\.post\(rute\('login'\)/);
        assert.match(login, /form\.reset\('password'\)/, 'kata sandi dikosongkan setelah percobaan');
    });

    test('halaman Masuk tidak lagi menawarkan akun pratinjau atau kata sandi bebas', () => {
        const login = baca('Pages/Auth/Login.vue');

        assert.doesNotMatch(login, /Mode pratinjau/);
        assert.doesNotMatch(login, /teks apa pun/);
        assert.doesNotMatch(login, /sim-demo-note/);
    });

    test('Keluar mengakhiri sesi server lewat POST /logout', () => {
        const shell = baca('Layouts/AppShell.vue');

        assert.doesNotMatch(shell, IMPOR_MOCK_AUTH);
        assert.match(shell, /router\.post\(rute\('logout'\)\)/);
    });

    test('identitas akun hanya dari sesi server (auth.akun), tanpa akun pratinjau AG-001', () => {
        const demo = tanpaKomentar(baca('composables/useDemo.js'));

        assert.doesNotMatch(demo, IMPOR_MOCK_AUTH);
        assert.match(demo, /page\.props\.auth\?\.akun \?\? null/);
        assert.doesNotMatch(demo, /AKUN_DEMO|AG-001/);
    });

    test('tidak ada halaman, layout, atau komponen yang memakai masuk/keluar/akunAktif mock', () => {
        const pelanggar = semuaBerkas(AKAR)
            .filter((jalur) => /\.(vue|js)$/.test(jalur) && !jalur.endsWith('useDataPerpustakaan.js'))
            .filter((jalur) => IMPOR_MOCK_AUTH.test(readFileSync(jalur, 'utf8')))
            .map((jalur) => jalur.slice(AKAR.length));

        assert.deepEqual(pelanggar, []);
    });

    test('tidak ada URL /demo/ yang di-hardcode di frontend', () => {
        const pelanggar = semuaBerkas(AKAR)
            .filter((jalur) => /\.(vue|js)$/.test(jalur))
            .filter((jalur) => /['"`]\/demo\//.test(readFileSync(jalur, 'utf8')))
            .map((jalur) => jalur.slice(AKAR.length));

        assert.deepEqual(pelanggar, []);
    });
});
