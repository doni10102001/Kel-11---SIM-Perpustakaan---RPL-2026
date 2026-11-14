<?php

namespace App\Enums;

/** Status eksemplar (book_copies.status). Berubah hanya melalui transaksi domain. */
enum StatusEksemplar: string
{
    case Tersedia = 'Tersedia';
    case Dipesan = 'Dipesan';
    case Dipinjam = 'Dipinjam';
}
