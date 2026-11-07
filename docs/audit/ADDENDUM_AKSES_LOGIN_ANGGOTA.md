# Addendum Audit — Akses Login Anggota

| | |
| --- | --- |
| Tanggal | 7 November 2026 |
| Jenis | **Penambahan scope setelah Final Compliance Audit** |
| Cakupan | Bagian "Akses Login Anggota" pada Tambah/Ubah Anggota, masuk simulasi untuk akun buatan Admin, identitas Anggota per sesi |
| Dokumen yang **tidak** diubah | `FINAL_COMPLIANCE_AUDIT.md`, `SRS_TRACEABILITY_MATRIX.md`, `UML_COMPLIANCE_MATRIX.md`, `FINAL_SUBMISSION_CHECKLIST.md`, SRS v1.1, UML |

> **Catatan revisi (7 November 2026):** Status Akses terpisah dalam addendum ini telah **dihapus** oleh keputusan final *Status Anggota tunggal* (KD S.1–S.5). Isi addendum di bawah dipertahankan sebagai catatan versi sebelumnya; perubahan dan hasil regresi ada di `REGRESI_STATUS_ANGGOTA_TUNGGAL.md`.

Kesimpulan laporan Final Audit tetap berlaku untuk versi yang diaudit saat itu. Addendum ini hanya mencatat fitur baru beserta dampaknya. Status FR di matriks lama **tidak** direvisi; tabel di bawah adalah penilaian addendum dan perlu dikonsolidasikan pada audit berikutnya.

## 1. Ringkasan fitur

- Pendaftaran Anggota dilakukan oleh **Admin**. **Registrasi mandiri tidak tersedia.**
- Akun login **opsional** untuk data Anggota (`members.user_id?`, SRS 6.1).
- Admin dapat membuat akses login (Tambah atau Ubah Anggota), mengubah Status Akses, dan mereset kata sandi.
- Halaman Masuk menerima akun buatan Admin, lalu halaman Anggota menampilkan data milik akun tersebut.
- **Kredensial hanya untuk simulasi.** Tidak ada keamanan autentikasi server, tidak ada persistensi database. Data kembali ke mock awal setelah muat ulang.

Keputusan rinci: `docs/KEPUTUSAN_DESAIN.md` bagian *Penambahan scope pasca-Final Audit* (L.1–L.9).

## 2. Struktur data

| Entitas | Perubahan |
| --- | --- |
| `pengguna` (`users.js`) | Field `passwordHash` ditambahkan (Class Diagram `Pengguna.passwordHash`). Data awal selalu `null`. Hash dibuat di memori: `sha256$<garam 16 byte>$<SHA-256(garam:kataSandi)>`. |
| `pengguna.statusAkun` | Dikelola sebagai **Status Akses**, terpisah dari `anggota.status`. |
| `anggota.idPengguna` | Tetap relasi Member → User Account (0..1). Diisi saat akses login dibuat. |

Kata sandi plaintext tidak disimpan di store, data anggota, tabel Data Anggota, laporan, localStorage, sessionStorage, log, URL, maupun repositori Git. Tes otomatis memakai kata sandi acak yang dibangkitkan saat tes berjalan.

## 3. Dampak terhadap requirement (penilaian addendum)

| Requirement | Status di Final Audit | Dampak fitur ini | Penilaian addendum |
| --- | --- | --- | --- |
| FR-002 Validasi kredensial ke basis data | NOT COMPLIANT | Validasi dilakukan di browser terhadap store memori, bukan basis data. | **Tetap NOT COMPLIANT** |
| FR-003 Pesan bila kredensial tidak valid | PARTIALLY COMPLIANT | Kata sandi salah kini ditolak untuk akun yang kata sandinya ditetapkan Admin. Akun pratinjau `admin`/`AG-001` masih menerima kata sandi apa pun sampai direset. | Tetap PARTIALLY COMPLIANT (cakupan bertambah) |
| FR-017 Autentikasi Anggota terdaftar & Aktif | PARTIALLY COMPLIANT | Status Anggota dan Status Akses kini diperiksa saat masuk; berlaku untuk akun mana pun yang dapat masuk. Tetap simulasi di browser. | Tetap PARTIALLY COMPLIANT |
| FR-018 Anggota hanya melihat datanya | PARTIALLY COMPLIANT | Halaman Anggota memakai akun sesi, bukan selalu AG-001. URL tetap tidak dilindungi. | Tetap PARTIALLY COMPLIANT |
| FR-019 Blokir sesi tidak valid | NOT COMPLIANT | Tidak ada sesi server atau middleware. Sesi tidak dicabut otomatis bila akses dinonaktifkan saat sedang masuk. | **Tetap NOT COMPLIANT** |
| FR-005/FR-006 Tambah/Ubah Anggota | COMPLIANT | Field lama dan validasinya tidak berubah; bagian akses login bersifat tambahan. | Tidak ada regresi (diuji) |
| NFR-003 Penyimpanan kata sandi aman | Di luar lingkup | Hash bergaram di memori browser, **bukan** penyimpanan aman produksi (perlu bcrypt/argon2 di server). | Tetap di luar lingkup |

