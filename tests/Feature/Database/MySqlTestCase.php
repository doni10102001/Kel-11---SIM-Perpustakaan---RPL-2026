<?php

namespace Tests\Feature\Database;

use App\Enums\Peran;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Booking;
use App\Models\Loan;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use Throwable;

/**
 * Dasar test database Phase 7A — memakai MySQL 8.4 SUNGGUHAN.
 *
 * Keamanan:
 * - Koneksi `mysql_testing` wajib menunjuk database berakhiran `_test` dan
 *   berbeda dari database development.
 * - TIDAK memakai RefreshDatabase/DatabaseMigrations (keduanya migrate:fresh).
 *   Migration dijalankan dengan `migrate` biasa (non-destruktif) sekali per
 *   proses, lalu setiap test dibungkus transaksi yang di-rollback.
 * - Bila MySQL uji tidak tersedia, test ditandai SKIPPED (bukan lulus).
 *
 * Catatan: test ini memverifikasi constraint skema. Perilaku konkurensi
 * (dua transaksi paralel) BELUM dibuktikan di sini — bagian service Phase 8+.
 */
abstract class MySqlTestCase extends TestCase
{
    protected const KONEKSI = 'mysql_testing';

    private static bool $sudahMigrasi = false;

    private bool $dalamTransaksi = false;

    protected function setUp(): void
    {
        parent::setUp();

        $namaUji = config('database.connections.'.self::KONEKSI.'.database');
        if (! is_string($namaUji) || ! str_ends_with($namaUji, '_test')) {
            $this->fail('Koneksi mysql_testing wajib menunjuk database berakhiran _test.');
        }
        if ($namaUji === config('database.connections.mysql.database')) {
            $this->fail('Database uji tidak boleh sama dengan database development.');
        }

        try {
            DB::connection(self::KONEKSI)->getPdo();
        } catch (Throwable $e) {
            $this->markTestSkipped("MySQL uji '{$namaUji}' belum tersedia: ".Str::limit($e->getMessage(), 160));
        }

        DB::setDefaultConnection(self::KONEKSI);

        if (! self::$sudahMigrasi) {
            $kode = Artisan::call('migrate', ['--database' => self::KONEKSI, '--force' => true]);
            $this->assertSame(0, $kode, 'Migration gagal: '.Artisan::output());
            self::$sudahMigrasi = true;
        }

        DB::connection(self::KONEKSI)->beginTransaction();
        $this->dalamTransaksi = true;
    }

    protected function tearDown(): void
    {
        if ($this->dalamTransaksi) {
            DB::connection(self::KONEKSI)->rollBack();
            $this->dalamTransaksi = false;
        }

        parent::tearDown();
    }

    /** Pastikan aksi ditolak MySQL dengan kode error tertentu (1062, 1451, 1452, 3819, ...). */
    protected function assertDitolakDb(int $kodeMySql, callable $aksi, string $pesan = ''): void
    {
        try {
            $aksi();
        } catch (QueryException $e) {
            $this->assertSame($kodeMySql, (int) ($e->errorInfo[1] ?? 0), $pesan ?: 'Kode error MySQL tidak sesuai: '.$e->getMessage());

            return;
        }

        $this->fail($pesan ?: "Aksi seharusnya ditolak MySQL dengan kode {$kodeMySql}.");
    }

    protected function buatAdmin(array $atribut = []): User
    {
        return User::factory()->admin()->create($atribut);
    }

    protected function buatAnggota(array $atribut = [], ?User $akun = null): Member
    {
        $anggota = Member::factory()->make($atribut);
        $anggota->user_id = $akun?->id;
        $anggota->save();

        return $anggota;
    }

    protected function buatAkunAnggota(array $atribut = []): User
    {
        return User::factory()->create(['role' => Peran::Anggota] + $atribut);
    }

    protected function buatEksemplar(?Book $buku = null, string $status = 'Tersedia'): BookCopy
    {
        return BookCopy::factory()
            ->for($buku ?? Book::factory()->create())
            ->create(['status' => $status]);
    }

    protected function buatBooking(Member $anggota, BookCopy $eksemplar, string $status = 'Diajukan', array $lain = []): Booking
    {
        $booking = new Booking;
        // $lain di KIRI: pada operator + PHP kunci kiri yang menang, sehingga override test berlaku.
        $booking->forceFill($lain + [
            'booking_code' => 'UJI-BP-'.Str::upper(Str::random(8)),
            'member_id' => $anggota->id,
            'copy_id' => $eksemplar->id,
            'status' => $status,
            'submitted_at' => now(),
        ])->save();

        return $booking;
    }

    protected function buatPeminjaman(Member $anggota, BookCopy $eksemplar, User $admin, string $status = 'Aktif', array $lain = []): Loan
    {
        $pinjam = new Loan;
        $pinjam->forceFill($lain + [
            'loan_code' => 'UJI-PJ-'.Str::upper(Str::random(8)),
            'member_id' => $anggota->id,
            'copy_id' => $eksemplar->id,
            'staff_id' => $admin->id,
            'loaned_at' => now(),
            'due_date' => '2026-11-21',
            'status' => $status,
        ])->save();

        return $pinjam;
    }
}
