<?php

namespace Tests\Unit;

use App\Enums\Peran;
use App\Models\Loan;
use App\Models\Member;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoPerpustakaanSeeder;
use Illuminate\Support\Facades\Password;
use InvalidArgumentException;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

/**
 * Phase 7A — perilaku model, cast, factory, dan seeder yang TIDAK memerlukan
 * database (tanpa migrasi, tanpa query). D-01, D-02, D-08.
 */
class ModelPhase7ATest extends TestCase
{
    // --- D-02: kolom tanggal-saja (loans.due_date) -------------------------

    public function test_due_date_string_disimpan_apa_adanya(): void
    {
        $pinjam = new Loan;
        $pinjam->due_date = '2026-11-16';

        $this->assertSame('2026-11-16', $pinjam->getAttributes()['due_date']);
        $this->assertSame('2026-11-16', $pinjam->toArray()['due_date']);
        $this->assertSame('2026-11-16 00:00:00 Asia/Jakarta', $pinjam->due_date->format('Y-m-d H:i:s e'));
    }

    public function test_due_date_dari_instant_memakai_kalender_wib(): void
    {
        $pinjam = new Loan;

        $pinjam->due_date = CarbonImmutable::parse('2026-11-16T17:30:00Z'); // 17 Nov 00:30 WIB
        $this->assertSame('2026-11-17', $pinjam->getAttributes()['due_date']);

        $pinjam->due_date = CarbonImmutable::parse('2026-11-16 23:30', 'Asia/Jakarta');
        $this->assertSame('2026-11-16', $pinjam->getAttributes()['due_date']);
    }

    public function test_due_date_baca_tulis_ulang_tidak_bergeser(): void
    {
        $pinjam = new Loan;
        $pinjam->due_date = '2026-12-31';

        for ($i = 0; $i < 3; $i++) {
            $pinjam->due_date = $pinjam->due_date;
        }

        $this->assertSame('2026-12-31', $pinjam->getAttributes()['due_date']);
    }

    public function test_due_date_tidak_valid_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $pinjam = new Loan;
        $pinjam->due_date = '2026-02-30';
    }

    // --- D-02: timestamp kejadian disimpan UTC ------------------------------

    public function test_timestamp_berzona_wib_disimpan_sebagai_utc(): void
    {
        $pinjam = new Loan;
        $pinjam->loaned_at = CarbonImmutable::parse('2026-11-02 05:15:00.123456', 'Asia/Jakarta');

        $this->assertSame('2026-11-01 22:15:00.123456', $pinjam->getAttributes()['loaned_at']);
        $this->assertSame('2026-11-01T22:15:00.123456Z', $pinjam->toArray()['loaned_at']);

        $akun = new User;
        $akun->created_at = CarbonImmutable::parse('2026-11-02 05:15:00', 'Asia/Jakarta');
        $this->assertSame('2026-11-01 22:15:00.000000', $akun->getAttributes()['created_at']);
    }

    public function test_timestamp_utc_dan_string_tidak_berubah(): void
    {
        $pinjam = new Loan;

        $pinjam->loaned_at = CarbonImmutable::parse('2026-11-01T22:15:00Z');
        $this->assertSame('2026-11-01 22:15:00.000000', $pinjam->getAttributes()['loaned_at']);

        $pinjam->loaned_at = '2026-11-01 22:15:00';
        $this->assertSame('2026-11-01 22:15:00.000000', $pinjam->getAttributes()['loaned_at']);
    }

    // --- D-01: users.name hanya untuk Admin ----------------------------------

    public function test_nama_tampilan_admin_dari_users_name(): void
    {
        $admin = (new User)->forceFill(['role' => Peran::Admin, 'name' => 'Rina Wulandari']);

        $this->assertSame('Rina Wulandari', $admin->nama_tampilan);
    }

    public function test_nama_tampilan_anggota_dari_members_name(): void
    {
        $akun = (new User)->forceFill(['role' => Peran::Anggota, 'name' => null]);
        $akun->setRelation('member', (new Member)->forceFill(['name' => 'Nadia Rahmawati']));

        $this->assertNull($akun->name);
        $this->assertSame('Nadia Rahmawati', $akun->nama_tampilan);
        $this->assertArrayNotHasKey('name', array_filter($akun->getAttributes()), 'Accessor tidak menulis ke users.name.');

        $tanpaAnggota = (new User)->forceFill(['role' => Peran::Anggota]);
        $tanpaAnggota->setRelation('member', null);
        $this->assertNull($tanpaAnggota->nama_tampilan);
    }

    public function test_factory_default_anggota_tanpa_nama_dan_admin_bernama(): void
    {
        $anggota = User::factory()->make();
        $this->assertSame(Peran::Anggota, $anggota->role);
        $this->assertNull($anggota->name);

        $admin = User::factory()->admin()->make();
        $this->assertSame(Peran::Admin, $admin->role);
        $this->assertNotSame('', trim((string) $admin->name));
    }

    public function test_role_dan_is_active_tetap_tidak_fillable(): void
    {
        $akun = (new User)->fill(['username' => 'x', 'role' => 'admin', 'is_active' => false]);

        $this->assertNull($akun->role);
        $this->assertTrue($akun->is_active);
    }

    // --- Seeder (D-01, D-08) tanpa menjalankan ke database -------------------

    public function test_data_seed_nama_akun_anggota_konsisten_dengan_data_anggota(): void
    {
        $data = $this->dataSeed();

        $this->panggilPemeriksaNama($data);
        $this->addToAssertionCount(1);

        $data['pengguna'][array_key_last($data['pengguna'])]['nama'] = 'Nama Lain';
        $this->expectException(RuntimeException::class);
        $this->panggilPemeriksaNama($data);
    }

    public function test_seeder_tidak_lagi_memiliki_kata_sandi_anggota_bersama(): void
    {
        $this->assertNull(config('perpustakaan.seed.anggota_demo_password'));
        $this->assertStringNotContainsString('SEED_ANGGOTA', (string) file_get_contents(database_path('seeders/DemoPerpustakaanSeeder.php')));
        $this->assertStringNotContainsString('SEED_ANGGOTA', (string) file_get_contents(base_path('.env.example')));
    }

    public function test_seeder_menolak_tanpa_kata_sandi_admin_sebelum_menyentuh_database(): void
    {
        config(['perpustakaan.seed.admin_password' => null]);
        $sandiAdmin = new ReflectionMethod(DemoPerpustakaanSeeder::class, 'sandiAdmin');

        $this->expectExceptionMessage('SEED_ADMIN_PASSWORD');
        $sandiAdmin->invoke(new DemoPerpustakaanSeeder);
    }

    // --- D-04: tanpa reset kata sandi lewat email ----------------------------

    public function test_broker_reset_kata_sandi_bawaan_tidak_aktif(): void
    {
        $this->assertArrayNotHasKey('passwords', config('auth.defaults'));

        $this->expectExceptionMessage('Password resetter [] is not defined');
        Password::broker();
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function dataSeed(): array
    {
        return json_decode((string) file_get_contents(database_path('seeders/data/perpustakaan-demo.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    private function panggilPemeriksaNama(array $data): void
    {
        (new ReflectionMethod(DemoPerpustakaanSeeder::class, 'pastikanNamaAkunAnggotaKonsisten'))
            ->invoke(new DemoPerpustakaanSeeder, $data);
    }
}
