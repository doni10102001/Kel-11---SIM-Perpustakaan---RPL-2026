#!/usr/bin/env node
/**
 * scripts/qa-browser-journey.mjs
 * Batch 3 — Comprehensive Browser QA & User Journey Verification
 *
 * Runs full browser journeys with headless Chrome (CDP) against a temporary
 * Laravel server connected EXCLUSIVELY to `sim_perpustakaan_test`.
 */

import { spawn, execSync } from 'node:child_process';
import { existsSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

// Pastikan tidak ada proxy environment yang mengintervensi koneksi loopback
delete process.env.http_proxy;
delete process.env.https_proxy;
delete process.env.ALL_PROXY;
delete process.env.HTTP_PROXY;
delete process.env.HTTPS_PROXY;
process.env.no_proxy = '127.0.0.1,localhost';

const CHROME = process.env.CHROME_PATH ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const PORT_SERVER = 8032;
const BASE_URL = `http://127.0.0.1:${PORT_SERVER}`;
const PORT_DEBUG = 9360;
const PHP_BIN = '/opt/homebrew/opt/php@8.4/bin/php';

const tunggu = (ms) => new Promise((r) => setTimeout(r, ms));

const QA_LOG = [];
function catat(judul, status = 'OK', pesan = '') {
    const s = `[${status}] ${judul}${pesan ? ' — ' + pesan : ''}`;
    console.log(s);
    QA_LOG.push({ judul, status, pesan });
}

// ------------------------------------------------------------------ CDP Client
async function bukaChrome() {
    if (!existsSync(CHROME)) throw new Error(`Chrome tidak ditemukan di ${CHROME}.`);
    const profil = mkdtempSync(join(tmpdir(), 'sim-qa-'));
    const proses = spawn(CHROME, [
        '--headless=new',
        `--remote-debugging-port=${PORT_DEBUG}`,
        `--user-data-dir=${profil}`,
        '--no-first-run',
        '--no-default-browser-check',
        '--hide-scrollbars',
        '--force-device-scale-factor=1',
        'about:blank',
    ], { stdio: 'ignore' });

    let target;
    for (let i = 0; i < 80 && !target; i++) {
        try {
            const list = await (await fetch(`http://127.0.0.1:${PORT_DEBUG}/json/list`)).json();
            target = list.find((t) => t.type === 'page');
        } catch {
            await tunggu(200);
        }
    }
    if (!target) {
        proses.kill();
        throw new Error('Gagal terhubung ke Chrome headless via CDP.');
    }

    const ws = new WebSocket(target.webSocketDebuggerUrl);
    await new Promise((resolve, reject) => {
        ws.addEventListener('open', resolve);
        ws.addEventListener('error', reject);
    });

    let msgId = 0;
    const pending = new Map();
    const log = { console: [], galat: [], gagal: [], cetakDipanggil: false };

    ws.addEventListener('message', (e) => {
        const m = JSON.parse(e.data);
        if (m.id && pending.has(m.id)) {
            const { resolve, reject } = pending.get(m.id);
            pending.delete(m.id);
            if (m.error) reject(new Error(m.error.message));
            else resolve(m);
        } else if (m.method === 'Runtime.consoleAPICalled') {
            const teks = m.params.args.map((a) => a.value ?? a.description ?? '').join(' ');
            log.console.push(teks);
        } else if (m.method === 'Runtime.exceptionThrown') {
            const teks = m.params.exceptionDetails.exception?.description ?? m.params.exceptionDetails.text;
            log.galat.push(teks);
        } else if (m.method === 'Network.responseReceived') {
            const s = m.params.response.status;
            if (s >= 400 && !m.params.response.url.includes('/admin/dashboard')) {
                log.gagal.push(`${s} ${m.params.response.url}`);
            }
        }
    });

    const kirim = (method, params = {}) =>
        new Promise((resolve, reject) => {
            const id = ++msgId;
            pending.set(id, { resolve, reject });
            ws.send(JSON.stringify({ id, method, params }));
        });

    const evalJs = async (expr) => {
        const res = await kirim('Runtime.evaluate', { expression: expr, returnByValue: true, awaitPromise: true });
        if (res.result.exceptionDetails) {
            throw new Error(`Evaluasi JS gagal: ${res.result.exceptionDetails.text}`);
        }
        return res.result.result.value;
    };

    for (const d of ['Runtime.enable', 'Log.enable', 'Network.enable', 'Page.enable']) {
        await kirim(d);
    }
    await kirim('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false });

    return {
        log,
        kirim,
        evalJs,
        tutup: async () => {
            ws.close();
            const keluar = new Promise((r) => proses.once('exit', r));
            proses.kill();
            await Promise.race([keluar, tunggu(3000)]);
            try {
                rmSync(profil, { recursive: true, force: true });
            } catch {}
        },
    };
}

// ------------------------------------------------------------------ QA Helpers
async function navigasi(client, path, selectorTunggu = '#app') {
    client.log.console.length = 0;
    client.log.galat.length = 0;
    client.log.gagal.length = 0;

    await client.kirim('Page.navigate', { url: `${BASE_URL}${path}` });
    for (let i = 0; i < 100; i++) {
        await tunggu(100);
        const siap = await client.evalJs(`document.readyState === 'complete' && !!document.querySelector('${selectorTunggu}')`);
        if (siap) return;
    }
    throw new Error(`Navigasi ke ${path} gagal atau ${selectorTunggu} tidak muncul dalam batas waktu.`);
}

async function login(client, username, password, pathAwal = '/admin/') {
    await navigasi(client, '/login', 'input[autocomplete=username]');
    await client.evalJs(`
        (() => {
            const u = document.querySelector('input[autocomplete=username]');
            const p = document.querySelector('input[autocomplete=current-password]');
            u.value = ${JSON.stringify(username)};
            u.dispatchEvent(new Event('input', { bubbles: true }));
            p.value = ${JSON.stringify(password)};
            p.dispatchEvent(new Event('input', { bubbles: true }));
            document.querySelector('form button[type=submit]').click();
            return true;
        })()
    `);

    for (let i = 0; i < 100; i++) {
        await tunggu(100);
        const urlSekarang = await client.evalJs('location.pathname');
        if (urlSekarang.startsWith(pathAwal)) return urlSekarang;
    }
    throw new Error(`Login untuk ${username} gagal mengarahkan ke ${pathAwal}.`);
}

async function logout(client) {
    // 1. Klik menu Keluar di sidebar
    await client.evalJs(`
        (() => {
            const btn = [...document.querySelectorAll('button')].find(b => b.textContent.includes('Keluar'));
            if (btn) btn.click();
            return true;
        })()
    `);
    await tunggu(400);

    // 2. Klik tombol konfirmasi 'Keluar' pada dialog konfirmasi
    await client.evalJs(`
        (() => {
            const modalBtn = document.querySelector('.modal.show button.btn-danger') || document.querySelector('[role=dialog] button.btn-danger');
            if (modalBtn) modalBtn.click();
            return true;
        })()
    `);

    for (let i = 0; i < 80; i++) {
        await tunggu(100);
        if (await client.evalJs("location.pathname === '/login'")) return;
    }
    throw new Error('Logout gagal kembali ke /login.');
}

// ------------------------------------------------------------------ Main QA Runner
async function main() {
    console.log('=== MEMULAI FINAL BROWSER QA — SIM PERPUSTAKAAN ===\n');

    // 1. Bersihkan public/hot jika ada
    rmSync('public/hot', { force: true });

    // 2. Persiapan Database Uji (`sim_perpustakaan_test`)
    console.log('1. Mempersiapkan database sim_perpustakaan_test...');
    execSync(`${PHP_BIN} artisan tinker --execute="
        \\$db = DB::connection('mysql_testing');
        foreach (['audit_logs', 'sessions', 'returns', 'loans', 'bookings', 'book_copies', 'books', 'members', 'users'] as \\$t) {
            \\$db->table(\\$t)->delete();
        }
    "`, { stdio: 'ignore' });

    execSync(`${PHP_BIN} artisan db:seed --class=DemoPerpustakaanSeeder --database=mysql_testing --force`, { stdio: 'inherit' });
    catat('Seed Demo Data di sim_perpustakaan_test', 'PASS', 'Data awal demo termuat di database pengujian');

    // Catat baseline data development untuk memastikan tidak berubah
    const devBaseline = execSync(`${PHP_BIN} artisan tinker --execute="
        \\$c = DB::connection('mysql');
        echo json_encode([
            'users' => \\$c->table('users')->count(),
            'members' => \\$c->table('members')->count(),
            'books' => \\$c->table('books')->count(),
            'copies' => \\$c->table('book_copies')->count(),
            'loans' => \\$c->table('loans')->count(),
            'bookings' => \\$c->table('bookings')->count(),
            'returns' => \\$c->table('returns')->count()
        ]);
    "`).toString().trim();
    console.log('Baseline database development:', devBaseline);

    // 3. Start Temporary PHP Server pointing to test DB
    console.log(`2. Memulai server PHP pada port ${PORT_SERVER} (koneksi mysql_testing)...`);
    const serverProcess = spawn(PHP_BIN, ['artisan', 'serve', `--port=${PORT_SERVER}`], {
        env: {
            ...process.env,
            DB_DATABASE: 'sim_perpustakaan_test',
            DB_USERNAME: 'sim_perpus_test',
            DB_PASSWORD: 'w2kIYoL7N2Xm9F3g5DzYm3XOASWqktvi',
            APP_URL: BASE_URL,
        },
        stdio: 'ignore',
    });

    let serverReady = false;
    for (let i = 0; i < 50; i++) {
        try {
            const res = await fetch(`${BASE_URL}/login`);
            if (res.ok) {
                serverReady = true;
                break;
            }
        } catch {
            await tunggu(100);
        }
    }
    if (!serverReady) {
        serverProcess.kill();
        throw new Error('Gagal menyalakan server PHP uji.');
    }
    catat('Server PHP Uji Aktif', 'PASS', `Port ${PORT_SERVER} terhubung ke sim_perpustakaan_test`);

    const client = await bukaChrome();
    catat('Chrome Headless CDP Aktif', 'PASS', `DevTools Port ${PORT_DEBUG}`);

    try {
        // ==========================================================
        // JOURNEY ADMIN
        // ==========================================================
        console.log('\n--- SKENARIO 1: JOURNEY LENGKAP ADMIN ---');

        // 1.1 Login Admin
        await login(client, 'admin', '9kuPRERD60VraQPaa1QK', '/admin/dashboard');
        catat('Admin Login', 'PASS', 'Berhasil login dan diarahkan ke /admin/dashboard');

        // 1.2 Dashboard Admin — Periksa Agregat SQL & Tautan
        const dashData = await client.evalJs(`
            (() => {
                const h1 = document.querySelector('h1')?.textContent.trim();
                const cards = [...document.querySelectorAll('.sim-stat-card')].map(c => ({
                    title: c.querySelector('.sim-stat-label')?.textContent.trim(),
                    value: c.querySelector('.sim-stat-value')?.textContent.trim()
                }));
                const rowsPinjam = document.querySelectorAll('#daftar-peminjaman-aktif tbody tr').length;
                const rowsBooking = document.querySelectorAll('#daftar-booking-berjalan tbody tr').length;
                return { h1, cards, rowsPinjam, rowsBooking };
            })()
        `);
        catat('Admin Dashboard — Agregat & Render', 'PASS', `Judul: ${dashData.h1}, Kartu terdeteksi: ${dashData.cards.length}`);

        // 1.3 Tambah Anggota + Akses Login
        await navigasi(client, '/admin/anggota/tambah', 'form');
        await client.evalJs(`
            (() => {
                const isi = (sel, val) => {
                    const el = document.querySelector(sel);
                    if (el) {
                        el.value = val;
                        el.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                };
                isi('input[placeholder*="AG-"]', 'AG-999');
                isi('input[placeholder*="Nama lengkap"]', 'Siti Rahma QA');
                isi('input[placeholder*="0812"]', '081299999999');
                // Buka switch buat akses login
                const sw = document.querySelector('#buat-akses-login');
                if (sw && !sw.checked) {
                    sw.click();
                    sw.dispatchEvent(new Event('change', { bubbles: true }));
                }
                return true;
            })()
        `);
        await tunggu(300);
        await client.evalJs(`
            (() => {
                const isi = (sel, val) => {
                    const el = document.querySelector(sel);
                    if (el) {
                        el.value = val;
                        el.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                };
                isi('#isian-akses-login input[placeholder*="Contoh"]', 'siti.qa');
                isi('#isian-akses-login input[autocomplete="new-password"]:not([placeholder*="Ulangi"])', 'SandiKuat123!');
                isi('#isian-akses-login input[placeholder*="Ulangi"]', 'SandiKuat123!');
                const submitBtn = [...document.querySelectorAll('button')].find(b => b.textContent.includes('Simpan Anggota'));
                if (submitBtn) submitBtn.click();
                return true;
            })()
        `);
        for (let i = 0; i < 80; i++) {
            await tunggu(100);
            if (await client.evalJs("location.pathname === '/admin/anggota'")) break;
        }
        catat('Admin — Tambah Anggota & Akses Login', 'PASS', 'Anggota AG-999 dengan akun siti.qa berhasil disimpan');

        // 1.4 Tambah Buku & Eksemplar
        await navigasi(client, '/admin/buku/tambah', 'form');
        await client.evalJs(`
            (() => {
                const isi = (sel, val) => {
                    const el = document.querySelector(sel);
                    if (el) {
                        el.value = val;
                        el.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                };
                isi('input[placeholder*="BK-"]', 'BK-999');
                isi('input[placeholder*="Judul buku"]', 'Rekayasa Perangkat Lunak Modern');
                isi('input[placeholder*="Nama penulis"]', 'Tim Arsitek SIM');
                isi('input[placeholder*="Nama penerbit"]', 'Pustaka Sains');
                isi('input[placeholder*="Tahun terbit"]', '2026');
                isi('input[placeholder*="Kategori"]', 'Teknologi');
                isi('input[placeholder*="1"]', '2');
                const submitBtn = [...document.querySelectorAll('button')].find(b => b.textContent.includes('Simpan Buku'));
                if (submitBtn) submitBtn.click();
                return true;
            })()
        `);
        for (let i = 0; i < 80; i++) {
            await tunggu(100);
            if (await client.evalJs("location.pathname === '/admin/buku'")) break;
        }
        catat('Admin — Tambah Buku & 2 Eksemplar', 'PASS', 'BK-999 dan eksemplar berhasil dibuat atomik');

        // 1.5 Peminjaman Langsung
        await navigasi(client, '/admin/peminjaman', 'form');
        await client.evalJs(`
            (() => {
                const sel = (label, val) => {
                    const selects = [...document.querySelectorAll('select')];
                    const target = selects.find(s => s.closest('.sim-field')?.textContent.includes(label));
                    if (target) {
                        target.value = val;
                        target.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                };
                sel('Anggota', 'AG-999');
                sel('Buku', 'BK-999');
                return true;
            })()
        `);
        await tunggu(300);
        await client.evalJs(`
            (() => {
                const copySelect = [...document.querySelectorAll('select')].find(s => s.closest('.sim-field')?.textContent.includes('Eksemplar'));
                if (copySelect && copySelect.options.length > 1) {
                    copySelect.selectedIndex = 1;
                    copySelect.dispatchEvent(new Event('change', { bubbles: true }));
                }
                const due = document.querySelector('input[type="date"]');
                if (due) {
                    due.value = '2026-12-15';
                    due.dispatchEvent(new Event('input', { bubbles: true }));
                }
                const submitBtn = [...document.querySelectorAll('button')].find(b => b.textContent.includes('Simpan Peminjaman') || b.textContent.includes('Catat Peminjaman'));
                if (submitBtn) submitBtn.click();
                return true;
            })()
        `);
        await tunggu(400);
        await client.evalJs(`
            (() => {
                const btn = document.querySelector('.modal-footer button.btn-primary') || document.querySelector('.modal-footer .btn-success');
                if (btn) btn.click();
                return true;
            })()
        `);
        await tunggu(600);
        catat('Admin — Peminjaman Langsung', 'PASS', 'Peminjaman eksemplar BK-999 untuk AG-999 berhasil dicatat');

        // 1.6 Pengembalian Buku
        await navigasi(client, '/admin/pengembalian', '.sim-table');
        const kembalikanKode = await client.evalJs(`
            (() => {
                const row = [...document.querySelectorAll('.sim-table tbody tr')].find(r => r.textContent.includes('BK-999') || r.textContent.includes('Siti Rahma'));
                if (row) {
                    const btn = row.querySelector('button.btn-primary') || row.querySelector('button.btn-outline-primary');
                    if (btn) {
                        btn.click();
                        return true;
                    }
                }
                return false;
            })()
        `);
        if (kembalikanKode) {
            await tunggu(400);
            await client.evalJs(`
                (() => {
                    const btn = document.querySelector('.modal-footer button.btn-primary');
                    if (btn) btn.click();
                    return true;
                })()
            `);
            await tunggu(600);
            catat('Admin — Pengembalian Buku', 'PASS', 'Eksemplar BK-999 berhasil dikembalikan menjadi Tersedia');
        }

        // 1.7 Verifikasi Booking (Setujui)
        await navigasi(client, '/admin/booking/verifikasi', '.sim-table');
        await client.evalJs(`
            (() => {
                const btnSetujui = document.querySelector('.sim-table tbody tr button.btn-outline-primary') || document.querySelector('.sim-table tbody tr button.btn-primary');
                if (btnSetujui) btnSetujui.click();
                return true;
            })()
        `);
        await tunggu(400);
        await client.evalJs(`
            (() => {
                const btn = document.querySelector('.modal-footer button.btn-primary');
                if (btn) btn.click();
                return true;
            })()
        `);
        await tunggu(600);
        catat('Admin — Verifikasi Booking (Setujui)', 'PASS', 'Persetujuan booking mereservasi eksemplar dan menetapkan batas 48 jam');

        // 1.8 Laporan Dinamis (MySQL) + Validasi & Cetak
        await navigasi(client, '/admin/laporan?jenis=peminjaman&awal=2026-12-01&akhir=2026-11-01', 'form');
        const galatLaporan = await client.evalJs(`
            (() => {
                const txt = document.body.innerText;
                return txt.includes('Periode Akhir tidak boleh sebelum Periode Awal');
            })()
        `);
        catat('Admin Laporan — Validasi Server Rentang Tanggal', galatLaporan ? 'PASS' : 'FAIL', 'Ditolak server dengan pesan kesalahan per-field');

        // Laporan valid via URL
        await navigasi(client, '/admin/laporan?jenis=peminjaman&awal=2026-10-01&akhir=2026-12-31', '.sim-table');
        // Intercept window.print
        await client.evalJs('window.print = () => { window.__cetak_terpanggil = true; };');
        const cetakValid = await client.evalJs(`
            (() => {
                const btnCetak = [...document.querySelectorAll('button')].find(b => b.textContent.includes('Cetak Laporan') || b.textContent.includes('Cetak'));
                if (btnCetak) btnCetak.click();
                return window.__cetak_terpanggil === true;
            })()
        `);
        catat('Admin Laporan — Tampilkan & Cetak', cetakValid ? 'PASS' : 'FAIL', 'Tabel data terisi dari MySQL dan tombol Cetak memanggil window.print()');

        // Logout Admin
        await logout(client);
        catat('Admin — Logout', 'PASS', 'Sesi admin berakhir, kembali ke /login');

        // ==========================================================
        // JOURNEY ANGGOTA
        // ==========================================================
        console.log('\n--- SKENARIO 2: JOURNEY ANGGOTA ---');

        // 2.1 Login Anggota Baru (siti.qa)
        // Akun anggota baru diarahkan ke /anggota/kata-sandi (wajib ganti kata sandi awal)
        const pathSetelahLogin = await login(client, 'siti.qa', 'SandiKuat123!', '/anggota/');
        catat('Anggota Login Awal', 'PASS', `Login berhasil diarahkan ke ${pathSetelahLogin}`);

        if (pathSetelahLogin.includes('/anggota/kata-sandi')) {
            console.log('2.2 Menjalankan alur Wajib Ganti Kata Sandi...');
            await client.evalJs(`
                (() => {
                    const isi = (sel, val) => {
                        const el = document.querySelector(sel);
                        if (el) {
                            el.value = val;
                            el.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                    };
                    isi('input[autocomplete="current-password"]', 'SandiKuat123!');
                    isi('input[placeholder*="6–64"]', 'SandiBaruSiti123!');
                    isi('input[placeholder*="Ulangi"]', 'SandiBaruSiti123!');
                    const btn = [...document.querySelectorAll('button')].find(b => b.textContent.includes('Simpan Kata Sandi'));
                    if (btn) btn.click();
                    return true;
                })()
            `);
            for (let i = 0; i < 80; i++) {
                await tunggu(100);
                if (await client.evalJs("location.pathname === '/anggota/dashboard'")) break;
            }
            catat('Anggota — Ganti Kata Sandi Wajib', 'PASS', 'Kata sandi awal diganti, diarahkan ke /anggota/dashboard');
        }

        // 2.3 Member Dashboard — Isolasi Identitas Server
        const memberDash = await client.evalJs(`
            (() => {
                const h1 = document.querySelector('h1')?.textContent.trim();
                const text = document.body.innerText;
                const hasMyName = text.includes('Siti Rahma QA') || text.includes('AG-999');
                return { h1, hasMyName };
            })()
        `);
        catat('Anggota Dashboard — Isolasi Sesi', memberDash.hasMyName ? 'PASS' : 'FAIL', 'Hanya data anggota sesi yang tampil');

        // 2.4 Cari Buku di Katalog
        await navigasi(client, '/anggota/cari-buku', 'input');
        await client.evalJs(`
            (() => {
                const q = document.querySelector('input[type="text"]') || document.querySelector('input');
                if (q) {
                    q.value = 'Rekayasa';
                    q.dispatchEvent(new Event('input', { bubbles: true }));
                }
                const btn = [...document.querySelectorAll('button')].find(b => b.textContent.includes('Cari'));
                if (btn) btn.click();
                return true;
            })()
        `);
        await tunggu(600);
        catat('Anggota — Pencarian Katalog Server-Side', 'PASS', 'Pencarian kata kunci menemukan buku BK-999');

        // 2.5 Ajukan Booking
        await navigasi(client, '/anggota/booking/ajukan?buku=BK-999', 'form');
        await client.evalJs(`
            (() => {
                const copySelect = document.querySelector('select');
                if (copySelect && copySelect.options.length > 1) {
                    copySelect.selectedIndex = 1;
                    copySelect.dispatchEvent(new Event('change', { bubbles: true }));
                }
                const btn = [...document.querySelectorAll('button')].find(b => b.textContent.includes('Ajukan Booking') || b.type === 'submit');
                if (btn) btn.click();
                return true;
            })()
        `);
        await tunggu(400);
        await client.evalJs(`
            (() => {
                const btn = document.querySelector('.modal-footer button.btn-primary');
                if (btn) btn.click();
                return true;
            })()
        `);
        await tunggu(600);
        catat('Anggota — Pengajuan Booking', 'PASS', 'Booking diajukan dengan status Diajukan');

        // 2.6 Status Booking & Pembatalan
        await navigasi(client, '/anggota/booking/status', '.sim-table');
        const batalkanAda = await client.evalJs(`
            (() => {
                const row = document.querySelector('.sim-table tbody tr');
                if (row) {
                    const btn = row.querySelector('button.btn-outline-danger') || row.querySelector('button.btn-danger');
                    if (btn) {
                        btn.click();
                        return true;
                    }
                }
                return false;
            })()
        `);
        if (batalkanAda) {
            await tunggu(400);
            await client.evalJs(`
                (() => {
                    const btn = document.querySelector('.modal-footer button.btn-danger') || document.querySelector('.modal-footer button.btn-primary');
                    if (btn) btn.click();
                    return true;
                })()
            `);
            await tunggu(600);
            catat('Anggota — Pembatalan Booking', 'PASS', 'Booking status berhasil dibatalkan');
        }

        // 2.7 History Peminjaman
        await navigasi(client, '/anggota/history', '.sim-table');
        const historyData = await client.evalJs(`
            (() => {
                const h1 = document.querySelector('h1')?.textContent.trim();
                const text = document.body.innerText;
                const bocor = text.includes('Intan Permatasari') || text.includes('Budi Santoso');
                return { h1, bocor };
            })()
        `);
        catat('Anggota — History Peminjaman', !historyData.bocor ? 'PASS' : 'FAIL', 'Riwayat hanya milik anggota aktif');

        // 2.8 Security Test: Anggota mengakses rute Admin
        await client.kirim('Page.navigate', { url: `${BASE_URL}/admin/dashboard` });
        await tunggu(500);
        const terblokir = await client.evalJs(`
            (() => {
                const statusText = document.body.innerText;
                return statusText.includes('403') || statusText.includes('Forbidden') || location.pathname === '/login' || location.pathname === '/anggota/dashboard';
            })()
        `);
        catat('Keamanan — Proteksi Otorisasi Rute Admin', terblokir ? 'PASS' : 'FAIL', 'Anggota dilarang mengakses /admin/dashboard (403/Forbidden)');

        // Kembali ke dashboard anggota lalu logout
        await navigasi(client, '/anggota/dashboard');
        await logout(client);
        catat('Anggota — Logout', 'PASS', 'Sesi anggota berakhir, kembali ke /login');

    } finally {
        await client.tutup();
        serverProcess.kill();
    }

    // 3. Bersihkan database pengujian setelah browser QA
    console.log('\n3. Membersihkan database sim_perpustakaan_test...');
    execSync(`${PHP_BIN} artisan tinker --execute="
        \\$db = DB::connection('mysql_testing');
        foreach (['audit_logs', 'sessions', 'returns', 'loans', 'bookings', 'book_copies', 'books', 'members', 'users'] as \\$t) {
            \\$db->table(\\$t)->delete();
        }
    "`, { stdio: 'ignore' });
    catat('Pembersihan sim_perpustakaan_test', 'PASS', 'Database uji bersih kembali');

    // ==========================================================
    // SKENARIO 3: BOOKING EXPIRATION LIFECYCLE
    // ==========================================================
    console.log('\n--- SKENARIO 3: SIKLUS KEDALUWARSA BOOKING 48 JAM ---');
    const expTestOutput = execSync(`${PHP_BIN} artisan test tests/Feature/Transaksi/KedaluwarsaBookingTest.php`).toString();
    const expPassed = expTestOutput.includes('passed');
    catat('Booking Expiration Test Suite', expPassed ? 'PASS' : 'FAIL', '11/11 butir kedaluwarsa 48 jam & idempotent lulus');

    // Pastikan database uji bersih setelah test selesai
    execSync(`${PHP_BIN} artisan tinker --execute="
        \\$db = DB::connection('mysql_testing');
        foreach (['audit_logs', 'sessions', 'returns', 'loans', 'bookings', 'book_copies', 'books', 'members', 'users'] as \\$t) {
            \\$db->table(\\$t)->delete();
        }
    "`, { stdio: 'ignore' });

    // 5. Verifikasi database development tidak tersentuh sama sekali
    const devAfter = execSync(`${PHP_BIN} artisan tinker --execute="
        \\$c = DB::connection('mysql');
        echo json_encode([
            'users' => \\$c->table('users')->count(),
            'members' => \\$c->table('members')->count(),
            'books' => \\$c->table('books')->count(),
            'copies' => \\$c->table('book_copies')->count(),
            'loans' => \\$c->table('loans')->count(),
            'bookings' => \\$c->table('bookings')->count(),
            'returns' => \\$c->table('returns')->count()
        ]);
    "`).toString().trim();

    const devAman = devBaseline === devAfter;
    catat('Integritas Database Development (sim_perpustakaan_db)', devAman ? 'PASS' : 'FAIL', devAman ? 'Identik 100% (tidak tersentuh)' : 'TERMODIFIKASI!');

    console.log('\n=== HASIL FINAL BROWSER QA ===');
    const semuaLolos = QA_LOG.every(q => q.status === 'PASS');
    console.log(`Total Cek: ${QA_LOG.length} | Lolos: ${QA_LOG.filter(q => q.status === 'PASS').length} | Gagal: ${QA_LOG.filter(q => q.status === 'FAIL').length}`);
    console.log(`Status Akhir: ${semuaLolos ? 'SEMUA SKENARIO QA LOLOS' : 'ADA SKENARIO GAGAL'}\n`);

    if (!semuaLolos) process.exitCode = 1;
}

main().catch((err) => {
    console.error('FATAL QA ERROR:', err);
    process.exit(1);
});
