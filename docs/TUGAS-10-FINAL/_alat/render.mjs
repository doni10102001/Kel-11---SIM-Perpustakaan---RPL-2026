// Render .mmd (Mermaid) dan .svg ke PNG via Chrome headless (CDP).
// Pemakaian: node --experimental-websocket render.mjs <file1.mmd|.svg> [...]
import { spawn } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const PORT = 9381;
const tunggu = (ms) => new Promise((r) => setTimeout(r, ms));
const files = process.argv.slice(2).map((f) => resolve(f));

const html = `<!doctype html><html><head><meta charset="utf-8"><style>body{margin:0;background:#fff;font-family:Arial,Helvetica,sans-serif}#c{display:inline-block;padding:24px}</style>
<script src="${pathToFileURL(join(HERE, 'node_modules/mermaid/dist/mermaid.min.js'))}"></script></head><body><div id="c"></div>
<script>mermaid.initialize({startOnLoad:false, securityLevel:'strict', theme:'neutral', fontFamily:'Arial, Helvetica, sans-serif',
 flowchart:{htmlLabels:true, curve:'basis', nodeSpacing:40, rankSpacing:48, wrappingWidth:260},
 sequence:{useMaxWidth:false, wrap:true, width:190, messageFontSize:15, actorFontSize:15, noteFontSize:14, mirrorActors:false},
 class:{useMaxWidth:false}, themeVariables:{fontSize:'16px'}});</script></body></html>`;
const pageFile = join(HERE, '_render.html');
writeFileSync(pageFile, html);

const profil = mkdtempSync(join(tmpdir(), 'mmd-'));
const chrome = spawn(CHROME, ['--headless=new', `--remote-debugging-port=${PORT}`, `--user-data-dir=${profil}`, '--allow-file-access-from-files', '--hide-scrollbars', 'about:blank'], { stdio: 'ignore' });
let target;
for (let i = 0; i < 80 && !target; i++) { try { target = (await (await fetch(`http://127.0.0.1:${PORT}/json/list`)).json()).find((t) => t.type === 'page'); } catch { await tunggu(200); } }
const ws = new WebSocket(target.webSocketDebuggerUrl);
await new Promise((r) => ws.addEventListener('open', r));
let id = 0; const pend = new Map();
ws.addEventListener('message', (e) => { const m = JSON.parse(e.data); if (m.id && pend.has(m.id)) { const p = pend.get(m.id); pend.delete(m.id); m.error ? p.j(new Error(m.error.message)) : p.r(m.result); } });
const kirim = (method, params = {}) => new Promise((r, j) => { const i = ++id; pend.set(i, { r, j }); ws.send(JSON.stringify({ id: i, method, params })); });
const js = async (expression) => { const r = await kirim('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true }); if (r.exceptionDetails) throw new Error(r.exceptionDetails.exception?.description || r.exceptionDetails.text); return r.result.value; };

await kirim('Page.enable');
for (const f of files) {
    await kirim('Emulation.setDeviceMetricsOverride', { width: 3000, height: 2000, deviceScaleFactor: 2, mobile: false });
    await kirim('Page.navigate', { url: pathToFileURL(pageFile).href });
    for (let i = 0; i < 50; i++) { await tunggu(100); if (await js(`typeof mermaid !== 'undefined' && document.readyState==='complete'`)) break; }
    const src = readFileSync(f, 'utf8');
    if (f.endsWith('.svg')) {
        await js(`document.getElementById('c').innerHTML = ${JSON.stringify(src)}; true`);
    } else {
        const svg = await js(`mermaid.render('d${Date.now()}', ${JSON.stringify(src)}).then(r => { document.getElementById('c').innerHTML = r.svg; return r.svg; })`);
        writeFileSync(f.replace(/\.mmd$/, '.svg'), svg);
    }
    await js(`(()=>{const s=document.querySelector('#c svg'); const vb=s.viewBox && s.viewBox.baseVal; if (vb && vb.width) { s.setAttribute('width', vb.width); s.setAttribute('height', vb.height); s.style.maxWidth='none'; } return true;})()`);
    await tunggu(300);
    const box = await js(`(()=>{const r=document.getElementById('c').getBoundingClientRect();return {w:Math.ceil(r.width),h:Math.ceil(r.height)}})()`);
    await kirim('Emulation.setDeviceMetricsOverride', { width: Math.max(box.w, 200), height: Math.max(box.h, 200), deviceScaleFactor: 2, mobile: false });
    await tunggu(300);
    const shot = await kirim('Page.captureScreenshot', { format: 'png', clip: { x: 0, y: 0, width: box.w, height: box.h, scale: 1 } });
    const out = f.replace(/\.(mmd|svg)$/, '.png');
    writeFileSync(out, Buffer.from(shot.data, 'base64'));
    console.log(`ok ${out} (${box.w * 2}x${box.h * 2})`);
}
ws.close(); chrome.kill(); await tunggu(300); rmSync(profil, { recursive: true, force: true }); rmSync(pageFile, { force: true });
