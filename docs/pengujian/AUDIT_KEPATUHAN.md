# Final Compliance Audit — SIM Perpustakaan

| Item | Nilai |
| --- | --- |
| Tanggal audit | 7 November 2026 |
| Objek | Prototipe UI statis Laravel 13.40 + Inertia v3 + Vue 3.5 + Bootstrap 5.3 |
| Basis kode | Commit `86552a1`, ditambah 2 penghapusan file instruksi yang sudah di-*stage* tetapi **belum di-commit** |
| Lingkungan | macOS · PHP 8.4.27 · Node 20.19.2 · Google Chrome headless (CDP) |
| Sifat audit | Hanya audit. Tidak ada kode sumber, desain, aturan bisnis, SRS, atau UML yang diubah. |
| Laporan terkait | [SRS_TRACEABILITY_MATRIX.md](SRS_TRACEABILITY_MATRIX.md) · [UML_COMPLIANCE_MATRIX.md](UML_COMPLIANCE_MATRIX.md) · [FINAL_SUBMISSION_CHECKLIST.md](FINAL_SUBMISSION_CHECKLIST.md) |

## 1. Ringkasan eksekutif

**Penilaian akhir: READY WITH DOCUMENTED LIMITATIONS**, dengan syarat yang dijelaskan di §12.

- Sebagai **prototipe UI statis**, aplikasi lengkap dan konsisten. Ke-18 halaman final berjalan tanpa database dan tanpa request eksternal. Seluruh uji yang dijalankan ulang pada audit ini lulus. Alur booking → peminjaman → pengembalian terbukti atomik dan konsisten.
- Sebagai **implementasi SRS v1.1 secara penuh**, aplikasi **belum** memenuhi persyaratan server-side, yaitu autentikasi, sesi, otorisasi, persistensi, transaksi ACID, dan audit log. Kekurangan ini adalah batas lingkup yang disetujui, bukan cacat tersembunyi. Statusnya tetap dicatat apa adanya (2 FR NOT COMPLIANT, 7 PARTIALLY COMPLIANT).
- Pembagian peran SRS (Admin + Petugas) **tidak diikuti**: prototipe hanya memakai Admin dan Anggota. Ini adalah keputusan proyek dan deviasi dokumentasi, **bukan** bukti kepatuhan.
- **Audit Figma: NOT VERIFIED.** Tidak ada berkas Figma di repositori, dan konektor Figma belum diotorisasi di sesi ini.

| Ukuran | Hasil |
| --- | --- |
| FR SRS diaudit | 37 |
| COMPLIANT | 28 |
| PARTIALLY COMPLIANT | 7 |
| NOT COMPLIANT | 2 |
| NOT APPLICABLE | 0 |
| NOT VERIFIED | 0 |
| Temuan | CRITICAL 0 · HIGH 0 · MEDIUM 1 · LOW 5 · INFORMATIONAL 10 |

## 2. Referensi yang diperiksa

| Referensi | Cara pemeriksaan |
| --- | --- |
| `docs/requirements/Tugas_2_SRS_FINAL.pdf` | Teks lengkap diekstrak (Ghostscript `txtwrite`, 812 baris) dan dibaca seluruhnya: FR-001–037, NFR, BR-01–09, AC-01–17, TBD-01–12 |
| `docs/uml/` | Use Case, Class, 4 State Machine, 3 Object Diagram dibuka langsung dari PNG. Activity/Sequence melalui transkripsi. |
| `docs/analysis/uml-activity-sequence.md`, `docs/analysis/mock-data.md`, `docs/KEPUTUSAN_DESAIN.md` | Dibaca seluruhnya |
| Dokumentasi tangkapan layar antarmuka sistem | Dibaca; klaimnya diverifikasi ulang, tidak diterima begitu saja |
| Rute Laravel | `php artisan route:list` (24 rute) dan probe HTTP |
| Halaman & komponen Vue | 17 halaman final + komponen dan utilitas validasi. Inventaris dibuat dengan bantuan subagen read-only lalu diperiksa silang secara acak. |
| Data mock & validator | `resources/js/data/mock/*`, `utils/validasi*.js`, `composables/useDataPerpustakaan.js` |
| Uji otomatis | Dijalankan ulang (§8) |

