<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Phase 7A — `members` (Anggota). Desain Phase 7 §4.2.
 * Akun login opsional: user_id NULL = belum punya akun; UNIQUE = maks. satu akun.
 * `status` adalah satu-satunya Status Anggota dan kelayakan login Anggota.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('member_code', 30);
            $table->string('name', 100);
            $table->string('contact', 50);
            $table->string('status', 16)->collation('utf8mb4_bin');
            $table->dateTime('created_at', 6)->useCurrent();
            $table->dateTime('updated_at', 6)->useCurrent()->useCurrentOnUpdate();

            $table->unique('member_code', 'uq_members_member_code');
            $table->unique('user_id', 'uq_members_user_id');
            // D-07 (disetujui): tanpa indeks `status` — hanya 2 nilai (selektivitas
            // rendah); tambahkan lewat migration baru bila EXPLAIN membuktikan perlu.

            $table->foreign('user_id', 'fk_members_user')
                ->references('id')->on('users')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE members ADD CONSTRAINT chk_members_status CHECK (status IN ('Aktif', 'Nonaktif'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
