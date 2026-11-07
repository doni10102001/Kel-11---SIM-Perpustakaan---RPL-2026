# SRS Traceability Matrix — SIM Perpustakaan

Audit tanggal 7 November 2026 terhadap commit `86552a1` (ditambah dua penghapusan file instruksi yang di-*stage* tetapi belum di-commit). Sumber: `docs/requirements/Tugas_2_SRS_FINAL.pdf` (SRS v1.1, teks diekstrak dengan Ghostscript), kode sumber, dan hasil uji yang **dijalankan ulang pada audit ini**.

## Konvensi

- **Status:** COMPLIANT · PARTIALLY COMPLIANT · NOT COMPLIANT · NOT APPLICABLE · NOT VERIFIED.
- **Prioritas SRS** disalin apa adanya. Tanda `*` berarti SRS menyatakan kebutuhan itu **usulan v1.1 yang belum final**; status usulan tersebut tetap berlaku walaupun fitur telah dibangun.
- **"Simulasi"** berarti perilaku berjalan pada store reaktif di browser (`resources/js/composables/useDataPerpustakaan.js`) tanpa server, database, atau sesi. Data kembali ke awal saat halaman dimuat ulang.
- **Peran:** SRS menyebut Admin/Petugas. Prototipe hanya memakai peran **Admin** (keputusan proyek, `docs/KEPUTUSAN_DESAIN.md` §"Keputusan final peran"). Ini adalah **deviasi dokumentasi**, bukan bukti kepatuhan terhadap pembagian peran SRS.
- **Rute** ditulis tanpa prefiks: `A:` = `/demo/admin/`, `G:` = `/demo/anggota/`.

### Singkatan berkas uji

| Kode | Berkas | Isi |
| --- | --- | --- |
| T-MOCK | `tests/js/mock-data.test.js` | Invariant data mock, dashboard, riwayat |
| T-MASTER | `tests/js/master-data.test.js` | Pencarian & validasi anggota/buku |
| T-TRX | `tests/js/transaksi.test.js` | Pinjam, kembali, verifikasi, realisasi, atomisitas, laporan |
| T-AG | `tests/js/anggota.test.js` | Cari buku, ajukan/batalkan booking, privasi, alur lintas peran |
| T-PHP | `tests/Feature/NavigasiDemoTest.php` | Rute, menu, tanpa query DB, rute Petugas 404 |
| T-BRW | Skrip Chrome headless `phase1/3/4/5-browser.mjs` | Interaksi browser. **Disimpan di scratchpad sesi Claude, tidak ada di repositori** (lihat temuan F-02) |
| T-AUD | Skenario audit `skenario.mjs` / `skenario-a.mjs` | Ditulis khusus audit ini di scratchpad; tidak ditambahkan ke proyek |

## Ringkasan

| Status | Jumlah | FR |
| --- | --- | --- |
| COMPLIANT | 28 | FR-001, 004, 005, 006, 007, 008, 009, 010, 011, 012, 014, 015, 020, 021, 022, 023, 026, 027, 028, 029, 030, 031, 032, 033, 034, 035, 036, 037 |
| PARTIALLY COMPLIANT | 7 | FR-003, 013, 016, 017, 018, 024, 025 |
| NOT COMPLIANT | 2 | FR-002, FR-019 |
| NOT APPLICABLE | 0 | — |
| NOT VERIFIED | 0 | — |
| **Total FR unik** | **37** | FR-001 s.d. FR-037. FR-007 tercantum di §4.2 dan §4.7, tetapi dihitung satu kali. |

"COMPLIANT" pada matriks ini berarti **perilaku yang diminta tersedia dan terbukti di antarmuka maupun simulasi**. Label ini **tidak** berarti persyaratan server-side SRS juga terpenuhi, seperti transaksi ACID, otorisasi server, persistensi, atau audit log. Persyaratan server-side tersebut dikecualikan oleh lingkup prototipe statis yang disetujui.

## Matriks FR

### 4.1 Autentikasi dan Dashboard (UC-01)

