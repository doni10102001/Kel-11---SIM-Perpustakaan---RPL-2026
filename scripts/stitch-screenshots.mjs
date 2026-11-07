#!/usr/bin/env node
/**
 * Ekspor screenshot PNG seluruh halaman final untuk Google Stitch (khusus pengembang).
 *
 * Prasyarat: aplikasi berjalan lokal (php artisan serve) dengan aset hasil `npm run build`.
 * Pemakaian:
 *   npm run screenshots:stitch                      # basis http://127.0.0.1:8000
 *   npm run screenshots:stitch -- --base=http://127.0.0.1:8013
 *   CHROME_PATH=/path/to/chrome npm run screenshots:stitch
 *
 * Tanpa dependency tambahan: Chrome headless dikendalikan lewat DevTools Protocol.
 * Node 20 memerlukan flag --experimental-websocket (sudah ada di skrip npm).
 *
 * Keluaran (docs/stitch/):
 *   screenshots/**.png, SCREENSHOT_MANIFEST.md, SIM_PERPUSTAKAAN_SCREENSHOTS.zip
 */
import { execFileSync, spawn } from 'node:child_process';
import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { inflateSync } from 'node:zlib';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const OUT = join(ROOT, 'docs', 'stitch');
const SHOTS = join(OUT, 'screenshots');
const BASE = (process.argv.find((a) => a.startsWith('--base='))?.split('=')[1] ?? process.env.STITCH_BASE ?? 'http://127.0.0.1:8000').replace(/\/$/, '');
const CHROME = process.env.CHROME_PATH ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const LEBAR = 1440;
const TINGGI = 900;
const PORT_DEBUG = 9350;

/** Halaman utama (full-page). `query` = state deterministik yang sudah didukung aplikasi. */
const HALAMAN = [
    { file: '01-login.png', peran: '—', judul: 'Masuk', path: '/login', h1: 'Masuk', presentasi: true, catatan: 'Mode presentasi: kotak "Mode pratinjau" disembunyikan saat capture.' },
    { file: 'admin/01-dashboard.png', peran: 'Admin', judul: 'Dashboard', path: '/demo/admin/dashboard', h1: 'Dashboard' },
    { file: 'admin/02-data-anggota.png', peran: 'Admin', judul: 'Data Anggota', path: '/demo/admin/anggota', h1: 'Data Anggota' },
    { file: 'admin/03-tambah-anggota.png', peran: 'Admin', judul: 'Tambah Anggota', path: '/demo/admin/anggota/tambah', h1: 'Tambah Anggota' },
    { file: 'admin/04-ubah-anggota.png', peran: 'Admin', judul: 'Ubah Anggota', path: '/demo/admin/anggota/AG-001/ubah', h1: 'Ubah Anggota', catatan: 'Contoh anggota AG-001.' },
    { file: 'admin/05-data-buku.png', peran: 'Admin', judul: 'Data Buku & Eksemplar', path: '/demo/admin/buku', h1: 'Data Buku & Eksemplar' },
    { file: 'admin/06-tambah-buku.png', peran: 'Admin', judul: 'Tambah Buku', path: '/demo/admin/buku/tambah', h1: 'Tambah Buku' },
    { file: 'admin/07-ubah-buku.png', peran: 'Admin', judul: 'Ubah Buku', path: '/demo/admin/buku/BK-001/ubah', h1: 'Ubah Buku', catatan: 'Contoh buku BK-001 (3 eksemplar: Tersedia & Dipinjam).' },
    { file: 'admin/08-peminjaman.png', peran: 'Admin', judul: 'Peminjaman Buku', path: '/demo/admin/peminjaman', h1: 'Peminjaman Buku' },
    { file: 'admin/09-pengembalian.png', peran: 'Admin', judul: 'Pengembalian Buku', path: '/demo/admin/pengembalian', h1: 'Pengembalian Buku' },
    { file: 'admin/10-verifikasi-booking.png', peran: 'Admin', judul: 'Verifikasi Booking', path: '/demo/admin/booking/verifikasi', h1: 'Verifikasi Booking', catatan: 'Tab bawaan: Diajukan.' },
    { file: 'admin/11-realisasi-booking.png', peran: 'Admin', judul: 'Realisasi Booking', path: '/demo/admin/booking/realisasi?booking=BP-001', h1: 'Realisasi Booking', catatan: 'Booking BP-001 terpilih (query `?booking=`).' },
    { file: 'admin/12-laporan.png', peran: 'Admin', judul: 'Laporan', path: '/demo/admin/laporan?jenis=peminjaman', h1: 'Laporan', catatan: 'Laporan Peminjaman seluruh periode (query `?jenis=`).' },
    { file: 'anggota/01-dashboard.png', peran: 'Anggota', judul: 'Dashboard', path: '/demo/anggota/dashboard', h1: 'Dashboard', catatan: 'Akun AG-001 Nadia Rahmawati.' },
    { file: 'anggota/02-cari-buku.png', peran: 'Anggota', judul: 'Cari Buku', path: '/demo/anggota/cari-buku', h1: 'Cari Buku' },
    { file: 'anggota/03-ajukan-booking.png', peran: 'Anggota', judul: 'Ajukan Booking', path: '/demo/anggota/booking/ajukan?eksemplar=EK-004', h1: 'Ajukan Booking', catatan: 'Eksemplar EK-004 terpilih (query `?eksemplar=`).' },
    { file: 'anggota/04-status-booking.png', peran: 'Anggota', judul: 'Status Booking', path: '/demo/anggota/booking/status', h1: 'Status Booking' },
    { file: 'anggota/05-history-peminjaman.png', peran: 'Anggota', judul: 'History Peminjaman', path: '/demo/anggota/history', h1: 'History Peminjaman' },
];

