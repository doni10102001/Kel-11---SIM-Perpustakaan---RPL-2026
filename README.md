# Sistem Informasi Manajemen Perpustakaan (SIM Perpustakaan)

**Tugas Besar Rekayasa Perangkat Lunak (RPL) — Kelompok 11**
Aplikasi Web Terintegrasi Berbasis Laravel 13, MySQL 8.4, Inertia.js, Vue 3, dan Bootstrap 5.3.

**Anggota Kelompok 11:** Doni Suhendar (202243501242) · Abdurrahman Wahid (202343500460) · Sarah Tirta Kencana (202343500443)

> 📦 **[Dokumentasi Pengumpulan Tugas 10](docs/TUGAS-10-FINAL/README.md)** — paket 10 deliverable (SRS, UML, desain UI/UX, pengujian, hasil sistem, dan PPT presentasi).

---

## 1. Gambaran Umum Sistem

**SIM Perpustakaan** adalah aplikasi berbasis web yang dirancang untuk mengotomatisasi dan menyederhanakan proses operasional perpustakaan secara menyeluruh. Sistem ini mengintegrasikan pengelolaan data master anggota dan katalog buku, siklus transaksi sirkulasi (peminjaman dan pengembalian), sistem reservasi (booking) buku dengan pembatasan waktu otomatis, visualisasi analitik melalui dashboard terpadu, serta modul pelaporan dinamis yang dapat dicetak.

Seluruh proses komputasi data, validasi hak akses, dan kalkulasi statistik dijalankan secara aman pada sisi server dengan persistensi penuh pada mesin basis data **MySQL 8.4 (InnoDB)**.

---

## 2. Arsitektur & Tumpukan Teknologi

Sistem dibangun menggunakan pendekatan arsitektur monolit modern (*Modern Monolith*) dengan pemisahan peran yang tegas antara backend API/layanan dan antarmuka Single-Page Application (SPA) berbasis Inertia.js:

| Lapisan | Teknologi | Versi | Peran & Deskripsi |
| --- | --- | --- | --- |
| **Backend Framework** | Laravel | 13.x | Routing, Middleware, Form Requests, Services, Gates, Eloquent ORM, dan Console Scheduler |
| **Basis Data** | MySQL LTS | 8.4.x | Mesin penyimpanan InnoDB, integritas referensial (Foreign Keys), transaksi ACID, dan penguncian baris pesimistik |
| **Frontend Framework** | Vue.js | 3.5.x | Composition API (`<script setup>`) untuk rendering antarmuka reaktif |
| **Adapter SPA** | Inertia.js | 3.x | Menghubungkan controller server Laravel langsung dengan komponen Vue tanpa REST API terpisah |
| **Desain Antarmuka** | Bootstrap | 5.3.x | Framework CSS responsif (tanpa ketergantungan JavaScript Bootstrap eksternal) |
| **Ikon & Tipografi** | Bootstrap Icons & Inter Variable | 1.13.x / 5.3.x | Aset dibundel lokal via Vite untuk performa tinggi tanpa dependensi CDN luar |
| **Build Tooling** | Vite | 8.x | Kompilator modul frontend cepat dan manajemen aset produksi |
| **Bahasa Pemrograman** | PHP & JavaScript | 8.4.x / Node.js 20.x | Runtime eksekusi backend dan tooling pengujian |

---

## 3. Fitur Utama Sistem

