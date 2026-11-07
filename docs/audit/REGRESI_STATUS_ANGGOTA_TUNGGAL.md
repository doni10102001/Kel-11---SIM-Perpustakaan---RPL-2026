# Catatan Perubahan & Regresi — Status Anggota Tunggal

| | |
| --- | --- |
| Tanggal | 7 November 2026 |
| Jenis | Penyederhanaan logika dan UI setelah `ADDENDUM_AKSES_LOGIN_ANGGOTA.md` (di luar cakupan Final Audit) |
| Keputusan | **Hanya Status Anggota yang mengendalikan status aktif/nonaktif Anggota dan izin login. Status Akses tidak dikelola terpisah.** (KD S.1–S.5) |
| Dokumen yang tidak diubah | Laporan `FINAL_*`, matriks SRS/UML, SRS v1.1, UML |

## 1. Akar masalah dua status

- **Sebelum fitur Akses Login:** `ubahAnggota` menyalin `anggota.status` ke `pengguna.statusAkun`. Status Anggota sudah menjadi satu-satunya sumber status.
- **Fitur Akses Login (addendum):** spesifikasi meminta field "Status Akses Aktif/Nonaktif". Implementasinya menjadikan `statusAkun` field yang bisa diedit sendiri. Penyalinan status dihapus (KD L.5), dan masuk memeriksa dua status secara terpisah.
- Hasilnya, ada dua status yang bisa bertentangan, misalnya Anggota Aktif dengan akses Nonaktif. Admin juga harus mengatur dua dropdown.

## 2. Perubahan

| Area | Sebelum | Sesudah |
| --- | --- | --- |
| Form Tambah | Status Anggota + Status Akses (saat switch aktif) | Hanya Status Anggota. Akses Login berisi Username, **Status Login** (baca-saja, mengikuti Status Anggota), Kata Sandi, Konfirmasi |
| Form Ubah (akun ada) | Dropdown Status Akses, tombol Reset di kotak kata sandi | Username baca-saja, Status Login baca-saja + keterangan otomatis, Reset Kata Sandi membuka **panel inline** berjudul (Kata Sandi Baru, Konfirmasi, Batal) |
| Ringkasan Keanggotaan | "Akses login" = badge Status Akses | "Akses login" = Sudah dibuat / Belum dibuat |
| Dialog nonaktifkan anggota | — | Menyebut akses login ikut dinonaktifkan, akun dan kata sandi tetap tersimpan |
| `statusAkun` | Diedit Admin | Dipertahankan untuk kompatibilitas, **diturunkan** dari Status Anggota; nilai dari isian diabaikan |
| `validasiAksesLogin` | Memvalidasi `statusAkun` | Tidak mengenal `statusAkun` |
| `masuk()` (Anggota) | Kredensial → Status Akses → relasi → Status Anggota | Kredensial → relasi → **Status Anggota** |
| `masuk()` (Admin) | Kredensial → `statusAkun` | Tidak berubah |
| `periksaKonsistensi` | — | Menolak akun anggota yang `statusAkun`-nya ≠ Status Anggota |

Catatan fakta: pada versi sebelumnya, field Kata Sandi Baru/Konfirmasi juga hanya muncul setelah tombol Reset diklik. Perbaikan di sini memperjelas tampilan reset sebagai panel tersendiri (judul, keterangan, Batal di dalam panel) dan menghapus Status Akses dari sekitarnya.

Kompatibilitas data: seed `users.js` tidak diubah. Status akun seed sudah sama dengan Status Anggota (AG-007 dan AG-009 Nonaktif di kedua tempat), sehingga dataset lolos aturan konsistensi baru tanpa migrasi.

## 3. Hasil pengujian

| Jenis | Hasil |
| --- | --- |
| `npm run test:data` | **184/184** lulus. `akses-login.test.js` berisi 42 tes. Tes `master-data` "ubah anggota menyinkronkan nama dan status akun login" dikembalikan ke ekspektasi semula. |
| `php artisan test` | **19/19** lulus |
| `npm run build` | Berhasil |
| Browser QA (Chrome headless) | **40/40** pemeriksaan lolos, 0 error JS/console (lihat §4) |
| Mobile 390 px | Tambah (switch aktif) dan Ubah (panel reset terbuka) tanpa overflow horizontal |

Pemetaan skenario wajib ke tes otomatis (`tests/js/akses-login.test.js`):

| # | Skenario | Tes |
| --- | --- | --- |
| 1 | Aktif + akun valid → berhasil | `5. kredensial valid …` |
| 2 | Nonaktif + akun valid → ditolak | `Anggota Nonaktif + akun valid → ditolak …`, `8b. …` |
| 3 | Nonaktif → Aktif → berhasil lagi | `Anggota Nonaktif → Aktif kembali …` |
| 4 | Aktif tanpa akun → tidak dapat masuk | `Anggota Aktif tanpa akun → tidak dapat masuk` |
| 5–6 | Tambah tanpa/dengan akses login | `1. …`, `2. …`, `anggota Nonaktif dengan akses login …` |
| 7 | Username duplikat | `3. …`, `3b. …` |
| 8 | Kata sandi ≠ konfirmasi | `4. …`, `reset kata sandi: konfirmasi berbeda …` |
| 9 | Ubah nama/kontak tanpa reset | `tanpa reset, kata sandi tidak berubah`, `ubah status tanpa reset …` |
| 10–11 | Reset eksplisit; kata sandi lama tidak berlaku | `9–10. reset kata sandi …` |
| 12 | Admin tidak terpengaruh | `14. akun Admin existing …`, `akun Admin tidak terpengaruh perubahan status anggota …` |
| 13 | Relasi & konsistensi | `periksaKonsistensi menolak status akun … bertentangan`, `… satu akun … dua anggota`, `tidak ada Status Akses: nilai statusAkun tersembunyi … diabaikan` |
| 14 | Regresi booking/peminjaman/pengembalian | `13. …` + seluruh tes Phase 3–5 |

