# Laporan Uji Transaksi — Batch 2

Semua test database memakai **`sim_perpustakaan_test`** saja (MySQL 8.4). Database development tidak disentuh test: checksum seluruh tabel identik sebelum dan sesudah `php artisan test` maupun QA browser.

## 1. Ringkasan hasil

| Pemeriksaan | Hasil |
| --- | --- |
| `php artisan test` | **262/262** lulus (3248 asersi); Batch 2 menambah 43 test |
| `npm run test:data` | **239/239** lulus; Batch 2 menambah 13 test |
| `npm run build` | berhasil |
| Pint | lulus |
| `git diff --check` | bersih |
| Migration | `000009` Ran di uji & development (12 migration) |
| Audit integritas (`PemeriksaInvarianData`) | development: 0 pelanggaran; uji setelah QA: 0 pelanggaran |
| `schedule:list` | satu entri `*/5 * * * * php artisan bookings:expire` |
| QA browser | **39/39** cek, satu putaran bersih |

## 2. Peta butir uji (rencana 1–64)

| Butir | Test |
| --- | --- |
| 1, 6 | `PeminjamanTest::test_peminjaman_langsung_berhasil_dengan_admin_dari_sesi` |
| 2, 3, 4 | `PeminjamanTest::test_anggota_nonaktif_dan_eksemplar_tidak_tersedia_ditolak` |
| 5 | `PeminjamanTest::test_tanggal_jatuh_tempo_wajib_valid_dan_setelah_hari_ini` (termasuk batas hari WIB vs UTC) |
| 7 | `PeminjamanTest::test_kegagalan_di_tengah_transaksi_tidak_meninggalkan_rekaman` |
| 8, 11, 12, 13 | `PeminjamanTest::test_pengembalian_berhasil` |
| 9 | `PeminjamanTest::test_pinjaman_anggota_nonaktif_tetap_dapat_dikembalikan_tanpa_mengaktifkan` |
| 10 | `PeminjamanTest::test_pengembalian_ganda_dan_tidak_valid_ditolak` (+ UNIQUE) |
| 14 | `PeminjamanTest::test_rollback_pengembalian_mempertahankan_keadaan_awal` |
| 15, 17, 20 | `BookingTest::test_pengajuan_berhasil_tanpa_mereservasi_eksemplar` |
| 16, 18, 19 | `BookingTest::test_pengajuan_ditolak_untuk_nonaktif_eksemplar_tidak_tersedia_dan_ganda` |
| 21, 24, 25 | `BookingTest::test_persetujuan_mereservasi_dan_batas_tepat_48_jam` |
| 22, 23, 28 | `BookingTest::test_persetujuan_ditolak_untuk_nonaktif_eksemplar_tidak_tersedia_dan_ganda` |
| 26, 27 | `BookingTest::test_penolakan_wajib_alasan_dan_eksemplar_tetap_tersedia` |
| 29 | `BookingTest::test_f01_persetujuan_dan_penonaktifan_saling_menjaga` + dua test F-01 dua koneksi |
| 30–34 | `BookingTest::test_realisasi_membuat_tepat_satu_peminjaman`, `…_ditolak_bila_eksemplar_tidak_dipesan_untuk_booking_ini` |
| 35, 48 | `KedaluwarsaBookingTest::test_realisasi_setelah_batas_ditolak_dan_kedaluwarsa_tetap_tercommit` |
| 36–40 | `BookingTest::test_anggota_membatalkan_booking_sendiri`, `…_anggota_lain_tidak_dapat_membatalkan…`; pembatalan setelah batas: `KedaluwarsaBookingTest::test_pembatalan_setelah_batas_menjadi_kedaluwarsa_bukan_dibatalkan` |
| 41, 42, 43, 49 | `KedaluwarsaBookingTest::test_tepat_pada_batas_kedaluwarsa_dan_sesaat_sebelumnya_tetap_disetujui`, `…_penjadwal_tertunda_menyimpan_waktu_pemrosesan_aktual`, `…_expired_at_memakai_waktu_pemrosesan_tiap_item_bukan_awal_eksekusi`, `…_eksemplar_milik_transaksi_lain_tidak_dilepas` |
| 44, 45 | `KedaluwarsaBookingTest::test_kedaluwarsa_idempoten_tanpa_audit_ganda` |
| 46, 47, J | `KedaluwarsaBookingTest::test_batch_memproses_semua_dan_kegagalan_satu_tidak_menghentikan_lainnya` |
| — (perintah & jadwal) | `KedaluwarsaBookingTest::test_perintah_artisan_dan_jadwal_tiap_lima_menit` |
| 50 | `SeederDemoTest::test_booking_disetujui_seed_berbatas_48_jam_dan_lewat_batas_diproses_aman`, `KedaluwarsaBookingTest::test_constraint_konsistensi_kedaluwarsa_ditegakkan_mysql`, gladi migration (§3) |
| 51–57, H, I | `KonkurensiTransaksiTest` (11 test, dua koneksi MySQL) |
| 57 (rekonsiliasi) | `KedaluwarsaBookingTest::test_reservasi_lewat_batas_direkonsiliasi_sebelum_persetujuan_baru_dan_penonaktifan` |
| 58 | `KonkurensiTransaksiTest` (lock wait → galat "coba lagi", tanpa perubahan); deadlock nyata tidak disimulasikan |
| 59, 61 | `PeminjamanTest::test_rute_transaksi_hanya_untuk_admin`, field istimewa diabaikan di test 1/15/21/30; `KredensialTest` (daftar rute tulis) |
| 60 | `BookingTest::test_anggota_lain_tidak_dapat_membatalkan_atau_melihat_booking_orang_lain` |
| 62, 63, 64 | `BookingTest::test_respons_tanpa_data_sensitif_dan_operasi_gagal_tanpa_audit`; aktor audit di test 1, 8, 15, 21, 36, 41 |

