# Phase 8C — Manajemen Kredensial Nyata

SIM Perpustakaan · Laravel 13 · MySQL 8.4 · Inertia 3 / Vue 3

| Metadata | Nilai |
| --- | --- |
| Tanggal | 14 November 2026 |
| Branch | `phase-7a-8a-mysql-auth` — checkpoint 7A `bd8ca39`, 8A `26ae255`, 8B `02c27f5`; Phase 8C belum di-commit |
| Lingkup | Buat akses login Anggota, reset kata sandi Anggota oleh Admin, ganti kata sandi Admin sendiri, verifikasi login nyata |
| Di luar lingkup | CRUD Data Anggota/Buku, transaksi, booking (Phase 9); tanpa migration baru |

## 1. Arsitektur

```
Vue (Ubah Anggota / Ubah Kata Sandi)
  → POST/PUT + CSRF (cookie XSRF-TOKEN, Inertia)
  → middleware: auth · auth.session · akun.aktif · peran:admin (+ throttle)
  → Form Request (validasi + Gate kelola-kredensial)
  → App\Services\KredensialAkun (DB::transaction, lockForUpdate, Eloquent, cast `hashed`)
  → MySQL users / members / audit_logs / sessions
  → redirect back + pesan status (tanpa kata sandi/hash)
```

Tidak ada tabel atau migration baru. Constraint yang ada tetap menjadi pengaman terakhir: `uq_users_username` (tidak peka huruf), `uq_members_user_id`, `chk_users_role`, `chk_users_nama_sesuai_peran`, `chk_users_anggota_tanpa_status_akses`.

## 2. Rute & Form Request

| Metode | URI | Nama | Request | Throttle |
| --- | --- | --- | --- | --- |
| POST | `/admin/anggota/{kodeAnggota}/akses-login` | `admin.anggota.akses-login.store` | `BuatAksesLoginRequest` | 20/menit |
| PUT | `/admin/anggota/{kodeAnggota}/kata-sandi` | `admin.anggota.kata-sandi.reset` | `ResetKataSandiAnggotaRequest` | 20/menit |
| GET | `/admin/kata-sandi` | `admin.kata-sandi` | — (halaman `Admin/KataSandi`) | — |
| PUT | `/admin/kata-sandi` | `admin.kata-sandi.update` | `UbahKataSandiRequest` | 6/menit |

Validasi (sama dengan validator frontend): username wajib, 3–30 karakter `[A-Za-z0-9._-]`, unik terhadap semua akun tanpa membedakan huruf; kata sandi 6–64 karakter (L.3) + konfirmasi; ganti kata sandi sendiri: `current_password:web` dan kata sandi baru harus berbeda.

## 3. Aturan otorisasi server

1. Tamu → 302 ke `/login` (JSON: 401). Anggota → 403 (`peran:admin`).
2. Gate `kelola-kredensial` di setiap Form Request: akun Admin **dan** layak masuk.
3. Field istimewa dari klien diabaikan: hanya `username`, `password`, `password_confirmation`, `current_password` yang dibaca. `role=anggota`, `name=NULL`, `is_active=true`, dan `members.user_id` ditetapkan server.
4. CSRF wajib (diuji: 419 tanpa token).

## 4. Buat akses login Anggota

- Hanya untuk Anggota yang **sudah tersimpan** dan belum berakun (form Ubah Anggota → saklar "Buat akses login" → tombol **Buat Akses Login**). Pada form Tambah Anggota saklar dinonaktifkan karena penyimpanan Anggota baru belum tersedia.
- Transaksi: baris Anggota dikunci (`lockForUpdate`) → cek belum berakun → buat `users` → tautkan `members.user_id` → audit `anggota.akses_login.dibuat` (aktor, kode anggota, username). Kegagalan di langkah mana pun = rollback penuh (diuji).
- Username bentrok (termasuk balapan antar-permintaan, error 1062) → galat validasi `username`; Anggota yang sudah berakun → galat `akses`.
- Status Anggota tidak berubah. Anggota Nonaktif boleh dibuatkan akun, tetapi login tetap ditolak sampai statusnya Aktif (D-03).

## 5. Reset kata sandi Anggota

- Aksi eksplisit: tombol **Reset Kata Sandi** → panel → **Simpan Kata Sandi Baru**. Simpan Anggota (nama/kontak/status) tidak pernah mewajibkan atau membawa kata sandi.
- Hash baru lewat cast `hashed`; `remember_token` diputar; data profil lain tidak berubah; audit `anggota.kata_sandi.direset` (tanpa kata sandi).
- **Sesi lama berakhir:** baris sesi database milik akun itu dihapus, dan middleware `auth.session` menolak sesi yang menyimpan HMAC hash lama (diuji otomatis dan di browser).

## 6. Ganti kata sandi Admin sendiri

- Halaman **Ubah Kata Sandi** (tautan di footer sidebar, khusus Admin; remah Dashboard › Ubah Kata Sandi).
- Kata sandi saat ini diverifikasi; percobaan dibatasi 6/menit (429). Pembatasan login 8A tidak berubah.
- **Perilaku sesi (ditetapkan & diuji):** sesi saat ini **tetap masuk** dengan ID sesi baru (`regenerate(true)` menghancurkan baris sesi lama; `auth.session` menyimpan hash baru); **semua sesi lain** akun ini dihapus/ditolak. Audit `akun.kata_sandi.diubah`.

