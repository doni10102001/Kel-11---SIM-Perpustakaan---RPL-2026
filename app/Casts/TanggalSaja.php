<?php

namespace App\Casts;

use App\Support\WaktuPerpustakaan;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Cast untuk kolom DATE murni (mis. loans.due_date) — tanggal kalender bisnis WIB.
 *
 * - String 'Y-m-d' disimpan persis apa adanya; tidak ada konversi zona waktu,
 *   sehingga tanggal tidak bergeser satu hari (desain Phase 7 DB-16, D-02).
 * - Input DateTimeInterface dianggap sebuah instant dan diambil tanggal
 *   kalendernya pada zona TAMPILAN (Asia/Jakarta), bukan zona aplikasi (UTC).
 *   Contoh: 2026-11-16T17:30Z (= 17 Nov 00:30 WIB) → '2026-11-17'. Objek yang
 *   sudah berzona WIB (termasuk hasil get() di bawah) tidak berubah tanggalnya.
 * - Dibaca sebagai CarbonImmutable pukul 00:00 zona tampilan.
 * - Diserialisasi (toArray/JSON/Inertia) sebagai string 'Y-m-d'.
 */
class TanggalSaja implements CastsAttributes, SerializesCastableAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        return WaktuPerpustakaan::awalHariLokal(substr((string) $value, 0, 10));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return WaktuPerpustakaan::tanggalLokal($value);
        }

        $teks = trim((string) $value);

        if (! WaktuPerpustakaan::tanggalValid($teks)) {
            throw new InvalidArgumentException("Nilai {$key} harus tanggal valid berformat Y-m-d.");
        }

        return $teks;
    }

    /**
     * Diambil dari nilai MENTAH ('Y-m-d') di $attributes, bukan $value: Eloquent
     * lebih dulu mengubah objek tanggal hasil get() menjadi ISO-8601 UTC
     * (00:00 WIB tgl 16 → "...-15T17:00:00Z") sebelum memanggil method ini,
     * sehingga $value tidak dapat dipercaya untuk tanggal kalender.
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        $mentah = $attributes[$key] ?? null;

        return $mentah === null ? null : substr((string) $mentah, 0, 10);
    }
}
