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
| 7 | Teknis | Repositori Git + commit baseline; font Inter dan Bootstrap Icons dipasang lokal (npm, tanpa CDN); timezone `Asia/Jakarta` *(diamandemen Phase 7A D-02, 14 Nov 2026: penyimpanan UTC, tampilan `Asia/Jakarta` — lihat bagian Phase 7A)*; syarat PHP `^8.4.1` (sesuai `composer.lock`, Symfony 8.1); screenshot memakai Chrome headless tanpa Playwright. |

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

## Keputusan implementasi Phase 3 (master data Admin)

Perlu dikonfirmasi pemilik proyek; semuanya diterapkan pada prototipe.

| # | Topik | Keputusan | Dasar |
| --- | --- | --- | --- |
| 3.1 | Rute Ubah | Ditambahkan `/demo/admin/anggota/{kodeAnggota}/ubah` dan `/demo/admin/buku/{kodeBuku}/ubah` karena Phase 1 hanya punya rute Tambah. Tidak ada rute lain yang ditambahkan. | FR-006, FR-008 |
| 3.2 | Lihat detail | Aksi "Lihat" membuka panel samping di halaman daftar (tanpa rute baru). Panel buku menampilkan daftar eksemplar dan aksi Tambah Eksemplar. | A02/A03 "Tampilkan hasil pencarian / detail" |
| 3.3 | Kode tidak dapat diubah | Kode Anggota dan Kode Buku hanya diisi saat Tambah; pada Ubah bersifat baca-saja karena dirujuk transaksi/eksemplar. **Keputusan prototipe, belum final** — SRS tidak mengaturnya. | Integritas relasi SRS 6.2 (inferensi) |
| 3.4 | Nonaktifkan anggota | **Direvisi di Phase 4:** boleh kapan pun; transaksi berjalan tidak dibatalkan. Perubahan Aktif → Nonaktif meminta konfirmasi dan menampilkan jumlah transaksi berjalan. Anggota Nonaktif tidak dapat membuat peminjaman baru (BR-01) dan tidak dapat merealisasikan booking (FR-026). | FR-022, BR-01, FR-026, SRS 3.1 (konfirmasi) |
| 3.5 | Anggota baru | Dibuat tanpa akun login (`idPengguna: null`); penyediaan akun masih TBD-02. **Direvisi pasca-Final Audit (L.1):** Admin dapat membuat akses login opsional; tanpa akses login perilakunya tetap sama. | SRS 6.1 `members.user_id?` |
| 3.6 | Status eksemplar | Eksemplar baru selalu Tersedia; Dipesan/Dipinjam tampil di pilihan tetapi nonaktif. Status eksemplar lama tidak dapat diubah dari formulir buku. | State Machine Eksemplar, FR-034/035 |
| 3.7 | Hapus | Tidak ada aksi hapus anggota/buku/eksemplar tersimpan. Hanya eksemplar baru yang belum disimpan dapat dilepas dari daftar formulir. Audit Phase 4: SRS **tidak mewajibkan** hapus (FR-005/006/008 hanya tambah, ubah, cari, lihat; SRS 6.2 menghindari penghapusan fisik; kebijakan penghapusan = TBD-08). | FR-005, FR-006, FR-008, SRS 6.2, TBD-08, A02/A03 |
| 3.8 | Validasi | Field wajib; kode unik (tidak peka huruf besar/kecil), tanpa spasi, maks. 20 karakter; Tahun Terbit 4 digit dan tidak melebihi tahun demo (2026); buku minimal satu eksemplar. | FR-021, FR-023, SRS 3.1 |
| 3.9 | Penyimpanan | Simulasi di browser: store reaktif dari salinan data mock (`useDataPerpustakaan`). Berlaku selama sesi; muat ulang mengembalikan data awal. Pesan sukses menyebut hal ini dan tidak mengklaim tersimpan permanen. | Instruksi prototipe statis |
| 3.10 | Pencarian | Anggota: kode, nama, kontak + filter status. Buku: kode buku, judul, kategori (penulis/penerbit tidak termasuk). | FR-007, FR-008 |

## Keputusan implementasi Phase 5 (halaman Anggota)

