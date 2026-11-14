<?php

namespace Tests\Feature\Auth;

use App\Enums\AlasanTolakMasuk;
use App\Enums\StatusAnggota;
use App\Http\Controllers\Auth\SesiController;
use App\Http\Requests\Auth\MasukRequest;
use App\Models\Member;
use App\Models\User;
use App\Support\NavigasiDemo;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Database\MySqlTestCase;

/**
 * Phase 8A — autentikasi nyata (username + hash MySQL), sesi server, logout,
 * CSRF, pembatasan percobaan, dan otorisasi peran.
 *
 * Semua data di database uji sim_perpustakaan_test (MySqlTestCase: transaksi
 * di-rollback). Database development dan data seed tidak disentuh.
 */
class AutentikasiTest extends MySqlTestCase
{
    private const SANDI = 'RahasiaUji-8A!';

    private function admin(array $atribut = []): User
    {
        return $this->buatAdmin(['password' => self::SANDI] + $atribut);
    }

    private function anggota(StatusAnggota $status = StatusAnggota::Aktif): User
    {
        $akun = $this->buatAkunAnggota(['password' => self::SANDI]);
        $this->buatAnggota(['status' => $status], $akun);

        return $akun;
    }

    private function masuk(string $username, string $sandi = self::SANDI)
    {
        return $this->from('/login')->post('/login', ['username' => $username, 'password' => $sandi]);
    }

    /** Tiru request HTTP baru: guard dibangun ulang dari sesi (bukan objek User di memori). */
    private function requestBaru(): void
    {
        Auth::forgetGuards();
    }

    // ---------------------------------------------------------------- 1–6 kredensial & kelayakan

    public function test_admin_valid_masuk_ke_dashboard_admin(): void
    {
        $admin = $this->admin();

        $this->masuk($admin->username)->assertRedirect(route('admin.dashboard'))->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($admin, 'web');
    }

