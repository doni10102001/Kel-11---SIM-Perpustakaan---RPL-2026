# UML Compliance Matrix — SIM Perpustakaan

Audit tanggal 7 November 2026. Gambar UML di `docs/uml/` diperiksa **langsung** untuk Use Case, Class, empat State Machine, dan tiga Object Diagram. Activity dan Sequence Diagram diperiksa melalui transkripsi `docs/analysis/uml-activity-sequence.md`, yang sampel S04-nya pernah dicek manual. Development Diagram tidak dibandingkan karena memuat arsitektur deployment yang di luar lingkup prototipe statis. Dokumen UML dan SRS **tidak diubah**.

## Klasifikasi perbedaan

| Kode | Arti |
| --- | --- |
| **V** | Melanggar kebutuhan yang disetujui |
| **D** | Perbedaan yang diizinkan secara eksplisit oleh `docs/KEPUTUSAN_DESAIN.md` (KD) |
| **U** | Inkonsistensi SRS ↔ UML yang belum diselesaikan di dokumen sumber. Implementasi mengikuti SRS v1.1 (hierarki sumber KD). |
| **S** | Sesuai |

Tidak ditemukan perbedaan berkategori **V**. Satu celah perilaku (F-01) terjadi di dalam ruang keputusan yang masih "belum final" (KD 4.5, 4.8), sehingga dicatat sebagai temuan MEDIUM, bukan pelanggaran kebutuhan yang telah disetujui.

## 1. Use Case Diagram

