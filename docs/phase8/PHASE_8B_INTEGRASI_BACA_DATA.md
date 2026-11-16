# Phase 8B — Integrasi Baca Data MySQL & Pengerasan Privasi

SIM Perpustakaan · Laravel 13 · MySQL 8.4 · Inertia 3 / Vue 3

| Metadata | Nilai |
| --- | --- |
| Tanggal | 14 November 2026 |
| Branch | `phase-7a-8a-mysql-auth` — checkpoint 7A `bd8ca39`, 8A `26ae255`; Phase 8B belum di-commit |
| Lingkup | Hapus data mock sensitif dari aset publik; seluruh halaman membaca MySQL; otorisasi data di server |
| Di luar lingkup | Operasi tulis ke database (8C/8D), kredensial Anggota (8C), scheduler kedaluwarsa booking |

## 1. Temuan paparan data (sebelum 8B)

| Temuan | Dampak |
| --- | --- |
| `resources/js/data/mock/*` (10 anggota beserta kontak, 11 akun, 10 buku, 18 eksemplar, 8 booking, 10 peminjaman, 5 pengembalian) diimpor halaman → **dibundel ke `public/build`** | Siapa pun (tanpa login) dapat mengunduh nama, kontak, dan riwayat transaksi semua Anggota dari file JS publik |
| `config/demo.js` (akun pratinjau `AKUN_DEMO`, `ID_ADMIN_DEMO`) ikut dibundel | Nama/username Admin dan Anggota demo terbuka |
| Halaman Anggota memfilter data di browser dari dataset lengkap | Data Anggota lain sudah ada di memori browser Anggota mana pun |
| Aksi tulis mock menampilkan "berhasil" tanpa menyimpan ke database | UI bisa berbeda dari database |

## 2. Perbaikan privasi

- Dataset mock, store mock (`useDataPerpustakaan` lama), dan `config/demo.js` **dipindah ke `tests/js/fixtures/`** (tidak dibundel). Isinya identik dengan seed `database/seeders/data/perpustakaan-demo.json`; tetap dipakai test Node sebagai acuan aturan bisnis untuk implementasi tulis server.
- Helper murni tanpa data dipindah ke `resources/js/utils/` (`relasi.js`, `ringkasan.js`, barrel `olahData.js`, `galat.js`).
- Tidak ada prop bersama (shared) yang memuat data Anggota: prop bersama hanya `demo` (navigasi), `auth.akun` (identitas sendiri), `hariIni`, `flash`.
- Verifikasi build: tidak ada kontak/nama Anggota, nama akun, kode transaksi, maupun id akun mock di `public/build`; 0 aset usang (semua file dirujuk `manifest.json`; Vite mengosongkan `public/build` setiap build, dan folder ini di-ignore git). Dijaga `tests/js/privasi-data.test.js`.

## 3. Arsitektur baca

```
GET /{peran}/{halaman}  →  auth · auth.session · akun.aktif · peran:{peran}
   → HalamanController (Gate buka-halaman, validasi filter Laporan)
   → App\Queries\DataPerpustakaan (Eloquent + eager loading, cakupan per peran)
   → MySQL 8.4
   → prop Inertia `data` (bentuk sama dengan prototipe: kodeAnggota, judul, tanggalPinjam, ...)
   → composable useDataPerpustakaan() → komponen Vue yang sudah ada (tanpa desain ulang)
```

- Tanggal: kolom timestamp UTC dikirim sebagai tanggal kalender WIB (`WaktuPerpustakaan::tanggalLokal`); `due_date` (DATE) dikirim apa adanya. "Hari ini" di header, Dashboard, dan validator = `WaktuPerpustakaan::hariIni()` (prop bersama `hariIni`), menggantikan tanggal demo tetap 7 Nov 2026.
- Laporan: filter dikirim lewat query string (`?jenis=&awal=&akhir=`), divalidasi server, periode dikonversi batas hari WIB → UTC (`[00:00 WIB awal, 00:00 WIB akhir+1)`). Tanpa filter valid → tidak ada data dimuat.
- Tanpa N+1: jumlah query per halaman konstan terhadap jumlah data (diuji).

### Koleksi per halaman

| Halaman | Koleksi | Cakupan |
| --- | --- | --- |
| Admin: Dashboard, Data Anggota (+Tambah/Ubah), Data Buku (+Tambah/Ubah), Peminjaman, Pengembalian, Verifikasi/Realisasi Booking | pengguna, anggota, buku, eksemplar, peminjaman, pengembalian, booking | Seluruh perpustakaan (Admin) |
| Admin: Laporan Buku / Anggota / Peminjaman / Pengembalian | sesuai jenis; peminjaman/pengembalian tersaring periode di server | Seluruh perpustakaan |
| Anggota: Dashboard, History | anggota (diri sendiri), buku, eksemplar, peminjaman, pengembalian, booking | **Milik sendiri** + katalog |
| Anggota: Ajukan Booking, Status Booking | anggota (diri sendiri), buku, eksemplar, peminjaman, booking | **Milik sendiri** + katalog |
| Anggota: Cari Buku | buku, eksemplar | Katalog publik |

Field `pengguna` untuk Admin: `idPengguna` = username (bukan id internal), `passwordHash` selalu `null`. Pada halaman Anggota tidak ada `pengguna`, `diprosesOleh`, `diputuskanOleh`, maupun data Anggota lain.

## 4. Otorisasi data

