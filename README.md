# SIM Perpustakaan

Sistem Informasi Manajemen Perpustakaan — **satu proyek Laravel terintegrasi**. Vue 3 berjalan di dalam Laravel melalui Inertia.js dan Vite (tidak ada proyek frontend/backend terpisah).

> **Status: Phase 1 — layout bersama, navigasi, halaman Masuk, dan komponen UI.**
> Prototipe UI statis dengan **dua peran: Admin dan Anggota**. Tidak ada database, API, maupun autentikasi backend.
> Halaman Masuk hanya **pratinjau peran**, bukan sistem login yang aman. Isi halaman modul dibangun pada Phase 2–5.

## Versi terpasang

| Komponen | Versi |
| --- | --- |
| PHP | 8.4.27 (Homebrew `php@8.4`) |
| Laravel Framework | 13.40.0 (skeleton `laravel/laravel` v13.11.0) |
| inertiajs/inertia-laravel | 3.7.0 (`^3.0`) |
| @inertiajs/vue3 / @inertiajs/vite | 3.11.0 |
| Vue | 3.5.45 |
| @vitejs/plugin-vue | 6.0.10 |
| Vite / laravel-vite-plugin | 8.3.6 / 3.2.1 |
| Bootstrap | 5.3.8 (CSS saja, tanpa JS Bootstrap) |
| Bootstrap Icons | 1.13.2 (dibundel lokal) |
| Font Inter | @fontsource-variable/inter 5.3.0 (dibundel lokal) |
| Composer / Node / npm | 2.10.3 / 20.19.2 / 10.8.2 |

Tidak memakai Tailwind, starter kit, TypeScript, maupun Axios (Inertia v3 memakai klien HTTP bawaan).

## Prasyarat

- PHP **≥ 8.4.1**. Laravel 13 sendiri hanya butuh `^8.3`, tetapi `composer.lock` mengunci Symfony 8.1, dan Symfony 8.1 mensyaratkan `>=8.4.1`. PHP 8.2/8.3 akan gagal di `vendor/composer/platform_check.php`.
- Composer 2, Node.js **≥ 20.19** dan npm.

### Catatan PHP di mesin ini

`~/.zshrc` menaruh PHP **8.2** di depan `PATH`, sehingga `php artisan ...` akan gagal dengan error:

```text
Your Composer dependencies require a PHP version ">= 8.4.1". You are running 8.2.28.
```

PHP 8.4 sudah terinstal via Homebrew. Pilih salah satu cara:

```bash
# A. Pakai alias yang sudah ada di ~/.zshrc (hanya untuk artisan)
php84 artisan serve

# B. Aktifkan PHP 8.4 untuk terminal ini (artisan + composer)
export PATH="/opt/homebrew/opt/php@8.4/bin:$PATH"
php -v   # harus menampilkan PHP 8.4.x
```

`composer` memakai `php` yang ada di `PATH`, jadi untuk `composer install/require/update` gunakan cara B.

## Instalasi

```bash
export PATH="/opt/homebrew/opt/php@8.4/bin:$PATH"
composer install
cp -n .env.example .env
php artisan key:generate
npm install
npm run build
```

**Jangan** menjalankan `php artisan migrate`: tahap ini tidak memakai database. Script `composer setup` sudah disesuaikan agar tidak menjalankan migrasi.

## Menjalankan aplikasi

Gunakan dua terminal:

```bash
# Terminal 1 — Laravel
export PATH="/opt/homebrew/opt/php@8.4/bin:$PATH"
php artisan serve            # http://localhost:8000

# Terminal 2 — Vite (HMR)
npm run dev                  # http://localhost:5173 (dipakai otomatis oleh Laravel)
```

Tanpa `npm run dev`, Laravel memakai aset hasil `npm run build` di `public/build`.

> Perintah gabungan `composer run dev` / `php artisan dev` memakai paket `@laravel/multiplex` yang mensyaratkan **Node ≥ 22.13**. Dengan Node 20.19 paket tersebut tidak terpasang, jadi gunakan dua terminal di atas, atau upgrade Node terlebih dahulu.

## URL

Basis: <http://localhost:8000>. Semua halaman dapat dibuka langsung tanpa login.

