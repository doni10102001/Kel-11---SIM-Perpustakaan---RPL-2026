# 7. Hasil Desain UI/UX — Google Stitch

Status: **REQUIRES OWNER INPUT**

Kelompok 11 memakai **Google Stitch** sebagai alternatif Figma untuk desain UI/UX. Proyek ini tidak memiliki berkas atau tautan Figma, dan paket ini tidak membuatnya.

Nomor 7 belum lengkap karena dua hal:

1. Ekspor layar dari proyek Stitch belum ada di repositori.
2. Tautan Stitch belum terbukti dapat dibuka tanpa login.

## 0. Ketentuan dosen

| Item | Keterangan |
| --- | --- |
| Instruksi awal | Materi *Pertemuan 09 — Perancangan UI/UX*, hlm. 17: "Pergunakan FIGMA untuk membuat rancangan layar mockup proyek. bukan dengan AI, pengumpulan beserta link FIGMA". |
| Sifat Google Stitch | Alat desain antarmuka berbasis AI dari Google (judul halaman: "Stitch - Design with AI"). |
| Izin penggunaan | Menurut pemilik proyek (24 Nov 2026), **dosen telah mengizinkan Google Stitch sebagai alternatif Figma**. Bukti tertulis izin tersebut belum disimpan di repositori. |

Disarankan menyimpan bukti izin (misalnya tangkapan pesan dosen) sebagai `persetujuan-dosen.png` atau `.pdf` di folder ini.

## 1. Tautan proyek Google Stitch

| Item | Nilai |
| --- | --- |
| Tautan | <https://stitch.google.com/projects/12475154501831437105> |
| Sumber tautan | Dokumen kelompok "Kelompok 11 Tugas RPL - Mockup.pdf" (18 Nov 2026). Berkas ini tersimpan lokal di komputer pemilik, tidak di repositori. |
| Verifikasi 21 Nov 2026 | HTTP 200. Chrome tanpa login (profil kosong) tidak menampilkan konten desain. |
| Verifikasi ulang 24 Nov 2026 | HTTP 200, tanpa pengalihan, judul HTML "Stitch - Design with AI". Chrome headless tanpa login (profil sekali pakai) hanya menampilkan **halaman kosong**, tanpa desain. |
| Kesimpulan | URL masih valid, tetapi **akses publik/dosen belum terbukti**. Kemungkinan dosen perlu login Google dan mendapat izin berbagi. |
| Tindakan pemilik | Atur berbagi proyek Stitch, lalu uji di jendela *incognito* atau dengan akun Google lain. |

## 2. Kronologi alur desain (berdasarkan bukti)

| Tanggal | Peristiwa | Bukti |
| --- | --- | --- |
| 6 Nov 2026 | Panduan Tugas 09 kelompok menyatakan mockup wajib dibuat manual di Figma; kolom "LINK FIGMA" masih kosong | `Tugas_09_Panduan_UIUX_Kelompok11.pdf` (lokal) |
| 6–7 Nov 2026 | Halaman antarmuka dibangun langsung sebagai prototipe Laravel + Vue (data contoh, tanpa database) | Riwayat Git |
| 7 Nov 2026 | Commit `86552a1`: 26 screenshot prototipe diekspor "**untuk direplikasi di Google Stitch**". Manifest saat itu: *"Website Laravel + Vue adalah acuan final; mockup Stitch harus menyamai screenshot ini tanpa mengubah tata letak, teks, warna, atau data."* | Commit `86552a1` |
| 14–21 Nov 2026 | Aplikasi tersambung ke MySQL; halaman baru ditambahkan (mis. Ubah Kata Sandi); screenshot final diambil 21 Nov | Riwayat Git, [09-HASIL-SISTEM](../09-HASIL-SISTEM/README.md) |
| 18 Nov 2026 | Tautan proyek Stitch dicantumkan pada dokumen mockup kelompok | `Kelompok 11 Tugas RPL - Mockup.pdf` (lokal) |
| 24 Nov 2026 | Pemilik proyek menyatakan dosen mengizinkan Google Stitch sebagai alternatif Figma | Pernyataan pemilik; bukti tertulis belum di repositori |