/** State tambahan (panel/dialog) — viewport 1440×900, tidak menggantikan screenshot utama. */
const STATE = [
    { file: 'admin/states/02a-data-anggota-panel-detail.png', peran: 'Admin', judul: 'Data Anggota — panel Detail Anggota', path: '/demo/admin/anggota', h1: 'Data Anggota', aksi: "document.querySelector('.sim-table tbody tr:nth-child(1) button[aria-label^=\"Lihat\"]').click()", tunggu: '.sim-drawer' },
    { file: 'admin/states/03a-tambah-anggota-akses-login.png', peran: 'Admin', judul: 'Tambah Anggota — Akses Login Anggota diaktifkan', path: '/demo/admin/anggota/tambah', h1: 'Tambah Anggota', aksi: "document.getElementById('buat-akses-login').click()", tunggu: '#isian-akses-login', penuh: true },
    { file: 'admin/states/04a-ubah-anggota-reset-kata-sandi.png', peran: 'Admin', judul: 'Ubah Anggota — Reset Kata Sandi akses login', path: '/demo/admin/anggota/AG-001/ubah', h1: 'Ubah Anggota', aksi: "[...document.querySelectorAll('button')].find((b) => b.textContent.trim() === 'Reset Kata Sandi').click()", tunggu: 'input[autocomplete=new-password]', penuh: true },
    { file: 'admin/states/05a-data-buku-panel-detail.png', peran: 'Admin', judul: 'Data Buku — panel Detail Buku & Daftar Eksemplar', path: '/demo/admin/buku', h1: 'Data Buku & Eksemplar', aksi: "document.querySelector('.sim-table tbody tr:nth-child(1) button[aria-label^=\"Lihat\"]').click()", tunggu: '.sim-drawer' },
    { file: 'admin/states/10a-verifikasi-panel-detail.png', peran: 'Admin', judul: 'Verifikasi Booking — panel Detail Booking', path: '/demo/admin/booking/verifikasi', h1: 'Verifikasi Booking', aksi: "document.querySelector('.sim-table tbody tr:nth-child(1) button[aria-label^=\"Detail\"]').click()", tunggu: '.sim-drawer' },
    { file: 'admin/states/10b-verifikasi-dialog-tolak.png', peran: 'Admin', judul: 'Verifikasi Booking — dialog Tolak (Alasan Penolakan)', path: '/demo/admin/booking/verifikasi', h1: 'Verifikasi Booking', aksi: "document.querySelector('.sim-table tbody tr:nth-child(1) .btn-outline-danger').click()", tunggu: '[role=dialog]' },
    { file: 'admin/states/12a-laporan-filter-awal.png', peran: 'Admin', judul: 'Laporan — sebelum laporan ditampilkan', path: '/demo/admin/laporan', h1: 'Laporan', penuh: true },
    { file: 'anggota/states/02a-cari-buku-panel-detail.png', peran: 'Anggota', judul: 'Cari Buku — panel Detail Buku', path: '/demo/anggota/cari-buku', h1: 'Cari Buku', aksi: "document.querySelector('.sim-table tbody tr:nth-child(1) button[aria-label^=\"Detail\"]').click()", tunggu: '.sim-drawer' },
    { file: 'anggota/states/04a-status-booking-panel-detail.png', peran: 'Anggota', judul: 'Status Booking — panel Detail Booking (Direalisasikan)', path: '/demo/anggota/booking/status', h1: 'Status Booking', aksi: "[...document.querySelectorAll('.sim-table tbody tr')].find(r => r.cells[0].innerText === 'BP-004').querySelector('button[aria-label^=\"Detail\"]').click()", tunggu: '.sim-drawer' },
    { file: 'anggota/states/05a-history-panel-detail.png', peran: 'Anggota', judul: 'History Peminjaman — panel Detail Riwayat', path: '/demo/anggota/history', h1: 'History Peminjaman', aksi: "document.querySelector('.sim-table tbody tr:nth-child(1) button[aria-label^=\"Detail\"]').click()", tunggu: '.sim-drawer' },
];