| # | Topik | Keputusan | Dasar | Status |
| --- | --- | --- | --- | --- |
| 5.1 | Identitas | Semua halaman Anggota memakai akun demo AG-001 (Nadia Rahmawati) dan hanya menampilkan booking/peminjaman miliknya. Katalog tidak menampilkan siapa yang meminjam/memesan. **Direvisi pasca-Final Audit (L.6):** halaman Anggota memakai akun yang masuk pada sesi SPA; AG-001 hanya bawaan bila belum masuk. | FR-018, FR-036, NFR-022, BR-08 | Final prototipe |
| 5.2 | Cari Buku | Pencarian kata kunci pada judul, kode buku, atau kategori + filter kategori; ketersediaan dihitung dari status eksemplar saat ini; detail menampilkan daftar eksemplar dan statusnya. | FR-007, FR-030, SRS 4.7.2 ("kata kunci atau filter"), A07/S07 | Sesuai SRS |
| 5.3 | Syarat ajukan booking | Anggota Aktif dan eksemplar Tersedia (tanpa peminjaman Aktif/booking Disetujui). Booking baru berstatus Diajukan dengan ID unik dan tanggal pengajuan = tanggal demo. Eksemplar **tidak** dikunci saat diajukan; tidak ada persetujuan atau peminjaman otomatis. | FR-031, FR-034, BR-06 | Sesuai SRS |
| 5.4 | Pengajuan ganda | Ditolak bila anggota yang sama sudah punya booking Diajukan/Disetujui untuk eksemplar yang sama (mencegah pengiriman ganda). | Pencegahan transaksi ganda | Keputusan prototipe |
| 5.5 | Kuota, antrean, masa berlaku | **Tidak dibatasi.** Anggota lain boleh mengajukan eksemplar yang sama selama belum Dipesan; Admin yang memutuskan. Booking untuk eksemplar Dipinjam tidak diizinkan (FR-031 "eksemplar yang tersedia"). | TBD-06, SRS 6.2 ("booking aktif serentak memerlukan batasan terpisah") | **Belum final** |
| 5.6 | Pembatalan oleh Anggota | Hanya booking milik sendiri berstatus **Diajukan** atau **Disetujui**; Disetujui → Dibatalkan melepas reservasi (Dipesan → Tersedia). Ditolak, Dibatalkan, Direalisasikan tidak dapat dibatalkan. Tanggal pembatalan tidak dicatat (tidak ada field di SRS). | FR-032, SRS 4.8.2 langkah 4, BR-06, State Machine 04, FR-035 | **Pembatalan setelah Disetujui masih TBD-06** |
| 5.7 | History Peminjaman | Filter status (Semua/Aktif/Selesai); Aktif tanpa Tanggal Kembali; detail menampilkan jatuh tempo dan asal transaksi (realisasi booking atau peminjaman langsung). Tanpa denda/perpanjangan. | FR-013, FR-036, FR-037, SRS 3.1 Histori, A09/S09 | Sesuai SRS |
| 5.8 | Anggota Nonaktif | Dashboard menampilkan peringatan; Ajukan Booking dinonaktifkan; riwayat tetap terlihat. | FR-022, FR-031 | Prototipe |
| 5.9 | Integrasi lintas peran | Admin dan Anggota memakai store yang sama; perpindahan peran lewat Keluar → Masuk adalah navigasi Inertia (tanpa muat ulang), sehingga simulasi berlanjut dalam satu sesi. Muat ulang browser mengembalikan data awal. | Instruksi prototipe statis | Final prototipe |

## Keputusan implementasi Phase 4 (transaksi & laporan Admin)

