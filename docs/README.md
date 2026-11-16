# Dokumentasi Teknis Sistem Informasi Manajemen Perpustakaan (SIM Perpustakaan)

Repositori ini memuat dokumentasi arsitektur, basis data, keamanan, fitur operasional, kinerja, pengujian kualitas, dan analisis sistem untuk aplikasi **SIM Perpustakaan**.

---

## Struktur Direktori Dokumentasi

```text
docs/
├── KEPUTUSAN_DESAIN.md           # Keputusan arsitektur, batasan sistem, dan aturan bisnis
├── README.md                     # Indeks dan panduan navigasi dokumentasi ini
├── analisis/                     # Analisis kebutuhan, matriks keterlacakan SRS & UML
│   ├── SRS_TRACEABILITY_MATRIX.md
│   ├── UML_COMPLIANCE_MATRIX.md
│   ├── mock-data.md
│   └── uml-activity-sequence.md
├── database/                     # Desain basis data dan DDL skema MySQL
│   ├── SKEMA_DATABASE.md
│   └── skema_ddl.sql
├── fitur/                        # Dokumentasi fitur fungsional sistem
│   ├── CRUD_ANGGOTA.md
│   ├── CRUD_BUKU_DAN_EKSEMPLAR.md
│   ├── KEDALUWARSA_BOOKING.md
│   ├── KONKURENSI_DAN_LOCKING.md
│   └── MANAJEMEN_TRANSAKSI.md
├── keamanan/                     # Arsitektur keamanan, otorisasi, dan enkripsi
│   ├── ADDENDUM_AKSES_LOGIN_ANGGOTA.md
│   ├── AUDIT_KEAMANAN.md
│   ├── AUTENTIKASI_DAN_AKSES.md
│   ├── INTEGRASI_DATA_SERVER.md
│   ├── MANAJEMEN_KREDENSIAL.md
│   └── PENGERASAN_KEAMANAN.md
├── kinerja/                      # Optimasi basis data, dashboard agregat, dan pelaporan
│   ├── AUDIT_KINERJA.md
│   ├── DASHBOARD_DAN_PELAPORAN.md
│   └── SPESIFIKASI_PELAPORAN.md
├── pengujian/                    # Jaminan mutu (QA), hasil uji, dan audit kepatuhan
│   ├── AUDIT_KEPATUHAN.md
│   ├── CHECKLIST_SUBMISI.md
│   ├── LAPORAN_QA_SISTEM.md
│   ├── LAPORAN_UJI_TRANSAKSI.md
│   └── REGRESI_STATUS_ANGGOTA_TUNGGAL.md
├── requirements/                 # Dokumen kebutuhan resmi (SRS)
│   └── Tugas_2_SRS_FINAL.pdf
├── screenshots/                  # Dokumentasi visual antarmuka sistem (26 halaman)
└── uml/                          # Diagram rancangan sistem UML (Draw.io PNG)
```

---

## Ringkasan Modul Utama

### 1. Arsitektur & Basis Data (`docs/database/` & `docs/KEPUTUSAN_DESAIN.md`)
- Menggunakan MySQL 8.4 LTS dengan engine InnoDB yang mendukung transaksi ACID penuh.
- Skema database mencakup 8 tabel utama: `users`, `members`, `books`, `book_copies`, `bookings`, `loans`, `returns`, dan `audit_logs`.
- Penyimpanan waktu terstandarisasi UTC di basis data dan ditampilkan dalam zona waktu WIB (`Asia/Jakarta`).

### 2. Autentikasi & Keamanan (`docs/keamanan/`)
- Memisahkan peran secara tegas antara **Admin Perpustakaan** dan **Anggota**.
- Autentikasi berbasis sesi server terenkripsi dengan proteksi token CSRF ganda.
- Password hashing modern (Argon2id/Bcrypt) tanpa kebocoran hash pada response props.
- Pembatasan brute-force menggunakan rate limiter terintegrasi.
- Alur wajib ganti kata sandi untuk akun anggota baru demi menjamin keamanan akun.

### 3. Sirkulasi Transaksi & Reservasi (`docs/fitur/`)
- Peminjaman buku langsung oleh Admin dengan penetapan tanggal jatuh tempo.
- Pengembalian buku yang secara otomatis memulihkan status eksemplar menjadi `Tersedia`.
- Siklus reservasi (booking) buku: Pengajuan $\rightarrow$ Persetujuan Admin (reservasi 48 jam) $\rightarrow$ Realisasi Peminjaman Fisik atau Pembatalan.
- Mekanisme penguncian baris pesimistik (`lockForUpdate`) untuk mencegah kondisi balapan (*race condition*) dan pemesanan ganda eksemplar.
- Penjadwal otomatis `bookings:expire` yang berjalan periodik untuk melepaskan reservasi yang tidak diambil tepat waktu.

### 4. Kinerja, Dashboard & Pelaporan (`docs/kinerja/`)
- Perhitungan ringkasan statistik Admin dan Anggota dihitung di level basis data menggunakan agregasi SQL efisien (`COUNT`, conditional `SUM`, `GROUP BY`).
- Modul pelaporan sirkulasi (Daftar Buku, Daftar Anggota, Peminjaman, dan Pengembalian) berbasis filter rentang kalender WIB yang dioptimalkan dengan indeks komposit.
- Tampilan antarmuka dilengkapi fitur pratinjau tabel dan layout cetak ramah peramban (`window.print()`).

### 5. Jaminan Mutu & Pengujian (`docs/pengujian/`)
- Pengujian otomatis backend menggunakan PHPUnit mencakup 270 skenario uji (3.466 assertions).
- Pengujian logika data frontend menggunakan Node.js Test Runner mencakup 245 pengujian.
- Pengujian perjalanan pengguna (*end-to-end*) otomatis menggunakan Chrome DevTools Protocol mencakup 23 skenario alur kerja nyata.

