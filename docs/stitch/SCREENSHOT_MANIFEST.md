# Manifest Screenshot — SIM Perpustakaan

Paket screenshot PNG halaman final untuk direplikasi di Google Stitch. Website Laravel + Vue adalah acuan final; mockup Stitch harus menyamai screenshot ini tanpa mengubah tata letak, teks, warna, atau data.

## Ringkasan

- Screenshot utama: **18** (1 Masuk, 12 Admin, 5 Anggota)
- Screenshot state tambahan (panel/dialog): **8**
- Lolos validasi: **26/26**
- Basis URL saat capture: `http://127.0.0.1:8013` (lokal; tidak ada URL publik)
- Tanggal data demo: 7 November 2026 (tetap). Setiap halaman dimuat ulang penuh sehingga memakai dataset awal.

## Standar capture

| Parameter | Nilai |
| --- | --- |
| Browser | Google Chrome headless (DevTools Protocol) |
| Viewport | 1440 × 900, device scale factor 1, zoom 100% |
| Format | PNG 8-bit |
| Halaman utama | Full-page: viewport diperbesar setinggi halaman agar sidebar (fixed) dan header (sticky) tampil utuh sekali |
| State tambahan | Viewport 1440 × 900 dengan panel/dialog terbuka |
| Font | Inter Variable & Bootstrap Icons (dibundel lokal oleh Vite) — diverifikasi termuat sebelum capture |
| Animasi | Transisi, animasi, dan kursor teks dinonaktifkan selama capture |
| Fokus/hover | Fokus dilepas dan kursor dipindah ke pojok sebelum capture |
| Login | Mode presentasi: kotak "Mode pratinjau" disembunyikan hanya saat capture (kode demo tetap ada) |

## Halaman utama

| No | Peran | Halaman | URL lokal | File PNG | Dimensi (px) | Ukuran | State / catatan | Validasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | — | Masuk | `/login` | `01-login.png` | 1440 × 900 | 316 KB | Mode presentasi: kotak "Mode pratinjau" disembunyikan saat capture. | ✓ Lolos |
| 2 | Admin | Dashboard | `/demo/admin/dashboard` | `admin/01-dashboard.png` | 1440 × 1672 | 230 KB | — | ✓ Lolos |
| 3 | Admin | Data Anggota | `/demo/admin/anggota` | `admin/02-data-anggota.png` | 1440 × 1016 | 166 KB | — | ✓ Lolos |
| 4 | Admin | Tambah Anggota | `/demo/admin/anggota/tambah` | `admin/03-tambah-anggota.png` | 1440 × 900 | 108 KB | — | ✓ Lolos |
| 5 | Admin | Ubah Anggota | `/demo/admin/anggota/AG-001/ubah` | `admin/04-ubah-anggota.png` | 1440 × 900 | 118 KB | Contoh anggota AG-001. | ✓ Lolos |
| 6 | Admin | Data Buku & Eksemplar | `/demo/admin/buku` | `admin/05-data-buku.png` | 1440 × 1105 | 183 KB | — | ✓ Lolos |
| 7 | Admin | Tambah Buku | `/demo/admin/buku/tambah` | `admin/06-tambah-buku.png` | 1440 × 1381 | 156 KB | — | ✓ Lolos |
| 8 | Admin | Ubah Buku | `/demo/admin/buku/BK-001/ubah` | `admin/07-ubah-buku.png` | 1440 × 1389 | 162 KB | Contoh buku BK-001 (3 eksemplar: Tersedia & Dipinjam). | ✓ Lolos |
| 9 | Admin | Peminjaman Buku | `/demo/admin/peminjaman` | `admin/08-peminjaman.png` | 1440 × 900 | 114 KB | — | ✓ Lolos |
| 10 | Admin | Pengembalian Buku | `/demo/admin/pengembalian` | `admin/09-pengembalian.png` | 1440 × 900 | 146 KB | — | ✓ Lolos |
| 11 | Admin | Verifikasi Booking | `/demo/admin/booking/verifikasi` | `admin/10-verifikasi-booking.png` | 1440 × 900 | 110 KB | Tab bawaan: Diajukan. | ✓ Lolos |
| 12 | Admin | Realisasi Booking | `/demo/admin/booking/realisasi?booking=BP-001` | `admin/11-realisasi-booking.png` | 1440 × 1200 | 158 KB | Booking BP-001 terpilih (query `?booking=`). | ✓ Lolos |
| 13 | Admin | Laporan | `/demo/admin/laporan?jenis=peminjaman` | `admin/12-laporan.png` | 1440 × 1475 | 233 KB | Laporan Peminjaman seluruh periode (query `?jenis=`). | ✓ Lolos |
| 14 | Anggota | Dashboard | `/demo/anggota/dashboard` | `anggota/01-dashboard.png` | 1440 × 900 | 119 KB | Akun AG-001 Nadia Rahmawati. | ✓ Lolos |
| 15 | Anggota | Cari Buku | `/demo/anggota/cari-buku` | `anggota/02-cari-buku.png` | 1440 × 1091 | 191 KB | — | ✓ Lolos |
| 16 | Anggota | Ajukan Booking | `/demo/anggota/booking/ajukan?eksemplar=EK-004` | `anggota/03-ajukan-booking.png` | 1440 × 900 | 134 KB | Eksemplar EK-004 terpilih (query `?eksemplar=`). | ✓ Lolos |
| 17 | Anggota | Status Booking | `/demo/anggota/booking/status` | `anggota/04-status-booking.png` | 1440 × 900 | 105 KB | — | ✓ Lolos |
| 18 | Anggota | History Peminjaman | `/demo/anggota/history` | `anggota/05-history-peminjaman.png` | 1440 × 900 | 90 KB | — | ✓ Lolos |

