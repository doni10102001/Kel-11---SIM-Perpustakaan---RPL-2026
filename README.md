# SIM Perpustakaan

Sistem Informasi Manajemen Perpustakaan — **satu proyek Laravel terintegrasi**. Vue 3 berjalan di dalam Laravel melalui Inertia.js dan Vite (tidak ada proyek frontend/backend terpisah).

> **Status: Phase 5 — seluruh halaman Admin dan Anggota selesai** (12 halaman Admin + 5 halaman Anggota + Masuk). Simulasi transaksi berjalan di browser dan dibagi antarperan dalam satu sesi; muat ulang mengembalikan data awal.
> Prototipe UI statis dengan **dua peran: Admin dan Anggota**. Tidak ada database, API, maupun autentikasi backend.
> Halaman Masuk hanya **pratinjau peran**, bukan sistem login yang aman. Isi halaman modul dibangun pada Phase 2–5.
> **Penambahan scope setelah Final Audit:** Admin dapat membuat akses login opsional untuk Anggota (Tambah/Ubah Anggota). Kredensial ini hanya simulasi di memori browser — lihat [Akses login Anggota](#akses-login-anggota-simulasi).

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
| Admin | `/demo/admin/anggota/{kodeAnggota}/ubah` | Ubah Anggota (mis. `AG-001`) |
| Admin | `/demo/admin/buku` | Data Buku & Eksemplar |
| Admin | `/demo/admin/buku/tambah` | Tambah Buku |
| Admin | `/demo/admin/buku/{kodeBuku}/ubah` | Ubah Buku & tambah eksemplar (mis. `BK-001`) |
| Admin | `/demo/admin/peminjaman` | Peminjaman Buku |
| Admin | `/demo/admin/pengembalian` | Pengembalian Buku |
| Admin | `/demo/admin/booking/verifikasi` | Verifikasi Booking |
| Admin | `/demo/admin/booking/realisasi` | Realisasi Booking (dibuka dari alur verifikasi; `?booking=BP-001` memilih booking) |
| Admin | `/demo/admin/laporan` | Laporan (filter dari URL: `?jenis=peminjaman&awal=2026-11-01&akhir=2026-11-07`) |
| Anggota | `/demo/anggota/dashboard` | Dashboard |
| Anggota | `/demo/anggota/cari-buku` | Cari Buku |
| Anggota | `/demo/anggota/booking/ajukan` | Ajukan Booking (`?buku=BK-001` atau `?eksemplar=EK-001` memilih buku/eksemplar) |
| Anggota | `/demo/anggota/booking/status` | Status Booking |
| Anggota | `/demo/anggota/history` | History Peminjaman |
| Pengembang | `/demo/komponen` | Katalog komponen UI (tidak ditautkan dari navigasi) |
| Teknis | `/hello/echo` | Smoke test Laravel → Inertia → Vue (Task 00) |

### Akun pratinjau (bukan autentikasi)

| Identitas Pengguna | Peran |
| --- | --- |
| `admin` | Admin |
| `AG-001` | Anggota |

Kedua akun pratinjau menerima kata sandi apa pun yang tidak kosong **selama Admin belum mereset kata sandinya**; identitas lain ditolak dengan pesan generik kecuali akun yang kata sandinya ditetapkan Admin (lihat di bawah). Peran halaman tetap dibaca dari URL `/demo/{peran}/...`; **tidak ada** sesi server, otorisasi, maupun perlindungan URL.

### Akses login Anggota (simulasi)

Fitur tambahan **setelah Final Audit** (belum tercakup laporan `docs/audit/FINAL_*`; lihat `docs/audit/ADDENDUM_AKSES_LOGIN_ANGGOTA.md`).

- Pendaftaran Anggota dilakukan oleh **Admin**. **Tidak ada registrasi mandiri.**
- Akses login **opsional**: Admin → Data Anggota → Tambah/Ubah Anggota → bagian *Akses Login Anggota* (Username, Kata Sandi, Konfirmasi; **Reset Kata Sandi** pada Ubah).
- **Hanya Status Anggota yang mengendalikan status aktif/nonaktif Anggota dan izin login.** Tidak ada Status Akses terpisah: Anggota Nonaktif tidak dapat masuk (akun, kata sandi, dan riwayat tetap tersimpan), dan dapat masuk lagi dengan kredensial lama setelah diaktifkan kembali.
- Masuk ditolak bila kredensial salah (pesan generik), Anggota Nonaktif, anggota belum punya akun, atau relasi akun–anggota tidak valid. Setelah masuk, halaman Anggota menampilkan data milik akun tersebut.
- **Bukan autentikasi aman.** Verifikasi berjalan di browser; kata sandi disimpan sebagai hash SHA-256 bergaram **hanya di memori**. Tidak ada server, database, rate limit, atau sesi yang tahan muat ulang. Kata sandi tidak ditulis ke localStorage/sessionStorage, log, URL, maupun Git.
- **Muat ulang browser** mengembalikan data ke mock awal: anggota, akun, dan kata sandi yang dibuat Admin hilang, dan halaman Anggota kembali memakai akun pratinjau AG-001.
- Fitur ini **bukan** implementasi FR-002 (validasi kredensial ke basis data) maupun FR-019 (blokir sesi tidak valid).

> SRS v1.1 membedakan Admin dan Petugas. Prototipe memakai dua peran (Admin dan Anggota); detail dan catatan penyelarasan ada di `docs/KEPUTUSAN_DESAIN.md`.

Route bawaan framework yang tetap ada (bukan halaman aplikasi): `GET /up` (health check) dan `storage/{path}` (disk lokal Laravel). Route DevTools Inertia dimatikan dengan `INERTIA_DEVTOOLS_ENABLED=false`.

## Screenshot untuk Google Stitch

Paket screenshot PNG halaman final (tanpa URL publik) ada di `docs/stitch/`:

- `screenshots/` — 18 screenshot utama (1440 px, full-page) + 10 screenshot state
- `SCREENSHOT_MANIFEST.md` — halaman, URL lokal, file, dimensi, validasi
- `QA_REPORT.md` — hasil QA final
- `SIM_PERPUSTAKAAN_SCREENSHOTS.zip` — screenshot + manifest

Buat ulang (aplikasi harus berjalan dengan aset `npm run build`):

```bash
npm run screenshots:stitch                              # basis http://127.0.0.1:8000
npm run screenshots:stitch -- --base=http://127.0.0.1:8013
```

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
│   ├── config/demo.js           # akun pratinjau (dari data mock) & tanggal demo tetap (2026-11-07)
│   ├── data/mock/               # data mock terpusat: users, members, books, bookCopies, loans,
│   │                            # returns, bookings + relasi, dashboard, validasi (lihat docs/analysis/mock-data.md)
│   ├── composables/useDemo.js   # akses peran, akun, menu, peta rute (templat berparameter)
│   ├── composables/useDataPerpustakaan.js # store reaktif sesi (salinan data mock) untuk simulasi simpan
│   ├── utils/status.js          # kosakata status resmi + tampilan badge
│   ├── utils/format.js          # format tanggal Indonesia (WIB), inisial
│   ├── utils/pencarian.js       # pencarian & filter (anggota, buku)
│   ├── utils/validasiMaster.js  # validasi form anggota, buku, eksemplar
│   ├── utils/validasiTransaksi.js # validasi peminjaman, pengembalian, setujui/tolak/realisasi booking
│   ├── utils/laporan.js         # penyusun laporan (buku, anggota, peminjaman, pengembalian)
│   ├── Components/              # AppSidebar, AppHeader, PageHeader, BaseButton, BaseInput,
│   │                            # BaseSelect, BaseTable, StatusBadge, FormFeedback,
│   │                            # EmptyState, ConfirmationModal, BrandMark, SearchInput, DetailPanel, StatCard,
│   │                            # SegmentedTabs, ReadonlyField
│   ├── Layouts/                 # AppShell, AdminLayout, AnggotaLayout
│   └── Pages/                   # Auth/Login, Admin/Dashboard, Admin/Anggota/{Index,Form}, Admin/Buku/{Index,Form},
│                                # Admin/Peminjaman, Admin/Pengembalian, Admin/Booking/{Verifikasi,Realisasi}, Admin/Laporan,
│                                # Anggota/Dashboard, Anggota/CariBuku, Anggota/Booking/{Ajukan,Status}, Anggota/History,
│                                # Sistem/PratinjauKomponen, HelloEcho
└── views/app.blade.php          # root template: @vite, <x-inertia::head>, <x-inertia::app>
```

Menambah halaman: daftarkan di `NavigasiDemo::halamanAdmin()` / `halamanAnggota()` (uri, judul, deskripsi, induk breadcrumb, komponen). Rute, menu aktif, dan breadcrumb mengikuti otomatis.

## Pengujian

```bash
export PATH="/opt/homebrew/opt/php@8.4/bin:$PATH"
php artisan route:list
php artisan test             # rute, menu, breadcrumb, batas peran, tanpa query database
npm run test:data            # data mock, validasi, store atomik, transaksi, laporan, booking Anggota, alur lintas peran & akses login Anggota (184 tes)
npm run build
```
