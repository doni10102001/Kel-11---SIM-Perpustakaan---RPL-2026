<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Batch 3 (audit kinerja) — indeks ADITIF untuk query yang teridentifikasi:
 *
 *   SELECT … FROM loans WHERE status = 'Aktif' ORDER BY due_date, loan_code LIMIT 10
 *   (Dashboard Admin "Peminjaman Aktif", jatuh tempo terdekat)
 *
 * Tanpa indeks ini EXPLAIN (5.200 loan) = type ALL, 5.038 baris, Using filesort.
 * Juga melayani pemuatan peminjaman Aktif pada formulir Peminjaman & Realisasi.
 * Tidak mengubah data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->index(['status', 'due_date', 'loan_code'], 'idx_loans_status_due');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropIndex('idx_loans_status_due');
        });
    }
};
