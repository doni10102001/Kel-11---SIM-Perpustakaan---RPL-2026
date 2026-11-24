# Laporan Pengujian Sistem & Jaminan Mutu (Quality Assurance)

Sistem Informasi Manajemen Perpustakaan (SIM Perpustakaan)

| Aspek | Keterangan |
| --- | --- |
| Ruang Lingkup | Pengujian Backend, Frontend, Transaksi Database, Konkurensi & End-to-End |
| Lingkungan Pengujian | PHP 8.4, Laravel 13, MySQL 8.4 Engine (`sim_perpustakaan_test`), Node.js 20 |
| Hasil Pengujian Otomatis | PHPUnit: **270 Passed** · Node.js: **245 Passed** · Browser QA: **23/23 Passed** |
| Verifikasi Invarian Data | **0 Pelanggaran** (`PemeriksaInvarianData`) pada Database Development dan Test |

---

## 1. Matriks Hasil Pengujian Otomatis

### 1.1 PHPUnit Test Suite (Backend & Transaksi Database)
- **Total Test**: 270 pengujian
- **Total Assertion**: 3.466 assertions
- **Status**: 100% Passed (0 Failed, 0 Error, 0 Skipped)
- **Cakupan Pengujian**:
  1. *Unit*: Waktu perpustakaan, model Eloquent, enum status.
  2. *Database Skema & Invarian*: Foreign keys, check constraints, composite unique constraints, audit log schema.
  3. *Autentikasi & Sesi*: Login throttling, sesi per peran, proteksi CSRF, redirect login, regenerasi sesi.
  4. *Kredensial*: Password hashing (bcrypt), wajib ganti kata sandi (`must_change_password`), reset kata sandi oleh Admin.
  5. *CRUD Master Data*: Anggota CRUD, Buku & Eksemplar atomic CRUD, pencarian buku server-side dengan pagination.
  6. *Transaksi Terisolasi*:
     - Peminjaman langsung & pengembalian buku (termasuk pengembalian oleh anggota Nonaktif).
     - Pengajuan booking, persetujuan booking (reservasi tepat 48 jam), penolakan booking (alasan wajib), pembatalan booking.
     - Realisasi booking menjadi pinjaman (tepat satu peminjaman per booking).
  7. *Konkurensi & Locking*: Pengujian balapan transaksi dengan **dua koneksi MySQL independen**, simulasi lock wait timeout 1 detik, verifikasi urutan kunci baris (`members` $\rightarrow$ `bookings` $\rightarrow$ `book_copies` $\rightarrow$ `loans` $\rightarrow$ `returns`).
  8. *Kedaluwarsa Booking*: Penjadwal 5 menit (`bookings:expire`), pemrosesan batch, idempotensi, audit pemicu sistem (`actor_id = NULL`).
  9. *Dashboard & Pelaporan*: Agregat SQL murni, bounded queries, konversi zona waktu WIB ke interval UTC.

---

### 1.2 Node.js Test Suite (Frontend State & Client Validation)
- **Total Test**: 245 pengujian
- **Status**: 100% Passed (0 Failed, 0 Error, 0 Skipped)
- **Cakupan Pengujian**:
  1. *Master Data & State Machine*: Integritas status eksemplar, aturan transisi booking dan peminjaman.
  2. *Validasi Formulir*: Validasi kredensial, validasi tanggal jatuh tempo, validasi filter laporan.
  3. *Inertia Binding*: Panggilan `router.post/put/get` sebagai method objek, pencegahan submission ganda (`useTransaksiServer`).
  4. *Dashboard & Laporan Client*: Ketiadaan kalkulasi matematika dataset di browser, pembacaan prop server `ringkasan`.

---

### 1.3 End-to-End Browser QA (Headless Chrome via CDP)

> **Catatan koreksi (24 Nov 2026):** angka 23/23 adalah jumlah cek yang tercatat oleh `scripts/qa-browser-journey.mjs`, bukan jumlah langkah yang diverifikasi penuh. Pemeriksaan kode menunjukkan:
>
> - Hanya 7 cek memiliki assertion eksplisit.
> - 8 cek hanya mengklik tanpa memeriksa hasil, termasuk "kartu ringkasan" yang tercatat 0 kartu.
> - Langkah **Pengembalian** (Admin no. 6) dan **pembatalan booking** (Anggota no. 6) bersifat kondisional dan tidak pernah dijalankan.
>
> Daftar di bawah dipertahankan sebagai catatan asli. Hasil yang terverifikasi ada di [laporan pengujian Tugas 10](../TUGAS-10-FINAL/08-SOFTWARE-TESTING/SOFTWARE_TESTING_REPORT.md) §D.4 dan pada E2E demo 22/22.
- **Total Skenario Uji**: 23 verifikasi terotomatisasi
- **Status**: 100% Passed (0 Failed, 0 Error)
- **Perjalanan Pengguna Teruji**:
  - **Admin**:
    1. Login via `/login` dengan kredensial aman.
    2. Akses Dashboard: kartu ringkasan berbasis agregat SQL termuat, daftar ringkas 10 baris.
    3. Tambah Anggota `AG-999` sekaligus mengaktifkan akun login `siti.qa`.
    4. Tambah Buku `BK-999` beserta 2 eksemplar awal secara atomik.
    5. Buat Peminjaman langsung untuk eksemplar `BK-999`.
    6. Proses Pengembalian buku dan verifikasi status eksemplar kembali `Tersedia`.
    7. Verifikasi Booking: Setujui booking dan tetapkan deadline 48 jam.
    8. Verifikasi Laporan Dinamis: Validasi penolakan server saat rentang tanggal terbalik (`akhir < awal`), tampilkan laporan peminjaman rentang WIB, dan uji trigger cetak `window.print()`.
    9. Logout Admin dengan dialog konfirmasi.
  - **Anggota**:
    1. Login pertama akun baru `siti.qa` $\rightarrow$ tertahan middleware `wajib.ganti.sandi` dan diarahkan ke `/anggota/kata-sandi`.
    2. Ganti kata sandi awal menjadi kata sandi pribadi $\rightarrow$ dialihkan ke `/anggota/dashboard`.
    3. Member Dashboard: hanya memuat metrik pribadi milik `siti.qa` (tidak ada kebocoran data anggota lain).
    4. Cari Buku di katalog dengan pencarian server-side.
    5. Ajukan booking eksemplar `BK-999`.
    6. Status Booking: verifikasi booking baru muncul dan batalkan booking tersebut.
    7. History Peminjaman: periksa riwayat pribadi.
    8. Uji Otorisasi: Anggota mencoba membuka `/admin/dashboard` $\rightarrow$ ditolak dengan kode `403 Forbidden`.
    9. Logout Anggota.

