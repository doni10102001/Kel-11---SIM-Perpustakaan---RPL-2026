// Generator deck Tugas 10 — SIM Perpustakaan Kelompok 11 (pptxgenjs, structured deck).
// Pemakaian: node build-deck.cjs <assetsDir> <out.pptx> <applyThemePath>
const fs = require('fs');
const path = require('path');
const pptxgen = require('pptxgenjs');
const [ASSETS, OUT, APPLY] = process.argv.slice(2);
const { applyTheme } = require(APPLY);

const THEME = {
    name: 'SIM Perpustakaan',
    headFontFace: 'Calibri',
    bodyFontFace: 'Calibri',
    colors: {
        dk1: '1E293B', lt1: 'FFFFFF', dk2: '0B2545', lt2: 'EEF3FA',
        accent1: '1F5FD1', accent2: '0E9F6E', accent3: 'D97706', accent4: 'DC2626', accent5: '64748B', accent6: '7C3AED',
        hlink: '1F5FD1', folHlink: '7C3AED',
    },
};
const HEX = THEME.colors;

const pres = new pptxgen();
pres.layout = 'LAYOUT_WIDE'; // 13.333 x 7.5 in
pres.theme = { headFontFace: THEME.headFontFace, bodyFontFace: THEME.bodyFontFace };
pres.title = 'SIM Perpustakaan — Kelompok 11 — Tugas 10 RPL';
pres.author = 'Kelompok 11';
pres.company = 'Universitas Indraprasta PGRI';
pres.subject = 'Presentasi akhir Tugas 10 Rekayasa Perangkat Lunak';
const C = pres.SchemeColor;
const W = 13.333, Hh = 7.5;

const sh = () => ({ type: 'outer', color: '0B2545', opacity: 0.14, blur: 8, offset: 2, angle: 90 });
const img = (f) => path.join(ASSETS, f);
function pngSize(f) { const b = fs.readFileSync(img(f)); return { w: b.readUInt32BE(16), h: b.readUInt32BE(20) }; }
function fit(f, x, y, mw, mh, align = 'center') {
    const s = pngSize(f); const r = Math.min(mw / s.w, mh / s.h); const w = s.w * r, h = s.h * r;
    const ox = align === 'left' ? 0 : (mw - w) / 2;
    return { x: x + ox, y: y + (mh - h) / 2, w, h };
}

// ---------------------------------------------------------------- Layouts
const FOOT = 'SIM Perpustakaan · Kelompok 11 · Rekayasa Perangkat Lunak 2026';
pres.defineSlideMaster({
    title: 'KONTEN',
    background: { color: C.background1 },
    margin: [0.5, 0.6, 0.6, 0.6],
    objects: [
        { placeholder: { options: { name: 'title', type: 'title', x: 0.6, y: 0.35, w: 12.1, h: 0.85, fontSize: 32, bold: true, color: C.text2, valign: 'middle', margin: 0 }, text: '' } },
        { text: { text: FOOT, options: { x: 0.6, y: 7.0, w: 8, h: 0.3, fontSize: 10, color: C.accent5, margin: 0 } } },
    ],
    slideNumber: { x: 12.2, y: 7.0, w: 0.5, h: 0.3, fontSize: 10, color: C.accent5, align: 'right' },
});
pres.defineSlideMaster({
    title: 'GELAP',
    background: { color: C.text2 },
    objects: [],
});

function judul(slide, t) { slide.addText(t, { placeholder: 'title' }); }
function tb(slide, text, o) { slide.addText(text, { isTextBox: true, margin: 0, valign: 'top', fontSize: 15, color: C.text1, ...o }); }
function kartu(slide, x, y, w, h, fill = C.background2, name = 'kartu') {
    slide.addShape(pres.shapes.ROUNDED_RECTANGLE, { x, y, w, h, rectRadius: 0.12, fill: { color: fill }, line: { color: fill, width: 0 }, shadow: sh(), objectName: name });
}
function bingkai(slide, f, x, y, mw, mh, alt, align) {
    const p = fit(f, x, y, mw, mh, align);
    slide.addShape(pres.shapes.RECTANGLE, { x: p.x - 0.04, y: p.y - 0.04, w: p.w + 0.08, h: p.h + 0.08, fill: { color: C.background1 }, line: { color: 'CBD5E1', width: 0.75 }, shadow: sh(), objectName: 'bingkai ' + f });
    slide.addImage({ path: img(f), x: p.x, y: p.y, w: p.w, h: p.h, altText: alt });
    return p;
}
function lencana(slide, x, y, teks, fill = C.accent1, d = 0.5, size = 16) {
    slide.addShape(pres.shapes.OVAL, { x, y, w: d, h: d, fill: { color: fill }, line: { color: fill, width: 0 }, objectName: 'lencana ' + teks });
    slide.addText(String(teks), { isTextBox: true, x, y, w: d, h: d, align: 'center', valign: 'middle', fontSize: size, bold: true, color: C.background1, margin: 0 });
}
function label(slide, x, y, w, teks, fill, color = C.background1, size = 11) {
    slide.addShape(pres.shapes.ROUNDED_RECTANGLE, { x, y, w, h: 0.32, rectRadius: 0.08, fill: { color: fill }, line: { color: fill, width: 0 }, objectName: 'label ' + teks });
    slide.addText(teks, { isTextBox: true, x, y, w, h: 0.32, align: 'center', valign: 'middle', fontSize: size, bold: true, color, margin: 0 });
}
const B = (arr, o = {}) => arr.map((t, i) => ({ text: t, options: { bullet: true, breakLine: i < arr.length - 1, paraSpaceAfter: 6, ...o } }));

