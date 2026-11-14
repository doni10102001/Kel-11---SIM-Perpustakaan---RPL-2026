<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Phase 8D — wajib ganti kata sandi untuk akun Anggota (ADITIF, non-destruktif).
 *
 * - `must_change_password` BOOLEAN NOT NULL DEFAULT 0: akun yang sudah ada tetap 0
 *   (tetap dapat masuk seperti sebelumnya).
 * - Diset 1 oleh server saat Admin membuat akses login atau mereset kata sandi
 *   Anggota; diset 0 hanya setelah Anggota berhasil mengganti kata sandinya sendiri.
 * - CHECK `chk_users_wajib_ganti_hanya_anggota`: kebijakan berlaku hanya untuk
 *   Anggota (Admin selalu 0), dijaga di tingkat database seperti D-03.
 * - Tidak fillable di model (diisi eksplisit oleh service).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('is_active');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT chk_users_wajib_ganti_hanya_anggota CHECK (role = 'anggota' OR must_change_password = 0)");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE users DROP CHECK chk_users_wajib_ganti_hanya_anggota');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
