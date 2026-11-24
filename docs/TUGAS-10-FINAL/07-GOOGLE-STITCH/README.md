# 7. Hasil Desain UI/UX — Google Stitch (Pengganti Figma)

Status: **REQUIRES OWNER INPUT**

> **Pengganti persyaratan "Hasil Figma".** Kelompok 11 memakai **Google Stitch**, bukan Figma. Proyek ini tidak memiliki berkas atau tautan Figma, dan paket ini tidak membuat satu pun.

Nomor 7 belum lengkap karena tiga hal:

1. Ekspor layar dari proyek Stitch belum ada di repositori.
2. Tautan Stitch belum terbukti dapat dibuka tanpa login.
3. Ada risiko kepatuhan terhadap instruksi dosen (lihat §0), sehingga persetujuan dosen wajib diperoleh.

## 0. Risiko kepatuhan: instruksi dosen "bukan dengan AI"

| Sumber | Isi (kutipan) |
| --- | --- |
| Materi dosen *Pertemuan 09 — Perancangan UI/UX*, hlm. 17 (berkas lokal pemilik `09 RPL 2026 - Perancangan UI UX.pdf`) | "Pergunakan FIGMA untuk membuat rancangan layar mockup proyek. **bukan dengan AI**, pengumpulan beserta link FIGMA" |
| Dokumen kelompok `Tugas_09_Panduan_UIUX_Kelompok11.pdf` (6 Nov 2026, berkas lokal) | "Desain layar/mockup dan prototipe wajib dikerjakan manual di Figma … Materi dosen melarang AI untuk membuat mockup." |
| Halaman proyek Stitch (judul HTML, diperiksa 24 Nov 2026) | "Stitch - **Design with AI**" |

Google Stitch adalah alat desain antarmuka berbasis AI generatif dari Google. Karena itu, menyerahkan proyek Stitch sebagai pengganti Figma **berpotensi bertentangan langsung** dengan instruksi tugas. Paket ini tidak menyatakan bahwa Stitch diterima.

**Sebelum mengumpulkan, pemilik perlu memperoleh persetujuan dosen secara eksplisit**, dengan menyebutkan bahwa Stitch adalah alat berbasis AI. Simpan bukti persetujuannya (misalnya tangkapan pesan) di folder ini.

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

**Kesimpulan:** urutan yang terbukti adalah **aplikasi dahulu, lalu mockup Stitch dibuat agar menyamai aplikasi**. Tidak ada bukti bahwa desain Stitch memandu implementasi, dan paket ini tidak mengklaimnya. Jika ada sesi Stitch yang lebih awal (sebelum 6 Nov), pemilik perlu menyediakan buktinya, misalnya riwayat versi proyek Stitch.

## 3. Tiga jenis bukti visual (jangan dicampur)

| Jenis | Lokasi | Asal | Status |
| --- | --- | --- | --- |
| **A. Screenshot aplikasi asli (prototipe)** | [`docs/screenshots/`](../../screenshots/) (26 PNG, 7 Nov 2026) | Chrome headless terhadap prototipe Laravel + Vue sebelum MySQL; paket acuan yang direplikasi di Stitch | Ada. **Bukan** ekspor Stitch |
| **B. Ekspor desain Google Stitch** | [`ekspor-stitch/`](ekspor-stitch/README.md) (`admin/`, `anggota/`) | Diekspor pemilik dari proyek Stitch | **BELUM ADA**, diperlukan dari pemilik |
| **C. Screenshot aplikasi final (dinamis, MySQL)** | [`09-HASIL-SISTEM/screenshots/`](../09-HASIL-SISTEM/screenshots/) (31 PNG, 21 Nov 2026) | Skenario E2E terhadap aplikasi berjalan + DB uji | Ada. Bukti **Hasil Sistem**, bukan desain |

### Cakupan layar dan nama berkas ekspor yang diharapkan

✅ tersedia · ❌ tidak tersedia · ⏳ menunggu ekspor dari pemilik

