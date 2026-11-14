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
    // Phase 8D: formulir ganti kata sandi dipakai bersama Admin & Anggota.
    const kataSandi = baca('Components/FormUbahKataSandi.vue');

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

    test('Simpan Anggota ke server: kredensial hanya saat Tambah, tidak pernah saat Ubah (Phase 9A)', () => {
        assert.match(form, /kirim\(mode === 'tambah' \? rute\('anggota\.store'\) : rute\('anggota\.update', \{ kodeAnggota: kodeAsal \}\), payload/);
        assert.match(form, /const payload = \{ nama: form\.nama, kontak: form\.kontak, status: form\.status \};\s*if \(mode === 'tambah'\) \{/);
        assert.match(form, /memprosesSimpan\.value = false;\s*kosongkanKataSandi\(\);/);
        assert.doesNotMatch(form, /tambahAnggota\(|ubahAnggota\(|setPesan\(/, 'tidak ada simulasi simpan di browser');
        assert.doesNotMatch(form, /kataSandiTerset|passwordHash/);
        assert.doesNotMatch(form, /mode pratinjau/i);
    });

    test('setelah sukses, default formulir dikosongkan (Inertia v3 menyalin isian sukses ke default)', () => {
        assert.match(kataSandi, /onSuccess: \(\) => form\.defaults\(\{ \.\.\.KOSONG \}\)/);
        assert.match(baca('Pages/Auth/Login.vue'), /onSuccess: \(\) => form\.defaults\(\{ username: '', password: '' \}\)/);
    });

    test('halaman Admin & Anggota memakai formulir bersama; Anggota wajib tanpa tombol Batal', () => {
        assert.match(baca('Pages/Admin/KataSandi.vue'), /<FormUbahKataSandi :batal-href="rute\('dashboard'\)" \/>/);
        const anggota = baca('Pages/Anggota/KataSandi.vue');
        assert.match(anggota, /<FormUbahKataSandi :wajib="wajib" :batal-href="wajib \? null : rute\('dashboard'\)" \/>/);
        assert.match(anggota, /akun\.value\?\.wajibGantiKataSandi/);
    });

    test('ganti kata sandi sendiri memakai PUT ke rute server dengan kata sandi saat ini', () => {
        assert.match(kataSandi, /const KOSONG = Object\.freeze\(\{ current_password: '', password: '', password_confirmation: '' \}\)/);
        assert.match(kataSandi, /form\.put\(rute\('kata-sandi\.update'\)/);
        assert.match(kataSandi, /autocomplete="current-password"/);
        assert.match(kataSandi, /autocomplete="new-password"/);
    });
});
