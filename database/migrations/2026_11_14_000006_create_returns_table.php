<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Phase 7A — `returns` (pengembalian). Desain Phase 7 §4.7.
 * UNIQUE loan_id = maksimal satu pengembalian per peminjaman (INV-08).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_code', 30);
            $table->unsignedBigInteger('loan_id');
            $table->unsignedBigInteger('staff_id');
            $table->dateTime('returned_at', 6);
            $table->dateTime('created_at', 6)->useCurrent();
            $table->dateTime('updated_at', 6)->useCurrent()->useCurrentOnUpdate();

            $table->unique('return_code', 'uq_returns_return_code');
            $table->unique('loan_id', 'uq_returns_loan_id');
            $table->index('staff_id', 'idx_returns_staff');
            $table->index('returned_at', 'idx_returns_returned_at');

            $table->foreign('loan_id', 'fk_returns_loan')
                ->references('id')->on('loans')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('staff_id', 'fk_returns_staff')
                ->references('id')->on('users')
                ->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('returns');
    }
};