const CSS_PRESENTASI = `
*, *::before, *::after { transition: none !important; animation: none !important; caret-color: transparent !important; }
`;
const CSS_LOGIN_PRESENTASI = `.sim-demo-note { display: none !important; }`;
const ISTILAH_INTERNAL = /\b(SRS|TBD|UC-\d+|FR-\d+|NFR-\d+|BR-\d+|AC-\d+|Tugas \d+|prototipe|prototype|usulan|Petugas)\b/i;

// ------------------------------------------------------------------ PNG
/** Dekode PNG 8-bit (RGB/RGBA) untuk validasi: dimensi & keragaman piksel. */
function analisisPng(buf) {
    const SIG = '89504e470d0a1a0a';
    if (buf.subarray(0, 8).toString('hex') !== SIG) throw new Error('bukan PNG');
    let pos = 8;
    let lebar = 0;
    let tinggi = 0;
    let tipe = 0;
    let bit = 0;
    const idat = [];
    while (pos < buf.length) {
        const len = buf.readUInt32BE(pos);
        const jenis = buf.toString('ascii', pos + 4, pos + 8);
        const data = buf.subarray(pos + 8, pos + 8 + len);
        if (jenis === 'IHDR') {
            lebar = data.readUInt32BE(0);
            tinggi = data.readUInt32BE(4);
            bit = data[8];
            tipe = data[9];
        } else if (jenis === 'IDAT') idat.push(data);
        else if (jenis === 'IEND') break;
        pos += 12 + len;
    }
    if (bit !== 8 || (tipe !== 2 && tipe !== 6)) return { lebar, tinggi, warna: null, isi: null };
    const bpp = tipe === 6 ? 4 : 3;
    const raw = inflateSync(Buffer.concat(idat));
    const baris = lebar * bpp;
    const px = Buffer.alloc(baris * tinggi);
    for (let y = 0; y < tinggi; y++) {
        const f = raw[y * (baris + 1)];
        const src = raw.subarray(y * (baris + 1) + 1, (y + 1) * (baris + 1));
        const o = y * baris;
        for (let x = 0; x < baris; x++) {
            const a = x >= bpp ? px[o + x - bpp] : 0;
            const b = y > 0 ? px[o - baris + x] : 0;
            const c = x >= bpp && y > 0 ? px[o - baris + x - bpp] : 0;
            let v = src[x];
            if (f === 1) v += a;
            else if (f === 2) v += b;
            else if (f === 3) v += (a + b) >> 1;
            else if (f === 4) {
                const p = a + b - c;
                const pa = Math.abs(p - a);
                const pb = Math.abs(p - b);
                const pc = Math.abs(p - c);
                v += pa <= pb && pa <= pc ? a : pb <= pc ? b : c;
            }
            px[o + x] = v & 0xff;
        }
    }
    const hitung = new Map();
    let sampel = 0;
    for (let i = 0; i < lebar * tinggi; i += 13) {
        const k = (px[i * bpp] << 16) | (px[i * bpp + 1] << 8) | px[i * bpp + 2];
        hitung.set(k, (hitung.get(k) ?? 0) + 1);
        sampel++;
    }
    const dominan = Math.max(...hitung.values());

    return { lebar, tinggi, warna: hitung.size, isi: 1 - dominan / sampel };
}