### 3.1 Autentikasi & Manajemen Pengguna
- **Pemisahan Peran Tegas**: Mendukung dua peran pengguna utama, yaitu **Admin Perpustakaan** (mengelola seluruh operasional) dan **Anggota** (melihat katalog, riwayat, dan melakukan reservasi). Akses lintas peran dicegah secara ketat oleh middleware otorisasi server (HTTP 403).
- **Keamanan Sesi & CSRF**: Sesi tersimpan aman pada tabel basis data (`sessions`) dengan perlindungan token CSRF ganda (`XSRF-TOKEN`).
- **Penyimpanan Kredensial Aman**: Seluruh kata sandi di-hash menggunakan algoritma modern bergaram (Argon2id/Bcrypt). Hash tidak pernah diekspos ke respon antarmuka pengguna.
- **Proteksi Brute-Force**: Dilengkapi *rate limiter* otomatis pada endpoint masuk untuk menangkal serangan tebak kredensial.
- **Alur Wajib Ganti Kata Sandi**: Anggota baru atau akun yang kata sandinya direset oleh Admin diwajibkan mengganti kata sandi awal pada saat login pertama kali.
- **Pengendalian Akun Terpadu**: Penonaktifan status anggota secara otomatis memutus sesi aktif pengguna yang bersangkutan.

### 3.2 Pengelolaan Data Master
- **Master Anggota**: Pencatatan data anggota dengan kode unik, nama, kontak, dan status keanggotaan (Aktif/Nonaktif). Admin dapat membuatkan akun login secara langsung maupun mereset kata sandi anggota.
- **Master Buku & Eksemplar Fisik**: Pengelolaan data bibliografi buku (judul, pengarang, penerbit, tahun, kategori) beserta eksemplar fisik buku dengan kode unik eksemplar (`EK-xxx`). Pembuatan buku dan eksemplar awal dilakukan secara atomik di dalam transaksi database.
- **Pencarian Katalog Cepat**: Pencarian judul, kode buku, atau kategori dengan pagination server-side (20 buku per halaman) untuk menjaga efisiensi transfer data.

### 3.3 Sirkulasi Peminjaman & Pengembalian
- **Peminjaman Langsung**: Admin mencatat peminjaman buku untuk anggota aktif dengan penentuan batas tanggal jatuh tempo. Status eksemplar fisik otomatis terkunci menjadi `Dipinjam`.
- **Pengembalian Buku**: Pencatatan pengembalian buku yang mengubah status transaksi menjadi `Selesai` dan secara otomatis memulihkan status eksemplar menjadi `Tersedia`. Anggota nonaktif tetap dapat mengembalikan buku pinjamannya.
- **Pencegahan Pengembalian Ganda**: Dijamin oleh indeks unik database `UNIQUE(loan_id)` pada tabel pengembalian.

### 3.4 Sistem Reservasi (Booking) Buku
- **Pengajuan Booking**: Anggota dapat memesan eksemplar buku yang berstatus `Tersedia`.
- **Verifikasi oleh Admin**: Admin dapat menyetujui pemesanan (mengubah eksemplar menjadi `Dipesan` dan menetapkan tenggat pengambilan 48 jam) atau menolak pemesanan dengan menyertakan alasan penolakan.
- **Realisasi Peminjaman**: Saat anggota mengambil buku fisik di perpustakaan, Admin merealisasikan booking menjadi transaksi peminjaman resmi.
- **Pembatalan Mandiri**: Anggota berhak membatalkan booking miliknya sendiri selama statusnya masih diajukan atau disetujui sebelum lewat tenggat waktu.

### 3.5 Otomasi Penjadwal Kedaluwarsa Booking
- Sistem dilengkapi command konsol terjadwal `php artisan bookings:expire` yang berjalan periodik setiap 5 menit.
- Booking yang telah disetujui tetapi tidak diambil oleh anggota dalam batas waktu **48 jam** akan otomatis dialihkan statusnya menjadi `Kedaluwarsa`.
- Eksemplar buku yang dipesan akan otomatis dilepaskan kembali menjadi `Tersedia` agar dapat dipinjam oleh pemustaka lain.
- Seluruh tindakan sistem ini dicatat secara akuntabel pada tabel `audit_logs`.

