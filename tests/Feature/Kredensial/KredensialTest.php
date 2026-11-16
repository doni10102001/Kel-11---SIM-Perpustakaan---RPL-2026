<?php

namespace Tests\Feature\Kredensial;

use App\Enums\AlasanTolakMasuk;
use App\Enums\StatusAnggota;
use App\Http\Requests\Auth\MasukRequest;
use App\Models\AuditLog;
use App\Models\Member;
use App\Models\User;
use App\Services\KredensialAkun;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\Feature\Database\MySqlTestCase;

/**
 * Phase 8C — manajemen kredensial nyata: buat akses login Anggota, reset kata
 * sandi Anggota oleh Admin, ganti kata sandi Admin sendiri, dan login dengan
 * kredensial baru. Semua di sim_perpustakaan_test (transaksi di-rollback).
 */
class KredensialTest extends MySqlTestCase
{
    private const SANDI_ADMIN = 'AdminUji-8C!';

    private const SANDI_ANGGOTA = 'AnggotaUji-8C!';

    private const SANDI_BARU = 'SandiBaru-8C!';

    private function admin(): User
    {
        return $this->buatAdmin(['password' => self::SANDI_ADMIN]);
    }

    private function anggotaTanpaAkun(StatusAnggota $status = StatusAnggota::Aktif): Member
    {
        return $this->buatAnggota(['status' => $status]);
    }

    private function anggotaBerakun(StatusAnggota $status = StatusAnggota::Aktif): Member
    {
        return $this->buatAnggota(['status' => $status], $this->buatAkunAnggota(['password' => self::SANDI_ANGGOTA]));
    }

    private function buatAkses(Member $m, array $isian, ?User $admin = null)
    {
        return $this->actingAs($admin ?? $this->admin())
            ->from("/admin/anggota/{$m->member_code}/ubah")
            ->post("/admin/anggota/{$m->member_code}/akses-login", $isian);
    }

    private function isianAkses(string $username = 'nadia.r', string $sandi = self::SANDI_ANGGOTA): array
    {
        return ['username' => $username, 'password' => $sandi, 'password_confirmation' => $sandi];
    }

    private function masuk(string $username, string $sandi)
    {
        return $this->from('/login')->post('/login', ['username' => $username, 'password' => $sandi]);
    }

    /** Tiru request HTTP baru: guard dibangun ulang dari sesi. */
    private function requestBaru(): void
    {
        Auth::forgetGuards();
    }

    // ---------------------------------------------------------------- 1–6, 16 buat akses login

