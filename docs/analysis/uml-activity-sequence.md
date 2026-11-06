> **Catatan dokumen — hasil transkripsi**
>
> Dokumen ini adalah **transkripsi teks** dari 18 gambar Activity Diagram (A01–A09) dan Sequence Diagram (S01–S09) di `docs/uml/`. Transkripsi dibuat oleh asisten AI (Claude) pada 7 November 2026, lalu sampel S04 diperiksa ulang secara manual terhadap gambar aslinya.
>
> - Sumber otoritatif tetap **gambar asli** di `docs/uml/` dan **SRS v1.1** (`docs/requirements/Tugas_2_SRS_FINAL.pdf`).
> - Bila diagram berbeda dengan SRS v1.1, **SRS v1.1 yang dipakai** (keputusan pengguna, lihat `docs/KEPUTUSAN_DESAIN.md`).
> - Penomoran langkah (`#`) ditambahkan saat transkripsi; diagram aslinya tidak bernomor.
> - Penjelasan ditulis dalam bahasa Inggris; seluruh label diagram dikutip apa adanya (bahasa Indonesia).

# SIM Perpustakaan: Activity and Sequence Diagram Transcription

Source: `docs/uml/` (18 PNG files, draw.io exports).

## General notes (apply to all diagrams)

- **Legibility:** all text in all 18 images was readable at native resolution. Nothing was guessed. In some sequence diagrams the dashed actor lifeline crosses the italic guard text (for example "[lih|at / cari]"), but the text can still be read.
- **Numbering:** none of the diagrams numbers its nodes or messages. The `#` columns below are numbers I added in reading order.
- **Activity legend (footer, verbatim):** `● Awal ☐ Aktivitas ◇ Keputusan ◎ Akhir | [kondisi] = guard`
- **Sequence legend (footer, verbatim):** `Solid = synchronous call | Dashed = return | alt / opt = combined fragment UML`
- **Activity swimlanes:** each diagram has two lanes, the actor (left) and `Sistem` (right). Rejection or alternate-end nodes are drawn with a brown or orange border and a light fill.
- **Sequence lifelines:** every sequence diagram uses the same four lifelines:
  - actor (name varies)
  - `«boundary» :Antarmuka`
  - `«control» :Layanan`
  - `«entity» :Basis Data`
  - No concrete table or entity names appear on any lifeline.
- **Status vocabulary:** no diagram uses any capitalised status value from the reference vocabulary: Aktif/Nonaktif, Tersedia/Dipesan/Dipinjam, Aktif/Selesai, or Diajukan/Disetujui/Ditolak/Dibatalkan/Direalisasikan. The words "Menunggu" and "Dikembalikan" also do not appear anywhere. State changes are only described generically, for example "ubah status buku".

---

## A01 - Login (Activity)

Title: `ACTIVITY DIAGRAM 01 - LOGIN`

**Swimlanes:** `Admin / Anggota` | `Sistem`

**Flow:**

| # | Lane | Node |
|---|------|------|
| 0 | Admin / Anggota | (start) |
| 1 | Admin / Anggota | Buka halaman login |
| 2 | Sistem | Tampilkan formulir login |
| 3 | Admin / Anggota | Masukkan identitas dan kata sandi |
| 4 | Sistem | Periksa akun dan kata sandi |
| 5 | Sistem | Decision **"Kredensial valid?"** |
| 5a | Sistem | `[tidak valid]` → **Tampilkan pesan gagal login** → (end) |
| 5b | Sistem | `[valid]` → Buat sesi pengguna → Tampilkan dashboard sesuai peran → (end) |

**UI elements:**
- Pages and forms: "halaman login", "formulir login", "dashboard sesuai peran"
- Input fields: "identitas", "kata sandi"
- Messages: "pesan gagal login" (the exact text is not given)

**State changes:** a session is created ("Buat sesi pengguna").

**Entities and fields:** akun (identitas, kata sandi); sesi pengguna; peran.

## S01 - Login (Sequence)

Title: `SEQUENCE DIAGRAM 01 - LOGIN`, frame `sd Login`