// ================================================================ SLIDES
// 1 — COVER
pres.addSection({ title: 'Pembuka' });
let s = pres.addSlide({ masterName: 'GELAP', sectionTitle: 'Pembuka' });
tb(s, 'TUGAS 10 · REKAYASA PERANGKAT LUNAK', { x: 0.7, y: 0.75, w: 6.6, h: 0.35, fontSize: 14, bold: true, color: 'A9C4F5', charSpacing: 2 });
tb(s, 'Sistem Informasi Manajemen Perpustakaan', { x: 0.7, y: 1.2, w: 6.7, h: 1.6, fontSize: 38, bold: true, color: C.background1, fontFace: THEME.headFontFace, valign: 'top' });
tb(s, 'Aplikasi web Laravel 13 · Vue 3 · Inertia.js · MySQL 8.4', { x: 0.7, y: 2.95, w: 6.6, h: 0.4, fontSize: 18, color: 'D6E2F7' });
tb(s, 'Kelompok 11 · Universitas Indraprasta PGRI · November 2026', { x: 0.7, y: 3.4, w: 6.6, h: 0.35, fontSize: 15, color: 'A9C4F5' });
// Susunan final Kelompok 11 untuk Tugas 10 (keputusan pemilik proyek, 24 Nov 2026): tiga anggota.
const anggota = [['Doni Suhendar', '202243501242'], ['Abdurrahman Wahid', '202343500460'], ['Sarah Tirta Kencana', '202343500443']];
anggota.forEach(([n, npm], i) => {
    const x = 0.7, y = 4.2 + i * 0.82;
    s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x, y, w: 6.4, h: 0.66, rectRadius: 0.1, fill: { color: '163A66' }, line: { color: '2B5288', width: 0.75 }, objectName: 'anggota ' + n });
    tb(s, n, { x: x + 0.25, y: y + 0.13, w: 3.7, h: 0.4, fontSize: 16, bold: true, color: C.background1, valign: 'middle' });
    tb(s, 'NPM ' + npm, { x: x + 3.95, y: y + 0.13, w: 2.25, h: 0.4, fontSize: 14, color: 'A9C4F5', align: 'right', valign: 'middle' });
});
{
    const p = fit('login.png', 7.75, 1.25, 5.0, 3.6);
    s.addShape(pres.shapes.RECTANGLE, { x: p.x - 0.06, y: p.y - 0.06, w: p.w + 0.12, h: p.h + 0.12, fill: { color: '163A66' }, line: { color: '2B5288', width: 0.75 }, objectName: 'bingkai login' });
    s.addImage({ path: img('login.png'), x: p.x, y: p.y, w: p.w, h: p.h, altText: 'Screenshot halaman Masuk aplikasi SIM Perpustakaan' });
    tb(s, 'Halaman Masuk aplikasi yang berjalan (screenshot nyata, 21 Nov 2026)', { x: 7.75, y: p.y + p.h + 0.15, w: 5.0, h: 0.3, fontSize: 11, color: 'A9C4F5', align: 'center' });
}
s.addNotes('Pembukaan. Perkenalkan ketiga anggota Kelompok 11 (Doni Suhendar, Abdurrahman Wahid, Sarah Tirta Kencana) dan topik: SIM Perpustakaan berbasis web. Sebutkan bahwa seluruh isi presentasi diambil dari repositori proyek: SRS v1.1, diagram UML, kode, dan hasil uji yang dijalankan ulang pada 21 November 2026.');

// 2 — LATAR BELAKANG
s = pres.addSlide({ masterName: 'KONTEN', sectionTitle: 'Pembuka' });
judul(s, 'Latar Belakang');
const lb = [
    ['Pencatatan ganda', 'Data buku, anggota, dan transaksi perlu dikelola dalam satu basis data.'],
    ['Ketertelusuran transaksi', 'Setiap pinjam, kembali, dan booking harus tercatat lengkap dengan waktu dan petugasnya.'],
    ['Ketersediaan per eksemplar', 'Status setiap eksemplar fisik harus divalidasi sebelum dipinjam atau dipesan.'],
    ['Penyajian laporan', 'Petugas membutuhkan laporan buku, anggota, peminjaman, dan pengembalian yang cepat.'],
];
lb.forEach(([h, d], i) => {
    const y = 1.45 + i * 1.3;
    lencana(s, 0.7, y + 0.1, i + 1);
    tb(s, h, { x: 1.4, y, w: 5.9, h: 0.4, fontSize: 19, bold: true, color: C.text2 });
    tb(s, d, { x: 1.4, y: y + 0.42, w: 5.9, h: 0.7, fontSize: 15 });
});
kartu(s, 7.8, 1.45, 4.9, 4.6, C.text2, 'kartu solusi');
tb(s, 'Solusi yang dirancang', { x: 8.2, y: 1.75, w: 4.2, h: 0.4, fontSize: 18, bold: true, color: 'A9C4F5' });
tb(s, 'Satu aplikasi web terpadu dengan satu basis data relasional. Setiap operasi kritis divalidasi di server Laravel; data persisten tidak disimpan di browser.', { x: 8.2, y: 2.25, w: 4.2, h: 2.0, fontSize: 17, color: C.background1 });
tb(s, 'Sumber: SRS v1.1 §1.4 (nilai yang diharapkan) dan §2.1 (perspektif produk)', { x: 8.2, y: 5.3, w: 4.2, h: 0.5, fontSize: 11, color: 'A9C4F5' });
s.addNotes('Latar belakang diambil langsung dari SRS v1.1 bagian 1.4 dan 2.1, bukan asumsi baru. Empat kebutuhan utama: menghindari pencatatan ganda, ketertelusuran transaksi, validasi ketersediaan per eksemplar, dan laporan.');

// 3 — RUMUSAN MASALAH
s = pres.addSlide({ masterName: 'KONTEN', sectionTitle: 'Pembuka' });
judul(s, 'Rumusan Masalah');
const rm = [
    'Bagaimana mengelola data buku, eksemplar, dan anggota dalam satu basis data tanpa pencatatan ganda?',
    'Bagaimana memastikan satu eksemplar tidak dipinjam atau dipesan ganda, termasuk saat ada permintaan bersamaan?',
    'Bagaimana anggota dapat mencari buku, mengajukan booking, serta memantau status dan riwayatnya sendiri?',
    'Bagaimana menyajikan laporan peminjaman dan pengembalian per periode secara cepat dan akurat?',
];
rm.forEach((t, i) => {
    const x = 0.6 + (i % 2) * 6.15, y = 1.5 + Math.floor(i / 2) * 2.55;
    kartu(s, x, y, 5.95, 2.25, C.background2, 'rumusan ' + (i + 1));
    lencana(s, x + 0.3, y + 0.3, i + 1, C.accent1, 0.55, 18);
    tb(s, t, { x: x + 1.1, y: y + 0.3, w: 4.55, h: 1.7, fontSize: 18, color: C.text1 });
});
tb(s, 'Dirumuskan dari SRS v1.1 §1.4 dan §2.2', { x: 0.6, y: 6.6, w: 6, h: 0.3, fontSize: 11, color: C.accent5 });
s.addNotes('Empat rumusan masalah ini diturunkan dari ruang lingkup dan fungsi produk di SRS. Masalah kedua, yaitu konkurensi, menjadi perhatian utama implementasi karena ditangani dengan transaksi dan penguncian baris MySQL.');

