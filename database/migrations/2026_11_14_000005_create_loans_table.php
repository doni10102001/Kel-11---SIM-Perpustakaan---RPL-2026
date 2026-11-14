<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Phase 7A — `loans` (peminjaman). Desain Phase 7 §4.6.
 *
 * - due_date = DATE NOT NULL (keputusan disetujui; diinput manual di UI).
 * - booking_id NULLABLE + UNIQUE = satu-satunya tautan booking → loan
 *   (NULL untuk peminjaman langsung; satu booking maks. satu loan, INV-09).
 * - active_copy_id (generated STORED) + UNIQUE = paling banyak satu loan
 *   'Aktif' per eksemplar (INV-04); loan 'Selesai' boleh berulang.
 * - Konsistensi silang copy/booking/loan tetap dijaga transaksi + row lock
 *   pada service domain (Phase 8+).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_code', 30);
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('copy_id');
            $table->unsignedBigInteger('staff_id');
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->dateTime('loaned_at', 6);
            $table->date('due_date');
            $table->string('status', 16)->collation('utf8mb4_bin')->default('Aktif');
            $table->unsignedBigInteger('active_copy_id')
                ->nullable()
                ->storedAs("CASE WHEN `status` = 'Aktif' THEN `copy_id` ELSE NULL END");
            $table->dateTime('created_at', 6)->useCurrent();
            $table->dateTime('updated_at', 6)->useCurrent()->useCurrentOnUpdate();

            $table->unique('loan_code', 'uq_loans_loan_code');
            $table->unique('booking_id', 'uq_loans_booking_id');
            $table->unique('active_copy_id', 'uq_loans_active_copy');
            $table->index(['member_id', 'status'], 'idx_loans_member_status');
            $table->index(['copy_id', 'status'], 'idx_loans_copy_status');
            $table->index('staff_id', 'idx_loans_staff');
            $table->index('loaned_at', 'idx_loans_loaned_at');
            $table->index('due_date', 'idx_loans_due_date');

            $table->foreign('member_id', 'fk_loans_member')
                ->references('id')->on('members')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('copy_id', 'fk_loans_copy')
                ->references('id')->on('book_copies')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('staff_id', 'fk_loans_staff')
                ->references('id')->on('users')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('booking_id', 'fk_loans_booking')
                ->references('id')->on('bookings')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE loans ADD CONSTRAINT chk_loans_status CHECK (status IN ('Aktif', 'Selesai'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
