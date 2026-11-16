# SIM Perpustakaan

Sistem Informasi Manajemen Perpustakaan — **Aplikasi Web Terintegrasi Dinamis (Database-Backed)**.
Dibangun menggunakan Laravel 13, MySQL 8.4, Vue 3, Inertia.js v3, dan Bootstrap 5.3.

> **Status Sistem: Batch 3 Finalized (Aplikasi Dinamis Selesai)**
> Seluruh fitur aplikasi telah terhubung secara nyata ke **MySQL 8.4**: Autentikasi aman berbasis sesi server, CRUD Anggota, CRUD Buku & Eksemplar atomik, Manajemen Transaksi Peminjaman, Pengembalian, dan Booking, Kedaluwarsa Booking 48 jam terjadwal, Dashboard Agregat SQL, serta Laporan Dinamis terintegrasi.
> Seluruh kalkulasi data dihitung di server (MySQL), bebas dari manipulasi atau mock di memori browser.

---

## 1. Versi & Spesifikasi Lingkungan

| Komponen | Versi | Catatan |
| --- | --- | --- |
| **PHP** | 8.4.27 (Homebrew `php@8.4`) | Minimum `>= 8.4.1` (persyaratan Symfony 8.1 / Laravel 13) |
| **Basis Data** | MySQL 8.4 LTS | Engine InnoDB, Collation `utf8mb4_0900_ai_ci`, Zona UTC |
| **Laravel** | 13.40.0 | Inertia v3, Session, Form Requests, Gates, Console Scheduler |
| **Frontend** | Vue 3.5.45 & Inertia 3.11.0 | Single-Page Application (SPA) berbasis server routing |
| **Aset & Build** | Vite 8.3.6 | Bootstrap 5.3.8 (CSS-only), Bootstrap Icons, Inter Variable |
| **Node.js** | ≥ 20.19.2 | Node Test Runner (`node --test`), Chrome DevTools Protocol |

---

## 2. Fitur Utama Sistem

### 2.1 Autentikasi & Keamanan (Phase 8)
- Login aman berbasis sesi server dengan proteksi CSRF otomatis (`XSRF-TOKEN`).
- Role-based authorization: **Admin** dan **Anggota** dipisahkan secara tegas melalui middleware `peran:admin` dan `peran:anggota` (HTTP 403 Forbidden).
- Rate limiting / login throttling untuk mencegah serangan brute force.
- Password hashing modern (Argon2id/Bcrypt) tanpa plaintext atau kebocoran hash di props Inertia.
- Alur wajib ganti kata sandi (`must_change_password`) untuk akun anggota baru atau setelah reset kata sandi oleh Admin.
- Pengendalian sesi otomatis: Anggota nonaktif langsung diblokir (`akun.aktif`).

### 2.2 Master Data CRUD (Phase 9A & 9B)
- **Data Anggota**: Tambah, ubah, dan kelola status (Aktif/Nonaktif). Pilihan membuat akses login anggota secara langsung atau mereset kata sandi.
- **Data Buku & Eksemplar**: Pembuatan buku baru bersama eksemplar awal secara atomik di dalam transaksi MySQL. Kode buku permanen, status eksemplar awal `Tersedia`.
- **Pencarian Katalog Server-Side**: Pencarian parameterized LIKE (judul, kode buku, kategori) dengan pagination 20 baris per halaman untuk efisiensi memori.

### 2.3 Manajemen Transaksi Perpustakaan (Batch 2)
- **Peminjaman Langsung**: Admin mencatat peminjaman anggota aktif, eksemplar berubah menjadi `Dipinjam`, batas jatuh tempo tanggal valid.
- **Pengembalian Buku**: Pengembalian valid mengubah pinjaman menjadi `Selesai` dan melepas eksemplar menjadi `Tersedia`. Anggota nonaktif tetap diizinkan mengembalikan buku. Pencegahan double return melalui constraint database `UNIQUE (loan_id)`.
- **Siklus Booking Buku**:
  - Anggota mengajukan booking untuk eksemplar `Tersedia` (status `Diajukan`, eksemplar belum dikunci).
  - Admin menyetujui booking (status `Disetujui`, eksemplar menjadi `Dipesan`, deadline pengambilan ditetapkan tepat 48 jam).
  - Admin menolak booking dengan alasan wajib (status `Ditolak`, eksemplar tetap `Tersedia`).
  - Realisasi booking menjadi peminjaman resmi saat anggota mengambil buku fisik.
  - Anggota dapat membatalkan booking miliknya sendiri selama berstatus `Diajukan` atau `Disetujui` sebelum lewat batas waktu.
- **Konkurensi & Locking**: Kunci baris pesimistik (`lockForUpdate`) dengan urutan penguncian tabel seragam (`members` $\rightarrow$ `bookings` $\rightarrow$ `book_copies` $\rightarrow$ `loans` $\rightarrow$ `returns`) dan retry otomatis saat terjadi deadlock.

### 2.4 Kedaluwarsa Booking Otomatis (Batch 2 & 3)
- Booking yang disetujui memiliki batas pengambilan tepat **48 jam** sejak persetujuan.
- Command scheduled `php artisan bookings:expire` berjalan tiap 5 menit:
  - Idempoten, per batch, mencatat audit log sistem (`actor_id = NULL`, `pemicu = 'penjadwal'`).
  - Melepas reservasi eksemplar menjadi `Tersedia` dan mengubah status booking menjadi `Kedaluwarsa`.
  - Mencegah eksekusi tumpang tindih (`withoutOverlapping(10)`).

