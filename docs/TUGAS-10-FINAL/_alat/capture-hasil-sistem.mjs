#!/usr/bin/env node
/**
 * Capture screenshot "Hasil Sistem" Tugas 10 dari aplikasi Laravel yang BERJALAN
 * dengan MySQL — khusus database uji `sim_perpustakaan_test`.
 *
 * - Kredensial MySQL uji WAJIB dari environment (DB_TEST_USERNAME, DB_TEST_PASSWORD); tanpa nilai bawaan,
 * tidak pernah dicetak / ditulis ke berkas. Admin uji di-seed dengan sandi acak sekali pakai.
 * - Kata sandi anggota demo dibuat acak saat runtime dan tidak disimpan.
 * - Setiap langkah mutasi diverifikasi dengan kueri ke DB uji.
 * - Di akhir: DB uji dikosongkan lagi, DB development dibandingkan (harus identik).
 *
 * Pemakaian: node --experimental-websocket capture-hasil-sistem.mjs <repo> <outDir> <laporanJson>
 */
import { spawn, execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { randomBytes } from 'node:crypto';

for (const k of ['http_proxy', 'https_proxy', 'ALL_PROXY', 'HTTP_PROXY', 'HTTPS_PROXY']) delete process.env[k];
process.env.no_proxy = '127.0.0.1,localhost';

const [REPO, OUT, LAPORAN] = process.argv.slice(2);
if (!REPO || !OUT || !LAPORAN) throw new Error('argumen: <repo> <outDir> <laporanJson>');
process.chdir(REPO);

const CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const PHP = '/opt/homebrew/opt/php@8.4/bin/php';
const PORT = 8041;
const BASE = `http://127.0.0.1:${PORT}`;
const PORT_DEBUG = 9371;
const tunggu = (ms) => new Promise((r) => setTimeout(r, ms));

// ------------------------------------------------------------------ Kredensial runtime (tanpa nilai bawaan, tanpa mencetak)
// Akun MySQL khusus DB uji wajib diberikan lewat environment: DB_TEST_USERNAME, DB_TEST_PASSWORD.
const kurang = ['DB_TEST_USERNAME', 'DB_TEST_PASSWORD'].filter((n) => !(process.env[n] ?? '').trim());
if (kurang.length) {
    console.error(`FATAL: variabel environment wajib belum diisi: ${kurang.join(', ')}. Tidak ada kata sandi bawaan.`);
    process.exit(2);
}
const DB_UJI = {
    DB_DATABASE: (process.env.DB_TEST_DATABASE ?? '').trim() || 'sim_perpustakaan_test',
    DB_USERNAME: process.env.DB_TEST_USERNAME,
    DB_PASSWORD: process.env.DB_TEST_PASSWORD,
};
if (DB_UJI.DB_DATABASE !== 'sim_perpustakaan_test') throw new Error('Menolak: DB uji bukan sim_perpustakaan_test.');
const ENV_UJI = {
    ...process.env,
    DB_TEST_DATABASE: DB_UJI.DB_DATABASE,
    DB_TEST_USERNAME: DB_UJI.DB_USERNAME,
    DB_TEST_PASSWORD: DB_UJI.DB_PASSWORD,
};

// Sandi Admin uji dan anggota demo: acak sekali pakai, hanya di memori proses ini.
// Admin uji di-seed dengan sandi ini, sehingga sandi Admin development tidak pernah dipakai.
const SANDI_ADMIN = 'Uji-' + randomBytes(15).toString('base64url');
const SANDI_AWAL = 'Demo-' + randomBytes(9).toString('base64url');
const SANDI_BARU = 'Demo-' + randomBytes(9).toString('base64url');

// ------------------------------------------------------------------ artisan helper (DB uji / dev, baca-saja kecuali reset)
const TABEL = ['audit_logs', 'sessions', 'returns', 'loans', 'bookings', 'book_copies', 'books', 'members', 'users'];
function tinker(php) {
    return execFileSync(PHP, ['artisan', 'tinker', `--execute=${php}`], { encoding: 'utf8', env: ENV_UJI }).trim().split('\n').pop();
}
function hitung(koneksi) {
    return tinker(`$c=DB::connection('${koneksi}'); echo json_encode(collect(['users','members','books','book_copies','bookings','loans','returns','audit_logs'])->mapWithKeys(fn($t)=>[$t=>$c->table($t)->count()]));`);
}
function kosongkanUji() {
    tinker(`$db=DB::connection('mysql_testing'); foreach (${JSON.stringify(TABEL).replace(/"/g, "'")} as $t) { $db->table($t)->delete(); } echo 'ok';`);
}
function kueriUji(php) {
    return JSON.parse(tinker(`$db=DB::connection('mysql_testing'); echo json_encode(${php});`));
}

// ------------------------------------------------------------------ CDP
async function bukaChrome() {
    const profil = mkdtempSync(join(tmpdir(), 'sim-t10-'));
    const proses = spawn(CHROME, ['--headless=new', `--remote-debugging-port=${PORT_DEBUG}`, `--user-data-dir=${profil}`,
        '--no-first-run', '--no-default-browser-check', '--hide-scrollbars', '--force-device-scale-factor=1', 'about:blank'], { stdio: 'ignore' });
    let target;
    for (let i = 0; i < 80 && !target; i++) {
        try { target = (await (await fetch(`http://127.0.0.1:${PORT_DEBUG}/json/list`)).json()).find((t) => t.type === 'page'); }
        catch { await tunggu(200); }
    }
    if (!target) { proses.kill(); throw new Error('CDP gagal'); }
    const ws = new WebSocket(target.webSocketDebuggerUrl);
    await new Promise((res, rej) => { ws.addEventListener('open', res); ws.addEventListener('error', rej); });
    let id = 0;
    const pending = new Map();
    const log = { galat: [], gagal: [] };
    ws.addEventListener('message', (e) => {
        const m = JSON.parse(e.data);
        if (m.id && pending.has(m.id)) { const p = pending.get(m.id); pending.delete(m.id); m.error ? p.rej(new Error(m.error.message)) : p.res(m); }
        else if (m.method === 'Runtime.exceptionThrown') log.galat.push(m.params.exceptionDetails.exception?.description ?? m.params.exceptionDetails.text);
        else if (m.method === 'Network.responseReceived' && m.params.response.status >= 500) log.gagal.push(`${m.params.response.status} ${m.params.response.url}`);
    });
    const kirim = (method, params = {}) => new Promise((res, rej) => { const i = ++id; pending.set(i, { res, rej }); ws.send(JSON.stringify({ id: i, method, params })); });
    const js = async (expr) => {
        const r = await kirim('Runtime.evaluate', { expression: expr, returnByValue: true, awaitPromise: true });
        if (r.result.exceptionDetails) throw new Error('JS: ' + (r.result.exceptionDetails.exception?.description ?? r.result.exceptionDetails.text));
        return r.result.result.value;
    };
    for (const d of ['Runtime.enable', 'Network.enable', 'Page.enable']) await kirim(d);
    await kirim('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false });
    return { kirim, js, log, tutup: async () => { ws.close(); proses.kill(); await tunggu(500); rmSync(profil, { recursive: true, force: true }); } };
}

// ------------------------------------------------------------------ Helper halaman (dipasang di window)
const HELPER = `
window.__t10 = {
  visible: (el) => !!el && el.offsetParent !== null && getComputedStyle(el).visibility !== 'hidden',
  scope: (modal) => modal ? [...document.querySelectorAll('.modal.show, [role=dialog]')].filter(m => window.__t10.visible(m)).pop() : document,
  klik: (teks, modal = false) => {
    const s = window.__t10.scope(modal); if (!s) return false;
    const norm = (x) => x.textContent.replace(/\\s+/g,' ').trim();
    const tombol = [...s.querySelectorAll('button')].filter(window.__t10.visible);
    const tautan = [...s.querySelectorAll('a')].filter(window.__t10.visible);
    const b = tombol.find(x => norm(x) === teks) || tombol.find(x => norm(x).includes(teks))
           || tautan.find(x => norm(x) === teks) || tautan.find(x => norm(x).includes(teks));
    if (!b) return false; b.click(); return true;
  },
  klikLabel: (aria) => { const b = [...document.querySelectorAll('button[aria-label]')].find(x => x.getAttribute('aria-label') === aria); if (!b) return false; b.click(); return true; },
  field: (label, idx = 0) => {
    const ls = [...document.querySelectorAll('label')].filter(l => l.textContent.replace(/\\*/g,'').replace(/\\s+/g,' ').trim() === label);
    const l = ls[idx]; return l ? document.getElementById(l.htmlFor) : null;
  },
  isi: (label, nilai, idx = 0) => {
    const el = window.__t10.field(label, idx); if (!el) return 'tidak ada field ' + label;
    const proto = el.tagName === 'SELECT' ? HTMLSelectElement.prototype : (el.tagName === 'TEXTAREA' ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype);
    Object.getOwnPropertyDescriptor(proto, 'value').set.call(el, nilai);
    el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true }));
    return el.value === nilai ? true : 'nilai tidak terpasang ' + label;
  },
  opsi: (label) => { const el = window.__t10.field(label); return el ? [...el.options].map(o => ({ v: o.value, t: o.textContent.trim(), d: o.disabled })) : null; },
  teks: () => document.body.innerText,
};`;

async function buka(c, path, tungguSelector = 'h1') {
    await c.kirim('Page.navigate', { url: BASE + path });
    for (let i = 0; i < 150; i++) {
        await tunggu(100);
        try { if (await c.js(`document.readyState==='complete' && !!document.querySelector(${JSON.stringify(tungguSelector)})`)) break; } catch {}
    }
    await c.js(HELPER);
    await stabil(c);
}
async function stabil(c) {
    await c.js(`document.fonts ? document.fonts.ready.then(()=>true) : true`);
    await tunggu(500);
    await c.js(HELPER);
}
async function tungguKondisi(c, expr, ms = 10000) {
    for (let i = 0; i < ms / 100; i++) { await tunggu(100); try { if (await c.js(expr)) { await stabil(c); return true; } } catch {} }
    return false;
}

const HASIL = { mulai: new Date().toISOString(), langkah: [], screenshot: [] };
function catat(nama, lolos, detail = '') {
    HASIL.langkah.push({ nama, status: lolos ? 'PASS' : 'FAIL', detail });
    console.log(`[${lolos ? 'PASS' : 'FAIL'}] ${nama}${detail ? ' — ' + detail : ''}`);
    if (!lolos) throw new Error('Langkah gagal: ' + nama + ' ' + detail);
}

async function foto(c, nama, judul, { penuh = true } = {}) {
    await c.js(`document.activeElement && document.activeElement.blur(); true`);
    await c.kirim('Input.dispatchMouseEvent', { type: 'mouseMoved', x: 1439, y: 899 });
    await c.js(`(()=>{const s=document.createElement('style');s.id='__t10css';s.textContent='*{transition:none!important;animation:none!important;caret-color:transparent!important}';document.head.appendChild(s);return true})()`);
    let tinggi = 900;
    if (penuh) {
        const m = await c.kirim('Page.getLayoutMetrics');
        tinggi = Math.max(900, Math.ceil(m.result.cssContentSize.height));
        tinggi = Math.min(tinggi, 4000);
        await c.kirim('Emulation.setDeviceMetricsOverride', { width: 1440, height: tinggi, deviceScaleFactor: 1, mobile: false });
        await tunggu(400);
    }
    const r = await c.kirim('Page.captureScreenshot', { format: 'png' });
    const berkas = join(OUT, nama);
    mkdirSync(join(berkas, '..'), { recursive: true });
    writeFileSync(berkas, Buffer.from(r.result.data, 'base64'));
    if (penuh) await c.kirim('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false });
    await c.js(`document.getElementById('__t10css')?.remove(); true`);
    const url = await c.js('location.pathname + location.search');
    HASIL.screenshot.push({ berkas: nama, judul, url, tinggi });
    // Pastikan tidak ada kata sandi terlihat sebagai teks pada halaman.
    const bocor = await c.js(`document.body.innerText.includes(${JSON.stringify(SANDI_AWAL)}) || document.body.innerText.includes(${JSON.stringify(SANDI_BARU)})`);
    if (bocor) throw new Error('Kata sandi terlihat di halaman ' + nama);
    console.log(`  📷 ${nama}  (${url}, h=${tinggi})`);
}

async function masuk(c, username, sandi, awalan) {
    await buka(c, '/login', 'input[autocomplete=username]');
    await c.js(`(()=>{const set=(el,v)=>{Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value').set.call(el,v);el.dispatchEvent(new Event('input',{bubbles:true}));};
      set(document.querySelector('input[autocomplete=username]'), ${JSON.stringify(username)});
      set(document.querySelector('input[autocomplete=current-password]'), ${JSON.stringify(sandi)});
      document.querySelector('form button[type=submit]').click(); return true;})()`);
    const ok = await tungguKondisi(c, `location.pathname.startsWith(${JSON.stringify(awalan)})`);
    return ok ? c.js('location.pathname') : null;
}
async function keluar(c) {
    await c.js(HELPER);
    await c.js(`window.__t10.klik('Keluar')`);
    await tunggu(400);
    await c.js(`window.__t10.klik('Keluar', true)`);
    return tungguKondisi(c, `location.pathname === '/login'`);
}

// ------------------------------------------------------------------ Skenario
async function main() {
    if (existsSync('public/hot')) throw new Error('public/hot ada (Vite dev server pengguna aktif) — dihentikan agar tidak mengganggu.');
    mkdirSync(OUT, { recursive: true });

    const devAwal = hitung('mysql');
    const ujiAwal = JSON.parse(hitung('mysql_testing'));
    if (Object.values(ujiAwal).some((n) => n > 0)) throw new Error('DB uji tidak kosong — dihentikan: ' + JSON.stringify(ujiAwal));
    HASIL.devAwal = JSON.parse(devAwal);

    execFileSync(PHP, ['artisan', 'db:seed', '--class=DemoPerpustakaanSeeder', '--database=mysql_testing', '--force'], {
        stdio: 'ignore',
        env: { ...ENV_UJI, SEED_ADMIN_PASSWORD: SANDI_ADMIN },
    });
    HASIL.ujiSetelahSeed = JSON.parse(hitung('mysql_testing'));
    catat('Seed data demo ke sim_perpustakaan_test', HASIL.ujiSetelahSeed.books > 0, JSON.stringify(HASIL.ujiSetelahSeed));

    const server = spawn(PHP, ['artisan', 'serve', `--port=${PORT}`], { env: { ...ENV_UJI, ...DB_UJI, APP_URL: BASE }, stdio: 'ignore' });
    let siap = false;
    for (let i = 0; i < 80 && !siap; i++) { try { siap = (await fetch(BASE + '/login')).ok; } catch { await tunggu(150); } }
    if (!siap) { server.kill(); throw new Error('server gagal'); }

    const c = await bukaChrome();
    try {
        // 1. Halaman masuk
        await buka(c, '/login', 'input[autocomplete=username]');
        await foto(c, '00-login.png', 'Halaman Masuk', { penuh: false });

        // 2. Admin masuk → dashboard
        const p1 = await masuk(c, 'admin', SANDI_ADMIN, '/admin/');
        catat('Admin masuk', p1 === '/admin/dashboard', p1);
        await stabil(c);
        const kartu = await c.js(`document.querySelectorAll('.sim-stat-grid > *').length`);
        catat('Dashboard Admin menampilkan kartu ringkasan', kartu > 0, `${kartu} kartu`);
        await foto(c, 'admin/01-dashboard.png', 'Dashboard Admin (agregasi SQL)');

        // 3. Data Anggota
        await buka(c, '/admin/anggota');
        await foto(c, 'admin/02-data-anggota.png', 'Data Anggota');

        // 4. Tambah Anggota + akses login
        await buka(c, '/admin/anggota/tambah', 'form');
        for (const [l, v] of [['Kode Anggota', 'AG-901'], ['Nama', 'Dimas Saputra'], ['Kontak', '0812-0000-0901']]) {
            const r = await c.js(`window.__t10.isi(${JSON.stringify(l)}, ${JSON.stringify(v)})`); if (r !== true) throw new Error(r);
        }
        await c.js(`(()=>{const s=document.getElementById('buat-akses-login'); if(s && !s.checked){ s.click(); } return true;})()`);
        await tungguKondisi(c, `!!document.getElementById('isian-akses-login')`);
        let r = await c.js(`window.__t10.isi('Username', 'dimas.demo')`); if (r !== true) throw new Error(r);
        await foto(c, 'admin/03-tambah-anggota.png', 'Tambah Anggota (dengan Akses Login; kata sandi belum diisi)');
        r = await c.js(`window.__t10.isi('Kata Sandi', ${JSON.stringify(SANDI_AWAL)})`); if (r !== true) throw new Error(r);
        r = await c.js(`window.__t10.isi('Konfirmasi Kata Sandi', ${JSON.stringify(SANDI_AWAL)})`); if (r !== true) throw new Error(r);
        await c.js(`window.__t10.klik('Simpan Anggota')`);
        await tungguKondisi(c, `location.pathname === '/admin/anggota'`);
        const ag = kueriUji(`$db->table('members')->where('member_code','AG-901')->select('member_code','status','user_id')->first()`);
        const akun = kueriUji(`$db->table('users')->where('username','dimas.demo')->select('role','must_change_password')->first()`);
        catat('Anggota AG-901 tersimpan di MySQL dengan akun login', ag && ag.user_id && akun && akun.role === 'anggota' && Number(akun.must_change_password) === 1,
            JSON.stringify({ status: ag?.status, role: akun?.role, wajib_ganti: akun?.must_change_password }));
        await foto(c, 'admin/04-anggota-tersimpan.png', 'Data Anggota setelah penyimpanan (pesan sukses)');

        // 5. Ubah Anggota
        await buka(c, '/admin/anggota/AG-001/ubah', 'form');
        await foto(c, 'admin/05-ubah-anggota.png', 'Ubah Anggota (AG-001)');

        // 6. Data Buku
        await buka(c, '/admin/buku');
        await foto(c, 'admin/06-data-buku.png', 'Data Buku & Eksemplar');

        // 7. Tambah Buku + 2 eksemplar
        await buka(c, '/admin/buku/tambah', 'form');
        for (const [l, v] of [['Kode Buku', 'BK-901'], ['Kategori', 'Informatika'], ['Judul', 'Pengantar Basis Data Relasional'],
            ['Penulis', 'Tim Penulis Demo'], ['Penerbit', 'Penerbit Demo'], ['Tahun Terbit', '2025']]) {
            r = await c.js(`window.__t10.isi(${JSON.stringify(l)}, ${JSON.stringify(v)})`); if (r !== true) throw new Error(r);
        }
        for (const kode of ['EK-901', 'EK-902']) {
            r = await c.js(`window.__t10.isi('Kode Eksemplar', ${JSON.stringify(kode)})`); if (r !== true) throw new Error(r);
            await c.js(`window.__t10.klik('Tambah Eksemplar')`);
            await stabil(c);
        }
        const barisEk = await c.js(`window.__t10.teks().includes('EK-901') && window.__t10.teks().includes('EK-902')`);
        catat('Formulir buku memuat 2 eksemplar', barisEk);
        await foto(c, 'admin/07-tambah-buku.png', 'Tambah Buku dengan 2 eksemplar');
        await c.js(`window.__t10.klik('Simpan Buku')`);
        await tungguKondisi(c, `location.pathname === '/admin/buku'`);
        const bk = kueriUji(`['buku'=>$db->table('books')->where('book_code','BK-901')->count(), 'eksemplar'=>$db->table('book_copies')->join('books','books.id','=','book_copies.book_id')->where('books.book_code','BK-901')->pluck('book_copies.status','book_copies.copy_code')]`);
        catat('Buku BK-901 + 2 eksemplar Tersedia tersimpan', bk.buku === 1 && bk.eksemplar['EK-901'] === 'Tersedia' && bk.eksemplar['EK-902'] === 'Tersedia', JSON.stringify(bk));
        await foto(c, 'admin/08-buku-tersimpan.png', 'Data Buku setelah penyimpanan (pesan sukses)');

        await buka(c, '/admin/buku/BK-901/ubah', 'form');
        await foto(c, 'admin/09-ubah-buku.png', 'Ubah Buku (BK-901)');

        // Halaman peminjaman langsung (formulir) — ditampilkan, tidak disimpan
        await buka(c, '/admin/peminjaman', 'form');
        await foto(c, 'admin/10-peminjaman.png', 'Peminjaman Buku (formulir peminjaman langsung)');

        catat('Admin keluar', await keluar(c));

        // 8. Anggota masuk pertama kali → wajib ganti kata sandi
        const p2 = await masuk(c, 'dimas.demo', SANDI_AWAL, '/anggota/');
        catat('Anggota baru diarahkan ke Ubah Kata Sandi', p2 === '/anggota/kata-sandi', p2);
        await stabil(c);
        await foto(c, 'anggota/01-wajib-ubah-kata-sandi.png', 'Ubah Kata Sandi (wajib saat login pertama)');
        const labelSandi = await c.js(`[...document.querySelectorAll('form label')].map(l=>l.textContent.replace(/\\*/g,'').trim())`);
        HASIL.labelKataSandi = labelSandi;
        r = await c.js(`window.__t10.isi(${JSON.stringify(labelSandi[0])}, ${JSON.stringify(SANDI_AWAL)})`); if (r !== true) throw new Error(r);
        r = await c.js(`window.__t10.isi(${JSON.stringify(labelSandi[1])}, ${JSON.stringify(SANDI_BARU)})`); if (r !== true) throw new Error(r);
        r = await c.js(`window.__t10.isi(${JSON.stringify(labelSandi[2])}, ${JSON.stringify(SANDI_BARU)})`); if (r !== true) throw new Error(r);
        await c.js(`window.__t10.klik('Simpan Kata Sandi')`);
        catat('Kata sandi diganti → Dashboard Anggota', await tungguKondisi(c, `location.pathname === '/anggota/dashboard'`));
        await foto(c, 'anggota/02-dashboard.png', 'Dashboard Anggota');

        // 9. Cari buku
        await buka(c, '/anggota/cari-buku');
        await c.js(`(()=>{const el=document.querySelector('input[type=search], input[placeholder*="Cari judul"]'); Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value').set.call(el,'Basis Data'); el.dispatchEvent(new Event('input',{bubbles:true})); return true;})()`);
        catat('Pencarian katalog menemukan BK-901', await tungguKondisi(c, `window.__t10.teks().includes('BK-901')`));
        await foto(c, 'anggota/03-cari-buku.png', 'Cari Buku (hasil pencarian "Basis Data")');
        await c.js(`window.__t10.klikLabel('Detail BK-901')`);
        catat('Panel Detail Buku terbuka', await tungguKondisi(c, `window.__t10.teks().includes('Daftar Eksemplar')`));
        await foto(c, 'anggota/04-detail-buku.png', 'Detail Buku & daftar eksemplar', { penuh: false });

        // 10. Ajukan booking EK-901
        await buka(c, '/anggota/booking/ajukan?buku=BK-901', 'form');
        const opsiEk = await c.js(`window.__t10.opsi('Kode Eksemplar')`);
        HASIL.opsiEksemplar = opsiEk;
        const ek901 = (opsiEk || []).find((o) => o.t.includes('EK-901') || o.v === 'EK-901');
        if (!ek901) throw new Error('Opsi EK-901 tidak ada: ' + JSON.stringify(opsiEk));
        r = await c.js(`window.__t10.isi('Kode Eksemplar', ${JSON.stringify(ek901.v)})`); if (r !== true) throw new Error(r);
        await stabil(c);
        await foto(c, 'anggota/05-ajukan-booking.png', 'Ajukan Booking (EK-901 dipilih)');
        await c.js(`window.__t10.klik('Ajukan Booking')`);
        await tunggu(500);
        await c.js(`window.__t10.klik('Ajukan Booking', true)`);
        await tungguKondisi(c, `window.__t10.teks().includes('berhasil diajukan')`);
        const bp = kueriUji(`$db->table('bookings')->join('book_copies','book_copies.id','=','bookings.copy_id')->where('book_copies.copy_code','EK-901')->select('bookings.booking_code','bookings.status','book_copies.status as status_eksemplar')->first()`);
        catat('Booking tersimpan berstatus Diajukan (eksemplar tetap Tersedia)', bp && bp.status === 'Diajukan' && bp.status_eksemplar === 'Tersedia', JSON.stringify(bp));
        HASIL.kodeBooking = bp.booking_code;
        await foto(c, 'anggota/06-booking-diajukan.png', `Konfirmasi booking ${bp.booking_code} diajukan`);
        await buka(c, '/anggota/booking/status');
        await foto(c, 'anggota/07-status-booking.png', 'Status Booking (Diajukan)');
        catat('Anggota keluar', await keluar(c));

        // 11. Admin verifikasi (setujui) booking
        await masuk(c, 'admin', SANDI_ADMIN, '/admin/');
        await buka(c, '/admin/booking/verifikasi');
        catat('Booking baru tampil di Verifikasi Booking', await c.js(`window.__t10.teks().includes(${JSON.stringify(bp.booking_code)})`));
        await foto(c, 'admin/11-verifikasi-booking.png', 'Verifikasi Booking (tab Diajukan)');
        await c.js(`window.__t10.klikLabel(${JSON.stringify('Detail ' + bp.booking_code)})`);
        await tungguKondisi(c, `!!window.__t10.scope(true)`);
        await foto(c, 'admin/12-detail-booking.png', 'Panel Detail Booking', { penuh: false });
        await c.js(`window.__t10.klik('Setujui', true)`);
        await tunggu(500);
        await c.js(`window.__t10.klik('Setujui Booking', true)`);
        await tunggu(1500);
        const bp2 = kueriUji(`$db->table('bookings')->where('booking_code','${bp.booking_code}')->selectRaw('status, TIMESTAMPDIFF(HOUR, decided_at, expires_at) as jam_batas')->first()`);
        const ekSt = kueriUji(`$db->table('book_copies')->where('copy_code','EK-901')->value('status')`);
        catat('Booking Disetujui, batas pengambilan 48 jam, eksemplar Dipesan', bp2.status === 'Disetujui' && Number(bp2.jam_batas) === 48 && ekSt === 'Dipesan', JSON.stringify({ ...bp2, eksemplar: ekSt }));
        await stabil(c);
        await foto(c, 'admin/13-booking-disetujui.png', 'Verifikasi Booking setelah persetujuan');

        // 12. Realisasi booking
        await buka(c, `/admin/booking/realisasi?booking=${bp.booking_code}`);
        r = await c.js(`window.__t10.isi('Tanggal Jatuh Tempo', '2026-11-28')`);
        if (r !== true) throw new Error(r);
        await stabil(c);
        await foto(c, 'admin/14-realisasi-booking.png', 'Realisasi Booking (jatuh tempo 28 Nov 2026)');
        await c.js(`window.__t10.klik('Realisasikan Menjadi Peminjaman')`);
        await tunggu(500);
        await c.js(`window.__t10.klik('Realisasikan', true)`);
        await tungguKondisi(c, `window.__t10.teks().includes('direalisasikan menjadi peminjaman')`);
        const pj = kueriUji(`$db->table('loans')->join('bookings','bookings.id','=','loans.booking_id')->where('bookings.booking_code','${bp.booking_code}')->select('loans.loan_code','loans.status','loans.due_date','bookings.status as status_booking')->first()`);
        const ekSt2 = kueriUji(`$db->table('book_copies')->where('copy_code','EK-901')->value('status')`);
        catat('Realisasi: peminjaman Aktif, booking Direalisasikan, eksemplar Dipinjam', pj && pj.status === 'Aktif' && pj.status_booking === 'Direalisasikan' && ekSt2 === 'Dipinjam', JSON.stringify({ ...pj, eksemplar: ekSt2 }));
        HASIL.kodePeminjaman = pj.loan_code;
        await foto(c, 'admin/15-bukti-realisasi.png', `Bukti realisasi ${bp.booking_code} → ${pj.loan_code}`);

        // 13. Pengembalian
        await buka(c, '/admin/pengembalian');
        await c.js(`(()=>{const el=document.querySelector('input[placeholder*="Cari ID"]'); Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value').set.call(el,${JSON.stringify(pj.loan_code)}); el.dispatchEvent(new Event('input',{bubbles:true})); return true;})()`);
        await tungguKondisi(c, `window.__t10.teks().includes(${JSON.stringify(pj.loan_code)})`);
        await foto(c, 'admin/16-pengembalian.png', `Pengembalian Buku (pencarian ${pj.loan_code})`);
        await c.js(`window.__t10.klik('Proses Pengembalian')`);
        await tunggu(500);
        await c.js(`window.__t10.klik('Proses Pengembalian', true)`);
        await tunggu(1500);
        const kb = kueriUji(`['pinjam'=>$db->table('loans')->where('loan_code','${pj.loan_code}')->value('status'), 'kembali'=>$db->table('returns')->join('loans','loans.id','=','returns.loan_id')->where('loans.loan_code','${pj.loan_code}')->value('returns.return_code'), 'eksemplar'=>$db->table('book_copies')->where('copy_code','EK-901')->value('status')]`);
        catat('Pengembalian: peminjaman Selesai, eksemplar Tersedia', kb.pinjam === 'Selesai' && kb.kembali && kb.eksemplar === 'Tersedia', JSON.stringify(kb));
        HASIL.kodePengembalian = kb.kembali;
        await stabil(c);
        await foto(c, 'admin/17-pengembalian-berhasil.png', `Pengembalian ${kb.kembali} berhasil diproses`);

        // 14. Laporan
        await buka(c, '/admin/laporan?jenis=peminjaman&awal=2026-11-01&akhir=2026-11-30', 'h1');
        catat('Laporan Peminjaman memuat transaksi demo', await tungguKondisi(c, `window.__t10.teks().includes(${JSON.stringify(pj.loan_code)})`));
        await foto(c, 'admin/18-laporan-peminjaman.png', 'Laporan Peminjaman (1–30 Nov 2026)');
        await buka(c, '/admin/laporan?jenis=pengembalian&awal=2026-11-01&akhir=2026-11-30', 'h1');
        await tungguKondisi(c, `window.__t10.teks().includes(${JSON.stringify(kb.kembali)})`);
        await foto(c, 'admin/19-laporan-pengembalian.png', 'Laporan Pengembalian (1–30 Nov 2026)');
        await buka(c, '/admin/dashboard');
        await foto(c, 'admin/20-dashboard-setelah-transaksi.png', 'Dashboard Admin setelah skenario demo');
        catat('Admin keluar (2)', await keluar(c));

        // 15. Anggota melihat history
        await masuk(c, 'dimas.demo', SANDI_BARU, '/anggota/');
        await buka(c, '/anggota/history');
        catat('History Anggota memuat peminjaman Selesai', await c.js(`window.__t10.teks().includes(${JSON.stringify(pj.loan_code)}) && window.__t10.teks().includes('Selesai')`));
        await foto(c, 'anggota/08-history-peminjaman.png', 'History Peminjaman (status Selesai)');
        await buka(c, '/anggota/booking/status');
        await foto(c, 'anggota/09-status-booking-akhir.png', 'Status Booking (Direalisasikan)');

        // 16. Keamanan: anggota membuka rute admin
        await c.kirim('Page.navigate', { url: BASE + '/admin/dashboard' });
        await tunggu(1200);
        const t403 = await c.js(`document.body.innerText`);
        catat('Anggota ditolak di /admin/dashboard', /403|Forbidden|tidak memiliki akses|Akses ditolak/i.test(t403), t403.slice(0, 80).replace(/\s+/g, ' '));
        await foto(c, 'keamanan/01-anggota-akses-admin-ditolak.png', 'Anggota membuka /admin/dashboard → ditolak', { penuh: false });

        HASIL.galatJs = c.log.galat;
        HASIL.respons5xx = c.log.gagal;
        catat('Tidak ada JavaScript error / respons 5xx', c.log.galat.length === 0 && c.log.gagal.length === 0, JSON.stringify({ js: c.log.galat.length, http5xx: c.log.gagal.length }));
    } finally {
        await c.tutup();
        server.kill();
        await tunggu(500);
        kosongkanUji();
        HASIL.ujiAkhir = JSON.parse(hitung('mysql_testing'));
        const devAkhir = hitung('mysql');
        HASIL.devAkhir = JSON.parse(devAkhir);
        HASIL.devIdentik = devAkhir === JSON.stringify(HASIL.devAwal);
        HASIL.selesai = new Date().toISOString();
        writeFileSync(LAPORAN, JSON.stringify(HASIL, null, 2));
        console.log('DB uji akhir:', JSON.stringify(HASIL.ujiAkhir), '| DB dev identik:', HASIL.devIdentik);
    }
}

main().catch((e) => { console.error('FATAL:', e.message); process.exitCode = 1; });
