# Audit Keamanan & Model Otorisasi Sistem

Sistem Informasi Manajemen Perpustakaan (SIM Perpustakaan)

| Aspek | Keterangan |
| --- | --- |
| Ruang Lingkup | Autentikasi, Otorisasi Berbasis Peran (RBAC), Proteksi CSRF, Throttling, Proteksi Data Pribadi, Concurrency Locking |
| Kerangka Kerja | Laravel 13, MySQL 8.4 Engine (InnoDB) |

---

## 1. Arsitektur Pertahanan Berlapis (Defense-in-Depth)

Setiap permintaan yang masuk ke SIM Perpustakaan diproteksi oleh serangkaian middleware server-side yang ketat:

```text
HTTP Request
  │
  ▼
[1. StartSession / EncryptCookies] ──> Cookie aman (HttpOnly, SameSite=Lax, Secure di Prod)
  │
  ▼
[2. VerifyCsrfToken] ─────────────────> Validasi token CSRF otomatis per sesi Inertia
  │
  ▼
[3. Authenticate (auth)] ─────────────> Wajib login untuk seluruh rute internal
  │
  ▼
[4. AuthenticateSession (auth.session)]> Invalidasi instan sesi lama saat ganti password
  │
  ▼
[5. PastikanAkunAktif (akun.aktif)] ──> Tolak akses pengguna Nonaktif (redirect ke /login)
  │
  ▼
[6. WajibGantiKataSandi] ─────────────> Karantina akun baru/reset ke /anggota/kata-sandi
  │
  ▼
[7. OtorisasiPeran (peran:admin/anggota)] Tolak lintas peran dengan HTTP 403 Forbidden
  │
  ▼
[8. RateLimiter (throttle)] ──────────> Batasi brute-force login & operasi sensitif
  │
  ▼
[9. FormRequest / Gate] ──────────────> Validasi input ketat & otorisasi kepemilikan data
  │
  ▼
Controller & Service Layer
```

---

## 2. Audit Hak Akses & Isolasi Kepemilikan Data

### 2.1 Isolasi Anggota (Zero Cross-Member Leakage)
- **Sumber Kepemilikan Data**:
  Identitas anggota pada rute `/anggota/*` diambil **eksklusif** dari sesi server melalui `$request->user()->member`.
- **Penolakan Parameter Browser**:
  Parameter input seperti `member_id` atau `kodeAnggota` yang dikirim dari browser diabaikan secara tegas pada seluruh service transaksi (`LayananBooking`, `LayananPeminjaman`, `RingkasanPerpustakaan`).
- **Verifikasi Batas**:
  Upaya Anggota A untuk membatalkan atau melihat booking milik Anggota B menghasilkan pengecualian otorisasi (`AuthorizationException` / HTTP 403).

### 2.2 Hak Akses Eksklusif Administrator
- Rute-rute transaksi (`POST /admin/peminjaman`, `POST /admin/pengembalian`, `POST /admin/booking/*`) dilindungi oleh `peran:admin` dan gate `kelola-transaksi`.
- Pemroses transaksi (`staff_id`) dicatat dari `$request->user()->id`, di mana akun tersebut dipastikan memiliki kolom `role = 'admin'`.

---

## 3. Pencegahan Kebocoran Data (Data Exposure Audit)

1. **Hash Kata Sandi**:
   - Seluruh hash kata sandi dilindungi oleh `$hidden = ['password', 'remember_token']` pada model `User`.
   - Pengujian otomatis memindai seluruh representasi JSON dari Inertia props pada halaman Admin dan Anggota, memastikan tidak ada potongan hash database yang bocor ke browser.
2. **Kontak Pribadi Anggota**:
   - Kolom `contact` anggota hanya dikirim ke halaman master yang relevan (`admin/anggota`, form edit).
   - Halaman formulir transaksi (`Peminjaman`, `Realisasi`) dan laporan ringkasan menyembunyikan kontak anggota (`contact = NULL`).
3. **Pemberitahuan Galat**:
   - Pesan kegagalan login bersifat generik ("Identitas atau kata sandi tidak sesuai") untuk mencegah teknik *user enumeration*.
   - Detail trace database dan query SQL tidak pernah bocor ke tampilan pengguna (tertangani rapi di level exception handler).

---

## 4. Keamanan Konkurensi & Integritas Transaksi MySQL

Untuk mencegah kondisi balapan (*race condition*), pembacaan kotor (*dirty read*), atau pemesanan ganda eksemplar (*double reservation*), SIM Perpustakaan menerapkan aturan penguncian baris ketat:

1. **Urutan Penguncian Konsisten**:
   Setiap transaksi selalu mengunci baris dengan urutan tabel yang sama:
   $$\text{members} \longrightarrow \text{bookings} \longrightarrow \text{book\_copies} \longrightarrow \text{loans} \longrightarrow \text{returns}$$
   Urutan ini menjamin ketiadaan siklus *deadlock* antar-transaksi yang bersaing.
2. **Pessimistic Locking (`lockForUpdate`)**:
   Status eksemplar dan booking selalu dibaca ulang **setelah** kunci baris diperoleh di dalam blok transaksi InnoDB.
3. **Mekanisme Percobaan Ulang Otomatis**:
   Galat deadlock MySQL (1213) dan lock wait timeout (1205) ditangkap oleh `DasarTransaksi`, yang secara otomatis mencoba ulang eksekusi hingga 3 kali dengan jeda eksponensial acak sebelum menyerah.
4. **Pencegahan Double Return**:
   Tabel `returns` memiliki indeks `UNIQUE (loan_id)`, mencegah dua proses pengembalian paralel berhasil secara bersamaan pada tingkat mesin basis data.

---

## 5. Audit Log & Akuntabilitas

Setiap operasi transaksi dan perubahan otorisasi dicatat ke dalam tabel `audit_logs` di dalam transaksi MySQL yang sama:

- **Aksi Tercatat**: `peminjaman.dibuat`, `pengembalian.dibuat`, `booking.diajukan`, `booking.disetujui`, `booking.ditolak`, `booking.dibatalkan`, `booking.direalisasikan`, `booking.kedaluwarsa`, `anggota.dibuat`, `anggota.diubah`, `akun.kata_sandi.diubah`.
- **Aktor Sistem**: Kejadian yang dipicu oleh penjadwal (seperti kedaluwarsa booking) memiliki `actor_id = NULL` dengan metadata `pemicu = 'penjadwal'` (tidak menyamar sebagai administrator).
- **Sanitasi Metadata**: Metadata audit hanya mencatat kode bisnis dan penanda status; teks alasan penolakan, kata sandi, atau data kontak tidak pernah disimpan dalam log audit.

---

## 6. Rekomendasi Hardening untuk Lingkungan Produksi

Meskipun sistem belum dideploy, prasyarat konfigurasi produksi telah diidentifikasi:

1. `APP_DEBUG=false` dan `APP_ENV=production`.
2. `SESSION_SECURE_COOKIE=true` untuk menjamin transmisi cookie hanya melalui HTTPS.
3. Menjalankan cron job sistem untuk scheduler Laravel:
   ```cron
   * * * * * cd /path/to/sim-perpustakaan && php artisan schedule:run >> /dev/null 2>&1
   ```
4. Menggunakan user MySQL terpisah dengan prinsip hak istimewa terkecil (*least privilege*):
   - User aplikasi hanya memiliki hak `SELECT, INSERT, UPDATE, DELETE` pada tabel operasional.
   - Tidak memberikan hak `DROP, ALTER, TRUNCATE` kepada user runtime aplikasi.

