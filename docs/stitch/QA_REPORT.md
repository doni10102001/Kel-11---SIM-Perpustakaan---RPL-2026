# Laporan QA Final — Paket Screenshot Google Stitch

Tanggal QA: 7 November 2026 · Aplikasi: SIM Perpustakaan (Laravel 13 + Inertia v3 + Vue 3 + Bootstrap 5.3) · Lingkungan: lokal, tanpa database/API, tanpa URL publik.

## Ringkasan

| Item | Hasil |
| --- | --- |
| Screenshot utama | **18/18** lolos (1 Masuk, 12 Admin, 5 Anggota) |
| Screenshot state tambahan | **8/8** lolos (panel detail, dialog Tolak, laporan sebelum ditampilkan) |
| Dimensi | Semua lebar **1440 px**; tinggi 900–1672 px (full-page) |
| Lokasi | `docs/stitch/screenshots/` |
| Paket | `docs/stitch/SIM_PERPUSTAKAAN_SCREENSHOTS.zip` — 32 entri (26 PNG + folder + manifest), `unzip -t` tanpa galat |
| Manifest | `docs/stitch/SCREENSHOT_MANIFEST.md` (halaman, URL lokal, file, dimensi, ukuran, state, validasi) |
| Status | **Siap diunggah ke Google Stitch** |

## Cakupan halaman (hasil audit `php artisan route:list`)

18 rute halaman terdaftar dan semuanya memiliki screenshot utama:

- **Masuk:** `/login`
- **Admin (12):** Dashboard, Data Anggota, Tambah Anggota, Ubah Anggota, Data Buku & Eksemplar, Tambah Buku, Ubah Buku, Peminjaman Buku, Pengembalian Buku, Verifikasi Booking, Realisasi Booking, Laporan
- **Anggota (5):** Dashboard, Cari Buku, Ajukan Booking, Status Booking, History Peminjaman

Tidak dimasukkan (bukan halaman final): `/` (redirect ke `/login`), `/hello/echo` (smoke test teknis), `/demo/komponen` (katalog komponen pengembang), `/up` dan `storage/{path}` (rute bawaan framework).

## State deterministik yang dipakai

Setiap halaman dimuat ulang penuh sehingga memakai dataset awal (tanggal demo tetap 7 November 2026). State khusus memakai parameter yang sudah didukung aplikasi — tidak ada aturan bisnis yang diubah:

| Screenshot | State |
| --- | --- |
| `01-login.png` | Mode presentasi: kotak "Mode pratinjau" disembunyikan **hanya saat capture** lewat CSS yang disuntikkan skrip; mekanisme demo login tetap ada di kode. |
| `admin/04-ubah-anggota.png` | Anggota AG-001 |
| `admin/07-ubah-buku.png` | Buku BK-001 (eksemplar Tersedia & Dipinjam) |
| `admin/11-realisasi-booking.png` | `?booking=BP-001` (booking terpilih) |
| `admin/12-laporan.png` | `?jenis=peminjaman` (laporan tampil, seluruh periode) |
| `anggota/03-ajukan-booking.png` | `?eksemplar=EK-004` (buku & eksemplar terpilih) |

## QA visual desktop (1440 × 900)

Seluruh 26 screenshot diperiksa langsung secara visual.

| Kriteria | Hasil |
| --- | --- |
| Konten final, tanpa placeholder halaman kosong | ✓ Semua halaman |
| Form, tabel, tombol, menu sesuai SRS & keputusan desain | ✓ (lihat `docs/KEPUTUSAN_DESAIN.md`) |
| Data demo konsisten antarhalaman | ✓ mis. Dashboard 5 peminjaman Aktif = 5 baris di Pengembalian = 5 "Aktif" di Laporan; 18 eksemplar = jumlah di Data Buku |
| Error JavaScript / console / request gagal | ✓ Tidak ada (dicek otomatis per halaman) |
| Gambar rusak / font hilang | ✓ Tidak ada; Inter Variable & Bootstrap Icons terverifikasi termuat |
| Komponen terpotong | ✓ Setelah perbaikan (lihat di bawah) |
| Sidebar & header konsisten, tidak terduplikasi pada full-page | ✓ |
| Catatan internal (SRS/TBD/UC/FR/BR/prototipe) di tampilan | ✓ Tidak ada (dicek otomatis) |
| Modal/tooltip terbuka tanpa sengaja di screenshot utama | ✓ Tidak ada |

