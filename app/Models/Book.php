<?php

namespace App\Models;

use App\Models\Concerns\PunyaKodeBisnis;
use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Data bibliografi (tabel `books`). Jumlah eksemplar dihitung dari `copies`. */
#[Table('books')]
#[Fillable(['book_code', 'title', 'author', 'publisher', 'publication_year', 'category'])]
class Book extends ModelDomain
{
    /** @use HasFactory<BookFactory> */
    use HasFactory, PunyaKodeBisnis;

    public const KOLOM_KODE = 'book_code';

    protected function casts(): array
    {
        return [
            'publication_year' => 'integer',
        ];
    }

    public function copies(): HasMany
    {
        return $this->hasMany(BookCopy::class);
    }
}
