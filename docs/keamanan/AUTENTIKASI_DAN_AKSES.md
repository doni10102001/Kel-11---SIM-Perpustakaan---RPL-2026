# Arsitektur Autentikasi & Manajemen Sesi Pengguna

Sistem Informasi Manajemen Perpustakaan (SIM Perpustakaan)

| Aspek | Keterangan |
| --- | --- |
| Ruang Lingkup | Autentikasi username + kata sandi, sesi server, logout, otorisasi berbasis peran (RBAC), proteksi rute |
| Komponen Keamanan | Guard `web`, sesi database MySQL (`sessions`), Hash Bcrypt/Argon2id, Rate Limiting (5 percobaan/menit), CSRF Token |

## 1. Arsitektur Autentikasi

```
Browser (Login.vue, useForm)                    Laravel (guard web, sesi DB)                MySQL
POST /login {username, password, XSRF} ──────▶ MasukRequest::autentikasi()  ───────────▶ users (hash bcrypt)
                                                 ├─ rate limit 5/60 dtk per (username, IP)    members (status)
                                                 ├─ Hash::check (+ hash pengecoh bila tak ada)
                                                 └─ User::alasanTidakBolehMasuk()
                                               Auth::login() + session()->regenerate() ─▶ sessions (user_id)
                                               redirect → /admin/dashboard | /anggota/dashboard
GET /{peran}/*  ───────────────────────────────▶ auth → auth.session → akun.aktif → peran:{peran}
POST /logout    ───────────────────────────────▶ logout + invalidate + regenerateToken → /login
```

| Komponen | Berkas | Tugas |
| --- | --- | --- |
| Request masuk | `app/Http/Requests/Auth/MasukRequest.php` | Validasi, pembatasan percobaan, verifikasi hash, kelayakan akun |
| Controller | `app/Http/Controllers/Auth/SesiController.php` | Tampil halaman Masuk, buat sesi, keluar |
| Kelayakan akun | `App\Models\User::alasanTidakBolehMasuk()` + `app/Enums/AlasanTolakMasuk.php` | Admin: `is_active`; Anggota: data Anggota tertaut dan `members.status = Aktif` |
| Middleware `akun.aktif` | `app/Http/Middleware/PastikanAkunAktif.php` | Periksa ulang kelayakan dari DB setiap request; akhiri sesi bila tidak layak |
| Middleware `peran` | `app/Http/Middleware/PastikanPeran.php` | Otorisasi peran di server → 403 |
| Middleware `auth.session` | bawaan Laravel (`AuthenticateSession`) | Sesi lama berakhir bila kata sandi akun berubah |
| Identitas ke Vue | `app/Support/AkunSesi.php` → prop `auth.akun` | `peran, identitas, nama, labelPeran, keterangan, kodeAnggota?` — tanpa id, hash, kontak |
| Navigasi | `app/Support/NavigasiDemo.php` | Daftar halaman/menu; tujuan setelah masuk |

## 2. Aturan masuk

| Kondisi | Hasil | Pesan |
| --- | --- | --- |
| Username tidak ada / kata sandi salah | Ditolak, dihitung percobaan | "Identitas pengguna atau kata sandi tidak sesuai. Periksa kembali, lalu coba lagi." (generik, sama untuk keduanya) |
| Kata sandi benar, Admin `is_active = 0` | Ditolak | "Akun ini sedang nonaktif. Hubungi Admin perpustakaan." |
| Kata sandi benar, Anggota `members.status = Nonaktif` | Ditolak | "Keanggotaan Anda sedang nonaktif sehingga tidak dapat masuk. Hubungi Admin perpustakaan." |
| Kata sandi benar, akun Anggota tanpa data Anggota | Ditolak | "Akun ini belum terhubung dengan data anggota yang valid. Hubungi Admin perpustakaan." |
| ≥ 5 percobaan ditolak dalam 60 dtk (username + IP) | Ditolak walau kata sandi benar | "Terlalu banyak percobaan masuk. Coba lagi dalam N detik." |
| Valid | Sesi baru (ID diganti) | Redirect ke URL tujuan awal **milik peran yang sama**, selain itu dashboard peran |

Pesan status hanya muncul setelah kata sandi cocok (perilaku prototipe yang disetujui, `PESAN_MASUK`). Username tidak peka huruf besar/kecil (collation) dan spasi tepi dibuang; kata sandi tidak di-trim, tidak dikembalikan ke form, dan dikosongkan di UI setelah setiap percobaan. Tidak ada registrasi mandiri dan tidak ada "Ingat saya" (D-05).