// 4 — TUJUAN
s = pres.addSlide({ masterName: 'KONTEN', sectionTitle: 'Pembuka' });
judul(s, 'Tujuan Sistem dan Manfaat');
kartu(s, 0.6, 1.45, 5.95, 5.2, C.background2, 'tujuan');
tb(s, 'Tujuan', { x: 0.95, y: 1.7, w: 5.3, h: 0.45, fontSize: 21, bold: true, color: C.accent1 });
tb(s, B([
    'Mengintegrasikan katalog, anggota, sirkulasi, booking, dan laporan dalam satu aplikasi web',
    'Menjaga status eksemplar selalu konsisten melalui transaksi ACID MySQL',
    'Menegakkan hak akses Admin dan Anggota di sisi server',
    'Menyediakan layanan mandiri bagi anggota: cari, booking, status, riwayat',
]), { x: 0.95, y: 2.3, w: 5.3, h: 4.1, fontSize: 16 });
kartu(s, 6.78, 1.45, 5.95, 5.2, C.background2, 'manfaat');
tb(s, 'Manfaat', { x: 7.13, y: 1.7, w: 5.3, h: 0.45, fontSize: 21, bold: true, color: C.accent2 });
tb(s, B([
    'Admin: transaksi tercatat lengkap dengan waktu dan petugas, laporan siap cetak',
    'Anggota: ketersediaan buku dapat dicek dan booking dapat diajukan sebelum datang',
    'Perpustakaan: data terpusat dan jejak audit untuk setiap perubahan penting',
    'Akademik: penerapan alur SRS → UML → kode → pengujian',
]), { x: 7.13, y: 2.3, w: 5.3, h: 4.1, fontSize: 16 });
s.addNotes('Tujuan mengacu pada SRS 1.1 dan 2.2. Manfaat dikelompokkan per pemangku kepentingan.');

// 5 — RUANG LINGKUP
s = pres.addSlide({ masterName: 'KONTEN', sectionTitle: 'Pembuka' });
judul(s, 'Ruang Lingkup');
const kol = [
    ['Admin', C.accent1, ['Dashboard ringkasan (agregasi SQL)', 'Kelola anggota + akses login', 'Kelola buku & eksemplar', 'Peminjaman & pengembalian', 'Verifikasi & realisasi booking', 'Laporan + cetak', 'Ubah kata sandi']],
    ['Anggota', C.accent2, ['Dashboard pribadi', 'Cari buku & detail eksemplar', 'Ajukan & batalkan booking', 'Lihat status booking', 'Lihat histori peminjaman', 'Ubah kata sandi (wajib saat login pertama)']],
    ['Di luar ruang lingkup', C.accent5, ['Peran Petugas terpisah & kelola role', 'Denda, kuota, perpanjangan (TBD-05)', 'Ekspor PDF/Excel/CSV (TBD-07)', 'RFID/barcode, SMS/WhatsApp', 'Aplikasi mobile native, SSO kampus', 'Server produksi / HTTPS (belum di-deploy)']],
];
kol.forEach(([h, col, items], i) => {
    const x = 0.6 + i * 4.1;
    kartu(s, x, 1.45, 3.9, 5.25, C.background2, 'lingkup ' + h);
    s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x: x + 0.3, y: 1.7, w: 3.3, h: 0.55, rectRadius: 0.1, fill: { color: col }, line: { color: col, width: 0 }, objectName: 'judul lingkup ' + h });
    tb(s, h, { x: x + 0.3, y: 1.7, w: 3.3, h: 0.55, fontSize: 18, bold: true, color: C.background1, align: 'center', valign: 'middle' });
    tb(s, B(items), { x: x + 0.35, y: 2.5, w: 3.25, h: 4.0, fontSize: 15 });
});
s.addNotes('Implementasi final hanya memakai dua peran, yaitu Admin dan Anggota. SRS v1.1 masih memuat Petugas; hak Petugas dijalankan oleh Admin berdasarkan keputusan desain 7 November 2026. Fitur di kolom kanan sengaja tidak dibuat atau masih menunggu keputusan TBD.');

// 6 — METODOLOGI
pres.addSection({ title: 'Metodologi & Teknologi' });
s = pres.addSlide({ masterName: 'KONTEN', sectionTitle: 'Metodologi & Teknologi' });
judul(s, 'Metodologi: Waterfall');
const fase = [
    ['Requirements', 'SRS v1.0 (Sep 2026)\nSRS v1.1 (24 Okt 2026)', C.accent1],
    ['Design', 'UML Tugas 3–7 (Okt 2026)\nPrototipe UI (7 Nov)', '2F6FDE'],
    ['Implementation', 'MySQL & autentikasi (14 Nov)\nCRUD, transaksi, booking, dashboard (16 Nov)', '3D7FEA'],
    ['Testing', 'PHPUnit · Node · Browser QA\nUji ulang 21 & 24 Nov', C.accent2],
    ['Maintenance', 'Rencana: TBD-05/06/07, penjadwal produksi, rotasi kredensial', C.accent5],
];
fase.forEach(([h, d, col], i) => {
    const x = 0.6 + i * 2.48, y = 2.0;
    s.addShape(pres.shapes.CHEVRON, { x, y, w: 2.45, h: 1.0, fill: { color: col }, line: { color: col, width: 0 }, objectName: 'fase ' + h });
    tb(s, h, { x: x + 0.35, y, w: 1.8, h: 1.0, fontSize: 15, bold: true, color: C.background1, align: 'center', valign: 'middle' });
    tb(s, d, { x: x + 0.1, y: 3.25, w: 2.25, h: 1.6, fontSize: 13, align: 'center' });
});
kartu(s, 0.6, 5.1, 12.1, 1.45, C.background2, 'catatan metodologi');
tb(s, 'Tanggal diambil dari Revision History SRS dan riwayat commit Git (7, 14, 16 Nov 2026). Setiap fase ditinjau sebelum lanjut ke fase berikutnya. Perubahan keputusan, misalnya peran final dan kebijakan kedaluwarsa booking, dicatat di docs/KEPUTUSAN_DESAIN.md.', { x: 0.95, y: 5.3, w: 11.4, h: 1.1, fontSize: 15 });
s.addNotes('SRS 1.4 menyebut metode Waterfall. Dalam praktiknya pekerjaan berjalan per fase dengan tinjauan di setiap akhir fase. Maintenance belum berjalan karena belum ada lingkungan produksi; isinya masih rencana.');

