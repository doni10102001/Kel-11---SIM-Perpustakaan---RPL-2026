<?php

namespace Tests\Feature\Anggota;

use App\Enums\AlasanTolakMasuk;
use App\Enums\StatusAnggota;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Member;
use App\Models\User;
use App\Services\LayananAnggota;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\Feature\Database\MySqlTestCase;

/**
 * Phase 9A — CRUD Anggota nyata (tambah, ubah profil, aktif/nonaktif) + akses
 * login opsional saat tambah + F-01 opsi A. Semua di sim_perpustakaan_test
 * (transaksi di-rollback); database development tidak disentuh.
 */
class AnggotaCrudTest extends MySqlTestCase
{
    private const SANDI_ADMIN = 'AdminUji-9A!';

    private const SANDI_ANGGOTA = 'AnggotaUji-9A!';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->buatAdmin(['password' => self::SANDI_ADMIN]);
    }

    private function profil(array $timpa = []): array
    {
        // $timpa di KIRI: pada operator + PHP kunci kiri yang menang.
        return $timpa + ['kodeAnggota' => 'AG-901', 'nama' => 'Anggota Uji Satu', 'kontak' => '0811-0000-0901', 'status' => 'Aktif'];
    }

    private function denganAkses(string $username = 'ag901', string $sandi = self::SANDI_ANGGOTA): array
    {
        return ['buatAkses' => true, 'username' => $username, 'password' => $sandi, 'password_confirmation' => $sandi];
    }

    private function tambah(array $isian)
    {
        return $this->actingAs($this->admin)->from('/admin/anggota/tambah')->post('/admin/anggota', $isian);
    }

    private function ubah(string $kode, array $isian)
    {
        return $this->actingAs($this->admin)->from("/admin/anggota/{$kode}/ubah")->put("/admin/anggota/{$kode}", $isian);
    }

    private function aksiAudit(): array
    {
        return AuditLog::query()->orderBy('id')->pluck('action')->all();
    }

    // ---------------------------------------------------------------- 1–3, 8, 9 tambah

    public function test_admin_menambah_anggota_tanpa_akun(): void
    {
        $this->tambah($this->profil())
            ->assertRedirect(route('admin.anggota.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Anggota AG-901 · Anggota Uji Satu berhasil ditambahkan.');

        $m = Member::cariKode('AG-901');
        $this->assertSame(['Anggota Uji Satu', '0811-0000-0901', StatusAnggota::Aktif, null], [$m->name, $m->contact, $m->status, $m->user_id]);
        $this->assertSame(['anggota.dibuat'], $this->aksiAudit());
    }

    public function test_admin_menambah_anggota_nonaktif_dengan_spasi_dirapikan(): void
    {
        $this->tambah($this->profil(['kodeAnggota' => '  AG-902 ', 'nama' => '  Nama Rapi  ', 'status' => 'Nonaktif']))->assertSessionHasNoErrors();

        $m = Member::cariKode('AG-902');
        $this->assertSame(['Nama Rapi', StatusAnggota::Nonaktif], [$m->name, $m->status]);
    }

    public function test_admin_menambah_anggota_sekaligus_akses_login_secara_atomik(): void
    {
        $this->tambah($this->profil() + $this->denganAkses('AG901.login'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'wajib mengganti kata sandi') && ! str_contains($s, self::SANDI_ANGGOTA));

        $m = Member::cariKode('AG-901');
        $akun = $m->user;
        $this->assertNotNull($akun);
        $this->assertSame(['AG901.login', 'anggota', null, true, true], [$akun->username, $akun->role->value, $akun->name, $akun->is_active, $akun->must_change_password]);
        $this->assertTrue(Hash::isHashed($akun->password));
        $this->assertTrue(Hash::check(self::SANDI_ANGGOTA, $akun->password));
        $this->assertNotSame(self::SANDI_ANGGOTA, DB::table('users')->where('id', $akun->id)->value('password'));
        $this->assertSame(['anggota.dibuat', 'anggota.akses_login.dibuat'], $this->aksiAudit());

        // Login pertama → wajib ganti kata sandi (Phase 8D).
        $this->post('/logout');
        Auth::forgetGuards();
        $this->from('/login')->post('/login', ['username' => 'ag901.login', 'password' => self::SANDI_ANGGOTA])->assertRedirect(route('anggota.kata-sandi'));
    }

    // ---------------------------------------------------------------- 4–7, 10 validasi & keunikan

    public function test_username_harus_unik(): void
    {
        $this->buatAkunAnggota(['username' => 'sudah.ada']);

        $this->tambah($this->profil() + $this->denganAkses('SUDAH.ADA'))
            ->assertSessionHasErrors(['username' => 'Username sudah digunakan. Gunakan username lain.']);
        $this->assertNull(Member::cariKode('AG-901'), 'Anggota tidak dibuat bila akses login tidak valid.');

        $this->tambah($this->profil() + $this->denganAkses($this->admin->username))->assertSessionHasErrors('username');
    }

    public function test_kode_anggota_harus_unik_dan_kirim_ganda_tidak_menggandakan(): void
    {
        $this->tambah($this->profil())->assertSessionHasNoErrors();
        $this->tambah($this->profil(['nama' => 'Kirim Kedua']))->assertSessionHasErrors(['kodeAnggota' => 'Kode Anggota sudah digunakan anggota lain.']);
        $this->tambah($this->profil(['kodeAnggota' => 'ag-901']))->assertSessionHasErrors('kodeAnggota');

        $this->assertSame(1, Member::where('member_code', 'AG-901')->count());
        $this->assertSame('Anggota Uji Satu', Member::cariKode('AG-901')->name);
        $this->assertSame(['anggota.dibuat'], $this->aksiAudit());
    }

    public function test_balapan_kode_ganda_di_tingkat_database_menjadi_galat_validasi(): void
    {
        $layanan = $this->app->make(LayananAnggota::class);
        $layanan->tambah($this->profil(), null, $this->admin);

        // Melewati Form Request (mensimulasikan permintaan bersamaan yang lolos validasi): UNIQUE DB → galat validasi.
        try {
            $layanan->tambah($this->profil(['nama' => 'Balapan']), null, $this->admin);
            $this->fail('Seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame([LayananAnggota::PESAN_KODE_DIPAKAI], $e->errors()['kodeAnggota']);
        }
        $this->assertSame(1, Member::where('member_code', 'AG-901')->count());
    }

    public function test_data_tidak_valid_ditolak(): void
    {
        $this->tambah([])->assertSessionHasErrors(['kodeAnggota', 'nama', 'kontak', 'status']);
        $this->tambah($this->profil(['kodeAnggota' => 'AG 901']))->assertSessionHasErrors('kodeAnggota');
        $this->tambah($this->profil(['kodeAnggota' => 'AG/901']))->assertSessionHasErrors('kodeAnggota');
        $this->tambah($this->profil(['kodeAnggota' => str_repeat('A', 21)]))->assertSessionHasErrors(['kodeAnggota' => 'Kode Anggota maksimal 20 karakter.']);
        $this->tambah($this->profil(['nama' => str_repeat('n', 101)]))->assertSessionHasErrors(['nama' => 'Nama maksimal 100 karakter.']);
        $this->tambah($this->profil(['kontak' => str_repeat('1', 51)]))->assertSessionHasErrors(['kontak' => 'Kontak maksimal 50 karakter.']);
        $this->tambah($this->profil(['status' => 'aktif']))->assertSessionHasErrors(['status' => 'Pilih Status Anggota.']);
        $this->tambah($this->profil(['status' => 'Diblokir']))->assertSessionHasErrors('status');

        $this->assertSame(0, Member::count());
        $this->assertSame([], $this->aksiAudit());
    }

    public function test_konfirmasi_kata_sandi_berbeda_ditolak_dan_tidak_ada_yang_tersimpan(): void
    {
        $this->tambah($this->profil() + ['buatAkses' => true, 'username' => 'ag901', 'password' => self::SANDI_ANGGOTA, 'password_confirmation' => 'beda-123456'])
            ->assertSessionHasErrors(['password' => 'Konfirmasi Kata Sandi tidak sama dengan Kata Sandi.']);
        $this->tambah($this->profil() + ['buatAkses' => true, 'username' => '', 'password' => '', 'password_confirmation' => ''])
            ->assertSessionHasErrors(['username', 'password', 'password_confirmation']);

        $this->assertNull(Member::cariKode('AG-901'));
        $this->assertFalse(User::where('username', 'ag901')->exists());
        $this->assertNull(session()->getOldInput('password'));
        $this->assertSame('Anggota Uji Satu', session()->getOldInput('nama'), 'Isian non-sensitif dipertahankan.');
    }

    // ---------------------------------------------------------------- 11, 27 rollback

    public function test_rollback_penuh_bila_pembuatan_akun_gagal(): void
    {
        User::creating(fn () => throw new RuntimeException('Simulasi kegagalan pembuatan akun.'));

        try {
            $this->app->make(LayananAnggota::class)->tambah($this->profil(), ['username' => 'gagal.akun', 'password' => self::SANDI_ANGGOTA], $this->admin);
            $this->fail('Seharusnya gagal.');
        } catch (RuntimeException) {
        } finally {
            User::flushEventListeners();
        }

        $this->assertNull(Member::cariKode('AG-901'), 'Anggota ikut di-rollback.');
        $this->assertFalse(User::where('username', 'gagal.akun')->exists());
        $this->assertSame([], $this->aksiAudit(), 'Tidak ada audit "berhasil" untuk transaksi gagal.');
    }

    // ---------------------------------------------------------------- 12–14 ubah profil

    public function test_admin_mengubah_profil_tanpa_menyentuh_akun_kode_dan_riwayat(): void
    {
        $akun = $this->buatAkunAnggota(['password' => self::SANDI_ANGGOTA, 'username' => 'tetap.sama']);
        $m = $this->buatAnggota(['member_code' => 'AG-903', 'name' => 'Nama Lama', 'contact' => '0800'], $akun);
        $pinjam = $this->buatPeminjaman($m, $this->buatEksemplar(status: 'Dipinjam'), $this->admin);
        $hash = $akun->password;
        $idAnggota = $m->id;

        $this->ubah('AG-903', ['nama' => 'Nama Baru', 'kontak' => '0899-1234', 'status' => 'Aktif', 'kodeAnggota' => 'AG-999', 'user_id' => 999, 'password' => 'Disusupkan-1'])
            ->assertRedirect(route('admin.anggota.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Perubahan data anggota AG-903 · Nama Baru disimpan.');

        $m = Member::cariKode('AG-903');
        $this->assertSame([$idAnggota, 'Nama Baru', '0899-1234', $akun->id], [$m->id, $m->name, $m->contact, $m->user_id]);
        $this->assertNull(Member::cariKode('AG-999'), 'Kode Anggota tidak dapat diubah.');
        $akun->refresh();
        $this->assertSame([$hash, 'tetap.sama', null], [$akun->password, $akun->username, $akun->name], 'Hash, username, dan users.name tetap.');
        $this->assertSame($m->id, $pinjam->fresh()->member_id, 'Riwayat tetap tertaut.');

        $audit = AuditLog::where('action', 'anggota.diubah')->sole();
        // Kolom JSON MySQL tidak menjamin urutan kunci objek.
        $this->assertEqualsCanonicalizing(['kode_anggota' => 'AG-903', 'kolom' => ['name', 'contact']], $audit->metadata);
        $this->assertStringNotContainsString('0899-1234', json_encode($audit->getAttributes()), 'Nilai kontak tidak dicatat.');
    }

    public function test_ubah_tanpa_perubahan_tidak_membuat_audit_dan_validasi_ubah(): void
    {
        $this->buatAnggota(['member_code' => 'AG-904', 'name' => 'Sama', 'contact' => '0800', 'status' => StatusAnggota::Aktif]);

        $this->ubah('AG-904', ['nama' => 'Sama', 'kontak' => '0800', 'status' => 'Aktif'])->assertSessionHasNoErrors();
        $this->assertSame([], $this->aksiAudit());

        $this->ubah('AG-904', ['nama' => '', 'kontak' => '', 'status' => 'X'])->assertSessionHasErrors(['nama', 'kontak', 'status']);
        $this->ubah('TIDAK-ADA', ['nama' => 'A', 'kontak' => 'B', 'status' => 'Aktif'])->assertNotFound();
    }

    // ---------------------------------------------------------------- 15–18 aktif/nonaktif

    public function test_menonaktifkan_menolak_login_dan_sesi_lama_lalu_mengaktifkan_kembali(): void
    {
        $akun = $this->buatAkunAnggota(['password' => self::SANDI_ANGGOTA, 'username' => 'status.uji']);
        $m = $this->buatAnggota(['member_code' => 'AG-905', 'status' => StatusAnggota::Aktif], $akun);
        $hash = $akun->password;

        // Sesi Anggota yang sedang berjalan.
        $this->from('/login')->post('/login', ['username' => 'status.uji', 'password' => self::SANDI_ANGGOTA])->assertRedirect(route('anggota.dashboard'));
        $this->get('/anggota/dashboard')->assertOk();

        // Admin menonaktifkan (lewat layanan; sesi HTTP test dipakai Anggota).
        $this->app->make(LayananAnggota::class)->ubah('AG-905', ['nama' => $m->name, 'kontak' => $m->contact, 'status' => 'Nonaktif'], $this->admin);
        Auth::forgetGuards();

        $this->get('/anggota/history')->assertRedirect(route('login'))->assertSessionHas('status', AlasanTolakMasuk::AnggotaNonaktif->pesan());
        $this->assertGuest('web');
        $this->from('/login')->post('/login', ['username' => 'status.uji', 'password' => self::SANDI_ANGGOTA])
            ->assertSessionHasErrors(['username' => AlasanTolakMasuk::AnggotaNonaktif->pesan()]);

        $akun->refresh();
        $this->assertSame($hash, $akun->password, 'Akun & kata sandi tidak dihapus.');
        $this->assertSame(['anggota.dinonaktifkan'], $this->aksiAudit());

        // Aktifkan kembali → kredensial lama berlaku lagi.
        $this->app->make(LayananAnggota::class)->ubah('AG-905', ['nama' => $m->name, 'kontak' => $m->contact, 'status' => 'Aktif'], $this->admin);
        $this->from('/login')->post('/login', ['username' => 'status.uji', 'password' => self::SANDI_ANGGOTA])->assertRedirect(route('anggota.dashboard'));
        $this->assertSame(['anggota.dinonaktifkan', 'anggota.diaktifkan'], $this->aksiAudit());
    }

    public function test_admin_menonaktifkan_lewat_http_dan_baris_sesi_dihapus(): void
    {
        config(['session.driver' => 'database', 'session.connection' => self::KONEKSI]);
        $akun = $this->buatAkunAnggota();
        $this->buatAnggota(['member_code' => 'AG-906', 'status' => StatusAnggota::Aktif], $akun);
        DB::table('sessions')->insert(['id' => 'sesi-ag906', 'user_id' => $akun->id, 'payload' => base64_encode('a:0:{}'), 'last_activity' => time()]);

        $this->ubah('AG-906', ['nama' => 'X', 'kontak' => 'Y', 'status' => 'Nonaktif'])->assertSessionHasNoErrors();

        $this->assertSame(StatusAnggota::Nonaktif, Member::cariKode('AG-906')->status);
        $this->assertFalse(DB::table('sessions')->where('id', 'sesi-ag906')->exists());
    }

    public function test_status_nonaktif_mempertahankan_kewajiban_ganti_kata_sandi(): void
    {
        $this->tambah($this->profil() + $this->denganAkses('wajib.tetap'))->assertSessionHasNoErrors();
        $this->ubah('AG-901', ['nama' => 'Anggota Uji Satu', 'kontak' => '0811-0000-0901', 'status' => 'Nonaktif']);
        $this->ubah('AG-901', ['nama' => 'Anggota Uji Satu', 'kontak' => '0811-0000-0901', 'status' => 'Aktif']);

        $this->assertTrue(User::where('username', 'wajib.tetap')->sole()->must_change_password);
    }

    // ---------------------------------------------------------------- 19–21 F-01

    public function test_f01_penonaktifan_ditolak_bila_ada_booking_disetujui(): void
    {
        $m = $this->buatAnggota(['member_code' => 'AG-907', 'name' => 'Punya Booking', 'contact' => '0800', 'status' => StatusAnggota::Aktif]);
        $booking = $this->buatBooking($m, $this->buatEksemplar(status: 'Dipesan'), 'Disetujui', ['staff_id' => $this->admin->id, 'decided_at' => now()]);
        $sebelum = $booking->fresh()->getAttributes();

        $this->ubah('AG-907', ['nama' => 'Nama Diubah Juga', 'kontak' => '0800', 'status' => 'Nonaktif'])
            ->assertRedirect('/admin/anggota/AG-907/ubah')
            ->assertSessionHasErrors(['status' => LayananAnggota::PESAN_F01]);

        $m->refresh();
        $this->assertSame([StatusAnggota::Aktif, 'Punya Booking'], [$m->status, $m->name], 'Status & profil tidak berubah (transaksi dibatalkan).');
        $this->assertSame($sebelum, $booking->fresh()->getAttributes(), 'Booking tidak dibatalkan atau diubah.');
        $this->assertSame('Dipesan', $booking->copy->fresh()->status->value, 'Reservasi eksemplar tidak dilepas.');
        $this->assertSame([], $this->aksiAudit(), 'Tidak ada audit untuk transaksi gagal.');
    }

    public function test_f01_hanya_booking_disetujui_yang_memblokir(): void
    {
        $m = $this->buatAnggota(['member_code' => 'AG-908', 'status' => StatusAnggota::Aktif]);
        foreach (['Diajukan', 'Ditolak', 'Dibatalkan'] as $status) {
            $this->buatBooking($m, $this->buatEksemplar(), $status);
        }
        // Peminjaman Aktif tidak memblokir (tidak ada aturan tambahan yang disetujui).
        $this->buatPeminjaman($m, $this->buatEksemplar(status: 'Dipinjam'), $this->admin);

        $this->ubah('AG-908', ['nama' => 'A', 'kontak' => 'B', 'status' => 'Nonaktif'])->assertSessionHasNoErrors();

        $this->assertSame(StatusAnggota::Nonaktif, Member::cariKode('AG-908')->status);
        $this->assertSame(['Diajukan', 'Ditolak', 'Dibatalkan'], Booking::where('member_id', $m->id)->orderBy('id')->get()->map(fn ($b) => $b->status->value)->all(), 'Booking tidak dibatalkan diam-diam.');
    }

    public function test_f01_mengaktifkan_kembali_selalu_boleh_dan_kunci_member_diambil_lebih_dulu(): void
    {
        $m = $this->buatAnggota(['member_code' => 'AG-909', 'status' => StatusAnggota::Nonaktif]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->app->make(LayananAnggota::class)->ubah('AG-909', ['nama' => $m->name, 'kontak' => $m->contact, 'status' => 'Aktif'], $this->admin);
        $q = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $this->assertSame(StatusAnggota::Aktif, $m->fresh()->status);
        $this->assertMatchesRegularExpression('/^select .* from `members` .* for update$/i', $q[0], 'Baris members dikunci (FOR UPDATE) sebagai kueri pertama.');

        // Penonaktifan: kunci member SEBELUM membaca booking (urutan kunci member → booking).
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->app->make(LayananAnggota::class)->ubah('AG-909', ['nama' => $m->name, 'kontak' => $m->contact, 'status' => 'Nonaktif'], $this->admin);
        $q = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();
        $posMember = collect($q)->search(fn ($s) => preg_match('/from `members` .* for update/i', $s));
        $posBooking = collect($q)->search(fn ($s) => str_contains($s, 'from `bookings`'));
        $this->assertSame(0, $posMember);
        $this->assertGreaterThan($posMember, $posBooking);
    }

    // ---------------------------------------------------------------- 22–25 otorisasi

    public function test_anggota_dan_tamu_tidak_dapat_menulis_data_anggota(): void
    {
        $m = $this->buatAnggota(['member_code' => 'AG-910', 'name' => 'Tetap']);
        $anggota = $this->buatAnggota([], $this->buatAkunAnggota());

        $this->post('/admin/anggota', $this->profil())->assertRedirect(route('login'));
        $this->putJson('/admin/anggota/AG-910', ['nama' => 'X', 'kontak' => 'Y', 'status' => 'Nonaktif'])->assertUnauthorized();

        $this->actingAs($anggota->user);
        $this->post('/admin/anggota', $this->profil())->assertForbidden();
        $this->put('/admin/anggota/AG-910', ['nama' => 'X', 'kontak' => 'Y', 'status' => 'Nonaktif'])->assertForbidden();

        $this->assertNull(Member::cariKode('AG-901'));
        $this->assertSame('Tetap', $m->fresh()->name);
    }

    public function test_csrf_wajib_untuk_tulis_anggota(): void
    {
        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        $this->actingAs($this->admin)->post('/admin/anggota', $this->profil())->assertStatus(419);
        $this->assertNull(Member::cariKode('AG-901'));
    }

    public function test_klien_tidak_dapat_menyuntikkan_field_istimewa(): void
    {
        $lain = $this->buatAkunAnggota(['username' => 'akun.orang.lain']);

        $this->tambah($this->profil() + $this->denganAkses('aman.saja') + [
            'role' => 'admin', 'is_active' => 0, 'name' => 'Disusupkan', 'user_id' => $lain->id, 'must_change_password' => 0, 'member_code' => 'HACK',
        ])->assertSessionHasNoErrors();

        $m = Member::cariKode('AG-901');
        $akun = $m->user;
        $this->assertSame(['aman.saja', 'anggota', null, true, true], [$akun->username, $akun->role->value, $akun->name, $akun->is_active, $akun->must_change_password]);
        $this->assertNotSame($lain->id, $m->user_id, 'Akun orang lain tidak dapat ditautkan.');
        $this->assertNull(Member::cariKode('HACK'));

        // Tanpa buatAkses: field akun diabaikan sepenuhnya.
        $this->tambah($this->profil(['kodeAnggota' => 'AG-911']) + ['user_id' => $lain->id, 'username' => 'x'])->assertSessionHasNoErrors();
        $this->assertNull(Member::cariKode('AG-911')->user_id);
    }

    // ---------------------------------------------------------------- 17 privasi, 28–29 integrasi

    public function test_halaman_baca_menampilkan_anggota_baru_dan_tanpa_rahasia(): void
    {
        $log = [];
        Event::listen(MessageLogged::class, function ($e) use (&$log) {
            $log[] = $e->message.json_encode($e->context);
        });
        $this->tambah($this->profil() + $this->denganAkses('tampil.baru'))->assertSessionHasNoErrors();

        $props = [];
        $this->get('/admin/anggota')->assertOk()->assertInertia(function (Assert $page) use (&$props) {
            $props = $page->toArray()['props'];
        });
        $baris = collect($props['data']['anggota'])->firstWhere('kodeAnggota', 'AG-901');
        $this->assertSame(['kodeAnggota' => 'AG-901', 'idPengguna' => 'tampil.baru', 'nama' => 'Anggota Uji Satu', 'kontak' => '0811-0000-0901', 'status' => 'Aktif'], $baris);
        $this->assertSame('Anggota AG-901 · Anggota Uji Satu berhasil ditambahkan. Akses login dibuat; anggota wajib mengganti kata sandi saat pertama masuk.', $props['flash']['status']);

        $this->get('/admin/anggota/AG-901/ubah')->assertOk();
        $json = json_encode($props).implode('', $log).AuditLog::query()->get()->toJson();
        foreach ([self::SANDI_ANGGOTA, User::where('username', 'tampil.baru')->value('password')] as $rahasia) {
            $this->assertStringNotContainsString($rahasia, $json);
        }
    }

    public function test_alur_kredensial_lama_tetap_berfungsi_untuk_anggota_baru(): void
    {
        $this->tambah($this->profil())->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post('/admin/anggota/AG-901/akses-login', ['username' => 'nanti.dibuat', 'password' => self::SANDI_ANGGOTA, 'password_confirmation' => self::SANDI_ANGGOTA])
            ->assertSessionHasNoErrors();
        $this->put('/admin/anggota/AG-901/kata-sandi', ['password' => 'SandiReset-9A', 'password_confirmation' => 'SandiReset-9A'])->assertSessionHasNoErrors();

        $akun = Member::cariKode('AG-901')->user;
        $this->assertTrue(Hash::check('SandiReset-9A', $akun->password));
        $this->assertTrue($akun->must_change_password);
        $this->assertSame(['anggota.dibuat', 'anggota.akses_login.dibuat', 'anggota.kata_sandi.direset'], $this->aksiAudit());
    }
}
