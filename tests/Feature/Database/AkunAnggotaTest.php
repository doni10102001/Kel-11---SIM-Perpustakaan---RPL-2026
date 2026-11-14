<?php

namespace Tests\Feature\Database;

use App\Enums\Peran;
use App\Enums\StatusAnggota;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Phase 7A — akun login Anggota opsional, relasi 1:1, username unik, hash
 * kata sandi, dan satu Status Anggota sebagai pengendali login.
 */
class AkunAnggotaTest extends MySqlTestCase
{
    public function test_anggota_boleh_tanpa_akun_login(): void
    {
        $anggota = $this->buatAnggota();

        $this->assertNull($anggota->fresh()->user_id);
        $this->assertNull($anggota->fresh()->user);
    }

    public function test_relasi_satu_ke_satu_anggota_dan_akun(): void
    {
        $akun = $this->buatAkunAnggota();
        $anggota = $this->buatAnggota([], $akun);

        $this->assertTrue($akun->fresh()->member->is($anggota));
        $this->assertTrue($anggota->fresh()->user->is($akun));

        $this->assertDitolakDb(1062, fn () => $this->buatAnggota([], $akun), 'Satu akun tidak boleh dipakai dua anggota.');
    }

    public function test_banyak_anggota_tanpa_akun_tidak_bentrok_pada_unique_user_id(): void
    {
        $this->buatAnggota();
        $this->buatAnggota();

        $this->assertSame(2, Member::whereNull('user_id')->count());
    }

    public function test_username_unik_tanpa_membedakan_huruf_besar_kecil(): void
    {
        $this->buatAkunAnggota(['username' => 'nadia.r']);

        $this->assertDitolakDb(1062, fn () => $this->buatAkunAnggota(['username' => 'nadia.r']));
        $this->assertDitolakDb(1062, fn () => $this->buatAkunAnggota(['username' => 'NADIA.R']));
    }

    public function test_kata_sandi_disimpan_sebagai_hash_dan_tidak_terserialisasi(): void
    {
        $polos = 'RahasiaUji-'.uniqid();
        $akun = $this->buatAkunAnggota(['password' => $polos]);

        $tersimpan = DB::table('users')->where('id', $akun->id)->value('password');

        $this->assertNotSame($polos, $tersimpan);
        $this->assertStringNotContainsString($polos, $tersimpan);
        $this->assertTrue(Hash::isHashed($tersimpan));
        $this->assertTrue(Hash::check($polos, $tersimpan));
        $this->assertArrayNotHasKey('password', $akun->fresh()->toArray());
    }

    public function test_role_dan_is_active_tidak_dapat_diisi_massal(): void
    {
        $akun = new User;
        $akun->fill([
            'username' => 'penyusup',
            'password' => 'RahasiaUji-123',
            'role' => 'admin',
            'is_active' => false,
        ]);

        $this->assertNull($akun->role);
        $this->assertTrue($akun->is_active);

        $anggota = new Member;
        $anggota->fill(['member_code' => 'UJI-X', 'name' => 'X', 'contact' => '1', 'status' => 'Aktif', 'user_id' => 99]);
        $this->assertNull($anggota->user_id);
    }

    public function test_role_hanya_admin_atau_anggota(): void
    {
        $this->assertDitolakDb(3819, fn () => DB::table('users')->insert([
            'username' => 'petugas1', 'name' => 'Petugas', 'password' => Hash::make('x'), 'role' => 'petugas', 'is_active' => 1,
        ]));
    }