### 2.5 Dashboard Berbasis Agregasi SQL (Batch 3)
- **Admin Dashboard**: Dihitung langsung oleh MySQL menggunakan kueri agregat efisien (`COUNT`, conditional `SUM`, `GROUP BY`) tanpa memuat tabel penuh. Menyajikan daftar 10 peminjaman aktif terdekat (dioptimalkan oleh indeks komposit `idx_loans_status_due`) dan 10 booking berjalan.
- **Member Dashboard**: Statistik personal (`bookingAktif`, `peminjamanAktif`, `riwayatSelesai`) diisolasi mutlak dari sesi pengguna server (`$request->user()->member`). Tidak mempercayai input parameter kepemilikan dari browser.

### 2.6 Laporan Dinamis (Batch 3)
- Mendukung 4 jenis laporan resmi: **Daftar Buku**, **Daftar Anggota**, **Peminjaman**, dan **Pengembalian**.
- Filter rentang waktu mengonversi batas tanggal kalender WIB ke interval setengah-terbuka UTC tanpa `DATE(kolom)` sehingga indeks waktu tetap terpakai optimal.
- Validasi rentang tanggal di server (menolak rentang tanggal terbalik dengan galat per-field).
- Mendukung cetak langsung via browser (`window.print()`) dengan penataan CSS cetak (`no-print`).

---

## 3. Instalasi & Setup Lokal

### 3.1 Prasyarat
Pastikan PHP 8.4 dan MySQL 8.4 aktif di lingkungan Anda:
```bash
export PATH="/opt/homebrew/opt/php@8.4/bin:$PATH"
php -v # Memastikan PHP 8.4.x
```

### 3.2 Langkah Instalasi
```bash
# 1. Clone & masuk direktori
cd SIM-Perpustakaan

# 2. Install dependensi
composer install
npm install

# 3. Konfigurasi Environment
cp -n .env.example .env
php artisan key:generate

# 4. Konfigurasi Database di .env
# DB_DATABASE=sim_perpustakaan_db
# DB_USERNAME=sim_perpus_app
# DB_PASSWORD=...
# DB_TEST_DATABASE=sim_perpustakaan_test
# DB_TEST_USERNAME=sim_perpus_test
# DB_TEST_PASSWORD=...

# 5. Jalankan migrasi dan seeder development
php artisan migrate
php artisan db:seed --class=DemoPerpustakaanSeeder

# 6. Kompilasi aset frontend
npm run build
```

---

## 4. Menjalankan Aplikasi

Jalankan aplikasi di terminal lokal:

```bash
export PATH="/opt/homebrew/opt/php@8.4/bin:$PATH"

# Jalankan server aplikasi
php artisan serve # http://127.0.0.1:8000

# Jalankan scheduler kedaluwarsa di latar belakang (terminal terpisah)
php artisan schedule:work
```

### Kredensial Bawaan Seeder Development

- **Admin**:
  - Username: `admin`
  - Password: Nilai dari `SEED_ADMIN_PASSWORD` pada file `.env`
- **Anggota Demo**:
  - Akun anggota dibuat oleh Admin melalui menu Data Anggota $\rightarrow$ Tambah/Ubah Anggota dengan fitur *Akses Login Anggota*.
  - Anggota baru wajib mengganti kata sandi awal saat pertama kali login.

---

## 5. Pengujian & Jaminan Mutu (Quality Assurance)

Semua pengujian database otomatis berjalan secara terisolasi pada database `sim_perpustakaan_test`:

```bash
export PATH="/opt/homebrew/opt/php@8.4/bin:$PATH"

# 1. Menjalankan PHPUnit (Backend, Database, Transaksi, Konkurensi)
php artisan test
# Hasil: 270 passed (3.466 assertions)

# 2. Menjalankan Node.js Test Runner (Frontend, Validasi, State)
npm run test:data
# Hasil: 245 passed

# 3. Menjalankan End-to-End Browser QA (Headless Chrome via CDP)
node --experimental-websocket scripts/qa-browser-journey.mjs
# Hasil: 23/23 skenario perjalanan Admin & Anggota lulus

# 4. Audit Standar Kode & Style
vendor/bin/pint --test
npm run build
```

---

## 6. Struktur Dokumentasi Proyek

Dokumentasi teknis lengkap tersedia pada direktori `docs/`:

- `docs/KEPUTUSAN_DESAIN.md`: Catatan arsitektur dan resolusi keputusan desain sistem.
- `docs/database/`: Spesifikasi skema basis data MySQL 8.4 Phase 7A.
- `docs/auth/`: Spesifikasi sistem autentikasi dan keamanan Phase 8.
- `docs/phase9/`: Dokumentasi CRUD Anggota dan Buku/Eksemplar.
- `docs/phase10/`: Dokumentasi manajemen transaksi, konkurensi locking, dan kedaluwarsa booking.
- `docs/phase12/`:
  - `BATCH_3_DASHBOARD_REPORTING.md`: Arsitektur dashboard agregat SQL dan pelaporan.
  - `PERFORMANCE_AUDIT.md`: Audit performa, indexing, dan EXPLAIN plan.
  - `REPORTING_SPECIFICATION.md`: Spesifikasi batas waktu dan aturan pelaporan.
- `docs/phase13/`:
  - `FINAL_SYSTEM_QA.md`: Laporan komprehensif seluruh pengujian QA.
  - `FINAL_SECURITY_AUDIT.md`: Audit keamanan aplikasi, proteksi data, dan rekomendasi produksi.
