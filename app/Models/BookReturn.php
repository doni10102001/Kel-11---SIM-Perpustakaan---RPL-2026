<?php

namespace App\Models;

use App\Models\Concerns\PunyaKodeBisnis;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengembalian (tabel `returns`). Nama kelas BookReturn karena `return`
 * adalah kata kunci PHP. Maksimal satu per peminjaman (UNIQUE loan_id).
 */
#[Table('returns')]
#[Fillable(['return_code', 'returned_at'])]
class BookReturn extends ModelDomain
{
    use PunyaKodeBisnis;

    public const KOLOM_KODE = 'return_code';

    protected function casts(): array
    {
        return [
            'returned_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /** Admin penerima pengembalian. */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
