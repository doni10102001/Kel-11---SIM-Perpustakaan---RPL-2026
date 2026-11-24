# Laporan Pengujian Perangkat Lunak — SIM Perpustakaan

| Atribut | Nilai |
| --- | --- |
| Kode yang diuji | Commit `80e2f5f` (pohon identik dengan `origin/main` @ `66d3fb7`). Eksekusi 24 Nov ditambah remediasi kredensial yang kemudian di-commit sebagai `b06049b`, yaitu perubahan pada `scripts/qa-browser-journey.mjs` (bukan logika aplikasi) |
| Eksekusi ulang ke-1 | **21 November 2026**, 20:04–20:21 WIB |
| Eksekusi ulang ke-2 | **24 November 2026**, 11:56–12:00 WIB, setelah remediasi kredensial skrip QA |
| Lingkungan | macOS arm64 · PHP 8.4.27 CLI · Node.js 20.19.2 · MySQL 8.4.4 lokal · Google Chrome headless (CDP) |
| Database uji | `sim_perpustakaan_test` (terpisah dari `sim_perpustakaan_db`, akun MySQL terpisah); kosong sebelum dan sesudah pengujian |
| Matriks uji | [TEST_CASE_MATRIX.md](TEST_CASE_MATRIX.md) (35 test case, dipetakan ke AC-01..AC-17 SRS) |

## Ringkasan: empat angka yang berbeda

| Suite | Hasil | Arti angka | Kekuatan bukti |
| --- | --- | --- | --- |
| **PHPUnit** | **270/270** · 3.466 assertions | Unit, feature, integrasi MySQL nyata, konkurensi dua koneksi | Kuat: setiap tes memiliki assertion |
| **Node.js** | **245/245** · 46 suite | Logika frontend (validasi, status alur, privasi) | Kuat: setiap tes memiliki assertion |
| **Browser QA lama** (`scripts/qa-browser-journey.mjs`) | **23/23 PASS** | 23 cek yang tercatat, **bukan** 23 cek yang semuanya ber-assertion | **Terbatas**: hanya 7 cek dengan assertion eksplisit, 8 cek tanpa assertion (lihat §D.4) |
| **E2E demo terverifikasi** (Tugas 10) | **22/22** langkah | Ke-7 langkah mutasi (seed, anggota, buku+eksemplar, booking diajukan, disetujui 48 jam, realisasi, pengembalian) diverifikasi dengan kueri MySQL ke DB uji; 15 langkah lain ber-assertion rute/teks halaman | Kuat: setiap langkah PASS/FAIL ditentukan oleh hasil nyata, dan langkah gagal menghentikan skrip |

## A. Strategi pengujian

| Level | Alat | Yang diuji | Lokasi |
| --- | --- | --- | --- |
| Unit | PHPUnit 12 | Konversi waktu WIB↔UTC, cast tanggal, model, aturan seeder | `tests/Unit/` (24 tes) |
| Feature | PHPUnit + Laravel HTTP test | Rute, Form Request, Gate, controller, respons Inertia, CSRF, rate limit | `tests/Feature/{Auth,Anggota,Buku,Kredensial,Data,Dashboard}` |
| Integrasi / Database | PHPUnit pada MySQL 8.4 nyata (koneksi `mysql_testing`) | Skema, FK, CHECK, UNIQUE, transaksi, rollback, seeder | `tests/Feature/Database/`, `tests/Feature/Transaksi/` |
| Konkurensi | PHPUnit dengan **dua koneksi MySQL independen** (`mysql_uji_kedua`) | Balapan pinjam/setujui/kembali/kedaluwarsa, lock wait | `tests/Feature/Transaksi/KonkurensiTransaksiTest.php` |
| Keamanan | PHPUnit | Otorisasi per peran, CSRF, hash kata sandi, tanpa kebocoran props/log, isolasi data anggota | tersebar, lihat TC-03..06, 25, 27, 31 |
| Logika frontend | Node.js test runner (`node --test`) | Validasi input, status alur, privasi data di klien | `tests/js/` (12 berkas, 46 suite) |
| Browser QA lama | Chrome headless via CDP | Perjalanan Admin dan Anggota; cakupan assertion terbatas (§D.4) | `scripts/qa-browser-journey.mjs` |
| E2E demo terverifikasi | Chrome headless via CDP + kueri DB uji | Skenario demo lengkap: booking → setujui 48 jam → realisasi → pengembalian → laporan → histori | [`_alat/capture-hasil-sistem.mjs`](../_alat/capture-hasil-sistem.mjs) |

