# Skenario Demo

Naskah ini sudah **dijalankan dan diverifikasi** pada 21 Nov 2026 (Chrome headless, database uji `sim_perpustakaan_test`). Setiap langkah yang mengubah data dicocokkan dengan kueri MySQL. Hasil: **22/22 langkah PASS**, 0 error JavaScript, 0 respons HTTP 5xx ([bukti JSON](bukti-skenario-demo-2026-11-21.json)).

Untuk demo langsung di depan dosen: jalankan aplikasi sesuai [PANDUAN_INSTALASI](PANDUAN_INSTALASI.md), lalu ikuti langkah di bawah. Pakai kode baru (mis. AG-902, BK-902) bila AG-901/BK-901 sudah ada di database demo Anda.

| # | Aktor | Langkah | Hasil yang diharapkan | Hasil terverifikasi 21 Nov | Screenshot |
| --- | --- | --- | --- | --- | --- |
| 1 | Admin | Masuk sebagai `admin` | Dashboard Admin dengan kartu ringkasan | `/admin/dashboard`, 5 kartu ringkasan | [01](screenshots/admin/01-dashboard.png) |
| 2 | Admin | Data Anggota → Tambah Anggota: AG-901 "Dimas Saputra", aktifkan Akses Login (username `dimas.demo`, kata sandi awal) → Simpan | Anggota Aktif + akun login, wajib ganti kata sandi | DB: `members.status=Aktif`, `users.role=anggota`, `must_change_password=1` | [03](screenshots/admin/03-tambah-anggota.png) · [04](screenshots/admin/04-anggota-tersimpan.png) |
| 3 | Admin | Data Buku → Tambah Buku BK-901 "Pengantar Basis Data Relasional" + eksemplar EK-901, EK-902 → Simpan | Buku & 2 eksemplar Tersedia tersimpan atomik | DB: 1 buku, EK-901/EK-902 = Tersedia | [07](screenshots/admin/07-tambah-buku.png) · [08](screenshots/admin/08-buku-tersimpan.png) |
| 4 | Anggota | Keluar sebagai Admin, masuk sebagai `dimas.demo` → ganti kata sandi | Diarahkan ke Ubah Kata Sandi, lalu Dashboard | `/anggota/kata-sandi` → `/anggota/dashboard` | [01](screenshots/anggota/01-wajib-ubah-kata-sandi.png) · [02](screenshots/anggota/02-dashboard.png) |
| 5 | Anggota | Cari Buku "Basis Data" → Detail → Ajukan Booking EK-901 → konfirmasi | Booking **Diajukan**; eksemplar **tetap Tersedia** | DB: BP-10429 Diajukan, EK-901 Tersedia | [03](screenshots/anggota/03-cari-buku.png) · [05](screenshots/anggota/05-ajukan-booking.png) · [06](screenshots/anggota/06-booking-diajukan.png) |
| 6 | Admin | Verifikasi Booking → Detail BP-… → **Setujui** | Booking Disetujui; eksemplar Dipesan; Batas Pengambilan = persetujuan + 48 jam | DB: Disetujui, `TIMESTAMPDIFF(HOUR, decided_at, expires_at)=48`, EK-901 Dipesan | [11](screenshots/admin/11-verifikasi-booking.png) · [13](screenshots/admin/13-booking-disetujui.png) |
| 7 | Admin | Realisasi Booking → pilih BP-… → Tanggal Jatuh Tempo 28 Nov 2026 → Realisasikan | Peminjaman Aktif dibuat; booking Direalisasikan; eksemplar Dipinjam | DB: PJ-14229 Aktif (`booking_id` terisi), booking Direalisasikan, EK-901 Dipinjam | [14](screenshots/admin/14-realisasi-booking.png) · [15](screenshots/admin/15-bukti-realisasi.png) |
| 8 | Admin | Pengembalian Buku → cari PJ-… → Proses Pengembalian | Peminjaman Selesai; eksemplar Tersedia | DB: PJ-14229 Selesai, KB-9172 tercatat, EK-901 Tersedia | [16](screenshots/admin/16-pengembalian.png) · [17](screenshots/admin/17-pengembalian-berhasil.png) |
| 9 | Admin | Laporan → Peminjaman / Pengembalian, periode 1–30 Nov 2026 → Tampilkan (→ Cetak) | Transaksi demo muncul di laporan | PJ-14229 dan KB-9172 tampil | [18](screenshots/admin/18-laporan-peminjaman.png) · [19](screenshots/admin/19-laporan-pengembalian.png) |
| 10 | Anggota | Masuk lagi sebagai `dimas.demo` → History Peminjaman | Riwayat memuat peminjaman berstatus **Selesai** | PJ-14229 Selesai; Status Booking = Direalisasikan | [08](screenshots/anggota/08-history-peminjaman.png) · [09](screenshots/anggota/09-status-booking-akhir.png) |
| + | Anggota | Buka `/admin/dashboard` langsung dari address bar | Ditolak | 403 "Anda tidak memiliki akses ke halaman ini." | [keamanan](screenshots/keamanan/01-anggota-akses-admin-ditolak.png) |

## Kebijakan kedaluwarsa booking 48 jam

- Saat Admin **menyetujui** booking, sistem menyimpan `decided_at` dan `expires_at = decided_at + 48 jam` (UTC). UI menampilkannya sebagai **Batas Pengambilan** dalam WIB. Eksemplar berubah menjadi **Dipesan**.
- Jika booking belum direalisasikan sampai batas itu, statusnya menjadi **Kedaluwarsa**, `expired_at` diisi waktu pemrosesan, dan eksemplar dilepas kembali ke **Tersedia** (kecuali eksemplar itu dimiliki transaksi sah lain). Kejadian dicatat di `audit_logs` dengan aktor = sistem.
- Pemrosesan dijalankan oleh perintah `php artisan bookings:expire` **setiap 5 menit** (`routes/console.php`, tanpa tumpang tindih). Secara lokal jalankan `php artisan schedule:work`.
- **Pengaman bila penjadwal tertunda:** peminjaman, persetujuan, realisasi, dan pembatalan selalu memeriksa ulang batas waktu di dalam transaksi terkunci. Realisasi setelah batas ditolak dan booking langsung dikedaluwarsakan.
- Kebijakan yang **belum** ditetapkan (TBD-06): masa berlaku booking yang masih **Diajukan**, kuota, dan antrean.