**Lifelines:** `Admin / Anggota` (actor), `«boundary» :Antarmuka`, `«control» :Layanan`, `«entity» :Basis Data`

| # | From → To | Type | Message |
|---|-----------|------|---------|
| 1 | Admin / Anggota → Antarmuka | call | bukaFormLogin() |
| 2 | Antarmuka → Admin / Anggota | return | formLogin |
| 3 | Admin / Anggota → Antarmuka | call | kirimKredensial(identitas,sandi) |
| 4 | Antarmuka → Layanan | call | autentikasi(identitas,sandi) |
| 5 | Layanan → Basis Data | call | cariAkun(identitas) |
| 6 | Basis Data → Layanan | return | akun dan hash sandi |
| alt | `[kredensial valid]` | | |
| 7 | Layanan → Antarmuka | return | sesi dan peran |
| 8 | Antarmuka → Admin / Anggota | return | dashboard sesuai peran |
| alt | `[kredensial tidak valid]` | | |
| 9 | Layanan → Antarmuka | return | autentikasi ditolak |
| 10 | Antarmuka → Admin / Anggota | return | pesan login gagal |

**Entities and fields:** akun (identitas, sandi stored as a "hash sandi"); sesi; peran.

**Inconsistencies (A01 / S01):**
- The failure message is worded "pesan gagal login" in A01 but "pesan login gagal" in S01.
- Neither diagram checks the member's status (Aktif/Nonaktif) at login. Only the credentials are checked.
- The diagrams do not say what "identitas" is (username, email or nomor anggota).

---

## A02 - Kelola Data Anggota (Activity)

Title: `ACTIVITY DIAGRAM 02 - KELOLA DATA ANGGOTA`

**Swimlanes:** `Admin` | `Sistem`

| # | Lane | Node |
|---|------|------|
| 1 | Admin | Buka menu Data Anggota |
| 2 | Sistem | Tampilkan daftar anggota |
| 3 | Admin | Pilih tambah / ubah / cari |
| 4 | Sistem | Decision **"Perlu menyimpan perubahan?"** |
| 4a | Sistem | `[tidak]` → **Tampilkan hasil pencarian / detail** → (end) |
| 4b | (arrow to Admin) | `[ya]` → Isi / ubah formulir anggota (Admin lane) |
| 5 | Sistem | Validasi data anggota |
| 6 | Sistem | Decision **"Data anggota valid?"** |
| 6a | Sistem | `[tidak valid]` → **Tampilkan pesan validasi** → (end) |
| 6b | Sistem | `[valid]` → Simpan perubahan ke basis data → Tampilkan konfirmasi berhasil → (end) |

**UI elements:**
- Menu: "Data Anggota"
- Page: "daftar anggota"
- Actions: "tambah", "ubah", "cari"
- Form: "formulir anggota"
- Views: "hasil pencarian / detail"
- Messages: "pesan validasi", "konfirmasi berhasil"

**State changes:** member data is created or updated. No status value is mentioned.

## S02 - Kelola Data Anggota (Sequence)

Title: `SEQUENCE DIAGRAM 02 - KELOLA DATA ANGGOTA`, frame `sd Kelola Data Anggota`

**Lifelines:** `Admin`, `«boundary» :Antarmuka`, `«control» :Layanan`, `«entity» :Basis Data`