**Kesimpulan:** urutan yang terbukti adalah **aplikasi dibuat lebih dulu, lalu mockup Stitch dibuat menyamai aplikasi**. Tidak ada bukti bahwa desain Stitch memandu implementasi, sehingga paket ini tidak mengklaimnya. Jika ada sesi Stitch sebelum 6 Nov, pemilik perlu menunjukkan buktinya, misalnya riwayat versi proyek Stitch.

## 3. Tiga jenis bukti visual

| Jenis | Lokasi | Asal | Status |
| --- | --- | --- | --- |
| **A. Screenshot prototipe aplikasi** | [`docs/archive/screenshots-prototipe/`](../../archive/screenshots-prototipe/) (28 PNG, 7 Nov 2026; 26 di antaranya diekspor untuk Stitch di commit `86552a1`, lalu `96859fb` memperbarui 2 berkas dan menambah 2) | Chrome headless terhadap prototipe Laravel + Vue sebelum MySQL; paket acuan yang direplikasi di Stitch | Ada. **Bukan** ekspor Stitch |
| **B. Ekspor desain Google Stitch** | [`ekspor-stitch/`](ekspor-stitch/README.md) (`admin/`, `anggota/`) | Diekspor pemilik dari proyek Stitch | **BELUM ADA**, diperlukan dari pemilik |
| **C. Screenshot aplikasi final (dinamis, MySQL)** | [`09-HASIL-SISTEM/screenshots/`](../09-HASIL-SISTEM/screenshots/) (31 PNG, 21 Nov 2026) | Skenario E2E terhadap aplikasi berjalan + DB uji | Ada. Bukti **Hasil Sistem**, bukan desain |

### Cakupan layar dan nama berkas ekspor yang diharapkan

✅ tersedia · ❌ tidak tersedia · ⏳ menunggu ekspor dari pemilik

