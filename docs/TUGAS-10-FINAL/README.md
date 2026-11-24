# TUGAS 10 — REKAYASA PERANGKAT LUNAK
## SISTEM INFORMASI MANAJEMEN PERPUSTAKAAN
### KELOMPOK 11

**Universitas Indraprasta PGRI · Program Studi Teknik Informatika · Mata kuliah Rekayasa Perangkat Lunak**

## Ringkasan proyek

SIM Perpustakaan adalah aplikasi web untuk mengelola katalog buku dan eksemplar, data anggota, peminjaman, pengembalian, booking (reservasi) dengan kedaluwarsa otomatis 48 jam, dashboard, dan laporan. Seluruh data tersimpan di MySQL 8.4. Aplikasi memiliki dua peran: **Admin** dan **Anggota**. Saat ini aplikasi berjalan di lingkungan pengembangan lokal; tidak ada URL produksi.

## Anggota kelompok

| Nama | NPM |
| --- | --- |
| Doni Suhendar | 202243501242 |
| Abdurrahman Wahid | 202343500460 |
| Sarah Tirta Kencana | 202343500443 |

Susunan final Kelompok 11 untuk pengumpulan Tugas 10 terdiri dari **tiga anggota** di atas, sesuai keputusan pemilik proyek (24 Nov 2026). Nama dan NPM cocok dengan dokumen kelompok `Kelompok 11 - RPL - Tugas Final.pdf` (22 Nov 2026) serta dokumen Tugas 9, 10, dan 11 (berkas lokal pemilik, bukan bagian repositori).

> **Catatan arsip:** dokumen yang disusun sebelum perubahan susunan kelompok, yaitu sampul SRS v1.1 (`docs/requirements/`) dan dokumen Tugas 9–11, masih mencantumkan empat nama. Dokumen tersebut adalah arsip yang sudah dikumpulkan atau disahkan, sehingga tidak diubah.

## Teknologi

Laravel 13 (PHP 8.4) · Vue 3 · Inertia.js 3 · Bootstrap 5.3 · MySQL 8.4 LTS · Vite 8 · Google Stitch (desain UI/UX) · GitHub (version control) · PHPUnit 12 & Node test runner (pengujian).

## Repositori

<https://github.com/doni10102001/Kel-11---SIM-Perpustakaan---RPL-2026>

> Repositori ini **privat**. Dosen perlu diundang sebagai kolaborator atau repositori dibuat publik sebelum tautan dikumpulkan. Paket `docs/TUGAS-10-FINAL/` baru terlihat di GitHub setelah di-commit, di-push, dan digabung ke `main` oleh pemilik proyek.
>
> ⚠️ **Keamanan:** riwayat Git (commit `5f7a030`) memuat dua kredensial aktif, yaitu kata sandi MySQL uji dan kata sandi Admin. Kode kerja sudah dibersihkan, tetapi riwayat tidak terhapus. **Jangan buat repositori publik sebelum kedua kredensial dirotasi**; prosedurnya ada di [`docs/keamanan/ROTASI_KREDENSIAL_QA.md`](../keamanan/ROTASI_KREDENSIAL_QA.md). Mengundang dosen sebagai kolaborator juga memberi akses ke riwayat itu, jadi lakukan rotasi lebih dulu.

## Daftar deliverable (urutan sesuai persyaratan dosen)

1. [SRS](01-SRS/README.md)
2. [Use Case Diagram](02-USECASE-DIAGRAM/README.md)
3. [Class Diagram](03-CLASS-DIAGRAM/README.md)
4. [Activity Diagram](04-ACTIVITY-DIAGRAM/README.md)
5. [Sequence Diagram](05-SEQUENCE-DIAGRAM/README.md)
6. [Deployment Diagram](06-DEPLOYMENT-DIAGRAM/README.md)
7. [Hasil Desain UI/UX — Google Stitch (Pengganti Figma)](07-GOOGLE-STITCH/README.md)
8. [File Pengujian Software](08-SOFTWARE-TESTING/README.md)
9. [Hasil Sistem](09-HASIL-SISTEM/README.md)
10. [File PPT Presentasi](10-PRESENTASI/SIM_Perpustakaan_Kelompok_11_Final.pptx)

| NO | PERSYARATAN | LINK | STATUS |
| --- | --- | --- | --- |
| 1 | SRS | [01-SRS](01-SRS/README.md) → [PDF asli](../requirements/Tugas_2_SRS_FINAL.pdf) | COMPLETE |
| 2 | Use Case Diagram | [02-USECASE-DIAGRAM](02-USECASE-DIAGRAM/README.md) | COMPLETE |
| 3 | Class Diagram | [03-CLASS-DIAGRAM](03-CLASS-DIAGRAM/README.md) | COMPLETE |
| 4 | Activity Diagram | [04-ACTIVITY-DIAGRAM](04-ACTIVITY-DIAGRAM/README.md) | COMPLETE |
| 5 | Sequence Diagram | [05-SEQUENCE-DIAGRAM](05-SEQUENCE-DIAGRAM/README.md) | COMPLETE |
| 6 | Deployment Diagram | [06-DEPLOYMENT-DIAGRAM](06-DEPLOYMENT-DIAGRAM/README.md) | COMPLETE |
| 7 | Hasil Desain UI/UX — Google Stitch (Pengganti Figma) | [07-GOOGLE-STITCH](07-GOOGLE-STITCH/README.md) | **REQUIRES OWNER INPUT** |
| 8 | File Pengujian Software | [08-SOFTWARE-TESTING](08-SOFTWARE-TESTING/README.md) | COMPLETE |
| 9 | Hasil Sistem | [09-HASIL-SISTEM](09-HASIL-SISTEM/README.md) | COMPLETE |
| 10 | File PPT Presentasi | [SIM_Perpustakaan_Kelompok_11_Final.pptx](10-PRESENTASI/SIM_Perpustakaan_Kelompok_11_Final.pptx) · [keterangan](10-PRESENTASI/README.md) | COMPLETE |