| # | From → To | Type | Message |
|---|-----------|------|---------|
| 1 | Admin → Antarmuka | call | bukaMenuAnggota() |
| 2 | Antarmuka → Layanan | call | ambilDaftarAnggota() |
| 3 | Layanan → Basis Data | call | queryAnggota() |
| 4 | Basis Data → Layanan | return | daftar anggota |
| 5 | Layanan → Antarmuka | return | daftar anggota |
| 6 | Antarmuka → Admin | return | tampilkan daftar |
| alt | `[lihat / cari]` | | |
| 7 | Admin → Antarmuka | call | cariAnggota(kataKunci) |
| 8 | Antarmuka → Layanan | call | cariAnggota(kataKunci) |
| 9 | Layanan → Basis Data | call | queryAnggota(kataKunci) |
| 10 | Basis Data → Layanan | return | hasil pencarian |
| 11 | Layanan → Antarmuka | return | hasil pencarian |
| 12 | Antarmuka → Admin | return | tampilkan hasil |
| alt | `[tambah / ubah]` | | |
| 13 | Admin → Antarmuka | call | kirimFormAnggota(data) |
| 14 | Antarmuka → Layanan | call | validasiAnggota(data) |
| nested alt | `[data valid]` | | |
| 15 | Layanan → Basis Data | call | simpanAnggota(data) |
| 16 | Basis Data → Layanan | return | tersimpan |
| 17 | Layanan → Antarmuka | return | operasi berhasil |
| 18 | Antarmuka → Admin | return | konfirmasi berhasil |
| nested alt | `[data tidak valid]` | | |
| 19 | Layanan → Antarmuka | return | kesalahan validasi |
| 20 | Antarmuka → Admin | return | tampilkan kesalahan |

**UI elements:** search input "kataKunci".

**Entities and fields:** anggota. The fields are only referred to as "data"; no individual member fields are named.

**Inconsistencies (A02 / S02):**
- No delete or "hapus" action, and no action to activate or deactivate a member (Aktif/Nonaktif).
- The validation rules are not specified, and there is no explicit duplicate check.
- In A02, the decision "Perlu menyimpan perubahan?" is the Admin's choice but is drawn in the Sistem lane.
- A02 has no step where the Admin enters a search keyword, while S02 sends cariAnggota(kataKunci).
- The error message is called "pesan validasi" in A02 but "kesalahan validasi" / "tampilkan kesalahan" in S02.

---

## A03 - Kelola Data Buku (Activity)

Title: `ACTIVITY DIAGRAM 03 - KELOLA DATA BUKU`

**Swimlanes:** `Admin` | `Sistem`

| # | Lane | Node |
|---|------|------|
| 1 | Admin | Buka menu Data Buku |
| 2 | Sistem | Tampilkan daftar dan pencarian buku |
| 3 | Admin | Pilih tambah / ubah / cari |
| 4 | Sistem | Decision **"Perlu menyimpan perubahan?"** |
| 4a | Sistem | `[tidak]` → **Tampilkan hasil pencarian / detail** → (end) |
| 4b | (arrow to Admin) | `[ya]` → Isi / ubah formulir buku (Admin lane) |
| 5 | Sistem | Validasi kode dan data buku |
| 6 | Sistem | Decision **"Data buku valid?"** |
| 6a | Sistem | `[tidak valid]` → **Tampilkan pesan validasi** → (end) |
| 6b | Sistem | `[valid]` → Simpan perubahan ke basis data → Tampilkan konfirmasi berhasil → (end) |

**UI elements:**
- Menu: "Data Buku"
- Page: "daftar dan pencarian buku"
- Actions: "tambah", "ubah", "cari"
- Form: "formulir buku"
- Views: "hasil pencarian / detail"
- Messages: "pesan validasi", "konfirmasi berhasil"

**Entities and fields:** buku (kode, plus other unspecified "data buku").

## S03 - Kelola Data Buku (Sequence)

Title: `SEQUENCE DIAGRAM 03 - KELOLA DATA BUKU`, frame `sd Kelola Data Buku`

**Lifelines:** `Admin`, `«boundary» :Antarmuka`, `«control» :Layanan`, `«entity» :Basis Data`