## State tambahan

| No | Peran | Halaman | URL lokal | File PNG | Dimensi (px) | Ukuran | State / catatan | Validasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | Admin | Data Anggota — panel Detail Anggota | `/demo/admin/anggota` | `admin/states/02a-data-anggota-panel-detail.png` | 1440 × 900 | 139 KB | Panel/dialog terbuka; viewport 1440 × 900 | ✓ Lolos |
| 2 | Admin | Data Buku — panel Detail Buku & Daftar Eksemplar | `/demo/admin/buku` | `admin/states/05a-data-buku-panel-detail.png` | 1440 × 900 | 157 KB | Panel/dialog terbuka; viewport 1440 × 900 | ✓ Lolos |
| 3 | Admin | Verifikasi Booking — panel Detail Booking | `/demo/admin/booking/verifikasi` | `admin/states/10a-verifikasi-panel-detail.png` | 1440 × 900 | 117 KB | Panel/dialog terbuka; viewport 1440 × 900 | ✓ Lolos |
| 4 | Admin | Verifikasi Booking — dialog Tolak (Alasan Penolakan) | `/demo/admin/booking/verifikasi` | `admin/states/10b-verifikasi-dialog-tolak.png` | 1440 × 900 | 113 KB | Panel/dialog terbuka; viewport 1440 × 900 | ✓ Lolos |
| 5 | Admin | Laporan — sebelum laporan ditampilkan | `/demo/admin/laporan` | `admin/states/12a-laporan-filter-awal.png` | 1440 × 900 | 91 KB | Panel/dialog terbuka; viewport 1440 × 900 | ✓ Lolos |
| 6 | Anggota | Cari Buku — panel Detail Buku | `/demo/anggota/cari-buku` | `anggota/states/02a-cari-buku-panel-detail.png` | 1440 × 900 | 147 KB | Panel/dialog terbuka; viewport 1440 × 900 | ✓ Lolos |
| 7 | Anggota | Status Booking — panel Detail Booking (Direalisasikan) | `/demo/anggota/booking/status` | `anggota/states/04a-status-booking-panel-detail.png` | 1440 × 900 | 125 KB | Panel/dialog terbuka; viewport 1440 × 900 | ✓ Lolos |
| 8 | Anggota | History Peminjaman — panel Detail Riwayat | `/demo/anggota/history` | `anggota/states/05a-history-panel-detail.png` | 1440 × 900 | 103 KB | Panel/dialog terbuka; viewport 1440 × 900 | ✓ Lolos |

## Validasi yang dijalankan

Per halaman, sebelum capture: judul (h1) sesuai, tidak ada placeholder atau istilah internal (SRS/TBD/UC/FR/BR/prototipe), tidak ada indikator memuat, tidak ada overflow horizontal, tidak ada dialog/panel terbuka pada screenshot utama, font Inter & Bootstrap Icons termuat, tidak ada gambar rusak, tidak ada error/peringatan console, JavaScript error, atau request gagal.

Per file: signature PNG valid, lebar 1440px, tinggi ≥ 900px, piksel didekode untuk memastikan gambar tidak kosong, dan sidik SHA-256 unik (tidak ada screenshot duplikat).

Dibuat ulang dengan: `npm run screenshots:stitch` (lihat `scripts/stitch-screenshots.mjs`).
