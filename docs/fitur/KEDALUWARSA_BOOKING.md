# Kedaluwarsa Booking (48 jam) — Desain & Operasional

## 1. Kebijakan (disetujui Batch 2)

- Booking **Disetujui** kedaluwarsa **tepat 48 jam** setelah persetujuan Admin: `expires_at = decided_at + 48 jam` (instant UTC, presisi mikrodetik).
- Kedaluwarsa bila `expires_at <= sekarang` (tepat pada batas sudah kedaluwarsa).
- Transisi: status → `Kedaluwarsa`, `expired_at` = waktu pemrosesan aktual (UTC), reservasi dilepas. Eksemplar kembali Tersedia **hanya** bila masih Dipesan, tanpa peminjaman Aktif, dan tanpa booking Disetujui lain.
- Audit `booking.kedaluwarsa`, aktor NULL (sistem), `metadata.pemicu`.
- Batas bisnis tetap 48 jam; jeda penjadwal (≤ 5 menit) tidak menambah batas: realisasi dan pembatalan memeriksa `expires_at` sendiri.
- UI menampilkan **Batas Pengambilan** dalam WIB.

## 2. Skema (migration `2026_11_16_000009_add_expiration_to_bookings_table`, aditif)

| Perubahan | Rincian |
| --- | --- |
| `bookings.expires_at` | `DATETIME(6) NULL` — batas pengambilan |
| `bookings.expired_at` | `DATETIME(6) NULL` — waktu transisi diproses |
| `chk_bookings_status` | ditambah `'Kedaluwarsa'` |
| `chk_bookings_expiry` | Disetujui ⇒ `decided_at` & `expires_at` terisi; Kedaluwarsa ⇒ `expires_at` & `expired_at` terisi; `expired_at` hanya untuk Kedaluwarsa; `expires_at > decided_at`; `expired_at >= expires_at` |
| `idx_booking_status_expires` | `(status, expires_at, id)` untuk pemindaian |
| `reserved_copy_id` (generated) | **tidak diubah** — tetap hanya `Disetujui`, sehingga Kedaluwarsa otomatis melepas `uq_bookings_reserved_copy` |

Backfill: hanya booking yang **saat ini** Disetujui: `expires_at = decided_at + 48 jam`. Bila ada Disetujui tanpa `decided_at`, migration berhenti sebelum mengubah skema (tidak ada timestamp yang dikarang). Status tidak diubah oleh migration. Riwayat Ditolak/Dibatalkan/Direalisasikan tidak ditulis ulang (`expires_at` NULL). `down()` menolak bila sudah ada booking Kedaluwarsa.

Gladi di `sim_perpustakaan_test` (data historis skema lama): backfill tepat +48 jam hanya pada Disetujui, status tidak berubah, guard menghentikan migration tanpa perubahan skema, rollback berfungsi.

### Data development

| Booking | decided_at (UTC) | expires_at (UTC) | Catatan |
| --- | --- | --- | --- |
| BP-001 | 2026-10-17 05:00 | 2026-10-19 05:00 | batas sudah lewat |
| BP-006 | 2026-11-06 05:00 | 2026-11-08 05:00 | batas sudah lewat |

Migration development tercatat **batch 3 pada 2026-11-16 07:21:41 UTC (14:21 WIB)**, 4 detik sebelum `php artisan serve` milik pengguna dimulai; bukan dijalankan oleh test atau sesi implementasi. Verifikasi terhadap checksum sebelum Batch 2: hanya `bookings` (backfill dua baris di atas + `updated_at`) dan satu baris `migrations` yang berubah.

**Perilaku yang diharapkan** (belum dijalankan di development): saat `bookings:expire` pertama berjalan, atau saat transaksi berikutnya menyentuh booking/eksemplar/anggota tersebut, BP-001 dan BP-006 menjadi Kedaluwarsa dengan `expired_at` = waktu pemrosesan, dan EK-002 serta EK-013 kembali Tersedia. Hal ini dibuktikan pada data seed yang sama di database uji (`SeederDemoTest`).

## 3. Komponen

| Komponen | Peran |
| --- | --- |
| `App\Services\Transaksi\LayananKedaluwarsaBooking` | `kedaluwarsakan()` (transaksi per booking, idempoten), `terapkanTerkunci()` (dipakai realisasi/pembatalan yang sudah memegang kunci), `prosesJatuhTempo()` (batch), `rekonsiliasiEksemplar()` / `rekonsiliasiAnggota()` (pelepasan reservasi lewat batas sebelum peminjaman, pengajuan, persetujuan, penonaktifan) |
| `php artisan bookings:expire {--batch=100}` | `App\Console\Commands\KedaluwarsakanBooking`; keluaran: jumlah diproses/dilewati/gagal; exit code gagal bila ada item gagal |
| `routes/console.php` | `Schedule::command('bookings:expire')->everyFiveMinutes()->withoutOverlapping(10)->onOneServer()` — satu-satunya pendaftaran |

## 4. Algoritme

1. Pilih ID booking `status = 'Disetujui' AND expires_at <= T` (T = waktu mulai eksekusi), urut `expires_at, id`, **maks. `--batch`** per putaran (tanpa memuat seluruh riwayat). `expired_at` tiap booking memakai waktu pemrosesan item itu sendiri, bukan T.
2. Per booking: transaksi → kunci **member** → kunci **booking** → periksa ulang status & `expires_at` → kunci **eksemplar** → status Kedaluwarsa + `expired_at` → lepas eksemplar bila aman → audit → commit.
3. Gagal satu item → rollback item itu saja, dicatat (`Log::warning` dengan `booking_id`, kelas galat, kode MySQL; tanpa data pribadi), tidak diulang di proses yang sama, dicoba lagi pada jadwal berikutnya.
4. Diulang sampai tidak ada kandidat (maks. 50 putaran per eksekusi).

Idempoten: booking yang sudah bukan Disetujui atau belum jatuh tempo dilewati tanpa audit; dua proses bersamaan → satu memproses, yang lain tertahan kunci lalu melewati (diuji dengan dua koneksi MySQL).

## 5. Operasional

| Lingkungan | Cara menjalankan |
| --- | --- |
| Lokal | `php artisan schedule:work` (menjalankan penjadwal tiap menit di terminal), atau sekali jalan `php artisan bookings:expire` |
| Production | **Wajib** cron/systemd timer: `* * * * * cd /path/aplikasi && php artisan schedule:run >> /dev/null 2>&1`. Belum dikonfigurasi; penjadwal **belum** beroperasi di production. |

- `withoutOverlapping(10)` & `onOneServer()` memakai kunci cache; untuk banyak server, `CACHE_STORE` harus bersama (database/redis).
- Pemantauan: keluaran perintah (diproses/dilewati/gagal), exit code ≠ 0 bila ada kegagalan, log `bookings:expire gagal memproses booking`, dan jumlah booking `Disetujui` dengan `expires_at <= NOW()` (harus 0 setelah penjadwal berjalan, ± 5 menit).