| Peran | Layar | A. Screenshot prototipe (7 Nov) | B. Ekspor Stitch (isi di sini) |
| --- | --- | --- | --- |
| Admin | Login | ✅ [01-login.png](../../screenshots/01-login.png) | ⏳ `ekspor-stitch/admin/01-login.png` |
| Admin | Dashboard | ✅ [admin/01-dashboard.png](../../screenshots/admin/01-dashboard.png) | ⏳ `ekspor-stitch/admin/02-dashboard.png` |
| Admin | Data Anggota | ✅ [admin/02-data-anggota.png](../../screenshots/admin/02-data-anggota.png) | ⏳ `ekspor-stitch/admin/03-data-anggota.png` |
| Admin | Tambah Anggota | ✅ [admin/03-tambah-anggota.png](../../screenshots/admin/03-tambah-anggota.png) | ⏳ `ekspor-stitch/admin/04-tambah-anggota.png` |
| Admin | Ubah Anggota | ✅ [admin/04-ubah-anggota.png](../../screenshots/admin/04-ubah-anggota.png) | ⏳ `ekspor-stitch/admin/05-ubah-anggota.png` |
| Admin | Data Buku | ✅ [admin/05-data-buku.png](../../screenshots/admin/05-data-buku.png) | ⏳ `ekspor-stitch/admin/06-data-buku.png` |
| Admin | Tambah Buku | ✅ [admin/06-tambah-buku.png](../../screenshots/admin/06-tambah-buku.png) | ⏳ `ekspor-stitch/admin/07-tambah-buku.png` |
| Admin | Ubah Buku | ✅ [admin/07-ubah-buku.png](../../screenshots/admin/07-ubah-buku.png) | ⏳ `ekspor-stitch/admin/08-ubah-buku.png` |
| Admin | Peminjaman | ✅ [admin/08-peminjaman.png](../../screenshots/admin/08-peminjaman.png) | ⏳ `ekspor-stitch/admin/09-peminjaman.png` |
| Admin | Pengembalian | ✅ [admin/09-pengembalian.png](../../screenshots/admin/09-pengembalian.png) | ⏳ `ekspor-stitch/admin/10-pengembalian.png` |
| Admin | Verifikasi Booking | ✅ [admin/10-verifikasi-booking.png](../../screenshots/admin/10-verifikasi-booking.png) | ⏳ `ekspor-stitch/admin/11-verifikasi-booking.png` |
| Admin | Realisasi Booking | ✅ [admin/11-realisasi-booking.png](../../screenshots/admin/11-realisasi-booking.png) | ⏳ `ekspor-stitch/admin/12-realisasi-booking.png` |
| Admin | Laporan | ✅ [admin/12-laporan.png](../../screenshots/admin/12-laporan.png) | ⏳ `ekspor-stitch/admin/13-laporan.png` |
| Anggota | Dashboard | ✅ [anggota/01-dashboard.png](../../screenshots/anggota/01-dashboard.png) | ⏳ `ekspor-stitch/anggota/01-dashboard.png` |
| Anggota | Cari Buku | ✅ [anggota/02-cari-buku.png](../../screenshots/anggota/02-cari-buku.png) | ⏳ `ekspor-stitch/anggota/02-cari-buku.png` |
| Anggota | Detail Buku | ✅ [anggota/states/02a-cari-buku-panel-detail.png](../../screenshots/anggota/states/02a-cari-buku-panel-detail.png) (panel) | ⏳ `ekspor-stitch/anggota/03-detail-buku.png` |
| Anggota | Ajukan Booking | ✅ [anggota/03-ajukan-booking.png](../../screenshots/anggota/03-ajukan-booking.png) | ⏳ `ekspor-stitch/anggota/04-ajukan-booking.png` |
| Anggota | Status Booking | ✅ [anggota/04-status-booking.png](../../screenshots/anggota/04-status-booking.png) | ⏳ `ekspor-stitch/anggota/05-status-booking.png` |
| Anggota | Histori Peminjaman | ✅ [anggota/05-history-peminjaman.png](../../screenshots/anggota/05-history-peminjaman.png) | ⏳ `ekspor-stitch/anggota/06-histori-peminjaman.png` |
| Anggota | Ubah Kata Sandi | ❌ Tidak ada di paket 7 Nov karena halaman ini baru dibuat 14 Nov. Versi final ada di [Hasil Sistem](../09-HASIL-SISTEM/screenshots/anggota/01-wajib-ubah-kata-sandi.png) | ⏳ `ekspor-stitch/anggota/07-ubah-kata-sandi.png` (bila ada di Stitch) |

## 4. Teknologi implementasi

Struktur layar diimplementasikan dengan **Vue 3 (Composition API) + Inertia.js + Bootstrap 5.3** (`resources/js/Pages`, `resources/js/Components`). Font Inter dan Bootstrap Icons dibundel lokal. Navigasi per peran diatur di `app/Support/NavigasiDemo.php`.

## 5. Langkah pemilik untuk menyelesaikan nomor 7

1. **Persetujuan dosen** (§0): tanyakan secara tertulis apakah proyek Google Stitch, yang berbasis AI, diterima sebagai pengganti Figma. Simpan buktinya sebagai `persetujuan-dosen.png` atau `.pdf` di folder ini. Jika dosen menolak, nomor 7 memerlukan desain Figma manual dan tidak dapat diselesaikan dari repositori ini.
2. **Akses tautan:** atur berbagi proyek Stitch, uji di *incognito*, lalu catat tanggal dan hasil uji di tabel §1.
3. **Ekspor layar:** ekspor PNG dari Stitch ke `ekspor-stitch/admin/` dan `ekspor-stitch/anggota/` sesuai nama di §3. Jangan masukkan screenshot aplikasi (jenis A atau C) ke folder ini. Lihat [`ekspor-stitch/README.md`](ekspor-stitch/README.md).
4. Setelah butir 1–3 terpenuhi, ubah status nomor 7 menjadi **COMPLETE** di README ini, di [README paket](../README.md), dan di [FINAL_SUBMISSION_CHECKLIST](../FINAL_SUBMISSION_CHECKLIST.md). Struktur paket tidak perlu diubah.
