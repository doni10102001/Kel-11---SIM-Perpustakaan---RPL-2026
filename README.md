# SIM Perpustakaan

Sistem Informasi Manajemen Perpustakaan — **satu proyek Laravel terintegrasi**. Vue 3 berjalan di dalam Laravel melalui Inertia.js dan Vite (tidak ada proyek frontend/backend terpisah).

> **Status tahap ini (Task 00):** hanya setup awal + satu halaman uji `/hello/echo`.
> Belum ada database, autentikasi, API, peran pengguna, maupun modul bisnis.

## Versi terpasang

| Komponen | Versi |
| --- | --- |
| PHP | 8.4.27 (Homebrew `php@8.4`) |
| Laravel Framework | 13.40.0 (skeleton `laravel/laravel` v13.11.0) |
| inertiajs/inertia-laravel | 3.x (`^3.0`) |
| @inertiajs/vue3 / @inertiajs/vite | 3.11.0 |
| Vue | 3.5.45 |
| @vitejs/plugin-vue | 6.0.10 |
| Vite / laravel-vite-plugin | 8.3.6 / 3.2.1 |
| Bootstrap | 5.3.8 (CSS saja) |
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

## URL uji

- **<http://localhost:8000/hello/echo>** — halaman "Hello Echo": input **Pesan** dan pratinjau reaktif **Echo: …** (state Vue di sisi klien, tanpa request ke server).
- `/` sengaja **404**, karena belum ada halaman aplikasi lain.

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
resources/
├── css/app.css              # CSS kustom di atas Bootstrap 5.3
├── js/
│   ├── app.js               # entry Inertia (createInertiaApp) + import Bootstrap CSS
│   └── Pages/HelloEcho.vue  # satu-satunya halaman
└── views/app.blade.php      # root template: @vite, <x-inertia::head>, <x-inertia::app>
routes/web.php               # Route::inertia('/hello/echo', 'HelloEcho')
bootstrap/app.php            # middleware HandleInertiaRequests di grup web
vite.config.js               # laravel-vite-plugin + @inertiajs/vite + @vitejs/plugin-vue
```

## Pengujian

```bash
export PATH="/opt/homebrew/opt/php@8.4/bin:$PATH"
php artisan route:list --path=hello
php artisan test             # memeriksa /hello/echo (komponen HelloEcho) dan / (404)
npm run build
```
