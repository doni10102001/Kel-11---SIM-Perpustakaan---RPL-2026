# 10. File PPT Presentasi

Status: **COMPLETE** — berkas PowerPoint asli (dapat diedit), lolos validasi struktur, dan setiap slide telah dirender serta diperiksa.

| Atribut | Nilai |
| --- | --- |
| Berkas | [`SIM_Perpustakaan_Kelompok_11_Final.pptx`](SIM_Perpustakaan_Kelompok_11_Final.pptx) (±4,8 MB) |
| Format | 16:9 widescreen (13,33 × 7,5 in), 18 slide, catatan pembicara di setiap slide |
| Struktur | Tema + layout + placeholder judul + 5 section (Pembuka · Metodologi & Teknologi · Rancangan UML · Desain & Implementasi · Pengujian & Penutup) |
| Font | Calibri (judul & isi) |
| Gambar | 18 gambar tertanam — semuanya diagram dan screenshot nyata dari repositori ini; tidak ada grafik/screenshot rekaan |
| Generator | [`../_alat/build-deck.cjs`](../_alat/build-deck.cjs) (pptxgenjs 3.12) |

## Daftar slide

| # | Judul | Visual utama (sumber) |
| --- | --- | --- |
| 1 | Sampul — Sistem Informasi Manajemen Perpustakaan | Screenshot halaman Masuk (09-HASIL-SISTEM) · nama & NPM **tiga anggota final** |
| 2 | Latar Belakang | Kebutuhan SRS §1.4 |
| 3 | Rumusan Masalah | Diturunkan dari SRS §1.4 & §2.2 |
| 4 | Tujuan Sistem dan Manfaat | — |
| 5 | Ruang Lingkup | Admin · Anggota · di luar lingkup |
| 6 | Metodologi: Waterfall | Tanggal dari SRS & commit Git |
| 7 | Teknologi yang Digunakan | Versi dari composer.lock / package.json |
| 8 | Use Case Diagram | Suplemen implementasi + diagram asli Tugas 3 |
| 9 | Class Diagram | Suplemen model Eloquent & MySQL |
| 10 | Activity Diagram | A04 asli + A10 suplemen |
| 11 | Sequence Diagram: Peminjaman Langsung | S-IMPL-04 |
| 12 | Deployment Diagram | Lokal **aktual** vs produksi **usulan** |
| 13 | Hasil Desain UI/UX — Google Stitch | Screenshot acuan (dilabeli *bukan ekspor Stitch*), kronologi Git, izin dosen menurut pemilik, status bukti yang belum ada |
| 14 | Hasil Implementasi | Screenshot aplikasi MySQL (Dashboard Admin, Dashboard Anggota, Data Buku) |
| 15 | Proses Peminjaman & Pengembalian | Alur status + screenshot pengembalian & realisasi |
| 16 | Booking & Kedaluwarsa Otomatis | Siklus status, 48 jam, 5 menit, screenshot persetujuan |
| 17 | Software Testing | 270/270 PHPUnit · 245/245 Node · 23/23 **Browser QA lama** (hanya 7 ber-assertion eksplisit, 8 tanpa assertion) · 22/22 **E2E demo terverifikasi** · eksekusi 21 & 24 Nov · keterbatasan |
| 18 | Kesimpulan & Demo | Pencapaian, agenda demo 10 langkah, status kesiapan (termasuk syarat rotasi kredensial dan persetujuan Stitch) |

## Pembaruan 24 Nov 2026

**Revisi Stitch dan keamanan:** judul slide 13 menjadi "Hasil Desain UI/UX — Google Stitch". Slide 7, 13, dan 18 kini menyebut izin dosen atas Stitch menurut pemilik proyek, serta status rotasi kredensial (K-1 sudah, K-2 belum).

**Revisi anggota:** slide 1 kini memuat tiga anggota final (Doni Suhendar, Abdurrahman Wahid, Sarah Tirta Kencana) dalam tiga kartu bertumpuk, sesuai keputusan pemilik proyek. Catatan pembicara slide 1 ikut diperbarui. Slide lain tidak memuat daftar anggota.

Slide 6, 13, 17, dan 18 diperbarui teksnya: tanggal uji ulang, risiko instruksi dosen "bukan dengan AI" untuk Stitch, label dan cakupan assertion Browser QA lama vs E2E demo terverifikasi, serta syarat rotasi kredensial. Gambar dan struktur slide tidak berubah. Generator diganti nama menjadi `build-deck.cjs` agar dapat dijalankan dari dalam repositori (`package.json` proyek memakai `"type": "module"`).

| Pemeriksaan (24 Nov) | Hasil |
| --- | --- |
| `validate.py` | **All validations PASSED** |
| python-pptx | 18 slide, 18 gambar = 18 berkas media, catatan di 18 slide, 13,33 × 7,5 in |
| Render OfficeImport | Slide 1, 6, 13, 17, 18 dirender ulang dan diperiksa; teks muat dan tidak terpotong |

## Validasi (21 Nov 2026)

| Pemeriksaan | Hasil |
| --- | --- |
| Validasi skema/relasi/content-type (`validate.py` skill pptx, Python 3.12) | **All validations PASSED** |
| Pembukaan ulang dengan python-pptx | 18 slide, 13,33 × 7,5 in, catatan di 18 slide |
| Render visual | Setiap slide dirender dengan **renderer Office bawaan macOS (Quick Look / OfficeImport)** dan diperiksa satu per satu; ditemukan & diperbaiki: teks membungkus pada 4 kotak, kolom sempit di slide 10, bullet tak terlihat di slide gelap, ukuran gambar salah |
| Keterbatasan render | LibreOffice/PowerPoint tidak tersedia di mesin ini. Font **Calibri tidak terpasang**, sehingga renderer memakai font pengganti; teks juga diperiksa dengan pemetaan ke Arial (lebih lebar dari Calibri) sebagai uji konservatif. Tampilan akhir di PowerPoint dapat sedikit berbeda — **mohon buka sekali di PowerPoint sebelum presentasi**. |
