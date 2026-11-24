// Generator Use Case Diagram implementasi (SVG UML). Pemakaian: node gen-usecase.mjs <out.svg>
import { writeFileSync } from 'node:fs';
const OUT = process.argv[2];
const W = 1900, H = 1250;
const F = "Arial, Helvetica, sans-serif";
const ink = '#1f2937', muted = '#4b5563', line = '#334155', dash = '#475569';
let s = [];
const esc = (t) => t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const txt = (x, y, t, o = {}) => s.push(`<text x="${x}" y="${y}" font-family="${F}" font-size="${o.size || 17}" fill="${o.fill || ink}" text-anchor="${o.anchor || 'middle'}" font-weight="${o.weight || 'normal'}" ${o.italic ? 'font-style="italic"' : ''} dominant-baseline="middle">${esc(t)}</text>`);

const UC = {};
function uc(id, x, y, label, rx = 165, ry = 34) {
    UC[id] = { x, y, rx, ry };
    s.push(`<ellipse cx="${x}" cy="${y}" rx="${rx}" ry="${ry}" fill="#ffffff" stroke="${line}" stroke-width="2"/>`);
    txt(x, y, label);
}
function actor(x, y, label, sub) {
    s.push(`<g stroke="${line}" stroke-width="2.4" fill="none"><circle cx="${x}" cy="${y - 62}" r="18" fill="#fff"/><line x1="${x}" y1="${y - 44}" x2="${x}" y2="${y + 10}"/><line x1="${x - 34}" y1="${y - 22}" x2="${x + 34}" y2="${y - 22}"/><line x1="${x}" y1="${y + 10}" x2="${x - 28}" y2="${y + 58}"/><line x1="${x}" y1="${y + 10}" x2="${x + 28}" y2="${y + 58}"/></g>`);
    txt(x, y + 84, label, { size: 20, weight: 'bold' });
    if (sub) txt(x, y + 108, sub, { size: 14, fill: muted });
}
// titik pada tepi elips menuju (tx,ty)
function edge(u, tx, ty) {
    const dx = tx - u.x, dy = ty - u.y;
    const t = 1 / Math.sqrt((dx * dx) / (u.rx * u.rx) + (dy * dy) / (u.ry * u.ry));
    return [u.x + dx * t, u.y + dy * t];
}
function assoc(ax, ay, id) {
    const u = UC[id]; const ex = ax < u.x ? u.x - u.rx : u.x + u.rx, ey = u.y; // tempel di titik terluar elips
    s.push(`<line x1="${ax}" y1="${ay}" x2="${ex.toFixed(1)}" y2="${ey.toFixed(1)}" stroke="${line}" stroke-width="1.8"/>`);
}
function dep(fromId, toId, label, pos = 0.5, dxLabel = 0, dyLabel = -12, sisi = false) {
    const a = UC[fromId], b = UC[toId];
    const [x1, y1] = sisi ? [a.x < b.x ? a.x + a.rx : a.x - a.rx, a.y] : edge(a, b.x, b.y);
    const [x2, y2] = edge(b, x1, y1);
    s.push(`<line x1="${x1.toFixed(1)}" y1="${y1.toFixed(1)}" x2="${x2.toFixed(1)}" y2="${y2.toFixed(1)}" stroke="${dash}" stroke-width="1.6" stroke-dasharray="8 6" marker-end="url(#open)"/>`);
    const lx = x1 + (x2 - x1) * pos + dxLabel, ly = y1 + (y2 - y1) * pos + dyLabel;
    s.push(`<rect x="${lx - 34}" y="${ly - 10}" width="68" height="20" fill="#fff" opacity="0.92"/>`);
    txt(lx, ly, label, { size: 13, fill: muted });
}

s.push(`<rect width="${W}" height="${H}" fill="#ffffff"/>`);
s.push(`<defs><marker id="open" viewBox="0 0 12 12" refX="11" refY="6" markerWidth="12" markerHeight="12" orient="auto-start-reverse"><path d="M1,1 L11,6 L1,11" fill="none" stroke="${dash}" stroke-width="1.6"/></marker></defs>`);
txt(W / 2, 30, 'Use Case Diagram Implementasi (Suplemen) — SIM Perpustakaan, kode commit 80e2f5f', { size: 22, weight: 'bold' });
// batas sistem
s.push(`<rect x="300" y="62" width="1300" height="1040" rx="6" fill="#f8fafc" stroke="${line}" stroke-width="2"/>`);
txt(320, 86, 'Sistem Informasi Manajemen Perpustakaan', { anchor: 'start', size: 16, weight: 'bold', fill: muted });

