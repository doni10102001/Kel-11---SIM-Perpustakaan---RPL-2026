<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Phase 7A — `books` (data bibliografi). Desain Phase 7 §4.3.
 * Jumlah eksemplar tidak disimpan; dihitung dari book_copies.
 * Batas tahun terbit (<= tahun berjalan) divalidasi server, bukan CHECK
 * non-deterministik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('book_code', 30);
            $table->string('title', 200);
            $table->string('author', 100);
            $table->string('publisher', 100);
            $table->unsignedSmallInteger('publication_year');
            $table->string('category', 50);
            $table->dateTime('created_at', 6)->useCurrent();
            $table->dateTime('updated_at', 6)->useCurrent()->useCurrentOnUpdate();

            $table->unique('book_code', 'uq_books_book_code');
            $table->index('title', 'idx_books_title');
            $table->index('category', 'idx_books_category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
