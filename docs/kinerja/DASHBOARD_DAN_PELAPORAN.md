# Dashboard Analitik & Modul Pelaporan Berbasis Agregasi Basis Data

Sistem Informasi Manajemen Perpustakaan (SIM Perpustakaan)

| Aspek | Keterangan |
| --- | --- |
| Modul | Dashboard Ringkasan & Pelaporan Dinamis |
| Teknologi | Laravel 13, MySQL 8.4 InnoDB, Vue 3, Inertia.js |
| Mekanisme Kueri | Agregasi Database Server-Side (`RingkasanPerpustakaan`, `LaporanPerpustakaan`), Indeks `idx_loans_status_due` |
| Dokumen Terkait | `AUDIT_KINERJA.md`, `SPESIFIKASI_PELAPORAN.md`, `../pengujian/LAPORAN_QA_SISTEM.md` |

---

## 1. Latar Belakang & Optimalisasi Sistem

Pada tahap pengembangan prototipe awal, ringkasan dashboard Admin dan Anggota dihitung di sisi klien (browser) dengan memuat seluruh koleksi transaksi dan entitas master ke memori JavaScript. Hal ini menimbulkan sejumlah kelemahan mendasar:

1. **Skalabilitas**: Seiring bertambahnya volume transaksi (ribuan peminjaman dan booking), memuat seluruh tabel ke browser mengakibatkan ukuran payload transfer membengkak, waktu parsing DOM melonjak, dan potensi kebocoran data kontak anggota.
2. **Konsistensi Data**: Perhitungan status transaksi di browser rawan desinkronisasi bila data booking kedaluwarsa atau peminjaman selesai tidak dihitung secara real-time dari database engine.
3. **Ketiadaan Laporan Terfilter di Server**: Halaman laporan Admin hanya melakukan filter visual atas dataset yang sudah diunduh, bukan kueri terarah ke MySQL berdasarkan batas waktu UTC.

Optimalisasi sistem menyelesaikan seluruh kelemahan di atas dengan memindahkan perhitungan agregat secara penuh ke **MySQL 8.4**, membatasi payload data ringkas, dan mengimplementasikan pelaporan dinamis server-side.

---

## 2. Arsitektur Agregasi SQL Server-Side

```text
                               +----------------------------+
                               |     Browser (Vue 3)        |
                               |  Hanya Menampilkan Props   |
                               +--------------+-------------+
                                              |
                                     Inertia Request
                                              |
                                              v
                               +----------------------------+
                               |     HalamanController      |
                               +--------------+-------------+
                                              |
                                              v
                               +----------------------------+
                               |      DataPerpustakaan      |
                               +-------+------------+-------+
                                       |            |
                     +-----------------+            +-----------------+
                     v                                                v
       +----------------------------+                   +----------------------------+
       |   RingkasanPerpustakaan    |                   |    LaporanPerpustakaan     |
       |  - admin(): Agregat Global |                   |  - buku(): Agregat Buku    |
       |  - anggota(): Scoped Sesi  |                   |  - anggota(): Agregat Agt  |
       |  - barisPeminjamanAktif()  |                   |  - peminjaman(WIB -> UTC)  |
       |  - barisBookingBerjalan()  |                   |  - pengembalian(WIB -> UTC)|
       +-------------+--------------+                   +-------------+--------------+
                     |                                                |
                     +------------------------+-----------------------+
                                              |
                                     Kueri SQL Teroptimasi
                                     (COUNT, SUM, GROUP BY)
                                              |
                                              v
                               +----------------------------+
                               |      MySQL 8.4 Engine      |
                               |    InnoDB + Index Scan     |
                               +----------------------------+
```

---

## 3. Implementasi Admin Dashboard (`RingkasanPerpustakaan::admin`)

Admin Dashboard menyajikan gambaran operasional perpustakaan secara komprehensif tanpa memuat tabel mentah ke memori:

### 3.1 Metrik Ringkasan (Kueri Agregat Tunggal per Entitas)
- **Anggota**:
  ```sql
  SELECT COUNT(*) AS total,
         COALESCE(SUM(status = 'Aktif'), 0) AS aktif,
         COALESCE(SUM(status = 'Nonaktif'), 0) AS nonaktif
  FROM members;
  ```
- **Buku**:
  ```sql
  SELECT COUNT(*) AS total,
         COUNT(DISTINCT category) AS kategori
  FROM books;
  ```
- **Eksemplar**:
  ```sql
  SELECT COUNT(*) AS total,
         COALESCE(SUM(status = 'Tersedia'), 0) AS tersedia,
         COALESCE(SUM(status = 'Dipesan'), 0) AS dipesan,
         COALESCE(SUM(status = 'Dipinjam'), 0) AS dipinjam
  FROM book_copies;
  ```
- **Peminjaman**:
  ```sql
  SELECT COALESCE(SUM(status = 'Aktif'), 0) AS aktif,
         COALESCE(SUM(status = 'Selesai'), 0) AS selesai,
         COALESCE(SUM(loaned_at >= ? AND loaned_at < ?), 0) AS hari_ini
  FROM loans;
  ```