    public function test_nama_akun_hanya_untuk_admin_dan_anggota_memakai_nama_data_anggota(): void
    {
        $sandi = Hash::make('x');

        $this->assertDitolakDb(3819, fn () => DB::table('users')->insert([
            'username' => 'admin.tanpa.nama', 'name' => null, 'password' => $sandi, 'role' => 'admin', 'is_active' => 1,
        ]), 'Admin wajib memiliki users.name.');
        $this->assertDitolakDb(3819, fn () => DB::table('users')->insert([
            'username' => 'admin.nama.kosong', 'name' => '   ', 'password' => $sandi, 'role' => 'admin', 'is_active' => 1,
        ]), 'Nama Admin tidak boleh kosong.');
        $this->assertDitolakDb(3819, fn () => DB::table('users')->insert([
            'username' => 'anggota.bernama', 'name' => 'Nama Ganda', 'password' => $sandi, 'role' => 'anggota', 'is_active' => 1,
        ]), 'Akun Anggota wajib users.name = NULL.');

        $akun = $this->buatAkunAnggota();
        $this->assertDitolakDb(3819, fn () => DB::table('users')->where('id', $akun->id)->update(['name' => 'Isi']));

        $anggota = $this->buatAnggota(['name' => 'Nadia Rahmawati'], $akun);
        $this->assertNull($akun->fresh()->name);
        $this->assertSame('Nadia Rahmawati', $akun->fresh()->nama_tampilan);

        $anggota->update(['name' => 'Nadia R.']);
        $this->assertSame('Nadia R.', $akun->fresh()->nama_tampilan, 'Satu sumber: perubahan members.name langsung berlaku.');

        $admin = $this->buatAdmin(['name' => 'Rina Wulandari']);
        $this->assertSame('Rina Wulandari', $admin->fresh()->nama_tampilan);
    }

    public function test_akun_anggota_tidak_punya_status_akses_terpisah(): void
    {
        $akun = $this->buatAkunAnggota();

        $this->assertDitolakDb(3819, fn () => DB::table('users')->where('id', $akun->id)->update(['is_active' => 0]),
            'Status login Anggota hanya dari members.status, bukan users.is_active.');

        $admin = $this->buatAdmin();
        DB::table('users')->where('id', $admin->id)->update(['is_active' => 0]);
        $this->assertFalse($admin->fresh()->is_active);
    }

    public function test_kelayakan_login_mengikuti_status_anggota(): void
    {
        $akun = $this->buatAkunAnggota();
        $anggota = $this->buatAnggota(['status' => StatusAnggota::Aktif], $akun);
        $this->assertTrue($akun->fresh()->bolehMasuk());

        $anggota->update(['status' => StatusAnggota::Nonaktif]);
        $this->assertFalse($akun->fresh()->bolehMasuk(), 'Anggota Nonaktif tidak boleh masuk.');

        $anggota->update(['status' => StatusAnggota::Aktif]);
        $this->assertTrue($akun->fresh()->bolehMasuk(), 'Diaktifkan kembali → akun lama berlaku lagi.');

        $tanpaAnggota = $this->buatAkunAnggota();
        $this->assertFalse($tanpaAnggota->bolehMasuk(), 'Akun Anggota tanpa data Anggota tidak boleh masuk.');

        $admin = $this->buatAdmin();
        $this->assertSame(Peran::Admin, $admin->fresh()->role);
        $this->assertTrue($admin->fresh()->bolehMasuk());
    }

    public function test_akun_tertaut_tidak_dapat_dihapus_dan_riwayat_tetap_ada(): void
    {
        $akun = $this->buatAkunAnggota();
        $anggota = $this->buatAnggota([], $akun);
        $admin = $this->buatAdmin();
        $this->buatPeminjaman($anggota, $this->buatEksemplar(status: 'Dipinjam'), $admin);

        $this->assertDitolakDb(1451, fn () => DB::table('users')->where('id', $akun->id)->delete());
        $this->assertDitolakDb(1451, fn () => DB::table('users')->where('id', $admin->id)->delete(),
            'Admin pemroses transaksi tidak boleh terhapus bersama riwayat.');

        $this->assertSame(1, $anggota->loans()->count());
    }

    public function test_perubahan_status_anggota_tidak_mengubah_hash_kata_sandi(): void
    {
        $akun = $this->buatAkunAnggota(['password' => 'RahasiaUji-456']);
        $anggota = $this->buatAnggota([], $akun);
        $hashAwal = DB::table('users')->where('id', $akun->id)->value('password');

        $anggota->update(['status' => StatusAnggota::Nonaktif, 'name' => 'Nama Baru']);

        $this->assertSame($hashAwal, DB::table('users')->where('id', $akun->id)->value('password'));
    }
}