Jam dibekukan dengan `travelTo` (tanpa `sleep`), termasuk batas tepat 48 jam, sesaat sebelum batas (−1 µs), dan penjadwal tertunda.

## 3. Gladi migration (database uji)

1. Rollback `000009` → isi data historis skema lama (Disetujui lewat batas, Disetujui belum lewat, Ditolak, Direalisasikan, Diajukan).
2. `migrate`: hanya Disetujui yang mendapat `expires_at = decided_at + 48 jam`; status tidak berubah.
3. Guard: Disetujui tanpa `decided_at` → migration berhenti, skema tidak berubah.
4. Data dibersihkan, migrate ulang → 12 migration, tabel kosong.

## 4. QA browser (39/39)

Chrome headless dijalankan terhadap database uji, dengan kata sandi sementara acak (file 600, dihapus setelah QA):

- **A** — peminjaman langsung, klik ganda hanya menghasilkan 1 transaksi, dan muncul di history Anggota.
- **B** — pengembalian; eksemplar kembali Tersedia.
- **C/D** — booking diajukan, lalu disetujui dengan Batas Pengambilan dalam WIB (hari ini + 2).
- **E** — realisasi; muncul di history.
- **F** — penolakan dengan alasan wajib, dan alasan terlihat oleh Anggota.
- **G** — Anggota membatalkan booking Disetujui; reservasi dilepas.
- **L** — booking lewat batas ditolak saat direalisasikan dan menjadi Kedaluwarsa.
- **H** — `bookings:expire` memproses 1 booking, dan Anggota melihat Kedaluwarsa.
- **K** — F-01 menolak penonaktifan.
- **I** — Anggota Nonaktif tidak dapat meminjam (browser maupun server) atau mengajukan booking.
- **J** — pinjaman Anggota Nonaktif dapat dikembalikan, dan status Nonaktif-nya tetap.
- **Lain-lain:**
  - dashboard memuat Kedaluwarsa;
  - laporan pengembalian memuat transaksi baru;
  - lebar 390 px tanpa geser horizontal;
  - tanpa request 4xx/5xx;
  - tanpa error JavaScript di ketiga sesi.

**Temuan QA yang diperbaiki:** setelah realisasi ditolak karena Kedaluwarsa, booking keluar dari daftar Disetujui sehingga formulir dan pesan galatnya hilang. Kini pesan ditampilkan di tingkat halaman (dijaga test Node).

## 5. Keterbatasan

- Deadlock MySQL yang nyata tidak direproduksi; hanya lock wait yang diuji.
- Dashboard dan laporan menghitung ringkasan di browser dari data MySQL yang dimuat (pola Phase 8B), bukan agregat SQL khusus.
- Penjadwal belum beroperasi di production (perlu cron `schedule:run` tiap menit). Di development, BP-001 dan BP-006 masih Disetujui dengan batas yang sudah lewat sampai penjadwal berjalan.
