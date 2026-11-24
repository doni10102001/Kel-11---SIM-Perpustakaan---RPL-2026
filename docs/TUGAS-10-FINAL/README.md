# TUGAS 10 — REKAYASA PERANGKAT LUNAK
## SISTEM INFORMASI MANAJEMEN PERPUSTAKAAN
### KELOMPOK 11

**Universitas Indraprasta PGRI · Program Studi Teknik Informatika · Mata kuliah Rekayasa Perangkat Lunak**

## Ringkasan proyek

SIM Perpustakaan adalah aplikasi web untuk mengelola katalog buku dan eksemplar, data anggota, peminjaman, pengembalian, booking (reservasi buku) dengan batas pengambilan 48 jam, dashboard, dan laporan. Seluruh data tersimpan di MySQL 8.4.

Aplikasi memiliki dua peran:

- **Admin** mengelola data dan transaksi.
- **Anggota** mencari buku, mengajukan booking, dan melihat riwayatnya sendiri.

Aplikasi saat ini berjalan di lingkungan pengembangan lokal dan belum memiliki alamat produksi.

## Anggota kelompok

| Nama | NPM |
| --- | --- |
| Doni Suhendar | 202243501242 |
| Abdurrahman Wahid | 202343500460 |
| Sarah Tirta Kencana | 202343500443 |

Susunan final Kelompok 11 untuk Tugas 10 terdiri dari **tiga anggota** di atas, sesuai keputusan pemilik proyek (24 Nov 2026). Nama dan NPM cocok dengan dokumen kelompok `Kelompok 11 - RPL - Tugas Final.pdf` (22 Nov 2026) serta dokumen Tugas 9, 10, dan 11. Dokumen-dokumen tersebut adalah berkas lokal pemilik dan tidak disimpan di repositori.

> **Catatan arsip:** dokumen yang disusun sebelum perubahan susunan kelompok, yaitu sampul SRS v1.1 (`docs/requirements/`) dan dokumen Tugas 9–11, masih mencantumkan empat nama. Dokumen tersebut sudah dikumpulkan atau disahkan, sehingga tidak diubah.

## Teknologi

Laravel 13 (PHP 8.4) · Vue 3 · Inertia.js 3 · Bootstrap 5.3 · MySQL 8.4 LTS · Vite 8 · Google Stitch (desain UI/UX) · GitHub (kontrol versi) · PHPUnit 12 dan Node.js test runner (pengujian).

## Repositori

<https://github.com/doni10102001/Kel-11---SIM-Perpustakaan---RPL-2026>

> Repositori ini **privat**. Agar dosen dapat membukanya, dosen perlu diundang sebagai kolaborator atau repositori dibuat publik. Paket ini sudah ada di cabang `phase-9b-buku-eksemplar` dan baru tampil di cabang `main` setelah Pull Request digabung.
>
> ⚠️ **Keamanan:** riwayat Git (commit `5f7a030`) memuat dua kredensial yang pernah aktif.
>
> - Kata sandi MySQL uji (K-1) **sudah dirotasi** pada 24 Nov 2026.
> - Kata sandi Admin development (K-2) **belum dirotasi** dan masih berlaku.
>
> **Jangan bagikan repositori kepada dosen sebelum K-2 dirotasi.** Mengundang kolaborator juga membuka akses ke riwayat Git. Prosedurnya ada di [`docs/keamanan/ROTASI_KREDENSIAL_QA.md`](../keamanan/ROTASI_KREDENSIAL_QA.md).

## Daftar deliverable

Urutan mengikuti persyaratan dosen.

| No. | Persyaratan | Tautan | Status |
| --- | --- | --- | --- |
| 1 | SRS | [01-SRS](01-SRS/README.md) · [PDF asli](../requirements/Tugas_2_SRS_FINAL.pdf) | COMPLETE |
| 2 | Use Case Diagram | [02-USECASE-DIAGRAM](02-USECASE-DIAGRAM/README.md) | COMPLETE |
| 3 | Class Diagram | [03-CLASS-DIAGRAM](03-CLASS-DIAGRAM/README.md) | COMPLETE |
| 4 | Activity Diagram | [04-ACTIVITY-DIAGRAM](04-ACTIVITY-DIAGRAM/README.md) | COMPLETE |
| 5 | Sequence Diagram | [05-SEQUENCE-DIAGRAM](05-SEQUENCE-DIAGRAM/README.md) | COMPLETE |
| 6 | Deployment Diagram | [06-DEPLOYMENT-DIAGRAM](06-DEPLOYMENT-DIAGRAM/README.md) | COMPLETE |
| 7 | Hasil Desain UI/UX — Google Stitch | [07-GOOGLE-STITCH](07-GOOGLE-STITCH/README.md) | **REQUIRES OWNER INPUT** |
| 8 | File Pengujian Software | [08-SOFTWARE-TESTING](08-SOFTWARE-TESTING/README.md) | COMPLETE |
| 9 | Hasil Sistem | [09-HASIL-SISTEM](09-HASIL-SISTEM/README.md) | COMPLETE |
| 10 | File PPT Presentasi | [SIM_Perpustakaan_Kelompok_11_Final.pptx](10-PRESENTASI/SIM_Perpustakaan_Kelompok_11_Final.pptx) · [keterangan](10-PRESENTASI/README.md) | COMPLETE |

