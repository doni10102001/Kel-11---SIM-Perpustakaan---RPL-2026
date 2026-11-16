<?php

namespace App\Enums;

/**
 * Status booking (bookings.status) — SRS §6.3 / State Machine 04.
 *
 * Batch 2 (Phase 10): `Kedaluwarsa` (keputusan disetujui) — booking Disetujui
 * yang tidak direalisasikan dalam 48 jam sejak persetujuan berpindah ke status
 * ini dan reservasi eksemplarnya dilepas. Lihat docs/fitur/KEDALUWARSA_BOOKING.md.
 */
enum StatusBooking: string
{
    case Diajukan = 'Diajukan';
    case Disetujui = 'Disetujui';
    case Ditolak = 'Ditolak';
    case Dibatalkan = 'Dibatalkan';
    case Direalisasikan = 'Direalisasikan';
    case Kedaluwarsa = 'Kedaluwarsa';
}