1. Middleware rute (Phase 8A): `auth`, `auth.session`, `akun.aktif`, `peran:{peran}` → tamu 302/401, lintas peran 403.
2. `Gate::define('buka-halaman')` di controller (berlapis): peran akun = peran halaman **dan** akun layak masuk.
3. `DataPerpustakaan::untukHalaman()` menolak (`AuthorizationException`) bila peran akun ≠ peran halaman atau akun Anggota tanpa data Anggota.
4. Cakupan Anggota diturunkan dari `$akun->member` (sesi server). Query string seperti `?kodeAnggota=`, `member_id=`, `user_id=` diabaikan (diuji). Rute Anggota tidak memiliki parameter identitas.

## 5. Aksi tulis yang menunggu implementasi berikutnya

Semua aksi di bawah **ditolak eksplisit** (`utils/aksiTertunda.js`): pesan "Belum tersedia: penyimpanan aksi ini ke database dikerjakan pada tahap implementasi berikutnya. Tidak ada data yang diubah." Tidak ada perubahan state di browser, tidak ada pesan sukses, tidak ada perubahan MySQL. Tata letak tombol dan form tetap.

| Halaman | Aksi tertunda |
| --- | --- |
| ~~Admin · Tambah/Ubah Anggota~~ | *Tersimpan ke MySQL sejak Phase 9A (simpan, status, akses login saat tambah) — lihat `docs/phase9/PHASE_9A_CRUD_ANGGOTA.md`; kredensial sejak Phase 8C.* |
| ~~Admin · Tambah/Ubah Buku~~ | *Tersimpan ke MySQL sejak Phase 9B (simpan buku, tambah eksemplar) — lihat `docs/phase9/PHASE_9B_BUKU_EKSEMPLAR.md`.* |
| ~~Admin · Peminjaman / Pengembalian / Verifikasi / Realisasi Booking~~ | *Tersimpan ke MySQL sejak Batch 2 — lihat `docs/phase10/BATCH_2_TRANSACTION_MANAGEMENT.md`.* |
| ~~Anggota · Ajukan / Batalkan Booking~~ | *Tersimpan ke MySQL sejak Batch 2 (pemilik dari sesi).* |

Sejak Batch 2 tidak ada lagi aksi tertunda; `AKSI_TERTUNDA` kosong.

Aturan bisnis aksi-aksi ini tetap terdokumentasi dan diuji di `tests/js/fixtures/storeMock.js` (+ `tests/js/transaksi.test.js`, `master-data.test.js`, `anggota.test.js`, `akses-login.test.js`) sebagai spesifikasi implementasi server (transaksi + `lockForUpdate`, Phase 8 berikutnya).

## 6. Skrip screenshot (`npm run screenshots:stitch`)

Dipulihkan untuk rute terautentikasi: login lewat halaman Masuk per peran (browser terpisah), kredensial dari environment — **tidak ada kata sandi di source**:

```bash
STITCH_ADMIN_PASSWORD=... STITCH_ANGGOTA_PASSWORD=... \
  npm run screenshots:stitch -- --base=http://127.0.0.1:8014 --out=/tmp/stitch-uji
# opsional: STITCH_ADMIN_USERNAME (default admin), STITCH_ANGGOTA_USERNAME (default AG-001)
```

Jalankan terhadap database berisi data demo fiktif (mis. `sim_perpustakaan_test` yang di-seed sementara), bukan data pribadi nyata. Akun Anggota development berkata sandi acak (D-08), sehingga capture Anggota memerlukan akun uji sementara di database uji. Tanpa `--out`, skrip menimpa `docs/stitch/` (artefak ter-track) — lakukan hanya bila memang ingin memperbarui ekspor.

## 7. Pengujian

| Lapisan | Hasil |
| --- | --- |
| `tests/Feature/Data/DataHalamanTest.php` (MySQL uji, seed di dalam transaksi) | 13 test: Admin baca anggota/buku/transaksi dari MySQL tanpa hash, tanggal WIB, `hariIni` WIB, Anggota hanya booking & peminjaman sendiri, tidak dapat membaca data Anggota lain (5 halaman + manipulasi query), URL Admin/Laporan 403 untuk Anggota, Gate & query service berlapis, login Anggota dari DB lalu melihat datanya, filter Laporan batas WIB→UTC (tepi 00:00/05:15/23:59:59), filter tidak valid, query konstan (tanpa N+1) |
| `tests/js/privasi-data.test.js` | 6 test: tanpa data sensitif di kode frontend & build, tanpa impor fixture, tanpa aset usang, semua aksi tulis ditolak eksplisit, composable hanya membaca prop server |
| Regresi | `php artisan test` 129/129 · `npm run test:data` 205/205 · `npm run build` OK |
| QA browser (Chrome headless, server terhadap `sim_perpustakaan_test` berisi seed + kredensial sementara) | 35/35 cek; skrip screenshot 28/28 |

## 8. Risiko tersisa

1. Halaman Admin memuat seluruh koleksi operasional per halaman (volume demo kecil). Untuk data nyata perlu paginasi/penyaringan server per daftar (Data Anggota, Data Buku, Laporan Buku/Anggota).
2. Semua operasi tulis belum tersedia (lihat §5) — aplikasi belum dapat dipakai operasional.
3. Halaman 403 masih tampilan bawaan Laravel.
4. `SESSION_SECURE_COOKIE` wajib `true` + HTTPS sebelum deployment; belum ada batas global percobaan masuk per IP.
5. Akun Anggota development belum dapat masuk sampai Phase 8C (provisioning/reset oleh Admin).
6. Fitur mock "Akses Login Anggota" di form Anggota masih menampilkan isian kata sandi (tidak disimpan; aksi ditolak) sampai diganti Phase 8C.