// ------------------------------------------------------------------ Chrome / CDP
const tunggu = (ms) => new Promise((r) => setTimeout(r, ms));

async function bukaChrome() {
    if (!existsSync(CHROME)) throw new Error(`Chrome tidak ditemukan di ${CHROME}. Set CHROME_PATH.`);
    const profil = mkdtempSync(join(tmpdir(), 'sim-stitch-'));
    const proses = spawn(CHROME, ['--headless=new', `--remote-debugging-port=${PORT_DEBUG}`, `--user-data-dir=${profil}`, '--no-first-run', '--no-default-browser-check', '--hide-scrollbars', '--force-device-scale-factor=1', 'about:blank'], { stdio: 'ignore' });
    let target;
    for (let i = 0; i < 60 && !target; i++) {
        try {
            target = (await (await fetch(`http://127.0.0.1:${PORT_DEBUG}/json/list`)).json()).find((t) => t.type === 'page');
        } catch {
            await tunggu(250);
        }
    }
    if (!target) throw new Error('Gagal terhubung ke Chrome headless.');
    const ws = new WebSocket(target.webSocketDebuggerUrl);
    await new Promise((r, j) => {
        ws.addEventListener('open', r);
        ws.addEventListener('error', j);
    });

    let id = 0;
    const pending = new Map();
    const log = { console: [], galat: [], gagal: [] };
    ws.addEventListener('message', (e) => {
        const m = JSON.parse(e.data);
        if (m.id && pending.has(m.id)) {
            pending.get(m.id)(m);
            pending.delete(m.id);
            return;
        }
        if (m.method === 'Runtime.consoleAPICalled' && ['error', 'warning', 'assert'].includes(m.params.type)) log.console.push(m.params.args.map((a) => a.value ?? a.description).join(' '));
        if (m.method === 'Runtime.exceptionThrown') log.galat.push(m.params.exceptionDetails.exception?.description ?? m.params.exceptionDetails.text);
        if (m.method === 'Log.entryAdded' && m.params.entry.level === 'error') log.console.push(m.params.entry.text);
        if (m.method === 'Network.loadingFailed') log.gagal.push(m.params.errorText);
        if (m.method === 'Network.responseReceived' && m.params.response.status >= 400) log.gagal.push(`${m.params.response.status} ${m.params.response.url}`);
    });
    const kirim = (method, params = {}) =>
        new Promise((r) => {
            const i = ++id;
            pending.set(i, r);
            ws.send(JSON.stringify({ id: i, method, params }));
        });
    const nilai = async (expr) => {
        const r = await kirim('Runtime.evaluate', { expression: expr, returnByValue: true, awaitPromise: true });
        if (r.result?.exceptionDetails) throw new Error(`Evaluasi gagal: ${expr.slice(0, 80)} — ${r.result.exceptionDetails.exception?.description ?? r.result.exceptionDetails.text}`);
        return r.result.result.value;
    };
    for (const d of ['Runtime.enable', 'Log.enable', 'Network.enable', 'Page.enable', 'Page.bringToFront']) await kirim(d);
    await kirim('Emulation.setFocusEmulationEnabled', { enabled: true });
    await kirim('Network.setCacheDisabled', { cacheDisabled: true });

    return {
        log,
        kirim,
        nilai,
        tutup: async () => {
            ws.close();
            // Tunggu Chrome benar-benar keluar sebelum profil sementara dihapus.
            const keluar = new Promise((r) => proses.once('exit', r));
            proses.kill();
            await Promise.race([keluar, tunggu(5000)]);
            // Proses pembantu Chrome bisa masih menulis sesaat; pembersihan profil
            // sementara (di folder temp OS) bersifat best-effort dan tidak menggagalkan ekspor.
            for (let i = 0; i < 10; i++) {
                try {
                    rmSync(profil, { recursive: true, force: true });
                    break;
                } catch {
                    await tunggu(300);
                }
            }
        },
    };
}