| # | From → To | Type | Message |
|---|-----------|------|---------|
| 1 | Admin → Antarmuka | call | bukaMenuBuku() |
| 2 | Antarmuka → Layanan | call | ambilDaftarBuku() |
| 3 | Layanan → Basis Data | call | queryBuku() |
| 4 | Basis Data → Layanan | return | daftar buku |
| 5 | Layanan → Antarmuka | return | daftar buku |
| 6 | Antarmuka → Admin | return | tampilkan daftar |
| alt | `[lihat / cari]` | | |
| 7 | Admin → Antarmuka | call | cariBuku(kataKunci) |
| 8 | Antarmuka → Layanan | call | cariBuku(kataKunci) |
| 9 | Layanan → Basis Data | call | queryBuku(kataKunci) |
| 10 | Basis Data → Layanan | return | hasil pencarian |
| 11 | Layanan → Antarmuka | return | hasil pencarian |
| 12 | Antarmuka → Admin | return | tampilkan hasil |
| alt | `[tambah / ubah]` | | |
| 13 | Admin → Antarmuka | call | kirimFormBuku(data) |
| 14 | Antarmuka → Layanan | call | validasiBuku(data) |
| nested alt | `[data valid]` | | |
| 15 | Layanan → Basis Data | call | simpanBuku(data) |
| 16 | Basis Data → Layanan | return | tersimpan |
| 17 | Layanan → Antarmuka | return | operasi berhasil |
| 18 | Antarmuka → Admin | return | konfirmasi berhasil |
| nested alt | `[data tidak valid]` | | |
| 19 | Layanan → Antarmuka | return | kesalahan validasi |
| 20 | Antarmuka → Admin | return | tampilkan kesalahan |

**Inconsistencies (A03 / S03):**
- Neither diagram mentions **eksemplar** (copies) or their status. Copy management is absent from "Kelola Data Buku".
- No delete or "hapus" action.
- "Validasi kode dan data buku" suggests a check on the book code, but "duplikat" or uniqueness is never stated. S03 says only validasiBuku(data).
- The "Perlu menyimpan perubahan?" decision has the same lane-placement problem as A02.

---

## A04 - Peminjaman Buku (Activity)

Title: `ACTIVITY DIAGRAM 04 - PEMINJAMAN BUKU`

**Swimlanes:** `Admin` | `Sistem`

| # | Lane | Node |
|---|------|------|
| 1 | Admin | Buka menu Peminjaman Buku |
| 2 | Sistem | Tampilkan formulir peminjaman |
| 3 | Admin | Pilih anggota dan buku |
| 4 | Sistem | Periksa keabsahan anggota |
| 5 | Sistem | Decision **"Anggota valid?"** |
| 5a | Sistem | `[tidak]` → **Tolak transaksi: anggota tidak valid** → (end) |
| 5b | Sistem | `[ya]` → Periksa ketersediaan buku |
| 6 | Sistem | Decision **"Buku tersedia?"** |
| 6a | Sistem | `[tidak]` → **Tolak transaksi: buku tidak tersedia** → (end) |
| 6b | Sistem | `[ya]` → Catat peminjaman dan ubah status buku → Tampilkan bukti peminjaman → (end) |

**UI elements:**
- Menu: "Peminjaman Buku"
- Form: "formulir peminjaman", with selections "anggota" and "buku"
- Messages: "Tolak transaksi: anggota tidak valid", "Tolak transaksi: buku tidak tersedia"
- Output: "bukti peminjaman"

**State changes:** "Catat peminjaman" creates a peminjaman record, and "ubah status buku" changes the book's status. The target status is not named.

## S04 - Peminjaman Buku (Sequence)

Title: `SEQUENCE DIAGRAM 04 - PEMINJAMAN BUKU`, frame `sd Peminjaman Buku`

**Lifelines:** `Admin`, `«boundary» :Antarmuka`, `«control» :Layanan`, `«entity» :Basis Data`

| # | From → To | Type | Message |
|---|-----------|------|---------|
| 1 | Admin → Antarmuka | call | pilihAnggotaDanBuku() |
| 2 | Antarmuka → Layanan | call | buatPeminjaman(anggota,buku) |
| 3 | Layanan → Basis Data | call | cekAnggotaDanStok() |
| 4 | Basis Data → Layanan | return | anggota dan stok buku |
| alt | `[anggota valid dan stok tersedia]` | | |
| 5 | Layanan → Basis Data | call | simpanPinjamDanStatusBuku() |
| 6 | Basis Data → Layanan | return | transaksi tersimpan |
| 7 | Layanan → Antarmuka | return | bukti peminjaman |
| 8 | Antarmuka → Admin | return | konfirmasi peminjaman |
| alt | `[anggota tidak valid / stok kosong]` | | |
| 9 | Layanan → Antarmuka | return | transaksi ditolak |
| 10 | Antarmuka → Admin | return | pesan alasan penolakan |

