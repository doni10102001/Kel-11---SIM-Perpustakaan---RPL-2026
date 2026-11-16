# Manajemen Transaksi Sirkulasi & Reservasi Perpustakaan

Sistem Informasi Manajemen Perpustakaan (SIM Perpustakaan)

| Aspek | Keterangan |
| --- | --- |
| Ruang Lingkup | Peminjaman langsung, pengembalian, ajukan/batalkan booking (Anggota), verifikasi/penolakan/realisasi booking (Admin), otomatisasi kedaluwarsa booking 48 jam |
| Basis Data & ORM | MySQL 8.4 InnoDB, Laravel 13 Eloquent, Pessimistic Locking (`lockForUpdate`), Deadlock Retry (x3) |
| Dokumen Terkait | `KEDALUWARSA_BOOKING.md`, `KONKURENSI_DAN_LOCKING.md`, `../pengujian/LAPORAN_UJI_TRANSAKSI.md` |

## 1. Arsitektur Transaksi

```text
Halaman Vue (router.post terikat, useTransaksiServer: cegah kirim ganda)
  → auth · auth.session · akun.aktif · wajib.ganti.sandi · peran:* · throttle · CSRF
  → Form Request (validasi) + Gate kelola-transaksi / booking-anggota
  → Controller Transaksi\* (redirect + flash `status` & `transaksi` SETELAH commit)
  → App\Services\Transaksi\{LayananPeminjaman, LayananBooking, LayananKedaluwarsaBooking}
       extends DasarTransaksi (DB::transaction ×3 percobaan deadlock, lockForUpdate,
       kode bisnis dari ID, audit dalam transaksi, 1205/1213 → galat "coba lagi")
  → Eloquent → MySQL (members, bookings, book_copies, loans, returns, audit_logs)
```

| Rute | Nama | Siapa |
| --- | --- | --- |
| `POST /admin/peminjaman` | `admin.peminjaman.store` | Admin |
| `POST /admin/pengembalian` | `admin.pengembalian.store` | Admin |
| `POST /admin/booking/{kodeBooking}/setujui` | `admin.booking.setujui` | Admin |
| `POST /admin/booking/{kodeBooking}/tolak` | `admin.booking.tolak` | Admin |
| `POST /admin/booking/{kodeBooking}/realisasi` | `admin.booking.realisasikan` | Admin |
| `POST /anggota/booking` | `anggota.booking.store` | Anggota (pemilik dari sesi) |
| `POST /anggota/booking/{kodeBooking}/batalkan` | `anggota.booking.batalkan` | Anggota (hanya miliknya) |

Kode bisnis transaksi baru diturunkan dari ID AUTO_INCREMENT (`PJ-011`, `BP-009`, `KB-006` di data development), sehingga tidak dapat bentrok antar-permintaan bersamaan.

## 2. Alur

| Alur | Syarat (dibaca ulang SETELAH kunci) | Hasil |
| --- | --- | --- |
| Peminjaman langsung | Anggota Aktif; eksemplar Tersedia, tanpa loan Aktif, tanpa booking Disetujui (Dipesan hanya lewat realisasi); kode eksemplar cocok dengan buku bila dikirim; Tanggal Jatuh Tempo wajib, valid, **setelah** hari ini (WIB) | Loan Aktif (`loaned_at` UTC, `due_date` DATE, `staff_id` = Admin sesi), eksemplar Dipinjam, audit `peminjaman.dibuat` |
| Pengembalian | Loan ada & Aktif, belum punya return, eksemplar Dipinjam. **Status anggota tidak diperiksa** (Aktif/Nonaktif boleh) | Return (`returned_at` UTC), loan Selesai, eksemplar Tersedia, anggota tetap pada statusnya, audit `pengembalian.dibuat`; ganda ditolak (+ UNIQUE `returns.loan_id`) |
| Ajukan booking | Anggota (sesi) Aktif; eksemplar Tersedia tanpa loan Aktif/booking Disetujui; tidak ada booking Diajukan/Disetujui milik anggota yang sama untuk eksemplar itu | Booking Diajukan (`submitted_at` UTC), **eksemplar tidak dipesan**, tanpa peminjaman/persetujuan otomatis, audit `booking.diajukan` |
| Setujui | Booking Diajukan; anggota Aktif (F-01); eksemplar Tersedia tanpa loan Aktif/booking Disetujui lain | Disetujui, `decided_at` = sekarang, `expires_at` = +48 jam, `expired_at` NULL, `staff_id` = Admin, eksemplar Dipesan, audit `booking.disetujui` |
| Tolak | Booking Diajukan; Alasan Penolakan wajib (≤ 200) | Ditolak + alasan + `decided_at` + `staff_id`; eksemplar tetap; tanpa loan; audit `booking.ditolak` (alasan TIDAK disalin ke audit) |
| Realisasi | Booking Disetujui & **belum lewat batas**; anggota Aktif; eksemplar Dipesan untuk booking ini; tanpa loan Aktif; Tanggal Jatuh Tempo seperti peminjaman | Satu loan (`booking_id`, UNIQUE), booking Direalisasikan (`expires_at` dipertahankan), eksemplar Dipinjam, audit `peminjaman.dibuat` + `booking.direalisasikan` |
| Batalkan (Anggota) | Hanya booking miliknya; Diajukan atau Disetujui yang belum lewat batas | Dibatalkan; bila Disetujui reservasi dilepas (eksemplar Tersedia bila tidak dimiliki transaksi lain); audit `booking.dibatalkan` |