| # | Topik | Keputusan | Dasar | Status |
| --- | --- | --- | --- | --- |
| 4.1 | Tanggal transaksi | Tanggal Pinjam, Tanggal Kembali, dan tanggal keputusan booking dicatat otomatis = tanggal demo (7 Nov 2026); tampil sebagai field baca-saja. | FR-024, FR-028, FR-033 ("sistem menyimpan") | Prototipe |
| 4.2 | Tanggal Jatuh Tempo | Input manual, wajib, harus setelah Tanggal Pinjam; tanpa durasi default, tanpa keterlambatan/denda/perpanjangan. | FR-024, TBD-05 | Sesuai keputusan Phase 0 |
| 4.3 | Admin Pemroses | Label UI "Admin Pemroses"; nilainya akun Admin aktif (`diprosesOleh`/`diputuskanOleh` = SRS `staff_id`). | SRS 6.1, keputusan dua peran | Final prototipe |
| 4.4 | Pinjam eksemplar yang punya booking Diajukan | Diizinkan (hanya Dipesan yang dilindungi) dengan informasi kepada Admin. | BR-07, FR-034 | Prototipe |
| 4.5 | Syarat Setujui | Booking Diajukan dan eksemplar Tersedia (tanpa peminjaman Aktif/booking Disetujui lain). Status anggota **tidak** diperiksa saat persetujuan karena SRS tidak mengaturnya. | State Machine 04 `setujui [eksemplarTersedia]`, FR-034 | **Belum final** |
| 4.6 | Syarat Tolak | Booking Diajukan; Alasan Penolakan wajib (maks. 200 karakter). Penolakan boleh untuk alasan apa pun; bila eksemplar tidak tersedia, Tolak adalah satu-satunya pilihan. | FR-033 (diutamakan), State Machine 04 `tolak [eksemplarTidakTersedia]` | **Belum final** — guard UML lebih sempit dari SRS |
| 4.7 | Syarat Realisasi | Booking Disetujui, anggota Aktif, eksemplar Dipesan untuk booking tersebut tanpa peminjaman Aktif; Tanggal Pinjam tidak sebelum tanggal persetujuan. | FR-026, FR-035, BR-07 | Prototipe |
| 4.8 | Pembatalan booking oleh Admin | Tidak dibuat. Transisi Disetujui → Dibatalkan ada di State Machine tetapi aktornya tidak ditetapkan; FR-032 hanya untuk Anggota. | FR-032, State Machine 04, TBD-06 | **Belum final** |
| 4.9 | Laporan | Jenis: Daftar Buku, Daftar Anggota, Peminjaman, Pengembalian. Periode hanya untuk Peminjaman (Tanggal Pinjam) dan Pengembalian (Tanggal Kembali); daftar buku/anggota = keadaan saat ini. Cetak memakai dialog cetak browser; tidak ada ekspor PDF/Excel/CSV. | FR-014, FR-015, FR-029, TBD-07, A06 | Sesuai SRS |
| 4.10 | Atomisitas | Setiap aksi divalidasi, diterapkan pada draf, diperiksa `periksaKonsistensi`, lalu baru diterapkan; gagal = tanpa perubahan sebagian. | AC-16, NFR-006, NFR-020 | Final prototipe |
| 4.11 | Bukti & konfirmasi | Peminjaman dan realisasi menampilkan kartu bukti; pengembalian menampilkan "Konfirmasi pengembalian". Aksi penting memakai dialog konfirmasi. | A04 "bukti peminjaman", A05, SRS 3.1 | Sesuai UML |

### Evaluasi aturan inferensi (awal Phase 4)

| Aturan | Dasar | Status |
| --- | --- | --- |
| Larangan menonaktifkan anggota dengan transaksi berjalan | Tidak ada di SRS; FR-022 justru membolehkan menonaktifkan tanpa menghapus riwayat. | **Dihapus.** |
| Kode Anggota/Buku tidak dapat diubah | Tidak diatur SRS. | Dipertahankan sebagai **keputusan prototipe, belum final**. |
| Buku minimal satu eksemplar | SRS 6.2 "Buku 1 → 1..* EksemplarBuku" dan Class Diagram (1..*). | Dipertahankan (didukung model data). FR-020 memakai kata "dapat memiliki satu atau lebih". |
| Invariant validator Phase 2: transaksi berjalan hanya untuk anggota Aktif; booking Diajukan hanya untuk eksemplar Tersedia | Aturan saat pembuatan transaksi (BR-01, FR-026, FR-031), bukan invariant sepanjang waktu. | Dipindahkan dari `periksaKonsistensi` ke validasi aksi (`utils/validasiTransaksi.js`). |

## Perbedaan SRS vs UML yang diselesaikan dengan SRS v1.1

- **Peminjaman:** A04/S04 memakai "anggota valid" dan "stok tersedia". Implementasi memakai status per eksemplar: anggota `Aktif` dan eksemplar `Tersedia` (BR-01, FR-020).
- **Booking:** A08/S08 berhenti di pengajuan. Implementasi mengikuti FR-031..035 + State Machine 04: `Diajukan` → `Disetujui`/`Ditolak`; `Diajukan`/`Disetujui` → `Dibatalkan`; `Disetujui` → `Direalisasikan`.
- **Data peminjaman:** Class Diagram tidak memuat jatuh tempo dan petugas. Implementasi mengikuti SRS 6.1 (`due_at`, `staff_id`) dan FR-024.

## Akun pratinjau (bukan autentikasi)

Halaman Login hanya simulasi di sisi browser. Tidak ada sesi, otorisasi, atau verifikasi apa pun di server.

| Identitas Pengguna | Peran | Tujuan |
| --- | --- | --- |
| `admin` | Admin (Rina Wulandari) | `/demo/admin/dashboard` |
| `AG-001` | Anggota (Nadia Rahmawati) | `/demo/anggota/dashboard` |

