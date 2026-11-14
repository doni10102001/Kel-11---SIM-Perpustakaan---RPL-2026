<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Phase 7A — `bookings`. Desain Phase 7 §4.5.
 *
 * - Tidak ada bookings.loan_id: tautan realisasi hanya lewat loans.booking_id
 *   (satu sumber, tanpa FK melingkar).
 * - reserved_copy_id (generated STORED) + UNIQUE = paling banyak satu booking
 *   'Disetujui' per eksemplar (INV-05). NULL untuk status lain sehingga riwayat
 *   tidak menghalangi booking berikutnya (MySQL mengizinkan banyak NULL di UNIQUE).
 * - Kedaluwarsa booking — ARAH DISETUJUI (Phase 7A): booking kedaluwarsa wajib
 *   BERPINDAH KELUAR dari status 'Disetujui', sehingga reserved_copy_id menjadi
 *   NULL dan reservasi eksemplar terlepas. Skema ini sengaja tidak memakai
 *   penanda waktu saja (mis. expires_at dengan status tetap 'Disetujui') karena
 *   UNIQUE di atas akan terus mengunci eksemplar. Nama status final, durasi,
 *   dan frekuensi scheduler BELUM disetujui; penerapannya nanti cukup migration
 *   tambahan (ubah chk_bookings_status + enum StatusBooking), tanpa mengubah
 *   kolom yang ada.
 * - Larangan booking ganda oleh anggota yang sama (KD 5.4) tetap aturan
 *   aplikasi; belum dijadikan constraint (TBD-06).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_code', 30);
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('copy_id');
            $table->string('status', 20)->collation('utf8mb4_bin')->default('Diajukan');
            $table->dateTime('submitted_at', 6);
            $table->dateTime('decided_at', 6)->nullable();
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->string('rejection_reason', 200)->nullable();
            $table->unsignedBigInteger('reserved_copy_id')
                ->nullable()
                ->storedAs("CASE WHEN `status` = 'Disetujui' THEN `copy_id` ELSE NULL END");
            $table->dateTime('created_at', 6)->useCurrent();
            $table->dateTime('updated_at', 6)->useCurrent()->useCurrentOnUpdate();

            $table->unique('booking_code', 'uq_bookings_booking_code');
            $table->unique('reserved_copy_id', 'uq_bookings_reserved_copy');
            $table->index(['member_id', 'status'], 'idx_booking_member_status');
            $table->index(['copy_id', 'status'], 'idx_booking_copy_status');
            $table->index(['status', 'submitted_at'], 'idx_booking_status_submitted');
            $table->index('staff_id', 'idx_booking_staff');

            $table->foreign('member_id', 'fk_bookings_member')
                ->references('id')->on('members')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('copy_id', 'fk_bookings_copy')
                ->references('id')->on('book_copies')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('staff_id', 'fk_bookings_staff')
                ->references('id')->on('users')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE bookings ADD CONSTRAINT chk_bookings_status CHECK (status IN ('Diajukan', 'Disetujui', 'Ditolak', 'Dibatalkan', 'Direalisasikan'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