    public function test_username_tidak_peka_huruf_besar_kecil_dan_spasi_tepi(): void
    {
        $admin = $this->admin(['username' => 'rina.w']);

        $this->masuk('  RINA.W ')->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'web');
    }

    public function test_kata_sandi_salah_dan_username_tidak_dikenal_mendapat_pesan_generik_yang_sama(): void
    {
        $admin = $this->admin();

        $this->masuk($admin->username, 'salah-total')
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['username' => MasukRequest::PESAN_GAGAL])
            ->assertSessionMissing('password');
        $this->assertGuest('web');

        $this->masuk('tidak.ada', self::SANDI)->assertSessionHasErrors(['username' => MasukRequest::PESAN_GAGAL]);
        $this->assertGuest('web');
    }

    public function test_kata_sandi_tidak_dikembalikan_ke_form_lama(): void
    {
        $admin = $this->admin();

        $this->masuk($admin->username, 'salah-total');

        $this->assertNull(session()->getOldInput('password'));
    }

    public function test_admin_nonaktif_ditolak_setelah_kata_sandi_cocok(): void
    {
        $admin = $this->admin();
        DB::table('users')->where('id', $admin->id)->update(['is_active' => 0]);

        $this->masuk($admin->username)->assertSessionHasErrors(['username' => AlasanTolakMasuk::AkunNonaktif->pesan()]);
        $this->assertGuest('web');

        // Kata sandi salah tetap pesan generik: status tidak dibocorkan.
        $this->masuk($admin->username, 'salah')->assertSessionHasErrors(['username' => MasukRequest::PESAN_GAGAL]);
    }

    public function test_anggota_aktif_masuk_ke_dashboard_anggota(): void
    {
        $akun = $this->anggota();

        $this->masuk($akun->username)->assertRedirect(route('anggota.dashboard'));
        $this->assertAuthenticatedAs($akun, 'web');
    }

    public function test_anggota_nonaktif_ditolak(): void
    {
        $akun = $this->anggota(StatusAnggota::Nonaktif);

        $this->masuk($akun->username)->assertSessionHasErrors(['username' => AlasanTolakMasuk::AnggotaNonaktif->pesan()]);
        $this->assertGuest('web');
    }

    public function test_akun_anggota_tanpa_data_anggota_ditolak(): void
    {
        $akun = $this->buatAkunAnggota(['password' => self::SANDI]);

        $this->masuk($akun->username)->assertSessionHasErrors(['username' => AlasanTolakMasuk::RelasiTidakValid->pesan()]);
        $this->assertGuest('web');
    }

    public function test_isian_wajib_divalidasi_server(): void
    {
        $this->post('/login', ['username' => '', 'password' => ''])
            ->assertSessionHasErrors(['username' => 'Identitas Pengguna wajib diisi.', 'password' => 'Kata Sandi wajib diisi.']);
        $this->assertGuest('web');
    }

    // ---------------------------------------------------------------- 7–8 sesi

    public function test_id_sesi_diganti_setelah_masuk_berhasil_saja(): void
    {
        $admin = $this->admin();
        $nama = config('session.cookie');

        $this->withCookie($nama, $idAwal = str_repeat('a', 40))->masuk($admin->username, 'salah');
        $this->assertSame($idAwal, session()->getId(), 'Gagal masuk tidak mengganti sesi.');

        $this->withCookie($nama, $idAwal)->masuk($admin->username);
        $this->assertNotSame($idAwal, session()->getId(), 'Session fixation: ID sesi wajib baru setelah masuk.');
        $this->assertAuthenticatedAs($admin, 'web');
    }

    public function test_keluar_mengakhiri_sesi_dan_mengganti_token_csrf(): void
    {
        $admin = $this->admin();
        $this->masuk($admin->username);
        session()->put('penanda', 'data-sesi-lama');
        $idSesi = session()->getId();
        $token = session()->token();

        $this->post('/logout')->assertRedirect(route('login'))->assertSessionHas('status', SesiController::PESAN_KELUAR);

        $this->assertGuest('web');
        $this->assertNotSame($idSesi, session()->getId());
        $this->assertNotSame($token, session()->token());
        $this->assertNull(session('penanda'));

        $this->requestBaru();
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
    }

    public function test_keluar_tanpa_sesi_diarahkan_ke_halaman_masuk(): void
    {
        $this->post('/logout')->assertRedirect(route('login'));
    }

    public function test_sesi_database_kedaluwarsa_setelah_lifetime(): void
    {
        config(['session.driver' => 'database', 'session.connection' => self::KONEKSI, 'session.lifetime' => 120]);
        $segarkanSesi = function () {
            $this->app['session']->forgetDrivers();
            $this->app->forgetInstance('session.store');
            Auth::forgetGuards();
        };
        $segarkanSesi();

        $admin = $this->admin();
        $respons = $this->masuk($admin->username)->assertRedirect(route('admin.dashboard'));
        $idSesi = $respons->getCookie(config('session.cookie'))->getValue();

        // Baris sesi tersimpan di MySQL dan tertaut ke akun.
        $this->assertSame($admin->id, (int) DB::table('sessions')->where('id', $idSesi)->value('user_id'));

        $segarkanSesi();
        $this->withCookie(config('session.cookie'), $idSesi)->get('/admin/dashboard')->assertOk();

        $this->travel(121)->minutes();
        $segarkanSesi();
        $this->withCookie(config('session.cookie'), $idSesi)->get('/admin/dashboard')->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    // ---------------------------------------------------------------- 9 CSRF

    public function test_csrf_wajib_untuk_masuk_dan_keluar(): void
    {
        // Laravel melewati CSRF saat unit test; aktifkan pemeriksaan aslinya.
        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $admin = $this->admin();

        $this->post('/login', ['username' => $admin->username, 'password' => self::SANDI])->assertStatus(419);
        $this->withHeader('Sec-Fetch-Site', 'cross-site')
            ->post('/login', ['username' => $admin->username, 'password' => self::SANDI])->assertStatus(419);
        $this->assertGuest('web');

        $this->withSession(['_token' => 'token-uji'])
            ->post('/login', ['_token' => 'token-uji', 'username' => $admin->username, 'password' => self::SANDI])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'web');

        $this->post('/logout', ['_token' => 'token-palsu'])->assertStatus(419);
        $this->assertAuthenticatedAs($admin, 'web');
    }

    // ---------------------------------------------------------------- 10–13 akses rute

    public function test_tamu_tidak_dapat_membuka_satu_pun_halaman_aplikasi(): void
    {
        foreach (NavigasiDemo::PERAN as $peran) {
            foreach (NavigasiDemo::halaman($peran) as $halaman) {
                $uri = strtr($halaman['uri'], ['{kodeAnggota}' => 'AG-001', '{kodeBuku}' => 'BK-001']);
                $this->get("/{$peran}/{$uri}")->assertRedirect(route('login'));
                $this->getJson("/{$peran}/{$uri}")->assertUnauthorized();
            }
        }
        $this->get('/admin/komponen')->assertRedirect(route('login'));
    }

    public function test_admin_hanya_halaman_admin(): void
    {
        $this->actingAs($this->admin());

        $this->get('/admin/dashboard')->assertOk();
        foreach (NavigasiDemo::halamanAnggota() as $halaman) {
            $this->get("/anggota/{$halaman['uri']}")->assertForbidden();
        }
    }

    public function test_anggota_hanya_halaman_anggota(): void
    {
        $this->actingAs($this->anggota());

        $this->get('/anggota/dashboard')->assertOk();
        foreach (NavigasiDemo::halamanAdmin() as $halaman) {
            $uri = strtr($halaman['uri'], ['{kodeAnggota}' => 'AG-001', '{kodeBuku}' => 'BK-001']);
            $this->get("/admin/{$uri}")->assertForbidden();
        }
        $this->get('/admin/komponen')->assertForbidden();
    }

    public function test_tujuan_awal_dipakai_hanya_bila_milik_peran_yang_sama(): void
    {
        $this->get('/admin/buku')->assertRedirect(route('login'));
        $this->masuk($this->admin()->username)->assertRedirect(url('/admin/buku'));

        $this->post('/logout');
        $this->requestBaru();

        $this->get('/admin/laporan')->assertRedirect(route('login'));
        $this->masuk($this->anggota()->username)->assertRedirect(route('anggota.dashboard'));
    }

    public function test_akun_yang_sudah_masuk_tidak_melihat_halaman_masuk(): void
    {
        $this->actingAs($this->admin())->get('/login')->assertRedirect(route('admin.dashboard'));
        $this->actingAs($this->anggota())->get('/login')->assertRedirect(route('anggota.dashboard'));
    }

    // ---------------------------------------------------------------- 14 throttling

    public function test_percobaan_masuk_dibatasi_per_username_dan_ip(): void
    {
        $admin = $this->admin();
        $lain = $this->admin();

        for ($i = 0; $i < MasukRequest::MAKS_PERCOBAAN; $i++) {
            $this->masuk($admin->username, "salah-{$i}")->assertSessionHasErrors(['username' => MasukRequest::PESAN_GAGAL]);
        }

        // Kata sandi BENAR pun ditolak selama terkunci.
        $this->masuk($admin->username)->assertSessionHasErrors('username');
        $this->assertStringStartsWith('Terlalu banyak percobaan masuk.', session('errors')->first('username'));
        $this->assertGuest('web');

        // Username lain tidak ikut terkunci.
        $this->masuk($lain->username)->assertRedirect(route('admin.dashboard'));
        $this->post('/logout');
        $this->requestBaru();

        $this->travel(MasukRequest::JENDELA_DETIK + 1)->seconds();
        $this->masuk($admin->username)->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'web');
    }

    // ---------------------------------------------------------------- perubahan status di tengah sesi

    public function test_anggota_dinonaktifkan_ditolak_pada_request_berikutnya(): void
    {
        $akun = $this->anggota();
        $this->masuk($akun->username);
        $this->get('/anggota/dashboard')->assertOk();

        Member::where('user_id', $akun->id)->first()->update(['status' => StatusAnggota::Nonaktif]);
        $this->requestBaru();

        $this->get('/anggota/history')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', AlasanTolakMasuk::AnggotaNonaktif->pesan());
        $this->assertGuest('web');

        $this->requestBaru();
        $this->get('/anggota/dashboard')->assertRedirect(route('login'));
    }

    public function test_admin_dinonaktifkan_ditolak_pada_request_berikutnya(): void
    {
        $admin = $this->admin();
        $this->masuk($admin->username);

        DB::table('users')->where('id', $admin->id)->update(['is_active' => 0]);
        $this->requestBaru();

        $this->get('/admin/dashboard')->assertRedirect(route('login'))
            ->assertSessionHas('status', AlasanTolakMasuk::AkunNonaktif->pesan());
        $this->assertGuest('web');
    }

    public function test_ganti_kata_sandi_mengakhiri_sesi_lama(): void
    {
        $admin = $this->admin();
        $this->masuk($admin->username);
        $this->get('/admin/dashboard')->assertOk();

        DB::table('users')->where('id', $admin->id)->update(['password' => Hash::make('SandiBaru-123')]);
        $this->requestBaru();

        $this->get('/admin/dashboard')->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    // ---------------------------------------------------------------- identitas & data sensitif

    public function test_identitas_anggota_dari_sesi_bukan_parameter_request(): void
    {
        $akun = $this->anggota();
        $lain = $this->anggota();
        $this->actingAs($akun);

        $this->get('/anggota/dashboard?kodeAnggota='.$lain->member->member_code.'&user_id='.$lain->id)
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.akun.kodeAnggota', $akun->member->member_code)
                ->where('demo.parameter', []));
    }

    public function test_prop_halaman_tidak_memuat_hash_atau_data_internal(): void
    {
        $akun = $this->anggota();
        $this->actingAs($akun);

        $props = null;
        $this->get('/anggota/dashboard')->assertInertia(function (Assert $page) use (&$props) {
            $props = $page->toArray()['props'];
        });

        $json = json_encode($props);
        $this->assertStringNotContainsString($akun->password, $json);
        $this->assertSame(['peran', 'identitas', 'nama', 'labelPeran', 'keterangan', 'kodeAnggota'], array_keys($props['auth']['akun']));
        foreach (['password', 'remember_token', 'user_id', 'contact'] as $kunci) {
            $this->assertStringNotContainsString("\"{$kunci}\"", $json);
        }
    }

    // ---------------------------------------------------------------- 17 tanpa jalan pintas mock

    public function test_tidak_ada_jalan_pintas_demo_atau_parameter_peran(): void
    {
        $this->get('/admin/dashboard?peran=admin')->assertRedirect(route('login'));
        $this->withHeader('X-Peran', 'admin')->get('/admin/dashboard')->assertRedirect(route('login'));

        // URL /demo lama hanya mengalihkan; halamannya tetap menuntut sesi.
        $this->get('/demo/admin/dashboard')->assertRedirect(url('/admin/dashboard'));
        $this->followingRedirects()->get('/demo/admin/laporan')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));

        // Halaman Masuk tidak membagikan daftar akun pratinjau.
        $this->get('/login')->assertInertia(fn (Assert $page) => $page
            ->where('auth.akun', null)
            ->missing('akunDemo'));

        // Akun pratinjau lama (sembarang kata sandi) tidak berlaku di server.
        $this->admin(['username' => 'admin']);
        $this->masuk('admin', 'apa-saja')->assertSessionHasErrors(['username' => MasukRequest::PESAN_GAGAL]);
        $this->assertGuest('web');
    }
}