- **Booking**:
  Dihitung menggunakan conditional `GROUP BY status` pada tabel `bookings`, memastikan status resmi (`Diajukan`, `Disetujui`, `Kedaluwarsa`, `Ditolak`, `Dibatalkan`, `Direalisasikan`) selalu tersedia (nilai 0 bila tidak ada baris).
- **Aktivitas Hari Ini (Kalender WIB)**:
  Batas hari ini dihitung menggunakan `WaktuPerpustakaan::rentangHariUtc()` menghasilkan rentang setengah-terbuka UTC `[mulai 00:00 WIB, sebelum 24:00 WIB)`. Diterapkan pada `loans.loaned_at`, `returns.returned_at`, dan `bookings.submitted_at`.

### 3.2 Daftar Ringkas Operasional (Bounded Datasets)
- **Peminjaman Aktif**:
  - Dibatasi maksimum 10 baris (`LIMIT 10`) dengan urutan `due_date ASC, loan_code ASC` (jatuh tempo terdekat).
  - Menggunakan eager loading relasi spesifik: `member:id,member_code,name`, `copy:id,book_id,copy_code`, `copy.book:id,title`.
  - Memanfaatkan indeks komposit baru: `idx_loans_status_due` pada `loans(status, due_date, loan_code)` sehingga kueri berjalan dengan **Zero Filesort** (type `ref`, Extra `NULL`).
  - Tidak memuat kontak anggota (`contact`) untuk menjaga privasi.
- **Booking Berjalan**:
  - Dibatasi maksimum 10 baris (`LIMIT 10`) untuk status `Diajukan` dan `Disetujui`.
  - Diurutkan dengan prioritas: `CASE status WHEN 'Diajukan' THEN 0 ELSE 1 END, submitted_at ASC` (perlu tindak lanjut lebih dulu).
  - Mengirimkan `batasPengambilan` dalam format ISO UTC (`Z`) yang dirender dalam zona waktu WIB oleh Vue.

---

## 4. Implementasi Member Dashboard (`RingkasanPerpustakaan::anggota`)

Member Dashboard menerapkan **Zero Trust Client Input**:

1. **Isolasi Identitas**:
   - `member_id` diambil mutlak dari relasi akun terautentikasi di server: `$akun->member`.
   - Parameter URL atau payload request seperti `?member_id=X` atau `?kodeAnggota=Y` diabaikan sepenuhnya dan tidak pernah dijadikan sumber kebenaran.
2. **Statistik Personal**:
   - `bookingAktif`: Jumlah booking berstatus `Diajukan` + `Disetujui` milik anggota yang bersangkutan.
   - `bookingDiajukan`, `bookingDisetujui`, `bookingKedaluwarsa`: Rincian per status booking milik anggota.
   - `peminjamanAktif`: Jumlah pinjaman berstatus `Aktif` milik anggota.
   - `riwayatSelesai`: Jumlah pinjaman berstatus `Selesai` milik anggota.
3. **Aktivitas Terkini Anggota**:
   - Seluruh peminjaman aktif milik anggota (tanpa batas 10, karena volume per anggota dibatasi aturan bisnis).
   - Seluruh booking berjalan milik anggota dengan informasi batas waktu pengambilan.
   - Bebas dari kebocoran data anggota lain (nama, kontak, kode eksemplar transaksi lain tidak pernah masuk payload).

---

## 5. UI/UX & Konsistensi Tampilan

1. **Tata Letak Kartu**: Semua kartu ringkasan visual pada desain antarmuka dipertahankan persis.
2. **Tautan Navigasi**:
   - Kartu Anggota menaut ke `admin.anggota.index`.
   - Kartu Buku menaut ke `admin.buku.index`.
   - Kartu Peminjaman Aktif menaut ke `admin.pengembalian`.
   - Kartu Booking Diajukan menaut ke `admin.booking.verifikasi`.
3. **Kosakata Status**:
   - Badge status booking menampilkan label `Kedaluwarsa` secara konsisten dengan ikon netral.
   - Deadline pengambilan hanya tampil pada status `Disetujui`.

---

## 6. Verifikasi & Pengujian

- Kueri agregat diuji pada `tests/Feature/Dashboard/DashboardLaporanTest.php`:
  - `test_ringkasan_admin_sama_dengan_isi_mysql_untuk_semua_status`: Memastikan seluruh nilai kartu sama persis dengan `COUNT` MySQL.
  - `test_dashboard_admin_memakai_agregat_dan_jumlah_query_tetap`: Memverifikasi jumlah kueri SQL tidak bertambah (bounded) saat data bertambah 25 peminjaman.
  - `test_dashboard_anggota_hanya_data_miliknya`: Memverifikasi ketiadaan kebocoran data antar-anggota.
- Pengujian unit JavaScript (`tests/js/dashboard-laporan.test.js`) memverifikasi bahwa Vue murni membaca prop `data.ringkasan` tanpa melakukan perhitungan array/looping dataset di browser.

