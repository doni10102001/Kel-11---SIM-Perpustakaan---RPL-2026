# Final Submission Checklist — Tugas 10

- Diperiksa: **24 November 2026**. Pemeriksaan pertama 21 Nov 2026.
- Basis kode: commit `80e2f5f` (pohon identik dengan `origin/main` @ `66d3fb7`), ditambah dua commit lokal di cabang ini: `b06049b` (remediasi kredensial skrip QA) dan commit dokumentasi paket ini. Keduanya belum di-push.
- Cabang lokal: `phase-9b-buku-eksemplar`.

Legenda: ✅ terpenuhi · ⚠️ terpenuhi dengan catatan · ❌ belum terpenuhi / perlu tindakan pemilik

## A. Sepuluh deliverable

| # | Pemeriksaan | Status | Bukti |
| --- | --- | --- | --- |
| 1 | SRS ada dan dapat dibuka | ✅ | `docs/requirements/Tugas_2_SRS_FINAL.pdf`, 22 halaman, teks dapat diekstrak, tidak diubah |
| 2 | Use Case Diagram ada dan terbaca | ✅ | Asli 1902×1098 px (tidak diubah) + suplemen 3896×2604 px |
| 3 | Class Diagram ada dan terbaca | ✅ | Asli 2200×1519 px (tidak diubah) + suplemen 4542×3682 px |
| 4 | Activity Diagram ada dan terbaca | ✅ | A01–A09 asli + A10–A12 suplemen |
| 5 | Sequence Diagram ada dan terbaca | ✅ | S01–S09 asli + S-IMPL-01..08 suplemen |
| 6 | Deployment Diagram ada dan terbaca | ✅ | Usulan produksi (asli) + lokal aktual (suplemen), diberi label jelas |
| 7 | Bukti desain Google Stitch ada | ❌ | Tautan valid (HTTP 200, 24 Nov), tetapi tanpa login tampil kosong. Ekspor layar Stitch belum ada (folder `ekspor-stitch/` disiapkan). Instruksi dosen Tugas 9: "Pergunakan FIGMA … **bukan dengan AI**", sedangkan Stitch berbasis AI; persetujuan dosen belum ada |
| 8 | Laporan pengujian didukung hasil nyata | ✅ | Log 21 Nov dan 24 Nov + daftar 270 nama tes + bukti E2E JSON; empat angka uji dibedakan |
| 9 | Screenshot sistem & panduan ada | ✅ | 31 screenshot aplikasi MySQL nyata, panduan instalasi, panduan penggunaan, skenario demo |
| 10 | PPTX ada dan dapat dibuka | ✅ | 18 slide, `validate.py`: All validations PASSED, setiap slide dirender dengan OfficeImport macOS (lihat [10-PRESENTASI](10-PRESENTASI/README.md)) |

## B. Integritas isi

| Pemeriksaan | Status | Catatan |
| --- | --- | --- |
| Tidak ada tautan Markdown relatif yang rusak | ✅ | Seluruh tautan dan anchor diperiksa skrip (paket + README root + dokumen rotasi) |
| Gambar dan PDF yang dirujuk dapat dibuka | ✅ | Semua PNG terbaca dan SRS PDF terbaca |
| Tautan Stitch sudah diverifikasi aksesnya | ⚠️ | URL aktif, tetapi akses tanpa login tidak menampilkan desain; ditandai jelas di 07 |
| Tidak ada tautan Figma palsu | ✅ | Tidak ada tautan Figma sama sekali |
| Tidak ada folder kosong yang ditandai COMPLETE | ✅ | 07 ditandai REQUIRES OWNER INPUT; `ekspor-stitch/` kosong dan dinyatakan kosong |
| Tidak ada gambar hilang di PPTX | ✅ | Jumlah berkas media tertanam sama dengan jumlah gambar di slide |
| Tidak ada hasil uji rekaan | ✅ | Baseline, eksekusi 21 Nov, dan eksekusi 24 Nov dipisah; keterbatasan Browser QA lama diungkap (7/8/8) |
| Screenshot aplikasi tidak disajikan sebagai ekspor Stitch | ✅ | Tiga jenis bukti visual (A prototipe, B ekspor Stitch, C aplikasi final) dipisah di 07 §3 |
| Diagram suplemen tidak menggantikan diagram asli | ✅ | SRS dan UML asli tidak diubah |
| Klaim "desain Stitch memandu implementasi" | ⚠️ | **Tidak didukung bukti**. Kronologi yang terbukti: aplikasi lebih dulu, lalu mockup Stitch dibuat menyamai aplikasi |
| Identitas anggota dan NPM | ✅ | Susunan final **tiga anggota** (Doni Suhendar 202243501242, Abdurrahman Wahid 202343500460, Sarah Tirta Kencana 202343500443), keputusan pemilik 24 Nov 2026. NPM cocok dengan dokumen "Tugas Final" 22 Nov dan dokumen Tugas 9–11. Dokumen arsip lama (SRS v1.1, Tugas 9–11) yang masih memuat empat nama tidak diubah |

## C. Pengujian