// 7 — TEKNOLOGI
s = pres.addSlide({ masterName: 'KONTEN', sectionTitle: 'Metodologi & Teknologi' });
judul(s, 'Teknologi yang Digunakan');
const tek = [
    ['Laravel 13.40', 'PHP 8.4 · routing, middleware, Form Request, Gate, Eloquent, scheduler', C.accent4],
    ['Vue 3.5', 'Composition API untuk seluruh halaman Admin & Anggota', C.accent2],
    ['Inertia.js 3', 'Jembatan controller ↔ halaman Vue tanpa REST API terpisah', C.accent6],
    ['Bootstrap 5.3.8', 'UI responsif; Inter & Bootstrap Icons dibundel lokal', C.accent1],
    ['MySQL 8.4 LTS', 'InnoDB, FK RESTRICT, CHECK, UNIQUE, SELECT … FOR UPDATE', '00758F'],
    ['Vite 8 · Node 20', 'Build aset frontend dan test runner Node', C.accent3],
    ['Google Stitch', 'Mockup UI/UX (pengganti Figma; perlu persetujuan dosen)', '4285F4'],
    ['GitHub · PHPUnit', 'Version control (PR #1–#5) dan pengujian otomatis (PHPUnit 12)', C.text1],
];
tek.forEach(([h, d, col], i) => {
    const x = 0.6 + (i % 4) * 3.05, y = 1.5 + Math.floor(i / 4) * 2.65;
    kartu(s, x, y, 2.85, 2.4, C.background2, 'teknologi ' + h);
    s.addShape(pres.shapes.OVAL, { x: x + 0.3, y: y + 0.3, w: 0.32, h: 0.32, fill: { color: col }, line: { color: col, width: 0 }, objectName: 'titik ' + h });
    tb(s, h, { x: x + 0.3, y: y + 0.75, w: 2.3, h: 0.45, fontSize: 18, bold: true, color: C.text2 });
    tb(s, d, { x: x + 0.3, y: y + 1.25, w: 2.3, h: 1.05, fontSize: 13 });
});
s.addNotes('Versi yang disebut adalah versi terpasang menurut composer.lock dan package.json, bukan perkiraan. Google Stitch dipakai sebagai alat mockup sebagai pengganti Figma; hal ini perlu disetujui dosen.');

// 8 — USE CASE
pres.addSection({ title: 'Rancangan UML' });
s = pres.addSlide({ masterName: 'KONTEN', sectionTitle: 'Rancangan UML' });
judul(s, 'Use Case Diagram');
bingkai(s, 'uc-impl.png', 0.6, 1.4, 8.4, 5.35, 'Use Case Diagram implementasi SIM Perpustakaan');
tb(s, 'Suplemen implementasi', { x: 9.35, y: 1.4, w: 3.4, h: 0.4, fontSize: 17, bold: true, color: C.text2 });
tb(s, B(['Aktor: Admin, Anggota, Penjadwal Laravel (sistem)', 'Ditambahkan: Verifikasi & Realisasi Booking, Status & Batalkan Booking, Ubah Kata Sandi, Kelola Eksemplar, Akses Login']), { x: 9.35, y: 1.85, w: 3.4, h: 2.2, fontSize: 14 });
bingkai(s, 'uc-asli.png', 9.35, 4.25, 3.4, 1.95, 'Use Case Diagram asli Tugas 3');
tb(s, 'Diagram asli Tugas 3 tetap dipertahankan (docs/uml)', { x: 9.35, y: 6.3, w: 3.4, h: 0.4, fontSize: 11, color: C.accent5, align: 'center' });
s.addNotes('Kiri: diagram suplemen yang disusun dari rute dan controller aktual. Kanan bawah: diagram asli Tugas 3, yang tidak diubah. Perbedaan utamanya adalah tambahan use case booking dari sisi Admin dan ubah kata sandi.');

// 9 — CLASS
s = pres.addSlide({ masterName: 'KONTEN', sectionTitle: 'Rancangan UML' });
judul(s, 'Class Diagram (Model Eloquent & MySQL)');
bingkai(s, 'class-impl.png', 0.6, 1.4, 7.6, 5.35, 'Class Diagram implementasi', 'left');
tb(s, '8 entitas domain', { x: 8.75, y: 1.4, w: 4.0, h: 0.4, fontSize: 17, bold: true, color: C.text2 });
tb(s, B([
    'User · Member · Book · BookCopy · Booking · Loan · BookReturn · AuditLog',
    'Book 1 ◆— 1..* BookCopy; Booking 1 — 0..1 Loan; Loan 1 — 0..1 BookReturn',
    'Semua FK RESTRICT (tanpa cascade); kode bisnis UNIQUE',
    'Kolom tersimpan active_copy_id & reserved_copy_id UNIQUE: maks. 1 pinjaman Aktif & 1 booking Disetujui per eksemplar',
    'Waktu disimpan UTC, ditampilkan WIB',
]), { x: 8.75, y: 1.85, w: 4.0, h: 4.4, fontSize: 14 });
tb(s, 'Class Diagram konseptual Tugas 6 tetap tersedia di docs/uml', { x: 8.75, y: 6.35, w: 4.0, h: 0.4, fontSize: 11, color: C.accent5 });
s.addNotes('Diagram ini dibuat dari app/Models, app/Enums, dan migration. Integritas tidak hanya dijaga di kode, tetapi juga di MySQL melalui UNIQUE, CHECK, dan FOREIGN KEY. Contohnya, dua pinjaman aktif untuk satu eksemplar ditolak oleh indeks unik.');