## 3. Audit kebutuhan SRS

Rincian per FR ada di [SRS_TRACEABILITY_MATRIX.md](SRS_TRACEABILITY_MATRIX.md).

| Status | FR |
| --- | --- |
| NOT COMPLIANT (2) | FR-002 (validasi kredensial ke basis data), FR-019 (blokir sesi tidak valid) |
| PARTIALLY COMPLIANT (7) | FR-003 (kata sandi salah tidak ditolak), FR-013 (riwayat tidak disimpan permanen), FR-016 (logout tanpa sesi), FR-017 (login Anggota simulasi), FR-018 (data Anggota terbatas, tetapi URL tidak dilindungi), FR-024 (jatuh tempo manual, TBD-05), FR-025 (konkurensi tidak dapat diuji) |
| COMPLIANT (28) | Seluruh FR buku/eksemplar, anggota, peminjaman, pengembalian, laporan, cari buku, booking (FR-031–035), dan history (FR-036/037) |

Butir berstatus usulan (`*`) tetap usulan menurut SRS. Implementasi tidak mengubah status tersebut, dan kebijakan TBD (TBD-05 durasi, TBD-06 aturan booking, TBD-07 format ekspor) tidak diberi nilai karangan.

## 4. Audit UML

Rincian ada di [UML_COMPLIANCE_MATRIX.md](UML_COMPLIANCE_MATRIX.md).

- **Tidak ada pelanggaran kebutuhan yang telah disetujui.**
- **Perbedaan yang diizinkan KD:**
  - login simulasi
  - dua peran
  - nama placeholder Object Diagram diganti
  - status per eksemplar (bukan "stok")
  - Admin tidak dapat membatalkan booking (KD 4.8)
  - penolakan dengan alasan apa pun (KD 4.6)
- **Inkonsistensi SRS ↔ UML yang belum diselesaikan di dokumen sumber (9 butir):**
  - Use Case dan Class tidak memuat verifikasi booking
  - Class tidak memuat atribut keputusan booking, jatuh tempo, maupun petugas
  - Anggota ⊂ Pengguna vs `user_id?`
  - guard tolak pada State Machine 04
  - aktor pembatalan Disetujui
  - eksemplar tidak dimodelkan di A03
  - model "stok" di A04/S04
  - A08/S08 berhenti pada pengajuan
  - pembagian peran SRS vs UML

## 5. Audit peran & navigasi

| Pemeriksaan | Hasil | Bukti |
| --- | --- | --- |
| Hanya dua peran | ✓ | `NavigasiDemo::PERAN = ['admin','anggota']` |
| Tidak ada akun/rute Petugas | ✓ | `/demo/petugas/dashboard` → 404 (probe HTTP + `test_petugas_role_routes_do_not_exist`). `cariAkunDemo('petugas') === null`. Kata "Petugas" tidak muncul di teks UI; hanya ada di komentar kode dan tes. |
| Fungsi operasional Admin | ✓ | Dashboard, Data Anggota (+tambah/ubah), Data Buku & Eksemplar (+tambah/ubah), Peminjaman, Pengembalian, Verifikasi Booking, Realisasi Booking, Laporan |
| Fungsi Anggota | ✓ | Dashboard, Cari Buku, Ajukan Booking, Status Booking (+batal), History Peminjaman |
| Label navigasi konsisten | ✓ | Menu dari satu sumber (`NavigasiDemo.php`). Judul halaman = label menu. Breadcrumb dibuat terpusat. |
| Rute rusak / halaman hilang | ✓ Tidak ada | 18/18 halaman final lulus uji browser fase 1. Rute berparameter `AG-001`/`BK-001` → 200. `/` → 302 ke `/login`. |
| Kelola Pengguna / Role | Tidak dibuat | Deviasi dari SRS ACM ("Kelola pengguna dan role": Admin). Lihat F-07. |