## B. Ruang lingkup

Autentikasi · otorisasi peran · CRUD anggota · CRUD buku & eksemplar · peminjaman · pengembalian · booking (ajukan, setujui, tolak, batalkan, realisasi) · kedaluwarsa otomatis 48 jam · dashboard SQL · laporan · zona waktu · integritas data · konkurensi.

## C. Matriks test case

Lihat [TEST_CASE_MATRIX.md](TEST_CASE_MATRIX.md). Kolom: Test Case ID, Module, Scenario, Expected Result, Actual Result, Status, Evidence / Test Reference.

## D. Hasil terverifikasi

### D.1 Baseline yang tercatat sebelumnya (dokumentasi proyek, 16 Nov 2026)

PHPUnit 270 lulus (3.466 assertions) · Node 245 lulus · Browser QA 23/23.

### D.2 Eksekusi ulang ke-1 (21 Nov 2026)

| Suite | Perintah | Hasil | Durasi | Bukti |
| --- | --- | --- | --- | --- |
| PHPUnit | `php artisan test` | **270/270 lulus, 3.466 assertions**, 0 gagal/error | 43,5 dtk | [`bukti/phpunit-2026-11-21.txt`](bukti/phpunit-2026-11-21.txt), [daftar 270 tes](bukti/phpunit-daftar-test-2026-11-21.tsv) |
| PHPUnit (dengan JUnit) | `php artisan test --log-junit …` | 270/270 lulus (eksekusi kedua, untuk daftar nama tes) | 42,7 dtk | idem |
| Node.js | `npm run test:data` | **245/245 lulus**, 46 suite, 0 gagal/skip | 0,5 dtk | [`bukti/node-test-2026-11-21.txt`](bukti/node-test-2026-11-21.txt) |
| Browser QA lama | `node --experimental-websocket scripts/qa-browser-journey.mjs` (versi sebelum remediasi) | **23/23 PASS**, dengan keterbatasan §D.4 | ±1 mnt | [`bukti/browser-qa-journey-2026-11-21.txt`](bukti/browser-qa-journey-2026-11-21.txt) |
| E2E demo terverifikasi | `_alat/capture-hasil-sistem.mjs` | **22/22 langkah PASS**, 0 error JS, 0 respons 5xx, screenshot Hasil Sistem | ±1 mnt | [`../09-HASIL-SISTEM/bukti-skenario-demo-2026-11-21.json`](../09-HASIL-SISTEM/bukti-skenario-demo-2026-11-21.json) |
| Integritas data (read-only) | `PemeriksaInvarianData::periksa()` pada DB dev | **0 pelanggaran invarian** | — | [`bukti/integritas-data-2026-11-21.txt`](bukti/integritas-data-2026-11-21.txt) |

### D.3 Eksekusi ulang ke-2 setelah remediasi kredensial (24 Nov 2026)

Tujuannya membuktikan bahwa penghapusan kredensial hardcoded dari skrip QA tidak merusak pengujian dan tidak membocorkan kredensial. Kredensial MySQL uji diberikan lewat environment saat runtime. Admin dan anggota QA memakai kata sandi acak sekali pakai.