Identitas tidak peka huruf besar/kecil dan spasi di tepi diabaikan. Kedua akun pratinjau menerima kata sandi apa pun yang tidak kosong **selama Admin belum mereset kata sandinya** (lihat L.5). Identitas lain (termasuk `petugas`) menampilkan pesan gagal yang generik (FR-003), kecuali akun yang kata sandinya ditetapkan Admin pada sesi yang sama. Akun pratinjau didefinisikan di `resources/js/config/demo.js`; verifikasi ada di `masuk()` pada `resources/js/composables/useDataPerpustakaan.js`.

## Penambahan scope pasca-Final Audit: Akses Login Anggota (7 November 2026)

Fitur ini ditambahkan **setelah** Final Compliance Audit. Laporan di `docs/audit/FINAL_*` dan matriks keterlacakan **tidak diubah** dan tidak mencakup fitur ini; temuan khusus fitur ada di `docs/audit/ADDENDUM_AKSES_LOGIN_ANGGOTA.md`. SRS dan UML asli tidak diubah.

| # | Topik | Keputusan | Dasar | Status |
| --- | --- | --- | --- | --- |
| L.1 | Pendaftaran | Anggota hanya didaftarkan oleh **Admin**. **Tidak ada registrasi mandiri.** Akses login **opsional**: anggota tetap dapat disimpan tanpa akun (`idPengguna: null`). | SRS 6.1 `members.user_id?`, FR-005 | Prototipe |
| L.2 | Field | Bagian "Akses Login Anggota" pada Tambah/Ubah Anggota: switch *Buat akses login untuk anggota ini*, **Username**, **Kata Sandi**, **Konfirmasi Kata Sandi**, **Status Akses** (Aktif/Nonaktif). Label memakai "Kata Sandi" agar konsisten dengan halaman Masuk. | Instruksi fitur, Class Diagram `Pengguna` | **Digantikan S.1–S.4** (sebelumnya: Prototipe) |
| L.3 | Validasi | Username wajib, 3–30 karakter, tanpa spasi, hanya huruf/angka/`.`/`_`/`-`, **unik terhadap semua akun termasuk Admin**, tidak peka huruf besar/kecil. Kata Sandi wajib saat membuat/mereset, 6–64 karakter. Konfirmasi wajib sama. Batas panjang/karakter adalah **keputusan prototipe** (SRS tidak menetapkannya). | Instruksi fitur | **Belum final** (batas panjang) |
| L.4 | Ubah Anggota | Akun ada → Username baca-saja, Status Akses dapat diubah, aksi **Reset Kata Sandi** (Kata Sandi Baru + Konfirmasi). Kata sandi lama tidak pernah ditampilkan/diisi; tanpa reset, hash tidak berubah. Akun belum ada → switch *Buat akses login* (tanpa membuat anggota baru). Satu anggota maksimal satu akun; akun kedua ditolak. Relasi rusak → bagian akses dikunci dengan pesan. | Instruksi fitur | **Digantikan S.1–S.4** (sebelumnya: Prototipe) |
| L.5 | Status | **Status Akses terpisah dari Status Anggota** (sebelumnya `ubahAnggota` menyalin status anggota ke `statusAkun`; sinkronisasi status dihapus, sinkronisasi nama tetap). Masuk ditolak bila Status Akses Nonaktif **atau** Anggota Nonaktif. Menonaktifkan akses tidak menghapus riwayat booking/peminjaman. | FR-017, FR-022 | **Digantikan S.1–S.4** (sebelumnya: Prototipe) |
| L.6 | Masuk | `masuk()` memeriksa: (1) username + kata sandi → pesan generik bila salah; (2) Status Akses; (3) relasi akun → tepat satu anggota; (4) Status Anggota. Status baru diungkap setelah kredensial cocok. Akun tanpa `passwordHash` hanya dapat masuk bila termasuk akun pratinjau (`admin`, `AG-001`); akun anggota bawaan lain (AG-002…AG-010) harus direset kata sandinya oleh Admin. Sesi = `idPengguna` di memori; Keluar menghapusnya. Halaman Anggota memakai akun sesi; tanpa sesi → AG-001. | FR-001, FR-003, FR-017 (sebagian) | **Digantikan S.1–S.4** (sebelumnya: Prototipe) |
| L.7 | Penyimpanan kredensial | Hanya **hash SHA-256 bergaram** di `pengguna.passwordHash` (field Class Diagram) pada store memori; data mock awal selalu `passwordHash: null`. Plaintext tidak disimpan di store, data anggota, tabel, laporan, localStorage, sessionStorage, log, URL, atau Git. Field kata sandi dikosongkan setelah simpan/batal. | Instruksi fitur, UML Class Diagram | Prototipe |
| L.8 | Atomisitas | Anggota + akun dibuat/diubah dalam satu `jalankanTransaksi`; `periksaKonsistensi` kini juga memeriksa username unik (case-insensitive), `statusAkun` valid, format `passwordHash`, satu akun ↔ satu anggota, dan akun peran anggota wajib terhubung. Gagal = tanpa perubahan sebagian. | AC-16, NFR-006, NFR-020 | Final prototipe |
| L.9 | Keterbatasan | **Bukan autentikasi aman** dan **bukan** implementasi FR-002/FR-019: tanpa server, database, rate limit, penguncian akun, sesi tahan muat ulang, atau perlindungan URL; SHA-256 tunggal di browser bukan penyimpanan kata sandi produksi. **Muat ulang** mengembalikan data ke mock awal sehingga akun/kata sandi buatan Admin hilang. Sesi tidak dicabut otomatis bila Admin menonaktifkan akses ketika anggota sedang "masuk" di tab yang sama. | Lingkup prototipe statis | Diketahui |

