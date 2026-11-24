# Remediasi Kredensial QA & Prosedur Rotasi

Tanggal: **24 November 2026** · Status: **kode sudah diperbaiki (commit `b06049b`); rotasi kredensial BELUM dilakukan (menunggu otorisasi pemilik)**

## 1. Temuan

Commit `5f7a030` (16 Nov 2026, sudah ada di `origin/main` dan `origin/phase-9b-buku-eksemplar`) menulis dua kredensial aktif sebagai teks biasa di `scripts/qa-browser-journey.mjs`:

| # | Kredensial | Dipakai untuk | Status per 24 Nov 2026 (diperiksa tanpa mencetak nilai) |
| --- | --- | --- | --- |
| K-1 | Kata sandi akun MySQL `sim_perpus_test` (`DB_TEST_PASSWORD`) | Database uji `sim_perpustakaan_test` (`@localhost` dan `@127.0.0.1`) | **Masih aktif**: koneksi `mysql_testing` berhasil dengan nilai `.env` yang sama |
| K-2 | Kata sandi akun aplikasi `admin` (`SEED_ADMIN_PASSWORD`) | Login Admin pada database **development** `sim_perpustakaan_db` | **Masih aktif**: `Hash::check` terhadap hash Admin di DB development bernilai benar |

Skrip yang sama juga memuat kata sandi akun anggota QA `siti.qa`. Akun ini hanya hidup sementara di DB uji dan dihapus di akhir QA. Nilainya tetap dihapus dari kode.

Kredensial lain yang diperiksa dan **tidak** ditemukan di berkas terlacak maupun riwayat Git: `DB_PASSWORD` (akun `sim_perpus_app`) dan `APP_KEY`. `REDIS_PASSWORD`, `MAIL_PASSWORD`, dan `AWS_*` kosong atau null.

## 2. Perbaikan kode (commit lokal `b06049b`, belum di-push)

- `scripts/qa-browser-journey.mjs` tidak lagi memuat kata sandi apa pun.
  - `DB_TEST_USERNAME` dan `DB_TEST_PASSWORD` **wajib** diberikan lewat environment. Tidak ada nilai bawaan; bila kosong, skrip berhenti dengan exit code 2 sebelum menyentuh database.
  - `DB_TEST_DATABASE` opsional dan wajib berakhiran `_test`. Skrip menolak jika `DB_TEST_USERNAME` sama dengan `DB_USERNAME` development.
  - Admin uji di-seed ulang di DB uji dengan **kata sandi acak sekali pakai** (`SEED_ADMIN_PASSWORD` di-override hanya untuk proses seeder uji). Kata sandi Admin development tidak lagi dipakai oleh QA.
  - Kata sandi anggota QA (`siti.qa`, awal dan pengganti) juga acak sekali pakai.
  - Kredensial hanya diteruskan ke proses yang menyentuh DB uji (server PHP sementara, seeder, pembersihan, suite kedaluwarsa). Tidak ada kredensial di argumen perintah atau di log.
- `docs/TUGAS-10-FINAL/_alat/capture-hasil-sistem.mjs` memakai pola yang sama. Skrip ini tidak lagi membaca `.env` langsung.

Verifikasi 24 Nov 2026: PHPUnit 270/270, Node 245/245, `npm run build` sukses, Browser QA 23/23, dan E2E demo 22/22. Pemindaian log tidak menemukan nilai `.env` maupun token mirip kata sandi acak. Jumlah baris DB development identik sebelum dan sesudah. Bukti: `docs/TUGAS-10-FINAL/08-SOFTWARE-TESTING/bukti/*-2026-11-24.*`.

## 3. Mengapa rotasi tetap wajib

Menghapus nilai di commit baru **tidak** menghapusnya dari riwayat Git. Riwayat itu sudah ada di GitHub (`origin/main`) dan di setiap clone. Riwayat bersama tidak ditulis ulang. Satu-satunya perbaikan efektif adalah **mengganti kedua kredensial** sehingga nilai lama tidak berlaku lagi. **Jangan buat repositori publik sebelum K-1 dan K-2 dirotasi.**

## 4. Prosedur rotasi (dijalankan pemilik, di mesin lokal)

Prinsip yang berlaku di semua langkah:

- Nilai baru dibangkitkan acak dan disimpan di variabel shell, lalu dikirim lewat stdin atau environment.
- Jangan ketik kata sandi di argumen perintah, dan jangan `echo` nilainya.
- Jangan restart MySQL dan jangan ubah akun lain (`root`, `sim_perpus_app`, akun proyek lain di instance bersama).

