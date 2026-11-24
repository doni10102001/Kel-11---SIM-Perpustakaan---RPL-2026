# 1. SRS — Software Requirements Specification

| Atribut | Nilai |
| --- | --- |
| Dokumen | *Software Requirements Specification for Sistem Informasi Manajemen Perpustakaan* |
| Versi | **1.1** (sampul: "Version 1.1 - Approved", 24 Oktober 2026). Catatan: §1.1 dan §1.4 di dalam dokumen masih menyebut 1.1 sebagai "rancangan revisi / DRAFT". |
| Template | Karl E. Wiegers (struktur IEEE-style) |
| Penyusun | Kelompok 11. Sampul SRS v1.1 (24 Okt 2026) mencantumkan empat nama sesuai susunan kelompok saat itu; dokumen ini adalah **arsip sebelum perubahan susunan kelompok** dan tidak diubah. Anggota final Tugas 10: Doni Suhendar, Abdurrahman Wahid, Sarah Tirta Kencana (lihat [README paket](../README.md#anggota-kelompok)). |
| Berkas asli | [`docs/requirements/Tugas_2_SRS_FINAL.pdf`](../../requirements/Tugas_2_SRS_FINAL.pdf) (22 halaman, dapat dibuka dan diekstrak; diverifikasi 21 Nov 2026) |
| Status paket | **COMPLETE** — berkas asli dirujuk langsung, tidak disalin dan tidak diubah |

> README ini adalah **ringkasan dan pemandu baca**. Sumber kebenaran tetap PDF asli. Perbedaan antara SRS dan implementasi final dicatat di §8 sebagai *catatan deviasi*; SRS **tidak** ditulis ulang.

## 1. Latar belakang (SRS §1.1, §1.4, §2.1)

SIM Perpustakaan dirancang untuk mengelola koleksi dan layanan peminjaman dalam **satu basis data**. Nilai yang diharapkan menurut SRS §1.4:

- berkurangnya pencatatan ganda;
- meningkatnya ketertelusuran transaksi;
- validasi ketersediaan per eksemplar;
- kemudahan petugas menyajikan laporan.

Metode pengembangan yang tercatat: **Waterfall**, bertahap (SRS §1.4).

## 2. Tujuan sistem

Menyediakan acuan yang konsisten untuk analisis, perancangan UML, pembangunan, pengujian, dan serah terima akademik (SRS §1.1), dengan fungsi utama: autentikasi & hak akses, katalog buku–eksemplar, data anggota, peminjaman/pengembalian atomik, booking, histori, dan laporan (SRS §2.2).

## 3. Aktor (SRS §2.3)

| Aktor di SRS | Wewenang menurut SRS | Implementasi final |
| --- | --- | --- |
| Admin | Kelola akun/peran, buku, eksemplar, anggota, transaksi, laporan | **Admin** — seluruh hak operasional |
| Petugas | Buku, anggota, pinjam, kembali, verifikasi booking, laporan | **Tidak dibuat sebagai peran terpisah**; hak Petugas dijalankan oleh Admin |
| Anggota | Login, cari buku, booking, status & histori milik sendiri | **Anggota** |

## 4. Kebutuhan fungsional (ringkas)

| Kelompok | FR | Ringkasan |
| --- | --- | --- |
| Autentikasi & dashboard | FR-001–004, 016–019 | Login, validasi kredensial di DB, pesan galat, dashboard per peran, logout, pembatasan per peran, blokir sesi tidak valid |
| Buku & eksemplar | FR-005–007, 020–021 | Tambah/ubah buku, pencarian (judul/kode/kategori), banyak eksemplar per judul, tolak kode ganda |
| Anggota | FR-008, 022–023 | Tambah/ubah/cari anggota, aktif/nonaktif tanpa hapus riwayat, kode anggota unik |
| Peminjaman | FR-009–010, 024–026 | Catat pinjam bila anggota & eksemplar valid, simpan jatuh tempo & petugas, cegah pinjam ganda bersamaan, realisasi booking |
| Pengembalian | FR-011–012, 027–028 | Kembalikan pinjaman Aktif, perbarui status, tolak pengembalian ganda, catat waktu & petugas |
| Laporan | FR-014–015, 029 | Laporan buku/anggota/pinjam/kembali, filter periode, cetak |
| Katalog & booking Anggota | FR-030–035 | Cari katalog, ajukan booking (Diajukan), batalkan, setujui/tolak (alasan), Dipesan, realisasi |
| Histori | FR-013, 036–037 | Riwayat transaksi; Anggota hanya melihat miliknya |

Kriteria penerimaan **AC-01 – AC-17** (SRS §6.4) dipakai sebagai dasar [matriks uji](../08-SOFTWARE-TESTING/TEST_CASE_MATRIX.md).

## 5. Kebutuhan nonfungsional (ringkas, SRS §5)

- **Kinerja:** respons ±3 detik untuk operasi umum (NFR-001), pagination server-side (NFR-019), target beban 25 sesi (NFR-018, *belum diuji*).
- **Keandalan/keamanan data:** transaksi konsisten & rollback (NFR-006, NFR-020), backup–restore (NFR-016, *belum diterapkan*).
- **Keamanan:** kata sandi di-hash (NFR-003), otorisasi server (NFR-004), sesi berakhir saat logout (NFR-005), HTTPS produksi (NFR-011, *belum ada produksi*), validasi server & ORM (NFR-013), CSRF (NFR-014), audit event (NFR-015), privasi data anggota (NFR-022).
- **Kualitas:** mudah dipahami, dapat dipelihara, galat penting tercatat, layar ≥360 px (NFR-017), browser modern (NFR-021).

## 6. Aturan bisnis (SRS §5.5)

BR-01 pinjam hanya untuk anggota aktif & eksemplar Tersedia · BR-02 pinjam + status eksemplar atomik · BR-03/04 pengembalian hanya untuk pinjaman Aktif → Selesai & Tersedia · BR-05 maksimal satu pinjaman Aktif per eksemplar · BR-06 alur status booking · BR-07 eksemplar Dipesan hanya keluar lewat realisasi · BR-08 Anggota hanya melihat datanya · BR-09 durasi/kuota/batas booking masih TBD.

## 7. Kebutuhan teknologi (SRS §2.4–2.5)

PHP 8.4 + Laravel 13, Vue 3 + Bootstrap 5.3 + Inertia.js + Vite, MySQL 8.4 LTS (InnoDB, utf8mb4), modular monolith; produksi yang diusulkan: Nginx + PHP-FPM 8.4 di Linux, HTTPS. Waktu ditampilkan dalam WIB.

## 8. Catatan deviasi implementasi (keputusan yang disetujui pemilik proyek)

Sumber: [`docs/KEPUTUSAN_DESAIN.md`](../../KEPUTUSAN_DESAIN.md). SRS tidak diubah; perbedaan berikut berlaku untuk aplikasi final.

| # | SRS v1.1 | Implementasi final | Dasar |
| --- | --- | --- | --- |
| D-1 | Tiga peran: Admin, Petugas, Anggota | **Dua peran: Admin dan Anggota.** Hak Petugas dijalankan Admin. Fitur "Kelola pengguna dan role" tidak dibuat. | Keputusan final peran, 7 Nov 2026 (TBD-03) |
| D-2 | Persistensi MySQL dirancang | **MySQL 8.4 nyata** (13 berkas migration, FK RESTRICT, CHECK, UNIQUE, penyimpanan UTC) | Phase 7A, D-01..D-08 |
| D-3 | Autentikasi dirancang | **Autentikasi Laravel nyata**: sesi di tabel `sessions`, hash kata sandi, rate limit 5×/60 detik, CSRF, regenerasi sesi | Phase 8A–8D |
| D-4 | Login Anggota: metode TBD-02 | Admin membuat akses login (username) secara opsional; Anggota **wajib ganti kata sandi** saat login pertama | Phase 8C–8D |
| D-5 | Batas waktu booking TBD-06 | **Booking Disetujui kedaluwarsa 48 jam** setelah persetujuan (status `Kedaluwarsa`), diproses `bookings:expire` tiap 5 menit dan direkonsiliasi saat transaksi | Batch 2, migration 000009 |
| D-6 | Durasi pinjam/denda TBD-05 | Tanggal jatuh tempo diisi manual oleh Admin; tanpa denda/perpanjangan | Keputusan Phase 0 #4 |
| D-7 | Ekspor laporan TBD-07 | Cetak lewat dialog cetak browser saja; tanpa PDF/Excel/CSV | KD 4.9 |
| D-8 | Hapus data TBD-08 | Tidak ada aksi hapus anggota/buku/eksemplar | KD 3.7 |
| D-9 | NFR-012 mengusulkan batas sesi idle 30 menit | `.env.example` memakai `SESSION_LIFETIME=120` (menit); dapat dikonfigurasi, belum diselaraskan ke 30 | Konfigurasi repositori |

TBD yang **masih terbuka**: TBD-05 (durasi, kuota, denda), TBD-06 (kuota/antrean booking Diajukan), TBD-07 (format ekspor), TBD-08 (retensi data), TBD-09/10 (sizing & lingkungan produksi).
