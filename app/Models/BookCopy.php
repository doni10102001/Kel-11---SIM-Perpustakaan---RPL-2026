<?php

namespace App\Models;

use App\Enums\StatusEksemplar;
use App\Models\Concerns\PunyaKodeBisnis;
use Database\Factories\BookCopyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eksemplar fisik (tabel `book_copies`).
 * `status` tidak fillable: hanya berubah melalui transaksi domain
 * (booking disetujui/dibatalkan, peminjaman, pengembalian).
 */
#[Table('book_copies')]
#[Fillable(['copy_code'])]
class BookCopy extends ModelDomain
{
    /** @use HasFactory<BookCopyFactory> */
    use HasFactory, PunyaKodeBisnis;

    public const KOLOM_KODE = 'copy_code';

    protected $attributes = [
        'status' => 'Tersedia',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusEksemplar::class,
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'copy_id');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class, 'copy_id');
    }
}