| Suite | Hasil | Bukti |
| --- | --- | --- |
| PHPUnit | **270/270 lulus, 3.466 assertions** (43,2 dtk) | [`bukti/phpunit-2026-11-24.txt`](bukti/phpunit-2026-11-24.txt) |
| Node.js | **245/245 lulus**, 46 suite, 0 gagal/skip | [`bukti/node-test-2026-11-24.txt`](bukti/node-test-2026-11-24.txt) |
| Build produksi | `npm run build` sukses (Vite 8) | [`bukti/build-2026-11-24.txt`](bukti/build-2026-11-24.txt) |
| Browser QA lama (setelah remediasi) | **23/23 PASS**, dengan keterbatasan §D.4 yang sama. Login Admin berhasil dengan kata sandi acak sekali pakai, sehingga kata sandi Admin development tidak dipakai | [`bukti/browser-qa-journey-2026-11-24.txt`](bukti/browser-qa-journey-2026-11-24.txt) |
| E2E demo terverifikasi (setelah remediasi) | **22/22 langkah PASS**, `devIdentik: true`, DB uji akhir 0 baris | [`bukti/e2e-skenario-demo-2026-11-24.txt`](bukti/e2e-skenario-demo-2026-11-24.txt), [`.json`](bukti/e2e-skenario-demo-2026-11-24.json) |
| Gagal-cepat tanpa kredensial | Tanpa `DB_TEST_USERNAME`/`DB_TEST_PASSWORD`, skrip berhenti dengan exit code 2 dan pesan jelas sebelum menyentuh database | Diuji manual 24 Nov (perilaku di kode, lihat awal skrip) |
| Kebocoran kredensial | Nilai `.env` (DB, DB uji, seed Admin, APP_KEY) **tidak** ditemukan di log mana pun; 0 token mirip kata sandi acak di log QA | Pemindaian 24 Nov |
| Isolasi database | Jumlah baris DB development **identik** sebelum dan sesudah; DB uji kembali 0 baris | [`bukti/isolasi-db-2026-11-24.txt`](bukti/isolasi-db-2026-11-24.txt) |

Screenshot eksekusi 24 Nov tidak dimasukkan ke paket. Screenshot resmi Hasil Sistem tetap hasil 21 Nov.

### D.4 Keterbatasan cakupan "Browser QA lama 23/23"

Angka 23/23 benar sebagai hasil eksekusi, tetapi **tidak berarti 23 langkah browser diverifikasi penuh**. Klasifikasi setiap cek berdasarkan kode skrip:

| Kategori | Jumlah | Cek |
| --- | --- | --- |
| Assertion eksplisit (status PASS/FAIL bergantung hasil) | **7** | Validasi rentang laporan · Laporan tampil & `window.print()` · Isolasi dashboard Anggota · Histori tidak bocor · Blokir rute Admin bagi Anggota · Suite kedaluwarsa (PHPUnit) · Integritas DB development |
| Terverifikasi tidak langsung (skrip berhenti dengan galat bila langkah gagal) | **8** | Seed DB uji · Server uji aktif · Chrome aktif · Login Admin · Logout Admin · Login Anggota · Logout Anggota · Pembersihan DB uji |
| **Tanpa assertion** (selalu PASS setelah klik) | **8** | Dashboard Admin (log: "Kartu terdeteksi: 0", selector tidak cocok) · Tambah Anggota · Tambah Buku · Peminjaman Langsung · Verifikasi Booking · Ganti Kata Sandi Wajib · Pencarian Katalog · Pengajuan Booking |
| Tidak tereksekusi (kondisional) | 2 | Pengembalian Buku · Pembatalan Booking (total cek potensial 25) |

Fungsi pada 8 cek tanpa assertion **dibuktikan di tempat lain**, yaitu oleh PHPUnit (lihat matriks) dan oleh E2E demo terverifikasi yang memeriksa status MySQL di setiap mutasi. Remediasi 24 Nov hanya menyentuh penanganan kredensial. Logika assertion skrip lama sengaja tidak diubah agar hasilnya tetap sebanding dengan baseline.

## E. Keterbatasan yang diketahui

