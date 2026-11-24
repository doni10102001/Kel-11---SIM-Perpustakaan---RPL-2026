# Dokumentasi SIM Perpustakaan — Kelompok 11

Indeks utama dokumentasi proyek **Sistem Informasi Manajemen Perpustakaan** untuk mata kuliah Rekayasa Perangkat Lunak, Universitas Indraprasta PGRI.

Dosen yang memeriksa pengumpulan Tugas 10 cukup mulai dari bagian 2.

## 1. Gambaran proyek

SIM Perpustakaan adalah aplikasi web berbasis Laravel 13, Vue 3, Inertia.js, Bootstrap 5.3, dan MySQL 8.4. Fiturnya meliputi pengelolaan buku dan eksemplar, anggota, peminjaman, pengembalian, booking dengan batas pengambilan 48 jam, dashboard, dan laporan.

Aplikasi memiliki dua peran:

- **Admin** mengelola data dan transaksi.
- **Anggota** mencari buku, mengajukan booking, dan melihat riwayatnya sendiri.

Anggota kelompok:

| Nama | NPM |
| --- | --- |
| Doni Suhendar | 202243501242 |
| Abdurrahman Wahid | 202343500460 |
| Sarah Tirta Kencana | 202343500443 |

## 2. Dokumentasi Tugas 10 (pengumpulan)

Semua dokumen pengumpulan ada di [`TUGAS-10-FINAL/`](TUGAS-10-FINAL/README.md).

| No. | Persyaratan | Lokasi |
| --- | --- | --- |
| 1 | SRS | [01-SRS](TUGAS-10-FINAL/01-SRS/README.md) |
| 2 | Use Case Diagram | [02-USECASE-DIAGRAM](TUGAS-10-FINAL/02-USECASE-DIAGRAM/README.md) |
| 3 | Class Diagram | [03-CLASS-DIAGRAM](TUGAS-10-FINAL/03-CLASS-DIAGRAM/README.md) |
| 4 | Activity Diagram | [04-ACTIVITY-DIAGRAM](TUGAS-10-FINAL/04-ACTIVITY-DIAGRAM/README.md) |
| 5 | Sequence Diagram | [05-SEQUENCE-DIAGRAM](TUGAS-10-FINAL/05-SEQUENCE-DIAGRAM/README.md) |
| 6 | Deployment Diagram | [06-DEPLOYMENT-DIAGRAM](TUGAS-10-FINAL/06-DEPLOYMENT-DIAGRAM/README.md) |
| 7 | Hasil Desain UI/UX — Google Stitch | [07-GOOGLE-STITCH](TUGAS-10-FINAL/07-GOOGLE-STITCH/README.md) |
| 8 | File Pengujian Software | [08-SOFTWARE-TESTING](TUGAS-10-FINAL/08-SOFTWARE-TESTING/README.md) |
| 9 | Hasil Sistem | [09-HASIL-SISTEM](TUGAS-10-FINAL/09-HASIL-SISTEM/README.md) |
| 10 | File PPT Presentasi | [10-PRESENTASI](TUGAS-10-FINAL/10-PRESENTASI/README.md) |

Status setiap deliverable ada di [README paket](TUGAS-10-FINAL/README.md#daftar-deliverable). Hasil pemeriksaan akhir ada di [checklist akhir](TUGAS-10-FINAL/FINAL_SUBMISSION_CHECKLIST.md).

## 3. Dokumentasi teknis

| Lokasi | Isi |
| --- | --- |
| [`KEPUTUSAN_DESAIN.md`](KEPUTUSAN_DESAIN.md) | Catatan keputusan desain dan aturan bisnis per phase |
| [`requirements/`](requirements/) | SRS v1.1 asli (`Tugas_2_SRS_FINAL.pdf`), arsip yang sudah disahkan |
| [`uml/`](uml/) | Diagram UML asli dari Tugas 3–7 (Draw.io PNG), arsip yang sudah disahkan |
| [`database/`](database/) | Desain skema MySQL ([`SKEMA_DATABASE.md`](database/SKEMA_DATABASE.md)) dan DDL ([`skema_ddl.sql`](database/skema_ddl.sql)) |
| [`fitur/`](fitur/) | Desain fitur: [CRUD Anggota](fitur/CRUD_ANGGOTA.md), [Buku & Eksemplar](fitur/CRUD_BUKU_DAN_EKSEMPLAR.md), [Transaksi](fitur/MANAJEMEN_TRANSAKSI.md), [Kedaluwarsa Booking](fitur/KEDALUWARSA_BOOKING.md), [Konkurensi & Locking](fitur/KONKURENSI_DAN_LOCKING.md) |
| [`keamanan/`](keamanan/) | [Autentikasi & akses](keamanan/AUTENTIKASI_DAN_AKSES.md), [manajemen kredensial](keamanan/MANAJEMEN_KREDENSIAL.md), [pengerasan keamanan](keamanan/PENGERASAN_KEAMANAN.md), [audit keamanan](keamanan/AUDIT_KEAMANAN.md), [rotasi kredensial QA](keamanan/ROTASI_KREDENSIAL_QA.md) |
| [`kinerja/`](kinerja/) | [Audit kinerja kueri](kinerja/AUDIT_KINERJA.md), [dashboard & pelaporan](kinerja/DASHBOARD_DAN_PELAPORAN.md), [spesifikasi laporan](kinerja/SPESIFIKASI_PELAPORAN.md) |
| [`pengujian/`](pengujian/) | [Laporan QA sistem](pengujian/LAPORAN_QA_SISTEM.md) dan [laporan uji transaksi](pengujian/LAPORAN_UJI_TRANSAKSI.md). Hasil pengujian terbaru ada di [08-SOFTWARE-TESTING](TUGAS-10-FINAL/08-SOFTWARE-TESTING/README.md) |

Catatan nama berkas UML asli:

- Nama berkas di `uml/` dipertahankan apa adanya karena merupakan arsip tugas yang sudah dikumpulkan.
- `Development Diagram.drawio.png` sebenarnya berisi **Deployment Diagram** (rancangan produksi).
- Ejaan "Digram" pada berkas State Machine memang berasal dari nama berkas aslinya.

## 4. Arsip pengembangan

[`archive/`](archive/README.md) berisi dokumen historis yang dipertahankan sebagai jejak pengembangan, tetapi sudah tidak menggambarkan kondisi aplikasi saat ini:

- Audit, matriks keterlacakan, dan checklist tahap prototipe statis (7 Nov 2026, sebelum MySQL).
- Screenshot prototipe 7 Nov 2026, yang menjadi acuan mockup Google Stitch.
- Laporan Phase 8B (integrasi baca data).

## Struktur direktori

```text
docs/
├── README.md                 ← indeks ini
├── KEPUTUSAN_DESAIN.md
├── TUGAS-10-FINAL/           ← dokumen pengumpulan Tugas 10
├── requirements/             ← SRS asli (arsip, tidak diubah)
├── uml/                      ← UML asli (arsip, tidak diubah)
├── database/
├── fitur/
├── keamanan/
├── kinerja/
├── pengujian/
└── archive/                  ← dokumen historis tahap prototipe
```

Folder teknis sengaja tidak dipindahkan ke subfolder lain. Komentar di kode aplikasi merujuk langsung ke `docs/KEPUTUSAN_DESAIN.md` dan `docs/fitur/`.
