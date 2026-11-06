# Keputusan Desain & Implementasi

Catatan keputusan yang telah disetujui pemilik proyek. Dokumen ini **khusus pengembang** — isinya tidak boleh tampil di antarmuka aplikasi.

## Hierarki sumber

1. **SRS v1.1** (`docs/requirements/Tugas_2_SRS_FINAL.pdf`) adalah referensi utama untuk fungsi, field, status, dan aturan bisnis.
2. **UML** (`docs/uml/`) dipakai untuk alur, state, dan relasi data. Bila berbeda dengan SRS v1.1, **SRS v1.1 yang dipakai**.
3. Transkripsi Activity/Sequence Diagram: `docs/analysis/uml-activity-sequence.md` (hasil transkripsi, bukan dokumen otoritatif).
4. Website Laravel/Vue yang sudah final menjadi acuan visual untuk mockup Google Stitch. Stitch hanya mereplikasi, tidak mendesain ulang.

## Keputusan Phase 0 (disetujui 7 November 2026)

| # | Topik | Keputusan |
| --- | --- | --- |
| 1 | Peran | **Digantikan oleh keputusan final 7 November 2026 di bawah: dua peran, Admin dan Anggota.** (Keputusan awal: Admin dan Petugas berbagi halaman yang sama.) Fitur **Kelola Pengguna dan Role tidak dibuat** (di luar scope halaman saat ini). |
| 2 | Identitas anggota | Label UI **"Kode Anggota"**, format contoh `AG-001`. Pemetaan: SRS 6.1 `members.member_code` = Class Diagram `Anggota.idAnggota` = "nomor anggota" (FR-023). |
| 3 | Route `/` | Redirect ke `/login`. |
| 4 | Tanggal Jatuh Tempo | Input manual oleh Admin saat mencatat peminjaman, **tanpa durasi pinjam default** (durasi masih TBD-05). |
| 5 | Tolak Booking | Ada input **"Alasan Penolakan"** (FR-033), hanya muncul dalam konteks penolakan. |
| 6 | Data Buku | Menampilkan **"Jumlah Eksemplar"** (SRS 3.1), dihitung dari data eksemplar, bukan nilai terpisah. |
| 7 | Teknis | Repositori Git + commit baseline; font Inter dan Bootstrap Icons dipasang lokal (npm, tanpa CDN); timezone `Asia/Jakarta`; syarat PHP `^8.4.1` (sesuai `composer.lock`, Symfony 8.1); screenshot memakai Chrome headless tanpa Playwright. |

## Keputusan final peran prototipe (7 November 2026)

**Prototipe hanya memakai dua peran: Admin dan Anggota.** Peran Petugas tidak dipakai sebagai peran terpisah.

| Peran | Rute | Cakupan |
| --- | --- | --- |
| Admin | `/demo/admin/*` | Seluruh fungsi pengelolaan perpustakaan: Dashboard, Data Anggota, Data Buku & Eksemplar, Peminjaman Buku, Pengembalian Buku, Verifikasi Booking (termasuk Realisasi Booking), Laporan. |
| Anggota | `/demo/anggota/*` | Dashboard, Cari Buku, Booking Pinjam Buku (Ajukan / Status), History Peminjaman. |

- Label UI operasional memakai **"Admin"** (sidebar "Panel Admin", chip peran di header, akun pratinjau).
- Struktur peran tetap dipertahankan untuk otorisasi berbasis peran di tahap lanjut: `App\Support\NavigasiDemo::PERAN`, nama rute `demo.{peran}.*`, dan atribut `peran` pada setiap item navigasi.
- Tidak ada halaman Kelola Pengguna maupun Kelola Role.

> **Perlu diselaraskan pada dokumentasi final.** SRS v1.1 (§2.3 dan Access Control Matrix) membedakan **Admin** dan **Petugas**. Satu-satunya hak yang khusus Admin di SRS adalah "Kelola pengguna dan role", yang tidak dibangun pada prototipe. Seluruh hak Petugas di SRS (buku, anggota, pinjam, kembali, verifikasi booking, laporan) dijalankan oleh peran **Admin** di prototipe. Use Case Diagram juga hanya memuat aktor "Admin", sehingga prototipe sejalan dengan UML, tetapi berbeda dengan pembagian peran di SRS v1.1. Dokumen SRS/UML final perlu diperbarui atau diberi catatan atas keputusan ini.

## Perbedaan SRS vs UML yang diselesaikan dengan SRS v1.1

- **Peminjaman:** A04/S04 memakai "anggota valid" dan "stok tersedia". Implementasi memakai status per eksemplar: anggota `Aktif` dan eksemplar `Tersedia` (BR-01, FR-020).
- **Booking:** A08/S08 berhenti di pengajuan. Implementasi mengikuti FR-031..035 + State Machine 04: `Diajukan` → `Disetujui`/`Ditolak`; `Diajukan`/`Disetujui` → `Dibatalkan`; `Disetujui` → `Direalisasikan`.
- **Data peminjaman:** Class Diagram tidak memuat jatuh tempo dan petugas. Implementasi mengikuti SRS 6.1 (`due_at`, `staff_id`) dan FR-024.

## Akun pratinjau (bukan autentikasi)

Halaman Login hanya simulasi di sisi browser. Tidak ada verifikasi kata sandi, sesi, atau otorisasi di server.

| Identitas Pengguna | Peran | Tujuan |
| --- | --- | --- |
| `admin` | Admin (Rina Wulandari) | `/demo/admin/dashboard` |
| `AG-001` | Anggota (Nadia Rahmawati) | `/demo/anggota/dashboard` |

Identitas tidak peka huruf besar/kecil dan spasi di tepi diabaikan. Kata sandi apa pun yang tidak kosong diterima. Identitas lain (termasuk `petugas`) menampilkan pesan gagal yang generik (FR-003). Akun pratinjau didefinisikan di `resources/js/config/demo.js`.

## Catatan untuk phase berikutnya

- **Phase 2 (selesai):** data mock terpusat di `resources/js/data/mock/`; akun pratinjau di `config/demo.js` kini diambil dari dataset (Admin USR-001 Rina Wulandari, Anggota AG-001 Nadia Rahmawati). Pemetaan field dan penyesuaian terhadap Object Diagram ("Anggota A/B" → nama fiktif realistis) dijelaskan di `docs/analysis/mock-data.md`.
- **Phase 3–5:** rute yang isinya belum dibangun memakai komponen sementara `Sistem/HalamanDisiapkan` (diatur di `NavigasiDemo`, kunci `komponen`). Setiap phase mengganti komponen tersebut dengan halaman final.
- **Phase 4 — field petugas pemroses:** SRS 6.1 (`loans.staff_id`, `returns.staff_id`, `bookings.staff_id`) dan FR-024/FR-028/FR-033 mencatat identitas *petugas* yang memproses transaksi. Field ini **tetap ada** (tidak diubah); di prototipe nilainya diisi akun Admin yang sedang aktif. Label field final (mis. "Petugas Proses" sesuai file phase) dikonfirmasi saat Phase 4.
- **Tanggal demo** tetap `2026-11-07` (`TANGGAL_DEMO`) agar tampilan dan screenshot deterministik.