Nomor 7 belum lengkap karena dua hal:

- Ekspor layar dari proyek Google Stitch belum ada di repositori. Folder [`ekspor-stitch/`](07-GOOGLE-STITCH/ekspor-stitch/README.md) sudah disiapkan.
- Tautan Stitch belum terbukti dapat dibuka tanpa login (diperiksa ulang 24 Nov 2026).

Menurut pemilik proyek, dosen telah mengizinkan Google Stitch sebagai alternatif Figma. Rinciannya ada di [07-GOOGLE-STITCH](07-GOOGLE-STITCH/README.md).

## Cara menjalankan sistem

Ikuti [panduan instalasi](09-HASIL-SISTEM/PANDUAN_INSTALASI.md). Ringkasnya:

1. `composer install` dan `npm install`.
2. Siapkan MySQL dan berkas `.env`.
3. `php artisan migrate`, lalu `php artisan db:seed --class=DemoPerpustakaanSeeder`.
4. `npm run build`, lalu `php artisan serve` dan buka <http://127.0.0.1:8000>.
5. Jalankan `php artisan schedule:work` agar booking kedaluwarsa diproses otomatis.

Alur demo ada di [DEMO_SCENARIO](09-HASIL-SISTEM/DEMO_SCENARIO.md).

## Bukti pengujian

[Laporan pengujian](08-SOFTWARE-TESTING/SOFTWARE_TESTING_REPORT.md) · [Matriks uji](08-SOFTWARE-TESTING/TEST_CASE_MATRIX.md) · [Log eksekusi](08-SOFTWARE-TESTING/bukti/) · [Screenshot aplikasi](09-HASIL-SISTEM/screenshots/)

Hasil eksekusi ulang 21 Nov 2026, dikonfirmasi lagi 24 Nov 2026 setelah perbaikan kredensial skrip QA:

| Suite | Hasil | Catatan |
| --- | --- | --- |
| PHPUnit | **270/270** · 3.466 assertions | Termasuk MySQL nyata dan uji konkurensi dua koneksi |
| Node.js | **245/245** | Logika frontend |
| Browser QA lama | **23/23** cek tercatat | Hanya 7 cek memiliki assertion eksplisit, 8 cek tanpa assertion, dan 2 langkah kondisional tidak dijalankan (laporan §D.4) |
| E2E demo terverifikasi | **22/22** langkah | 7 langkah yang mengubah data diverifikasi dengan kueri MySQL |

## Membuka PPT

Unduh [`SIM_Perpustakaan_Kelompok_11_Final.pptx`](10-PRESENTASI/SIM_Perpustakaan_Kelompok_11_Final.pptx), lalu buka dengan Microsoft PowerPoint, Keynote, atau Google Slides. Setiap slide memiliki catatan pembicara.

## Keterbatasan dan catatan akademik

| Topik | Catatan |
| --- | --- |
| Peran | SRS v1.1 memuat Admin, Petugas, dan Anggota. Implementasi final hanya memakai **Admin** dan **Anggota**; hak Petugas dijalankan Admin (keputusan 7 Nov 2026). Diagram UML asli tidak diubah; perbedaannya dicatat dalam diagram suplemen berlabel terpisah. |
| Desain UI | Memakai Google Stitch, bukan Figma, dengan izin dosen menurut pemilik proyek. Bukti di repositori menunjukkan aplikasi menjadi acuan visual mockup Stitch, bukan sebaliknya (lihat nomor 7). |
| Keamanan | Kredensial QA sudah dihapus dari kode (commit `b06049b`). K-1 sudah dirotasi; K-2 belum. |
| Deployment | Hanya lingkungan lokal yang berjalan. Produksi (Nginx, PHP-FPM, HTTPS, cron, backup) masih berupa rancangan. |
| Aturan bisnis terbuka | TBD-05 (durasi pinjam, kuota, denda), TBD-06 (kuota dan antrean booking), TBD-07 (format ekspor), TBD-08 (retensi data). |
| Pengujian | Deadlock nyata tidak direproduksi; uji beban, HTTPS, backup-restore, dan UAT belum dilakukan. |
| Tenggat | Tenggat yang tercantum adalah 18 Nov 2026, sedangkan paket ini disusun mulai 21 Nov 2026. Paket ini tidak menyatakan bahwa pengumpulan terlambat diterima. |
| Arsip | Audit dan screenshot tahap prototipe (7 Nov 2026, sebelum MySQL) dipindahkan ke [`docs/archive/`](../archive/README.md). Untuk status terkini, gunakan paket ini. |

Checklist akhir: [FINAL_SUBMISSION_CHECKLIST.md](FINAL_SUBMISSION_CHECKLIST.md)