// 10 — ACTIVITY
s = pres.addSlide({ masterName: 'KONTEN', sectionTitle: 'Rancangan UML' });
judul(s, 'Activity Diagram');
bingkai(s, 'a04-asli.png', 0.6, 1.45, 5.9, 4.95, 'Activity Diagram A04 Peminjaman Buku (asli)');
tb(s, 'A04 Peminjaman Buku (diagram asli Tugas 4)', { x: 0.6, y: 6.5, w: 5.9, h: 0.35, fontSize: 12, color: C.accent5, align: 'center' });
bingkai(s, 'a10.png', 6.8, 1.45, 3.4, 4.95, 'Activity Diagram A10 Verifikasi Booking (suplemen)');
tb(s, 'A10 Verifikasi Booking (suplemen)', { x: 6.6, y: 6.5, w: 3.8, h: 0.35, fontSize: 12, color: C.accent5, align: 'center' });
tb(s, 'Cakupan', { x: 10.5, y: 1.45, w: 2.25, h: 0.4, fontSize: 17, bold: true, color: C.text2 });
tb(s, B(['9 diagram asli (A01–A09)', '3 suplemen: Verifikasi, Realisasi, Kedaluwarsa otomatis', 'Langkah suplemen mengikuti urutan pemeriksaan di service']), { x: 10.5, y: 1.9, w: 2.25, h: 4.3, fontSize: 14 });
s.addNotes('Kiri: alur peminjaman dari diagram asli. Kanan: suplemen verifikasi booking yang belum ada di diagram asli. Setiap percabangan pada suplemen sesuai dengan pemeriksaan di LayananBooking::setujui dan tolakBooking.');

// 11 — SEQUENCE
s = pres.addSlide({ masterName: 'KONTEN', sectionTitle: 'Rancangan UML' });
judul(s, 'Sequence Diagram: Peminjaman Langsung');
bingkai(s, 's04.png', 0.6, 1.4, 7.4, 5.35, 'Sequence Diagram implementasi peminjaman', 'left');
tb(s, 'Lapisan nyata', { x: 8.55, y: 1.4, w: 4.2, h: 0.4, fontSize: 17, bold: true, color: C.text2 });
tb(s, 'Vue 3 + Inertia → Form Request + Gate → Controller → Service → Eloquent → MySQL', { x: 8.55, y: 1.85, w: 4.2, h: 0.8, fontSize: 14 });
tb(s, B([
    'DB::transaction: kunci members → book_copies (FOR UPDATE)',
    'Cek anggota Aktif & eksemplar Tersedia setelah kunci dipegang',
    'INSERT loans + UPDATE eksemplar + audit dalam satu commit',
    'Deadlock diulang otomatis maks. 3×',
    'Pesan sukses dikirim setelah commit',
]), { x: 8.55, y: 2.8, w: 4.2, h: 3.4, fontSize: 14 });
tb(s, '8 sequence suplemen + 9 diagram asli (S01–S09)', { x: 8.55, y: 6.35, w: 4.2, h: 0.4, fontSize: 11, color: C.accent5 });
s.addNotes('Sequence ini memperlihatkan komponen yang benar-benar ada di kode. Kegagalan validasi dikembalikan sebagai ValidationException, yang pada Inertia berarti redirect back dengan pesan galat. Diagram asli memakai notasi boundary, control, dan entity, sedangkan suplemen memakai kelas konkret.');

// 12 — DEPLOYMENT
s = pres.addSlide({ masterName: 'KONTEN', sectionTitle: 'Rancangan UML' });
judul(s, 'Deployment Diagram');
label(s, 0.6, 1.35, 2.6, 'AKTUAL — lokal', C.accent2);
bingkai(s, 'dep-lokal.png', 0.6, 1.8, 7.9, 4.6, 'Deployment Diagram lingkungan lokal aktual', 'left');
label(s, 8.95, 1.35, 3.75, 'USULAN — belum di-deploy', C.accent3);
bingkai(s, 'dep-usulan.png', 8.95, 1.8, 3.75, 2.6, 'Deployment Diagram produksi usulan (asli Tugas 7)');
tb(s, B(['Nginx + PHP-FPM 8.4 + MySQL 8.4 di Linux', 'HTTPS, cron schedule:run, backup: belum ada', 'Lingkungan final masih TBD-10']), { x: 8.95, y: 4.6, w: 3.75, h: 1.8, fontSize: 13 });
tb(s, 'Tidak ada URL publik; aplikasi berjalan di 127.0.0.1:8000', { x: 0.6, y: 6.5, w: 7.9, h: 0.35, fontSize: 12, color: C.accent5 });
s.addNotes('Bedakan dengan tegas: yang berjalan hanya lingkungan lokal di laptop pengembang. Diagram kanan adalah rancangan produksi dari Tugas 7 dan belum diimplementasikan.');

