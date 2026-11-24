# Final Submission Checklist — Tugas 10

| Atribut | Nilai |
| --- | --- |
| Diperiksa | **24 November 2026** (pemeriksaan pertama 21 Nov 2026) |
| Cabang | `phase-9b-buku-eksemplar` |
| Basis kode | `80e2f5f` (isinya identik dengan `origin/main` @ `66d3fb7`) |
| Commit Tugas 10 | `b06049b` (perbaikan kredensial skrip QA), `268407f` (paket dokumentasi), `e33abb5` (anggota final). Ketiganya sudah di-push ke cabang; Pull Request ke `main` belum dibuat |

Legenda: ✅ terpenuhi · ⚠️ terpenuhi dengan catatan · ❌ belum terpenuhi atau perlu tindakan pemilik

## A. Sepuluh deliverable

| No. | Pemeriksaan | Status | Bukti |
| --- | --- | --- | --- |
| 1 | SRS ada dan dapat dibuka | ✅ | `docs/requirements/Tugas_2_SRS_FINAL.pdf`, 22 halaman, teks dapat diekstrak, tidak diubah |
| 2 | Use Case Diagram ada dan terbaca | ✅ | Diagram asli 1902×1098 px (tidak diubah) dan suplemen 3896×2604 px |
| 3 | Class Diagram ada dan terbaca | ✅ | Diagram asli 2200×1519 px (tidak diubah) dan suplemen 4542×3682 px |
| 4 | Activity Diagram ada dan terbaca | ✅ | A01–A09 asli dan A10–A12 suplemen |
| 5 | Sequence Diagram ada dan terbaca | ✅ | S01–S09 asli dan S-IMPL-01..08 suplemen |
| 6 | Deployment Diagram ada dan terbaca | ✅ | Rancangan produksi (asli) dan lingkungan lokal aktual (suplemen), masing-masing diberi label |
| 7 | Hasil desain Google Stitch ada | ❌ | Izin dosen atas Stitch dinyatakan pemilik proyek (24 Nov 2026), tetapi ekspor layar Stitch belum ada (folder `ekspor-stitch/` sudah disiapkan) dan tautan tampil kosong tanpa login |
| 8 | Laporan pengujian didukung hasil nyata | ✅ | Log 21 dan 24 Nov, daftar 270 nama tes, bukti E2E JSON; empat angka uji dibedakan |
| 9 | Screenshot sistem dan panduan ada | ✅ | 31 screenshot aplikasi MySQL nyata, panduan instalasi, panduan penggunaan, skenario demo |
| 10 | PPTX ada dan dapat dibuka | ✅ | 18 slide, `validate.py`: All validations PASSED, setiap slide dirender dan diperiksa (lihat [10-PRESENTASI](10-PRESENTASI/README.md)) |

## B. Integritas isi

| Pemeriksaan | Status | Catatan |
| --- | --- | --- |
| Tautan Markdown relatif tidak ada yang rusak | ✅ | Seluruh tautan dan anchor di `docs/` dan README utama diperiksa dengan skrip |
| Gambar dan PDF yang dirujuk dapat dibuka | ✅ | Semua PNG dan PDF SRS terbaca |
| Akses tautan Stitch | ⚠️ | URL aktif, tetapi tanpa login tidak menampilkan desain; dicatat di nomor 7 |
| Tidak ada tautan Figma palsu | ✅ | Tidak ada tautan Figma sama sekali |
| Tidak ada folder kosong yang ditandai COMPLETE | ✅ | Nomor 7 berstatus REQUIRES OWNER INPUT; `ekspor-stitch/` dinyatakan kosong |
| Tidak ada gambar hilang di PPTX | ✅ | Jumlah berkas media sama dengan jumlah gambar di slide |
| Tidak ada hasil uji rekaan | ✅ | Baseline, eksekusi 21 Nov, dan eksekusi 24 Nov dipisah; keterbatasan Browser QA lama diungkap (7/8/8) |
| Screenshot aplikasi tidak disajikan sebagai ekspor Stitch | ✅ | Tiga jenis bukti visual (A prototipe, B ekspor Stitch, C aplikasi final) dipisah di nomor 7 §3 |
| Diagram suplemen tidak menggantikan diagram asli | ✅ | SRS dan UML asli tidak diubah |
| Klaim "desain Stitch memandu implementasi" | ⚠️ | **Tidak didukung bukti.** Urutan yang terbukti: aplikasi dibuat lebih dulu, lalu mockup Stitch menyamai aplikasi |
| Identitas anggota dan NPM | ✅ | Tiga anggota final: Doni Suhendar 202243501242, Abdurrahman Wahid 202343500460, Sarah Tirta Kencana 202343500443 (keputusan pemilik 24 Nov 2026). Dokumen arsip lama yang masih memuat empat nama tidak diubah |

## C. Pengujian

