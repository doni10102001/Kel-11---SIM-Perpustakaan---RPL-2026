# Phase 9B (Batch 1) — Buku & Eksemplar Nyata (MySQL)

SIM Perpustakaan · Laravel 13 · MySQL 8.4 · Inertia 3 / Vue 3

| Metadata | Nilai |
| --- | --- |
| Tanggal | 16 November 2026 |
| Branch | `phase-9b-buku-eksemplar` dari `origin/main` `e13d143` (PR #2); commit `feat: implement database-backed book and copy management` |
| Lingkup | Tambah Buku + eksemplar awal, ubah data buku, tambah eksemplar, pencarian SQL + pagination, detail, ketersediaan, katalog Anggota |
| Di luar lingkup | Hapus buku/eksemplar, ubah status eksemplar manual, peminjaman, pengembalian, booking |
| Migration baru | **Tidak ada** (tabel `books`, `book_copies`, `audit_logs` yang ada) |

## 1. Arsitektur

```text
Form Tambah/Ubah Buku (Inertia router.post / router.put)
  → auth · auth.session · akun.aktif · wajib.ganti.sandi · peran:admin · throttle:30,1 · CSRF
  → TambahBukuRequest / UbahBukuRequest (trait AturanBuku; validasi + Gate kelola-buku)
  → BukuController
  → App\Services\LayananBuku (DB::transaction, lockForUpdate pada books saat Ubah)
  → Eloquent → MySQL books / book_copies / audit_logs
  → redirect Data Buku + flash (setelah commit)
```

| Rute | Nama | Request |
| --- | --- | --- |
| `POST /admin/buku` | `admin.buku.store` | `TambahBukuRequest` |
| `PUT /admin/buku/{kodeBuku}` | `admin.buku.update` | `UbahBukuRequest` |

Tidak ada rute `DELETE`; uji daftar rute tulis (`KredensialTest`) menjaga hal ini.

## 2. Tambah Buku

- Payload: `kodeBuku, judul, penulis, penerbit, tahunTerbit, kategori, eksemplar: [{kodeEksemplar, status?}]`.
- Validasi server (sama dengan validator frontend dan kolom MySQL):
  - Kode Buku: wajib, ≤ 20 karakter, pola `[A-Za-z0-9._-]` (sama dengan parameter rute), unik tanpa membedakan huruf.
  - Judul ≤ 200, Penulis ≤ 100, Penerbit ≤ 100, Kategori ≤ 50.
  - Tahun Terbit: 4 digit dan tidak melebihi tahun berjalan (WIB).
  - Eksemplar: minimal 1, maksimal 100 per penyimpanan. Kode Eksemplar wajib, ≤ 20, pola yang sama, unik terhadap database dan unik di dalam permintaan (tanpa membedakan huruf).
  - `status` eksemplar baru hanya boleh `Tersedia` atau tidak dikirim.
  - Kunci lain di dalam item eksemplar (mis. `book_id`) ditolak.
- Buku dan seluruh eksemplar awal dibuat dalam **satu transaksi**. Bila satu eksemplar gagal, buku, eksemplar lain, dan audit ikut di-rollback (diuji).
- Kirim ganda: permintaan kedua ditolak "Kode Buku sudah digunakan buku lain."; tombol Simpan dinonaktifkan selama proses.

## 3. Ubah Buku & tambah eksemplar

- **Dapat diubah:** Judul, Penulis, Penerbit, Tahun Terbit, Kategori.
- **Tidak dapat diubah:** Kode Buku. Nilainya diambil dari URL; `kodeBuku` di payload diabaikan.
- **Eksemplar baru (opsional)** disimpan dalam transaksi yang sama dengan perubahan data buku. Bila eksemplar gagal, perubahan buku ikut batal (diuji).
- **Eksemplar lama** tidak pernah dikirim maupun disentuh: status, ID, relasi booking dan peminjaman tetap (diuji dengan eksemplar Dipesan + booking Disetujui dan Dipinjam + peminjaman Aktif).
- **Buku lama tanpa eksemplar** wajib diberi minimal satu eksemplar saat disimpan.
- **Tanpa perubahan** → tanpa audit `book.updated`.

## 4. Status eksemplar

- Eksemplar baru selalu `Tersedia`, ditetapkan server (`status` tidak fillable; payload `Dipesan` atau `Dipinjam` ditolak dengan pesan galat).
- Tidak ada endpoint untuk mengubah status eksemplar; Dipesan/Dipinjam tetap hanya berubah lewat transaksi booking/peminjaman (fase berikutnya).
- UI: pilihan Dipesan/Dipinjam tetap tampil nonaktif; status eksemplar lama tampil sebagai badge baca-saja.

## 5. Penghapusan

- Tidak ada aksi atau rute hapus. `DELETE /admin/buku/{kode}` → 405.
- MySQL menolak penghapusan langsung (FK `RESTRICT`), semuanya diuji:
  - buku yang punya eksemplar (1451);
  - eksemplar yang punya peminjaman atau booking (1451);
  - eksemplar tanpa buku yang valid (1452 / 1048).

## 6. Keunikan & konkurensi

| Jaminan | Status |
| --- | --- |
| Kode Buku ganda saat balapan → galat validasi (`uq_books_book_code`) | **Diuji dengan dua koneksi MySQL**: koneksi kedua menyimpan dan commit lebih dulu, permintaan yang sudah lolos validasi lalu ditolak 1062 → `kodeBuku` |
| Kode Eksemplar ganda saat balapan → galat validasi + rollback penuh (`uq_book_copies_copy_code`) | **Diuji dengan dua koneksi MySQL** (Tambah dan Ubah) |
| Ubah Buku mengunci baris `books` sebelum memeriksa jumlah eksemplar dan menyisipkan eksemplar | Diterapkan (`lockForUpdate`) |
| Dua transaksi yang berjalan benar-benar bersamaan (insert yang saling menunggu kunci) | Tidak diuji: harness satu proses tidak dapat menahan dua transaksi aktif sekaligus. Hasil akhirnya dijamin oleh UNIQUE InnoDB. |

LayananBuku tidak mengunci member, booking, peminjaman, atau baris eksemplar yang sudah ada. Urutan kunci transaksi (member → copy → booking → loan → return) tidak terganggu.

## 7. Audit log

| Aksi | Kapan | Metadata |
| --- | --- | --- |
| `buku.dibuat` | Tambah Buku | `kode_buku`, `jumlah_eksemplar` |
| `buku.diubah` | Data bibliografi berubah | `kode_buku`, **nama kolom** yang berubah (tanpa nilai) |
| `eksemplar.dibuat` | Setiap eksemplar baru (Tambah/Ubah) | `kode_eksemplar`, `kode_buku` |

Nama aksi berbahasa Indonesia, seragam dengan aksi Anggota (`anggota.dibuat`, …). Belum pernah ada audit berawalan `book.*` di database development, jadi tidak ada riwayat yang perlu diubah atau dihapus. `subject_type` tetap `book` / `book_copy` (pola `member`).

Aktor = Admin dari sesi (`actor_id` dari klien diabaikan). Audit ditulis dalam transaksi yang sama; transaksi gagal tidak meninggalkan audit (diuji).

## 8. Pencarian SQL, pagination, katalog

Data Buku (`/admin/buku`) dan Cari Buku (`/anggota/cari-buku`) memakai `DataPerpustakaan::cariBuku()`. Halaman lain (formulir, booking, laporan) tetap menerima koleksi lengkap seperti sebelumnya.

| Aspek | Perilaku |
| --- | --- |
| Parameter | `?q=` kata kunci (dirapikan, maks. 100), `?kategori=` filter persis (maks. 50), `?page=` (bilangan ≥ 1; selain itu → 1). Nilai bukan teks (mis. `q[]=`) diabaikan. |
| Query | `book_code LIKE ? OR title LIKE ? OR category LIKE ?` lewat binding Eloquent. `%`, `_`, `\` di-escape sehingga diperlakukan sebagai huruf biasa. Tidak peka huruf besar/kecil (collation `utf8mb4_0900_ai_ci`). Penulis/penerbit tidak dicari (KEPUTUSAN 3.10). |
| Urutan & halaman | Urut Kode Buku, 20 buku per halaman (`paginate`). Halaman di luar rentang → halaman terakhir. |
| Eksemplar | Satu query `whereIn(book_id)` untuk buku di halaman ini, **lengkap per buku**, sehingga ketersediaan (total/Tersedia/Dipesan/Dipinjam) tetap akurat. |
| Kategori | `data.kategori` = semua kategori (`DISTINCT`) untuk pilihan filter Cari Buku. |
| Meta (`pencarianBuku`) | `q, kategori, halaman, perHalaman, halamanTerakhir, total, totalSemua`; dipakai teks "Menampilkan N dari M buku" dan tautan halaman. |
| N+1 | Jumlah query tetap saat buku/eksemplar bertambah (diuji di dua halaman, termasuk halaman 2 dan filter kategori). |
| Privasi | Katalog Anggota hanya `buku`, `eksemplar` (kode, kode buku, status), `kategori`. Tidak ada peminjam/pemesan, kontak, atau username (diuji). |

Frontend (`composables/usePencarianBuku.js`, `utils/pencarianServer.js`, `Components/PaginasiTabel.vue`):

- Ketik → jeda 300 ms → `router.get(url, {q, kategori})` dengan `preserveState`, `preserveScroll`, `replace`, dan `only: ['data', 'pencarianBuku']`. Halaman kembali ke 1. Request identik dengan URL saat ini tidak dikirim.
- Tautan pagination (`<Link>`) membawa `q` dan `kategori` aktif. Filter tersimpan di URL, sehingga muat ulang dan tombol kembali memulihkan isian.
- Tampilan tabel, kotak pencarian, filter kategori, panel detail, dan empty state tidak diubah. Satu-satunya tambahan adalah navigasi halaman (Bootstrap `.pagination`), yang hanya muncul bila hasil lebih dari 20 buku.
- `LIKE '%…%'` tidak memakai indeks; untuk ukuran katalog saat ini hal itu memadai. Bila koleksi membesar, pertimbangkan FULLTEXT.

## 9. Frontend

- Simpan Buku (Tambah/Ubah) → server lewat `router[metode](…)` (pelajaran Phase 9A). Hanya eksemplar baru yang dikirim.
- Galat server per field. Galat eksemplar (`eksemplar.N.kodeEksemplar`) ditampilkan di bagian Eksemplar.
- Data Buku menampilkan pesan sukses dari flash server dan data terbaru dari MySQL.
- `tambahBuku`/`ubahBuku` dikeluarkan dari `AKSI_TERTUNDA`.
- Validasi browser kini juga memeriksa panjang maksimum (Judul 200, Penulis/Penerbit 100, Kategori 50).

## 10. Pengujian

| Lapisan | Hasil |
| --- | --- |
| `tests/Feature/Buku/BukuCrudTest.php` (MySQL uji) | 22 test mencakup butir 1–31 rencana uji (CRUD, atomik, status, hapus, otorisasi, audit, balapan dua koneksi, N+1) |
| `tests/Feature/Buku/PencarianBukuTest.php` (MySQL uji) | 9 test: cari kode/judul/kategori, binding + escape wildcard, filter kategori, pagination + meta, ketersediaan lintas halaman, buku baru langsung tercari, privasi & parameter aneh, akses, N+1 |
| `tests/js/buku.test.js` | 15 test: pengiriman ke server, payload, flash, validasi panjang, pencarian via Inertia GET, tautan pagination, parameter query, nomor halaman, ketersediaan |
| Regresi | `php artisan test` 219/219 · `npm run test:data` 226/226 · `npm run build` OK · Pint OK |
| QA browser (DB uji, kredensial sementara acak) | CRUD 37/37 · pencarian & pagination 27/27 (35 buku: 10 seed + 25 buku QA) |

## 11. Aksi yang masih dinonaktifkan

Catat Peminjaman, Proses Pengembalian, Setujui/Tolak/Realisasi Booking, Ajukan/Batalkan Booking. Semuanya tetap ditolak secara jujur ("Belum tersedia … Tidak ada data yang diubah").

## 12. Temuan & catatan

1. Validasi keunikan Kode Buku dan Kode Eksemplar di browser memakai data halaman formulir. Server tetap memvalidasi ulang dan UNIQUE MySQL menjadi pertahanan terakhir.
2. Inertia menyusun ulang urutan query string (`?page=2&q=…`); perilakunya sama.
3. Permintaan `X-Inertia` tanpa versi aset dijawab 409 oleh middleware Inertia sebelum pemeriksaan peran. Responsnya tanpa data; permintaan biasa dari Anggota tetap 403.
