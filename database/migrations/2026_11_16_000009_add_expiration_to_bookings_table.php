<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Batch 2 (Phase 10) — kedaluwarsa booking (ADITIF).
 *
 * Kebijakan disetujui: booking Disetujui kedaluwarsa tepat 48 jam setelah
 * persetujuan Admin (expires_at = decided_at + 48 jam, instant UTC). Saat
 * diproses, status → 'Kedaluwarsa', expired_at = waktu pemrosesan (UTC), dan
 * reservasi eksemplar dilepas. Kolom generated reserved_copy_id TIDAK diubah:
 * tetap hanya mereservasi status 'Disetujui', sehingga Kedaluwarsa otomatis
 * melepas UNIQUE uq_bookings_reserved_copy.
 *
 * Backfill: HANYA booking berstatus 'Disetujui' (saat ini) memperoleh
 * expires_at = decided_at + 48 jam. Booking Disetujui tanpa decided_at → migration
 * BERHENTI (tidak ada timestamp persetujuan yang dikarang). Riwayat lain
 * (Ditolak, Dibatalkan, Direalisasikan) tidak ditulis ulang (expires_at NULL).
 * Migration TIDAK mengubah status apa pun; booking yang batasnya sudah lewat
 * tetap 'Disetujui' sampai diproses `bookings:expire` / transaksi berikutnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        $mysql = DB::getDriverName() === 'mysql';

        if ($mysql) {
            $tanpaKeputusan = DB::table('bookings')->where('status', 'Disetujui')->whereNull('decided_at')->pluck('booking_code');
            if ($tanpaKeputusan->isNotEmpty()) {
                throw new RuntimeException('Migration dihentikan: booking Disetujui tanpa decided_at ('.$tanpaKeputusan->implode(', ').'). Timestamp persetujuan tidak dikarang.');
            }
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->dateTime('expires_at', 6)->nullable()->after('decided_at');
            $table->dateTime('expired_at', 6)->nullable()->after('expires_at');
            // Pemindaian kedaluwarsa: WHERE status = 'Disetujui' AND expires_at <= ? ORDER BY expires_at, id.
            $table->index(['status', 'expires_at', 'id'], 'idx_booking_status_expires');
        });

        if (! $mysql) {
            return;
        }

        DB::statement("UPDATE bookings SET expires_at = DATE_ADD(decided_at, INTERVAL 48 HOUR) WHERE status = 'Disetujui' AND expires_at IS NULL");

        DB::statement('ALTER TABLE bookings DROP CHECK chk_bookings_status');
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT chk_bookings_status CHECK (status IN ('Diajukan', 'Disetujui', 'Ditolak', 'Dibatalkan', 'Direalisasikan', 'Kedaluwarsa'))");
        DB::statement(<<<'SQL'
            ALTER TABLE bookings ADD CONSTRAINT chk_bookings_expiry CHECK (
                (status <> 'Disetujui' OR (decided_at IS NOT NULL AND expires_at IS NOT NULL))
                AND (status <> 'Kedaluwarsa' OR (expires_at IS NOT NULL AND expired_at IS NOT NULL))
                AND (expired_at IS NULL OR status = 'Kedaluwarsa')
                AND (expires_at IS NULL OR (decided_at IS NOT NULL AND expires_at > decided_at))
                AND (expired_at IS NULL OR expired_at >= expires_at)
            )
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            if (DB::table('bookings')->where('status', 'Kedaluwarsa')->exists()) {
                throw new RuntimeException('Rollback dihentikan: sudah ada booking Kedaluwarsa. Status itu tidak dapat dipetakan ulang tanpa menulis ulang riwayat.');
            }
            DB::statement('ALTER TABLE bookings DROP CHECK chk_bookings_expiry');
            DB::statement('ALTER TABLE bookings DROP CHECK chk_bookings_status');
            DB::statement("ALTER TABLE bookings ADD CONSTRAINT chk_bookings_status CHECK (status IN ('Diajukan', 'Disetujui', 'Ditolak', 'Dibatalkan', 'Direalisasikan'))");
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('idx_booking_status_expires');
            $table->dropColumn(['expires_at', 'expired_at']);
        });
    }
};
