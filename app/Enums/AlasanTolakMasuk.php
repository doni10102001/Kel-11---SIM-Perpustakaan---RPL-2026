<?php

namespace App\Enums;

/**
 * Phase 8A — alasan akun dengan kredensial BENAR tetap tidak boleh masuk
 * (atau sesinya diakhiri). Diungkap hanya SETELAH kata sandi cocok, sehingga
 * pesan tidak membocorkan keberadaan akun kepada pihak yang tidak tahu
 * kata sandinya. Pesan sama dengan prototipe yang disetujui (PESAN_MASUK di
 * resources/js/composables/useDataPerpustakaan.js).
 */
enum AlasanTolakMasuk: string
{
    /** Admin dengan users.is_active = false. */
    case AkunNonaktif = 'akun_nonaktif';

    /** Anggota dengan members.status = Nonaktif (satu-satunya sumber status Anggota). */
    case AnggotaNonaktif = 'anggota_nonaktif';

    /** Akun berperan Anggota tanpa data Anggota tertaut. */
    case RelasiTidakValid = 'relasi_tidak_valid';

    public function pesan(): string
    {
        return match ($this) {
            self::AkunNonaktif => 'Akun ini sedang nonaktif. Hubungi Admin perpustakaan.',
            self::AnggotaNonaktif => 'Keanggotaan Anda sedang nonaktif sehingga tidak dapat masuk. Hubungi Admin perpustakaan.',
            self::RelasiTidakValid => 'Akun ini belum terhubung dengan data anggota yang valid. Hubungi Admin perpustakaan.',
        };
    }
}
