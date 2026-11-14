<?php

namespace App\Enums;

/**
 * Status Anggota (members.status) — satu-satunya status keanggotaan sekaligus
 * kelayakan login Anggota (keputusan S.1–S.4). Tidak ada Status Akses terpisah.
 */
enum StatusAnggota: string
{
    case Aktif = 'Aktif';
    case Nonaktif = 'Nonaktif';
}