## 3. Sesi

| Pengaturan | Nilai | Catatan |
| --- | --- | --- |
| Driver | `database` (tabel `sessions` MySQL, dari migration Phase 7A) | `.env` dan `.env.example`: `SESSION_DRIVER=database` |
| Lifetime | 120 menit idle, `expire_on_close=false` | Kedaluwarsa diuji end-to-end pada DB uji |
| Cookie sesi | `HttpOnly`, `SameSite=Lax`, `Secure` mengikuti `SESSION_SECURE_COOKIE` | Wajib `true` di deployment HTTPS |
| Masuk | `session()->regenerate()` | Mencegah session fixation |
| Keluar | `logout()` + `invalidate()` + `regenerateToken()` | Data sesi lama hilang, token CSRF baru |
| Perubahan status | `akun.aktif` membaca ulang `users` + `members` setiap request | Anggota/Admin yang dinonaktifkan ditolak pada request berikutnya walau sesi lama masih ada |
| Ganti kata sandi | `auth.session` | Sesi lain otomatis berakhir (siap untuk Phase 8C) |
| CSRF | `PreventRequestForgery` Laravel 13 + cookie `XSRF-TOKEN` (Inertia 3 otomatis) | Laravel 13 juga menerima request ber-header `Sec-Fetch-Site: same-origin` |

## 4. Rute (migrasi dari `/demo/*`)

| Sebelum (prototipe, tanpa autentikasi) | Sesudah (Phase 8A) | Middleware |
| --- | --- | --- |
| `GET /login` (pratinjau) | `GET /login` (`guest`), `POST /login` (`guest`), `POST /logout` (`auth`) | — |
| `/demo/admin/*` (nama `demo.admin.*`) | `/admin/*` (nama `admin.*`) | `auth`, `auth.session`, `akun.aktif`, `peran:admin` |
| `/demo/anggota/*` (nama `demo.anggota.*`) | `/anggota/*` (nama `anggota.*`) | `auth`, `auth.session`, `akun.aktif`, `peran:anggota` |
| `/demo/komponen` | `/admin/komponen` (hanya di luar production, khusus Admin) | sama dengan Admin |
| `/hello/echo` | tetap, hanya di luar production | — |
| — | `/demo/{admin|anggota}/{jalur}` dan `/demo/komponen` → **redirect 302** ke rute baru | tujuan tetap menuntut sesi dan peran |

Halaman Vue tidak memakai URL `/demo/` secara langsung (dijaga `tests/js/autentikasi-server.test.js`); semua tautan berasal dari peta rute server, sehingga navigasi, menu, remah roti, dan rute berparameter tetap berfungsi (dijaga `tests/Feature/NavigasiDemoTest.php`, 19 test).

## 5. Perubahan frontend

- `Pages/Auth/Login.vue`: tata letak tetap; verifikasi mock diganti `useForm().post('/login')`; kotak "Mode pratinjau" (berisi instruksi "isi kata sandi dengan teks apa pun" dan tombol akun pratinjau) dihapus karena tidak lagi benar; pesan satu kali (`flash.status`) tampil setelah keluar atau sesi diakhiri.
- `Layouts/AppShell.vue`: Keluar → `router.post('/logout')`.
- `composables/useDemo.js`: `akun` dibaca dari `page.props.auth.akun` (sesi server). Akun pratinjau AG-001 tidak dipakai lagi; halaman Anggota memakai `kodeAnggota` akun yang masuk.
- Fungsi mock `masuk/keluar/akunAktif` di `useDataPerpustakaan.js` tetap ada hanya sebagai aturan data fitur mock "Akses Login Anggota" (diganti Phase 8C) dan tidak dipanggil halaman mana pun.

## 6. Kredensial

**Admin awal.** Akun `admin` (Rina Wulandari) memakai hash di MySQL yang dibuat seeder dari `SEED_ADMIN_PASSWORD` di `.env` lokal. QA browser membuktikan Admin dapat masuk dengan kredensial lokal tersebut (nilai tidak dicetak). Login tidak mengubah baris `users` (diverifikasi checksum).