**Entities and fields:** anggota; buku (status, "stok"); peminjaman ("transaksi").

**Inconsistencies (A04 / S04):**
- The diagrams say "Anggota valid?" / "keabsahan anggota" rather than "Aktif". The vocabulary Aktif/Nonaktif is never used.
- The diagrams model availability as **"stok"** ("stok tersedia" / "stok kosong" / "anggota dan stok buku"). That is a quantity model, not per-copy **eksemplar** status (Tersedia/Dipesan/Dipinjam).
- The status change is shown generically ("ubah status buku", simpanPinjamDanStatusBuku()). The diagrams do not name eksemplar → Dipinjam or peminjaman → Aktif.
- A04 has two separate decisions with two different rejection texts. S04 merges them into one guard with one generic "pesan alasan penolakan".
- S04 has no message for opening the menu or showing "formulir peminjaman". It starts at pilihAnggotaDanBuku().
- Neither diagram checks for an existing booking (Dipesan) or turns a booking into a loan (Direalisasikan).
- Neither diagram shows a tanggal pinjam, jatuh tempo, or a limit on how many books a member can borrow.

---

## A05 - Pengembalian Buku (Activity)

Title: `ACTIVITY DIAGRAM 05 - PENGEMBALIAN BUKU`

**Swimlanes:** `Admin` | `Sistem`

| # | Lane | Node |
|---|------|------|
| 1 | Admin | Buka menu Pengembalian Buku |
| 2 | Sistem | Tampilkan daftar peminjaman |
| 3 | Admin | Pilih transaksi yang dikembalikan |
| 4 | Sistem | Periksa status transaksi |
| 5 | Sistem | Decision **"Peminjaman masih aktif?"** |
| 5a | Sistem | `[tidak]` → **Tolak pengembalian tidak valid** → (end) |
| 5b | Sistem | `[ya]` → Catat tanggal pengembalian → Perbarui ketersediaan buku → Tampilkan konfirmasi pengembalian → (end) |

**UI elements:**
- Menu: "Pengembalian Buku"
- Page: "daftar peminjaman"
- Action: select a "transaksi"
- Messages: "Tolak pengembalian tidak valid", "konfirmasi pengembalian"

**State changes:** tanggal pengembalian is recorded, and book availability is updated.

**Entities and fields:** peminjaman/transaksi (status, tanggal pengembalian); buku (ketersediaan).

## S05 - Pengembalian Buku (Sequence)

Title: `SEQUENCE DIAGRAM 05 - PENGEMBALIAN BUKU`, frame `sd Pengembalian Buku`

**Lifelines:** `Admin`, `«boundary» :Antarmuka`, `«control» :Layanan`, `«entity» :Basis Data`

| # | From → To | Type | Message |
|---|-----------|------|---------|
| 1 | Admin → Antarmuka | call | pilihTransaksiPengembalian() |
| 2 | Antarmuka → Layanan | call | prosesPengembalian(idPinjam) |
| 3 | Layanan → Basis Data | call | cariTransaksi(idPinjam) |
| 4 | Basis Data → Layanan | return | status transaksi |
| alt | `[peminjaman aktif]` | | |
| 5 | Layanan → Basis Data | call | simpanKembaliDanStatusBuku() |
| 6 | Basis Data → Layanan | return | pengembalian tersimpan |
| 7 | Layanan → Antarmuka | return | transaksi selesai |
| 8 | Antarmuka → Admin | return | konfirmasi pengembalian |
| alt | `[bukan peminjaman aktif]` | | |
| 9 | Layanan → Antarmuka | return | pengembalian ditolak |
| 10 | Antarmuka → Admin | return | pesan transaksi tidak aktif |

**Fields:** idPinjam.