## 4. Browser QA

Alur yang diperiksa:

- **Tambah Anggota:** hanya satu `<select>`, tidak ada teks "Status Akses", dan keterangan Status Login ikut berubah saat Status Anggota diganti. Validasi diperiksa untuk field kosong, username duplikat `ADMIN`, dan konfirmasi berbeda. Kata sandi tidak muncul di pesan, tabel, panel detail, storage, URL, maupun cookie.
- **Login Anggota Aktif:** berhasil. Header menampilkan Dimas Ramadhan · AG-011.
- **Ubah Anggota:** username baca-saja, tidak ada input kata sandi sebelum Reset, dan Batal menutup panel. Mengubah kontak tidak mereset kata sandi.
- **Status Nonaktif:** saat diubah ke Nonaktif, keterangan dan dialog menyebut akses login ikut dinonaktifkan. Login ditolak walau kredensial benar. Setelah diaktifkan kembali, kredensial lama berlaku.
- **Reset Kata Sandi:** konfirmasi berbeda ditolak. Setelah reset, kata sandi lama ditolak dan yang baru diterima.
- **Anggota tanpa akun:** tidak dapat masuk. Admin membuat akses dari Ubah Anggota, lalu anggota itu dapat masuk.
- **Akun Admin:** tetap dapat masuk.
- **Muat ulang penuh:** akun buatan Admin hilang (keterbatasan yang sudah diketahui).

## 5. Screenshot

| File | Perubahan |
| --- | --- |
| `admin/03-tambah-anggota.png` | Teks petunjuk Akses Login berubah |
| `admin/04-ubah-anggota.png` | Status Akses diganti Status Login baca-saja; Ringkasan "Akses login: Sudah dibuat" |
| `admin/states/03a-tambah-anggota-akses-login.png` | Status Akses diganti Status Login baca-saja |
| `admin/states/04a-ubah-anggota-reset-kata-sandi.png` | Panel Reset Kata Sandi baru |

Screenshot lain identik byte-per-byte. Keempat file di atas perlu diregenerasi di Google Stitch dan diperbarui di Figma secara manual. Figma tidak diubah otomatis.

## 6. Temuan terbuka

| ID | Tingkat | Temuan | Rekomendasi (belum diimplementasikan) |
| --- | --- | --- | --- |
| **RS-01** (terkait F-01) | MEDIUM | **Aturan login menutup jalan pelepasan yang tersisa pada F-01.** Final Audit mencatat eksemplar Dipesan milik anggota yang dinonaktifkan hanya terlepas bila anggota itu sendiri membatalkan bookingnya (`validasiBatalkanBooking` tidak memeriksa status anggota). Pada versi itu, hanya AG-001 yang dapat masuk dan statusnya tidak diperiksa. Kini Anggota Nonaktif **mana pun ditolak saat masuk**, sehingga pembatalan mandiri tidak lagi dapat dicapai. Admin tidak punya aksi batal (KD 4.8), dan realisasi mensyaratkan anggota Aktif (KD 4.7). Satu-satunya jalan melepas eksemplar adalah mengaktifkan kembali anggota tersebut. Diverifikasi dengan data mock: menonaktifkan AG-002 (booking Disetujui BP-001) membuat EK-002 tetap Dipesan dan realisasi BP-001 ditolak. | Putuskan dalam TBD-06: (1) beri Admin aksi **Batalkan booking Disetujui** (State Machine 04 sudah punya transisi Disetujui → Dibatalkan, aktornya belum ditetapkan), dan/atau (2) tampilkan peringatan khusus booking Disetujui di dialog nonaktifkan anggota, dan/atau (3) cek status anggota saat Setujui. Opsi (1) paling langsung menutup celah. Aturan booking **tidak diubah** dalam pekerjaan ini. |
| RS-02 | LOW | Sesi Anggota yang sedang masuk tidak dicabut bila Admin menonaktifkan anggotanya di tab yang sama (tidak ada sesi server, FR-019). Batasan "Anggota Nonaktif tidak dapat membuat booking/peminjaman baru" tetap berlaku lewat validasi transaksi. | Fase backend (middleware sesi). |
| AL-01…AL-05, AL-07 | — | Tetap berlaku seperti di addendum (simulasi browser, data hilang saat muat ulang, akun pratinjau menerima kata sandi apa pun, dan seterusnya). AL-06 tidak berlaku lagi karena penyalinan status dikembalikan. | — |