**Keputusan lingkup yang dicatat eksplisit:** SRS §2.3 dan Access Control Matrix membedakan Admin dan Petugas. Prototipe menggabungkan seluruh hak Petugas ke peran Admin, dan hak khusus Admin ("Kelola pengguna dan role") tidak dibangun. Ini **deviasi dokumentasi** yang perlu dicantumkan pada SRS/UML final atau laporan tugas (F-07).

## 6. Audit form & tabel

Inventaris lengkap per layar (field, label, wajib, opsi dropdown, kolom tabel, tombol, pencarian/filter, status, pesan validasi) dirangkum di bawah ini. Rujukan berkas dan baris ada pada inventaris audit.

| Layar | URL | Field input (wajib = \*) | Kolom tabel utama | Pencarian / filter |
| --- | --- | --- | --- | --- |
| Masuk | `/login` | Identitas Pengguna\*, Kata Sandi\* | — | — |
| Dashboard Admin | `/demo/admin/dashboard` | — | Peminjaman Aktif: ID Peminjaman, Anggota, Buku, Tanggal Pinjam, Jatuh Tempo · Booking Perlu Tindak Lanjut: ID Booking, Anggota, Buku, Status | — |
| Data Anggota | `/demo/admin/anggota` | — | Kode Anggota, Nama, Kontak, Status, Aksi | Kode/nama/kontak · filter Aktif/Nonaktif |
| Tambah/Ubah Anggota | `…/anggota/tambah`, `…/anggota/{kode}/ubah` | Kode Anggota\* (baca-saja saat ubah), Status Anggota\* (Aktif/Nonaktif), Nama\*, Kontak\* | — | — |
| Data Buku & Eksemplar | `/demo/admin/buku` | — | Kode Buku, Judul, Penulis, Penerbit, Tahun Terbit, Kategori, Jumlah Eksemplar, Aksi · panel: Kode Eksemplar, Status | Kode buku/judul/kategori |
| Tambah/Ubah Buku | `…/buku/tambah`, `…/buku/{kode}/ubah` | Kode Buku\*, Kategori\*, Judul\*, Penulis\*, Penerbit\*, Tahun Terbit\* + baris Kode Eksemplar/Status (Tersedia; Dipesan/Dipinjam nonaktif) | Kode Eksemplar, Status, Aksi | — |
| Peminjaman Buku | `/demo/admin/peminjaman` | Anggota\*, Buku\*, Kode Eksemplar\*, Tanggal Jatuh Tempo\* · baca-saja: Status Anggota, Tanggal Pinjam, Admin Pemroses | Bukti: ID Peminjaman … Status | — |
| Pengembalian Buku | `/demo/admin/pengembalian` | — | Aktif: ID Peminjaman, Anggota, Buku & Eksemplar, Tanggal Pinjam, Tanggal Jatuh Tempo, Status, Aksi · Riwayat: … Tanggal Kembali, Admin Pemroses, Status | Tab Aktif/Riwayat · ID/anggota/judul/eksemplar |
| Verifikasi Booking | `/demo/admin/booking/verifikasi` | Alasan Penolakan\* (dialog Tolak, maks. 200) | ID Booking, Anggota, Buku & Eksemplar, Tanggal Pengajuan, Status Booking, Aksi | Tab 5 status + Semua · ID/anggota/judul (+kode eksemplar) |
| Realisasi Booking | `/demo/admin/booking/realisasi` | Tanggal Jatuh Tempo\* · baca-saja: ID Booking, Status Booking, Anggota, Status Anggota, Buku, Kode Eksemplar, Tanggal Pinjam, Admin Pemroses | ID Booking, Anggota, Buku, Kode Eksemplar, Status Eksemplar, Tanggal Disetujui, Aksi | — |
| Laporan | `/demo/admin/laporan` | Jenis Laporan\* (Daftar Buku/Daftar Anggota/Peminjaman/Pengembalian), Periode Awal, Periode Akhir | Kolom sesuai jenis (No + atribut entitas) | — |
| Dashboard Anggota | `/demo/anggota/dashboard` | — | Peminjaman Aktif · Booking Berjalan | — |
| Cari Buku | `/demo/anggota/cari-buku` | — | Kode Buku, Judul, Penulis, Kategori, Ketersediaan, Aksi | Judul/kode/kategori · filter kategori |
| Ajukan Booking | `/demo/anggota/booking/ajukan` | Buku\*, Kode Eksemplar\* · baca-saja: Identitas Anggota, Kode Anggota, Tanggal Pengajuan | — | — |
| Status Booking | `/demo/anggota/booking/status` | — | ID Booking, Buku & Eksemplar, Tanggal Pengajuan, Status, Keterangan, Aksi | Tab Semua + 5 status |
| History Peminjaman | `/demo/anggota/history` | — | ID Peminjaman, Judul Buku, Kode Eksemplar, Tanggal Pinjam, Tanggal Kembali, Status, Aksi | Tab Semua/Aktif/Selesai |