- **Deadlock MySQL nyata tidak direproduksi.** Yang diuji adalah *lock wait* dan balapan dua koneksi; ulang-coba deadlock bergantung pada `DB::transaction($kerja, 3)`.
- **Penjadwal produksi belum dipasang.** Secara lokal `php artisan schedule:work` harus dijalankan manual. DB development memiliki 2 booking Disetujui yang sudah lewat 48 jam dan belum diproses karena penjadwal tidak berjalan. `bookings:expire` sengaja tidak dijalankan terhadap DB development.
- **Aturan bisnis terbuka:** TBD-05 (durasi pinjam, kuota, denda, perpanjangan) dan TBD-06 (kuota/antrean/masa berlaku booking Diajukan).
- **Format ekspor** PDF/Excel/CSV ditunda (TBD-07); hanya cetak lewat browser.
- **Nonfungsional belum diuji:** beban 25 sesi (NFR-018), tampilan 360 px lintas browser Firefox/Edge (NFR-017/021), HTTPS (NFR-011), backup-restore (NFR-016). Tidak ada lingkungan produksi.
- **UAT** dengan perwakilan pengguna (SRS §6.5) belum dilakukan.
- **Keamanan repositori:** kredensial hardcoded di `scripts/qa-browser-journey.mjs` sudah dihapus (commit lokal `b06049b`, 24 Nov, belum di-push). Nilai lama **masih ada di riwayat Git** (commit `5f7a030`, sudah di GitHub) dan **masih aktif**. Kedua kredensial wajib dirotasi oleh pemilik; lihat [`docs/keamanan/ROTASI_KREDENSIAL_QA.md`](../../keamanan/ROTASI_KREDENSIAL_QA.md) dan [FINAL_SUBMISSION_CHECKLIST](../FINAL_SUBMISSION_CHECKLIST.md#d-keamanan).

## F. Bukti

| Bukti | Lokasi |
| --- | --- |
| Log PHPUnit (21 & 24 Nov), daftar 270 tes | [`bukti/`](bukti/) |
| Log Node test, TAP lengkap (21 & 24 Nov) | [`bukti/node-test-2026-11-21.txt`](bukti/node-test-2026-11-21.txt), [`bukti/node-test-2026-11-24.txt`](bukti/node-test-2026-11-24.txt) |
| Log build (24 Nov) | [`bukti/build-2026-11-24.txt`](bukti/build-2026-11-24.txt) |
| Log Browser QA lama (21 Nov sebelum remediasi, 24 Nov sesudah) | [`bukti/browser-qa-journey-2026-11-21.txt`](bukti/browser-qa-journey-2026-11-21.txt), [`bukti/browser-qa-journey-2026-11-24.txt`](bukti/browser-qa-journey-2026-11-24.txt) |
| Laporan langkah E2E demo (21 Nov, dengan screenshot) | [`../09-HASIL-SISTEM/bukti-skenario-demo-2026-11-21.json`](../09-HASIL-SISTEM/bukti-skenario-demo-2026-11-21.json) |
| Laporan langkah E2E demo (24 Nov, verifikasi ulang) | [`bukti/e2e-skenario-demo-2026-11-24.json`](bukti/e2e-skenario-demo-2026-11-24.json) |
| Screenshot E2E (31 PNG), termasuk penolakan akses 403 | [`../09-HASIL-SISTEM/screenshots/`](../09-HASIL-SISTEM/screenshots/) |
| Integritas data (21 Nov) dan isolasi DB (24 Nov) | [`bukti/integritas-data-2026-11-21.txt`](bukti/integritas-data-2026-11-21.txt), [`bukti/isolasi-db-2026-11-24.txt`](bukti/isolasi-db-2026-11-24.txt) |
| Kode sumber tes | [`tests/`](../../../tests/) |
| Laporan QA & transaksi sebelumnya | [`docs/pengujian/LAPORAN_QA_SISTEM.md`](../../pengujian/LAPORAN_QA_SISTEM.md), [`LAPORAN_UJI_TRANSAKSI.md`](../../pengujian/LAPORAN_UJI_TRANSAKSI.md) |