## Keputusan final: Status Anggota tunggal (7 November 2026)

> **Hanya Status Anggota yang mengendalikan status aktif/nonaktif Anggota dan izin login. Status Akses tidak dikelola terpisah.**

Menggantikan L.2, L.4, L.5, dan bagian status pada L.6. Catatan perubahan & regresi: `docs/audit/REGRESI_STATUS_ANGGOTA_TUNGGAL.md`.

| # | Topik | Keputusan | Status |
| --- | --- | --- | --- |
| S.1 | Sumber status | Status Anggota (`anggota.status`) satu-satunya sumber status Anggota. `pengguna.statusAkun` **dipertahankan** untuk kompatibilitas data (Class Diagram `Pengguna.statusAkun`, seed `users.js`) tetapi **selalu diturunkan**: diisi = Status Anggota saat akun dibuat dan disalin ulang setiap `ubahAnggota`. Nilai `statusAkun` dari isian formulir/pemanggil diabaikan. `periksaKonsistensi` menolak akun anggota yang `statusAkun`-nya berbeda dari Status Anggota. Akun **Admin** tidak terpengaruh. | Final prototipe |
| S.2 | Formulir | Dropdown **Status Akses dihapus** dari Tambah dan Ubah Anggota; hanya ada satu dropdown **Status Anggota**. Bagian Akses Login menampilkan field baca-saja **Status Login** (badge mengikuti Status Anggota yang sedang dipilih) dengan keterangan "Akses login tersedia karena status anggota Aktif." / "Akses login dinonaktifkan otomatis karena status anggota Nonaktif." Tambah: switch, Username, Kata Sandi, Konfirmasi. Ubah (akun ada): Username baca-saja, Status Login, tombol **Reset Kata Sandi** yang membuka panel inline (Kata Sandi Baru, Konfirmasi Kata Sandi Baru, Batal). Field kata sandi tidak dirender sampai Reset diklik. Ubah (tanpa akun): switch Buat akses login. | Final prototipe |
| S.3 | Masuk | Urutan: (1) username ditemukan, (2) kata sandi cocok, (3) akun terhubung ke tepat satu anggota yang valid, (4) Status Anggota = Aktif. Anggota Nonaktif ditolak walau kredensial benar; diaktifkan kembali → kredensial lama berlaku lagi tanpa akun/kata sandi baru. Anggota tanpa akun tidak dapat masuk. Admin: perilaku tidak berubah. | Final prototipe |
| S.4 | Data | Mengubah status tidak menghapus akun, kata sandi, maupun riwayat. Ubah nama/kontak/status tanpa Reset tidak mengubah `passwordHash`. Reset tidak menyentuh transaksi. Dialog konfirmasi menonaktifkan anggota menyebut bahwa akses login ikut dinonaktifkan. | Final prototipe |
| S.5 | F-01 | Tidak ada perubahan aturan booking (TBD-06 tetap terbuka). Interaksi dengan F-01 dicatat sebagai temuan terpisah (RS-01) di laporan regresi. | **Perlu keputusan** |

## Catatan untuk phase berikutnya