**Hasil perbandingan dengan SRS/UML:**

- **Field wajib yang hilang:** tidak ada. Semua atribut SRS 6.1 dan Class Diagram yang relevan untuk UI tersedia.
- **Field ekstra tanpa dasar:** tidak ada field *tersimpan* yang tidak berdasar. Tampilan tambahan berupa nilai turunan dari data yang sama, yaitu: status anggota/eksemplar di formulir transaksi, jumlah per status, Penulis di panel detail, "Asal Transaksi" di History, kolom "No"/"Tersedia" di laporan, serta "Tanggal laporan" dan "Disusun oleh" di kepala laporan. Penanda bahwa nilai ini tidak disimpan di field baru: tidak ada email, program studi, ISBN, kode rak, denda, atau perpanjangan (`mock-data.md`).
- **Label:** konsisten dengan SRS/UML, dengan dua penyesuaian terdokumentasi: "Kode Anggota" untuk `idAnggota`/nomor anggota, dan "Admin Pemroses" untuk petugas pemroses (KD 4.3).
- **Nilai status:** persis sesuai SRS 6.3 (`utils/status.js:7-28`). Tidak ada "Menunggu" atau "Dikembalikan" sebagai nilai status. Kata "Menunggu verifikasi" hanya muncul sebagai teks keterangan, misalnya kapsi kartu Dashboard (F-15).
- **Aksi hilang:** tidak ada untuk FR yang disetujui. Aksi hapus tidak dibuat karena tidak diwajibkan SRS (KD 3.7). Pembatalan booking oleh Admin tidak dibuat (KD 4.8, F-01).
- **Navigasi salah:** tidak ditemukan.
- **Aturan bisnis tanpa dasar SRS:** ada tiga keputusan prototipe yang terdokumentasi dan berstatus *belum final*:
  - kode tidak dapat diubah (KD 3.3)
  - penolakan pengajuan booking ganda (KD 5.4)
  - batas teks: maks. 20/100/50/200 karakter, Tahun Terbit ≤ 2026 (KD 3.8)
- **Istilah internal di UI:** tidak ada SRS/UC/FR/BR/TBD/usulan/prototipe di teks yang tampil. Halaman Masuk menyatakan "Mode pratinjau — Autentikasi belum diaktifkan…" (`Login.vue:175-178`).

## 7. Integritas data mock

