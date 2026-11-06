# Data Mock Terpusat (Phase 2)

Dokumen pengembang. Data mock adalah satu-satunya sumber data untuk seluruh halaman prototipe statis. Tidak ada database, API, maupun localStorage.

- Lokasi: `resources/js/data/mock/`
- Titik masuk: `resources/js/data/mock/index.js` (`DATA_MOCK`, fungsi relasi, ringkasan dashboard, validator)
- Tanggal acuan ("hari ini"): `TANGGAL_DEMO = 2026-11-07` di `resources/js/config/demo.js`
- Tes: `npm run test:data` (test runner bawaan Node, `tests/js/mock-data.test.js`)

## Struktur file

| File | Isi | Ekspor |
| --- | --- | --- |
| `users.js` | Akun pengguna (Admin + Anggota) | `PENGGUNA`, `ID_ADMIN_DEMO` |
| `members.js` | Anggota | `ANGGOTA`, `KODE_ANGGOTA_DEMO` |
| `books.js` | Buku (bibliografi) | `BUKU` |
| `bookCopies.js` | Eksemplar buku | `EKSEMPLAR` |
| `loans.js` | Peminjaman | `PEMINJAMAN` |
| `returns.js` | Pengembalian | `PENGEMBALIAN` |
| `bookings.js` | Booking pinjam buku | `BOOKING` |
| `relasi.js` | Fungsi relasi (cari, detail, ketersediaan, status turunan) | `cariAnggota`, `detailPeminjaman`, `ketersediaanBuku`, `statusEksemplarDariTransaksi`, … |
| `dashboard.js` | Ringkasan dashboard, dihitung dari data | `ringkasanAdmin`, `ringkasanAnggota` |
| `validasi.js` | Pemeriksa konsistensi | `periksaKonsistensi` |
| `util.js` | `bekukan` (data awal tidak bisa diubah), `salin` (salinan untuk simulasi) | |
| `index.js` | Titik masuk tunggal | `DATA_MOCK` + semua di atas |

Fungsi relasi dan ringkasan menerima `data` sebagai parameter pertama, misalnya `ringkasanAdmin(DATA_MOCK)`. Simulasi transaksi di Phase 4–5 cukup bekerja pada `salin(DATA_MOCK)` lalu memanggil `periksaKonsistensi` untuk memastikan hasilnya tetap konsisten.

## Pemetaan field

Nama file mengikuti SRS 6.1. Nama field mengikuti Class Diagram, ditambah field yang hanya ada di SRS 6.1 atau FR.

| Entitas | Field mock | SRS 6.1 | Class Diagram | Label UI |
| --- | --- | --- | --- | --- |
| Pengguna | `idPengguna`, `username`, `nama`, `peran`, `statusAkun` | users.id, username, role, is_active | Pengguna.idPengguna, username, nama, statusAkun | — |
| Anggota | `kodeAnggota` | members.member_code | Anggota.idAnggota | Kode Anggota |
| | `idPengguna` | members.user_id | — | — |
| | `nama`, `kontak`, `status` | name, contact, status | Pengguna.nama, kontak, statusAnggota | Nama, Kontak, Status |
| Buku | `kodeBuku`, `judul`, `penulis`, `penerbit`, `tahunTerbit`, `kategori` | book_code, title, author, publisher, year, category | Buku.* (nama sama) | Kode Buku, Judul, Penulis, Penerbit, Tahun Terbit, Kategori |
| Eksemplar | `kodeEksemplar`, `kodeBuku`, `status` | book_copies.copy_code, book_id, status | EksemplarBuku.idEksemplar, statusEksemplar | Kode Eksemplar, Status |
| Peminjaman | `idPeminjaman`, `kodeAnggota`, `kodeEksemplar` | loans.id, member_id, copy_id | Peminjaman.idPeminjaman | ID Peminjaman |
| | `tanggalPinjam`, `tanggalJatuhTempo` | loaned_at, due_at | tanggalPinjam (jatuh tempo hanya di SRS) | Tanggal Pinjam, Tanggal Jatuh Tempo |
| | `diprosesOleh`, `status` | staff_id, status | statusPeminjaman | (petugas pemroses), Status |
| Pengembalian | `idPengembalian`, `idPeminjaman`, `tanggalKembali`, `diprosesOleh` | returns.id, loan_id, returned_at, staff_id | Pengembalian.idPengembalian, tanggalKembali | Tanggal Kembali |
| Booking | `idBooking`, `kodeAnggota`, `kodeEksemplar`, `tanggalBooking`, `status` | bookings.id, member_id, copy_id, submitted_at, status | BookingPeminjaman.idBooking, tanggalBooking, statusBooking | |
| | `tanggalKeputusan`, `diputuskanOleh` | decided_at, staff_id | — | |
| | `alasanPenolakan` | (FR-033) | — | Alasan Penolakan |
| | `idPeminjaman` | (FR-035, State Machine "kaitkanTransaksi") | — | |

