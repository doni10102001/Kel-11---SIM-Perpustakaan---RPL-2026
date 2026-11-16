# Spesifikasi Teknis Laporan Dinamis — Batch 3

SIM Perpustakaan · SRS v1.1 §4.6 (FR-014, FR-015, FR-029) · MySQL 8.4 Engine

| Metadata | Nilai |
| --- | --- |
| Dokumen Sumber | SRS v1.1 §4.6, KEPUTUSAN_DESAIN.md (TBD-07), UC-06 |
| Pengendali Server | `App\Queries\LaporanPerpustakaan` & `App\Http\Controllers\HalamanController` |
| Presentasi Klien | `resources/js/Pages/Admin/Laporan.vue` & `resources/js/utils/laporan.js` |
| Batas Ekspor | Cetak Langsung Browser (`window.print()`). CSV/Excel/PDF Ditangguhkan (TBD-07). |

---

## 1. Lingkup & Jenis Laporan Resmi

Berdasarkan SRS v1.1 FR-014 dan antarmuka resmi yang disetujui, hanya terdapat **4 (empat)** kategori laporan:

| Jenis Laporan | Kunci Parameter | Kolom Waktu Acuan | Dukungan Filter Periode (FR-015) |
| --- | --- | --- | --- |
| **Daftar Buku** | `buku` | — | Tidak (Master Data Katalog) |
| **Daftar Anggota** | `anggota` | — | Tidak (Master Data Keanggotaan) |
| **Peminjaman** | `peminjaman` | `loans.loaned_at` (Tanggal Pinjam) | **Wajib / Opsional Tanggal** |
| **Pengembalian** | `pengembalian` | `returns.returned_at` (Tanggal Kembali) | **Wajib / Opsional Tanggal** |

> [!NOTE]
> Format ekspor tambahan seperti CSV, XLSX, atau PDF server-side tetap berstatus **TBD-07: Terbuka/Ditangguhkan** sesuai spesifikasi desain. Hanya fungsi browser printing (`window.print()`) yang aktif dan teruji.

---

## 2. Aturan Batas Waktu & Zona Waktu (WIB ke UTC)

Sistem perpustakaan beroperasi pada zona waktu **Asia/Jakarta (WIB / UTC+7)** untuk operasional dan tampilan pengguna, sementara seluruh cap waktu instan MySQL disimpan dalam format **UTC**.

### 2.1 Konversi Rentang Tanggal Filter
Pengguna memilih tanggal kalender WIB pada kontrol input:
- `awal`: Tanggal mulai (misal `2026-12-01`)
- `akhir`: Tanggal akhir inklusif (misal `2026-12-03`)

Konversi ke UTC dilakukan oleh `WaktuPerpustakaan::awalHariLokal($tanggal)->utc()` dengan formulasi interval setengah-terbuka:
$$\text{Interval UTC} = [\text{Awal } 00:00:00\text{ WIB}, (\text{Akhir} + 1\text{ hari}) 00:00:00\text{ WIB})$$

Contoh Konversi:
- Awal: `2026-12-01` $\rightarrow$ `2026-11-30 17:00:00 UTC`
- Akhir: `2026-12-03` $\rightarrow$ `2026-12-03 17:00:00 UTC` (+1 hari 00:00:00 WIB)

### 2.2 Penolakan Penggunaan `DATE(kolom)`
Penyaringan di database dilakukan dengan kueri langsung terhadap timestamp:
```sql
WHERE loaned_at >= ? AND loaned_at < ?
```
**Alasan Arsitektural**:
1. Membungkus kolom dengan `DATE(loaned_at)` mematikan kemampuan MySQL menggunakan indeks B-Tree pada kolom waktu.
2. `DATE(loaned_at)` pada sesi UTC mengevaluasi tanggal kalender UTC, yang menyebabkan pergeseran tanggal terhadap kalender WIB (misal transaksi pukul 05:00 WIB adalah pukul 22:00 UTC hari sebelumnya).

---

## 3. Validasi & Penanganan Galat Server-Side

Kueri laporan divalidasi ketat di `HalamanController`:

1. **Jenis Laporan**: Wajib bernilai salah satu dari: `buku`, `anggota`, `peminjaman`, `pengembalian`.
2. **Format Tanggal**: Mengikuti pola ISO `Y-m-d`. Nilai kalender fiktif (seperti `2026-02-30`) ditolak oleh validator kalender murni.
3. **Integritas Kronologis**: Bila `awal` dan `akhir` terisi, maka `akhir` tidak boleh mendahului `awal` ($akhir \ge awal$).
4. **Respon Galat Terstruktur**:
   Bila parameter tidak valid, server mengembalikan respon props:
   - `data`: `[]` (tidak ada baris yang dimuat)
   - `filterLaporan`: `null`
   - `galatLaporan`: `['akhir' => 'Periode Akhir tidak boleh sebelum Periode Awal.']` atau pesan per-field yang relevan.

---

## 4. Struktur Data Respon Laporan

Respon `data.laporan` dari server memiliki skema konsisten:

```json
{
  "jenis": "peminjaman",
  "baris": [
    {
      "no": 1,
      "idPeminjaman": "PJ-001",
      "idBooking": null,
      "anggota": {
        "kodeAnggota": "AG-001",
        "nama": "Nadia Rahmawati"
      },
      "buku": {
        "judul": "Algoritma Pemrograman"
      },
      "kodeEksemplar": "EK-001",
      "tanggalPinjam": "2026-10-15",
      "tanggalJatuhTempo": "2026-10-22",
      "status": "Selesai"
    }
  ],
  "ringkasan": [
    { "label": "Jumlah peminjaman", "nilai": 1 },
    { "label": "Aktif", "nilai": 0 },
    { "label": "Selesai", "nilai": 1 }
  ]
}
```

### Privasi Data Anggota
Laporan transaksi tidak pernah menyertakan informasi kontak anggota (`contact`) atau riwayat akun autentikasi, meminimalkan risiko paparan data pribadi pada dokumen cetak.

---

## 5. Fitur Cetak Browser (`window.print()`)

Laporan didesain untuk pencetakan dokumen fisik/PDF langsung melalui dialog cetak browser:

1. **Kelas CSS `no-print`**:
   - Menyembunyikan sidebar navigasi, header sistem, form filter, dan tombol aksi saat mode cetak (`@media print`).
2. **Kop Laporan**:
   - Menampilkan Brand Mark resmi, judul laporan spesifik, tanggal cetak (WIB), dan identitas administrator pemohon.
3. **Tabel Cetak**:
   - Memiliki nomor baris (`no`), teks tebal pada kode transaksi, dan penataan halaman otomatis (*page breaks*).

