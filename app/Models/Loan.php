<?php

namespace App\Models;

use App\Casts\TanggalSaja;
use App\Enums\StatusPeminjaman;
use App\Models\Concerns\PunyaKodeBisnis;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Peminjaman (tabel `loans`). `due_date` = DATE murni (cast TanggalSaja).
 * member_id, copy_id, staff_id (Admin pemroses), booking_id, dan status tidak
 * fillable: diisi service domain setelah otorisasi dan row lock.
 */
#[Table('loans')]
#[Fillable(['loan_code', 'loaned_at', 'due_date'])]
#[Hidden(['active_copy_id'])]
class Loan extends ModelDomain
{
    use PunyaKodeBisnis;

    public const KOLOM_KODE = 'loan_code';

    protected $attributes = [
        'status' => 'Aktif',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusPeminjaman::class,
            'loaned_at' => 'datetime',
            'due_date' => TanggalSaja::class,
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function copy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class, 'copy_id');
    }

    /** Admin pemroses. */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /** Booking asal bila peminjaman ini hasil realisasi; null untuk peminjaman langsung. */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function bookReturn(): HasOne
    {
        return $this->hasOne(BookReturn::class, 'loan_id');
    }
}