### 3.6 Dashboard Analitik & Statistik
- **Dashboard Admin**: Menyajikan ringkasan metrik perpustakaan secara *real-time* (total anggota, buku, eksemplar tersedia/dipesan/dipinjam, transaksi aktif/selesai, dan status booking) menggunakan agregasi SQL efisien (`COUNT`, conditional `SUM`, `GROUP BY`) tanpa memuat seluruh tabel ke memori browser.
- **Dashboard Anggota**: Statistik personal terisolasi (pinjaman aktif anggota, riwayat selesai, status booking) yang diekstraksi eksklusif dari relasi akun sesi server untuk mencegah manipulasi parameter URL.

### 3.7 Modul Pelaporan Sirkulasi
- Menyediakan 4 kategori laporan utama: **Daftar Buku**, **Daftar Anggota**, **Laporan Peminjaman**, dan **Laporan Pengembalian**.
- Dilengkapi filter rentang kalender tanggal WIB yang dikonversi secara akurat ke interval setengah-terbuka UTC pada kueri basis data.
- Dilengkapi tabel pratinjau data dan dukungan cetak langsung peramban (*print-ready CSS*) yang menghilangkan elemen navigasi saat dokumen dicetak.

---

## 4. Panduan Instalasi & Menjalankan Sistem

### 4.1 Prasyarat Lingkungan
Pastikan perangkat Anda telah terpasang:
- **PHP** versi $\ge$ 8.4 (disarankan PHP 8.4.x) beserta ekstensi `pdo_mysql`, `mbstring`, `openssl`.
- **MySQL Server** versi $\ge$ 8.4.
- **Composer** versi $\ge$ 2.7.
- **Node.js** versi $\ge$ 20.x dan **npm**.

### 4.2 Langkah-Langkah Instalasi

```bash
# 1. Kloning repositori proyek dan masuk ke direktori
git clone <URL_REPOSITORI>
cd SIM-Perpustakaan

# 2. Pasang dependensi PHP dan Node.js
composer install
npm install

# 3. Siapkan file konfigurasi environment
cp .env.example .env

# 4. Generate Application Encryption Key
php artisan key:generate

# 5. Konfigurasi kredensial basis data pada file .env
# Buka file .env dan sesuaikan bagian berikut:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=sim_perpustakaan_db
# DB_USERNAME=root
# DB_PASSWORD=kata_sandi_mysql_anda

# 6. Jalankan migrasi tabel basis data dan pengisian data awal (seeder)
php artisan migrate
php artisan db:seed --class=DemoPerpustakaanSeeder

# 7. Kompilasi aset antarmuka frontend
npm run build
```

---

## 5. Menjalankan Aplikasi

Jalankan server pengembangan Laravel pada terminal:

```bash
php artisan serve
```
Aplikasi dapat diakses melalui peramban pada alamat: **http://127.0.0.1:8000**

Untuk menjalankan otomasi kedaluwarsa booking secara berkala di latar belakang, buka terminal terpisah dan jalankan:
```bash
php artisan schedule:work
```

---

## 6. Akun Pengguna Bawaan (Demo)

Sistem menyediakan data awal untuk memudahkan pengujian fungsionalitas:

1. **Akun Administrator**:
   - **Username**: `admin`
   - **Password**: Sesuai nilai `SEED_ADMIN_PASSWORD` pada file `.env` (atau kata sandi yang dikonfigurasi saat seeding awal).
   - **Akses**: Panel Admin, Dashboard Analitik, Manajemen Master Data, Transaksi Sirkulasi, dan Pelaporan.

2. **Akun Anggota**:
   - Akun anggota dapat didaftarkan atau dibuatkan akses login oleh Admin melalui menu **Data Anggota** $\rightarrow$ **Tambah Anggota** (atau **Ubah Anggota**) dengan mengaktifkan fitur *Akses Login Anggota*.
   - Anggota yang baru dibuatkan akun dapat masuk menggunakan username dan kata sandi yang diberikan, lalu akan diarahkan untuk melakukan penggantian kata sandi baru.

---

## 7. Pengujian Sistem & Jaminan Mutu (Quality Assurance)

Aplikasi telah melalui serangkaian pengujian otomatis komprehensif pada berbagai lapisan arsitektur:

### 7.1 Pengujian Backend (PHPUnit)
Menguji logika bisnis, model relasional, transaksi atomik, penanganan konkurensi locking, penjadwal otomatis, dan kontrol akses:
```bash
php artisan test
```
*Hasil Pengujian*: **270 skenario uji lulus** (3.466 assertions) tanpa kegagalan.

### 7.2 Pengujian Logika Frontend (Node.js Test Runner)
Menguji validasi input, konsistensi data transaksi, status alur kerja, dan isolasi privasi:
```bash
npm run test:data
```
*Hasil Pengujian*: **245 pengujian lulus** (46 test suites) tanpa kegagalan.

### 7.3 Pengujian Alur Perjalanan Pengguna (Browser QA, Chrome headless)
Menjalankan perjalanan Admin dan Anggota (login, tambah anggota/buku, peminjaman, verifikasi booking, laporan, pengajuan booking, histori, otorisasi) terhadap database uji. Kredensial MySQL uji **wajib** diberikan lewat environment; skrip tidak memiliki kata sandi bawaan:
```zsh
read -rs "DB_TEST_PASSWORD?Kata sandi MySQL uji: " && export DB_TEST_PASSWORD
DB_TEST_USERNAME=sim_perpus_test node --experimental-websocket scripts/qa-browser-journey.mjs
unset DB_TEST_PASSWORD
```
*Hasil Pengujian*: **23/23 cek tercatat PASS** (24 Nov 2026). Hanya 7 cek memiliki assertion eksplisit, 8 cek tidak memeriksa hasil, dan langkah Pengembalian serta Pembatalan bersifat kondisional dan tidak tereksekusi. Verifikasi alur lengkap (booking → realisasi → pengembalian) dengan pemeriksaan MySQL di setiap mutasi ada pada E2E demo 22/22: lihat [laporan pengujian Tugas 10](docs/TUGAS-10-FINAL/08-SOFTWARE-TESTING/SOFTWARE_TESTING_REPORT.md).

---

## 8. Panduan Dokumentasi Teknis

Dokumentasi rancangan dan laporan teknis lengkap tersimpan terstruktur pada direktori `docs/`:

| Direktori / Berkas | Isi & Ruang Lingkup |
| --- | --- |
| [`docs/KEPUTUSAN_DESAIN.md`](docs/KEPUTUSAN_DESAIN.md) | Catatan keputusan arsitektur, batasan sistem, dan aturan bisnis terintegrasi |
| [`docs/database/`](docs/database/) | Desain skema basis data MySQL 8.4, diagram relasi, dan berkas DDL SQL |
| [`docs/fitur/`](docs/fitur/) | Spesifikasi modul CRUD Anggota, Buku & Eksemplar, Transaksi Sirkulasi, serta Locking & Konkurensi |
| [`docs/keamanan/`](docs/keamanan/) | Model autentikasi, otorisasi RBAC, manajemen kredensial, dan audit keamanan sistem |
| [`docs/kinerja/`](docs/kinerja/) | Analisis kinerja kueri (EXPLAIN plan), optimasi indeks komposit, dan modul pelaporan dinamis |
| [`docs/pengujian/`](docs/pengujian/) | Laporan pengujian kualitas sistem (QA), hasil uji transaksi, dan checklist kepatuhan |
| [`docs/analisis/`](docs/analisis/) | Matriks keterlacakan kebutuhan (SRS) dan kepatuhan diagram UML |
| [`docs/uml/`](docs/uml/) | Berkas gambar diagram rancangan UML lengkap (Use Case, Activity, Sequence, Class Diagram) |
| [`docs/screenshots/`](docs/screenshots/) | Tangkapan layar seluruh halaman antarmuka pengguna sistem |
| [`docs/requirements/`](docs/requirements/) | Dokumen spesifikasi kebutuhan perangkat lunak resmi (*Software Requirements Specification* - SRS) |

---
**Kelompok 11 — Rekayasa Perangkat Lunak 2026**