| Pemeriksaan | Hasil | Bukti |
| --- | --- | --- |
| ID unik & format | ✓ | `periksaKonsistensi` aturan 1. T-MOCK "1. Identitas unik". ID booking baru BP-009 unik. |
| Relasi valid | ✓ | Aturan 3. T-MOCK "2. Relasi antar data". |
| Status booking/peminjaman/eksemplar valid | ✓ | Aturan 2, 4–6. Dataset awal: eksemplar 11 Tersedia / 2 Dipesan / 5 Dipinjam; peminjaman 5 Aktif / 5 Selesai; booking 2/2/1/2/1. |
| Dashboard dihitung dari data | ✓ | `ringkasanAdmin` = anggota 10 (8 Aktif), buku 10, eksemplar 18, peminjaman Aktif 5, booking Diajukan 2 / Disetujui 2. AG-001: booking aktif 1, peminjaman Aktif 1, riwayat Selesai 2. Uji browser memeriksa angka yang sama di layar. |
| Data per anggota benar | ✓ | AG-001: 3 booking, 3 peminjaman, semuanya `kodeAnggota === 'AG-001'` (T-AUD E1) |
| Simulasi transaksi atomik | ✓ | Draf → `periksaKonsistensi` → terapkan. Bila gagal, data tidak berubah (T-TRX "Atomisitas & rollback", T-AUD D2/A3b). |
| Tidak ada paparan data antaranggota di UI Anggota | ✓ | Katalog tidak menampilkan peminjam/pemesan (KD 5.1). Halaman Anggota memfilter berdasarkan akun demo. URL Admin tetap terbuka (F-09). |
| Diajukan → Disetujui/Ditolak | ✓ | T-AUD A2, B2, B3 |
| Disetujui → Direalisasikan | ✓ | T-AUD A4 |
| Pembatalan sah | ✓ | T-AUD C1–C4 |
| Tersedia → Dipesan → Dipinjam | ✓ | T-AUD A2 → A4 |
| Aktif → Selesai & eksemplar kembali Tersedia | ✓ | T-AUD A5, A6 |
| Konsistensi setelah transaksi berturut-turut | ✓ | `periksaKonsistensi` = 0 galat setelah setiap skenario |
| Kasus tepi reservasi buntu | ✗ celah | F-01 |

## 8. Pengujian otomatis (dijalankan pada audit ini)

| Pengujian | Perintah | Hasil aktual |
| --- | --- | --- |
| Data & logika | `npm run test:data` | **142/142 lulus**, 26 suite, 0 gagal (≈229 ms) |
| PHP | `php artisan test` | **19/19 lulus**, 655 assertion (≈288 ms) |
| Build | `npm run build` | **Berhasil** (Vite, 449 ms) |
| Rute | `php artisan route:list` | 24 rute: 18 halaman final + `/` (redirect) + `/hello/echo` + `/demo/komponen` + `up` + `storage/{path}` GET & PUT (bawaan framework) |
| Probe tanpa DB | `curl` ke 8 URL saat `DB_CONNECTION=sqlite` tanpa file `database.sqlite` | 200 untuk semua halaman, 302 untuk `/`, 404 untuk `/demo/petugas/dashboard`. Halaman tidak menyentuh DB. |
| Browser fase 1 | Chrome headless | 18/18 halaman · 47/47 interaksi · 36/36 responsif · 0 request non-lokal · tanpa error console |
| Browser fase 3 | idem | 7/7 · 65/65 · 14/14 · 0 non-lokal |
| Browser fase 4 | idem | 5/5 · 56/56 · 10/10 · 0 non-lokal |
| Browser fase 5 | idem | 5/5 · 63/63 · 10/10 · 0 non-lokal |
| **Total browser** | | **35/35 halaman, 231/231 cek, 70/70 cek responsif (390×844 dan 360×740), 0 request eksternal** |
| Skenario audit independen | `node skenario*.mjs` | Alur A (10 langkah) dan alur B–G lulus. Kasus tepi F/G mengonfirmasi F-01. Run pertama alur A gagal karena kesalahan skrip audit, lalu diperbaiki (UML matrix §6). |

**Cakupan dan celah uji:**

- Tercakup:
  - invariant data
  - semua validator aksi
  - atomisitas
  - laporan
  - privasi data anggota
  - alur lintas peran
  - rute dan menu
  - tanpa query DB (PHPUnit)
  - render dan interaksi seluruh halaman (browser)
- Belum tercakup oleh tes di repositori:
  - kasus tepi F-01
  - uji browser, karena skripnya tidak berada di repositori (F-02)
  - Firefox/Edge (NFR-021)
  - target performa NFR-001/018
