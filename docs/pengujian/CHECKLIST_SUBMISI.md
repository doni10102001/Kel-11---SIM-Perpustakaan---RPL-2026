# Final Submission Checklist — SIM Perpustakaan

Status per 7 November 2026 (audit final). Legenda: ✅ terverifikasi pada audit ini · ⚠️ terverifikasi dengan keterbatasan terdokumentasi · ❌ belum terpenuhi · ❔ tidak terverifikasi.

## A. Repositori & Git

| # | Butir | Status | Bukti / catatan |
| --- | --- | --- | --- |
| A1 | Satu repositori Laravel; Vue di `resources/js/` | ✅ | Struktur proyek |
| A2 | `.env` tidak terlacak | ✅ | `.gitignore:3`, `git ls-files` |
| A3 | Tidak ada rahasia/kredensial di file terlacak | ✅ | `git grep` pola kunci/kata sandi kosong; `APP_KEY=` kosong di `.env.example` |
| A4 | Tidak ada file sementara (log, profil Chrome, `.DS_Store`, SQLite) | ✅ | `git ls-files` |
| A5 | Working tree bersih | ❌ | 2 penghapusan file instruksi masih *staged* (F-06). Commit atau batalkan. |
| A6 | Riwayat commit per phase | ✅ | `86552a1` (Phase 6) dan commit Phase 1–5 sebelumnya |
| A7 | Tidak ada push/deploy dalam audit | ✅ | Tidak dilakukan |

## B. Build & uji (dijalankan ulang)

| # | Butir | Status | Bukti |
| --- | --- | --- | --- |
| B1 | `npm run test:data` | ✅ | 142/142 lulus |
| B2 | `php artisan test` | ✅ | 19/19 lulus, 655 assertion |
| B3 | `npm run build` | ✅ | Berhasil |
| B4 | `php artisan route:list` | ✅ | 24 rute; 18 halaman final |
| B5 | Halaman tanpa DB | ✅ | Probe HTTP 200 tanpa file SQLite; PHPUnit memastikan tanpa query |
| B6 | Uji browser (render, interaksi, console) | ⚠️ | 35/35 halaman, 231/231 cek, tanpa error console, **tetapi skrip tidak ada di repositori** (F-02) |
| B7 | Responsif 360 px (NFR-017) | ⚠️ | 70/70 cek di 390×844 & 360×740 (skrip yang sama, F-02) |
| B8 | Tanpa request eksternal | ✅ | 0 request non-lokal |
| B9 | Firefox/Edge (NFR-021) | ❔ | Hanya Chrome yang diuji |

## C. Kebutuhan & desain

