<?php

namespace App\Models;

use App\Enums\StatusAnggota;
use App\Models\Concerns\PunyaKodeBisnis;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Anggota perpustakaan (tabel `members`), dibuat oleh Admin.
 * Akun login opsional (`user_id` nullable, unik). `user_id` tidak fillable:
 * penautan akun dilakukan eksplisit oleh service Admin.
 */
#[Table('members')]
#[Fillable(['member_code', 'name', 'contact', 'status'])]
class Member extends ModelDomain
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory, PunyaKodeBisnis;

    public const KOLOM_KODE = 'member_code';

    protected function casts(): array
    {
        return [
            'status' => StatusAnggota::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function isAktif(): bool
    {
        return $this->status === StatusAnggota::Aktif;
    }
}