Fitur ini **tidak** boleh disebut sebagai implementasi penuh FR-002 atau FR-019.

## 4. Temuan

| ID | Tingkat | Temuan | Tindak lanjut |
| --- | --- | --- | --- |
| AL-01 | INFORMATIONAL | Verifikasi kredensial berjalan di browser; siapa pun dengan akses DevTools dapat melewati atau mengubah state. | Wajib diganti autentikasi server pada fase backend. |
| AL-02 | INFORMATIONAL | Muat ulang menghapus anggota, akun, dan kata sandi buatan Admin; halaman Anggota kembali memakai AG-001. | Ditampilkan di UI (petunjuk di bagian Akses Login, pesan sukses, catatan halaman Masuk) dan didokumentasikan. |
| AL-03 | LOW | Akun pratinjau `admin` dan `AG-001` menerima kata sandi apa pun selama belum direset (dipertahankan agar alur demo lama tetap berjalan). | Hapus saat autentikasi server dibangun. |
| AL-04 | LOW | Akun anggota bawaan AG-002…AG-010 tidak punya kata sandi, jadi tidak dapat masuk sebelum Admin mereset kata sandinya. | Sesuai syarat "kata sandi harus sesuai kredensial yang ditetapkan Admin". |
| AL-05 | LOW | Tidak ada rate limit atau penguncian setelah percobaan gagal berulang. | Fase backend. |
| AL-06 | INFORMATIONAL | Perilaku berubah: `ubahAnggota` tidak lagi menyalin Status Anggota ke `statusAkun`. Tes lama disesuaikan. | Disengaja (L.5). |
| AL-07 | INFORMATIONAL | Batas Username 3–30 karakter dan Kata Sandi 6–64 karakter adalah keputusan prototipe. SRS tidak menetapkannya. | Konfirmasi pemilik proyek. |

## 5. Bukti pengujian

| Jenis | Hasil |
| --- | --- |
| `npm run test:data` | 179/179 lulus (37 tes baru di `tests/js/akses-login.test.js`, 1 tes lama disesuaikan untuk L.5) |
| `php artisan test` | 19/19 lulus (655 assertion) |
| `npm run build` | Berhasil |
| Browser (Chrome headless, CDP) | 34/34 pemeriksaan lolos: Tambah dengan akses login, validasi (kosong, duplikat `ADMIN`, konfirmasi berbeda), pesan sukses tanpa kata sandi, tidak ada kata sandi di storage/URL/cookie, masuk akun baru (username tidak peka huruf besar/kecil), header & data milik AG-011, kata sandi salah, Admin tetap dapat masuk, Ubah (username baca-saja, kata sandi tidak ditampilkan), Reset Kata Sandi (lama ditolak, baru diterima), Status Akses Nonaktif ditolak, Anggota Nonaktif ditolak, akun hilang setelah muat ulang, 0 error JS/console. Tata letak mobile 390 px tanpa overflow horizontal. |

Cakupan tes otomatis terhadap daftar pengujian fitur:

| # | Skenario | Tes |
| --- | --- | --- |
| 1 | Anggota tanpa akun login | `1. tanpa akses login …` |
| 2 | Anggota dengan akun login | `2. dengan akses login …` |
| 3 | Username duplikat | `3. username duplikat …`, `3b. …` |
| 4 | Konfirmasi tidak sesuai | `4. konfirmasi kata sandi …` |
| 5 | Masuk dengan kredensial valid | `5. kredensial valid …` |
| 6 | Kata sandi salah | `6. kata sandi salah …` |
| 7 | Akses Nonaktif | `7. Status Akses Nonaktif …` |
| 8 | Anggota Nonaktif | `8. …`, `8b. …` |
| 9–10 | Reset & masuk dengan kata sandi baru | `9–10. reset kata sandi …` |
| 11 | Anggota baru melihat datanya sendiri | `11. …` (booking tercatat atas AG-011, bukan AG-001) |
| 12 | Tanpa data parsial | `12. Atomisitas` (validasi gagal & draf tidak konsisten) |
| 13 | Regresi booking & peminjaman | `13. …` + seluruh tes Phase 3–5 yang sudah ada |
| 14 | Akun Admin existing | `14. akun Admin existing …` |

## 6. Screenshot

Diperbarui: `admin/03-tambah-anggota.png`, `admin/04-ubah-anggota.png`. Ditambahkan: `admin/states/03a-tambah-anggota-akses-login.png`, `admin/states/04a-ubah-anggota-reset-kata-sandi.png`. Screenshot lain identik byte-per-byte dengan versi sebelumnya. Halaman Masuk berubah teks pada kotak "Mode pratinjau", tetapi kotak itu disembunyikan saat capture sehingga `01-login.png` tidak berubah.