| ID | Kebutuhan (ringkas) | Prio | Peran | Halaman / Rute | Implementasi | Uji | Status | Bukti | Temuan |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| FR-001 | Form login Admin/Petugas dengan identitas pengguna & kata sandi | Must | Admin, Anggota | `Auth/Login.vue` · `/login` | `Login.vue:129-153` | T-BRW (fase 1), T-PHP | COMPLIANT | Field "Identitas Pengguna" dan "Kata Sandi" wajib diisi, tombol "Masuk" | Form hanya simulasi. Petugas tidak punya akun (keputusan peran). |
| FR-002 | Validasi kredensial terhadap data pengguna di basis data | Must | Semua | `/login` | `config/demo.js` (`cariAkunDemo`) | T-MOCK | NOT COMPLIANT | Tidak ada basis data. Kata sandi apa pun yang tidak kosong diterima (`Login.vue:178`, `KEPUTUSAN_DESAIN.md:110`). | Dikecualikan oleh lingkup statis. Lihat F-08. |
| FR-003 | Pesan kesalahan bila kredensial tidak valid | Must | Semua | `/login` | `Login.vue:61,127` | T-MOCK (`cariAkunDemo('petugas') === null`), T-BRW | PARTIALLY COMPLIANT | Identitas tak dikenal memunculkan pesan generik "Identitas pengguna atau kata sandi tidak sesuai…" | Kata sandi yang salah tidak pernah ditolak karena tidak ada verifikasi. |
| FR-004 | Dashboard tampil setelah autentikasi berhasil | Must | Admin, Anggota | `A:dashboard`, `G:dashboard` | `Login.vue`, `Admin/Dashboard.vue`, `Anggota/Dashboard.vue` | T-BRW (login admin & AG-001) | COMPLIANT | Login simulasi mengarahkan ke dashboard sesuai peran | Simulasi. |
| FR-016 | Logout mengakhiri sesi | Must | Admin, Anggota | Semua halaman → `/login` | `AppShell.vue:77-81` (dialog "Keluar dari aplikasi?") | T-BRW | PARTIALLY COMPLIANT | Ada aksi Keluar dengan konfirmasi | Tidak ada sesi server yang diakhiri. URL terproteksi tetap dapat dibuka. |
| FR-017* | Autentikasi Anggota terdaftar & Aktif | Must* | Anggota | `/login` | `config/demo.js` | T-MOCK | PARTIALLY COMPLIANT | Hanya AG-001 (Aktif) yang dapat masuk | Status Aktif tidak diperiksa saat login karena hanya ada satu akun anggota. Metode identitas masih TBD-02. |
| FR-018* | Fungsi & data dibatasi per peran; anggota tidak melihat histori anggota lain | Must* | Semua | `G:*` | `Anggota/*.vue` (filter `kodeAnggota` dari akun demo), `NavigasiDemo.php` | T-AG (privasi), T-AUD E1, T-PHP | PARTIALLY COMPLIANT | Halaman Anggota hanya menampilkan data AG-001 (booking 3, peminjaman 3). Katalog tidak menampilkan peminjam. | Tidak ada penegakan akses: siapa pun dapat membuka `/demo/admin/*` lewat URL (F-09). |
| FR-019* | Blokir akses dengan sesi tidak valid/berakhir | Must* | Semua | — | — | — | NOT COMPLIANT | Tidak ada sesi maupun middleware autentikasi. README menyatakan "Semua halaman dapat dibuka langsung tanpa login." | Dikecualikan oleh lingkup statis (F-08). |

### 4.2 Kelola Data Buku dan Eksemplar (UC-02)

| ID | Kebutuhan | Prio | Peran | Halaman / Rute | Implementasi | Uji | Status | Bukti | Temuan |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| FR-005 | Tambah buku | Must | Admin | `Admin/Buku/Form.vue` · `A:buku/tambah` | `useDataPerpustakaan.js:120` `tambahBuku` | T-MASTER, T-BRW (fase 3) | COMPLIANT | Form dengan 6 field wajib dan minimal 1 eksemplar | Simulasi. |
| FR-006 | Perbarui buku | Must | Admin | `A:buku/{kodeBuku}/ubah` | `useDataPerpustakaan.js:133` `ubahBuku` | T-MASTER, T-BRW | COMPLIANT | Field dapat diubah. Kode Buku baca-saja. | Kode tidak dapat diubah adalah keputusan prototipe yang belum final (KD 3.3). |
| FR-007 | Pencarian buku min. judul, kode, kategori | Must | Admin, Anggota | `A:buku`, `G:cari-buku` | `utils/pencarian.js:29` (`filterBuku`, `filterKatalog`) | T-MASTER, T-AG, T-BRW | COMPLIANT | Kata kunci dicocokkan dengan kodeBuku, judul, kategori. Cari Buku juga punya filter kategori. | — |
| FR-020* | Satu judul punya ≥1 eksemplar dengan kode unik & status sendiri | Must* | Admin | `A:buku`, `A:buku/tambah` | `data/mock/bookCopies.js`, `validasiMaster.js` | T-MOCK, T-MASTER | COMPLIANT | 18 eksemplar untuk 10 buku, masing-masing berstatus Tersedia/Dipesan/Dipinjam | Prototipe mewajibkan ≥1 eksemplar (SRS 6.2 "1..*"). FR-020 sendiri berbunyi "dapat". |
| FR-021* | Tolak kode buku/eksemplar duplikat beserta sebabnya | Must* | Admin | `A:buku/tambah`, `A:buku/{kodeBuku}/ubah` | `validasiMaster.js:82,104` | T-MASTER | COMPLIANT | "Kode Buku {kode} sudah digunakan buku lain.", "Kode Eksemplar {kode} sudah digunakan." | Perbandingan tidak peka huruf besar/kecil. |