// 13 — UI/UX STITCH
pres.addSection({ title: 'Desain & Implementasi' });
s = pres.addSlide({ masterName: 'KONTEN', sectionTitle: 'Desain & Implementasi' });
judul(s, 'UI/UX — Google Stitch (Pengganti Figma)');
tb(s, 'Proyek Stitch', { x: 0.6, y: 1.4, w: 5.3, h: 0.4, fontSize: 17, bold: true, color: C.text2 });
tb(s, 'stitch.google.com/projects/12475154501831437105', { x: 0.6, y: 1.82, w: 5.3, h: 0.35, fontSize: 13, color: C.accent1, hyperlink: { url: 'https://stitch.google.com/projects/12475154501831437105' } });
tb(s, 'Alur yang tercatat di Git:', { x: 0.6, y: 2.4, w: 5.3, h: 0.35, fontSize: 15, bold: true, color: C.text2 });
['UI dibangun sebagai prototipe Laravel + Vue (6–7 Nov)', '26 screenshot diekspor sebagai acuan untuk Stitch (commit 86552a1)', 'Mockup Stitch dibuat menyamai acuan; tautan dicantumkan 18 Nov'].forEach((t, i) => {
    lencana(s, 0.6, 2.85 + i * 0.75, i + 1, C.accent1, 0.42, 14);
    tb(s, t, { x: 1.15, y: 2.85 + i * 0.75, w: 4.75, h: 0.65, fontSize: 14 });
});
kartu(s, 0.6, 5.2, 5.3, 1.5, 'FFF7E6', 'catatan stitch');
tb(s, 'Instruksi dosen Tugas 9: Figma, “bukan dengan AI”. Stitch adalah alat berbasis AI, jadi wajib ada persetujuan dosen. Belum tersedia: ekspor layar Stitch dan akses tautan tanpa login.', { x: 0.85, y: 5.33, w: 4.85, h: 1.3, fontSize: 12, color: '78350F' });
bingkai(s, 'ref-admin-dashboard.png', 6.35, 1.45, 6.35, 2.75, 'Screenshot acuan Dashboard Admin');
bingkai(s, 'ref-cari-buku.png', 6.35, 4.35, 3.1, 1.95, 'Screenshot acuan Cari Buku');
bingkai(s, 'ref-verifikasi.png', 9.6, 4.35, 3.1, 1.95, 'Screenshot acuan Verifikasi Booking');
tb(s, 'Screenshot acuan yang diunggah ke Stitch (prototipe aplikasi, 7 Nov) — bukan ekspor Stitch', { x: 6.35, y: 6.4, w: 6.35, h: 0.4, fontSize: 11, color: C.accent5, align: 'center' });
s.addNotes('Sampaikan dengan jujur: tim memakai Google Stitch, bukan Figma. Materi dosen Pertemuan 9 halaman 17 meminta Figma dan menyebut bukan dengan AI, sedangkan Stitch adalah alat desain berbasis AI; karena itu penggunaannya memerlukan persetujuan dosen. Riwayat Git menunjukkan website dibuat lebih dulu lalu screenshot-nya dijadikan acuan mockup Stitch. Gambar di kanan adalah screenshot prototipe, bukan ekspor Stitch. Ekspor dari Stitch dan izin akses tautan masih harus disediakan pemilik proyek.');

// 14 — HASIL IMPLEMENTASI
s = pres.addSlide({ masterName: 'KONTEN', sectionTitle: 'Desain & Implementasi' });
judul(s, 'Hasil Implementasi');
[['admin-dashboard.png', 'Dashboard Admin — agregasi SQL'], ['anggota-dashboard.png', 'Dashboard Anggota — data milik sendiri'], ['data-buku.png', 'Data Buku & Eksemplar — pencarian server-side']].forEach(([f, c], i) => {
    const x = 0.6 + i * 4.1;
    bingkai(s, f, x, 1.55, 3.9, 2.44, c);
    tb(s, c, { x, y: 4.15, w: 3.9, h: 0.4, fontSize: 13, color: C.text2, bold: true, align: 'center' });
});
const chips = ['MySQL 8.4 · data nyata', '18 halaman · 2 peran', '8 tabel domain', 'Sesi server + CSRF', 'Audit log'];
chips.forEach((t, i) => label(s, 0.6 + i * 2.45, 4.95, 2.3, t, C.background2, C.text2, 12));
tb(s, 'Screenshot diambil 21 Nov 2026 dari aplikasi yang berjalan dengan database uji MySQL (data demo fiktif dari seeder). 31 screenshot lengkap tersedia di docs/TUGAS-10-FINAL/09-HASIL-SISTEM.', { x: 0.6, y: 5.6, w: 12.1, h: 0.8, fontSize: 14 });
s.addNotes('Ketiga gambar ini adalah screenshot aplikasi final yang terhubung ke MySQL, bukan mockup. Jumlah halaman: 1 halaman masuk, 12 halaman Admin, dan 5 halaman Anggota, ditambah halaman ubah kata sandi untuk setiap peran.');

// 15 — PEMINJAMAN & PENGEMBALIAN
s = pres.addSlide({ masterName: 'KONTEN', sectionTitle: 'Desain & Implementasi' });
judul(s, 'Proses Peminjaman & Pengembalian');
const st = (x, y, t, col) => { s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x, y, w: 1.75, h: 0.6, rectRadius: 0.1, fill: { color: col }, line: { color: col, width: 0 }, objectName: 'status ' + t + x }); tb(s, t, { x, y, w: 1.75, h: 0.6, fontSize: 15, bold: true, color: C.background1, align: 'center', valign: 'middle' }); };
const panah = (x1, y, x2, t) => { s.addShape(pres.shapes.LINE, { x: x1, y, w: x2 - x1, h: 0, line: { color: C.accent5, width: 2, endArrowType: 'triangle' }, objectName: 'panah ' + t }); tb(s, t, { x: x1, y: y - 0.42, w: x2 - x1, h: 0.35, fontSize: 12, color: C.accent5, align: 'center' }); };
tb(s, 'Eksemplar', { x: 0.6, y: 1.45, w: 2, h: 0.35, fontSize: 14, bold: true, color: C.text2 });
st(0.6, 1.95, 'Tersedia', C.accent2); panah(2.4, 2.25, 3.75, 'pinjam'); st(3.8, 1.95, 'Dipinjam', C.accent1); panah(5.6, 2.25, 6.95, 'kembali'); st(7.0, 1.95, 'Tersedia', C.accent2);
tb(s, 'Peminjaman', { x: 0.6, y: 2.85, w: 2, h: 0.35, fontSize: 14, bold: true, color: C.text2 });
st(3.8, 3.3, 'Aktif', C.accent1); panah(5.6, 3.6, 6.95, 'pengembalian'); st(7.0, 3.3, 'Selesai', C.accent5);
tb(s, B([
    'Satu transaksi InnoDB: INSERT loans/returns + UPDATE book_copies + audit_logs',
    'Kunci baris berurutan members → book_copies → loans; syarat dibaca ulang setelah kunci',
    'UNIQUE active_copy_id cegah dua pinjaman Aktif; UNIQUE returns.loan_id cegah pengembalian ganda',
    'Admin pemroses & waktu (UTC) tercatat; anggota Nonaktif tetap boleh mengembalikan',
]), { x: 0.6, y: 4.2, w: 8.4, h: 2.6, fontSize: 14 });
bingkai(s, 'pengembalian.png', 9.25, 1.5, 3.45, 2.2, 'Pengembalian berhasil diproses');
tb(s, 'Pengembalian KB-9172 berhasil (E2E 21 Nov)', { x: 9.25, y: 3.8, w: 3.45, h: 0.35, fontSize: 11, color: C.accent5, align: 'center' });
bingkai(s, 'realisasi.png', 9.25, 4.35, 3.45, 2.2, 'Bukti realisasi booking menjadi peminjaman');
tb(s, 'Realisasi BP-10429 → PJ-14229', { x: 9.25, y: 6.6, w: 3.45, h: 0.3, fontSize: 11, color: C.accent5, align: 'center' });
s.addNotes('Status eksemplar selalu berubah dalam transaksi yang sama dengan pembuatan peminjaman atau pengembalian. Jika satu langkah gagal, seluruhnya di-rollback. Pengujian konkurensi dengan dua koneksi MySQL membuktikan hanya satu pinjaman yang berhasil.');