| Suite | 21 Nov 2026 | 24 Nov 2026 (setelah perbaikan skrip QA) |
| --- | --- | --- |
| PHPUnit | 270/270, 3.466 assertions | 270/270, 3.466 assertions |
| Node.js | 245/245 | 245/245 |
| `npm run build` | — | Sukses |
| Browser QA lama | 23/23 cek tercatat (7 dengan assertion eksplisit, 8 tanpa assertion, 2 kondisional tidak dijalankan) | 23/23, keterbatasan sama |
| E2E demo terverifikasi | 22/22 (7 langkah mutasi diverifikasi kueri MySQL, 15 langkah dengan assertion rute/teks) | 22/22, rincian sama |
| Integritas data dev (baca-saja) | 0 pelanggaran invarian | — |
| DB development sebelum/sesudah | Identik | Identik |
| DB uji setelah selesai | Semua tabel 0 baris | Semua tabel 0 baris |
| Kredensial di log | — | Tidak ditemukan |

Pengujian 24 Nov dijalankan **sebelum** rotasi K-1. Regression test lengkap setelah rotasi belum dijalankan karena menunggu rotasi K-2. Setelah rotasi K-1, koneksi Laravel ke DB uji sudah diperiksa dan berhasil.

## D. Keamanan

| Pemeriksaan | Status | Catatan |
| --- | --- | --- |
| Tidak ada berkas `.env` atau kunci di Git | ✅ | Hanya `.env.example` (nilai kosong) yang dilacak |
| Nilai rahasia `.env` tidak ada di berkas terlacak | ✅ | `DB_PASSWORD`, `DB_TEST_PASSWORD`, `SEED_ADMIN_PASSWORD`, `APP_KEY` tidak ditemukan; pola token dan kunci privat umum tidak ditemukan |
| Kredensial hardcoded di `scripts/qa-browser-journey.mjs` | ✅ | Dihapus di commit `b06049b`. Kredensial MySQL uji wajib dari environment, tanpa nilai bawaan |
| Skrip capture `_alat/capture-hasil-sistem.mjs` | ✅ | Pola yang sama; tidak membaca `.env` secara langsung |
| K-1: kata sandi MySQL `sim_perpus_test` | ✅ | **Dirotasi 24 Nov 2026** atas persetujuan pemilik. Kata sandi lama ditolak (ERROR 1045) di `@localhost` dan `@127.0.0.1`; hak akses tidak berubah; MySQL tidak di-restart |
| K-2: kata sandi Admin development | ❌ | **Belum dirotasi.** Nilai lama masih berlaku dan tercatat di riwayat Git (commit `5f7a030`) |
| Riwayat Git | ⚠️ | Tidak ditulis ulang. Nilai lama tetap tercatat; rotasi membuatnya tidak dapat dipakai |
| Data anggota sensitif dan kontak pribadi | ✅ | Hanya data demo fiktif |

Syarat sebelum repositori dibagikan (publik atau undangan dosen): **K-2 sudah dirotasi dan diverifikasi**. Prosedurnya ada di [`docs/keamanan/ROTASI_KREDENSIAL_QA.md`](../keamanan/ROTASI_KREDENSIAL_QA.md).

## E. Keselamatan Git dan lingkungan

| Pemeriksaan | Status |
| --- | --- |
| Tidak ada perubahan logika bisnis, migration, atau konfigurasi aplikasi | ✅ Kode yang berubah hanya `scripts/qa-browser-journey.mjs` (skrip QA); sisanya dokumentasi |
| `git diff --check` | ✅ Bersih |
| Tidak ada force-push, rebase, amend, merge, atau penulisan ulang riwayat | ✅ Push ke cabang dilakukan sebagai fast-forward atas permintaan pemilik |
| Tidak menjalankan `migrate:fresh`, `db:wipe`, atau `bookings:expire` terhadap DB development | ✅ |
| MySQL tidak di-restart atau dikonfigurasi ulang | ✅ Akun yang diubah hanya `sim_perpus_test` (rotasi K-1 yang disetujui) |
| Vite dev server pengguna tidak terganggu | ✅ `public/hot` tidak ada saat pengujian |

## Tindakan pemilik sebelum mengumpulkan

1. **Rotasi K-2** lewat `/admin/kata-sandi`, lalu minta verifikasi ulang dan jalankan regression test.
2. **Nomor 7:** atur berbagi tautan Stitch, uji di *incognito*, ekspor layar ke `07-GOOGLE-STITCH/ekspor-stitch/`, dan simpan bukti izin dosen bila ada.
3. Buat Pull Request dari `phase-9b-buku-eksemplar` ke `main`.
4. Beri dosen akses ke repositori privat **setelah** langkah 1.
5. Buka PPTX sekali di PowerPoint (font Calibri) untuk memastikan tata letak.
6. Tenggat yang tercantum (18 Nov 2026) sudah lewat. Konfirmasikan ke dosen apakah pengumpulan masih diterima.

## Penilaian kesiapan

**READY WITH LIMITATIONS.** Sembilan dari sepuluh deliverable lengkap dan terverifikasi. Ada dua syarat:

- K-2 wajib dirotasi sebelum repositori dibagikan.
- Nomor 7 memerlukan ekspor layar Stitch dan akses tautan yang terverifikasi.