## Validasi file otomatis (`scripts/stitch-screenshots.mjs`)

Per file: signature PNG valid · lebar 1440 px · tinggi ≥ 900 px · piksel didekode untuk memastikan tidak kosong · sidik SHA-256 unik (tidak ada duplikat). Hasil: **26/26 lolos**.

## Pengujian otomatis

| Pengujian | Hasil |
| --- | --- |
| `npm run test:data` | 142/142 lulus |
| `php artisan test` | 19/19 lulus (655 assertion, tanpa query database) |
| `npm run build` | Berhasil |
| Rute & interaksi browser (Phase 1, 3, 4, 5) | 35/35 halaman, 231/231 cek interaksi, tanpa error console |
| Regresi mobile (390 × 844 dan 360 × 740) | 70/70 tanpa overflow horizontal |
| Request ke luar localhost | 0 |

## Masalah yang ditemukan & diperbaiki di Phase 6

| # | Masalah | Perbaikan |
| --- | --- | --- |
| 1 | Placeholder pencarian Verifikasi Booking terpotong pada 1440 px ("…atau eksempl") karena enam tab status memakan lebar toolbar. | Placeholder dipersingkat menjadi "Cari ID, anggota, atau judul" (pencarian tetap mencakup kode eksemplar). |
| 2 | Kode di pesan dialog terpotong di tanda hubung saat teks dibungkus ("EK-" / "018"). | `ConfirmationModal` menampilkan tanda hubung di dalam kode (mis. EK-018, BP-008) sebagai tanda hubung tak-terputus; teks tidak berubah. |
| 3 | Skrip screenshot gagal menghapus profil Chrome sementara (`ENOTEMPTY`) sehingga manifest/ZIP tidak terbentuk. | Pembersihan profil menunggu Chrome keluar dan bersifat best-effort; ekspor tidak lagi terhenti. |

Tidak ada perubahan desain atau aturan bisnis lain.

## Catatan untuk replikasi di Google Stitch

- **Sidebar fixed setinggi layar.** Pada screenshot full-page, sidebar navy membentang setinggi halaman dan menu "Keluar" berada di dasar halaman. Ini perilaku asli layout (di browser, "Keluar" selalu di dasar layar).
- **Field tanggal** (`dd/mm/yyyy`) adalah tampilan bawaan Chrome yang mengikuti bahasa browser, bukan teks aplikasi.
- **Panel detail & dialog** pada screenshot state memperlihatkan latar halaman yang digelapkan; tombol tutup panel mendapat fokus awal (cincin fokus biru) sesuai perilaku aksesibilitas.
- Hanya tampilan desktop 1440 px yang dimasukkan ke paket; tampilan mobile diuji secara otomatis tetapi tidak diekspor.

## Lingkungan capture

- Google Chrome 157.0.8103.39 headless, DevTools Protocol, device scale factor 1, `--hide-scrollbars`
- Aset hasil `npm run build` (Vite) yang disajikan `php artisan serve` (PHP 8.4.27); tidak memakai Vite dev server agar tidak ada klien HMR
- Basis URL lokal `http://127.0.0.1:8013` (port bebas; URL publik tidak dibuat)

## Membuat ulang paket

```bash
export PATH="/opt/homebrew/opt/php@8.4/bin:$PATH"
npm run build
php artisan serve                     # terminal 1
npm run screenshots:stitch            # terminal 2 (basis http://127.0.0.1:8000)
# atau: npm run screenshots:stitch -- --base=http://127.0.0.1:8013
```

Skrip menulis ulang `docs/stitch/screenshots/`, `SCREENSHOT_MANIFEST.md`, dan `SIM_PERPUSTAKAAN_SCREENSHOTS.zip`, lalu keluar dengan kode 1 bila ada screenshot yang gagal validasi.