// 16 — BOOKING & EXPIRATION
s = pres.addSlide({ masterName: 'KONTEN', sectionTitle: 'Desain & Implementasi' });
judul(s, 'Booking & Kedaluwarsa Otomatis');
const bs = (x, y, t, col, w = 1.85) => { s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x, y, w, h: 0.55, rectRadius: 0.1, fill: { color: col }, line: { color: col, width: 0 }, objectName: 'booking ' + t }); tb(s, t, { x, y, w, h: 0.55, fontSize: 14, bold: true, color: C.background1, align: 'center', valign: 'middle' }); };
const pn = (x1, y1, x2, y2, t) => { s.addShape(pres.shapes.LINE, { x: x1, y: y1, w: x2 - x1, h: y2 - y1, line: { color: C.accent5, width: 1.75, endArrowType: 'triangle' }, objectName: 'alur ' + t }); };
bs(0.6, 2.05, 'Diajukan', C.accent5); bs(3.3, 2.05, 'Disetujui', C.accent1); bs(6.0, 1.35, 'Direalisasikan', C.accent2); bs(6.0, 2.05, 'Kedaluwarsa', C.accent3); bs(6.0, 2.75, 'Dibatalkan', '475569'); bs(3.3, 2.95, 'Ditolak', C.accent4);
pn(2.45, 2.32, 3.3, 2.32, 'a'); pn(5.15, 2.2, 6.0, 1.62, 'b'); pn(5.15, 2.32, 6.0, 2.32, 'c'); pn(5.15, 2.45, 6.0, 3.02, 'd'); pn(1.5, 2.6, 3.3, 3.22, 'e');
tb(s, 'eksemplar → Dipesan', { x: 3.1, y: 1.65, w: 2.3, h: 0.3, fontSize: 11, color: C.accent5, align: 'center' });
tb(s, 'Diajukan juga dapat dibatalkan Anggota', { x: 0.6, y: 3.45, w: 2.6, h: 0.3, fontSize: 11, color: C.accent5 });
kartu(s, 8.35, 1.35, 2.1, 1.95, C.text2, 'stat 48');
tb(s, '48 jam', { x: 8.35, y: 1.5, w: 2.1, h: 0.9, fontSize: 36, bold: true, color: C.background1, align: 'center', valign: 'middle' });
tb(s, 'batas pengambilan sejak disetujui', { x: 8.5, y: 2.4, w: 1.8, h: 0.75, fontSize: 12, color: 'A9C4F5', align: 'center' });
kartu(s, 10.6, 1.35, 2.1, 1.95, C.text2, 'stat 5');
tb(s, '5 menit', { x: 10.6, y: 1.5, w: 2.1, h: 0.9, fontSize: 36, bold: true, color: C.background1, align: 'center', valign: 'middle' });
tb(s, 'interval bookings:expire (scheduler)', { x: 10.75, y: 2.4, w: 1.8, h: 0.75, fontSize: 12, color: 'A9C4F5', align: 'center' });
tb(s, B([
    'Diajukan tidak mereservasi; reservasi baru terjadi saat Admin menyetujui',
    'expires_at = decided_at + 48 jam (UTC), ditampilkan sebagai Batas Pengambilan WIB',
    'Lewat batas → Kedaluwarsa, eksemplar dilepas, audit aktor = sistem; idempoten',
    'Bila penjadwal tertunda: realisasi/persetujuan/pembatalan memeriksa ulang batas di dalam transaksi',
    'Masih TBD-06: masa berlaku booking Diajukan, kuota, antrean',
]), { x: 0.6, y: 3.85, w: 7.4, h: 3.0, fontSize: 14 });
bingkai(s, 'booking-disetujui.png', 8.35, 3.8, 4.35, 2.72, 'Verifikasi Booking setelah persetujuan dengan Batas Pengambilan');
tb(s, 'Persetujuan BP-10429 dengan Batas Pengambilan', { x: 8.35, y: 6.6, w: 4.35, h: 0.3, fontSize: 11, color: C.accent5, align: 'center' });
s.addNotes('Kebijakan 48 jam adalah keputusan yang disetujui pemilik proyek (migration 000009). Penjadwal berjalan tiap 5 menit tanpa tumpang tindih. Di produksi diperlukan cron yang menjalankan schedule:run setiap menit, dan ini belum dipasang.');