| Peran | Layar | A. Screenshot prototipe (7 Nov) | B. Ekspor Stitch (isi di sini) |
| --- | --- | --- | --- |
| Admin | Login | ✅ [01-login.png](../../archive/screenshots-prototipe/01-login.png) | ⏳ `ekspor-stitch/admin/01-login.png` |
| Admin | Dashboard | ✅ [admin/01-dashboard.png](../../archive/screenshots-prototipe/admin/01-dashboard.png) | ⏳ `ekspor-stitch/admin/02-dashboard.png` |
| Admin | Data Anggota | ✅ [admin/02-data-anggota.png](../../archive/screenshots-prototipe/admin/02-data-anggota.png) | ⏳ `ekspor-stitch/admin/03-data-anggota.png` |
| Admin | Tambah Anggota | ✅ [admin/03-tambah-anggota.png](../../archive/screenshots-prototipe/admin/03-tambah-anggota.png) | ⏳ `ekspor-stitch/admin/04-tambah-anggota.png` |
| Admin | Ubah Anggota | ✅ [admin/04-ubah-anggota.png](../../archive/screenshots-prototipe/admin/04-ubah-anggota.png) | ⏳ `ekspor-stitch/admin/05-ubah-anggota.png` |
| Admin | Data Buku | ✅ [admin/05-data-buku.png](../../archive/screenshots-prototipe/admin/05-data-buku.png) | ⏳ `ekspor-stitch/admin/06-data-buku.png` |
| Admin | Tambah Buku | ✅ [admin/06-tambah-buku.png](../../archive/screenshots-prototipe/admin/06-tambah-buku.png) | ⏳ `ekspor-stitch/admin/07-tambah-buku.png` |
| Admin | Ubah Buku | ✅ [admin/07-ubah-buku.png](../../archive/screenshots-prototipe/admin/07-ubah-buku.png) | ⏳ `ekspor-stitch/admin/08-ubah-buku.png` |
| Admin | Peminjaman | ✅ [admin/08-peminjaman.png](../../archive/screenshots-prototipe/admin/08-peminjaman.png) | ⏳ `ekspor-stitch/admin/09-peminjaman.png` |
| Admin | Pengembalian | ✅ [admin/09-pengembalian.png](../../archive/screenshots-prototipe/admin/09-pengembalian.png) | ⏳ `ekspor-stitch/admin/10-pengembalian.png` |
| Admin | Verifikasi Booking | ✅ [admin/10-verifikasi-booking.png](../../archive/screenshots-prototipe/admin/10-verifikasi-booking.png) | ⏳ `ekspor-stitch/admin/11-verifikasi-booking.png` |
| Admin | Realisasi Booking | ✅ [admin/11-realisasi-booking.png](../../archive/screenshots-prototipe/admin/11-realisasi-booking.png) | ⏳ `ekspor-stitch/admin/12-realisasi-booking.png` |
| Admin | Laporan | ✅ [admin/12-laporan.png](../../archive/screenshots-prototipe/admin/12-laporan.png) | ⏳ `ekspor-stitch/admin/13-laporan.png` |
| Anggota | Dashboard | ✅ [anggota/01-dashboard.png](../../archive/screenshots-prototipe/anggota/01-dashboard.png) | ⏳ `ekspor-stitch/anggota/01-dashboard.png` |
| Anggota | Cari Buku | ✅ [anggota/02-cari-buku.png](../../archive/screenshots-prototipe/anggota/02-cari-buku.png) | ⏳ `ekspor-stitch/anggota/02-cari-buku.png` |
| Anggota | Detail Buku | ✅ [anggota/states/02a-cari-buku-panel-detail.png](../../archive/screenshots-prototipe/anggota/states/02a-cari-buku-panel-detail.png) (panel) | ⏳ `ekspor-stitch/anggota/03-detail-buku.png` |
| Anggota | Ajukan Booking | ✅ [anggota/03-ajukan-booking.png](../../archive/screenshots-prototipe/anggota/03-ajukan-booking.png) | ⏳ `ekspor-stitch/anggota/04-ajukan-booking.png` |
| Anggota | Status Booking | ✅ [anggota/04-status-booking.png](../../archive/screenshots-prototipe/anggota/04-status-booking.png) | ⏳ `ekspor-stitch/anggota/05-status-booking.png` |
| Anggota | Histori Peminjaman | ✅ [anggota/05-history-peminjaman.png](../../archive/screenshots-prototipe/anggota/05-history-peminjaman.png) | ⏳ `ekspor-stitch/anggota/06-histori-peminjaman.png` |
| Anggota | Ubah Kata Sandi | ❌ Tidak ada di paket 7 Nov karena halaman ini baru dibuat 14 Nov. Versi final ada di [Hasil Sistem](../09-HASIL-SISTEM/screenshots/anggota/01-wajib-ubah-kata-sandi.png) | ⏳ `ekspor-stitch/anggota/07-ubah-kata-sandi.png` (bila ada di Stitch) |

## 4. Teknologi implementasi

Struktur layar diimplementasikan dengan **Vue 3 (Composition API) + Inertia.js + Bootstrap 5.3** (`resources/js/Pages`, `resources/js/Components`). Font Inter dan Bootstrap Icons dibundel lokal. Navigasi per peran diatur di `app/Support/NavigasiDemo.php`.

## 5. Langkah pemilik untuk menyelesaikan nomor 7

1. **Akses tautan:** atur berbagi proyek Stitch, uji di jendela *incognito*, lalu catat tanggal dan hasilnya di tabel §1.
2. **Ekspor layar:** ekspor PNG dari Stitch ke `ekspor-stitch/admin/` dan `ekspor-stitch/anggota/` sesuai nama di §3. Jangan masukkan screenshot aplikasi (jenis A atau C) ke folder ini. Lihat [`ekspor-stitch/README.md`](ekspor-stitch/README.md).
3. **Bukti izin dosen (disarankan):** simpan tangkapan pesan izin di folder ini.
4. Setelah butir 1 dan 2 terpenuhi, ubah status nomor 7 menjadi **COMPLETE** di README ini, di [README paket](../README.md), dan di [checklist akhir](../FINAL_SUBMISSION_CHECKLIST.md). Struktur paket tidak perlu diubah.
