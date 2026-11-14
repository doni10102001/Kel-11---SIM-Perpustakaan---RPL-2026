<?php

namespace App\Enums;

/** Peran akun (users.role). Proyek hanya memakai dua peran: Admin dan Anggota. */
enum Peran: string
{
    case Admin = 'admin';
    case Anggota = 'anggota';
}