- **Phase 2 (selesai):** data mock terpusat di `resources/js/data/mock/`; akun pratinjau di `config/demo.js` kini diambil dari dataset (Admin USR-001 Rina Wulandari, Anggota AG-001 Nadia Rahmawati). Pemetaan field dan penyesuaian terhadap Object Diagram ("Anggota A/B" → nama fiktif realistis) dijelaskan di `docs/analysis/mock-data.md`.
- **Phase 3–5 (selesai):** seluruh rute memakai halaman final; komponen sementara `Sistem/HalamanDisiapkan` dihapus di Phase 5.
- **Phase 4 — field petugas pemroses:** SRS 6.1 (`loans.staff_id`, `returns.staff_id`, `bookings.staff_id`) dan FR-024/FR-028/FR-033 mencatat identitas *petugas* yang memproses transaksi. Field ini **tetap ada** (tidak diubah); di prototipe nilainya diisi akun Admin yang sedang aktif. Label field final (mis. "Petugas Proses" sesuai file phase) dikonfirmasi saat Phase 4.
- **Tanggal demo** tetap `2026-11-07` (`TANGGAL_DEMO`) agar tampilan dan screenshot deterministik.

## Phase 7A — Fondasi database MySQL (14 November 2026)

Rincian teknis: `docs/database/phase7/PHASE_7A_DATABASE_SETUP.md` §8. Dokumen SRS dan UML asli **tidak diubah**.

- Dasar: MySQL 8.4 LTS/InnoDB/utf8mb4, dua peran, Anggota dibuat Admin, akun Anggota opsional, satu Status Anggota, `loans.due_date` DATE, F-01 opsi A, migrasi bertahap.
- Stage B (14 Nov 2026): database `sim_perpustakaan_db` + `sim_perpustakaan_test`, user least-privilege `sim_perpus_app` + `sim_perpus_test`.

### Keputusan skema disetujui pemilik proyek (14 November 2026)

Persetujuan mencakup **revisi skema dan source saja**. Menjalankan migration, seeder, dan test database MySQL memerlukan persetujuan terpisah.

| # | Keputusan |
| --- | --- |
| D-01 | `users.name` nullable: Admin wajib bernama, akun Anggota wajib `NULL`; nama Anggota bersumber dari `members.name`. Dijaga CHECK MySQL `chk_users_nama_sesuai_peran`. |
| D-02 | Timestamp kejadian disimpan **UTC**, ditampilkan **Asia/Jakarta**. `app.timezone = UTC`, sesi MySQL `+00:00`, zona tampilan `Asia/Jakarta`. Batas filter laporan dikonversi WIB→UTC; "hari ini" dan validator tanggal memakai kalender WIB; kolom tanggal-saja (`due_date` DATE) tidak dikonversi. |
| D-03 | CHECK `role = 'admin' OR is_active = 1` dipertahankan. Status Anggota satu-satunya sumber aktif/nonaktif; login Anggota wajib `members.status = Aktif`; tanpa dropdown Status Akses kedua. |
| D-04 | `password_reset_tokens` dihapus; reset kata sandi Anggota oleh Admin lewat backend. |
| D-05 | `remember_token` dipertahankan nullable; "Ingat saya" belum diaktifkan. |
| D-06 | Database dan user pengujian terpisah dari development; test database tidak pernah dijalankan pada database development. |
| D-07 | `idx_members_status` dihapus; indeks eksplisit FK `staff_id` dipertahankan. |
| D-08 | Akun Anggota hasil seed memakai hash acak; tanpa kata sandi development bersama; tanpa plaintext di repository; provisioning kredensial Admin awal lewat `SEED_ADMIN_PASSWORD` di `.env` lokal (terdokumentasi). Reset kata sandi Anggota wajib tersedia pada fase berikutnya. |
| Kedaluwarsa booking | **Arah disetujui:** booking kedaluwarsa wajib berpindah keluar dari status `Disetujui` (reservasi eksemplar dilepas). **Belum disetujui:** nama status final, durasi, frekuensi scheduler. |

### Amandemen Phase 0 #7 (disetujui 14 November 2026)

Keputusan awal "timezone `Asia/Jakarta`" diamandemen menjadi: **penyimpanan dan pemrosesan UTC, tampilan dan kalender bisnis `Asia/Jakarta` (WIB)**. Alasan: konsisten dengan serialisasi ISO-8601 UTC Laravel dan frontend yang sudah merender `Asia/Jakarta` (`resources/js/utils/format.js`). Tampilan UI tidak berubah. Konsekuensi: log Laravel bertimestamp UTC; Phase 8 wajib memakai `App\Support\WaktuPerpustakaan` untuk "hari ini" dan filter periode (lihat §8.3 dokumen setup 7A).

## Phase 8A — Autentikasi nyata (14 November 2026)