| # | Butir | Status | Bukti |
| --- | --- | --- | --- |
| C1 | Matriks keterlacakan SRS | ✅ | [SRS_TRACEABILITY_MATRIX.md](SRS_TRACEABILITY_MATRIX.md): 37 FR, terdiri atas 28 COMPLIANT, 7 PARTIAL, 2 NOT COMPLIANT |
| C2 | Matriks kepatuhan UML | ✅ | [UML_COMPLIANCE_MATRIX.md](UML_COMPLIANCE_MATRIX.md), tanpa pelanggaran kategori V |
| C3 | Hanya peran Admin & Anggota; tanpa rute Petugas | ✅ | `/demo/petugas/*` → 404 |
| C4 | Deviasi peran SRS (Admin + Petugas) tercatat | ⚠️ | KD + F-07. **Perlu dicantumkan di laporan tugas/SRS final.** |
| C5 | Status memakai kosakata SRS 6.3 | ✅ | `utils/status.js` |
| C6 | Tidak ada istilah internal (SRS/UC/FR/TBD/usulan/prototipe) di UI | ✅ | Pemindaian teks UI |
| C7 | Login dinyatakan sebagai pratinjau, bukan autentikasi | ✅ | `Login.vue:175-178`, README |
| C8 | Kebijakan TBD tidak dikarang | ✅ | Jatuh tempo manual; kuota/masa berlaku booking tidak dibatasi; PDF tidak dibuat |
| C9 | Celah reservasi buntu (anggota Nonaktif + booking Disetujui) | ❌ | F-01 (MEDIUM). Perlu keputusan TBD-06. Tidak memblokir prototipe, tetapi sebaiknya diputuskan atau didokumentasikan. |
| C10 | UML diperbarui sesuai keputusan (Quality Gate #5) | ❌ | 9 inkonsistensi terbuka (F-10). Di luar lingkup kode. |

## D. Data mock

| # | Butir | Status | Bukti |
| --- | --- | --- | --- |
| D1 | ID unik & relasi valid | ✅ | `periksaKonsistensi`, T-MOCK |
| D2 | Dashboard dihitung dari data | ✅ | `ringkasanAdmin`, `ringkasanAnggota` |
| D3 | Transaksi atomik tanpa perubahan parsial | ✅ | T-TRX, skenario audit |
| D4 | Data Anggota hanya milik akun demo | ✅ | Skenario audit E1 |
| D5 | Object Diagram dipetakan konsisten | ✅ | UML matrix §5 |

## E. Paket screenshot & Figma

| # | Butir | Status | Bukti |
| --- | --- | --- | --- |
| E1 | 26 PNG (1 Masuk, 12 Admin, 5 Anggota, 8 state) | ✅ | `docs/screenshots/` (dihitung ulang: 26) |
| E2 | Manifest & QA report | ✅ | Dokumentasi tangkapan layar antarmuka sistem |
| E3 | ZIP hanya berisi screenshot + manifest | ✅ | 32 entri |
| E4 | 26 frame Figma sesuai screenshot | ❔ | **NOT VERIFIED.** Tidak ada berkas Figma lokal; konektor Figma belum diotorisasi (F-13). |
| E5 | Prototipe Figma yang dapat diklik | — | Di luar lingkup |

## F. Dokumentasi

| # | Butir | Status | Catatan |
| --- | --- | --- | --- |
| F1 | README: versi, cara menjalankan, URL, akun pratinjau | ✅ | — |
| F2 | README: status terkini | ❌ | Masih "Phase 5" (F-03) |
| F3 | `KEPUTUSAN_DESAIN.md` mutakhir | ⚠️ | Catatan "Petugas Proses" kedaluwarsa (F-04) |
| F4 | Penjelasan kerangka DB bawaan Laravel yang tidak dipakai | ❌ | F-05 (LOW) |
| F5 | Laporan audit final | ✅ | `docs/audit/` (4 dokumen) |

## Penilaian

**READY WITH DOCUMENTED LIMITATIONS** untuk pengumpulan sebagai **prototipe UI statis**. Syaratnya: A5 diselesaikan, dan E4 diverifikasi bila Figma termasuk yang dinilai. Untuk klaim implementasi SRS penuh dengan backend, statusnya **NOT READY FOR SUBMISSION**. Rincian dasar penilaian ada di [FINAL_COMPLIANCE_AUDIT.md §12](FINAL_COMPLIANCE_AUDIT.md#12-penilaian-akhir).

### Tindakan yang disarankan (urut prioritas, tidak dikerjakan dalam audit ini)

1. Commit atau batalkan dua penghapusan file instruksi (A5 / F-06).
2. Verifikasi 26 frame Figma terhadap screenshot (E4 / F-13).
3. Putuskan penanganan reservasi buntu, catat di KD, lalu tambahkan tes (C9 / F-01).
4. Pindahkan skrip uji browser ke repositori (B6 / F-02).
5. Perbarui README status, catatan KD, dan penjelasan kerangka DB bawaan (F-03, F-04, F-05).
6. Cantumkan deviasi peran dan 9 inkonsistensi UML di laporan tugas (C4, C10 / F-07, F-10).