| Elemen UML | Implementasi | Kode | Catatan |
| --- | --- | --- | --- |
| Aktor **Admin** dan **Anggota** (tidak ada Petugas) | Peran `admin` dan `anggota` (`NavigasiDemo::PERAN`) | S / D | Sejalan dengan UML tetapi berbeda dengan SRS §2.3 (Admin + Petugas). KD "Keputusan final peran". |
| Admin: Kelola Data Anggota, Kelola Data Buku, Peminjaman Buku, Pengembalian Buku, Kelola Laporan | Menu Data Anggota, Data Buku & Eksemplar, Peminjaman Buku, Pengembalian Buku, Laporan | S | — |
| Kelola Laporan «extend» Lihat Laporan, Cetak Laporan | "Tampilkan Laporan", "Cetak Laporan" | S | — |
| Anggota: Cari Buku, Booking Pinjam Buku, Lihat History Peminjaman | Menu Cari Buku, Booking Pinjam Buku (Ajukan/Status), History Peminjaman | S | — |
| Semua UC «include» Login | Halaman `/login` (simulasi). Rute tidak dilindungi. | D | Login hanya pratinjau (KD "Akun pratinjau"). Panah Booking → Login di gambar tidak berlabel «include». |
| **Tidak ada** UC verifikasi/realisasi booking untuk Admin | Admin punya Verifikasi Booking dan Realisasi Booking | U | SRS UC-08 "Anggota; verifikasi Petugas", FR-026, FR-033–035. Use Case Diagram perlu ditambah. |
| Tidak ada UC Kelola Pengguna/Role | Tidak dibuat | S / D | SRS ACM mencantumkan "Kelola pengguna dan role" (Admin). Tidak dibangun (KD Phase 0 #1). |

## 2. Activity & Sequence Diagram (A01–A09, S01–S09)

| Diagram | Alur UML | Implementasi | Kode | Catatan |
| --- | --- | --- | --- | --- |
| A01/S01 Login | Periksa kredensial → gagal: pesan gagal login; valid: buat sesi → dashboard sesuai peran | Pesan generik "Gagal masuk"; dashboard per peran | D | Tidak ada pemeriksaan hash sandi atau sesi (lingkup statis) |
| A02/S02 Kelola Anggota | Daftar → tambah/ubah/cari → validasi → simpan → konfirmasi / pesan validasi | Index + Form, validasi inline, banner sukses | S | Implementasi menambah aksi aktif/nonaktif (FR-022) yang tidak ada di diagram (U) |
| A03/S03 Kelola Buku | Idem, tanpa eksemplar | Form buku + baris eksemplar | U | Diagram tidak memodelkan eksemplar. SRS FR-020/021 mewajibkannya. |
| A04/S04 Peminjaman | Pilih anggota & buku → "Anggota valid?" → "Buku tersedia?" → catat + ubah status → bukti | Pesan "Anggota tidak valid: …" / "Buku tidak tersedia: …"; kartu "Bukti Peminjaman" | S / U | UML memakai model **stok**. Implementasi memakai status per eksemplar (BR-01, FR-020; KD "Perbedaan SRS vs UML"). Jatuh tempo dan petugas tidak ada di diagram. |
| A05/S05 Pengembalian | Daftar peminjaman → "masih aktif?" → catat tanggal → perbarui ketersediaan → konfirmasi | Tab Peminjaman Aktif, dialog, banner "Konfirmasi pengembalian" | S | Penolakan "tidak aktif"/"sudah dikembalikan" ada di validasi aksi |
| A06/S06 Laporan | Pilih jenis & periode → tampilkan → opt cetak | Jenis + periode → Tampilkan → Cetak (dialog browser) | S | "simpan" di A06 tidak diwujudkan sebagai ekspor berkas (TBD-07). Keadaan kosong punya empty state. |
| A07/S07 Cari Buku | Kata kunci → ditemukan? → detail & ketersediaan / hasil kosong | Kata kunci + filter kategori; empty state "Buku tidak ditemukan"; panel detail | S | — |
| A08/S08 Booking | Ajukan → validasi anggota & ketersediaan → catat / alasan penolakan | Ajukan → validasi → status Diajukan; pesan "Booking ditolak: …" | S / U | Diagram berhenti pada pengajuan. Persetujuan, penolakan, pembatalan, dan realisasi diambil dari FR-031–035 dan State Machine 04. |
| A09/S09 History | Kenali anggota dari sesi → ambil transaksi miliknya → kosong / daftar → detail | Data AG-001 dari akun demo; empty state "Belum ada riwayat peminjaman"; panel "Detail Riwayat" | S / D | Identitas dari konfigurasi demo, bukan sesi |

## 3. Class Diagram

| Elemen UML | Implementasi (`resources/js/data/mock/`) | Kode | Catatan |
| --- | --- | --- | --- |
| `Pengguna` (idPengguna, nama, username, passwordHash, statusAkun) | `users.js`: idPengguna, username, nama, peran, statusAkun | D | passwordHash tidak ada (tanpa autentikasi). `peran` ditambahkan dari SRS 6.1 `role`. |
| `Admin` ⊂ Pengguna | Pengguna `peran: 'admin'` (USR-001) | S | — |
| `Anggota` ⊂ Pengguna (idAnggota, kontak, statusAnggota) | `members.js`: kodeAnggota, idPengguna, nama, kontak, status | U / D | UML: setiap Anggota adalah Pengguna. SRS 6.1: `members.user_id?` opsional. Anggota baru dibuat tanpa akun (KD 3.5). `idAnggota` → "Kode Anggota" (KD Phase 0 #2). |
| `Buku` 1 ◆— 1..* `EksemplarBuku` | Buku wajib ≥1 eksemplar (`validasiMaster.js:123`) | S | — |
| `EksemplarBuku` (idEksemplar, statusEksemplar) | kodeEksemplar, kodeBuku, status | S | idEksemplar → "Kode Eksemplar" (SRS `copy_code`) |
| `Peminjaman` (idPeminjaman, tanggalPinjam, statusPeminjaman) | + tanggalJatuhTempo, diprosesOleh | U | Field tambahan berasal dari SRS 6.1 `due_at`, `staff_id`, FR-024 |
| `Peminjaman` 1 — 0..1 `Pengembalian` | `periksaKonsistensi` aturan 4 | S | — |
| `Pengembalian` (idPengembalian, tanggalKembali) | + idPeminjaman, diprosesOleh | U | SRS 6.1 `staff_id`, FR-028 |
| `BookingPeminjaman` (idBooking, tanggalBooking, statusBooking; ajukan, batalkan, realisasikan) | + tanggalKeputusan, diputuskanOleh, alasanPenolakan, idPeminjaman | U | Class tidak punya operasi **setujui/tolak** maupun atribut keputusan. SRS FR-033, 6.1 `decided_at`, `staff_id`. |
| `Admin` operasi: kelolaDataBuku, kelolaDataAnggota, catatPeminjaman, catatPengembalian, kelolaLaporan | Semua tersedia + verifikasi/realisasi booking | U | Tidak ada operasi verifikasi booking di Class Diagram |
| `Laporan` (jenisLaporan, periodeAwal[0..1], periodeAkhir[0..1]; tampilkan, cetak) | `laporan.js` (virtual, tidak disimpan) | S | Sesuai SRS 6.1 "reports [virtual]" |

## 4. State Machine Diagram

### 01 Sesi Pengguna

| Transisi | Implementasi | Kode |
| --- | --- | --- |
| Belum Login —login [kredensialTidakValid]→ Belum Login | Pesan "Gagal masuk" | S (simulasi) |
| Belum Login —login [kredensialValid] / buatSesi→ Terautentikasi | Navigasi ke dashboard. Tidak ada objek sesi. | D |
| Terautentikasi —logout / akhiriSesi→ Berakhir | Dialog Keluar → `/login`. Rute tetap dapat diakses. | D |
| Terautentikasi —sesiTidakValid→ Berakhir | Tidak diimplementasikan | D (lingkup statis; FR-019 NOT COMPLIANT) |

### 02 Transaksi Peminjaman

| Transisi | Implementasi | Kode |
| --- | --- | --- |
| ● → Aktif (setelah peminjaman tercatat) | `catatPeminjaman`, `realisasikanBooking` | S |
| Aktif —pengembalianTersimpan / tandaiBukuTersedia→ Selesai | `prosesPengembalian` (atomik) | S |
| Aktif —pengembalianGagal / statusTidakBerubah→ Aktif | Draf + `periksaKonsistensi`; gagal berarti tanpa perubahan (T-TRX "Atomisitas", T-AUD D2) | S |
| Selesai stabil, riwayat dipertahankan | Tidak ada aksi hapus (KD 3.7) | S |

### 03 Status Eksemplar Buku

| Transisi | Implementasi | Kode |
| --- | --- | --- |
| ● → Tersedia | Eksemplar baru selalu Tersedia (KD 3.6) | S |
| Tersedia —bookingDisetujui [tersedia] / kunciEksemplar→ Dipesan | `setujuiBooking` + `eksemplarDapatDipesan` | S |
| Dipesan —bookingDibatalkan / lepasKunci→ Tersedia | `batalkanBooking` (Disetujui) | S |
| Dipesan —bookingDirealisasikan [pinjamanTersimpan]→ Dipinjam | `realisasikanBooking` | S |
| Tersedia —peminjamanTersimpan→ Dipinjam | `catatPeminjaman` | S |
| Dipinjam —pengembalianTersimpan / setTersedia→ Tersedia | `prosesPengembalian` | S |
| Status tidak diubah manual | Status lama tidak dapat diubah dari form buku (KD 3.6) | S |

### 04 Booking Pinjam Buku (model usulan)

| Transisi UML | Implementasi | Kode | Catatan |
| --- | --- | --- | --- |
| ● → Diajukan | `ajukanBooking` | S | — |
| Diajukan —setujui [eksemplarTersedia] / kunciEksemplar→ Disetujui | `setujuiBooking`; tombol Setujui nonaktif bila eksemplar tidak tersedia | S | Status anggota tidak diperiksa (KD 4.5, belum final) → F-01 |
| Diajukan —tolak [eksemplarTidakTersedia]→ Ditolak | Tolak diizinkan untuk **alasan apa pun**, dengan Alasan Penolakan wajib | U / D | Guard UML lebih sempit daripada FR-033 (KD 4.6, belum final) |
| Diajukan —batalkan / catatPembatalan→ Dibatalkan | `batalkanBooking` oleh Anggota | S / D | Tanggal pembatalan tidak dicatat karena tidak ada field di SRS (KD 5.6) |
| Disetujui —batalkan / lepasKunciEksemplar→ Dibatalkan | Hanya oleh Anggota pemilik | U / D | Aktor tidak ditetapkan di UML. Admin tidak diberi aksi (KD 4.8). Masih TBD-06. |
| Disetujui —peminjamanTersimpan / kaitkanTransaksi→ Direalisasikan | `realisasikanBooking` menulis loan + `idPeminjaman` secara atomik | S | — |
| Tidak ada transisi lain | Divalidasi `validasiTransaksi.js` (hanya Diajukan dapat disetujui/ditolak; hanya Disetujui dapat direalisasikan) | S | T-AUD B3, C3 |

## 5. Object Diagram

Dataset menyimpan keadaan akhir per tanggal demo (7 Nov 2026). Snapshot UML dipetakan sebagai berikut:

| Object Diagram | Nilai UML | Data mock | Kode |
| --- | --- | --- | --- |
| 02 Peminjaman Aktif | angg01 USR-002 "Anggota A" AG-001; buku01 BK-001 "Algoritma Dasar"; eks01 EK-001 Dipinjam; pin01 PJ-001 2026-10-15 Aktif | AG-001 **Nadia Rahmawati** (USR-002); BK-001 "Algoritma Dasar"; PJ-001 2026-10-15 kini **Selesai**, EK-001 Tersedia | D (`mock-data.md` §Penyesuaian): nama placeholder diganti; snapshot Aktif adalah keadaan sebelum 18 Okt |
| 03 Booking Buku | angg02 USR-003 "Anggota B" AG-002; BK-002 "Basis Data"; EK-002 Dipesan; BP-001 2026-10-16 Disetujui | AG-002 **Rizky Pratama** (USR-003); BK-002 "Basis Data"; EK-002 Dipesan; BP-001 2026-10-16 Disetujui | S (nama diganti, D) |
| 04 Pengembalian Selesai | PJ-001 Selesai; KB-001 2026-10-18; EK-001 Tersedia | PJ-001 Selesai; KB-001 2026-10-18; EK-001 Tersedia | S |

Snapshot tidak saling bertentangan dengan dataset (SRS B.3), dan `periksaKonsistensi` lulus pada dataset awal (T-MOCK, T-AUD).

## 6. Fokus khusus: alur booking, peminjaman, pengembalian

Diverifikasi ulang pada audit ini dengan skenario independen (T-AUD, dijalankan pada store yang sama dengan UI):

| Alur | Hasil |
| --- | --- |
| Ajukan (AG-001, EK-004) → Diajukan, eksemplar tetap Tersedia, ID BP-009 unik | PASS |
| Pengajuan ganda atas eksemplar yang sudah dibooking sendiri | PASS (ditolak) |
| Setujui → Disetujui, EK-004 Dipesan, tanggal & pelaku tercatat | PASS |
| Peminjaman langsung atas eksemplar Dipesan | PASS (ditolak, BR-07) |
| Realisasi tanpa jatuh tempo | PASS (ditolak, tanpa perubahan) |
| Realisasi → Direalisasikan + PJ-011 Aktif + EK-004 Dipinjam | PASS |
| Pengembalian → Selesai + KB baru + EK-004 Tersedia; pengembalian ganda ditolak | PASS |
| Tolak tanpa alasan ditolak; dengan alasan → Ditolak; booking Ditolak tidak dapat disetujui | PASS |
| Batal Diajukan / Disetujui (Dipesan → Tersedia); batal milik orang lain / Direalisasikan ditolak | PASS |
| Peminjaman oleh anggota Nonaktif; eksemplar Dipinjam; jatuh tempo = tanggal pinjam | PASS (semuanya ditolak, tanpa perubahan parsial) |
| `periksaKonsistensi` setelah setiap alur | PASS (0 galat) |
| **Kasus tepi:** booking Disetujui milik anggota yang kemudian dinonaktifkan, atau booking milik anggota Nonaktif yang disetujui | Eksemplar tetap **Dipesan**. Realisasi ditolak (anggota Nonaktif) dan Admin tidak punya aksi pembatalan. Terkunci sampai anggota sendiri membatalkan. → **F-01** |

Catatan kejujuran: pada run pertama skenario, bagian A gagal karena **kesalahan skrip audit**. Skrip memilih EK-005 yang sudah dibooking AG-001 (BP-007), lalu membaca objek lama setelah store diganti secara atomik. Skrip diperbaiki dan dijalankan ulang, dan seluruh langkahnya lulus. Aplikasi tidak diubah.

## 7. Inkonsistensi SRS ↔ UML yang perlu diselesaikan di dokumen sumber (kategori U)

1. Use Case dan Class Diagram tidak memuat verifikasi booking oleh Admin/Petugas (setujui/tolak), padahal ada di SRS FR-033.
2. Class `BookingPeminjaman` tidak punya `tanggalKeputusan`, `pelaku`, `alasanPenolakan`, atau keterkaitan dengan `Peminjaman`.
3. Class `Peminjaman` tidak punya jatuh tempo dan petugas (SRS 6.1, FR-024).
4. Class `Anggota ⊂ Pengguna` (akun wajib) berbeda dengan SRS 6.1 `members.user_id?` (akun opsional).
5. Guard `tolak [eksemplarTidakTersedia]` di State Machine 04 lebih sempit daripada FR-033.
6. Aktor transisi Disetujui → Dibatalkan tidak ditetapkan (TBD-06).
7. A03/S03 tidak memodelkan eksemplar. A04/S04 memakai model "stok".
8. A08/S08 berhenti pada pengajuan.
9. SRS memakai peran Admin + Petugas, sedangkan UML hanya Admin. Prototipe mengikuti UML untuk peran (KD).

SRS Quality Gate #5 mewajibkan perubahan Tugas 3–6 diperbarui pada semua diagram terdampak. Butir-butir di atas belum diperbarui pada UML.
