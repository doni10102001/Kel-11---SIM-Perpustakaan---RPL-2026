<?php

namespace App\Support;

use App\Enums\Peran;
use App\Models\User;

/**
 * Phase 8A — identitas akun yang masuk, dibagikan ke Vue sebagai `auth.akun`.
 *
 * Satu-satunya sumber identitas di frontend (menggantikan akun pratinjau
 * AG-001/AKUN_DEMO). Hanya field tampilan: TIDAK memuat id internal, hash kata
 * sandi, remember_token, kontak, maupun data Anggota lain. Bentuknya sama dengan
 * akun pratinjau lama agar halaman Vue yang ada tetap berfungsi.
 */
final class AkunSesi
{
    /** @return array{peran: string, identitas: string, nama: ?string, labelPeran: string, keterangan: ?string, kodeAnggota?: string}|null */
    public static function untuk(?User $akun): ?array
    {
        if ($akun === null) {
            return null;
        }

        return match ($akun->role) {
            Peran::Admin => [
                'peran' => Peran::Admin->value,
                'identitas' => $akun->username,
                'nama' => $akun->nama_tampilan,
                'labelPeran' => 'Admin',
                'keterangan' => 'Admin Perpustakaan',
            ],
            Peran::Anggota => [
                'peran' => Peran::Anggota->value,
                'identitas' => $akun->username,
                'nama' => $akun->nama_tampilan,
                'labelPeran' => 'Anggota',
                'keterangan' => $akun->member?->member_code,
                'kodeAnggota' => $akun->member?->member_code,
            ],
            default => null,
        };
    }
}
