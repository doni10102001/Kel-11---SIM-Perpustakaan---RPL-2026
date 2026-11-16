<?php

namespace App\Console\Commands;

use App\Services\Transaksi\LayananKedaluwarsaBooking;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Batch 2 — `php artisan bookings:expire`.
 * Mengubah booking Disetujui yang melewati Batas Pengambilan (decided_at + 48 jam)
 * menjadi Kedaluwarsa dan melepas reservasi eksemplar. Idempoten, per batch,
 * kegagalan satu booking tidak menghentikan booking lain. Dijadwalkan tiap 5 menit
 * (routes/console.php). Lihat docs/phase10/BOOKING_EXPIRATION.md.
 */
#[Signature('bookings:expire {--batch=100 : Jumlah booking per batch}')]
#[Description('Kedaluwarsakan booking Disetujui yang melewati Batas Pengambilan (48 jam) dan lepaskan reservasinya')]
class KedaluwarsakanBooking extends Command
{
    public function handle(LayananKedaluwarsaBooking $layanan): int
    {
        $batch = max(1, min(1000, (int) $this->option('batch')));
        $hasil = $layanan->prosesJatuhTempo($batch);

        $this->info(sprintf('Booking kedaluwarsa: diproses %d, dilewati %d, gagal %d.', $hasil['diproses'], $hasil['dilewati'], $hasil['gagal']));

        return $hasil['gagal'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