- Karena semua logika berjalan di browser, uji akses terproteksi, sesi, dan CSRF tidak dapat dibuat.

## 9. Keamanan & lingkup proyek

| Pemeriksaan | Hasil | Bukti |
| --- | --- | --- |
| Tidak ada database yang dipakai | ✓ | Tidak ada query (PHPUnit). Tidak ada file SQLite. Halaman tetap 200. Kerangka bawaan Laravel (`User.php`, 3 migrasi default, `DB_CONNECTION=sqlite` di `.env.example`) masih ada tetapi tidak dijalankan (F-05). |
| Tidak ada API eksternal | ✓ | 0 request non-lokal di 35 halaman. Tidak ada `fetch`/`axios`/`Http::` di kode aplikasi. |
| Autentikasi ditandai demo | ✓ | `Login.vue:175-178`; README baris 6-7, 85, 111; `KEPUTUSAN_DESAIN.md` §Akun pratinjau |
| Tidak ada klaim keamanan produksi | ✓ | README: "bukan sistem login yang aman"; "Semua halaman dapat dibuka langsung tanpa login" |
| Tidak ada kredensial/rahasia | ✓ | `git grep` pola APP_KEY/kata sandi/kunci privat: kosong. `.env.example` `APP_KEY=` kosong. |
| `.env` dikecualikan dari Git | ✓ | `.gitignore:3` |
| File sampah terlacak | ✓ Tidak ada | Tidak ada `.DS_Store`, log, profil Chrome, atau SQLite di `git ls-files` |
| Fungsi di luar proyek | Hanya yang disetujui | `/hello/echo` (smoke test Task 00) dan `/demo/komponen` (katalog komponen) dipertahankan atas instruksi pemilik dan dikecualikan dari paket tangkapan layar (F-16) |
| Rute bawaan framework | Info | `PUT storage/{path}` (unggah bertanda tangan Laravel 13, `filesystems.local.serve = true`) tidak dipakai aplikasi. README hanya menyebut `storage/{path}` (F-14). |

Uji penetrasi dan deployment tidak dilakukan (sesuai instruksi).

## 10. Temuan

