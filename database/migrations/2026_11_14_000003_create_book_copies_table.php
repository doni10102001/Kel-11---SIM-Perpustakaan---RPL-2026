<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Phase 7A — `book_copies` (eksemplar fisik). Desain Phase 7 §4.4.
 * "Buku minimal satu eksemplar" (INV-03) dijaga transaksi aplikasi, bukan FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_copies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('book_id');
            $table->string('copy_code', 30);
            $table->string('status', 16)->collation('utf8mb4_bin')->default('Tersedia');
            $table->dateTime('created_at', 6)->useCurrent();
            $table->dateTime('updated_at', 6)->useCurrent()->useCurrentOnUpdate();

            $table->unique('copy_code', 'uq_book_copies_copy_code');
            // Indeks komposit ini juga melayani FK book_id (kolom paling kiri).
            $table->index(['book_id', 'status'], 'idx_copies_book_status');

            $table->foreign('book_id', 'fk_book_copies_book')
                ->references('id')->on('books')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE book_copies ADD CONSTRAINT chk_book_copies_status CHECK (status IN ('Tersedia', 'Dipesan', 'Dipinjam'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('book_copies');
    }
};