Rincian: `docs/auth/PHASE_8A_AUTENTIKASI.md`.

- Autentikasi username + kata sandi terhadap `users` MySQL, guard `web`, sesi server di tabel `sessions` (`SESSION_DRIVER=database`), tanpa registrasi mandiri dan tanpa "Ingat saya".
- Rute aplikasi dipindah dari `/demo/{peran}/*` ke `/admin/*` dan `/anggota/*`, dilindungi `auth`, `auth.session`, `akun.aktif`, `peran:{peran}`. URL `/demo/*` lama hanya redirect.
- Kelayakan login diperiksa saat masuk **dan setiap request**: Admin `is_active`; Anggota wajib tertaut ke data Anggota berstatus Aktif (`members.status` tetap satu-satunya sumber, D-03).
- Identitas di frontend hanya dari sesi server (`auth.akun`); akun pratinjau AG-001 tidak dipakai lagi. Kotak "Mode pratinjau" di halaman Masuk dihapus.
- Akun Anggota hasil seed belum dapat masuk (hash acak, D-08) sampai Phase 8C menyediakan provisioning/reset oleh Admin.

## Phase 8B — Integrasi baca MySQL & privasi data (14 November 2026)

Rincian: `docs/phase8/PHASE_8B_INTEGRASI_BACA_DATA.md`.

- Data mock (anggota, akun, buku, transaksi) dan store mock dikeluarkan dari bundel frontend ke `tests/js/fixtures/`; build publik diverifikasi bebas data seed.
- Seluruh halaman Admin dan Anggota membaca MySQL lewat `HalamanController` → `App\Queries\DataPerpustakaan` (Eloquent, eager loading) → prop Inertia `data` berbentuk sama dengan prototipe; komponen Vue tidak didesain ulang.
- Anggota hanya menerima katalog publik dan data miliknya (identitas dari sesi server); Admin menerima data operasional. Otorisasi berlapis: middleware peran, Gate `buka-halaman`, dan cakupan query.
- "Hari ini" mengikuti kalender WIB server; filter periode Laporan dikonversi batas hari WIB → UTC di server.
- Semua aksi tulis ditolak eksplisit ("Belum tersedia … Tidak ada data yang diubah") sampai endpoint server tersedia; tidak ada simulasi sukses.

## Phase 8C — Manajemen kredensial nyata (14 November 2026)

Rincian: `docs/phase8/PHASE_8C_MANAJEMEN_KREDENSIAL.md`.

- Admin membuat akses login untuk Anggota yang sudah tersimpan dan mereset kata sandi Anggota lewat aksi eksplisit (bukan lewat Simpan Anggota); tersimpan ke MySQL dalam transaksi dengan penguncian baris Anggota.
- Akun Anggota selalu `role=anggota`, `name=NULL`, `is_active=true`; Status Anggota tidak berubah dan tetap satu-satunya pengendali login (D-03). Field istimewa dari klien diabaikan.
- Admin dapat mengganti kata sandinya sendiri (halaman Ubah Kata Sandi): sesi saat ini tetap masuk dengan ID baru, sesi lain berakhir.
- Reset/ganti kata sandi mengakhiri sesi lama (baris sesi dihapus + `auth.session`). Audit log mencatat aksi dan aktor, tanpa kata sandi.
- Tidak ada migration baru; aksi tulis non-kredensial tetap ditolak eksplisit.

## Phase 8D — Pengerasan keamanan & wajib ganti kata sandi (14 November 2026)

Rincian: `docs/phase8/PHASE_8D_PENGERASAN_KEAMANAN.md`.

- SSR Inertia dinonaktifkan (`config/inertia.php` default false, `INERTIA_SSR_ENABLED=false`, `inertia({ ssr: false })`); aplikasi dirender di klien.
- Penyajian URL sementara disk `local` dimatikan (`serve => false`); tidak ada pemakaian di aplikasi.
- Kolom aditif `users.must_change_password` (default 0, CHECK hanya Anggota): diset saat Admin membuat akses login atau mereset kata sandi Anggota; dihapus hanya setelah Anggota mengganti kata sandinya sendiri.
- Middleware `wajib.ganti.sandi` membatasi Anggota ber-flag pada halaman Ubah Kata Sandi dan Keluar; Admin tidak terdampak.
- Anggota dapat mengganti kata sandi sendiri (`/anggota/kata-sandi`); sesi lain berakhir, sesi ini diregenerasi, lalu diarahkan ke Dashboard.

## Phase 9A — CRUD Anggota nyata (15 November 2026)

