# Phase 8D — Pengerasan Keamanan & Wajib Ganti Kata Sandi

SIM Perpustakaan · Laravel 13 · MySQL 8.4 · Inertia 3 / Vue 3

| Metadata | Nilai |
| --- | --- |
| Tanggal | 14 November 2026 |
| Branch | `phase-7a-8a-mysql-auth` — 7A `bd8ca39`, 8A `26ae255`, 8B `02c27f5`, 8C `4cd261f`; Phase 8D belum di-commit |
| Lingkup | SSR nonaktif, penyajian storage lokal nonaktif, wajib ganti kata sandi Anggota, ganti kata sandi mandiri Anggota |

## 1. SSR dinonaktifkan

| Lokasi | Perubahan |
| --- | --- |
| `config/inertia.php` (dipublikasikan dari paket) | `ssr.enabled` default **`false`** (`INERTIA_SSR_ENABLED`) |
| `.env`, `.env.example` | `INERTIA_SSR_ENABLED=false` |
| `vite.config.js` | `inertia({ ssr: false })` — tanpa entry SSR dan tanpa endpoint `/__inertia_ssr` di server dev |

Server dev Vite biasa (HMR, `npm run dev`) tidak terdampak. Proses server dev yang sedang berjalan tidak di-restart; karena Laravel tidak lagi meminta SSR, halaman tidak memuat markup `data-server-rendered` sehingga peringatan *hydration mismatch* hilang (diuji otomatis dan di browser dengan server dev tetap aktif). Skrip screenshot/QA tidak bergantung pada SSR.

## 2. Storage lokal

Pemeriksaan dependensi: tidak ada `temporaryUrl`/`temporaryUploadUrl`, `Storage::`, atau URL `/storage/` di kode aplikasi; `storage/app/private` kosong; tidak ada symlink `public/storage`.

`config/filesystems.php` → `disks.local.serve = false`. Rute bawaan `GET|PUT /storage/{path}` (`storage.local`, `storage.local.upload`) tidak terdaftar lagi (404). Akses file privat di server (`Storage::disk('local')`) dan konfigurasi disk `public` tidak berubah.

## 3. Flag `users.must_change_password`

| Aspek | Keputusan |
| --- | --- |
| Kolom | `TINYINT(1) NOT NULL DEFAULT 0` setelah `is_active` |
| Default akun lama | `0` (tetap dapat masuk seperti sebelumnya) |
| Diset `1` | Admin membuat akses login Anggota; Admin mereset kata sandi Anggota |
| Diset `0` | Hanya setelah Anggota berhasil mengganti kata sandinya sendiri |
| Constraint | `chk_users_wajib_ganti_hanya_anggota CHECK (role = 'anggota' OR must_change_password = 0)` — Admin tidak pernah dapat diwajibkan (diuji: error 3819) |
| Model | Cast boolean, default `false`, **tidak fillable**; helper `User::wajibGantiKataSandi()` |

### Migration

`2026_11_14_000008_add_must_change_password_to_users_table.php` — aditif murni:

```sql
alter table `users` add `must_change_password` tinyint(1) not null default '0' after `is_active`;
ALTER TABLE users ADD CONSTRAINT chk_users_wajib_ganti_hanya_anggota CHECK (role = 'anggota' OR must_change_password = 0);
```

| Langkah | Hasil |
| --- | --- |
| Pratinjau SQL offline | Hanya `ADD COLUMN` + `ADD CONSTRAINT` |
| Gladi di `sim_perpustakaan_test` berisi data demo | Hash isi 8 tabel identik sebelum/sesudah (kolom lama `users`, termasuk hash kata sandi); 0 akun ber-flag; CHECK menolak Admin (3819), mengizinkan Anggota |
| Suite test penuh | 166/166 |
| `sim_perpustakaan_db` (identitas `sim_perpus_app@127.0.0.1`, hak hanya DB sendiri) | 11 migration `Ran`; hash isi 8 tabel identik; 0 akun ber-flag; audit integritas Stage C tanpa pelanggaran baru |

## 4. Rute & middleware baru

| Rute | Middleware | Keterangan |
| --- | --- | --- |
| `GET /anggota/kata-sandi` (`anggota.kata-sandi`) | auth · auth.session · akun.aktif · wajib.ganti.sandi · peran:anggota | Halaman `Anggota/KataSandi` |
| `PUT /anggota/kata-sandi` (`anggota.kata-sandi.update`) | sama + `throttle:6,1` | `UbahKataSandiRequest` (Gate `ubah-kata-sandi-sendiri`) |