Nomor 7 belum lengkap karena:

- (a) Ekspor layar dari proyek Google Stitch belum ada di repositori. Folder [`ekspor-stitch/`](07-GOOGLE-STITCH/ekspor-stitch/README.md) sudah disiapkan.
- (b) Tautan Stitch belum terbukti dapat dibuka tanpa login (diperiksa ulang 24 Nov 2026).
- (c) Instruksi dosen Tugas 9 berbunyi "Pergunakan FIGMA … **bukan dengan AI**", sedangkan Google Stitch adalah alat desain berbasis AI. Karena itu persetujuan dosen yang eksplisit **wajib** diperoleh.

Rinciannya ada di [07-GOOGLE-STITCH](07-GOOGLE-STITCH/README.md).

## Cara membuka sistem

Ikuti [PANDUAN_INSTALASI](09-HASIL-SISTEM/PANDUAN_INSTALASI.md): `composer install`, `npm install`, siapkan MySQL & `.env`, `php artisan migrate`, `php artisan db:seed --class=DemoPerpustakaanSeeder`, `npm run build`, `php artisan serve` → <http://127.0.0.1:8000>, dan `php artisan schedule:work` untuk penjadwal. Alur demo: [DEMO_SCENARIO](09-HASIL-SISTEM/DEMO_SCENARIO.md).

## Cara melihat bukti pengujian

[Laporan pengujian](08-SOFTWARE-TESTING/SOFTWARE_TESTING_REPORT.md) · [Matriks uji](08-SOFTWARE-TESTING/TEST_CASE_MATRIX.md) · [Log eksekusi ulang 21 Nov 2026](08-SOFTWARE-TESTING/bukti/) · [Screenshot aplikasi](09-HASIL-SISTEM/screenshots/)

Ringkasan eksekusi ulang 21 Nov 2026, dikonfirmasi lagi 24 Nov 2026 setelah remediasi kredensial skrip QA:

| Suite | Hasil | Catatan |
| --- | --- | --- |
| PHPUnit | **270/270** · 3.466 assertions | Termasuk MySQL nyata dan konkurensi dua koneksi |
| Node.js | **245/245** | Logika frontend |
| Browser QA lama | **23/23** cek tercatat | Hanya 7 cek ber-assertion eksplisit, 8 cek tanpa assertion, 2 langkah kondisional tidak tereksekusi (laporan §D.4) |
| E2E demo terverifikasi | **22/22** langkah | Setiap mutasi diverifikasi kueri MySQL |

## Cara membuka PPT

Unduh [`10-PRESENTASI/SIM_Perpustakaan_Kelompok_11_Final.pptx`](10-PRESENTASI/SIM_Perpustakaan_Kelompok_11_Final.pptx) lalu buka dengan Microsoft PowerPoint (atau Keynote / Google Slides). Catatan pembicara tersedia di setiap slide.

## Keterbatasan dan catatan akademik

- **Peran:** SRS v1.1 memuat Admin, Petugas, dan Anggota. Implementasi final memakai **Admin dan Anggota**; hak Petugas dijalankan Admin (keputusan 7 Nov 2026). Diagram UML asli tidak diubah; perbedaan dicatat dan diagram suplemen diberi label terpisah.
- **Desain UI:** memakai Google Stitch, bukan Figma. Stitch adalah alat berbasis AI, padahal instruksi dosen meminta Figma "bukan dengan AI". Bukti di repositori menunjukkan website menjadi acuan visual bagi mockup Stitch, bukan sebaliknya (lihat nomor 7).
- **Keamanan:** kredensial QA yang pernah ter-commit sudah dihapus dari kode kerja (24 Nov 2026), tetapi masih ada di riwayat Git dan masih aktif sampai dirotasi pemilik.
- **Deployment:** hanya lingkungan lokal yang berjalan. Produksi (Nginx, PHP-FPM, HTTPS, cron, backup) masih rancangan usulan.
- **Aturan bisnis terbuka:** TBD-05 (durasi pinjam, kuota, denda), TBD-06 (kuota/antrean booking), TBD-07 (format ekspor), TBD-08 (retensi data).
- **Pengujian:** deadlock nyata tidak direproduksi; uji beban, HTTPS, backup-restore, dan UAT belum dilakukan.
- **Tenggat:** tenggat yang tercantum adalah 18 November 2026, dan paket ini disusun 21 November 2026. Paket ini tidak menyatakan bahwa pengumpulan terlambat masih diterima.
- Dokumen audit lama di `docs/pengujian/` dan `docs/analisis/` (7 Nov 2026) menggambarkan tahap prototipe statis sebelum MySQL. Untuk status terkini, rujuk paket ini.

Checklist akhir: [FINAL_SUBMISSION_CHECKLIST.md](FINAL_SUBMISSION_CHECKLIST.md)