| Suite | 21 Nov 2026 | 24 Nov 2026 (setelah remediasi) |
| --- | --- | --- |
| PHPUnit | 270/270, 3.466 assertions | 270/270, 3.466 assertions |
| Node.js | 245/245 | 245/245 |
| `npm run build` | — | Sukses |
| Browser QA lama | 23/23 cek tercatat (7 ber-assertion eksplisit, 8 tanpa assertion, 2 kondisional tidak tereksekusi) | 23/23, keterbatasan sama; kredensial dari environment, Admin uji berkata sandi acak |
| E2E demo terverifikasi | 22/22 (7 langkah mutasi diverifikasi kueri MySQL, 15 langkah ber-assertion UI/rute) | 22/22, rincian sama |
| Integritas data dev (read-only) | 0 pelanggaran invarian | — |
| DB development sebelum/sesudah | Identik | Identik |
| DB uji setelah selesai | Semua tabel 0 baris | Semua tabel 0 baris |
| Kredensial di log | — | Tidak ditemukan |

## D. Keamanan

| Pemeriksaan | Status | Catatan |
| --- | --- | --- |
| Tidak ada berkas `.env`/kunci di paket atau di Git | ✅ | Hanya `.env.example` (nilai kosong) yang dilacak |
| Nilai rahasia `.env` tidak ada di berkas terlacak (working tree) | ✅ | 24 Nov: `DB_PASSWORD`, `DB_TEST_PASSWORD`, `SEED_ADMIN_PASSWORD`, `APP_KEY` tidak ditemukan; pola token/kunci privat umum tidak ditemukan |
| Kredensial hardcoded di `scripts/qa-browser-journey.mjs` | ✅ (kode) | Dihapus. Kredensial MySQL uji wajib dari environment, tanpa nilai bawaan dan dengan gagal-cepat. Admin dan anggota QA memakai kata sandi acak sekali pakai. Di-commit lokal sebagai `b06049b` (belum di-push) |
| Skrip capture `_alat/capture-hasil-sistem.mjs` | ✅ | Pola yang sama; tidak lagi membaca `.env` langsung |
| Kredensial di riwayat Git | ❌ | `DB_TEST_PASSWORD` dan `SEED_ADMIN_PASSWORD` ada di commit `5f7a030` (`origin/main`). **Keduanya masih aktif** per 24 Nov (diperiksa tanpa mencetak nilai). Riwayat tidak ditulis ulang |
| Rotasi kredensial | ❌ | Belum dilakukan karena butuh otorisasi pemilik. Prosedur: [`docs/keamanan/ROTASI_KREDENSIAL_QA.md`](../keamanan/ROTASI_KREDENSIAL_QA.md) |
| Data anggota sensitif / kontak pribadi | ✅ | Hanya data demo fiktif; tidak ada email atau kontak pribadi pemilik |

Syarat sebelum repositori dibuka (publik atau undangan dosen): **K-1 (MySQL uji) dan K-2 (Admin) sudah dirotasi**, lalu baris "Kredensial di riwayat Git" dan "Rotasi kredensial" diubah menjadi ✅.

## E. Keselamatan Git & lingkungan

| Pemeriksaan | Status |
| --- | --- |
| Tidak ada perubahan logika bisnis, migration, atau konfigurasi aplikasi | ✅ Kode yang berubah hanya `scripts/qa-browser-journey.mjs` (skrip QA). Sisanya dokumentasi |
| `git diff --check` | ✅ Bersih |
| Tidak ada push, merge, rebase, amend, force-push, atau penulisan ulang riwayat; perubahan hanya berupa dua commit lokal baru di `phase-9b-buku-eksemplar` (bukan `main`), atas persetujuan pemilik | ✅ |
| Tidak menjalankan `migrate:fresh`, `db:wipe`, atau `bookings:expire` terhadap DB development | ✅ |
| MySQL tidak di-restart / dikonfigurasi ulang; akun MySQL tidak diubah | ✅ |
| Vite dev server pengguna tidak terganggu | ✅ `public/hot` tidak ada saat pengujian |

## Tindakan pemilik sebelum mengumpulkan

1. **Rotasi K-1 dan K-2** sesuai [`ROTASI_KREDENSIAL_QA.md`](../keamanan/ROTASI_KREDENSIAL_QA.md), lalu jalankan ulang PHPUnit dan Browser QA.
2. **Nomor 7:**
   - Tanyakan ke dosen secara tertulis apakah Google Stitch (berbasis AI) diterima sebagai pengganti Figma.
   - Atur berbagi tautan dan uji di *incognito*.
   - Ekspor layar ke `07-GOOGLE-STITCH/ekspor-stitch/`.
3. Commit lokal sudah dibuat; setelah langkah 1, push cabang dan gabungkan ke `main` lewat PR agar terlihat di GitHub.
4. Beri dosen akses ke repositori privat **setelah** langkah 1.
5. Buka PPTX sekali di PowerPoint (font Calibri) untuk memastikan tata letak.
6. Tenggat yang tercantum (18 Nov 2026) sudah lewat. Konfirmasikan langsung ke dosen apakah pengumpulan masih diterima.

## Penilaian kesiapan

**READY WITH LIMITATIONS**, dengan dua syarat keras:

- Kredensial wajib dirotasi sebelum repositori dibuka.
- Nomor 7 memerlukan persetujuan dosen terkait instruksi "bukan dengan AI".

Sembilan dari sepuluh deliverable lengkap dan terverifikasi.