`App\Http\Middleware\WajibGantiKataSandi` (`wajib.ganti.sandi`) di grup rute terlindungi:

- Berlaku hanya untuk Anggota dengan flag `true` **dan** rute `anggota.*`.
- Diizinkan: `anggota.kata-sandi`, `anggota.kata-sandi.update`; `POST /logout` berada di luar grup sehingga selalu tersedia.
- Lainnya: HTML/Inertia → redirect ke `/anggota/kata-sandi` (+ pesan); JSON → 403 `{wajib_ganti_kata_sandi: true}` tanpa data halaman.
- Rute Admin tidak disentuh: Anggota tetap mendapat 403 dari `peran:admin`; Admin tidak pernah terdampak.
- Tanpa loop: halaman tujuan diizinkan; setelah login dan pada `/login` (guest redirect) Anggota ber-flag langsung diarahkan ke `/anggota/kata-sandi`.
- Pemeriksaan status keanggotaan (`akun.aktif`) tetap berjalan lebih dulu.

## 5. Ganti kata sandi mandiri Anggota

- Isian: Kata Sandi Saat Ini, Kata Sandi Baru, Konfirmasi (komponen bersama `FormUbahKataSandi.vue`, juga dipakai halaman Admin).
- Server: kata sandi saat ini diverifikasi (`current_password:web`), baru 6–64 karakter, harus berbeda, konfirmasi cocok, 6 percobaan/menit.
- Berhasil: hash baru, `must_change_password = false`, sesi lain dihapus/ditolak (`auth.session`), sesi ini diregenerasi (`regenerate(true)`), redirect ke **Dashboard Anggota**.
- Isian dan default formulir dikosongkan (perlindungan Inertia v3 dari Phase 8C dipindah ke komponen bersama).
- Saat wajib: pemberitahuan "Ganti kata sandi terlebih dahulu", tanpa tombol Batal. Tautan "Ubah Kata Sandi" tersedia di footer sidebar untuk Admin dan Anggota.
- Prop sesi `auth.akun.wajibGantiKataSandi` (boolean, Anggota saja) hanya untuk tampilan; penegakan sepenuhnya di server.

## 6. Audit log

| Aksi | Aktor | Subjek | Metadata |
| --- | --- | --- | --- |
| `anggota.akses_login.dibuat` | Admin | member | kode anggota, username |
| `anggota.kata_sandi.direset` | Admin | member | kode anggota |
| `akun.kata_sandi.diubah` | akun itu sendiri | user | peran |
| `anggota.wajib_ganti_kata_sandi.selesai` | Anggota itu sendiri | user | — |

Tidak pernah memuat kata sandi, hash, atau rahasia (diuji bersama log Laravel).

## 7. Pengujian

| Lapisan | Hasil |
| --- | --- |
| `tests/Feature/Kredensial/WajibGantiKataSandiTest.php` | 16 test: flag saat provisioning & reset, blokir semua rute Anggota (HTML, Inertia ber-versi, JSON, parameter manipulasi), halaman ganti & logout tanpa loop, rute Admin tetap 403, status Nonaktif tetap mengakhiri sesi, validasi (lama salah, konfirmasi, sama, panjang), throttle 429, sukses (flag hilang, lama gagal, baru berhasil, regenerasi sesi), sesi lain berakhir (driver database), Admin tak terdampak + CHECK, flag tidak fillable, audit lengkap tanpa rahasia, props tanpa data sensitif, SSR nonaktif, storage lokal 404, kolom & default |
| Test 8A–8C | Diperbarui sesuai kebijakan (login setelah provisioning/reset → halaman ganti kata sandi; prop `wajibGantiKataSandi`; rute tulis baru `PUT anggota/kata-sandi`) — semua lulus |
| Regresi | `php artisan test` 166/166 · `npm run test:data` 211/211 · `npm run build` OK |
| QA browser (DB uji, kredensial sementara, server dev Vite tetap aktif, tanpa override SSR) | 34/34 cek |

## 8. Risiko tersisa

1. Aksi tulis non-kredensial (Anggota, Buku, transaksi, booking) tetap ditolak eksplisit — lingkup Phase 9.
2. Halaman 403 masih tampilan bawaan Laravel.
3. `SESSION_SECURE_COOKIE=true` + HTTPS wajib sebelum deployment; belum ada batas global percobaan masuk per IP.
4. `SEED_ADMIN_PASSWORD` masih ada di `.env` lokal — pensiunkan dengan prosedur dokumen 8C §8 setelah Admin mengganti kata sandi lewat UI.
