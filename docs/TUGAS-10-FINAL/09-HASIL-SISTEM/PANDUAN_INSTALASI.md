# Panduan Instalasi & Menjalankan Sistem (Lokal)

Panduan ini tidak memuat kata sandi atau rahasia apa pun. Semua nilai rahasia diisi sendiri di `.env` lokal yang **tidak** di-commit (`.env` sudah ada di `.gitignore`).

## 1. Prasyarat

| Perangkat lunak | Versi | Catatan |
| --- | --- | --- |
| PHP | ≥ 8.4.1 | Ekstensi `pdo_mysql`, `mbstring`, `openssl`, `intl` disarankan. Di macOS dengan beberapa versi PHP, pastikan PHP 8.4 yang dipakai (mis. `export PATH="/opt/homebrew/opt/php@8.4/bin:$PATH"`) |
| Composer | 2.x | — |
| Node.js + npm | 20.x | `composer run dev` memerlukan Node ≥ 22.13; dengan Node 20 jalankan Laravel dan Vite secara terpisah (lihat §5) |
| MySQL | 8.4 LTS | InnoDB, utf8mb4 |
| Google Chrome | terbaru | Hanya untuk Browser QA |

## 2. Ambil kode dan pasang dependensi

```bash
git clone git@github.com:doni10102001/Kel-11---SIM-Perpustakaan---RPL-2026.git
cd Kel-11---SIM-Perpustakaan---RPL-2026
composer install
npm install
cp .env.example .env
php artisan key:generate
```

## 3. Siapkan MySQL

Buat **dua** database dan **dua** akun dengan hak minimal:

| Keperluan | Database | Akun (contoh di `.env.example`) |
| --- | --- | --- |
| Development / demo | `sim_perpustakaan_db` | `sim_perpus_app` |
| Pengujian otomatis (nama wajib berakhiran `_test`) | `sim_perpustakaan_test` | `sim_perpus_test` |

Pilihan otomatis: skrip `scripts/db/siapkan-mysql-lokal.sh` membaca nilai dari `.env`, memverifikasi instance MySQL, lalu membuat database dan akun setelah konfirmasi "YA". Skrip ini tidak menjalankan `DROP` dan tidak mencetak kata sandi.

## 4. Isi `.env` lokal

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sim_perpustakaan_db
DB_USERNAME=sim_perpus_app
DB_PASSWORD=            # isi sendiri

DB_TEST_DATABASE=sim_perpustakaan_test
DB_TEST_USERNAME=sim_perpus_test
DB_TEST_PASSWORD=       # isi sendiri

SEED_ADMIN_PASSWORD=    # kata sandi awal akun "admin" (6–64 karakter), hanya untuk seeding pertama
```

## 5. Inisialisasi database dan jalankan aplikasi

```bash
php artisan migrate                                   # membuat 8 tabel domain + tabel pendukung Laravel (sessions, cache, jobs, …)
php artisan db:seed --class=DemoPerpustakaanSeeder    # data demo; menolak jalan bila SEED_ADMIN_PASSWORD kosong
npm run build                                         # atau: npm run dev (terminal terpisah) untuk HMR
php artisan serve                                     # http://127.0.0.1:8000
```

Terminal tambahan untuk **penjadwal** (kedaluwarsa booking 48 jam, tiap 5 menit):

```bash
php artisan schedule:work
```

> Tanpa penjadwal, booking yang lewat batas tetap ditangani saat ada transaksi terkait (rekonsiliasi). Namun statusnya baru berubah ketika eksemplar/booking tersebut disentuh.
> **Produksi** (belum ada): cron/systemd wajib menjalankan `php artisan schedule:run` setiap menit.

## 6. Akun

- **Admin**: username `admin`, kata sandi = nilai `SEED_ADMIN_PASSWORD` di `.env` Anda saat seeding pertama. Setelah itu kata sandi diganti lewat menu **Ubah Kata Sandi**; mengubah `.env` tidak mengubah akun yang sudah ada (lihat `docs/keamanan/ROTASI_KREDENSIAL_QA.md`).
- **Anggota**: akun hasil seed memakai hash acak sehingga tidak dapat ditebak. Buat akses login lewat menu **Data Anggota → Tambah/Ubah Anggota → Akses Login Anggota**. Anggota wajib mengganti kata sandi saat login pertama.

## 7. Menjalankan pengujian

```bash
php artisan test        # 270 tes; memakai sim_perpustakaan_test (harus kosong)
npm run test:data       # 245 tes Node
```

Browser QA membutuhkan kredensial MySQL uji lewat environment; skrip tidak memiliki kata sandi bawaan dan berhenti bila variabel kosong:

```zsh
read -rs "DB_TEST_PASSWORD?Kata sandi MySQL uji: " && export DB_TEST_PASSWORD
DB_TEST_USERNAME=sim_perpus_test node --experimental-websocket scripts/qa-browser-journey.mjs   # jangan saat `npm run dev` aktif
unset DB_TEST_PASSWORD
```

## 8. Larangan pada database development

Jangan jalankan `migrate:fresh`, `db:wipe`, atau `bookings:expire` terhadap database development yang berisi data uji manual, kecuali memang disengaja.
