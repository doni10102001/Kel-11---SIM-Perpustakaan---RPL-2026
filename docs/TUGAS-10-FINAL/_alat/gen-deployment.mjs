// Generator Deployment Diagram lokal AKTUAL (SVG UML). Pemakaian: node gen-deployment.mjs <out.svg>
import { writeFileSync } from 'node:fs';
const OUT = process.argv[2];
const W = 1800, H = 940;
const F = 'Arial, Helvetica, sans-serif';
const ink = '#1f2937', muted = '#4b5563', line = '#334155';
const s = [];
const esc = (t) => t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const txt = (x, y, t, o = {}) => s.push(`<text x="${x}" y="${y}" font-family="${F}" font-size="${o.size || 16}" fill="${o.fill || ink}" text-anchor="${o.anchor || 'start'}" font-weight="${o.weight || 'normal'}" dominant-baseline="middle">${esc(t)}</text>`);
function node(x, y, w, h, stereo, nama, fill = '#ffffff', d = 14) {
    s.push(`<polygon points="${x},${y} ${x + d},${y - d} ${x + w + d},${y - d} ${x + w},${y}" fill="#e2e8f0" stroke="${line}" stroke-width="1.6"/>`);
    s.push(`<polygon points="${x + w},${y} ${x + w + d},${y - d} ${x + w + d},${y + h - d} ${x + w},${y + h}" fill="#cbd5e1" stroke="${line}" stroke-width="1.6"/>`);
    s.push(`<rect x="${x}" y="${y}" width="${w}" height="${h}" fill="${fill}" stroke="${line}" stroke-width="1.8"/>`);
    txt(x + 14, y + 20, stereo, { size: 14, fill: muted });
    txt(x + 14, y + 42, nama, { size: 17, weight: 'bold' });
}
function artefak(x, y, w, h, stereo, baris) {
    s.push(`<path d="M${x},${y} H${x + w - 16} L${x + w},${y + 16} V${y + h} H${x} Z" fill="#f8fafc" stroke="${line}" stroke-width="1.5"/>`);
    s.push(`<path d="M${x + w - 16},${y} V${y + 16} H${x + w}" fill="none" stroke="${line}" stroke-width="1.5"/>`);
    txt(x + 12, y + 18, stereo, { size: 13, fill: muted });
    baris.forEach((b, i) => txt(x + 12, y + 40 + i * 21, b, { size: 15, weight: i === 0 ? 'bold' : 'normal' }));
}
function garis(x1, y1, x2, y2, { putus = false, panah = true, label, lx, ly, anchor = 'middle' } = {}) {
    s.push(`<line x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}" stroke="${line}" stroke-width="1.8" ${putus ? 'stroke-dasharray="8 6"' : ''} ${panah ? 'marker-end="url(#ar)"' : ''}/>`);
    if (label) {
        const ls = Array.isArray(label) ? label : [label];
        ls.forEach((t, i) => { const yy = (ly ?? (y1 + y2) / 2 - 14) + i * 19, xx = lx ?? (x1 + x2) / 2, w = t.length * 7.6 + 10; s.push(`<rect x="${xx - w / 2}" y="${yy - 10}" width="${w}" height="20" fill="#ffffff" opacity="0.95"/>`); });
        ls.forEach((t, i) => txt(lx ?? (x1 + x2) / 2, (ly ?? (y1 + y2) / 2 - 14) + i * 19, t, { size: 14, fill: muted, anchor }));
    }
}

s.push(`<rect width="${W}" height="${H}" fill="#ffffff"/>`);
s.push(`<defs><marker id="ar" viewBox="0 0 12 12" refX="11" refY="6" markerWidth="11" markerHeight="11" orient="auto"><path d="M1,1 L11,6 L1,11" fill="none" stroke="${line}" stroke-width="1.6"/></marker></defs>`);
txt(W / 2, 28, 'Deployment Diagram — Lingkungan Pengembangan Lokal AKTUAL (Suplemen, November 2026)', { size: 22, weight: 'bold', anchor: 'middle' });

node(30, 70, 1720, 700, '«device»', 'Laptop pengembang — macOS (arm64)', '#f8fafc', 18);

node(70, 160, 380, 300, '«executionEnvironment»', 'Google Chrome (peramban)');
artefak(95, 240, 330, 120, '«artifact»', ['SPA Vue 3 + Inertia.js', 'Bootstrap 5.3 · Inter · Bootstrap Icons', '(aset dibundel lokal, tanpa CDN)']);

node(560, 160, 620, 420, '«executionEnvironment»', 'PHP 8.4 CLI (Homebrew)');
artefak(585, 240, 270, 90, '«process»', ['php artisan serve', '127.0.0.1 port 8000']);
artefak(585, 380, 270, 110, '«process»', ['php artisan schedule:work', 'bookings:expire', 'tiap 5 menit (withoutOverlapping)']);
artefak(890, 240, 265, 250, '«artifact»', ['Aplikasi Laravel 13', 'Routes & Middleware', '(auth, peran, akun.aktif)', 'Form Request + Gate', 'Controller', 'Service (transaksi)', 'Eloquent ORM', 'Inertia response']);
garis(855, 285, 888, 285);
garis(855, 435, 888, 435);

node(560, 610, 620, 140, '«executionEnvironment»', 'Node.js 20');
artefak(585, 672, 570, 60, '«process»', ['Vite 8 — npm run dev (HMR) atau npm run build → public/build']);

node(1290, 160, 430, 420, '«executionEnvironment»', 'MySQL 8.4 LTS (127.0.0.1:3306)');
artefak(1320, 240, 370, 120, '«schema»', ['sim_perpustakaan_db', 'data development / demo', 'InnoDB · utf8mb4 · waktu UTC']);
artefak(1320, 420, 370, 120, '«schema»', ['sim_perpustakaan_test', 'khusus pengujian otomatis', '(PHPUnit & Browser QA)']);

garis(425, 285, 583, 285, { label: ['HTTP lokal', 'Inertia XHR', 'sesi · CSRF'], lx: 505, ly: 218 });
garis(260, 362, 580, 700, { putus: true, label: ['aset JS/CSS'], lx: 360, ly: 560 });
garis(1155, 300, 1318, 300, { label: ['PDO MySQL', 'transaksi', 'FOR UPDATE'], lx: 1237, ly: 232 });
garis(1155, 470, 1318, 480, { putus: true, label: ['koneksi', 'mysql_testing'], lx: 1237, ly: 438 });

s.push(`<rect x="30" y="800" width="1720" height="110" rx="6" fill="#fffbeb" stroke="#d97706" stroke-width="1.4"/>`);
[
    'CATATAN: Hanya lingkungan ini yang benar-benar dipakai. TIDAK ada server produksi, domain, HTTPS, cron/systemd untuk schedule:run, maupun backup yang sudah dipasang.',
    'Rancangan produksi (Nginx + PHP-FPM 8.4 + MySQL 8.4 di Linux, HTTPS) adalah USULAN pada Deployment Diagram asli: docs/uml/Development Diagram.drawio.png (TBD-10).',
    'Instance MySQL lokal dipakai bersama proyek lain di mesin pengembang; aplikasi hanya memakai dua skema di atas dengan akun basis data terpisah.',
].forEach((t, i) => txt(48, 828 + i * 26, t, { size: 15, fill: '#78350f' }));

writeFileSync(OUT, `<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}" viewBox="0 0 ${W} ${H}">\n${s.join('\n')}\n</svg>\n`);
console.log('ok', OUT);