**Inconsistencies (A05 / S05):**
- These terms agree with the reference: "aktif" for an active loan (A05, S05) and "transaksi selesai" (S05) for Selesai.
- The copy's change to Tersedia is not named. A05 says "Perbarui ketersediaan buku" and S05 says simpanKembaliDanStatusBuku().
- The rejection text is "Tolak pengembalian tidak valid" in A05 but "pesan transaksi tidak aktif" in S05.
- S05 has no messages for opening the menu or loading "daftar peminjaman".
- Neither diagram has a late-return or fine (denda) branch.
- The "already returned" case is covered only implicitly, by `[tidak]` / `[bukan peminjaman aktif]`.

---

## A06 - Kelola Laporan (Activity)

Title: `ACTIVITY DIAGRAM 06 - KELOLA LAPORAN`

**Swimlanes:** `Admin` | `Sistem`

| # | Lane | Node |
|---|------|------|
| 1 | Admin | Buka menu Laporan |
| 2 | Sistem | Tampilkan pilihan laporan |
| 3 | Admin | Pilih jenis dan periode laporan |
| 4 | Sistem | Ambil dan susun data laporan |
| 5 | Sistem | Tampilkan hasil laporan |
| 6 | Admin | Decision **"Cetak laporan?"** (this decision sits in the Admin lane) |
| 6a | → Sistem | `[tidak]` → **Selesai melihat laporan** → (end) |
| 6b | → Sistem | `[ya]` → Siapkan tampilan cetak |
| 7 | Admin | Cetak / simpan laporan → (end) |

**UI elements:**
- Menu: "Laporan"
- Page: "pilihan laporan"
- Filters: "jenis" and "periode" laporan
- View: "hasil laporan"
- Action: "Cetak / simpan laporan", with a "tampilan cetak" (print view)

**Entities and fields:** report data with jenis and periode parameters. The report types are not listed.

## S06 - Kelola Laporan (Sequence)

Title: `SEQUENCE DIAGRAM 06 - KELOLA LAPORAN`, frame `sd Kelola Laporan`

**Lifelines:** `Admin`, `«boundary» :Antarmuka`, `«control» :Layanan`, `«entity» :Basis Data`

| # | From → To | Type | Message |
|---|-----------|------|---------|
| 1 | Admin → Antarmuka | call | pilihJenisDanPeriodeLaporan() |
| 2 | Antarmuka → Layanan | call | mintaLaporan(jenis,periode) |
| 3 | Layanan → Basis Data | call | queryDataLaporan(jenis,periode) |
| 4 | Basis Data → Layanan | return | rekap data / data kosong |
| 5 | Layanan → Antarmuka | return | hasil laporan |
| 6 | Antarmuka → Admin | return | tampilkan laporan |
| opt | `[admin memilih cetak]` | | |
| 7 | Admin → Antarmuka | call | cetakLaporan() |
| 8 | Antarmuka → Layanan | call | siapkanTampilanCetak() |
| 9 | Layanan → Antarmuka | return | dokumen siap cetak |
| 10 | Antarmuka → Admin | return | tampilkan dialog cetak |

**Inconsistencies (A06 / S06):**
- S06 returns "rekap data / data kosong", but neither diagram has a branch or message for an empty report.
- "simpan" (save or export) appears only in A06. S06 shows only printing ("tampilkan dialog cetak").
- The `[tidak]` end node "Selesai melihat laporan" uses the alternate/error styling and sits in the Sistem lane, although it is a normal end.
- The jenis laporan values are not enumerated.
- S06 has no "Tampilkan pilihan laporan" message.

---

## A07 - Cari Buku (Activity)

Title: `ACTIVITY DIAGRAM 07 - CARI BUKU`

**Swimlanes:** `Anggota` | `Sistem`

| # | Lane | Node |
|---|------|------|
| 1 | Anggota | Buka fitur Cari Buku |
| 2 | Sistem | Tampilkan kotak pencarian |
| 3 | Anggota | Masukkan judul, kode, atau kategori |
| 4 | Sistem | Cari buku pada basis data |
| 5 | Sistem | Decision **"Buku ditemukan?"** |
| 5a | Sistem | `[tidak]` → **Tampilkan hasil kosong** → (end) |
| 5b | Sistem | `[ya]` → Tampilkan detail dan ketersediaan |
| 6 | Anggota | Lihat informasi buku → (end) |