| ID | Tingkat | Referensi | Berkas & baris | Perilaku aktual | Perilaku yang diharapkan | Rekomendasi |
| --- | --- | --- | --- | --- | --- | --- |
| **F-01** | MEDIUM | FR-034, FR-035, FR-026, BR-06, BR-07, TBD-06; KD 3.4, 4.5, 4.8 | `resources/js/utils/validasiTransaksi.js:98-110` (setujui tanpa cek status anggota); `:136-137` (realisasi wajib anggota Aktif); `resources/js/composables/useDataPerpustakaan.js:90` (`ubahAnggota` boleh menonaktifkan kapan pun) | (a) Admin menonaktifkan pemilik booking **Disetujui**, atau (b) Admin menyetujui booking milik anggota **Nonaktif**. Akibatnya eksemplar tetap **Dipesan**, realisasi ditolak, dan Admin tidak punya aksi pembatalan. Eksemplar hanya terlepas bila anggota itu sendiri membatalkan. Terbukti dengan T-AUD F1–F3 dan G1. | FR-034 menyiratkan reservasi berakhir dengan realisasi atau pembatalan. Harus selalu ada jalan sah untuk melepas eksemplar. | Putuskan dalam TBD-06/KD: (1) cek status anggota saat Setujui, dan/atau (2) beri Admin aksi batal untuk booking Disetujui (State Machine 04 tidak menetapkan aktornya), dan/atau (3) tampilkan peringatan saat menonaktifkan anggota yang punya booking Disetujui. Setelah diputuskan, dokumentasikan di KD dan tambahkan tes. **Tidak diperbaiki dalam audit ini.** |
| **F-02** | LOW | SRS 6.5 (UI/E2E), Quality Gate #1, #3 | Skrip pengujian antarmuka awal berada di lingkungan lokal pengembang di luar repositori | Bukti 231 cek interaksi dan 70 cek responsif awal diuji pada lingkungan lokal | Uji E2E dapat dijalankan ulang oleh penguji | Skrip pengujian E2E final telah disertakan pada repositori (`scripts/qa-browser-journey.mjs`) |
| **F-03** | LOW | Dokumentasi | `README.md:5` | Status tertulis "Phase 5", padahal Phase 6 sudah selesai | Status terkini | Perbarui baris status README |
| **F-04** | LOW | Dokumentasi | `docs/KEPUTUSAN_DESAIN.md:116` | Catatan "Label field final (mis. 'Petugas Proses' …) dikonfirmasi saat Phase 4" masih ada, padahal KD 4.3 sudah menetapkan "Admin Pemroses". Bagian Phase 5 juga berada sebelum Phase 4. | Catatan tidak kedaluwarsa dan berurutan | Tandai catatan sebagai selesai/rujuk KD 4.3; urutkan bagian |
| **F-05** | LOW | Klaim "tanpa database" | `app/Models/User.php`, `database/migrations/0001_01_01_00000{0,1,2}_*.php`, `.env.example:24` | Artefak DB bawaan Laravel masih ada (tidak dijalankan, tidak dipakai) | Tidak membingungkan penilai | Biarkan untuk fase backend, tetapi sebutkan di README bahwa itu kerangka bawaan yang tidak dipakai |
| **F-06** | LOW | Kesiapan pengumpulan | Terdapat berkas catatan sementara yang perlu dibersihkan sebelum rilis | Working tree harus bersih dan rapi | Working tree bersih saat dikumpulkan | Bersihkan berkas sementara sebelum pengumpulan |
| F-07 | INFORMATIONAL | SRS §2.3, ACM, TBD-03 | `app/Support/NavigasiDemo.php` (`PERAN`) | Dua peran (Admin, Anggota). Hak Petugas digabung ke Admin. Kelola pengguna/role tidak dibuat. | Sesuai keputusan proyek | Cantumkan deviasi di SRS/UML final atau laporan tugas |
| F-08 | INFORMATIONAL | FR-002, 019 (+ sebagian FR-003/013/016/017/018/024/025); NFR-003–005, 011–016, 019, 020; AC-02, 17 | — | Tidak ada autentikasi, sesi, otorisasi server, persistensi, transaksi DB, CSRF, maupun audit log | Dikecualikan oleh lingkup prototipe statis | Tetap tercatat sebagai NOT/PARTIALLY COMPLIANT. Kerjakan pada fase backend. |
| F-09 | INFORMATIONAL | FR-018, BR-08, AC-03, AC-15 | `routes/web.php` (rute `demo/*` publik) | Siapa pun dapat membuka `/demo/admin/*`. Peran hanya disimulasikan lewat URL. | Didokumentasikan sebagai simulasi, bukan keamanan | Jangan menyatakan pembatasan akses di laporan. README sudah jujur. |
| F-10 | INFORMATIONAL | SRS B.3, Quality Gate #5 | `docs/uml/*` | 9 inkonsistensi SRS ↔ UML terkait booking, atribut Class, peran, model stok (UML matrix §7) | Diagram diperbarui setelah keputusan | Perbarui UML terdampak (di luar lingkup audit) |
| F-11 | INFORMATIONAL | FR-024, FR-028, FR-033, SRS 6.1 `*_at` | `data/mock/*.js` | Tanggal transaksi disimpan sebagai tanggal (`YYYY-MM-DD`), bukan stempel waktu | Sesuai Class Diagram (`Date`). SRS 6.1 memakai `*_at`. | Tentukan presisi waktu saat desain DB (TBD-12) |
| F-12 | INFORMATIONAL | FR-024, TBD-05 | `validasiTransaksi.js:29-32` | Jatuh tempo manual, tanpa durasi default | Kebijakan belum ditetapkan | Pertahankan sampai TBD-05 diputuskan |
| F-13 | INFORMATIONAL | Deliverable desain | — | Tidak ada berkas Figma lokal; konektor Figma belum diotorisasi | 26 frame Figma dibandingkan dengan `docs/screenshots/` | Audit Figma **NOT VERIFIED**. Lakukan perbandingan manual atau beri akses Figma. |
| F-14 | INFORMATIONAL | Lingkup rute | `config/filesystems.php:36` | Rute bawaan `GET/PUT storage/{path}` aktif | Tidak dipakai aplikasi | Opsional: sebutkan rute PUT di README |
| F-15 | INFORMATIONAL | Kosakata status | `Admin/Dashboard.vue:111`, `Anggota/Booking/Status.vue:46` | Teks keterangan "Menunggu verifikasi" muncul. Nilai status tetap "Diajukan". | Status tidak diganti menjadi "Menunggu" (terpenuhi) | Tidak perlu tindakan. Dicatat untuk transparansi. |
| F-16 | INFORMATIONAL | Lingkup | `routes/web.php` (`hello/echo`, `demo/komponen`) | Dua halaman teknis non-bisnis ada | Dipertahankan atas instruksi pemilik; dikecualikan dari paket tangkapan layar | Tidak perlu tindakan |