| Peran | URL | Halaman |
| --- | --- | --- |
| — | `/` | Redirect ke `/login` |
| — | `/login` | Masuk (pratinjau peran) |
| Admin | `/demo/admin/dashboard` | Dashboard |
| Admin | `/demo/admin/anggota` | Data Anggota |
| Admin | `/demo/admin/anggota/tambah` | Tambah Anggota |
| Admin | `/demo/admin/buku` | Data Buku & Eksemplar |
| Admin | `/demo/admin/buku/tambah` | Tambah Buku |
| Admin | `/demo/admin/peminjaman` | Peminjaman Buku |
| Admin | `/demo/admin/pengembalian` | Pengembalian Buku |
| Admin | `/demo/admin/booking/verifikasi` | Verifikasi Booking |
| Admin | `/demo/admin/booking/realisasi` | Realisasi Booking (dibuka dari alur verifikasi) |
| Admin | `/demo/admin/laporan` | Laporan |
| Anggota | `/demo/anggota/dashboard` | Dashboard |
| Anggota | `/demo/anggota/cari-buku` | Cari Buku |
| Anggota | `/demo/anggota/booking/ajukan` | Ajukan Booking |
| Anggota | `/demo/anggota/booking/status` | Status Booking |
| Anggota | `/demo/anggota/history` | History Peminjaman |
| Pengembang | `/demo/komponen` | Katalog komponen UI (tidak ditautkan dari navigasi) |
| Teknis | `/hello/echo` | Smoke test Laravel → Inertia → Vue (Task 00) |

### Akun pratinjau (bukan autentikasi)

| Identitas Pengguna | Peran |
| --- | --- |
| `admin` | Admin |
| `AG-001` | Anggota |

Kata sandi apa pun yang tidak kosong diterima; identitas lain ditolak dengan pesan generik. Peran hanya dibaca dari URL `/demo/{peran}/...`; **tidak ada** sesi, verifikasi kata sandi, atau otorisasi server.

> SRS v1.1 membedakan Admin dan Petugas. Prototipe memakai dua peran (Admin dan Anggota); detail dan catatan penyelarasan ada di `docs/KEPUTUSAN_DESAIN.md`.

Route bawaan framework yang tetap ada (bukan halaman aplikasi): `GET /up` (health check) dan `storage/{path}` (disk lokal Laravel). Route DevTools Inertia dimatikan dengan `INERTIA_DEVTOOLS_ENABLED=false`.

## Konfigurasi tanpa database

`.env.example` diatur agar aplikasi boot tanpa database:

```dotenv
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
INERTIA_DEVTOOLS_ENABLED=false
```

`DB_CONNECTION=sqlite` tetap ada sebagai default framework, tetapi tidak ada file database yang dibuat dan tidak ada kode yang melakukan query.

## Struktur integrasi

```text
app/Support/NavigasiDemo.php     # pusat daftar halaman, rute, menu sidebar, dan peran
app/Http/Middleware/HandleInertiaRequests.php  # shared prop `demo` (peran, menu, rute, meta halaman)
routes/web.php                   # rute dibangkitkan dari NavigasiDemo
resources/
├── css/app.css                  # design tokens + komponen (di atas Bootstrap 5.3)
├── js/
│   ├── app.js                   # entry Inertia + font Inter, Bootstrap, Bootstrap Icons
│   ├── config/demo.js           # akun pratinjau & tanggal demo tetap (2026-11-07)
│   ├── composables/useDemo.js   # akses peran, akun, menu, peta rute
│   ├── utils/status.js          # kosakata status resmi + tampilan badge
│   ├── utils/format.js          # format tanggal Indonesia (WIB), inisial
│   ├── Components/              # AppSidebar, AppHeader, PageHeader, BaseButton, BaseInput,
│   │                            # BaseSelect, BaseTable, StatusBadge, FormFeedback,
│   │                            # EmptyState, ConfirmationModal, BrandMark
│   ├── Layouts/                 # AppShell, AdminLayout, AnggotaLayout
│   └── Pages/                   # Auth/Login, Sistem/HalamanDisiapkan, Sistem/PratinjauKomponen, HelloEcho
└── views/app.blade.php          # root template: @vite, <x-inertia::head>, <x-inertia::app>
```

Menambah halaman: daftarkan di `NavigasiDemo::halamanAdmin()` / `halamanAnggota()` (uri, judul, deskripsi, induk breadcrumb, komponen). Rute, menu aktif, dan breadcrumb mengikuti otomatis.

## Pengujian

```bash
export PATH="/opt/homebrew/opt/php@8.4/bin:$PATH"
php artisan route:list
php artisan test             # rute, menu, breadcrumb, batas peran, tanpa query database
npm run build
```