**UI elements:**
- Feature: "Cari Buku"
- Input: "kotak pencarian", with search criteria "judul", "kode", "kategori"
- Results: "detail dan ketersediaan", "informasi buku"
- Empty state: "hasil kosong"

**Entities and fields:** buku (judul, kode, kategori, ketersediaan).

## S07 - Cari Buku (Sequence)

Title: `SEQUENCE DIAGRAM 07 - CARI BUKU`, frame `sd Cari Buku`

**Lifelines:** `Anggota`, `«boundary» :Antarmuka`, `«control» :Layanan`, `«entity» :Basis Data`

| # | From → To | Type | Message |
|---|-----------|------|---------|
| 1 | Anggota → Antarmuka | call | cariBuku(kataKunci) |
| 2 | Antarmuka → Layanan | call | pencarianBuku(kataKunci) |
| 3 | Layanan → Basis Data | call | queryBuku(kataKunci) |
| 4 | Basis Data → Layanan | return | data buku |
| alt | `[hasil ditemukan]` | | |
| 5 | Layanan → Antarmuka | return | daftar dan status buku |
| 6 | Antarmuka → Anggota | return | tampilkan hasil dan detail |
| alt | `[hasil kosong]` | | |
| 7 | Layanan → Antarmuka | return | hasil kosong |
| 8 | Antarmuka → Anggota | return | pesan buku tidak ditemukan |

**Inconsistencies (A07 / S07):**
- A07 lists three separate criteria (judul, kode, kategori). S07 uses a single kataKunci.
- The empty state is "Tampilkan hasil kosong" in A07 but "pesan buku tidak ditemukan" in S07.
- "status buku" / "ketersediaan" is shown in results, but the status values (Tersedia/Dipesan/Dipinjam) are not named.
- Only Anggota is the actor here; Admin is not shown using Cari Buku.
- S07 has no message for opening the feature or showing "kotak pencarian".

---

## A08 - Booking Pinjam Buku (Activity)

Title: `ACTIVITY DIAGRAM 08 - BOOKING PINJAM BUKU`

**Swimlanes:** `Anggota` | `Sistem`

| # | Lane | Node |
|---|------|------|
| 1 | Anggota | Pilih fitur Booking Pinjam Buku |
| 2 | Sistem | Tampilkan informasi buku |
| 3 | Anggota | Pilih buku dan ajukan booking |
| 4 | Sistem | Validasi anggota dan ketersediaan |
| 5 | Sistem | Decision **"Booking dapat diterima?"** |
| 5a | Sistem | `[tidak]` → **Tampilkan alasan penolakan** → (end) |
| 5b | Sistem | `[ya]` → Catat permintaan booking → Tampilkan status booking tercatat → (end) |

**UI elements:**
- Feature: "Booking Pinjam Buku"
- View: "informasi buku"
- Action: "ajukan booking"
- Messages: "alasan penolakan", "status booking tercatat"

**State changes:** a booking request is recorded ("Catat permintaan booking"). The status value is not named.

## S08 - Booking Pinjam Buku (Sequence)

Title: `SEQUENCE DIAGRAM 08 - BOOKING PINJAM BUKU`, frame `sd Booking Pinjam Buku`

**Lifelines:** `Anggota`, `«boundary» :Antarmuka`, `«control» :Layanan`, `«entity» :Basis Data`

| # | From → To | Type | Message |
|---|-----------|------|---------|
| 1 | Anggota → Antarmuka | call | pilihBukuDanAjukanBooking() |
| 2 | Antarmuka → Layanan | call | ajukanBooking(anggota,buku) |
| 3 | Layanan → Basis Data | call | cekAnggotaDanKetersediaan() |
| 4 | Basis Data → Layanan | return | status anggota dan buku |
| alt | `[permintaan memenuhi syarat]` | | |
| 5 | Layanan → Basis Data | call | simpanPermintaanBooking() |
| 6 | Basis Data → Layanan | return | booking tercatat |
| 7 | Layanan → Antarmuka | return | status booking |
| 8 | Antarmuka → Anggota | return | konfirmasi booking |
| alt | `[tidak memenuhi syarat]` | | |
| 9 | Layanan → Antarmuka | return | booking ditolak |
| 10 | Antarmuka → Anggota | return | tampilkan alasan penolakan |

