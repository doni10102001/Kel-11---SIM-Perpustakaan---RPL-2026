# Ekspor desain Google Stitch (diisi pemilik)

Folder ini **sengaja kosong** sampai pemilik proyek mengekspor layar dari proyek Google Stitch:
<https://stitch.google.com/projects/12475154501831437105>

## Aturan

- Isi folder ini **hanya** dengan berkas yang benar-benar diekspor dari Stitch (PNG atau PDF).
- **Jangan** memasukkan screenshot aplikasi: tidak dari `docs/archive/screenshots-prototipe/` dan tidak dari `09-HASIL-SISTEM/screenshots/`. Keduanya bukan desain Stitch.
- Pakai nama berkas sesuai tabel cakupan di [README nomor 7 §3](../README.md#cakupan-layar-dan-nama-berkas-ekspor-yang-diharapkan), misalnya `admin/01-login.png` dan `anggota/01-dashboard.png`.
- Jangan ubah isi ekspor (tanpa edit dan tanpa crop yang menghilangkan bagian layar).

## Struktur

```text
ekspor-stitch/
├── admin/     ← 13 layar Admin (01-login … 13-laporan)
└── anggota/   ← 6–7 layar Anggota (01-dashboard … 07-ubah-kata-sandi)
```

Setelah ekspor ditambahkan, perbarui tanda ⏳ menjadi ✅ pada tabel §3 di README nomor 7.
