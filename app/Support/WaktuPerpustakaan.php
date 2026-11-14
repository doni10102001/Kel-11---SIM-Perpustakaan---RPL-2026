<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Aturan waktu SIM Perpustakaan (Phase 7A D-02).
 *
 * - Timestamp kejadian (DATETIME(6): loaned_at, returned_at, submitted_at, ...)
 *   disimpan dan diproses dalam UTC.
 * - Kalender bisnis dan tampilan memakai zona tampilan (Asia/Jakarta, WIB).
 *   "Hari ini", batas hari filter laporan, dan kolom tanggal-saja (DATE, mis.
 *   loans.due_date) selalu dihitung pada zona tampilan, BUKAN UTC.
 *
 * Contoh kesalahan yang dicegah: pukul 05:00 WIB tanggal 14 = 22:00 UTC
 * tanggal 13. `today()` Laravel (UTC) akan memberi tanggal 13, padahal di
 * perpustakaan sudah tanggal 14. Karena itu aturan validasi tanggal JANGAN
 * memakai kata kunci 'today' bawaan Laravel; pakai self::hariIni().
 */
final class WaktuPerpustakaan
{
    private const FORMAT_TANGGAL = 'Y-m-d';

    public static function zonaTampilan(): DateTimeZone
    {
        return new DateTimeZone((string) config('perpustakaan.zona_waktu_tampilan', 'Asia/Jakarta'));
    }

    /** Saat ini sebagai instant UTC (untuk mengisi kolom timestamp kejadian). */
    public static function sekarangUtc(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC');
    }

    /** Tanggal kalender "hari ini" pada zona tampilan, format Y-m-d. */
    public static function hariIni(): string
    {
        return CarbonImmutable::now(self::zonaTampilan())->format(self::FORMAT_TANGGAL);
    }

    /** Tanggal kalender (Y-m-d) dari sebuah instant, dilihat pada zona tampilan. */
    public static function tanggalLokal(DateTimeInterface $instant): string
    {
        return CarbonImmutable::instance($instant)->setTimezone(self::zonaTampilan())->format(self::FORMAT_TANGGAL);
    }

    /**
     * Batas filter periode laporan: tanggal awal..akhir (inklusif, kalender WIB)
     * menjadi rentang setengah-terbuka UTC [mulai, sebelum).
     *
     * Pakai: where(kolom, '>=', $mulai)->where(kolom, '<', $sebelum).
     * Jangan memakai DATE(kolom) di SQL: kolom berisi jam UTC, sehingga tanggal
     * hasil DATE() bergeser untuk kejadian 00:00–06:59 WIB.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable} keduanya berzona UTC
     */
    public static function rentangHariUtc(string $tanggalAwal, string $tanggalAkhir): array
    {
        $awal = self::awalHariLokal($tanggalAwal);
        $akhir = self::awalHariLokal($tanggalAkhir);

        if ($akhir->lessThan($awal)) {
            throw new InvalidArgumentException('Tanggal akhir tidak boleh sebelum tanggal awal.');
        }

        return [$awal->utc(), $akhir->addDay()->utc()];
    }

    /**
     * Instant UTC untuk tanggal kalender lokal pada jam lokal tertentu.
     * Dipakai seeder untuk data mock tanpa jam (konvensi 12:00 WIB, sama
     * dengan resources/js/utils/format.js), agar tanggal tidak bergeser.
     */
    public static function instantDariTanggalLokal(string $tanggal, string $jam = '12:00'): CarbonImmutable
    {
        self::pastikanTanggal($tanggal);
        if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $jam)) {
            throw new InvalidArgumentException("Jam '{$jam}' harus berformat HH:MM.");
        }

        return CarbonImmutable::createFromFormat('!Y-m-d H:i', "{$tanggal} {$jam}", self::zonaTampilan())->utc();
    }

    /** Pukul 00:00 zona tampilan pada tanggal kalender tersebut. */
    public static function awalHariLokal(string $tanggal): CarbonImmutable
    {
        self::pastikanTanggal($tanggal);

        return CarbonImmutable::createFromFormat('!Y-m-d', $tanggal, self::zonaTampilan());
    }

    public static function tanggalValid(string $tanggal): bool
    {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $tanggal, $m) === 1
            && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    private static function pastikanTanggal(string $tanggal): void
    {
        if (! self::tanggalValid($tanggal)) {
            throw new InvalidArgumentException("Tanggal '{$tanggal}' harus tanggal valid berformat Y-m-d.");
        }
    }
}