## 7. Privasi kata sandi

| Lokasi | Perlakuan |
| --- | --- |
| Inertia props / HTML | Tidak pernah memuat kata sandi atau hash (`pengguna.passwordHash` selalu `null`); diuji |
| Old input / session flash | `password`, `password_confirmation`, `current_password` tidak di-flash (dontFlash Laravel); diuji |
| Laravel log | Tidak ada pencatatan input; diuji dengan pendengar `MessageLogged` |
| Audit log | Hanya aksi, aktor, subjek, kode anggota/username |
| Formulir di browser | Dikosongkan setelah setiap permintaan. **Temuan QA:** Inertia v3 menjadikan isian sukses sebagai default formulir, sehingga `form.reset()` mengisi ulang kata sandi. Diperbaiki dengan `onSuccess: form.defaults(<kosong>)` di Ubah Kata Sandi dan Masuk; dijaga `tests/js/kredensial.test.js` |
| Build publik & repo | Tidak ada kata sandi; test memakai kata sandi sementara di transaksi DB uji |

## 8. Pensiun `SEED_ADMIN_PASSWORD` (provisioning awal)

Alur ganti kata sandi Admin sudah terbukti (test + QA browser). Prosedur yang disarankan, **dijalankan pemilik** (tidak otomatis):

1. Masuk sebagai `admin` dengan kata sandi awal (nilai `SEED_ADMIN_PASSWORD` di `.env` lokal).
2. Buka **Ubah Kata Sandi**, isi kata sandi saat ini dan kata sandi baru yang kuat (simpan di pengelola kata sandi).
3. Keluar, lalu masuk dengan kata sandi baru untuk memastikan.
4. Hapus baris `SEED_ADMIN_PASSWORD=...` dari `.env` (atau kosongkan). Seeder tidak akan menimpa akun yang sudah ada; tanpa nilai ini seeder menolak berjalan sehingga tidak ada kata sandi bawaan.
5. Bila `.env` pernah tersalin ke lokasi lain (backup, berbagi layar), anggap kata sandi awal bocor — langkah 2 sudah membuatnya tidak berlaku.

Kata sandi development **tidak** diubah otomatis pada Phase 8C.

## 9. Akun Anggota untuk pengujian

Akun Anggota seed tetap berhash acak (D-08); tidak ada kata sandi bersama. Untuk mengaktifkan login Anggota, Admin memakai **Reset Kata Sandi** (atau **Buat Akses Login** untuk Anggota tanpa akun) — kata sandi ditentukan Admin dan tidak disimpan di mana pun selain hash.

## 10. Pengujian

| Lapisan | Hasil |
| --- | --- |
| `tests/Feature/Kredensial/KredensialTest.php` (MySQL uji) | 21 test: buat akses (aktif & nonaktif), username duplikat (huruf berbeda, termasuk Admin), akun kedua ditolak, rollback atomik, validasi, field istimewa diabaikan, 404 anggota tak dikenal, reset (lama gagal, baru berhasil, profil tak berubah), reset tanpa akun & konfirmasi salah, sesi Anggota lama berakhir, baris sesi DB dihapus, ganti kata sandi Admin (sesi ini tetap), kata sandi saat ini salah/sama, throttle 429, sesi Admin lain berakhir (driver database, cookie nyata), otorisasi tamu/Anggota, CSRF 419, halaman Ubah Kata Sandi, tanpa kata sandi/hash di props/log/audit, hanya rute tulis kredensial |
| `tests/js/kredensial.test.js` | 5 test frontend (endpoint server, pengosongan isian, default formulir, Simpan Anggota tanpa kredensial) |
| Regresi | `php artisan test` 150/150 · `npm run test:data` 210/210 · `npm run build` OK |
| QA browser (Chrome headless; server terhadap `sim_perpustakaan_test` berisi seed + kredensial sementara) | 34/34 cek |

## 11. Catatan lingkungan & risiko

1. **Server dev Vite aktif (`public/hot`)** saat QA. Laravel lalu memuat JS dari server dev dan Inertia mencoba SSR lewat server dev (konfigurasi default `INERTIA_SSR_ENABLED=true`), menghasilkan peringatan *hydration mismatch* pada `ConfirmationModal` (komponen lama, tidak diubah). QA final dijalankan dengan `INERTIA_SSR_ENABLED=false` → 0 error. Karena aplikasi tidak memakai SSR, disarankan menetapkan `INERTIA_SSR_ENABLED=false` di `.env`/`.env.example` (perlu persetujuan).
2. Rute bawaan framework `GET|PUT storage/{path}` aktif karena `filesystems.disks.local.serve = true` (baseline). Rute ini mewajibkan URL bertanda tangan dan aplikasi tidak membuatnya; disarankan `serve => false` bila tidak dipakai (perlu persetujuan).
3. Aksi tulis lain tetap ditolak eksplisit (lihat dokumen 8B §5): simpan Anggota (tambah/ubah data & status), Buku, Peminjaman, Pengembalian, Setujui/Tolak/Realisasi/Ajukan/Batalkan Booking.
4. Belum ada kewajiban ganti kata sandi pada login pertama setelah reset (perlu keputusan bila diinginkan).