    public function test_admin_membuat_akses_login_untuk_anggota_tersimpan(): void
    {
        $admin = $this->admin();
        $m = $this->anggotaTanpaAkun();

        $this->buatAkses($m, $this->isianAkses('Nadia.R'), $admin)
            ->assertRedirect("/admin/anggota/{$m->member_code}/ubah")
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'Nadia.R') && ! str_contains($s, self::SANDI_ANGGOTA));

        $akun = $m->fresh()->user;
        $this->assertNotNull($akun);
        $this->assertSame(['Nadia.R', 'anggota', null, true], [$akun->username, $akun->role->value, $akun->name, $akun->is_active]);
        $this->assertTrue(Hash::check(self::SANDI_ANGGOTA, $akun->password));
        $this->assertSame(StatusAnggota::Aktif, $m->fresh()->status, 'Status Anggota tidak berubah.');

        $log = AuditLog::where('action', 'anggota.akses_login.dibuat')->sole();
        $this->assertSame([$admin->id, 'member', $m->id], [$log->actor_id, $log->subject_type, $log->subject_id]);
        $this->assertStringNotContainsString(self::SANDI_ANGGOTA, json_encode($log->getAttributes()));
        $this->assertStringNotContainsString($akun->password, json_encode($log->getAttributes()));

        // Login nyata dengan kredensial baru → Phase 8D: wajib ganti kata sandi dulu.
        $this->assertTrue($akun->must_change_password);
        $this->post('/logout');
        $this->requestBaru();
        $this->masuk('nadia.r', self::SANDI_ANGGOTA)->assertRedirect(route('anggota.kata-sandi'));
        $this->assertAuthenticatedAs($akun, 'web');
    }

    public function test_akses_login_anggota_nonaktif_dibuat_tetapi_login_tetap_diblokir(): void
    {
        $m = $this->anggotaTanpaAkun(StatusAnggota::Nonaktif);

        $this->buatAkses($m, $this->isianAkses('melati.k'))->assertSessionHasNoErrors();
        $this->assertNotNull($m->fresh()->user_id);
        $this->assertSame(StatusAnggota::Nonaktif, $m->fresh()->status);

        $this->post('/logout');
        $this->requestBaru();
        $this->masuk('melati.k', self::SANDI_ANGGOTA)->assertSessionHasErrors(['username' => AlasanTolakMasuk::AnggotaNonaktif->pesan()]);
        $this->assertGuest('web');
    }

    public function test_username_duplikat_ditolak_tanpa_membedakan_huruf(): void
    {
        $admin = $this->admin();
        $this->anggotaBerakun()->user->forceFill(['username' => 'dipakai.1'])->save();
        $m = $this->anggotaTanpaAkun();
        $jumlah = User::count();

        foreach (['DIPAKAI.1', $admin->username, strtoupper($admin->username)] as $username) {
            $this->buatAkses($m, $this->isianAkses($username), $admin)
                ->assertSessionHasErrors(['username' => 'Username sudah digunakan. Gunakan username lain.']);
        }
        $this->assertSame($jumlah, User::count());
        $this->assertNull($m->fresh()->user_id);
    }

    public function test_anggota_yang_sudah_berakun_tidak_dapat_dibuatkan_akun_kedua(): void
    {
        $admin = $this->admin();
        $m = $this->anggotaBerakun();
        $jumlah = User::count();

        $this->buatAkses($m, $this->isianAkses('akun.kedua'), $admin)
            ->assertSessionHasErrors(['akses' => KredensialAkun::PESAN_SUDAH_PUNYA_AKUN]);
        $this->assertSame($jumlah, User::count());
    }

    public function test_pembuatan_akun_atomik_bila_penautan_gagal(): void
    {
        $m = $this->anggotaTanpaAkun();
        $jumlah = User::count();
        Member::saving(fn () => throw new RuntimeException('Simulasi kegagalan penautan akun.'));

        try {
            $this->app->make(KredensialAkun::class)->buatAksesLogin($m->member_code, 'atomik.uji', self::SANDI_ANGGOTA, $this->admin());
            $this->fail('Seharusnya gagal.');
        } catch (RuntimeException) {
        } finally {
            Member::flushEventListeners();
        }

        $this->assertSame($jumlah + 1, User::count(), 'Hanya akun Admin uji yang bertambah; akun Anggota di-rollback.');
        $this->assertFalse(User::where('username', 'atomik.uji')->exists());
        $this->assertNull($m->fresh()->user_id);
        $this->assertFalse(AuditLog::where('action', 'anggota.akses_login.dibuat')->exists());
    }

    public function test_validasi_isian_akses_login(): void
    {
        // Satu Admin untuk semua permintaan (auth.session mengikat sesi ke satu akun).
        $admin = $this->admin();
        $m = $this->anggotaTanpaAkun();

        $this->buatAkses($m, ['username' => 'valid.user', 'password' => self::SANDI_ANGGOTA, 'password_confirmation' => 'berbeda-123'], $admin)
            ->assertSessionHasErrors(['password' => 'Konfirmasi Kata Sandi tidak sama dengan Kata Sandi.']);
        $this->buatAkses($m, $this->isianAkses('ada spasi'), $admin)->assertSessionHasErrors('username');
        $this->buatAkses($m, $this->isianAkses('ab'), $admin)->assertSessionHasErrors(['username' => 'Username harus 3–30 karakter.']);
        $this->buatAkses($m, $this->isianAkses('valid.user', '12345'), $admin)->assertSessionHasErrors(['password' => 'Kata Sandi minimal 6 karakter.']);
        $this->buatAkses($m, $this->isianAkses('valid.user', str_repeat('x', 65)), $admin)->assertSessionHasErrors(['password' => 'Kata Sandi maksimal 64 karakter.']);
        $this->buatAkses($m, [], $admin)->assertSessionHasErrors(['username', 'password', 'password_confirmation']);

        $this->assertNull($m->fresh()->user_id);
        $this->assertNull(session()->getOldInput('password'), 'Kata sandi tidak dikembalikan ke form.');
        $this->assertNull(session()->getOldInput('password_confirmation'));
    }

    public function test_klien_tidak_dapat_menetapkan_field_istimewa(): void
    {
        $m = $this->anggotaTanpaAkun();

        $this->buatAkses($m, $this->isianAkses('coba.admin') + ['role' => 'admin', 'is_active' => 0, 'name' => 'Penyusup', 'user_id' => 999999])
            ->assertSessionHasNoErrors();

        $akun = User::where('username', 'coba.admin')->sole();
        $this->assertSame(['anggota', null, true], [$akun->role->value, $akun->name, $akun->is_active]);
        $this->assertSame($akun->id, $m->fresh()->user_id);
        $this->assertFalse($akun->isAdmin());
    }

    public function test_anggota_tidak_dikenal_404(): void
    {
        $this->buatAkses(new Member(['member_code' => 'TIDAK-ADA']), $this->isianAkses('x.user'))->assertNotFound();
    }

    // ---------------------------------------------------------------- 7–10 reset kata sandi Anggota

    public function test_reset_kata_sandi_anggota_kata_sandi_lama_gagal_baru_berhasil(): void
    {
        $admin = $this->admin();
        $m = $this->anggotaBerakun();
        $akun = $m->user;
        $profil = $m->fresh()->only(['name', 'contact', 'status']);

        $this->actingAs($admin)->from("/admin/anggota/{$m->member_code}/ubah")
            ->put("/admin/anggota/{$m->member_code}/kata-sandi", ['password' => self::SANDI_BARU, 'password_confirmation' => self::SANDI_BARU])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertTrue(Hash::check(self::SANDI_BARU, $akun->fresh()->password));
        $this->assertSame($profil, $m->fresh()->only(['name', 'contact', 'status']), 'Data profil lain tidak berubah.');
        $this->assertSame($akun->username, $akun->fresh()->username);
        $this->assertTrue(AuditLog::where('action', 'anggota.kata_sandi.direset')->where('actor_id', $admin->id)->exists());

        $this->post('/logout');
        $this->requestBaru();
        $this->masuk($akun->username, self::SANDI_ANGGOTA)->assertSessionHasErrors(['username' => MasukRequest::PESAN_GAGAL]);
        $this->masuk($akun->username, self::SANDI_BARU)->assertRedirect(route('anggota.kata-sandi'));
        $this->assertAuthenticatedAs($akun, 'web');
        $this->assertTrue($akun->fresh()->must_change_password, 'Phase 8D: reset → wajib ganti kata sandi.');
        $this->get('/admin/dashboard')->assertForbidden();
    }

    public function test_reset_untuk_anggota_tanpa_akun_dan_konfirmasi_salah_ditolak(): void
    {
        $admin = $this->admin();
        $tanpa = $this->anggotaTanpaAkun();
        $berakun = $this->anggotaBerakun();
        $hash = $berakun->user->password;

        $this->actingAs($admin)->put("/admin/anggota/{$tanpa->member_code}/kata-sandi", ['password' => self::SANDI_BARU, 'password_confirmation' => self::SANDI_BARU])
            ->assertSessionHasErrors(['akses' => KredensialAkun::PESAN_BELUM_PUNYA_AKUN]);
        $this->put("/admin/anggota/{$berakun->member_code}/kata-sandi", ['password' => self::SANDI_BARU, 'password_confirmation' => 'lain-123456'])
            ->assertSessionHasErrors(['password' => 'Konfirmasi Kata Sandi Baru tidak sama dengan Kata Sandi Baru.']);
        $this->put("/admin/anggota/{$berakun->member_code}/kata-sandi", [])
            ->assertSessionHasErrors(['password', 'password_confirmation']);

        $this->assertSame($hash, $berakun->user->fresh()->password);
        $this->assertNull($tanpa->fresh()->user_id);
    }

    public function test_sesi_anggota_sebelumnya_berakhir_setelah_reset(): void
    {
        $m = $this->anggotaBerakun();
        $this->masuk($m->user->username, self::SANDI_ANGGOTA)->assertRedirect(route('anggota.dashboard'));
        $this->get('/anggota/dashboard')->assertOk();

        $this->app->make(KredensialAkun::class)->resetKataSandiAnggota($m->member_code, self::SANDI_BARU, $this->admin());
        $this->requestBaru();

        $this->get('/anggota/history')->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_reset_menghapus_baris_sesi_database_anggota(): void
    {
        config(['session.driver' => 'database', 'session.connection' => self::KONEKSI]);
        $m = $this->anggotaBerakun();
        $lain = $this->anggotaBerakun();
        foreach ([[$m->user_id, 'sesi-anggota-a'], [$m->user_id, 'sesi-anggota-b'], [$lain->user_id, 'sesi-anggota-lain']] as [$uid, $id]) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $uid, 'payload' => base64_encode('a:0:{}'), 'last_activity' => time()]);
        }

        $this->app->make(KredensialAkun::class)->resetKataSandiAnggota($m->member_code, self::SANDI_BARU, $this->admin());

        $this->assertSame(['sesi-anggota-lain'], DB::table('sessions')->pluck('id')->all());
    }

    // ---------------------------------------------------------------- 11–13 ganti kata sandi Admin

    public function test_admin_mengganti_kata_sandi_sendiri_dan_sesi_ini_tetap_berlaku(): void
    {
        $admin = $this->admin();
        $this->masuk($admin->username, self::SANDI_ADMIN);
        $idLama = session()->getId();

        $this->from('/admin/kata-sandi')->put('/admin/kata-sandi', [
            'current_password' => self::SANDI_ADMIN, 'password' => self::SANDI_BARU, 'password_confirmation' => self::SANDI_BARU,
        ])->assertRedirect('/admin/kata-sandi')->assertSessionHasNoErrors()->assertSessionHas('status');

        $this->assertTrue(Hash::check(self::SANDI_BARU, $admin->fresh()->password));
        $this->assertNotSame($idLama, session()->getId(), 'ID sesi diregenerasi.');
        $this->assertTrue(AuditLog::where('action', 'akun.kata_sandi.diubah')->where('actor_id', $admin->id)->exists());

        // Sesi saat ini tetap masuk pada request berikutnya.
        $this->requestBaru();
        $this->get('/admin/dashboard')->assertOk();
        $this->assertAuthenticatedAs($admin, 'web');

        $this->post('/logout');
        $this->requestBaru();
        $this->masuk($admin->username, self::SANDI_ADMIN)->assertSessionHasErrors(['username' => MasukRequest::PESAN_GAGAL]);
        $this->masuk($admin->username, self::SANDI_BARU)->assertRedirect(route('admin.dashboard'));
    }

    public function test_kata_sandi_saat_ini_salah_dan_validasi_ganti_kata_sandi(): void
    {
        $admin = $this->admin();
        $hash = $admin->password;
        $this->actingAs($admin)->from('/admin/kata-sandi');

        $this->put('/admin/kata-sandi', ['current_password' => 'salah-total', 'password' => self::SANDI_BARU, 'password_confirmation' => self::SANDI_BARU])
            ->assertSessionHasErrors(['current_password' => 'Kata Sandi Saat Ini tidak sesuai.']);
        $this->put('/admin/kata-sandi', ['current_password' => self::SANDI_ADMIN, 'password' => self::SANDI_ADMIN, 'password_confirmation' => self::SANDI_ADMIN])
            ->assertSessionHasErrors(['password' => 'Kata Sandi Baru harus berbeda dari Kata Sandi Saat Ini.']);
        $this->put('/admin/kata-sandi', ['current_password' => self::SANDI_ADMIN, 'password' => self::SANDI_BARU, 'password_confirmation' => 'beda-123456'])
            ->assertSessionHasErrors('password');

        $this->assertSame($hash, $admin->fresh()->password);
        $this->assertNull(session()->getOldInput('current_password'));
    }

    public function test_verifikasi_kata_sandi_saat_ini_dibatasi(): void
    {
        $this->actingAs($this->admin())->from('/admin/kata-sandi');

        for ($i = 0; $i < 6; $i++) {
            $this->put('/admin/kata-sandi', ['current_password' => "salah-{$i}", 'password' => self::SANDI_BARU, 'password_confirmation' => self::SANDI_BARU])
                ->assertSessionHasErrors('current_password');
        }
        $this->put('/admin/kata-sandi', ['current_password' => self::SANDI_ADMIN, 'password' => self::SANDI_BARU, 'password_confirmation' => self::SANDI_BARU])
            ->assertStatus(429);
    }

    public function test_sesi_admin_lain_berakhir_setelah_ganti_kata_sandi(): void
    {
        config(['session.driver' => 'database', 'session.connection' => self::KONEKSI]);
        $segarkan = function () {
            $this->app['session']->forgetDrivers();
            $this->app->forgetInstance('session.store');
            Auth::forgetGuards();
        };
        $cookie = config('session.cookie');
        $admin = $this->admin();

        $segarkan();
        $sesiLain = $this->masuk($admin->username, self::SANDI_ADMIN)->getCookie($cookie)->getValue();
        $segarkan();
        $sesiIni = $this->masuk($admin->username, self::SANDI_ADMIN)->getCookie($cookie)->getValue();
        $this->assertNotSame($sesiLain, $sesiIni);

        $segarkan();
        $this->withCookie($cookie, $sesiIni)->get('/admin/kata-sandi')->assertOk();
        $segarkan();
        $respons = $this->withCookie($cookie, $sesiIni)->from('/admin/kata-sandi')->put('/admin/kata-sandi', [
            'current_password' => self::SANDI_ADMIN, 'password' => self::SANDI_BARU, 'password_confirmation' => self::SANDI_BARU,
        ])->assertSessionHasNoErrors();
        $sesiBaru = $respons->getCookie($cookie)->getValue();

        $this->assertFalse(DB::table('sessions')->where('id', $sesiLain)->exists(), 'Baris sesi lain dihapus.');
        $this->assertFalse(DB::table('sessions')->where('id', $sesiIni)->exists(), 'ID sesi lama dihancurkan (regenerate).');

        $segarkan();
        $this->withCookie($cookie, $sesiLain)->get('/admin/dashboard')->assertRedirect(route('login'));
        $segarkan();
        $this->withCookie($cookie, $sesiBaru)->get('/admin/dashboard')->assertOk();
    }

    // ---------------------------------------------------------------- 14, 18 otorisasi

    public function test_anggota_dan_tamu_tidak_dapat_mengelola_kredensial(): void
    {
        $m = $this->anggotaTanpaAkun();
        $berakun = $this->anggotaBerakun();
        $isian = $this->isianAkses('serang.1');
        $sandi = ['password' => self::SANDI_BARU, 'password_confirmation' => self::SANDI_BARU];

        $this->post("/admin/anggota/{$m->member_code}/akses-login", $isian)->assertRedirect(route('login'));
        $this->putJson("/admin/anggota/{$berakun->member_code}/kata-sandi", $sandi)->assertUnauthorized();
        $this->put('/admin/kata-sandi', ['current_password' => 'x'] + $sandi)->assertRedirect(route('login'));
        $this->get('/admin/kata-sandi')->assertRedirect(route('login'));

        $this->actingAs($berakun->user);
        $this->post("/admin/anggota/{$m->member_code}/akses-login", $isian)->assertForbidden();
        $this->put("/admin/anggota/{$berakun->member_code}/kata-sandi", $sandi)->assertForbidden();
        $this->put('/admin/kata-sandi', ['current_password' => self::SANDI_ANGGOTA] + $sandi)->assertForbidden();
        $this->get('/admin/kata-sandi')->assertForbidden();

        $this->assertNull($m->fresh()->user_id);
        $this->assertTrue(Hash::check(self::SANDI_ANGGOTA, $berakun->user->fresh()->password));
    }

    public function test_csrf_wajib_untuk_mutasi_kredensial(): void
    {
        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $m = $this->anggotaTanpaAkun();
        $this->actingAs($this->admin());

        $this->post("/admin/anggota/{$m->member_code}/akses-login", $this->isianAkses('csrf.uji'))->assertStatus(419);
        $this->put('/admin/kata-sandi', ['current_password' => self::SANDI_ADMIN, 'password' => self::SANDI_BARU, 'password_confirmation' => self::SANDI_BARU])->assertStatus(419);
        $this->assertNull($m->fresh()->user_id);
    }

    public function test_halaman_ubah_kata_sandi_admin(): void
    {
        $this->actingAs($this->admin())->get('/admin/kata-sandi')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/KataSandi')
            ->where('data', [])
            ->where('demo.halaman.judul', 'Ubah Kata Sandi')
            ->where('demo.rute', fn ($rute) => $rute['kata-sandi.update'] === route('admin.kata-sandi.update')));
    }

    // ---------------------------------------------------------------- 17, 19, 20 privasi & cakupan

    public function test_respons_dan_log_tidak_memuat_kata_sandi_atau_hash(): void
    {
        $pesanLog = [];
        Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$pesanLog) {
            $pesanLog[] = $e->message.json_encode($e->context);
        });
        $admin = $this->admin();
        $m = $this->anggotaTanpaAkun();

        $this->buatAkses($m, $this->isianAkses('privasi.uji'), $admin);
        $this->put("/admin/anggota/{$m->member_code}/kata-sandi", ['password' => self::SANDI_BARU, 'password_confirmation' => self::SANDI_BARU]);

        $props = [];
        $this->get("/admin/anggota/{$m->member_code}/ubah")->assertOk()->assertInertia(function (Assert $page) use (&$props) {
            $props = $page->toArray()['props'];
        });
        $json = json_encode($props);

        foreach ([self::SANDI_ANGGOTA, self::SANDI_BARU, self::SANDI_ADMIN, ...DB::table('users')->pluck('password')] as $rahasia) {
            $this->assertStringNotContainsString($rahasia, $json);
            foreach ($pesanLog as $baris) {
                $this->assertStringNotContainsString($rahasia, $baris);
            }
            $this->assertStringNotContainsString($rahasia, (string) AuditLog::query()->get()->toJson());
        }
        $this->assertSame('privasi.uji', collect($props['data']['pengguna'])->firstWhere('username', 'privasi.uji')['idPengguna']);
    }

    public function test_hanya_mutasi_kredensial_dan_anggota_yang_menjadi_rute_tulis(): void
    {
        // Rute aplikasi saja: redirect `/` (semua metode) dan rute disk lokal bawaan framework
        // (storage/{path}, wajib URL bertanda tangan) dikecualikan.
        $tulis = collect(Route::getRoutes()->getRoutes())
            ->reject(fn ($r) => $r->uri() === '/' || str_starts_with($r->uri(), 'storage/'))
            ->filter(fn ($r) => array_diff($r->methods(), ['GET', 'HEAD']) !== [])
            ->map(fn ($r) => implode('|', array_diff($r->methods(), ['HEAD'])).' '.$r->uri())
            ->sort()->values()->all();

        $this->assertSame([
            'POST admin/anggota',
            'POST admin/anggota/{kodeAnggota}/akses-login',
            'POST login',
            'POST logout',
            'PUT admin/anggota/{kodeAnggota}',
            'PUT admin/anggota/{kodeAnggota}/kata-sandi',
            'PUT admin/kata-sandi',
            'PUT anggota/kata-sandi',
        ], $tulis, 'Aksi tulis lain (buku, transaksi, booking) belum boleh punya endpoint.');
    }
}
