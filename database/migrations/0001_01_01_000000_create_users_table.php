<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Phase 7A — tabel `users` (akun login Admin/Anggota) dan `sessions`.
 *
 * Diadaptasi dari migration bawaan Laravel (belum pernah dijalankan):
 * - identitas login = `username` (tanpa email; desain Phase 7 §4.1, TBD-02);
 * - `role` hanya 'admin' | 'anggota';
 * - `is_active` hanya bermakna untuk Admin. Untuk Anggota, kelayakan login
 *   ditentukan `members.status` (keputusan S.1–S.4), sehingga CHECK
 *   `chk_users_anggota_tanpa_status_akses` mengunci is_active = 1 bagi Anggota;
 * - `name` hanya identitas Admin (D-01, disetujui): Admin wajib berisi nama
 *   (tidak kosong), akun Anggota wajib NULL karena nama Anggota bersumber dari
 *   `members.name` (satu sumber). Dijaga CHECK `chk_users_nama_sesuai_peran`;
 * - `password_reset_tokens` bawaan dihapus (D-04, disetujui) karena bergantung
 *   pada email; reset kata sandi Anggota dilakukan Admin lewat backend;
 * - `remember_token` dipertahankan nullable (D-05); fitur "Ingat saya" belum aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 100);
            $table->string('name', 100)->nullable();
            $table->string('password');
            $table->string('role', 16)->collation('utf8mb4_bin');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->dateTime('created_at', 6)->useCurrent();
            $table->dateTime('updated_at', 6)->useCurrent()->useCurrentOnUpdate();

            $table->unique('username', 'uq_users_username');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
                ALTER TABLE users
                    ADD CONSTRAINT chk_users_role CHECK (role IN ('admin', 'anggota')),
                    ADD CONSTRAINT chk_users_anggota_tanpa_status_akses CHECK (role = 'admin' OR is_active = 1),
                    ADD CONSTRAINT chk_users_nama_sesuai_peran CHECK (
                        (role = 'admin' AND name IS NOT NULL AND TRIM(name) <> '')
                        OR (role = 'anggota' AND name IS NULL)
                    )
                SQL);
        }

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
