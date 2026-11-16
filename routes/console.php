<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Batch 2 — kedaluwarsa booking: tiap 5 menit, tanpa tumpang tindih (kunci cache,
| kedaluwarsa 10 menit bila proses terhenti). Lokal: `php artisan schedule:work`.
| Production WAJIB menjalankan `php artisan schedule:run` tiap menit (cron/systemd);
| belum dikonfigurasi di repositori ini. Lihat docs/fitur/KEDALUWARSA_BOOKING.md.
*/
Schedule::command('bookings:expire')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->onOneServer();
