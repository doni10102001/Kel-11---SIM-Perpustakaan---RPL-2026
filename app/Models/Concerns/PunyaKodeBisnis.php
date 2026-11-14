<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Kode bisnis (AG-001, BK-001, EK-001, BP-001, PJ-001, KB-001) disimpan di kolom
 * unik terpisah; relasi database tetap memakai ID internal.
 *
 * Model pemakai wajib mendefinisikan konstanta KOLOM_KODE.
 */
trait PunyaKodeBisnis
{
    public static function kolomKode(): string
    {
        return static::KOLOM_KODE;
    }

    /** Rute UI memakai kode bisnis, bukan ID internal. */
    public function getRouteKeyName(): string
    {
        return static::KOLOM_KODE;
    }

    /** Pencarian kode tidak peka huruf besar/kecil mengikuti collation utf8mb4_0900_ai_ci. */
    public function scopeKode(Builder $query, string $kode): Builder
    {
        return $query->where(static::KOLOM_KODE, trim($kode));
    }

    public static function cariKode(string $kode): ?static
    {
        return static::query()->kode($kode)->first();
    }
}