Field yang **tidak** ditambahkan karena tidak ada di SRS atau UML: email, program studi, ISBN, kode rak, tanggal daftar, denda, perpanjangan, tanggal pembatalan booking.

## Jumlah rekaman

| Entitas | Jumlah | Rincian status |
| --- | --- | --- |
| Pengguna | 11 | 1 Admin, 10 Anggota |
| Anggota | 10 | Aktif 8 · Nonaktif 2 (AG-007, AG-009) |
| Buku | 10 | 3 buku 1 eksemplar (BK-005, BK-007, BK-009) · 7 buku 2–3 eksemplar · 2 buku tanpa eksemplar Tersedia (BK-005, BK-007) |
| Eksemplar | 18 | Tersedia 11 · Dipesan 2 · Dipinjam 5 |
| Peminjaman | 10 | Aktif 5 · Selesai 5 |
| Pengembalian | 5 | satu untuk setiap peminjaman Selesai |
| Booking | 8 | Diajukan 2 · Disetujui 2 · Ditolak 1 · Dibatalkan 2 · Direalisasikan 1 |

Ringkasan Dashboard Admin (`ringkasanAdmin`): 10 anggota (8 Aktif), 10 buku, 18 eksemplar, 5 peminjaman Aktif, 2 booking Diajukan, 2 booking Disetujui menunggu realisasi.

Ringkasan Dashboard Anggota AG-001 (`ringkasanAnggota`): 1 booking aktif, 1 peminjaman Aktif, 2 riwayat Selesai.

## Alur transaksi (kronologis)

| Tanggal | Kejadian | Dampak eksemplar |
| --- | --- | --- |
| 15 Okt | PJ-001: AG-001 meminjam EK-001 (Algoritma Dasar) | EK-001 Dipinjam |
| 16 Okt | PJ-002: AG-007 meminjam EK-016 · BP-001: AG-002 booking EK-002 (Basis Data) | EK-016 Dipinjam |
| 17 Okt | BP-001 Disetujui | EK-002 **Dipesan** (sampai sekarang) |
| 18 Okt | KB-001: PJ-001 dikembalikan | EK-001 Tersedia |
| 19 Okt | BP-002: AG-004 booking EK-006 | (Diajukan tidak mengunci) |
| 20 Okt | PJ-003: AG-003 meminjam EK-006 · BP-002 **Ditolak** (eksemplar sudah dipinjam) | EK-006 Dipinjam |
| 21 Okt | PJ-004: AG-004 meminjam EK-008 | EK-008 Dipinjam |
| 24 Okt | PJ-005: AG-001 meminjam EK-011 (Kalkulus I) | EK-011 Dipinjam |
| 27 Okt | KB-002: PJ-002 dikembalikan | EK-016 Tersedia |
| 28 Okt | KB-003: PJ-004 dikembalikan · BP-003: AG-001 booking EK-004, lalu **Dibatalkan** | EK-008 Tersedia |
| 30–31 Okt | BP-004: AG-001 booking EK-017 (SIM), Disetujui | EK-017 Dipesan |
| 1 Nov | KB-004: PJ-005 dikembalikan · BP-005: AG-008 booking EK-009 | EK-011 Tersedia |
| 2 Nov | PJ-006: AG-002 meminjam EK-003 · KB-005: PJ-003 dikembalikan · BP-005 Disetujui lalu **Dibatalkan** | EK-003 Dipinjam, EK-006 Tersedia, EK-009 Tersedia |
| 3 Nov | PJ-007: realisasi BP-004 → BP-004 **Direalisasikan** | EK-017 Dipinjam |
| 4 Nov | PJ-008: AG-005 meminjam EK-010 (satu-satunya eksemplar Jaringan Komputer) | EK-010 Dipinjam |
| 5 Nov | PJ-009: AG-006 meminjam EK-014 · BP-006: AG-003 booking EK-013 | EK-014 Dipinjam |
| 6 Nov | BP-006 Disetujui · PJ-010: AG-008 meminjam EK-012 · BP-007: AG-001 booking EK-005 | EK-013 Dipesan, EK-012 Dipinjam |
| 7 Nov | BP-008: AG-010 booking EK-018 | (Diajukan) |

## Contoh relasi

- **PJ-001** → anggota AG-001 Nadia Rahmawati → eksemplar EK-001 → buku BK-001 "Algoritma Dasar" → pengembalian KB-001 (18 Okt) → status Selesai.
- **BP-004** (AG-001, EK-017, Direalisasikan) → peminjaman PJ-007 (Aktif, 3–17 Nov) → EK-017 Dipinjam → BK-010 "Sistem Informasi Manajemen".
- **BP-001** (AG-002, Disetujui) → EK-002 Dipesan → BK-002 "Basis Data" (2 eksemplar: EK-002 Dipesan, EK-005 Tersedia).
- **BK-005** "Jaringan Komputer" → 1 eksemplar (EK-010, Dipinjam oleh PJ-008), sehingga tidak ada eksemplar Tersedia.