---

## 2. Verifikasi Skenario Konsistensi (A–J)

| Skenario | Deskripsi | Status Uji |
| --- | --- | --- |
| **A** | Peminjaman langsung menambah Pinjaman Aktif dan mengurangi Eksemplar Tersedia. | **TERVERIFIKASI** |
| **B** | Pengembalian mengurangi Pinjaman Aktif, menambah Pinjaman Selesai, dan menambah Eksemplar Tersedia. | **TERVERIFIKASI** |
| **C** | Pengajuan booking menambah Booking Diajukan dan **tidak** mengurangi Eksemplar Tersedia. | **TERVERIFIKASI** |
| **D** | Persetujuan booking menambah Booking Disetujui, mengurangi Eksemplar Tersedia, dan menambah Eksemplar Dipesan. | **TERVERIFIKASI** |
| **E** | Penolakan booking mengurangi Booking Diajukan dan tidak mengubah status eksemplar. | **TERVERIFIKASI** |
| **F** | Realisasi booking mengubah booking menjadi Direalisasikan, membuat Pinjaman Aktif baru, dan mengubah eksemplar Dipesan menjadi Dipinjam. | **TERVERIFIKASI** |
| **G** | Pembatalan booking Disetujui melepas reservasi dan mengembalikan status eksemplar menjadi Tersedia. | **TERVERIFIKASI** |
| **H** | Kedaluwarsa booking otomatis mengubah status menjadi Kedaluwarsa dan melepas reservasi eksemplar menjadi Tersedia. | **TERVERIFIKASI** |
| **I** | Member Dashboard hanya merefleksikan transaksi milik anggota yang sedang login. | **TERVERIFIKASI** |
| **J** | Admin Laporan merefleksikan seluruh transaksi dalam rentang kalender WIB yang sesuai. | **TERVERIFIKASI** |

---

## 3. Laporan Dry-Run Kedaluwarsa Database Development (BP-001 & BP-006)

Sebagaimana diidentifikasi pada laporan Batch 2, pada database development (`sim_perpustakaan_db`) terdapat 2 rekaman booking Disetujui yang telah melewati batas 48 jam:

1. **BP-001**:
   - `member_id`: 2 (`AG-002`, Rizky Pratama)
   - `copy_id`: 2 (`EK-002`, status saat ini `Dipesan`)
   - `decided_at`: `2026-10-17 05:00:00 UTC`
   - `expires_at`: `2026-10-19 05:00:00 UTC` (melewati batas 28 hari)
   - *Status Kepemilikan*: Tidak ada loan aktif atau booking lain pada `EK-002`.
2. **BP-006**:
   - `member_id`: 3 (`AG-003`, Siti Aisyah Putri)
   - `copy_id`: 13 (`EK-013`, status saat ini `Dipesan`)
   - `decided_at`: `2026-11-06 05:00:00 UTC`
   - `expires_at`: `2026-11-08 05:00:00 UTC` (melewati batas 8 hari)
   - *Status Kepemilikan*: Tidak ada loan aktif atau booking lain pada `EK-013`.

### Rencana Aksi saat Perintah Dijalankan:
- `BP-001` $\rightarrow$ status menjadi `Kedaluwarsa`, `reserved_copy_id` menjadi `NULL`, `EK-002` menjadi `Tersedia`, audit log `booking.kedaluwarsa` dicatat.
- `BP-006` $\rightarrow$ status menjadi `Kedaluwarsa`, `reserved_copy_id` menjadi `NULL`, `EK-013` menjadi `Tersedia`, audit log `booking.kedaluwarsa` dicatat.

> [!IMPORTANT]
> Sesuai instruksi ketat, **perintah `bookings:expire` TIDAK DIJALANKAN pada database development** dalam Batch ini dan menunggu konfirmasi eksplisit dari pemilik sistem. Seluruh skenario pengujian kedaluwarsa dijalankan secara aman pada database uji `sim_perpustakaan_test`.

---

## 4. Audit Invarian Database

Pemeriksaan integritas relasional mendalam dijalankan dengan `App\Support\PemeriksaInvarianData` dan kueri audit:

- **Orphan Members**: 0
- **Orphan Copies**: 0
- **Duplicate Active Loans per Copy**: 0
- **Duplicate Approved Bookings per Copy**: 0
- **Double Returns**: 0
- **Discrepancy Decided vs Expires (+48 jam)**: 0
- **Inkonsistensi Status Eksemplar**: 0

Hasil integritas database: **100% KONSISTEN & VALID**.

