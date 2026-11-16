<?php

namespace Tests\Feature\Data;

use App\Enums\StatusAnggota;
use App\Models\Member;
use App\Models\User;
use App\Queries\DataPerpustakaan;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoPerpustakaanSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Database\MySqlTestCase;

/**
 * Phase 8B — halaman membaca data dari MySQL dan hanya data yang berhak.
 *
 * Data demo dimuat oleh DemoPerpustakaanSeeder DI DALAM transaksi database uji
 * (sim_perpustakaan_test, di-rollback setiap test). Database development tidak
 * disentuh.
 */
class DataHalamanTest extends MySqlTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['perpustakaan.seed.admin_password' => 'UjiAdmin-'.Str::random(16)]);
        $this->app->make(DemoPerpustakaanSeeder::class)->run();
    }

    private function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    private function akunAnggota(string $kode): User
    {
        return Member::where('member_code', $kode)->firstOrFail()->user;
    }

    /** @return array<string, mixed> props halaman */
    private function props(string $url): array
    {
        $props = [];
        $this->get($url)->assertOk()->assertInertia(function (Assert $page) use (&$props) {
            $props = $page->toArray()['props'];
        });

        return $props;
    }

    // ---------------------------------------------------------------- Admin

    public function test_admin_membaca_anggota_dari_mysql_tanpa_hash(): void
    {
        $this->actingAs($this->admin());
        $props = $this->props('/admin/anggota');

        $this->assertCount(10, $props['data']['anggota']);
        $ag001 = collect($props['data']['anggota'])->firstWhere('kodeAnggota', 'AG-001');
        $this->assertSame(['kodeAnggota', 'idPengguna', 'nama', 'kontak', 'status'], array_keys($ag001));
        $this->assertSame(Member::where('member_code', 'AG-001')->value('contact'), $ag001['kontak']);
        $this->assertSame('AG-001', $ag001['idPengguna'], 'Kunci akun = username, bukan id internal.');

        $this->assertCount(11, $props['data']['pengguna']);
        foreach ($props['data']['pengguna'] as $akun) {
            $this->assertNull($akun['passwordHash']);
        }

        $json = json_encode($props);
        foreach (DB::table('users')->pluck('password') as $hash) {
            $this->assertStringNotContainsString($hash, $json);
        }
        $this->assertStringNotContainsString('remember_token', $json);
        $this->assertStringNotContainsString('"user_id"', $json);
    }

    public function test_admin_membaca_buku_dan_eksemplar_dari_mysql(): void
    {
        $this->actingAs($this->admin());
        $props = $this->props('/admin/buku');

        $this->assertCount(10, $props['data']['buku']);
        $this->assertCount(18, $props['data']['eksemplar']);
        $this->assertSame(
            ['kodeBuku' => 'BK-001', 'judul' => 'Algoritma Dasar', 'penulis' => 'Arief Hidayat', 'penerbit' => 'Cakrawala Ilmu', 'tahunTerbit' => 2021, 'kategori' => 'Informatika'],
            collect($props['data']['buku'])->firstWhere('kodeBuku', 'BK-001'),
        );
        $this->assertSame(['kodeEksemplar' => 'EK-002', 'kodeBuku' => 'BK-002', 'status' => 'Dipesan'], collect($props['data']['eksemplar'])->firstWhere('kodeEksemplar', 'EK-002'));
    }

    public function test_admin_membaca_transaksi_dari_mysql_dengan_tanggal_wib(): void
    {
        $this->actingAs($this->admin());

        $pinjam = $this->props('/admin/pengembalian')['data'];
        $this->assertCount(10, $pinjam['peminjaman']);
        $this->assertCount(5, $pinjam['pengembalian']);
        $this->assertSame([
            'idPeminjaman' => 'PJ-006', 'kodeAnggota' => 'AG-002', 'kodeEksemplar' => 'EK-003',
            'tanggalPinjam' => '2026-11-02', 'tanggalJatuhTempo' => '2026-11-16', 'diprosesOleh' => 'admin', 'status' => 'Aktif',
        ], collect($pinjam['peminjaman'])->firstWhere('idPeminjaman', 'PJ-006'));
        $this->assertSame('2026-10-18', collect($pinjam['pengembalian'])->firstWhere('idPengembalian', 'KB-001')['tanggalKembali']);

        $booking = $this->props('/admin/booking/verifikasi')['data']['booking'];
        $this->assertCount(8, $booking);
        $bp004 = collect($booking)->firstWhere('idBooking', 'BP-004');
        $this->assertSame(['Direalisasikan', 'PJ-007'], [$bp004['status'], $bp004['idPeminjaman']]);
        $this->assertSame('Eksemplar sudah dipinjam anggota lain sebelum pengajuan diproses.', collect($booking)->firstWhere('idBooking', 'BP-002')['alasanPenolakan']);
    }

    public function test_hari_ini_dari_kalender_wib(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-13T22:30:00Z')); // 14 Nov 05:30 WIB
        $this->actingAs($this->admin());

        $this->get('/admin/dashboard')->assertInertia(fn (Assert $page) => $page->where('hariIni', '2026-11-14'));
    }

    // ---------------------------------------------------------------- Anggota

    public function test_anggota_hanya_membaca_booking_miliknya(): void
    {
        $akun = $this->akunAnggota('AG-002');
        $this->actingAs($akun);
        $data = $this->props('/anggota/booking/status')['data'];

        $harapan = DB::table('bookings')->join('members', 'members.id', '=', 'bookings.member_id')
            ->where('members.member_code', 'AG-002')->orderBy('booking_code')->pluck('booking_code')->all();
        $this->assertNotEmpty($harapan);
        $this->assertSame($harapan, array_column($data['booking'], 'idBooking'));
        $this->assertSame(['AG-002'], array_values(array_unique(array_column($data['booking'], 'kodeAnggota'))));
        foreach ($data['booking'] as $b) {
            $this->assertNull($b['diputuskanOleh'], 'Username Admin tidak dikirim ke Anggota.');
        }
        $this->assertSame(['AG-002'], array_column($data['anggota'], 'kodeAnggota'));
    }

    public function test_anggota_hanya_membaca_peminjaman_dan_pengembalian_miliknya(): void
    {
        $this->actingAs($this->akunAnggota('AG-001'));
        $data = $this->props('/anggota/history')['data'];

        $harapan = DB::table('loans')->join('members', 'members.id', '=', 'loans.member_id')
            ->where('members.member_code', 'AG-001')->orderBy('loan_code')->pluck('loan_code')->all();
        $this->assertSame($harapan, array_column($data['peminjaman'], 'idPeminjaman'));
        $this->assertSame(['AG-001'], array_values(array_unique(array_column($data['peminjaman'], 'kodeAnggota'))));
        $this->assertEmpty(array_diff(array_column($data['pengembalian'], 'idPeminjaman'), $harapan), 'Pengembalian hanya milik peminjaman sendiri.');
        $this->assertNotEmpty($data['pengembalian']);
    }

    public function test_anggota_tidak_dapat_membaca_data_anggota_lain(): void
    {
        $this->actingAs($this->akunAnggota('AG-001'));
        $lain = Member::where('member_code', '<>', 'AG-001')->get();

        foreach (['/anggota/dashboard', '/anggota/cari-buku', '/anggota/booking/ajukan', '/anggota/booking/status', '/anggota/history'] as $url) {
            // Parameter request mencoba menunjuk anggota lain — diabaikan.
            $props = $this->props($url.'?kodeAnggota=AG-002&member_id=2&user_id=3');
            $json = json_encode($props['data']);

            $this->assertArrayNotHasKey('pengguna', $props['data'], $url);
            foreach ($lain as $m) {
                $this->assertStringNotContainsString($m->contact, $json, "{$url}: kontak {$m->member_code}");
                $this->assertStringNotContainsString($m->name, $json, "{$url}: nama {$m->member_code}");
            }
            $this->assertSame('AG-001', $props['auth']['akun']['kodeAnggota']);
            foreach (['peminjaman', 'booking'] as $koleksi) {
                foreach ($props['data'][$koleksi] ?? [] as $baris) {
                    $this->assertSame('AG-001', $baris['kodeAnggota'], "{$url}: {$koleksi}");
                }
            }
        }

        // Katalog publik: semua buku & status eksemplar boleh dilihat.
        $katalog = $this->props('/anggota/cari-buku')['data'];
        $this->assertSame(['buku', 'eksemplar', 'kategori'], array_keys($katalog));
        $this->assertCount(18, $katalog['eksemplar']);
    }

    public function test_akses_langsung_url_admin_dan_laporan_ditolak_untuk_anggota(): void
    {
        $this->actingAs($this->akunAnggota('AG-001'));

        foreach (['/admin/anggota', '/admin/anggota/AG-002/ubah', '/admin/peminjaman', '/admin/laporan?jenis=anggota', '/admin/booking/verifikasi'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->getJson('/admin/laporan?jenis=anggota')->assertForbidden();
    }

    public function test_otorisasi_berlapis_gate_dan_query_service(): void
    {
        $anggota = $this->akunAnggota('AG-001');
        $admin = $this->admin();

        $this->assertTrue(Gate::forUser($admin)->allows('buka-halaman', 'admin'));
        $this->assertFalse(Gate::forUser($admin)->allows('buka-halaman', 'anggota'));
        $this->assertTrue(Gate::forUser($anggota)->allows('buka-halaman', 'anggota'));
        $this->assertFalse(Gate::forUser($anggota)->allows('buka-halaman', 'admin'));

        Member::where('member_code', 'AG-001')->first()->update(['status' => StatusAnggota::Nonaktif]);
        $this->assertFalse(Gate::forUser($anggota->fresh())->allows('buka-halaman', 'anggota'), 'Anggota Nonaktif tidak boleh membuka halaman.');

        $this->expectException(AuthorizationException::class);
        $this->app->make(DataPerpustakaan::class)->untukHalaman('admin', 'anggota.index', $anggota);
    }

    public function test_login_anggota_tetap_dari_database_lalu_melihat_datanya(): void
    {
        $akun = $this->akunAnggota('AG-002');
        $akun->forceFill(['password' => 'RahasiaUji-8B!'])->save(); // hanya di transaksi DB uji

        $this->post('/login', ['username' => 'ag-002', 'password' => 'RahasiaUji-8B!'])->assertRedirect(route('anggota.dashboard'));
        $data = $this->props('/anggota/dashboard')['data'];
        $this->assertSame(['AG-002'], array_column($data['anggota'], 'kodeAnggota'));

        $this->post('/logout');
        $this->post('/login', ['username' => 'AG-002', 'password' => 'salah'])->assertSessionHasErrors('username');
        $this->assertGuest('web');
    }

    // ---------------------------------------------------------------- Laporan & tanggal

    public function test_filter_laporan_memakai_batas_hari_wib_yang_dikonversi_ke_utc(): void
    {
        $admin = $this->admin();
        $anggota = Member::where('member_code', 'AG-001')->first();
        $buat = fn (string $wib) => $this->buatPeminjaman($anggota, $this->buatEksemplar(), $admin, 'Selesai', [
            'loaned_at' => CarbonImmutable::parse($wib, 'Asia/Jakarta'),
            'loan_code' => 'UJI-'.str_replace([' ', ':', '-'], '', $wib),
        ]);
        $buat('2026-12-02 00:00:00');   // = 01 Des 17:00 UTC → masuk
        $buat('2026-12-02 05:15:00');   // = 01 Des 22:15 UTC → masuk (DATE() UTC = 1 Des)
        $buat('2026-12-02 23:59:59');   // masuk
        $buat('2026-12-03 00:00:00');   // = 02 Des 17:00 UTC → tidak masuk
        $buat('2026-12-01 23:59:59');   // tidak masuk

        $this->actingAs($admin);
        $props = $this->props('/admin/laporan?jenis=peminjaman&awal=2026-12-02&akhir=2026-12-02');

        $this->assertSame(['jenis' => 'peminjaman', 'awal' => '2026-12-02', 'akhir' => '2026-12-02'], $props['filterLaporan']);
        $this->assertSame(['UJI-20261202000000', 'UJI-20261202051500', 'UJI-20261202235959'], array_column($props['data']['peminjaman'], 'idPeminjaman'));
        $this->assertSame(['2026-12-02'], array_values(array_unique(array_column($props['data']['peminjaman'], 'tanggalPinjam'))));
    }

    public function test_filter_laporan_pengembalian_dan_filter_tidak_valid(): void
    {
        $this->actingAs($this->admin());

        $props = $this->props('/admin/laporan?jenis=pengembalian&awal=2026-10-27&akhir=2026-10-28');
        $this->assertSame(['KB-002', 'KB-003'], array_column($props['data']['pengembalian'], 'idPengembalian'));
        $this->assertEqualsCanonicalizing(['PJ-002', 'PJ-004'], array_column($props['data']['peminjaman'], 'idPeminjaman'));

        // Laporan tanpa periode: daftar anggota saja.
        $this->assertSame(['anggota'], array_keys($this->props('/admin/laporan?jenis=anggota')['data']));

        // Tanpa filter / filter tidak valid → tidak ada data dimuat.
        foreach (['/admin/laporan', '/admin/laporan?jenis=rahasia', '/admin/laporan?jenis=peminjaman&awal=2026-11-30&akhir=2026-11-01', '/admin/laporan?jenis=peminjaman&awal=2026-02-30'] as $url) {
            $props = $this->props($url);
            $this->assertSame([], $props['data'], $url);
            $this->assertNull($props['filterLaporan'], $url);
        }
    }

    // ---------------------------------------------------------------- N+1

    public function test_jumlah_query_tidak_bertambah_seiring_jumlah_data(): void
    {
        $hitung = function (string $url): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get($url)->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $admin = $this->admin();
        $akun = $this->akunAnggota('AG-001');
        $urlAdmin = ['/admin/dashboard', '/admin/booking/verifikasi', '/admin/laporan?jenis=pengembalian'];

        // Ganti akun = sesi baru (auth.session menolak pergantian akun dalam satu sesi).
        $sebagai = function (User $u) {
            $this->flushSession();
            $this->actingAs($u);
        };

        $sebagai($admin);
        $awalAdmin = array_map($hitung, $urlAdmin);
        $sebagai($akun);
        $awalAnggota = $hitung('/anggota/history');

        // Tambah data: 6 peminjaman (3 selesai + pengembalian), 3 booking.
        $anggota = $akun->member;
        for ($i = 0; $i < 6; $i++) {
            $p = $this->buatPeminjaman($anggota, $this->buatEksemplar(), $admin, 'Selesai');
            DB::table('returns')->insert(['return_code' => 'UJI-KB-'.$i, 'loan_id' => $p->id, 'staff_id' => $admin->id, 'returned_at' => now()]);
        }
        for ($i = 0; $i < 3; $i++) {
            $this->buatBooking($anggota, $this->buatEksemplar(), 'Dibatalkan');
        }

        $sebagai($admin);
        $this->assertSame($awalAdmin, array_map($hitung, $urlAdmin), 'Query Admin harus konstan (eager loading).');
        $sebagai($akun);
        $this->assertSame($awalAnggota, $hitung('/anggota/history'), 'Query Anggota harus konstan.');
    }
}
