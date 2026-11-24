# Alat pembangkit artefak Tugas 10

Skrip di folder ini dipakai untuk membuat artefak visual paket Tugas 10. Skrip ini **bukan** bagian dari aplikasi dan tidak dijalankan oleh aplikasi.

| Skrip | Fungsi | Cara pakai |
| --- | --- | --- |
| `capture-hasil-sistem.mjs` | Menjalankan skenario demo di Chrome headless terhadap **`sim_perpustakaan_test` saja**, memverifikasi setiap mutasi dengan kueri DB, menyimpan screenshot, lalu mengosongkan DB uji. Berhenti bila DB uji tidak kosong atau `public/hot` ada. Kredensial MySQL uji **wajib** dari environment (`DB_TEST_USERNAME`, `DB_TEST_PASSWORD`), tanpa nilai bawaan dan tidak dicetak. Admin uji di-seed dengan kata sandi acak sekali pakai. | `DB_TEST_USERNAME=sim_perpus_test DB_TEST_PASSWORD=… node --experimental-websocket capture-hasil-sistem.mjs <repo> <folderOutput> <laporan.json>` (setelah `npm run build`; isi `DB_TEST_PASSWORD` lewat `read -rs`, bukan diketik di perintah) |
| `gen-usecase.mjs` | Membangkitkan SVG Use Case Diagram implementasi | `node gen-usecase.mjs ../02-USECASE-DIAGRAM/suplemen-implementasi/use-case-implementasi.svg` |
| `gen-deployment.mjs` | Membangkitkan SVG Deployment Diagram lokal aktual | `node gen-deployment.mjs ../06-DEPLOYMENT-DIAGRAM/suplemen-implementasi/deployment-lokal-aktual.svg` |
| `render.mjs` | Merender `.mmd` (Mermaid) dan `.svg` menjadi PNG (2×) lewat Chrome headless | Letakkan di folder berisi `node_modules/mermaid` (`npm i mermaid@11.4.1` di folder sementara), lalu `node --experimental-websocket render.mjs <berkas...>` |
| `build-deck.cjs` | Membangkitkan `10-PRESENTASI/SIM_Perpustakaan_Kelompok_11_Final.pptx` (pptxgenjs 3.12 + `apply_theme.js` dari skill pptx) dari aset PNG yang sudah di-crop/di-resize | `NODE_PATH=<node_modules> node build-deck.cjs <folderAset> <out.pptx> <apply_theme.js>` |

Prasyarat: Node.js 20, Google Chrome. Tidak ada dependensi yang ditambahkan ke `package.json` proyek.
