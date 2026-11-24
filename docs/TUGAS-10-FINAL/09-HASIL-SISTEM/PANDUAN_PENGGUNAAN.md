# Panduan Penggunaan

Waktu ditampilkan dalam **WIB**. Semua aksi yang mengubah data meminta konfirmasi, lalu menampilkan pesan sukses atau alasan penolakan.

## A. Admin

| Menu | Langkah utama | Aturan yang ditegakkan server | Screenshot |
| --- | --- | --- | --- |
| **Masuk** | Isi identitas pengguna & kata sandi → Masuk | 5 percobaan gagal / 60 detik; pesan gagal generik; akun nonaktif ditolak | [00-login](screenshots/00-login.png) |
| **Dashboard** | Lihat ringkasan anggota, buku, eksemplar, transaksi, booking; tautan ke peminjaman aktif & booking yang perlu tindak lanjut | Dihitung dengan agregasi SQL | [01-dashboard](screenshots/admin/01-dashboard.png) |
| **Data Anggota** | Cari, lihat detail, **Tambah Anggota** (kode, nama, kontak, status, opsional Akses Login), **Ubah** (profil, status, reset kata sandi) | Kode anggota unik; tidak ada hapus; menonaktifkan anggota mengakhiri sesinya dan ditolak bila anggota punya booking Disetujui | [02](screenshots/admin/02-data-anggota.png) · [03](screenshots/admin/03-tambah-anggota.png) · [05](screenshots/admin/05-ubah-anggota.png) |
| **Data Buku & Eksemplar** | Cari (20 per halaman), **Tambah Buku** + minimal 1 Kode Eksemplar, **Ubah Buku** + tambah eksemplar | Kode buku & eksemplar unik; penyimpanan atomik; status Dipinjam/Dipesan tidak dapat diatur manual | [06](screenshots/admin/06-data-buku.png) · [07](screenshots/admin/07-tambah-buku.png) · [09](screenshots/admin/09-ubah-buku.png) |
| **Peminjaman Buku** | Pilih Anggota → Buku → Kode Eksemplar (Tersedia) → Tanggal Jatuh Tempo → Simpan → Bukti Peminjaman | Anggota harus Aktif; eksemplar Tersedia & tidak dipesan; jatuh tempo setelah hari ini | [10](screenshots/admin/10-peminjaman.png) |
| **Pengembalian Buku** | Tab Peminjaman Aktif → cari → **Proses Pengembalian** → konfirmasi | Hanya pinjaman Aktif; pengembalian ganda ditolak; anggota Nonaktif tetap boleh mengembalikan | [16](screenshots/admin/16-pengembalian.png) · [17](screenshots/admin/17-pengembalian-berhasil.png) |
| **Verifikasi Booking** | Tab Diajukan → **Setujui** (eksemplar menjadi Dipesan, Batas Pengambilan = +48 jam) atau **Tolak** (wajib Alasan Penolakan) | Hanya booking Diajukan; anggota Aktif; eksemplar tersedia | [11](screenshots/admin/11-verifikasi-booking.png) · [13](screenshots/admin/13-booking-disetujui.png) |
| **Realisasi Booking** | Pilih booking Disetujui → isi Tanggal Jatuh Tempo → **Realisasikan Menjadi Peminjaman** | Ditolak bila Batas Pengambilan terlewati (booking menjadi Kedaluwarsa) | [14](screenshots/admin/14-realisasi-booking.png) · [15](screenshots/admin/15-bukti-realisasi.png) |
| **Laporan** | Pilih jenis (Daftar Buku, Daftar Anggota, Peminjaman, Pengembalian) dan periode → Tampilkan → **Cetak Laporan** | Periode WIB; Periode Akhir tidak boleh sebelum Periode Awal; cetak lewat dialog browser | [18](screenshots/admin/18-laporan-peminjaman.png) · [19](screenshots/admin/19-laporan-pengembalian.png) |
| **Ubah Kata Sandi** | Kata sandi saat ini + baru + konfirmasi | Dibatasi 6 percobaan/menit; sesi lain diakhiri | — |
| **Keluar** | Menu Keluar → konfirmasi | Sesi diinvalidasi, token CSRF diganti | — |

## B. Anggota

| Menu | Langkah utama | Aturan | Screenshot |
| --- | --- | --- | --- |
| **Masuk pertama kali** | Masuk dengan akun dari Admin → diarahkan ke **Ubah Kata Sandi** | Menu lain diblokir sampai kata sandi diganti | [01](screenshots/anggota/01-wajib-ubah-kata-sandi.png) |
| **Dashboard** | Ringkasan booking aktif, peminjaman aktif, riwayat selesai | Hanya data milik sendiri (identitas dari sesi) | [02](screenshots/anggota/02-dashboard.png) |
| **Cari Buku** | Kata kunci (judul/kode/kategori) + filter kategori → ikon mata untuk **Detail Buku** & daftar eksemplar | Tanpa data pribadi anggota lain | [03](screenshots/anggota/03-cari-buku.png) · [04](screenshots/anggota/04-detail-buku.png) |
| **Ajukan Booking** | Pilih Buku → Kode Eksemplar (Tersedia) → Ajukan → konfirmasi | Anggota Aktif; eksemplar Tersedia; tidak boleh booking ganda pada eksemplar yang sama. Status awal **Diajukan** (eksemplar belum dipesan) | [05](screenshots/anggota/05-ajukan-booking.png) · [06](screenshots/anggota/06-booking-diajukan.png) |
| **Status Booking** | Lihat status per tab; **Batalkan** booking Diajukan/Disetujui milik sendiri | Booking yang lewat batas tidak dibatalkan, melainkan menjadi Kedaluwarsa | [07](screenshots/anggota/07-status-booking.png) · [09](screenshots/anggota/09-status-booking-akhir.png) |
| **History Peminjaman** | Tab Semua/Aktif/Selesai, panel detail | Hanya riwayat milik sendiri | [08](screenshots/anggota/08-history-peminjaman.png) |

## C. Siklus status

- **Eksemplar:** Tersedia → (booking disetujui) Dipesan → (realisasi) Dipinjam → (pengembalian) Tersedia; atau Tersedia → (pinjam langsung) Dipinjam.
- **Booking:** Diajukan → Disetujui / Ditolak / Dibatalkan; Disetujui → Direalisasikan / Dibatalkan / **Kedaluwarsa** (48 jam).
- **Peminjaman:** Aktif → Selesai.
