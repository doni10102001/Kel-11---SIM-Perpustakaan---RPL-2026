# Phase 9A — CRUD Anggota Nyata (MySQL)

SIM Perpustakaan · Laravel 13 · MySQL 8.4 · Inertia 3 / Vue 3

| Metadata | Nilai |
| --- | --- |
| Tanggal | 15 November 2026 |
| Branch | `phase-7a-8a-mysql-auth` — 8D `e6a6cdc`; 9A `bfa430d` (implementasi antara) + commit koreksi `fix: send member saves via bound Inertia router and correct member tests` |
| Lingkup | Tambah Anggota (opsional dengan akses login), ubah profil, aktif/nonaktif, F-01 opsi A |
| Di luar lingkup | Buku/eksemplar, transaksi, booking, scheduler, hapus fisik, registrasi mandiri |
| Migration baru | **Tidak ada** (tabel `members`, `users`, `audit_logs` yang ada) |

## 1. Arsitektur

```text
Form Tambah/Ubah Anggota (Inertia router.post / router.put)
  → auth · auth.session · akun.aktif · wajib.ganti.sandi · peran:admin · throttle:30,1 · CSRF
  → TambahAnggotaRequest / UbahAnggotaRequest (validasi + Gate kelola-anggota)
  → AnggotaController
  → App\Services\LayananAnggota (DB::transaction, lockForUpdate)  ──► KredensialAkun (akses login, Phase 8C/8D)
  → Eloquent → MySQL members / users / audit_logs / sessions
  → redirect Data Anggota + flash (setelah commit)
```

| Rute | Nama | Request |
| --- | --- | --- |
| `POST /admin/anggota` | `admin.anggota.store` | `TambahAnggotaRequest` |
| `PUT /admin/anggota/{kodeAnggota}` | `admin.anggota.update` | `UbahAnggotaRequest` |

## 2. Tambah Anggota

- Field UI yang ada: Kode Anggota, Nama, Kontak, Status Anggota (+ Akses Login opsional).
- Validasi server: Kode wajib, ≤ 20 karakter, pola `[A-Za-z0-9._-]` (sama dengan parameter rute agar halaman Ubah selalu dapat dibuka), unik tanpa membedakan huruf; Nama ≤ 100; Kontak ≤ 50; Status `Aktif|Nonaktif` (biner, persis).
- **Kode Anggota diisi Admin** (konvensi UI yang disetujui; tidak ada pembangkitan otomatis). Keunikan: validasi + `uq_members_member_code`; bentrok balapan (1062) diterjemahkan menjadi galat validasi, bukan SQL.
- Kirim ganda: permintaan kedua ditolak "Kode Anggota sudah digunakan", tidak ada duplikasi; tombol Simpan dinonaktifkan selama proses.

## 3. Akses login opsional saat Tambah

- Tanpa akses: hanya Anggota, `members.user_id = NULL`.
- Dengan akses (`buatAkses`): username (3–30, unik terhadap semua akun), kata sandi 6–64 + konfirmasi. Anggota dan akun dibuat **dalam satu transaksi** lewat `KredensialAkun::buatAksesLogin` (`role=anggota`, `name=NULL`, `is_active=true`, `must_change_password=true`). Kegagalan akun → Anggota ikut di-rollback (diuji).
- Pada **Ubah Anggota**, akses login & reset kata sandi tetap aksi eksplisit terpisah (Phase 8C); Simpan Anggota tidak pernah membawa kata sandi.

## 4. Ubah profil

- Dapat diubah: Nama, Kontak, Status. **Tidak dapat**: Kode Anggota (dari URL), akun tertaut, username, hash kata sandi, `users.name`.
- Field istimewa dari klien (`role`, `user_id`, `is_active`, `must_change_password`, `member_code`, `password`) diabaikan (diuji).
- Riwayat transaksi tetap tertaut (ID Anggota tidak berubah). Tanpa perubahan → tanpa audit.

## 5. Aktifkan / nonaktifkan

- Satu Status Anggota (D-03); tidak ada Status Akses.
- Nonaktif: login ditolak; sesi lama ditolak pada request berikutnya (`akun.aktif`) dan **baris sesi database akun itu dihapus**. Akun dan kata sandi tidak dihapus; booking/peminjaman tidak diubah.
  - Catatan UX: karena baris sesi dihapus, request berikutnya diperlakukan sebagai tamu → halaman Masuk **tanpa** pesan "Keanggotaan Anda sedang nonaktif" (pesan tetap muncul saat mencoba masuk kembali).
- Aktif kembali: kredensial lama berlaku lagi; `must_change_password` yang masih berlaku tetap ditegakkan (diuji).

## 6. F-01 opsi A dan penguncian