**Entities and fields:** anggota (status); buku (status, ketersediaan); booking ("permintaan booking", status).

**Inconsistencies (A08 / S08):**
- The new booking's status is not named. "Catat permintaan booking" / "booking tercatat" presumably means **Diajukan**, but the word is never used.
- There is no step for an Admin to approve or reject a booking. Disetujui, Dibatalkan and Direalisasikan never appear, and the Admin actor is absent.
- S08's "booking ditolak" is an immediate system rejection at validation time. It is not the same as a booking status **Ditolak** set later, and the two could be confused.
- No step sets the copy to **Dipesan**.
- The syarat (conditions) are unspecified: "Validasi anggota dan ketersediaan", "status anggota dan buku". They do not say whether a book that is available or one that is on loan can be booked.
- The diagrams have no cancel action for the Anggota.
- A08 uses "Pilih fitur …" where the other activity diagrams use "Buka menu …".

---

## A09 - Lihat History Peminjaman (Activity)

Title: `ACTIVITY DIAGRAM 09 - LIHAT HISTORY PEMINJAMAN`

**Swimlanes:** `Anggota` | `Sistem`

| # | Lane | Node |
|---|------|------|
| 1 | Anggota | Buka History Peminjaman |
| 2 | Sistem | Kenali akun anggota dari sesi |
| 3 | Sistem | Ambil transaksi milik anggota |
| 4 | Sistem | Decision **"Riwayat tersedia?"** |
| 4a | Sistem | `[tidak]` → **Tampilkan keterangan belum ada riwayat** → (end) |
| 4b | Sistem | `[ya]` → Tampilkan daftar dan status transaksi |
| 5 | Anggota | Pilih dan lihat detail riwayat → (end) |

**UI elements:**
- Page: "History Peminjaman"
- List: "daftar dan status transaksi"
- Detail: "detail riwayat"
- Empty state: "keterangan belum ada riwayat"

**Entities and fields:** peminjaman/transaksi (status), filtered by the logged-in member's session.

## S09 - Lihat History Peminjaman (Sequence)

Title: `SEQUENCE DIAGRAM 09 - LIHAT HISTORY PEMINJAMAN`, frame `sd Lihat History Peminjaman`

**Lifelines:** `Anggota`, `«boundary» :Antarmuka`, `«control» :Layanan`, `«entity» :Basis Data`

| # | From → To | Type | Message |
|---|-----------|------|---------|
| 1 | Anggota → Antarmuka | call | bukaHistoryPeminjaman() |
| 2 | Antarmuka → Layanan | call | ambilRiwayat(idAnggotaSesi) |
| 3 | Layanan → Basis Data | call | queryPeminjamanMilikAnggota() |
| 4 | Basis Data → Layanan | return | daftar transaksi milik anggota |
| alt | `[riwayat tersedia]` | | |
| 5 | Layanan → Antarmuka | return | daftar dan status transaksi |
| 6 | Antarmuka → Anggota | return | tampilkan riwayat |
| alt | `[riwayat kosong]` | | |
| 7 | Layanan → Antarmuka | return | data kosong |
| 8 | Antarmuka → Anggota | return | tampilkan pesan belum ada riwayat |

**Fields:** idAnggotaSesi.

**Inconsistencies (A09 / S09):**
- "History" and "Riwayat" are used for the same thing.
- The empty-state message is "keterangan belum ada riwayat" in A09 but "pesan belum ada riwayat" in S09.
- The status values (Aktif/Selesai) are not named.
- The diagrams do not say whether bookings are included in the history.
- S09 has no message for "Pilih dan lihat detail riwayat".