### 4.3 Kelola Data Anggota (UC-03)

| ID | Kebutuhan | Prio | Peran | Halaman / Rute | Implementasi | Uji | Status | Bukti | Temuan |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| FR-008 | Tambah, ubah, cari, lihat anggota | Must | Admin | `Admin/Anggota/Index.vue`, `Form.vue` · `A:anggota`, `A:anggota/tambah`, `A:anggota/{kodeAnggota}/ubah` | `useDataPerpustakaan.js:72,90`, `pencarian.js:23` | T-MASTER, T-BRW | COMPLIANT | Tabel, pencarian (kode/nama/kontak), filter status, panel "Detail Anggota", form tambah/ubah | — |
| FR-022* | Aktifkan/nonaktifkan tanpa menghapus riwayat | Should* | Admin | `A:anggota/{kodeAnggota}/ubah` | `Form.vue:91-95,221-229`, `useDataPerpustakaan.js:90` | T-MASTER, T-AUD F1 | COMPLIANT | Field Status Anggota dengan dialog "Nonaktifkan anggota?". Riwayat tetap ada. | Penonaktifan anggota yang punya booking Disetujui mengunci eksemplar tanpa jalan keluar bagi Admin (F-01). |
| FR-023* | Tolak nomor anggota ganda | Must* | Admin | `A:anggota/tambah` | `validasiMaster.js:53` | T-MASTER | COMPLIANT | "Kode Anggota {kode} sudah digunakan anggota lain." | "Nomor anggota" = Kode Anggota (KD Phase 0 #2). |

### 4.4 Peminjaman Buku (UC-04)

| ID | Kebutuhan | Prio | Peran | Halaman / Rute | Implementasi | Uji | Status | Bukti | Temuan |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| FR-009 | Catat peminjaman untuk anggota & buku valid | Must | Admin | `Admin/Peminjaman.vue` · `A:peminjaman` | `useDataPerpustakaan.js:156`, `validasiTransaksi.js:45-70` | T-TRX, T-AUD D4, T-BRW (fase 4) | COMPLIANT | Peminjaman tercatat dan kartu "Bukti Peminjaman" ditampilkan | Simulasi. |
| FR-010 | Periksa ketersediaan sebelum disimpan | Must | Admin | `A:peminjaman` | `validasiTransaksi.js:55-70` | T-TRX, T-AUD D2/A3 | COMPLIANT | Eksemplar Dipinjam/Dipesan ditolak dan data tidak berubah | — |
| FR-024* | Simpan tanggal pinjam, petugas, jatuh tempo berdasarkan konfigurasi kebijakan | Must* | Admin | `A:peminjaman`, `A:booking/realisasi` | `Peminjaman.vue:205-219`, `validasiTransaksi.js:29-32` | T-TRX, T-AUD D3 | PARTIALLY COMPLIANT | Tanggal Pinjam otomatis, "Admin Pemroses", dan Tanggal Jatuh Tempo wajib | Jatuh tempo diisi **manual** karena kebijakan durasi masih TBD-05. Belum ada "konfigurasi kebijakan". Tanggal disimpan tanpa jam. |
| FR-025* | Cegah 2 peminjaman aktif pada eksemplar sama, termasuk permintaan bersamaan | Must* | Admin | `A:peminjaman` | `validasiTransaksi.js`, `data/mock/validasi.js` (`periksaKonsistensi`) | T-TRX, T-MOCK, T-AUD D4/D5 | PARTIALLY COMPLIANT | Permintaan kedua yang berurutan ditolak, dan invariant memeriksa maksimal satu peminjaman Aktif per eksemplar | Permintaan **bersamaan** tidak dapat diuji tanpa server/DB (locking, ACID). |
| FR-026* | Realisasi booking Disetujui menjadi peminjaman setelah cek anggota & eksemplar | Must* | Admin | `Admin/Booking/Realisasi.vue` · `A:booking/realisasi` | `useDataPerpustakaan.js:228`, `validasiTransaksi.js:125-147` | T-TRX, T-AUD A4/F2, T-BRW | COMPLIANT | Memeriksa anggota Aktif, eksemplar Dipesan untuk booking ini, dan tanggal ≥ persetujuan | Lihat F-01 (anggota Nonaktif → realisasi buntu). |

### 4.5 Pengembalian Buku (UC-05)

| ID | Kebutuhan | Prio | Peran | Halaman / Rute | Implementasi | Uji | Status | Bukti | Temuan |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| FR-011 | Catat pengembalian untuk peminjaman aktif | Must | Admin | `Admin/Pengembalian.vue` · `A:pengembalian` | `useDataPerpustakaan.js:180` | T-TRX, T-AUD A5 | COMPLIANT | Tab "Peminjaman Aktif" → "Proses Pengembalian" → KB baru | — |
| FR-012 | Perbarui status buku setelah pinjam/kembali | Must | Admin | `A:peminjaman`, `A:pengembalian` | idem | T-TRX, T-AUD A4/A5 | COMPLIANT | Tersedia→Dipinjam dan Dipinjam→Tersedia dalam satu transaksi atomik | — |
| FR-027* | Tolak pengembalian ganda | Must* | Admin | `A:pengembalian` | `validasiTransaksi.js:79-89` | T-TRX, T-AUD A6 | COMPLIANT | "Pengembalian ditolak: transaksi {id} sudah dikembalikan." | Di UI, peminjaman Selesai tidak bisa dipilih lagi. Validasi tetap ada di lapisan aksi. |
| FR-028* | Simpan waktu kembali & petugas pemroses | Must* | Admin | `A:pengembalian` | `data/mock/returns.js` (`tanggalKembali`, `diprosesOleh`) | T-TRX, T-MOCK | COMPLIANT | Tab Riwayat menampilkan Tanggal Kembali dan Admin Pemroses | Disimpan sebagai tanggal (sesuai Class Diagram `tanggalKembali: Date`), bukan stempel waktu (`returned_at`). |

### 4.6 Kelola Laporan (UC-06)

| ID | Kebutuhan | Prio | Peran | Halaman / Rute | Implementasi | Uji | Status | Bukti | Temuan |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| FR-014 | Laporan daftar buku, anggota, peminjaman, pengembalian | Must | Admin | `Admin/Laporan.vue` · `A:laporan` | `utils/laporan.js:9-14` | T-TRX (Kelola Laporan), T-BRW | COMPLIANT | Empat jenis laporan dengan kolom dari data bersama | — |
| FR-015 | Filter periode bila data periode tersedia | Should | Admin | `A:laporan` | `laporan.js` (`mendukungPeriode`, `validasiFilterLaporan`) | T-TRX | COMPLIANT | Periode berlaku untuk Peminjaman (Tanggal Pinjam) dan Pengembalian (Tanggal Kembali), dinonaktifkan untuk daftar buku/anggota | — |
| FR-029* | Cetak laporan hasil filter; PDF opsional | Should* | Admin | `A:laporan` | `Laporan.vue` (dialog cetak browser), CSS cetak di `app.css` | T-BRW | COMPLIANT | Tombol "Cetak Laporan" aktif setelah laporan ditampilkan | PDF/Excel tidak dibuat (TBD-07). |

### 4.7 Cari Buku (UC-07)

| ID | Kebutuhan | Prio | Peran | Halaman / Rute | Implementasi | Uji | Status | Bukti | Temuan |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| FR-007 | (lihat §4.2) | Must | Anggota | `G:cari-buku` | `pencarian.js` (`filterKatalog`) | T-AG | COMPLIANT | — | — |
| FR-030* | Anggota mencari & melihat daftar buku serta ketersediaan | Must* | Anggota | `Anggota/CariBuku.vue` · `G:cari-buku` | `CariBuku.vue:26-33`, `ketersediaanBuku` | T-AG, T-BRW (fase 5) | COMPLIANT | Kolom Ketersediaan ("Tersedia"/"Tidak tersedia" + ikon + teks "n dari m eksemplar tersedia") dan panel eksemplar | Status dibedakan dengan teks dan ikon, tidak hanya warna (SRS 3.1). |

### 4.8 Booking Pinjam Buku (UC-08) — seluruh fitur berstatus usulan

| ID | Kebutuhan | Prio | Peran | Halaman / Rute | Implementasi | Uji | Status | Bukti | Temuan |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| FR-031* | Anggota aktif booking eksemplar tersedia; ID unik; status awal Diajukan | Must* | Anggota | `Anggota/Booking/Ajukan.vue` · `G:booking/ajukan` | `useDataPerpustakaan.js:258`, `validasiTransaksi.js:160-185` | T-AG, T-AUD A0/A1 | COMPLIANT | BP-009 dibuat dengan status Diajukan dan eksemplar tetap Tersedia. Anggota Nonaktif ditolak. | Penolakan pengajuan ganda adalah keputusan prototipe (KD 5.4). Kuota masih TBD-06. |
| FR-032* | Anggota membatalkan booking miliknya sebelum direalisasikan | Should* | Anggota | `Anggota/Booking/Status.vue` · `G:booking/status` | `useDataPerpustakaan.js:284`, `validasiTransaksi.js:191-200` | T-AG, T-AUD C1–C4 | COMPLIANT | Diajukan/Disetujui dapat dibatalkan. Booking milik orang lain dan booking Direalisasikan ditolak. | Pembatalan setelah Disetujui masih TBD-06 (KD 5.6). |
| FR-033* | Petugas setujui/tolak booking Diajukan; catat waktu, pelaku, alasan tolak | Must* | Admin | `Admin/Booking/Verifikasi.vue` · `A:booking/verifikasi` | `useDataPerpustakaan.js:200,213`, `validasiTransaksi.js:98-122` | T-TRX, T-AUD A2/B1/B2, T-BRW | COMPLIANT | Tanggal Keputusan, "Diputuskan oleh", dan Alasan Penolakan (wajib, maks. 200 karakter) | Pelaku = Admin (deviasi peran). Waktu disimpan sebagai tanggal. |
| FR-034* | Booking disetujui → eksemplar Dipesan sampai direalisasikan/dibatalkan | Must* | Admin | `A:booking/verifikasi` | `useDataPerpustakaan.js:200` | T-TRX, T-AUD A2/A3 | COMPLIANT | EK-004 menjadi Dipesan, dan peminjaman langsung atas eksemplar itu ditolak | — |
| FR-035* | Realisasi → Direalisasikan + peminjaman; batal/tolak melepas reservasi | Must* | Admin, Anggota | `A:booking/realisasi`, `G:booking/status` | `useDataPerpustakaan.js:228,284` | T-TRX, T-AG, T-AUD A4/C4 | COMPLIANT | Booking Direalisasikan terkait PJ-011 dan EK-004 Dipinjam. Pembatalan Disetujui mengubah Dipesan→Tersedia. | Booking Diajukan tidak mengunci eksemplar, sehingga penolakan tidak perlu melepas reservasi. |

### 4.9 Lihat History Peminjaman (UC-09)

| ID | Kebutuhan | Prio | Peran | Halaman / Rute | Implementasi | Uji | Status | Bukti | Temuan |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| FR-013 | Simpan dan tampilkan riwayat transaksi | Must | Admin, Anggota | `G:history`, `A:pengembalian` (tab Riwayat), `A:laporan` | `Anggota/History.vue`, `laporan.js` | T-MOCK, T-AG | PARTIALLY COMPLIANT | Riwayat ditampilkan di tiga tempat | "Menyimpan" tidak terpenuhi: perubahan hanya ada di memori dan hilang saat dimuat ulang (lingkup statis). |
| FR-036* | Anggota login hanya melihat riwayat miliknya | Must* | Anggota | `G:history` | `History.vue` (`peminjamanAnggota(data, kodeAnggota)`) | T-AG (History & privasi), T-AUD E1 | COMPLIANT | Hanya peminjaman AG-001 yang tampil | Identitas berasal dari akun demo, bukan sesi. Penegakan akses dibahas di FR-018. |
| FR-037* | Tampilkan status Aktif/Selesai + tanggal terkait | Must* | Anggota | `G:history` | `History.vue:37-45` | T-AG, T-BRW | COMPLIANT | Kolom Tanggal Pinjam, Tanggal Kembali ("—" untuk Aktif), dan Status | — |

## Business Rules (SRS 5.5)

| BR | Aturan | Status | Bukti |
| --- | --- | --- | --- |
| BR-01 | Pinjam hanya anggota Aktif & eksemplar Tersedia | COMPLIANT | `validasiTransaksi.js:50-70`, T-AUD D1/D2 |
| BR-02 | Pinjam + status eksemplar atomik | COMPLIANT (simulasi) | `jalankanTransaksi` (`useDataPerpustakaan.js:53-66`). Tidak ada transaksi DB. |
| BR-03 | Kembali hanya untuk peminjaman Aktif | COMPLIANT | `validasiTransaksi.js:79-89` |
| BR-04 | Kembali → Selesai + Tersedia | COMPLIANT | T-AUD A5 |
| BR-05 (usulan) | Maks. satu peminjaman Aktif per eksemplar | COMPLIANT (sekuensial) | `periksaKonsistensi`, T-AUD D5 |
| BR-06 (usulan) | Diajukan → Disetujui/Ditolak; Disetujui → Dibatalkan/Direalisasikan | COMPLIANT dengan tambahan | Diajukan → Dibatalkan juga diizinkan (State Machine 04, FR-032). Aktor pembatalan Disetujui = Anggota saja (KD 4.8). |
| BR-07 (usulan) | Dipesan tidak boleh dipinjam selain lewat realisasi | COMPLIANT | T-AUD A3 |
| BR-08 (usulan) | Anggota hanya melihat miliknya; Admin/Petugas dibatasi kewenangannya | PARTIALLY COMPLIANT | Cakupan data Anggota benar, tetapi tidak ada pembatasan akses URL (F-09) |
| BR-09 | Periode, kuota, batas booking masih TBD | Dipertahankan TBD | Tidak ada nilai kebijakan yang dikarang. Jatuh tempo manual. |

## Acceptance Criteria (SRS 6.4)

| AC | Status | Catatan |
| --- | --- | --- |
| AC-01 | PARTIALLY COMPLIANT | Login valid menampilkan dashboard, tetapi kata sandi salah tidak ditolak |
| AC-02 | NOT COMPLIANT | Tidak ada sesi yang dapat diinvalidasi |
| AC-03 | PARTIALLY COMPLIANT | Anggota dapat login (simulasi), tetapi tidak dicegah membuka fungsi Admin lewat URL |
| AC-04 | COMPLIANT | Diuji oleh Admin (bukan Petugas) |
| AC-05 | COMPLIANT | Idem |
| AC-06 | COMPLIANT | T-TRX, T-AUD D4 |
| AC-07 | PARTIALLY COMPLIANT | Sekuensial terpenuhi; konkuren tidak dapat diuji |
| AC-08 | COMPLIANT | T-AUD A5 |
| AC-09 | COMPLIANT | T-AUD A6 |
| AC-10 | COMPLIANT | T-TRX "Kelola Laporan" |
| AC-11 | COMPLIANT | Filter periode dan cetak browser |
| AC-12 | COMPLIANT | T-AG "Cari Buku" |
| AC-13 | COMPLIANT | T-AUD A1/A2/B2 |
| AC-14 | COMPLIANT (simulasi) | Atomik pada store, bukan DB |
| AC-15 | PARTIALLY COMPLIANT | Halaman Anggota benar, tetapi URL Admin terbuka untuk siapa pun |
| AC-16 | COMPLIANT (simulasi) | T-TRX "Atomisitas & rollback", T-AUD A3b/D2 |
| AC-17 | NOT COMPLIANT | Tidak ada validasi server dan tidak ada audit log (lingkup statis) |

## NFR — ringkasan singkat

NFR tidak termasuk perhitungan FR di atas. Catatan berbasis bukti:

- **Terpenuhi atau terbukti di prototipe:**
  - NFR-006 (konsistensi data, simulasi)
  - NFR-008 (navigasi dan pesan dalam Bahasa Indonesia)
  - NFR-017 (lebar 360 px: 70/70 cek responsif pada 390×844 dan 360×740, dijalankan ulang)
- **Tidak berlaku pada lingkup statis (NOT COMPLIANT secara SRS):**
  - NFR-003/004/005 (sandi, otorisasi, sesi)
  - NFR-011–015 (HTTPS, timeout, validasi server, CSRF, audit)
  - NFR-016 (backup)
  - NFR-019 (pagination server-side)
  - NFR-020 (rollback DB)
- **NOT VERIFIED:**
  - NFR-001/018 (target respons dan beban)
  - NFR-021 (Firefox/Edge; hanya Chrome yang diuji)