**Strategi ganti kata sandi Admin (usulan untuk Phase 8C, belum diimplementasikan):**
1. Halaman "Ubah Kata Sandi" untuk akun yang sedang masuk: wajib kata sandi saat ini, kata sandi baru 6–64 karakter (L.3) dan konfirmasi, rate limit.
2. Simpan lewat cast `hashed`; panggil `Auth::logoutOtherDevices()` / andalkan `auth.session` agar sesi lain berakhir; regenerasi sesi saat ini.
3. Catat ke `audit_logs` (aktor, aksi `auth.password_changed`, tanpa nilai kata sandi).
4. Setelah Admin mengganti kata sandi, hapus `SEED_ADMIN_PASSWORD` dari `.env`.
5. Pemulihan darurat tanpa UI: perintah artisan lokal yang meminta kata sandi baru secara interaktif (tidak lewat argumen/riwayat shell) — perlu persetujuan terpisah.

**Akun Anggota.** Ke-10 akun Anggota hasil seed memakai hash acak (D-08), sehingga **tidak ada Anggota yang dapat masuk secara interaktif** sampai alur provisioning/reset tersedia. Tidak ada kata sandi demo bersama. Login Anggota dibuktikan oleh test otomatis di DB uji (akun factory dengan kata sandi uji).

### 6.1 Kebutuhan Phase 8C — kredensial Anggota

1. Admin membuat akses login Anggota (username unik, kata sandi awal 6–64) dan mereset kata sandi Anggota lewat backend; ganti fitur mock "Akses Login Anggota" di form Anggota.
2. Kata sandi awal/reset hanya ditampilkan sekali kepada Admin atau ditetapkan Admin; tidak disimpan plaintext, tidak di-log.
3. Reset kata sandi mengakhiri semua sesi Anggota tersebut (`auth.session` sudah aktif).
4. Status login tetap hanya dari `members.status` (D-03); tidak ada Status Akses kedua.
5. Opsional (perlu keputusan): wajib ganti kata sandi pada login pertama setelah reset.
6. Audit log untuk pembuatan akses dan reset kata sandi.
7. Test: otorisasi Admin saja, hash tersimpan, sesi lama berakhir, Anggota Nonaktif tetap tidak dapat masuk.

## 7. Pengujian

| Lapisan | Hasil |
| --- | --- |
| `tests/Feature/Auth/AutentikasiTest.php` (MySQL uji) | 26 test: kredensial valid/salah, generik, Admin nonaktif, Anggota aktif/nonaktif, tanpa relasi, validasi, regenerasi ID sesi, logout + token CSRF baru, kedaluwarsa sesi DB (travel 121 menit), CSRF 419, tamu ditolak semua rute (redirect / 401 JSON), 403 lintas peran, tujuan awal, `/login` saat sudah masuk, throttling, nonaktif di tengah sesi (Anggota & Admin), ganti kata sandi mengakhiri sesi, identitas dari sesi bukan parameter, prop tanpa data sensitif, tanpa jalan pintas demo |
| `tests/Feature/NavigasiDemoTest.php` (MySQL uji) | 19 test navigasi lama pada rute baru + redirect `/demo` + identitas sesi |
| `tests/js/autentikasi-server.test.js` | 6 test: tidak ada pemakaian auth mock di UI, Login/Keluar ke server, tanpa URL `/demo/` |
| QA browser (Chrome headless, `php artisan serve`, DB development) | 20 cek lulus (lihat laporan Phase 8A) |

## 8. Keterbatasan keamanan yang diketahui

1. ~~**Data mock masih dibundel ke JavaScript publik.**~~ **Diselesaikan Phase 8B** (lihat `docs/archive/keamanan/INTEGRASI_DATA_SERVER.md`). Halaman masih membaca `resources/js/data/mock/` (Phase 8B mengganti dengan data server). Bundel `public/build` dapat diunduh tanpa login dan memuat seluruh data demo (nama, kontak, transaksi semua Anggota). Data ini fiktif dan identik dengan seed, tetapi pola ini tidak boleh dipakai untuk data nyata.
2. Aksi transaksi di halaman (pinjam, kembali, booking) masih memodifikasi store mock di browser dan memakai ID Admin mock (`ID_ADMIN_DEMO`) sebagai pemroses; belum ada endpoint server.
3. Halaman 403 memakai tampilan error bawaan Laravel (belum ditata sesuai desain).
4. Pembatasan percobaan per (username, IP); belum ada batas global per IP lintas username.
5. `SESSION_SECURE_COOKIE` belum di-set (lokal HTTP); wajib `true` dan HTTPS sebelum deployment. `SESSION_ENCRYPT=false`.
6. Skrip uji browser memakai URL `/admin/*` dan `/anggota/*` dengan sesi login yang valid.
7. Laravel 13 menerima request POST dengan `Sec-Fetch-Site: same-origin` tanpa token CSRF (perilaku framework).