## Penyesuaian terhadap UML (keputusan)

| Sumber UML | Isi UML | Di data mock | Alasan |
| --- | --- | --- | --- |
| Object Diagram 02 & 04 | `angg01` nama "Anggota A", idAnggota AG-001, idPengguna USR-002 | AG-001 **Nadia Rahmawati**, USR-002 | Nama dan kode demo Phase 1 dipertahankan; "Anggota A" adalah nama placeholder. Kode dan idPengguna sama. |
| Object Diagram 03 | `angg02` "Anggota B", AG-002, USR-003 | AG-002 **Rizky Pratama**, USR-003 | Sama seperti di atas. |
| Object Diagram 02 vs 04 | PJ-001 Aktif (snapshot 1) dan Selesai (snapshot 2) | PJ-001 **Selesai**, KB-001 18 Okt | Dataset menyimpan keadaan akhir per tanggal demo. Peminjaman Aktif memakai ID lain. |
| Object Diagram | `idEksemplar` | `kodeEksemplar` (EK-001, EK-002 dipertahankan) | Label UI "Kode Eksemplar" (SRS `copy_code`). |
| Class Diagram | `Anggota.idAnggota` | `kodeAnggota` | Keputusan Phase 0: label UI "Kode Anggota". |
| Class Diagram `Peminjaman` | tanpa jatuh tempo & petugas | `tanggalJatuhTempo`, `diprosesOleh` | SRS 6.1 `due_at`, `staff_id`, FR-024 (SRS diutamakan). |
| Class Diagram `BookingPeminjaman` | tanpa waktu/pelaku keputusan | `tanggalKeputusan`, `diputuskanOleh`, `alasanPenolakan`, `idPeminjaman` | SRS 6.1 `decided_at`, `staff_id`; FR-033; FR-035. |
| A04/S04 | "anggota valid", "stok tersedia" | anggota `Aktif` + eksemplar `Tersedia` | BR-01, FR-020 (SRS diutamakan). |
| State Machine 04 | `tolak [eksemplarTidakTersedia]` | BP-002 ditolak karena eksemplar sudah dipinjam | Contoh penolakan yang sejalan dengan guard UML dan FR-033. |
| Peran | SRS: Admin & Petugas | Semua `diprosesOleh`/`diputuskanOleh` = USR-001 (Admin) | Keputusan final dua peran (`docs/KEPUTUSAN_DESAIN.md`). |

## Aturan yang divalidasi (`periksaKonsistensi`)

1. Kode/ID unik dan berformat benar (USR-, AG-, BK-, EK-, PJ-, KB-, BP- + 3 digit); username unik.
2. Status hanya dari kosakata resmi (`resources/js/utils/status.js`); tidak ada "Menunggu".
3. Relasi valid: eksemplar → buku; buku punya ≥ 1 eksemplar; peminjaman → anggota & eksemplar; pengembalian → peminjaman (0..1); booking → anggota, eksemplar, peminjaman realisasi; pemroses/pemutus adalah Admin; nama anggota sama dengan akunnya.
4. Peminjaman Aktif ⇔ tanpa pengembalian; Selesai ⇔ punya tepat satu pengembalian. Maksimal satu peminjaman Aktif per eksemplar.
5. Status eksemplar = status turunan transaksi saat ini (Dipinjam / Dipesan / Tersedia). Eksemplar tidak boleh dipesan sekaligus dipinjam; maksimal satu booking Disetujui per eksemplar.
6. Booking: Diajukan tanpa keputusan; Disetujui/Ditolak/Direalisasikan punya waktu & pelaku keputusan; alasan penolakan hanya pada Ditolak; Direalisasikan ⇔ punya `idPeminjaman` dengan anggota & eksemplar yang sama dan tanggal pinjam ≥ tanggal keputusan; satu peminjaman berasal dari maksimal satu booking.
7. Transaksi berjalan (peminjaman Aktif, booking Diajukan/Disetujui) hanya untuk anggota Aktif; booking Diajukan menunjuk eksemplar Tersedia.
8. Tanggal valid; tanggal kejadian ≤ tanggal demo; jatuh tempo > tanggal pinjam; kembali ≥ pinjam; keputusan ≥ pengajuan; periode peminjaman pada eksemplar yang sama tidak tumpang tindih.

Kondisi tabel kosong untuk halaman nanti: filter tanpa hasil (mis. pencarian kata kunci yang tidak ada) atau anggota tanpa transaksi (AG-009 tidak punya peminjaman maupun booking).