const viewport = (b, tinggi) => b.kirim('Emulation.setDeviceMetricsOverride', { width: LEBAR, height: tinggi, deviceScaleFactor: 1, mobile: false });

async function muat(b, h) {
    b.log.console.length = 0;
    b.log.galat.length = 0;
    b.log.gagal.length = 0;
    await viewport(b, TINGGI);
    // Muat penuh setiap halaman → store kembali ke dataset awal (deterministik).
    await b.kirim('Page.navigate', { url: BASE + h.path });
    for (let i = 0; i < 80; i++) {
        await tunggu(100);
        if (await b.nilai(`document.readyState === 'complete' && !!document.querySelector('#app h1')`)) break;
    }
    await b.nilai(`(() => { const s = document.createElement('style'); s.dataset.stitch = '1'; s.textContent = ${JSON.stringify(CSS_PRESENTASI + (h.presentasi ? CSS_LOGIN_PRESENTASI : ''))}; document.head.appendChild(s); return true; })()`);
    await b.nilai('document.fonts.ready.then(() => true)');
    if (h.aksi) {
        await b.nilai(`(() => { ${h.aksi}; return true; })()`);
        for (let i = 0; i < 30 && !(await b.nilai(`!!document.querySelector(${JSON.stringify(h.tunggu)})`)); i++) await tunggu(100);
    }
    // Hilangkan fokus/hover agar tidak ada cincin fokus atau tooltip tak sengaja.
    if (!h.aksi) await b.nilai('document.activeElement?.blur?.(); true');
    await b.kirim('Input.dispatchMouseEvent', { type: 'mouseMoved', x: 0, y: 0 });
    await tunggu(400);
}

async function periksaHalaman(b, h, { utama }) {
    const info = await b.nilai(`(() => {
        const teks = document.body.innerText;
        return {
            judulHalaman: document.title,
            h1: document.querySelector('h1')?.textContent.trim() ?? null,
            placeholder: teks.includes('Konten halaman sedang disiapkan'),
            internal: (teks.match(${ISTILAH_INTERNAL}) ?? [null])[0],
            demoNote: !!document.querySelector('.sim-demo-note') && getComputedStyle(document.querySelector('.sim-demo-note')).display !== 'none',
            spinner: [...document.querySelectorAll('.spinner-border')].some((e) => e.offsetParent),
            overflowX: document.documentElement.scrollWidth > window.innerWidth,
            dialogTerbuka: !!document.querySelector('[role=dialog]'),
            fontInter: document.fonts.check('400 14px "Inter Variable"') && document.fonts.check('700 24px "Inter Variable"'),
            fontGagal: [...document.fonts].filter((f) => f.status === 'error').map((f) => f.family),
            ikon: document.fonts.check('16px "bootstrap-icons"'),
            gambarRusak: [...document.images].filter((i) => !i.complete || i.naturalWidth === 0).map((i) => i.src),
            fontBody: getComputedStyle(document.body).fontFamily.split(',')[0].replace(/"/g, ''),
        };
    })()`);
    const masalah = [];
    if (info.h1 !== h.h1) masalah.push(`judul h1 "${info.h1}" ≠ "${h.h1}"`);
    if (info.placeholder) masalah.push('placeholder halaman');
    if (info.internal) masalah.push(`istilah internal "${info.internal}"`);
    if (h.presentasi && info.demoNote) masalah.push('kotak Mode pratinjau masih tampil');
    if (info.spinner) masalah.push('indikator memuat tampil');
    if (info.overflowX) masalah.push('overflow horizontal');
    if (utama && info.dialogTerbuka) masalah.push('dialog/panel terbuka');
    if (!info.fontInter || info.fontBody !== 'Inter Variable') masalah.push('font Inter belum termuat');
    if (info.fontGagal.length) masalah.push(`font gagal: ${info.fontGagal.join(', ')}`);
    if (!info.ikon) masalah.push('Bootstrap Icons belum termuat');
    if (info.gambarRusak.length) masalah.push(`gambar rusak: ${info.gambarRusak.join(', ')}`);
    if (b.log.console.length) masalah.push(`console: ${b.log.console.join(' | ')}`);
    if (b.log.galat.length) masalah.push(`JS error: ${b.log.galat.join(' | ')}`);
    if (b.log.gagal.length) masalah.push(`request gagal: ${b.log.gagal.join(' | ')}`);

    return { info, masalah };
}