// Use case Admin
uc('anggota', 540, 140, 'Kelola Data Anggota');
uc('buku', 540, 240, 'Kelola Data Buku');
uc('pinjam', 540, 370, 'Kelola Peminjaman');
uc('kembali', 540, 470, 'Kelola Pengembalian');
uc('verif', 540, 570, 'Verifikasi Booking');
uc('real', 540, 670, 'Realisasi Booking');
uc('lap', 540, 790, 'Kelola Laporan');
// Kolom tengah
uc('akses', 960, 140, 'Kelola Akses Login Anggota', 170);
uc('eks', 960, 240, 'Kelola Eksemplar', 150);
uc('login', 960, 560, 'Login', 130, 40);
uc('cetak', 960, 790, 'Cetak Laporan', 140);
// Use case Anggota
uc('cari', 1385, 300, 'Cari Buku');
uc('ajukan', 1385, 400, 'Ajukan Booking');
uc('status', 1385, 500, 'Lihat Status Booking');
uc('batal', 1385, 600, 'Batalkan Booking');
uc('history', 1385, 700, 'Lihat Histori Peminjaman');
uc('sandi', 1385, 800, 'Ubah Kata Sandi');
uc('exp', 1385, 1010, 'Kedaluwarsakan Booking Otomatis', 180);

// Aktor
actor(140, 450, 'Admin');
actor(1770, 520, 'Anggota');
actor(1770, 975, 'Penjadwal Laravel', '«system» bookings:expire');

for (const id of ['anggota', 'buku', 'pinjam', 'kembali', 'verif', 'real', 'lap']) assoc(176, 428, id);
for (const id of ['cari', 'ajukan', 'status', 'batal', 'history', 'sandi']) assoc(1734, 498, id);
assoc(1734, 953, 'exp');

// include / extend
dep('akses', 'anggota', '«extend»', 0.5, 0, 0); // extend: dari perluasan ke use case dasar
dep('buku', 'eks', '«include»', 0.5, 0, 0);
dep('cetak', 'lap', '«extend»', 0.5, 0, 0);
// Login: semua use case aktor «include» Login
const keLogin = [['anggota', 0.62], ['buku', 0.6], ['pinjam', 0.42], ['kembali', 0.45], ['verif', 0.45], ['real', 0.45], ['lap', 0.35],
    ['cari', 0.4], ['ajukan', 0.42], ['status', 0.45], ['batal', 0.45], ['history', 0.42], ['sandi', 0.35]];
for (const [id, p] of keLogin) dep(id, 'login', '«include»', p, 0, 0, true);

// Catatan
s.push(`<rect x="300" y="1118" width="1300" height="112" rx="6" fill="#fffbeb" stroke="#d97706" stroke-width="1.4"/>`);
const cat = [
    'CATATAN: Suplemen ini TIDAK menggantikan Use Case Diagram asli (docs/uml/Use Case - SIM Perpustakaan.drawio.png). Disusun dari rute & controller aktual.',
    'Ditambahkan vs. diagram asli: Verifikasi Booking, Realisasi Booking, Lihat Status Booking, Batalkan Booking, Ubah Kata Sandi, Kelola Akses Login Anggota, Kelola Eksemplar,',
    'dan proses otomatis kedaluwarsa booking 48 jam. Peran final hanya Admin dan Anggota (Petugas SRS v1.1 dijalankan oleh Admin). Admin juga memiliki halaman',
    'Ubah Kata Sandi (/admin/kata-sandi) — asosiasinya tidak digambar agar garis tidak bersilangan. Garis solid = asosiasi aktor; panah putus-putus = «include» / «extend».',
];
cat.forEach((t, i) => txt(318, 1140 + i * 24, t, { anchor: 'start', size: 14, fill: '#78350f' }));

writeFileSync(OUT, `<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}" viewBox="0 0 ${W} ${H}">\n${s.join('\n')}\n</svg>\n`);
console.log('ok', OUT);