## 11. Audit Figma

**Status: NOT VERIFIED.**

- Ditargetkan 26 frame: 1 Masuk, 12 Admin, 5 Anggota, 8 varian state.
- Tidak ada berkas `.fig` di repositori maupun di direktori pengguna yang dipindai. Konektor Figma di sesi ini memerlukan otorisasi, sehingga file desain tidak dapat dibuka.
- Yang **terverifikasi** hanyalah acuan screenshot: 26 PNG di `docs/screenshots/` (18 halaman utama + 8 state), lebar 1440 px. ZIP berisi 32 entri dan cocok dengan manifest.
- Keberadaan PNG **tidak** membuktikan bahwa desain Figma sesuai. Perbandingan sidebar, header, tipografi, warna, posisi komponen, field, kolom, teks, badge, dialog, panel detail, dan konten full-page **belum dilakukan**.
- Prototipe Figma yang dapat diklik di luar lingkup proyek.

## 12. Penilaian akhir

### READY WITH DOCUMENTED LIMITATIONS

**Dasar penilaian:**

1. Tidak ada temuan CRITICAL atau HIGH. Seluruh uji yang dijalankan ulang lulus: 142/142 Node, 19/19 PHPUnit, build berhasil, 35/35 halaman, 231/231 cek browser, 70/70 cek responsif, 0 request eksternal.
2. Semua FR fungsional yang dapat diwujudkan pada prototipe UI statis terbukti di antarmuka dan simulasi (28 COMPLIANT). Data mock konsisten, dan alur kritis booking–peminjaman–pengembalian atomik.
3. Kekurangan (2 NOT COMPLIANT, 7 PARTIALLY COMPLIANT) bersumber dari lingkup statis yang disetujui (tanpa DB, autentikasi, dan sesi) atau dari kebijakan SRS yang masih TBD. Semuanya didokumentasikan dan tidak disamarkan sebagai kepatuhan.
4. Deviasi peran (Admin + Petugas → Admin) dan 9 inkonsistensi SRS ↔ UML tercatat sebagai keputusan proyek atau butir terbuka.

**Syarat sebelum pengumpulan:**

- Selesaikan F-06 (working tree belum bersih).
- Bila Figma termasuk deliverable yang dinilai, verifikasi 26 frame secara terpisah. Status F-13 saat ini NOT VERIFIED, sehingga **kesiapan deliverable Figma tidak dapat dinyatakan**.
- Disarankan (tidak memblokir): putuskan F-01, perbaiki F-02 s.d. F-05.

**Penilaian ini tidak berlaku** bila aplikasi dikumpulkan sebagai implementasi SRS penuh dengan backend. Untuk klaim tersebut, statusnya **NOT READY FOR SUBMISSION** karena FR-002, FR-019, dan persyaratan keamanan/persistensi belum dibangun.
