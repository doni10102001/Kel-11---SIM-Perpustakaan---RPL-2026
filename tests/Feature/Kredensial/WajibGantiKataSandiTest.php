<?php

namespace Tests\Feature\Kredensial;

use App\Http\Middleware\WajibGantiKataSandi;
use App\Http\Requests\Auth\MasukRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\KredensialAkun;
use App\Support\NavigasiDemo;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Database\MySqlTestCase;

/**
 * Phase 8D — Anggota wajib mengganti kata sandi setelah Admin membuat akses
 * login atau mereset kata sandinya; penegakan di middleware server. Juga
 * pengerasan SSR & storage. Semua di sim_perpustakaan_test (di-rollback).
 */
class WajibGantiKataSandiTest extends MySqlTestCase
{
    private const SANDI_ADMIN = 'AdminUji-8D!';

    private const SANDI_DARI_ADMIN = 'DariAdmin-8D!';

    private const SANDI_SENDIRI = 'MilikSendiri-8D!';

    private KredensialAkun $kredensial;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kredensial = $this->app->make(KredensialAkun::class);
    }

    private function admin(): User
    {
        return $this->buatAdmin(['password' => self::SANDI_ADMIN]);
    }

    /** Anggota Aktif yang akunnya baru dibuat Admin (wajib ganti kata sandi). */
    private function anggotaBaruDariAdmin(string $username = 'ag.baru'): User
    {
        $m = $this->buatAnggota();
        $this->kredensial->buatAksesLogin($m->member_code, $username, self::SANDI_DARI_ADMIN, $this->admin());

        return User::where('username', $username)->sole();
    }

    private function masuk(string $username, string $sandi)
    {
        return $this->from('/login')->post('/login', ['username' => $username, 'password' => $sandi]);
    }

    private function gantiSendiri(array $isian)
    {
        return $this->from('/anggota/kata-sandi')->put('/anggota/kata-sandi', $isian);
    }

    private function isianValid(string $lama = self::SANDI_DARI_ADMIN, string $baru = self::SANDI_SENDIRI): array
    {
        return ['current_password' => $lama, 'password' => $baru, 'password_confirmation' => $baru];
    }

    private function requestBaru(): void
    {
        Auth::forgetGuards();
    }

    // ---------------------------------------------------------------- 1–2 flag diset Admin

    public function test_akun_baru_dari_admin_wajib_ganti_kata_sandi(): void
    {
        $akun = $this->anggotaBaruDariAdmin();

        $this->assertTrue($akun->must_change_password);
        $this->assertTrue($akun->wajibGantiKataSandi());
        $this->masuk('ag.baru', self::SANDI_DARI_ADMIN)->assertRedirect(route('anggota.kata-sandi'));
        $this->assertAuthenticatedAs($akun, 'web');
    }

    public function test_reset_oleh_admin_mewajibkan_ganti_lagi_dan_mengakhiri_sesi(): void
    {
        $m = $this->buatAnggota([], $this->buatAkunAnggota(['password' => self::SANDI_SENDIRI]));
        $akun = $m->user;
        $this->assertFalse($akun->must_change_password, 'Akun lama default false.');
        $profil = $m->only(['name', 'contact', 'status']);

        $this->masuk($akun->username, self::SANDI_SENDIRI)->assertRedirect(route('anggota.dashboard'));
        $this->kredensial->resetKataSandiAnggota($m->member_code, self::SANDI_DARI_ADMIN, $this->admin());
        $this->requestBaru();

        $this->get('/anggota/dashboard')->assertRedirect(route('login'));
        $this->assertTrue($akun->fresh()->must_change_password);
        $this->assertSame($profil, $m->fresh()->only(['name', 'contact', 'status']));
        $this->masuk($akun->username, self::SANDI_DARI_ADMIN)->assertRedirect(route('anggota.kata-sandi'));
    }

    // ---------------------------------------------------------------- 3–5, 14 penegakan

    public function test_rute_anggota_lain_diblokir_sampai_kata_sandi_diganti(): void
    {
        $this->actingAs($this->anggotaBaruDariAdmin());

        foreach (NavigasiDemo::halamanAnggota() as $kunci => $halaman) {
            if ($kunci === 'kata-sandi') {
                continue;
            }
            $this->get("/anggota/{$halaman['uri']}")
                ->assertRedirect(route('anggota.kata-sandi'))
                ->assertSessionHas('status', WajibGantiKataSandi::PESAN);
            // Permintaan Inertia (XHR) juga dialihkan, bukan dilayani.
            $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => (string) Inertia::getVersion(), 'X-Requested-With' => 'XMLHttpRequest'])
                ->get("/anggota/{$halaman['uri']}")->assertRedirect(route('anggota.kata-sandi'));
            // JSON → 403 dengan penanda, tanpa data halaman.
            $this->getJson("/anggota/{$halaman['uri']}")
                ->assertForbidden()->assertJson(['wajib_ganti_kata_sandi' => true])->assertJsonMissingPath('props');
        }
        // Akses langsung dengan parameter tidak membuka jalan pintas.
        $this->get('/anggota/history?lewati=1&must_change_password=0')->assertRedirect(route('anggota.kata-sandi'));
    }

    public function test_halaman_ganti_kata_sandi_dan_keluar_tetap_dapat_diakses_tanpa_loop(): void
    {
        $akun = $this->anggotaBaruDariAdmin();
        $this->actingAs($akun);

        $this->get('/anggota/kata-sandi')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Anggota/KataSandi')
            ->where('auth.akun.wajibGantiKataSandi', true)
            ->where('data', []));
        $this->get('/login')->assertRedirect(route('anggota.kata-sandi'), 'Pengalihan /login juga ke halaman ganti kata sandi (tanpa loop).');

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_anggota_wajib_tetap_ditolak_di_rute_admin_dan_status_keanggotaan_tetap_dicek(): void
    {
        $akun = $this->anggotaBaruDariAdmin();
        $this->actingAs($akun);

        $this->get('/admin/dashboard')->assertForbidden();
        $this->put('/admin/kata-sandi', $this->isianValid())->assertForbidden();

        // Status Anggota Nonaktif tetap mengakhiri sesi, walau sedang wajib ganti kata sandi.
        $akun->member->update(['status' => 'Nonaktif']);
        $this->requestBaru();
        $this->get('/anggota/kata-sandi')->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    // ---------------------------------------------------------------- 6–8 validasi

    public function test_validasi_penggantian_kata_sandi_anggota(): void
    {
        $akun = $this->anggotaBaruDariAdmin();
        $hash = $akun->password;
        $this->actingAs($akun);

        $this->gantiSendiri($this->isianValid('salah-total'))->assertSessionHasErrors(['current_password' => 'Kata Sandi Saat Ini tidak sesuai.']);
        $this->gantiSendiri(['current_password' => self::SANDI_DARI_ADMIN, 'password' => self::SANDI_SENDIRI, 'password_confirmation' => 'beda-123456'])
            ->assertSessionHasErrors(['password' => 'Konfirmasi Kata Sandi Baru tidak sama dengan Kata Sandi Baru.']);
        $this->gantiSendiri($this->isianValid(self::SANDI_DARI_ADMIN, self::SANDI_DARI_ADMIN))
            ->assertSessionHasErrors(['password' => 'Kata Sandi Baru harus berbeda dari Kata Sandi Saat Ini.']);
        $this->gantiSendiri($this->isianValid(self::SANDI_DARI_ADMIN, '12345'))->assertSessionHasErrors(['password' => 'Kata Sandi Baru minimal 6 karakter.']);

        $akun->refresh();
        $this->assertSame($hash, $akun->password);
        $this->assertTrue($akun->must_change_password);
        $this->assertNull(session()->getOldInput('current_password'));
        $this->assertNull(session()->getOldInput('password'));
    }

    public function test_percobaan_ganti_kata_sandi_anggota_dibatasi(): void
    {
        $this->actingAs($this->anggotaBaruDariAdmin());

        for ($i = 0; $i < 6; $i++) {
            $this->gantiSendiri($this->isianValid("salah-{$i}"))->assertSessionHasErrors('current_password');
        }
        $this->gantiSendiri($this->isianValid())->assertStatus(429);
    }

    // ---------------------------------------------------------------- 9–11, 15 berhasil

    public function test_penggantian_berhasil_menghapus_kewajiban_dan_kata_sandi_lama_tidak_berlaku(): void
    {
        $akun = $this->anggotaBaruDariAdmin();
        $this->masuk('ag.baru', self::SANDI_DARI_ADMIN);
        $idLama = session()->getId();

        $this->gantiSendiri($this->isianValid())
            ->assertRedirect(route('anggota.dashboard'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $akun->refresh();
        $this->assertFalse($akun->must_change_password);
        $this->assertTrue(Hash::check(self::SANDI_SENDIRI, $akun->password));
        $this->assertNotSame($idLama, session()->getId(), 'ID sesi diregenerasi.');
        $this->assertNull(session()->getOldInput('password'));

        $this->requestBaru();
        $this->get('/anggota/history')->assertOk();
        $this->get('/anggota/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page->where('auth.akun.wajibGantiKataSandi', false));

        $this->post('/logout');
        $this->requestBaru();
        $this->masuk('ag.baru', self::SANDI_DARI_ADMIN)->assertSessionHasErrors(['username' => MasukRequest::PESAN_GAGAL]);
        $this->masuk('ag.baru', self::SANDI_SENDIRI)->assertRedirect(route('anggota.dashboard'));
    }

    // ---------------------------------------------------------------- 12 sesi lain

    public function test_sesi_lain_anggota_berakhir_setelah_ganti_kata_sandi(): void
    {
        config(['session.driver' => 'database', 'session.connection' => self::KONEKSI]);
        $segarkan = function () {
            $this->app['session']->forgetDrivers();
            $this->app->forgetInstance('session.store');
            Auth::forgetGuards();
        };
        $cookie = config('session.cookie');
        $this->anggotaBaruDariAdmin();

        $segarkan();
        $sesiLain = $this->masuk('ag.baru', self::SANDI_DARI_ADMIN)->getCookie($cookie)->getValue();
        $segarkan();
        $sesiIni = $this->masuk('ag.baru', self::SANDI_DARI_ADMIN)->getCookie($cookie)->getValue();

        $segarkan();
        $sesiBaru = $this->withCookie($cookie, $sesiIni)->from('/anggota/kata-sandi')->put('/anggota/kata-sandi', $this->isianValid())
            ->assertRedirect(route('anggota.dashboard'))->getCookie($cookie)->getValue();

        $this->assertFalse(DB::table('sessions')->where('id', $sesiLain)->exists());
        $this->assertFalse(DB::table('sessions')->where('id', $sesiIni)->exists());

        $segarkan();
        $this->withCookie($cookie, $sesiLain)->get('/anggota/dashboard')->assertRedirect(route('login'));
        $segarkan();
        $this->withCookie($cookie, $sesiBaru)->get('/anggota/dashboard')->assertOk();
    }

    // ---------------------------------------------------------------- 13 Admin tidak terdampak

    public function test_admin_tidak_terdampak_kebijakan_wajib_ganti(): void
    {
        $admin = $this->admin();
        $this->assertFalse($admin->wajibGantiKataSandi());

        // Admin tidak dapat diwajibkan, bahkan lewat database (CHECK).
        $this->assertDitolakDb(3819, fn () => DB::table('users')->where('id', $admin->id)->update(['must_change_password' => 1]));

        $this->masuk($admin->username, self::SANDI_ADMIN)->assertRedirect(route('admin.dashboard'));
        foreach (['/admin/dashboard', '/admin/anggota', '/admin/kata-sandi'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->from('/admin/kata-sandi')->put('/admin/kata-sandi', ['current_password' => self::SANDI_ADMIN, 'password' => self::SANDI_SENDIRI, 'password_confirmation' => self::SANDI_SENDIRI])
            ->assertRedirect('/admin/kata-sandi')->assertSessionHasNoErrors();
        $this->assertFalse($admin->fresh()->must_change_password);
        $this->get('/admin/dashboard')->assertOk();
    }

    public function test_flag_tidak_dapat_diisi_dari_input_klien(): void
    {
        $akun = new User;
        $akun->fill(['username' => 'x', 'name' => null, 'password' => 'abcdef', 'must_change_password' => true]);
        $this->assertFalse($akun->must_change_password);

        // Anggota tidak dapat menghapus kewajibannya lewat field tambahan.
        $wajib = $this->anggotaBaruDariAdmin();
        $this->actingAs($wajib)->gantiSendiri($this->isianValid('salah') + ['must_change_password' => 0]);
        $this->assertTrue($wajib->fresh()->must_change_password);
    }

    // ---------------------------------------------------------------- 9 audit, 16 privasi

    public function test_audit_log_lengkap_tanpa_rahasia(): void
    {
        $pesanLog = [];
        Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$pesanLog) {
            $pesanLog[] = $e->message.json_encode($e->context);
        });
        $admin = $this->admin();
        $m = $this->buatAnggota();
        $this->kredensial->buatAksesLogin($m->member_code, 'audit.uji', self::SANDI_DARI_ADMIN, $admin);
        $this->kredensial->resetKataSandiAnggota($m->member_code, self::SANDI_DARI_ADMIN.'2', $admin);
        $akun = User::where('username', 'audit.uji')->sole();

        $this->actingAs($akun)->gantiSendiri($this->isianValid(self::SANDI_DARI_ADMIN.'2'))->assertSessionHasNoErrors();

        $log = AuditLog::query()->orderBy('id')->get();
        $this->assertSame(
            ['anggota.akses_login.dibuat', 'anggota.kata_sandi.direset', 'akun.kata_sandi.diubah', 'anggota.wajib_ganti_kata_sandi.selesai'],
            $log->pluck('action')->all(),
        );
        $this->assertSame([$admin->id, $admin->id, $akun->id, $akun->id], $log->pluck('actor_id')->all());

        $semua = $log->toJson().implode('', $pesanLog);
        foreach ([self::SANDI_DARI_ADMIN, self::SANDI_SENDIRI, self::SANDI_ADMIN, ...DB::table('users')->pluck('password')] as $rahasia) {
            $this->assertStringNotContainsString($rahasia, $semua);
        }
    }

    public function test_props_halaman_ganti_kata_sandi_tanpa_data_sensitif(): void
    {
        $akun = $this->anggotaBaruDariAdmin();
        $props = [];
        $this->actingAs($akun)->get('/anggota/kata-sandi')->assertInertia(function (Assert $page) use (&$props) {
            $props = $page->toArray()['props'];
        });
        $json = json_encode($props);

        foreach ([self::SANDI_DARI_ADMIN, $akun->password, 'must_change_password', 'remember_token'] as $rahasia) {
            $this->assertStringNotContainsString($rahasia, $json);
        }
        $this->assertSame([], $props['data']);
    }

    // ---------------------------------------------------------------- 18–19 SSR & storage

    public function test_ssr_inertia_nonaktif(): void
    {
        $this->assertFalse(config('inertia.ssr.enabled'));
        $this->assertStringContainsString('inertia({ ssr: false })', file_get_contents(base_path('vite.config.js')));

        $html = $this->actingAs($this->admin())->get('/admin/dashboard')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-server-rendered', $html, 'Tidak ada markup SSR → tidak ada hydration.');
        $this->assertStringContainsString('<script data-page="app" type="application/json">', $html);
    }

    public function test_penyajian_storage_lokal_tidak_tersedia(): void
    {
        $this->assertFalse(config('filesystems.disks.local.serve'));
        $this->assertFalse(Route::has('storage.local'));
        $this->assertFalse(Route::has('storage.local.upload'));
        $this->get('/storage/apa-saja.txt')->assertNotFound();
        $this->put('/storage/apa-saja.txt', ['isi' => 'x'])->assertNotFound();
        $this->assertSame('http://localhost:8000/storage/x.txt', Storage::disk('public')->url('x.txt'), 'Disk public tidak berubah.');
    }

    public function test_kolom_flag_tersedia_dengan_default_false(): void
    {
        $kolom = DB::selectOne("SELECT COLUMN_TYPE t, IS_NULLABLE n, COLUMN_DEFAULT d FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'must_change_password'");

        $this->assertSame(['tinyint(1)', 'NO', '0'], [$kolom->t, $kolom->n, $kolom->d]);
        $this->assertTrue(Schema::hasColumn('users', 'must_change_password'));
        $this->assertFalse($this->buatAkunAnggota()->fresh()->must_change_password, 'Akun yang tidak lewat alur Admin: default false.');
    }
}