// 17 — TESTING
pres.addSection({ title: 'Pengujian & Penutup' });
s = pres.addSlide({ masterName: 'KONTEN', sectionTitle: 'Pengujian & Penutup' });
judul(s, 'Software Testing');
const stats = [
    ['270/270', 'PHPUnit', '3.466 assertions · unit, feature, MySQL, konkurensi', C.accent1],
    ['245/245', 'Node.js', '46 suite · logika & privasi data frontend', C.accent2],
    ['23/23', 'Browser QA lama', 'Cek tercatat; hanya 7 ber-assertion eksplisit, 8 tanpa assertion', C.accent3],
    ['22/22', 'E2E demo terverifikasi', 'Setiap mutasi diverifikasi dengan kueri MySQL', C.accent6],
];
stats.forEach(([n, h, d, col], i) => {
    const x = 0.6 + i * 3.05;
    kartu(s, x, 1.45, 2.85, 2.6, C.background2, 'stat ' + h);
    tb(s, n, { x: x + 0.2, y: 1.6, w: 2.45, h: 0.95, fontSize: 40, bold: true, color: col, align: 'center', valign: 'middle' });
    tb(s, h, { x: x + 0.2, y: 2.6, w: 2.45, h: 0.4, fontSize: 15, bold: true, color: C.text2, align: 'center' });
    tb(s, d, { x: x + 0.2, y: 3.05, w: 2.45, h: 0.85, fontSize: 12, align: 'center' });
});
tb(s, 'Dijalankan ulang 21 & 24 Nov 2026 (24 Nov: setelah remediasi kredensial skrip QA) · PHP 8.4.27 · Node 20.19 · MySQL 8.4.4 · DB uji terpisah · DB development tidak berubah', { x: 0.6, y: 4.25, w: 12.1, h: 0.45, fontSize: 12, color: C.accent5 });
kartu(s, 0.6, 4.85, 12.1, 1.85, 'FFF7E6', 'keterbatasan uji');
tb(s, 'Keterbatasan', { x: 0.9, y: 4.98, w: 4, h: 0.35, fontSize: 15, bold: true, color: '78350F' });
tb(s, B(['Deadlock MySQL nyata tidak direproduksi (yang diuji: lock wait & balapan dua koneksi)', 'Uji beban 25 sesi, HTTPS, backup-restore, dan UAT belum dilakukan', 'Penjadwal produksi belum dipasang; TBD-05/06/07 masih terbuka'], { color: '78350F' }), { x: 0.9, y: 5.35, w: 11.5, h: 1.3, fontSize: 13, color: '78350F' });
s.addNotes('Semua angka berasal dari eksekusi ulang pada 21 November dan dikonfirmasi lagi pada 24 November setelah kredensial dihapus dari skrip QA; log-nya tersimpan di folder 08-SOFTWARE-TESTING/bukti. Bedakan empat angka ini. PHPUnit dan Node adalah tes otomatis yang semuanya memiliki assertion. Browser QA lama 23/23 adalah jumlah cek yang tercatat: hanya 7 cek memiliki assertion eksplisit, 8 cek hanya mengklik tanpa memeriksa hasil, dan langkah pengembalian serta pembatalan tidak tereksekusi. Karena itu ditambahkan E2E demo 22 langkah yang memeriksa status MySQL di setiap mutasi.');

// 18 — KESIMPULAN & DEMO
s = pres.addSlide({ masterName: 'GELAP', sectionTitle: 'Pengujian & Penutup' });
tb(s, 'Kesimpulan & Demo', { x: 0.7, y: 0.5, w: 12, h: 0.8, fontSize: 34, bold: true, color: C.background1 });
tb(s, 'Pencapaian', { x: 0.7, y: 1.5, w: 5.6, h: 0.4, fontSize: 18, bold: true, color: 'A9C4F5' });
[
    'Fitur inti SRS berjalan dengan MySQL 8.4: autentikasi, anggota, buku–eksemplar, pinjam, kembali, booking, laporan',
    'Integritas dijaga di dua lapis: service terkunci + constraint basis data',
    'Kedaluwarsa booking 48 jam otomatis dan aman terhadap konkurensi',
    '270 + 245 tes otomatis lulus; E2E demo terverifikasi',
].forEach((t, i) => {
    const y = 2.0 + i * 0.85;
    s.addShape(pres.shapes.OVAL, { x: 0.7, y: y + 0.08, w: 0.22, h: 0.22, fill: { color: C.accent2 }, line: { color: C.accent2, width: 0 }, objectName: 'poin capaian ' + (i + 1) });
    tb(s, t, { x: 1.1, y, w: 5.3, h: 0.8, fontSize: 15, color: C.background1 });
});
s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x: 6.8, y: 1.45, w: 5.85, h: 4.0, rectRadius: 0.12, fill: { color: '163A66' }, line: { color: '2B5288', width: 0.75 }, objectName: 'agenda demo' });
tb(s, 'Agenda demo', { x: 7.1, y: 1.6, w: 5.3, h: 0.4, fontSize: 18, bold: true, color: 'A9C4F5' });
const agenda = ['Admin masuk', 'Tambah anggota + akses login', 'Tambah buku + 2 eksemplar', 'Anggota masuk & ganti kata sandi', 'Anggota ajukan booking', 'Admin setujui (batas 48 jam)', 'Admin realisasikan', 'Admin proses pengembalian', 'Admin lihat laporan', 'Anggota lihat histori'];
agenda.forEach((t, i) => {
    const x = 7.1 + Math.floor(i / 5) * 2.8, y = 2.15 + (i % 5) * 0.62;
    lencana(s, x, y, i + 1, C.accent1, 0.4, 12);
    tb(s, t, { x: x + 0.5, y: y - 0.02, w: 2.25, h: 0.5, fontSize: 13, color: C.background1, valign: 'middle' });
});
s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x: 0.7, y: 5.75, w: 11.95, h: 1.15, rectRadius: 0.1, fill: { color: '3A2A0A' }, line: { color: C.accent3, width: 1 }, objectName: 'status kesiapan' });
tb(s, 'Status: siap dengan keterbatasan. Aplikasi masih lokal. Menunggu pemilik: rotasi kredensial QA sebelum repositori dibuka, serta ekspor, akses tautan, dan persetujuan dosen atas Google Stitch (alat berbasis AI) sebagai pengganti Figma.', { x: 1.0, y: 5.9, w: 11.4, h: 0.9, fontSize: 14, color: 'FDE68A', valign: 'middle' });
s.addNotes('Tutup dengan pencapaian, lalu jalankan demo sesuai DEMO_SCENARIO.md. Sampaikan keterbatasan dengan terbuka: belum ada produksi, kredensial QA yang pernah ter-commit harus dirotasi sebelum repositori dibuka, dan bukti desain Stitch masih harus dilengkapi serta disetujui dosen.');

(async () => {
    await pres.writeFile({ fileName: OUT });
    await applyTheme(OUT, THEME);
    console.log('ok', OUT);
})().catch((e) => { console.error(e); process.exit(1); });
