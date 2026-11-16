# Konkurensi & Penguncian Transaksi (Batch 2)

## 1. Urutan kunci (wajib)

```text
1. members  →  2. bookings  →  3. book_copies  →  4. loans  →  5. returns
```

Setiap service transaksi (`App\Services\Transaksi\*` dan `LayananAnggota::ubah`) membuka `DB::transaction`, mengunci baris dengan `SELECT … FOR UPDATE` (`lockForUpdate`) **dalam urutan di atas**, lalu membaca ulang semua syarat setelah kunci dipegang. Pembacaan awal tanpa kunci hanya untuk menemukan ID (mis. `member_id` sebuah booking); keputusan selalu diambil dari data terkunci.

| Operasi | Kunci (urut) |
| --- | --- |
| Peminjaman langsung | member → copy |
| Pengembalian | member → copy → loan (return disisipkan) |
| Ajukan booking | member (pemilik) → copy |
| Setujui / Realisasi | member → booking → copy |
| Tolak | member → booking |
| Batalkan | member (pemilik) → booking → copy (bila Disetujui) |
| Kedaluwarsa | member → booking → copy |
| Nonaktifkan anggota (F-01) | member → (baca booking Disetujui) |
| Tambah eksemplar (Batch 1) | book → (insert copy) — tidak mengunci member/booking/loan |

Rekonsiliasi reservasi yang lewat batas sebelum peminjaman/pengajuan/persetujuan/penonaktifan berjalan sebagai **transaksi terpisah** sebelum transaksi utama, sehingga tidak pernah mengunci dua anggota dalam satu transaksi.

## 2. Pertahanan berlapis MySQL

| Constraint | Menjamin |
| --- | --- |
| `uq_loans_active_copy` (generated `active_copy_id`) | Maks. satu loan Aktif per eksemplar |
| `uq_loans_booking_id` | Satu booking maks. satu loan |
| `uq_bookings_reserved_copy` (generated `reserved_copy_id`) | Maks. satu booking Disetujui per eksemplar |
| `uq_returns_loan_id` | Maks. satu pengembalian per loan |
| `chk_bookings_status`, `chk_bookings_expiry` | Kosakata status & konsistensi timestamp kedaluwarsa |
| FK `RESTRICT` | Riwayat tidak terhapus berantai |

Bentrok UNIQUE (1062) yang lolos validasi diterjemahkan menjadi galat validasi bisnis.

## 3. Deadlock & lock wait

- `DB::transaction($kerja, 3)`: galat konkurensi (deadlock 1213 / lock wait timeout 1205) diulang otomatis hingga 3 percobaan.
- Tetap gagal → `ValidationException` "Data yang sama sedang diproses transaksi lain. Tidak ada perubahan yang disimpan; coba lagi sebentar lagi." Transaksi di-rollback; tanpa audit.
- Penjadwal: item yang tertahan dihitung `gagal`, dicoba lagi pada jadwal berikutnya.

## 4. Skenario balapan (diuji dengan DUA koneksi MySQL)

`tests/Feature/Transaksi/KonkurensiTransaksiTest.php`. Koneksi kedua membuka transaksi, menjalankan operasi A lewat service dan menahan kuncinya. Koneksi utama menjalankan operasi B (`innodb_lock_wait_timeout = 1`) dan harus **tertahan** (≥ 0,9 detik, galat "coba lagi", tanpa perubahan). Setelah A commit, B diulang dan harus mengambil keputusan benar dari data ter-commit.

| # | Skenario | Pemenang | Yang kalah |
| --- | --- | --- | --- |
| A | Dua Admin meminjamkan eksemplar yang sama | peminjaman pertama | ditolak "masih dalam peminjaman Aktif" |
| B | Dua persetujuan berbeda untuk eksemplar yang sama | persetujuan pertama | ditolak "tidak tersedia (status Dipesan)" |
| C | Realisasi vs kedaluwarsa | kedaluwarsa (setelah batas) / realisasi (sebelum batas) | realisasi ditolak / kedaluwarsa no-op |
| D | Pembatalan vs kedaluwarsa | kedaluwarsa | pembatalan ditolak (status Kedaluwarsa) |
| E/F | Persetujuan vs penonaktifan (F-01), kedua arah | yang pertama mengunci member | penonaktifan ditolak F-01 / persetujuan ditolak (anggota Nonaktif) |
| G | Dua pengembalian loan yang sama (anggota Nonaktif) | pengembalian pertama | ditolak "sudah dikembalikan" |
| H | Dua anggota mengajukan eksemplar yang sama | keduanya Diajukan (perilaku teruji) | — |
| I | Penjadwal memproses booking yang sama dua kali | proses pertama | proses kedua: gagal tertahan, lalu no-op; satu audit |
| K | Kedaluwarsa reservasi lama vs persetujuan baru | kedaluwarsa, lalu persetujuan baru | persetujuan tertahan, lalu berhasil; satu reservasi |

J (penjadwal dimulai ulang setelah kegagalan sebagian) diuji di `KedaluwarsaBookingTest` (sisa booking diproses, yang selesai tidak diulang).

## 5. Batasan pembuktian

- Deadlock (1213) yang sebenarnya tidak disimulasikan secara deterministik; perilaku ulang-coba bergantung pada `DB::transaction` Laravel. Urutan kunci yang seragam membuat deadlock antar-operasi di atas tidak terjadi pada skenario yang diuji.
- Test dua koneksi berjalan dalam satu proses PHP (koneksi kedua menahan kunci, koneksi utama menunggu); ini membuktikan saling-eksklusi dan keputusan setelah commit, bukan throughput beban tinggi.
