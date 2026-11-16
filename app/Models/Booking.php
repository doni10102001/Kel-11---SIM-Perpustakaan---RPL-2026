<?php

namespace App\Models;

use App\Enums\StatusBooking;
use App\Models\Concerns\PunyaKodeBisnis;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Booking pinjam buku (tabel `bookings`).
 *
 * member_id, copy_id, status, decided_at, expires_at, expired_at, dan staff_id
 * tidak fillable: diisi
 * oleh service domain (Phase 8+) setelah otorisasi dan row lock.
 * Peminjaman hasil realisasi diakses lewat `loan()` (loans.booking_id) —
 * tidak ada kolom bookings.loan_id.
 */
#[Table('bookings')]
#[Fillable(['booking_code', 'submitted_at', 'rejection_reason'])]
#[Hidden(['reserved_copy_id'])]
class Booking extends ModelDomain
{
    use PunyaKodeBisnis;

    public const KOLOM_KODE = 'booking_code';

    protected $attributes = [
        'status' => 'Diajukan',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusBooking::class,
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
            // Batch 2: batas pengambilan (decided_at + 48 jam) dan waktu kedaluwarsa diproses — UTC.
            'expires_at' => 'datetime',
            'expired_at' => 'datetime',
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

    /** Admin yang menyetujui/menolak. */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /** Peminjaman hasil realisasi (0..1). */
    public function loan(): HasOne
    {
        return $this->hasOne(Loan::class, 'booking_id');
    }
}