Booking Disetujui yang **sudah lewat batas** (penjadwal belum jalan) saat direalisasikan atau dibatalkan: transaksi mengubahnya menjadi **Kedaluwarsa**, COMMIT, lalu permintaan ditolak dengan pesan setelah commit (hasil transaksi eksplisit, tanpa exception di dalam transaksi). Lihat `BOOKING_EXPIRATION.md`.

Tidak ada pembatalan otomatis atas booking lain yang tidak terkait (mis. booking Diajukan untuk eksemplar yang kemudian dipinjam tetap Diajukan; persetujuan berikutnya akan ditolak karena eksemplar tidak tersedia).

## 3. Kebijakan yang dipertahankan / masih terbuka

- Kuota booking, antrean, beberapa anggota mengajukan eksemplar yang sama selama masih Diajukan, pengajuan ganda, booking eksemplar Dipinjam: **perilaku teruji dipertahankan** (lihat tabel di atas; TBD-06).
- Denda, perpanjangan, durasi pinjam bawaan: **tidak ada** (TBD-05). Tanggal Jatuh Tempo diisi Admin.
- Status anggota tidak memengaruhi pengembalian (disetujui Batch 2).

## 4. Frontend

- `composables/useTransaksiServer.js`: `router.post` sebagai method, `memproses` mencegah kirim ganda (tombol konfirmasi `:loading`), sukses → kode dari flash `transaksi` → bukti/pesan dibangun dari data MySQL terbaru.
- Halaman diaktifkan tanpa desain ulang: Peminjaman Buku, Pengembalian Buku, Verifikasi Booking, Realisasi Booking, Ajukan Booking, Status Booking. Teks "mode pratinjau" dihapus.
- Perubahan tampilan minimum: status `Kedaluwarsa` (badge netral, ikon jam pasir; tab & rekap mengikutinya), **Batas Pengambilan** (WIB, `formatWaktu`) di detail Status Booking, Verifikasi, dan formulir Realisasi; pesan penolakan realisasi tingkat halaman (booking Kedaluwarsa keluar dari daftar Disetujui).
- `AKSI_TERTUNDA` kini kosong.

## 5. Data baca, dashboard, laporan

- `DataPerpustakaan` menambah `batasPengambilan` dan `waktuKedaluwarsa` (ISO UTC "Z") pada booking; tampilan WIB di browser.
- Dashboard dan laporan membaca koleksi MySQL yang sama (eager loading, tanpa N+1) dan menghitung ringkasan di browser seperti Phase 8B; rekap booking otomatis memuat Kedaluwarsa. Agregat SQL khusus dashboard **tidak** ditambahkan (lihat keterbatasan di `TRANSACTION_TEST_REPORT.md`).
- Filter laporan memakai batas WIB→UTC yang ada (`WaktuPerpustakaan::awalHariLokal`), bukan `DATE(kolom)`.

## 6. Audit

`peminjaman.dibuat`, `pengembalian.dibuat`, `booking.diajukan`, `booking.disetujui`, `booking.ditolak`, `booking.dibatalkan`, `booking.direalisasikan`, `booking.kedaluwarsa`. Aktor = akun sesi; `booking.kedaluwarsa` beraktor **NULL** (kejadian sistem) dengan `metadata.pemicu` (`penjadwal`, `realisasi`, `pembatalan`, `persetujuan`, `peminjaman`, `pengajuan`, `penonaktifan`). Metadata hanya kode bisnis, tanggal jatuh tempo, dan penanda; tanpa kata sandi, hash, token, kontak, atau teks alasan penolakan.

## 7. Aksi yang masih dinonaktifkan

Tidak ada aksi tulis yang tertunda. Hapus data (anggota, buku, eksemplar, transaksi) sengaja tidak tersedia.
