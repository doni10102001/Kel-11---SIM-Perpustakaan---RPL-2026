<?php

namespace Tests\Feature\Database;

use App\Enums\StatusAnggota;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Booking;
use App\Models\BookReturn;
use App\Models\Loan;
use App\Models\Member;
use App\Models\User;
use App\Support\PemeriksaInvarianData;
use App\Support\WaktuPerpustakaan;
use Database\Seeders\DemoPerpustakaanSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Phase 7A — seeder development: data valid, relasi sesuai mock, kata sandi
 * aman, dan dapat dijalankan berulang tanpa duplikasi.
 *
 * Berjalan di dalam transaksi test yang di-rollback; database development
 * tidak tersentuh.
 */
class SeederDemoTest extends MySqlTestCase
{
    private string $sandiAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandiAdmin = 'UjiAdmin-'.Str::random(16);
        config(['perpustakaan.seed.admin_password' => $this->sandiAdmin]);

        $this->assertSame(0, DB::table('members')->count(), 'Database uji harus kosong sebelum test seeder.');
    }

    private function jalankanSeeder(): void
    {
        // Dipanggil langsung (bukan via `db:seed`) agar penolakan seeder muncul sebagai exception.
        $this->app->make(DemoPerpustakaanSeeder::class)->run();
    }

    /** @return array<string, int> */
    private function hitungBaris(): array
    {
        return collect(['users', 'members', 'books', 'book_copies', 'bookings', 'loans', 'returns', 'audit_logs'])
            ->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()])->all();
    }

    public function test_seed_menghasilkan_data_mock_yang_valid_dan_konsisten(): void
    {
        $this->jalankanSeeder();

        $this->assertSame([
            'users' => 11, 'members' => 10, 'books' => 10, 'book_copies' => 18,
            'bookings' => 8, 'loans' => 10, 'returns' => 5, 'audit_logs' => 0,
        ], $this->hitungBaris());

        $this->assertSame([], (new PemeriksaInvarianData)->periksa());
    }

    public function test_relasi_demo_utama_terjaga(): void
    {
        $this->jalankanSeeder();

        $admin = User::where('username', 'admin')->first();
        $this->assertTrue($admin->isAdmin());
        $this->assertSame('Rina Wulandari', $admin->name);
        $this->assertSame('Rina Wulandari', $admin->nama_tampilan);
        $this->assertNull($admin->member);

        $nadia = Member::cariKode('AG-001');
        $this->assertSame('Nadia Rahmawati', $nadia->name);
        $this->assertSame('AG-001', $nadia->user->username);
        $this->assertNull($nadia->user->name, 'D-01: nama akun Anggota hanya di members.name.');
        $this->assertSame('Nadia Rahmawati', $nadia->user->nama_tampilan);
        $this->assertSame(0, DB::table('users')->where('role', 'anggota')->whereNotNull('name')->count());

        $this->assertSame(StatusAnggota::Nonaktif, Member::cariKode('AG-007')->status);
        $this->assertSame(['EK-001', 'EK-003', 'EK-004'], Book::cariKode('BK-001')->copies()->orderBy('copy_code')->pluck('copy_code')->all());

        $bp004 = Booking::cariKode('BP-004');
        $this->assertSame('PJ-007', $bp004->loan->loan_code);
        $this->assertSame('BP-004', Loan::cariKode('PJ-007')->booking->booking_code);
        $this->assertSame(1, Loan::whereNotNull('booking_id')->count());

        $pj006 = Loan::cariKode('PJ-006');
        $this->assertSame('2026-11-16', DB::table('loans')->where('id', $pj006->id)->value('due_date'));
        // D-02: tanggal mock 2026-11-02 → 12:00 WIB = 05:00 UTC; tanggal kalender WIB tetap 2 November.
        $this->assertSame('2026-11-02 05:00:00.000000', DB::table('loans')->where('id', $pj006->id)->value('loaned_at'));
        $this->assertSame('2026-11-02', WaktuPerpustakaan::tanggalLokal($pj006->loaned_at));
        $this->assertStringEndsWith('Z', $pj006->toArray()['loaned_at']);
        $this->assertTrue($pj006->staff->is($admin));

        $this->assertSame('PJ-001', BookReturn::cariKode('KB-001')->loan->loan_code);
        $this->assertSame('Dipesan', BookCopy::cariKode('EK-002')->status->value);
        $this->assertSame('Eksemplar sudah dipinjam anggota lain sebelum pengajuan diproses.', Booking::cariKode('BP-002')->rejection_reason);
    }

    public function test_kata_sandi_seed_hanya_hash_dan_akun_anggota_tidak_dapat_ditebak(): void
    {
        $this->jalankanSeeder();

        $hashAdmin = DB::table('users')->where('username', 'admin')->value('password');
        $this->assertTrue(Hash::check($this->sandiAdmin, $hashAdmin));
        $this->assertStringNotContainsString($this->sandiAdmin, $hashAdmin);

        $hashAnggota = DB::table('users')->where('role', 'anggota')->pluck('password');
        $this->assertCount(10, $hashAnggota);
        $this->assertCount(10, $hashAnggota->unique(), 'Setiap akun Anggota memakai hash acak berbeda.');
        foreach ($hashAnggota as $hash) {
            $this->assertTrue(Hash::isHashed($hash));
            $this->assertFalse(Hash::check($this->sandiAdmin, $hash), 'D-08: tidak ada kata sandi bersama.');
            $this->assertFalse(Hash::check('AG-001', $hash));
        }
    }

    public function test_seed_dapat_dijalankan_berulang_tanpa_duplikasi_atau_menimpa(): void
    {
        $this->jalankanSeeder();
        $sebelum = $this->hitungBaris();
        $hashAdmin = DB::table('users')->where('username', 'admin')->value('password');
        $diubah = DB::table('loans')->max('updated_at');

        $this->jalankanSeeder();

        $this->assertSame($sebelum, $this->hitungBaris());
        $this->assertSame($hashAdmin, DB::table('users')->where('username', 'admin')->value('password'));
        $this->assertSame($diubah, DB::table('loans')->max('updated_at'));
        $this->assertSame([], (new PemeriksaInvarianData)->periksa());
    }

    public function test_seed_ditolak_tanpa_kata_sandi_admin_dan_tidak_meninggalkan_data(): void
    {
        config(['perpustakaan.seed.admin_password' => null]);

        try {
            $this->jalankanSeeder();
            $this->fail('Seeder seharusnya menolak berjalan tanpa SEED_ADMIN_PASSWORD.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('SEED_ADMIN_PASSWORD', $e->getMessage());
        }

        $this->assertSame(0, DB::table('users')->count());
    }

    public function test_seed_ditolak_di_luar_lingkungan_local_atau_testing(): void
    {
        $asli = app()->environment();
        app()->instance('env', 'production');

        try {
            $this->jalankanSeeder();
            $this->fail('Seeder seharusnya menolak lingkungan production.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('local/testing', $e->getMessage());
        } finally {
            app()->instance('env', $asli);
        }

        $this->assertSame(0, DB::table('users')->count());
    }

    public function test_seed_dibatalkan_penuh_bila_data_yang_ada_tidak_konsisten(): void
    {
        // Eksemplar EK-001 sudah ada dengan status yang bertentangan dengan transaksi mock.
        $buku = Book::factory()->create(['book_code' => 'BK-001']);
        $e = BookCopy::factory()->for($buku)->make(['copy_code' => 'EK-001']);
        $e->forceFill(['status' => 'Dipesan'])->save();

        try {
            $this->jalankanSeeder();
            $this->fail('Seeder seharusnya dibatalkan karena data tidak konsisten.');
        } catch (RuntimeException $ex) {
            $this->assertStringContainsString('EK-001', $ex->getMessage());
        }

        $this->assertSame(0, DB::table('users')->count(), 'Tidak boleh ada data parsial setelah rollback.');
        $this->assertSame(1, DB::table('book_copies')->count());
    }
}
