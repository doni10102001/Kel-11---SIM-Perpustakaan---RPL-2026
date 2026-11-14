<?php

namespace App\Enums;

/**
 * Status booking (bookings.status) — SRS §6.3 / State Machine 04.
 *
 * Status kedaluwarsa booking BELUM ditambahkan: durasi dan representasinya
 * masih menunggu keputusan (lihat docs/database/phase7/PHASE_7A_DATABASE_SETUP.md).
 */
enum StatusBooking: string
{
    case Diajukan = 'Diajukan';
    case Disetujui = 'Disetujui';
    case Ditolak = 'Ditolak';
    case Dibatalkan = 'Dibatalkan';
    case Direalisasikan = 'Direalisasikan';
}