### 4.1 K-1 — akun MySQL uji `sim_perpus_test`

```zsh
cd ~/Aplikasi/S1/SIM-Perpustakaan
BARU="$(openssl rand -base64 48 | tr -dc 'A-Za-z0-9' | cut -c1-32)"   # 32 karakter, tidak dicetak

# Jalankan sebagai root MySQL; mysql meminta kata sandi root secara interaktif (-p tanpa nilai).
# SQL dikirim lewat stdin, jadi nilai BARU tidak muncul di daftar proses.
mysql -u root -p <<SQL
ALTER USER 'sim_perpus_test'@'localhost' IDENTIFIED BY '${BARU}';
ALTER USER 'sim_perpus_test'@'127.0.0.1' IDENTIFIED BY '${BARU}';
SQL

# Perbarui .env tanpa mencetak nilai (cadangan .env.bak dibuat lalu dihapus setelah verifikasi).
BARU="$BARU" perl -i.bak -pe 's/^DB_TEST_PASSWORD=.*/DB_TEST_PASSWORD="$ENV{BARU}"/' .env
unset BARU
php artisan config:clear

# Verifikasi: koneksi uji berhasil (keluaran hanya true/false)
php artisan tinker --execute='echo json_encode((bool) DB::connection("mysql_testing")->select("select 1"));'
rm -f .env.bak
```

Hanya dua akun `sim_perpus_test@localhost` dan `@127.0.0.1` yang diubah. Hak (`GRANT`) tidak berubah.

### 4.2 K-2 — akun aplikasi `admin` (DB development)

Catatan penting: `DemoPerpustakaanSeeder` **tidak** mengubah akun yang sudah ada. Mengganti `SEED_ADMIN_PASSWORD` di `.env` saja **tidak** merotasi kata sandi Admin.

1. Jalankan aplikasi lokal (`php artisan serve`), login sebagai `admin`, buka **Ubah Kata Sandi** (`/admin/kata-sandi`), lalu ganti ke kata sandi baru yang kuat (6–64 karakter, unik, simpan di pengelola kata sandi).
2. Verifikasi read-only bahwa nilai lama tidak berlaku lagi. Jalankan selagi nilai lama masih ada di `.env`:

   ```zsh
   php artisan tinker --execute='$h=DB::table("users")->where("username","admin")->value("password"); echo json_encode(["sandi_lama_masih_aktif"=>Hash::check((string) config("perpustakaan.seed.admin_password"), $h)]);'
   # harus: {"sandi_lama_masih_aktif":false}
   ```

3. Hapus `SEED_ADMIN_PASSWORD` dari `.env` (sesuai `docs/keamanan/AUTENTIKASI_DAN_AKSES.md` §6 langkah 4). Bila suatu saat perlu men-seed database baru, isi dengan nilai acak **baru**, bukan nilai lama.

### 4.3 Konfigurasi QA yang bergantung

- `scripts/qa-browser-journey.mjs` dan `_alat/capture-hasil-sistem.mjs` tidak menyimpan kredensial, jadi tidak perlu diubah setelah rotasi.
- Jalankan QA dengan kredensial uji yang baru:

  ```zsh
  read -rs "DB_TEST_PASSWORD?Kata sandi MySQL uji: " && export DB_TEST_PASSWORD
  DB_TEST_USERNAME=sim_perpus_test node --experimental-websocket scripts/qa-browser-journey.mjs
  unset DB_TEST_PASSWORD
  ```

- PHPUnit (`php artisan test`) tetap membaca `DB_TEST_*` dari `.env` lokal lewat koneksi `mysql_testing`.
- Tidak ada CI atau secret GitHub Actions di repositori ini. Bila nanti ditambahkan, simpan nilainya sebagai *repository secret*, bukan di berkas.

### 4.4 Setelah rotasi

1. Jalankan ulang `php artisan test` dan Browser QA. Keduanya harus tetap lulus.
2. Ubah status di `docs/TUGAS-10-FINAL/FINAL_SUBMISSION_CHECKLIST.md` bagian D menjadi ✅.
3. Baru setelah itu pertimbangkan membuka akses repositori (publik atau undangan kolaborator dosen).

## 5. Yang sengaja tidak dilakukan

- Tidak merotasi kredensial nyata (perlu otorisasi eksplisit pemilik).
- Tidak menulis ulang riwayat Git, tidak force-push, dan tidak menghapus commit.
- Tidak mengubah akun MySQL lain, tidak me-restart MySQL, dan tidak mengubah logika bisnis aplikasi.
