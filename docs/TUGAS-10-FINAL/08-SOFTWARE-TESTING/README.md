# 8. File Pengujian Software

Status: **COMPLETE**. Tersedia laporan, matriks uji, kode tes, dan log eksekusi ulang 21 Nov 2026 serta 24 Nov 2026 (setelah remediasi kredensial skrip QA).

> Log bukti disimpan berekstensi `.txt`, bukan `.log`, karena `.gitignore` proyek mengabaikan `*.log`. Dengan begitu berkas bukti ikut ter-commit tanpa perlu `git add -f`. Isinya adalah keluaran asli tanpa perubahan.

| Berkas | Isi |
| --- | --- |
| [SOFTWARE_TESTING_REPORT.md](SOFTWARE_TESTING_REPORT.md) | Strategi, ruang lingkup, lingkungan, hasil (baseline, eksekusi ulang 21 & 24 Nov), keterbatasan cakupan Browser QA lama, keterbatasan umum |
| [TEST_CASE_MATRIX.md](TEST_CASE_MATRIX.md) | 35 test case (AC-01..AC-17 SRS + area implementasi), masing-masing dengan rujukan tes nyata |
| [`bukti/phpunit-2026-11-21.txt`](bukti/phpunit-2026-11-21.txt) · [`bukti/phpunit-2026-11-24.txt`](bukti/phpunit-2026-11-24.txt) | PHPUnit 270/270, 3.466 assertions |
| [`bukti/phpunit-daftar-test-2026-11-21.tsv`](bukti/phpunit-daftar-test-2026-11-21.tsv) | Daftar 270 nama tes beserta status dan jumlah assertion (dari laporan JUnit) |
| [`bukti/node-test-2026-11-21.txt`](bukti/node-test-2026-11-21.txt) · [`bukti/node-test-2026-11-24.txt`](bukti/node-test-2026-11-24.txt) | Output TAP Node.js 245/245 |
| [`bukti/build-2026-11-24.txt`](bukti/build-2026-11-24.txt) | `npm run build` sukses |
| [`bukti/browser-qa-journey-2026-11-21.txt`](bukti/browser-qa-journey-2026-11-21.txt) · [`bukti/browser-qa-journey-2026-11-24.txt`](bukti/browser-qa-journey-2026-11-24.txt) | Browser QA lama 23/23 (sebelum dan sesudah remediasi kredensial); cakupan assertion terbatas |
| [`../09-HASIL-SISTEM/bukti-skenario-demo-2026-11-21.json`](../09-HASIL-SISTEM/bukti-skenario-demo-2026-11-21.json) · [`bukti/e2e-skenario-demo-2026-11-24.json`](bukti/e2e-skenario-demo-2026-11-24.json) | E2E demo terverifikasi 22/22 + verifikasi DB |
| [`bukti/integritas-data-2026-11-21.txt`](bukti/integritas-data-2026-11-21.txt) · [`bukti/isolasi-db-2026-11-24.txt`](bukti/isolasi-db-2026-11-24.txt) | Invarian read-only (0 pelanggaran); jumlah baris DB dev identik sebelum/sesudah |

## Ringkasan hasil

| PHPUnit | Node.js | Browser QA lama | E2E demo terverifikasi |
| --- | --- | --- | --- |
| **270/270** · 3.466 assertions | **245/245** · 46 suite | **23/23** cek tercatat, tetapi hanya 7 ber-assertion eksplisit, 8 tanpa assertion, dan 2 langkah kondisional tidak tereksekusi (laporan §D.4) | **22/22** langkah, setiap mutasi diverifikasi kueri MySQL |

Angka di atas sama pada eksekusi 21 Nov dan 24 Nov.

## Cara menjalankan ulang

Prasyarat: `.env` lokal dengan `DB_TEST_*` menunjuk ke database uji yang **kosong** (`sim_perpustakaan_test`), PHP 8.4, Node 20. Lihat [panduan instalasi](../09-HASIL-SISTEM/PANDUAN_INSTALASI.md).

```zsh
php artisan test                    # PHPUnit (unit + feature + MySQL)
npm run test:data                   # Node.js test runner
npm run build                       # aset frontend diperlukan untuk Browser QA

# Browser QA: kredensial MySQL uji WAJIB lewat environment (tidak ada nilai bawaan di skrip)
read -rs "DB_TEST_PASSWORD?Kata sandi MySQL uji: " && export DB_TEST_PASSWORD
DB_TEST_USERNAME=sim_perpus_test node --experimental-websocket scripts/qa-browser-journey.mjs
unset DB_TEST_PASSWORD
```

Tanpa `DB_TEST_USERNAME`/`DB_TEST_PASSWORD`, skrip berhenti dengan exit code 2 sebelum menyentuh database. Akun Admin dan Anggota QA dibuat di DB uji dengan kata sandi acak sekali pakai.

> Perhatian: `scripts/qa-browser-journey.mjs` menghapus file `public/hot`. Jangan jalankan saat Vite dev server (`npm run dev`) sedang aktif.
