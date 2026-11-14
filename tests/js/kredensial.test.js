/**
 * Phase 8C — frontend kredensial: aksi eksplisit ke server, kata sandi tidak
 * disimpan/diulang di browser, dan Simpan Anggota tetap tidak membawa kredensial.
 * Jalankan: npm run test:data
 */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, test } from 'node:test';

const baca = (relatif) => readFileSync(new URL(`../../resources/js/${relatif}`, import.meta.url), 'utf8');

describe('Kredensial di frontend (Phase 8C)', () => {
    const form = baca('Pages/Admin/Anggota/Form.vue');
    const kataSandi = baca('Pages/Admin/KataSandi.vue');

    test('buat akses login & reset kata sandi dikirim ke endpoint server', () => {
        assert.match(form, /rute\(buat \? 'anggota\.akses-login' : 'anggota\.kata-sandi\.reset', \{ kodeAnggota: anggota\.kodeAnggota \}\)/);
        assert.match(form, /router\[buat \? 'post' : 'put'\]/);
        assert.match(form, /kirimAkses\('buat'\)/);
        assert.match(form, /kirimAkses\('reset'\)/);
    });

    test('isian kata sandi dikosongkan setelah setiap permintaan', () => {
        assert.match(form, /onFinish: \(\) => \{\s*memprosesAkses\.value = false;\s*kosongkanKataSandi\(\);/);
        assert.match(kataSandi, /onFinish: \(\) => \{\s*form\.reset\(\);/);
    });

    test('Simpan Anggota tidak membawa kredensial dan tidak menampilkan kata sandi', () => {
        assert.match(form, /tambahAnggota\(form, null\)/);
        assert.match(form, /ubahAnggota\(kodeAsal, form, null\)/);
        assert.doesNotMatch(form, /kataSandiTerset|passwordHash/);
        assert.doesNotMatch(form, /mode pratinjau/i);
    });

    test('setelah sukses, default formulir dikosongkan (Inertia v3 menyalin isian sukses ke default)', () => {
        assert.match(kataSandi, /onSuccess: \(\) => form\.defaults\(\{ \.\.\.KOSONG \}\)/);
        assert.match(baca('Pages/Auth/Login.vue'), /onSuccess: \(\) => form\.defaults\(\{ username: '', password: '' \}\)/);
    });

    test('ganti kata sandi Admin memakai PUT ke rute server dengan kata sandi saat ini', () => {
        assert.match(kataSandi, /const KOSONG = Object\.freeze\(\{ current_password: '', password: '', password_confirmation: '' \}\)/);
        assert.match(kataSandi, /form\.put\(rute\('kata-sandi\.update'\)/);
        assert.match(kataSandi, /autocomplete="current-password"/);
        assert.match(kataSandi, /autocomplete="new-password"/);
    });
});