Rincian: `docs/phase9/PHASE_9A_CRUD_ANGGOTA.md`.

- Tambah Anggota (kode diisi Admin, unik), ubah profil (nama, kontak, status), aktif/nonaktif tersimpan ke MySQL lewat `LayananAnggota` dalam transaksi; tanpa migration baru dan tanpa hapus fisik.
- Akses login opsional saat Tambah dibuat atomik bersama Anggota (wajib ganti kata sandi saat pertama masuk); pada Ubah tetap aksi eksplisit Phase 8C.
- F-01 opsi A diterapkan: penonaktifan ditolak bila ada booking `Disetujui`; member dikunci lebih dulu. Persetujuan booking (Phase 11) wajib mengikuti urutan kunci member → copy → booking.
- Penonaktifan menghapus baris sesi database akun Anggota (selain penolakan oleh `akun.aktif`); akun, kata sandi, dan riwayat tidak dihapus.

## Phase 9B (Batch 1) — Buku & eksemplar nyata (16 November 2026)

Rincian: `docs/phase9/PHASE_9B_BUKU_EKSEMPLAR.md`.

- Tambah Buku beserta eksemplar awal (minimal satu) dibuat atomik lewat `LayananBuku`; ubah data bibliografi dan tambah eksemplar dalam satu transaksi. Tanpa migration baru, tanpa rute hapus.
- Kode Buku tetap tidak dapat diubah (keputusan 3.3). Eksemplar baru selalu `Tersedia` dan ditetapkan server; status eksemplar lama tidak pernah diubah dari formulir buku (3.6).
- Pencarian Data Buku dan Cari Buku dijalankan MySQL (kode buku, judul, kategori; filter kategori persis), lewat Inertia GET `?q=&kategori=&page=`, dengan 20 buku per halaman. Kolom pencarian tetap sesuai 3.10/5.2.
- Nama aksi audit seragam berbahasa Indonesia: `buku.dibuat`, `buku.diubah`, `eksemplar.dibuat`.

## Batch 2 — Manajemen transaksi & kedaluwarsa booking (16 November 2026)

Rincian: `docs/phase10/` (BATCH_2_TRANSACTION_MANAGEMENT, BOOKING_EXPIRATION, CONCURRENCY_AND_LOCKING, TRANSACTION_TEST_REPORT). Ekstensi atas SRS/UML; dokumen SRS dan UML asli tidak diubah.

- **Kedaluwarsa booking (disetujui):** booking Disetujui kedaluwarsa tepat 48 jam setelah persetujuan (`expires_at = decided_at + 48 jam`, UTC). Status baru `Kedaluwarsa`; `expired_at` = waktu pemrosesan; reservasi dilepas tanpa melepas eksemplar milik transaksi lain. UI menampilkan "Batas Pengambilan" (WIB). Migration aditif `000009`; backfill hanya booking Disetujui, tanpa mengarang timestamp.
- **Penjadwal:** `bookings:expire` tiap 5 menit (`withoutOverlapping`, `onOneServer`), per batch, idempoten. Realisasi, pembatalan, peminjaman, pengajuan, persetujuan, dan penonaktifan memeriksa/merekonsiliasi batas sendiri, sehingga jeda penjadwal tidak memperpanjang batas. Production memerlukan cron `schedule:run` tiap menit (belum dikonfigurasi).
- **Pengembalian oleh Admin untuk Anggota Nonaktif (disetujui):** diizinkan; status anggota tidak berubah.
- **Status anggota:** hanya Aktif yang dapat meminjam dan mengajukan booking; persetujuan untuk anggota Nonaktif ditolak; F-01 tetap (penonaktifan ditolak bila ada booking Disetujui yang belum lewat batas).
- **Urutan kunci:** members → bookings → book_copies → loans → returns (`lockForUpdate`), dibuktikan dengan test dua koneksi MySQL.
- **Tanggal:** timestamp kejadian UTC, ditampilkan WIB; `loans.due_date` DATE diisi Admin, harus setelah tanggal pinjam (WIB), tanpa durasi bawaan.
- **Audit:** `peminjaman.dibuat`, `pengembalian.dibuat`, `booking.diajukan|disetujui|ditolak|dibatalkan|direalisasikan|kedaluwarsa`; kejadian penjadwal beraktor NULL (sistem).
- **Tidak ada pembatalan otomatis** atas booking lain yang tidak terkait.
- **Masih terbuka:** kuota/antrean/masa booking Diajukan (TBD-06; perilaku teruji dipertahankan), denda/perpanjangan/durasi pinjam (TBD-05).