- Penonaktifan **ditolak** bila Anggota memiliki booking berstatus `Disetujui`: "Anggota tidak dapat dinonaktifkan karena masih memiliki booking Disetujui. Selesaikan booking tersebut terlebih dahulu." Status & profil tidak berubah (seluruh transaksi dibatalkan), booking tidak dibatalkan, reservasi eksemplar tidak dilepas, tidak ada audit.
- Hanya `Disetujui` yang memblokir; `Diajukan`, `Ditolak`, `Dibatalkan`, dan peminjaman Aktif **tidak** (tidak ada aturan tambahan yang disetujui). Mengaktifkan kembali selalu boleh.
- Protokol: `DB::transaction` → `SELECT … FROM members … FOR UPDATE` (kueri pertama) → baca booking `Disetujui` → tulis. Diverifikasi lewat log kueri (urutan member → booking).

### Jaminan konkurensi

| Jaminan | Status |
| --- | --- |
| Baris member dikunci sebelum pemeriksaan booking (urutan member → booking) | **Diuji** (log kueri) |
| Kode Anggota ganda saat balapan → galat validasi (UNIQUE DB) | **Diuji** (melewati Form Request) |
| Username ganda saat balapan → galat validasi (UNIQUE DB, Phase 8C) | Diuji di 8C |
| Dua akun untuk satu Anggota (`uq_members_user_id` + kunci member) | Diuji di 8C |
| Persetujuan booking vs penonaktifan bersamaan (dua koneksi) | **Belum diuji** — membutuhkan alur persetujuan (Phase 11) dan harness dua koneksi dengan data ter-commit |

### Kewajiban Phase 11 (persetujuan booking)

1. Satu transaksi; **kunci `members` lebih dulu** (`lockForUpdate`) untuk anggota pemilik booking, lalu `book_copies` (urut ID), lalu booking.
2. Setelah kunci dipegang, baca ulang: tolak bila `members.status = Nonaktif` ("Booking tidak dapat disetujui karena status anggota Nonaktif.").
3. Jangan pernah mengunci booking sebelum member (hindari deadlock dengan penonaktifan).
4. Uji dua transaksi bersamaan: persetujuan vs penonaktifan untuk anggota yang sama.

## 7. Audit log

| Aksi | Kapan | Metadata |
| --- | --- | --- |
| `anggota.dibuat` | Tambah | kode anggota, status |
| `anggota.akses_login.dibuat` | Tambah dengan akses / Buat Akses Login | kode anggota, username |
| `anggota.diubah` | Nama/kontak berubah | kode anggota, **nama kolom** yang berubah (tanpa nilai) |
| `anggota.dinonaktifkan` / `anggota.diaktifkan` | Status berubah | kode anggota |

Aktor = Admin dari sesi; ditulis dalam transaksi yang sama (transaksi gagal → tanpa audit, diuji). Tidak ada kata sandi, hash, token, atau kontak.

## 8. Frontend

- Simpan Anggota (Tambah/Ubah) → server; saklar Akses Login aktif kembali di Tambah.
- Galat per field dari server; isian non-sensitif dipertahankan; kata sandi selalu dikosongkan; kirim ganda dicegah.
- Data Anggota menampilkan pesan sukses dari flash server (bukan lagi pesan dari browser) dan data terbaru dari MySQL.
- **Bug ditemukan QA browser & diperbaiki:** `const kirim = router.post` memisahkan method dari objek Inertia (`this` hilang) sehingga tidak ada request yang terkirim; kini `router[metode](…)`, dijaga `tests/js/kredensial.test.js`.

## 9. Pengujian

| Lapisan | Hasil |
| --- | --- |
| `tests/Feature/Anggota/AnggotaCrudTest.php` (MySQL uji) | 22 test mencakup butir 1–30 rencana uji (tambah ± akses, keunikan & balapan, validasi, rollback, ubah profil, riwayat & hash tetap, aktif/nonaktif, sesi, F-01 + urutan kunci, otorisasi, CSRF, injeksi field, audit, halaman baca, alur kredensial lama) |
| Regresi | `php artisan test` 188/188 · `npm run test:data` 211/211 · `npm run build` OK |
| QA browser (DB uji, kredensial sementara) | 30/30 cek |

## 10. Aksi yang masih dinonaktifkan

Tambah/Ubah Buku & eksemplar, Catat Peminjaman, Proses Pengembalian, Setujui/Tolak/Realisasi Booking, Ajukan/Batalkan Booking — tetap ditolak jujur ("Belum tersedia … Tidak ada data yang diubah").

## 11. Temuan & kebijakan terbuka

1. Banner atas formulir memakai judul generik "Data anggota belum valid" juga untuk penolakan F-01 (pesan spesifik ada di field Status).
2. Kebijakan v0.9 §7 butir 5 (pengembalian tetap dapat diproses untuk Anggota Nonaktif) masih "perlu konfirmasi" — relevan untuk Phase pengembalian.
3. Data lama yang sudah melanggar F-01 (bila ada) tidak diperbaiki otomatis; `PemeriksaInvarianData` melaporkannya.