async function ambil(b, h, { penuh }) {
    if (penuh) {
        // Viewport setinggi halaman: sidebar fixed & header sticky tampil sekali, utuh.
        let tinggi = TINGGI;
        for (let i = 0; i < 4; i++) {
            const t = await b.nilai('Math.ceil(document.documentElement.scrollHeight)');
            if (t === tinggi) break;
            tinggi = Math.max(TINGGI, t);
            await viewport(b, tinggi);
            await tunggu(300);
        }
    }
    const r = await b.kirim('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false, fromSurface: true });
    const tujuan = join(SHOTS, h.file);
    mkdirSync(dirname(tujuan), { recursive: true });
    writeFileSync(tujuan, Buffer.from(r.result.data, 'base64'));
    await viewport(b, TINGGI);

    return tujuan;
}

// ------------------------------------------------------------------ Utama
async function main() {
    try {
        const r = await fetch(`${BASE}/login`);
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
    } catch (e) {
        throw new Error(`Aplikasi tidak dapat dibuka di ${BASE} (${e.message}). Jalankan "php artisan serve" dan "npm run build" terlebih dahulu.`);
    }

    rmSync(SHOTS, { recursive: true, force: true });
    mkdirSync(SHOTS, { recursive: true });

    const b = await bukaChrome();
    const hasil = [];
    try {
        for (const [daftar, utama] of [[HALAMAN, true], [STATE, false]]) {
            for (const h of daftar) {
                await muat(b, h);
                const { info, masalah } = await periksaHalaman(b, h, { utama });
                const file = await ambil(b, h, { penuh: utama || h.penuh });
                hasil.push({ ...h, utama, info, masalah, file });
                process.stdout.write(`${masalah.length ? '✗' : '✓'} ${h.file}${masalah.length ? ` — ${masalah.join('; ')}` : ''}\n`);
            }
        }
    } finally {
        await b.tutup();
    }

    // Validasi file PNG
    const hash = new Map();
    for (const h of hasil) {
        const buf = readFileSync(h.file);
        const png = analisisPng(buf);
        h.png = { ...png, ukuranKb: Math.round(buf.length / 1024) };
        if (png.lebar !== LEBAR) h.masalah.push(`lebar ${png.lebar}px`);
        if (png.tinggi < TINGGI) h.masalah.push(`tinggi ${png.tinggi}px < ${TINGGI}`);
        if (png.warna !== null && (png.warna < 20 || png.isi < 0.05)) h.masalah.push('gambar tampak kosong');
        const sidik = createHash('sha256').update(buf).digest('hex');
        if (hash.has(sidik)) h.masalah.push(`duplikat dari ${hash.get(sidik)}`);
        hash.set(sidik, h.file);
        h.sha256 = sidik.slice(0, 12);
    }

    tulisManifest(hasil);
    const zip = buatZip();
    const gagal = hasil.filter((h) => h.masalah.length);
    console.log(`\n${hasil.length - gagal.length}/${hasil.length} screenshot lolos validasi.`);
    console.log(`Manifest: ${relative(ROOT, join(OUT, 'SCREENSHOT_MANIFEST.md'))}`);
    console.log(`ZIP: ${relative(ROOT, zip.file)} (${zip.entri} entri, uji integritas: ${zip.ok ? 'OK' : 'GAGAL'})`);
    if (gagal.length || !zip.ok) process.exitCode = 1;
}

function tulisManifest(hasil) {
    const baris = (h, i) => {
        const rel = relative(SHOTS, h.file);
        const status = h.masalah.length ? `✗ ${h.masalah.join('; ')}` : '✓ Lolos';

        return `| ${i + 1} | ${h.peran} | ${h.judul} | \`${h.path}\` | \`${rel}\` | ${h.png.lebar} × ${h.png.tinggi} | ${h.png.ukuranKb} KB | ${h.catatan ?? (h.utama ? '—' : 'Panel/dialog terbuka; viewport 1440 × 900')} | ${status} |`;
    };
    const kepala = '| No | Peran | Halaman | URL lokal | File PNG | Dimensi (px) | Ukuran | State / catatan | Validasi |\n| --- | --- | --- | --- | --- | --- | --- | --- | --- |';
    const utama = hasil.filter((h) => h.utama);
    const state = hasil.filter((h) => !h.utama);
    const lolos = hasil.filter((h) => !h.masalah.length).length;

    const md = `# Manifest Screenshot — SIM Perpustakaan

Paket screenshot PNG halaman final untuk direplikasi di Google Stitch. Website Laravel + Vue adalah acuan final; mockup Stitch harus menyamai screenshot ini tanpa mengubah tata letak, teks, warna, atau data.

## Ringkasan

- Screenshot utama: **${utama.length}** (1 Masuk, ${utama.filter((h) => h.peran === 'Admin').length} Admin, ${utama.filter((h) => h.peran === 'Anggota').length} Anggota)
- Screenshot state tambahan (panel/dialog): **${state.length}**
- Lolos validasi: **${lolos}/${hasil.length}**
- Basis URL saat capture: \`${BASE}\` (lokal; tidak ada URL publik)
- Tanggal data demo: 7 November 2026 (tetap). Setiap halaman dimuat ulang penuh sehingga memakai dataset awal.

## Standar capture

| Parameter | Nilai |
| --- | --- |
| Browser | Google Chrome headless (DevTools Protocol) |
| Viewport | ${LEBAR} × ${TINGGI}, device scale factor 1, zoom 100% |
| Format | PNG 8-bit |
| Halaman utama | Full-page: viewport diperbesar setinggi halaman agar sidebar (fixed) dan header (sticky) tampil utuh sekali |
| State tambahan | Viewport ${LEBAR} × ${TINGGI} dengan panel/dialog terbuka |
| Font | Inter Variable & Bootstrap Icons (dibundel lokal oleh Vite) — diverifikasi termuat sebelum capture |
| Animasi | Transisi, animasi, dan kursor teks dinonaktifkan selama capture |
| Fokus/hover | Fokus dilepas dan kursor dipindah ke pojok sebelum capture |
| Login | Mode presentasi: kotak "Mode pratinjau" disembunyikan hanya saat capture (kode demo tetap ada) |

## Halaman utama

${kepala}
${utama.map(baris).join('\n')}

## State tambahan

${kepala}
${state.map((h, i) => baris(h, i)).join('\n')}

## Validasi yang dijalankan

Per halaman, sebelum capture: judul (h1) sesuai, tidak ada placeholder atau istilah internal (SRS/TBD/UC/FR/BR/prototipe), tidak ada indikator memuat, tidak ada overflow horizontal, tidak ada dialog/panel terbuka pada screenshot utama, font Inter & Bootstrap Icons termuat, tidak ada gambar rusak, tidak ada error/peringatan console, JavaScript error, atau request gagal.

Per file: signature PNG valid, lebar ${LEBAR}px, tinggi ≥ ${TINGGI}px, piksel didekode untuk memastikan gambar tidak kosong, dan sidik SHA-256 unik (tidak ada screenshot duplikat).

Dibuat ulang dengan: \`npm run screenshots:stitch\` (lihat \`scripts/stitch-screenshots.mjs\`).
`;
    writeFileSync(join(OUT, 'SCREENSHOT_MANIFEST.md'), md);
}

function buatZip() {
    const file = join(OUT, 'SIM_PERPUSTAKAAN_SCREENSHOTS.zip');
    rmSync(file, { force: true });
    // Hanya screenshot + manifest; tanpa atribut tambahan macOS.
    execFileSync('zip', ['-X', '-q', '-r', file, 'screenshots', 'SCREENSHOT_MANIFEST.md', '-x', '*.DS_Store'], { cwd: OUT });
    let ok = true;
    try {
        execFileSync('unzip', ['-tq', file]);
    } catch {
        ok = false;
    }
    const daftar = execFileSync('unzip', ['-Z1', file]).toString().trim().split('\n');
    const terlarang = daftar.filter((n) => !(n === 'SCREENSHOT_MANIFEST.md' || n.startsWith('screenshots/')));
    if (terlarang.length) ok = false;

    return { file, entri: daftar.length, ok };
}

main().catch((e) => {
    console.error(`Gagal: ${e.message}`);
    process.exitCode = 1;
});
