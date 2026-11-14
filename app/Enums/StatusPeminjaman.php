<?php

namespace App\Enums;

/** Status peminjaman (loans.status). */
enum StatusPeminjaman: string
{
    case Aktif = 'Aktif';
    case Selesai = 'Selesai';
}
