#!/usr/bin/env node
/*
 * responsive-audit.js — auditoría de adaptabilidad de EBAEMY por CDP.
 *
 * Sin dependencias npm: Node 20 + `--experimental-websocket` + el Chrome instalado.
 * Chrome headless IGNORA --window-size en este equipo (el escalado de Windows lo
 * fija en ~504 px), así que el ancho se fuerza con Emulation.setDeviceMetricsOverride
 * y se COMPRUEBA con document.documentElement.clientWidth antes de creer una medida.
 *
 * Uso:
 *   node --experimental-websocket responsive-audit.js [opciones] <url> [<url> ...]
 *
 * Opciones:
 *   --out <dir>            carpeta de salida (informe JSON/MD + PNG). Def: ./responsive-out
 *   --widths 360,390,...   anchos a medir. Def: 360,390,768,1024,1366
 *   --file <txt>           una URL por línea; «etiqueta|url» opcional; # comenta
 *   --cookie "k=v"         cookie a enviar (repetible). Para el panel del tenant:
 *                          --cookie "laravel_session=XXXX" --cookie "XSRF-TOKEN=YYYY"
 *   --open <selector>      tras medir, abre este elemento (click real por CDP) ...
 *   --options <selector>   ... y comprueba con elementFromPoint que el centro de cada
 *                          opción visible sea clicable (detecta backdrops/overlays encima)
 *   --click-option         además hace click real en la primera opción bloqueada o
 *                          en la primera opción, y dice si pasó algo (URL o DOM)
 *   --full                 captura la página completa (tope 6000 px) en vez del viewport
 *   --wait <ms>            espera extra tras cargar (def 1200)
 *   --chrome <ruta>        ejecutable de Chrome/Edge
 *   --label <txt>          prefijo para los nombres de archivo
 *
 * Salida: <out>/report.json, <out>/report.md, <out>/<label>_<ancho>.png
 * (y <label>_<ancho>_open.png si se usa --open). Chrome se cierra SIEMPRE.
 */
'use strict';
const { spawn } = require('child_process');
const http = require('http');
const fs = require('fs');
const os = require('os');
const path = require('path');

if (typeof WebSocket === 'undefined') {
  console.error('Falta WebSocket global: ejecuta con  node --experimental-websocket ' + path.basename(__filename));
  process.exit(2);
}

// ───────────────────────── argumentos ─────────────────────────
const opt = { out: path.resolve('responsive-out'), widths: [360, 390, 768, 1024, 1366], cookies: [], urls: [], wait: 1200, full: false, clickOption: false };
let targets = [];
function parseArgs(argv) {
for (let i = 0; i < argv.length; i++) {
  const a = argv[i];
  const next = () => argv[++i];
  if (a === '--out') opt.out = path.resolve(next());
  else if (a === '--widths') opt.widths = next().split(',').map(Number).filter(Boolean);
  else if (a === '--file') {
    fs.readFileSync(next(), 'utf8').split(/\r?\n/).map(s => s.trim()).filter(s => s && !s.startsWith('#')).forEach(l => opt.urls.push(l));
  }
  else if (a === '--cookie') opt.cookies.push(next());
  else if (a === '--open') opt.open = next();
  else if (a === '--options') opt.options = next();
  else if (a === '--click-option') opt.clickOption = true;
  else if (a === '--full') opt.full = true;
  else if (a === '--wait') opt.wait = Number(next());
  else if (a === '--chrome') opt.chrome = next();
  else if (a === '--label') opt.label = next();
  else if (a === '-h' || a === '--help') { console.log(fs.readFileSync(__filename, 'utf8').split('*/')[0]); process.exit(0); }
  else opt.urls.push(a);
}
if (!opt.urls.length) { console.error('Indica al menos una URL (o --file). --help para ayuda.'); process.exit(2); }
targets = opt.urls.map((l, idx) => {
  const [maybeLabel, maybeUrl] = l.includes('|') ? l.split('|') : [null, l];
  const url = (maybeUrl || maybeLabel).trim();
  let label = maybeLabel && maybeUrl ? maybeLabel.trim() : slug(url);
  if (opt.label) label = opt.label + (opt.urls.length > 1 ? '_' + (idx + 1) : '');
  return { label, url };
});
fs.mkdirSync(opt.out, { recursive: true });
}

function slug(u) {
  try { const x = new URL(u); return (x.hostname.replace(/\.ebaemy\.com$/, '') + x.pathname + (x.search || '')).replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '').slice(0, 70) || 'root'; }
  catch { return 'url'; }
}
const sleep = ms => new Promise(r => setTimeout(r, ms));

// ───────────────────────── Chrome ─────────────────────────
function findChrome() {
  const c = [opt.chrome, process.env.CHROME_PATH,
    'C:/Program Files/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
    '/usr/bin/google-chrome', '/usr/bin/chromium', '/usr/bin/chromium-browser',
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'].filter(Boolean);
  const f = c.find(p => { try { return fs.existsSync(p); } catch { return false; } });
  if (!f) throw new Error('No encuentro Chrome. Usa --chrome <ruta> o CHROME_PATH.');
  return f;
}
function getJSON(url) {
  return new Promise((res, rej) => http.get(url, r => { let s = ''; r.on('data', d => s += d); r.on('end', () => { try { res(JSON.parse(s)); } catch (e) { rej(e); } }); }).on('error', rej));
}

let chromeProc = null, profileDir = null;
function killChrome() {
  if (chromeProc && !chromeProc.killed) {
    try {
      if (process.platform === 'win32') require('child_process').spawnSync('taskkill', ['/PID', String(chromeProc.pid), '/T', '/F'], { stdio: 'ignore' });
      else chromeProc.kill('SIGKILL');
    } catch { /* ya cerrado */ }
  }
  chromeProc = null;
  if (profileDir) {
    // Windows retiene el lock del perfil unos cientos de ms tras matar Chrome: reintentar.
    for (let i = 0; i < 10; i++) {
      try { fs.rmSync(profileDir, { recursive: true, force: true }); break; }
      catch { Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, 300); }
    }
    profileDir = null;
  }
}
process.on('exit', killChrome);
['SIGINT', 'SIGTERM'].forEach(s => process.on(s, () => { killChrome(); process.exit(130); }));
process.on('uncaughtException', e => { console.error(e); killChrome(); process.exit(1); });

async function launch() {
  profileDir = fs.mkdtempSync(path.join(os.tmpdir(), 'eb-resp-'));
  chromeProc = spawn(findChrome(), [
    '--headless=new', '--remote-debugging-port=0', '--user-data-dir=' + profileDir,
    '--no-first-run', '--no-default-browser-check', '--disable-extensions', '--hide-scrollbars',
    '--mute-audio', '--disable-gpu', '--force-device-scale-factor=1', 'about:blank'
  ], { stdio: 'ignore' });
  const portFile = path.join(profileDir, 'DevToolsActivePort');
  for (let i = 0; i < 100 && !fs.existsSync(portFile); i++) await sleep(100);
  const port = fs.readFileSync(portFile, 'utf8').split('\n')[0].trim();
  let page;
  for (let i = 0; i < 30 && !page; i++) { page = (await getJSON(`http://127.0.0.1:${port}/json`)).find(t => t.type === 'page'); if (!page) await sleep(200); }
  const ws = new WebSocket(page.webSocketDebuggerUrl);
  await new Promise((r, j) => { ws.addEventListener('open', r); ws.addEventListener('error', j); });
  let id = 0; const pending = new Map(); const listeners = [];
  ws.addEventListener('message', ev => {
    const m = JSON.parse(ev.data);
    if (m.id && pending.has(m.id)) { pending.get(m.id)(m); pending.delete(m.id); }
    else if (m.method) listeners.forEach(l => l(m));
  });
  const send = (method, params = {}) => new Promise(r => { const i = ++id; pending.set(i, r); ws.send(JSON.stringify({ id: i, method, params })); });
  const evalJS = async (expr) => {
    const r = await send('Runtime.evaluate', { expression: expr, awaitPromise: true, returnByValue: true });
    if (r.result && r.result.exceptionDetails) throw new Error('JS: ' + JSON.stringify(r.result.exceptionDetails.exception && r.result.exceptionDetails.exception.description));
    return r.result.result.value;
  };
  await send('Page.enable'); await send('Runtime.enable'); await send('Network.enable');
  return { send, evalJS, ws, on: f => listeners.push(f) };
}

// ───────────────────────── emulación ─────────────────────────
const UA_MOBILE = 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36';
const UA_TABLET = 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
const UA_DESKTOP = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';
const HEIGHTS = { 360: 780, 390: 844, 768: 1024, 1024: 768, 1366: 768 };
async function emulate(cdp, w) {
  const mobile = w < 1024;
  await cdp.send('Emulation.setDeviceMetricsOverride', { width: w, height: HEIGHTS[w] || (mobile ? 844 : 800), deviceScaleFactor: 1, mobile, screenWidth: w, screenHeight: HEIGHTS[w] || 800 });
  await cdp.send('Emulation.setTouchEmulationEnabled', { enabled: mobile, maxTouchPoints: mobile ? 5 : 0 });
  await cdp.send('Network.setUserAgentOverride', { userAgent: w <= 480 ? UA_MOBILE : (mobile ? UA_TABLET : UA_DESKTOP) });
}
async function setCookies(cdp, url) {
  for (const c of opt.cookies) {
    const eq = c.indexOf('=');
    await cdp.send('Network.setCookie', { name: c.slice(0, eq).trim(), value: c.slice(eq + 1).trim(), url });
  }
}
async function navigate(cdp, url) {
  const r = await cdp.send('Page.navigate', { url });
  await sleep(600);
  for (let i = 0; i < 60; i++) {
    let st; try { st = await cdp.evalJS('document.readyState'); } catch { st = ''; }
    if (st === 'complete') break; await sleep(500);
  }
  await sleep(opt.wait);
  return r.result && r.result.errorText;
}
async function screenshot(cdp, file, full) {
  let params = { format: 'png' };
  if (full) {
    const h = await cdp.evalJS('Math.min(6000, Math.max(document.documentElement.scrollHeight, document.body ? document.body.scrollHeight : 0))');
    const w = await cdp.evalJS('document.documentElement.clientWidth');
    params = { format: 'png', captureBeyondViewport: true, clip: { x: 0, y: 0, width: w, height: h, scale: 1 } };
  }
  const r = await cdp.send('Page.captureScreenshot', params);
  if (r.result && r.result.data) fs.writeFileSync(file, Buffer.from(r.result.data, 'base64'));
}
async function realClick(cdp, x, y) {
  for (const type of ['mouseMoved', 'mousePressed', 'mouseReleased']) {
    await cdp.send('Input.dispatchMouseEvent', { type, x, y, button: 'left', clickCount: type === 'mouseMoved' ? 0 : 1 });
  }
}

// ───────────────────────── checks dentro de la página ─────────────────────────
// Se inyecta como texto. Todo lo que devuelve es JSON plano.
const PAGE_LIB = String.raw`
(function(){
  if (window.__ebResp) return;
  function sel(el){
    if (!el || el.nodeType !== 1) return String(el);
    if (el.id) return '#' + CSS.escape(el.id);
    var parts = [], cur = el, depth = 0;
    while (cur && cur.nodeType === 1 && depth < 4 && cur !== document.body && cur !== document.documentElement) {
      var p = cur.tagName.toLowerCase();
      var cls = (typeof cur.className === 'string' ? cur.className : (cur.getAttribute('class') || '')).trim().split(/\s+/).filter(function(c){ return c && !/^(is-|active|show|open|hover)/.test(c); }).slice(0, 2);
      if (cls.length) p += '.' + cls.map(function(c){ return CSS.escape(c); }).join('.');
      if (cur.id) { parts.unshift('#' + CSS.escape(cur.id)); break; }
      parts.unshift(p); cur = cur.parentElement; depth++;
    }
    return parts.join(' > ');
  }
  function visible(el){
    if (!el.isConnected) return false;
    var r = el.getBoundingClientRect(); if (r.width < 1 || r.height < 1) return false;
    var cs = getComputedStyle(el);
    if (cs.visibility === 'hidden' || cs.display === 'none' || parseFloat(cs.opacity) === 0) return false;
    for (var a = el; a && a !== document.documentElement; a = a.parentElement) {
      var s = getComputedStyle(a); if (s.display === 'none' || parseFloat(s.opacity) === 0) return false;
    }
    return true;
  }
  function rect(el){ var r = el.getBoundingClientRect(); return { x: Math.round(r.left), y: Math.round(r.top), w: Math.round(r.width), h: Math.round(r.height) }; }
  // ¿Está el elemento recortado por algún ancestro (overflow != visible) que sí cabe en el viewport?
  function clippedByAncestor(el, cw){
    for (var a = el.parentElement; a && a !== document.body && a !== document.documentElement; a = a.parentElement) {
      var cs = getComputedStyle(a);
      if (cs.overflowX !== 'visible' && cs.overflowX !== '') { var r = a.getBoundingClientRect(); if (r.right <= cw + 1 && r.left >= -1) return true; }
      if (cs.position === 'fixed') return true; // fijos no generan scroll del documento
    }
    return false;
  }
  function info(el){
    var cs = getComputedStyle(el);
    return { selector: sel(el), tag: el.tagName.toLowerCase(), position: cs.position, zIndex: cs.zIndex, pointerEvents: cs.pointerEvents, opacity: cs.opacity, display: cs.display, rect: rect(el) };
  }
  function blockerChain(el){
    var chain = [];
    for (var a = el; a && a !== document.documentElement && chain.length < 6; a = a.parentElement) {
      var cs = getComputedStyle(a);
      if (cs.position !== 'static' || cs.zIndex !== 'auto' || cs.transform !== 'none') chain.push(info(a));
    }
    return chain;
  }
  window.__ebResp = { sel: sel, visible: visible, rect: rect, info: info, blockerChain: blockerChain,
  audit: function(){
    var de = document.documentElement, cw = de.clientWidth, vh = window.innerHeight, mobile = cw < 1024;
    var out = { viewport: { clientWidth: cw, innerWidth: window.innerWidth, innerHeight: vh, dpr: window.devicePixelRatio },
      scrollWidth: Math.max(de.scrollWidth, document.body ? document.body.scrollWidth : 0) };
    out.horizontalScroll = out.scrollWidth > cw + 1;

    // 1. Elementos que se salen del viewport (sólo los "raíz" del desborde).
    var all = Array.prototype.slice.call(document.body ? document.body.querySelectorAll('*') : []);
    var offenders = [];
    var offSet = new Set();
    all.forEach(function(el){
      if (/^(SCRIPT|STYLE|NOSCRIPT|BR|svg|path|TEMPLATE)$/i.test(el.tagName)) return;
      var r = el.getBoundingClientRect(); if (r.width < 1 || r.height < 1) return;
      // Por la izquierda sólo cuenta si asoma (un honeypot a -9999px no es un fallo).
      if (r.right > cw + 1 || (r.left < -1 && r.right > 0)) {
        var cs = getComputedStyle(el);
        if (cs.position === 'fixed' || cs.visibility === 'hidden') return;
        if (clippedByAncestor(el, cw)) return;
        offSet.add(el);
      }
    });
    offSet.forEach(function(el){ if (!offSet.has(el.parentElement)) offenders.push({ selector: sel(el), rect: rect(el), overflowPx: Math.round(Math.max(el.getBoundingClientRect().right - cw, -el.getBoundingClientRect().left)) }); });
    offenders.sort(function(a,b){ return b.overflowPx - a.overflowPx; });
    out.overflowing = offenders.slice(0, 15); out.overflowingCount = offenders.length;
    // Sin scroll pero con desbordes: html/body tienen overflow-x:hidden y el contenido queda CORTADO.
    out.clippedByRoot = !out.horizontalScroll && offenders.length > 0;

    // 2. Objetivos táctiles < 44x44 y 3. inputs < 16px (iOS hace zoom).
    var touch = [], smallInputs = [];
    if (mobile) {
      document.querySelectorAll('a[href],button,input:not([type=hidden]),select,textarea,[role=button],[role=menuitem],[role=tab],summary,[onclick]').forEach(function(el){
        if (!visible(el)) return;
        var r = el.getBoundingClientRect();
        if (r.bottom < 0 || r.top > de.scrollHeight) return;
        if (r.width <= 2 && r.height <= 2) return; // patrón sr-only / enlace SEO invisible
        var cs = getComputedStyle(el);
        // Excepción WCAG 2.5.8: enlace en línea dentro de un párrafo de texto.
        if (el.tagName === 'A' && cs.display === 'inline' && el.parentElement && /^(P|LI|SPAN|SMALL|LABEL|TD)$/.test(el.parentElement.tagName) && (el.parentElement.textContent || '').trim().length > (el.textContent || '').trim().length + 15) return;
        // Un hijo interactivo dentro de otro interactivo más grande cuenta como el padre.
        var parentHit = el.parentElement && el.parentElement.closest('a[href],button,label');
        if (parentHit && parentHit !== el) { var pr = parentHit.getBoundingClientRect(); if (pr.width >= 44 && pr.height >= 44) return; }
        if ((el.type === 'checkbox' || el.type === 'radio') && el.closest('label')) { var lr = el.closest('label').getBoundingClientRect(); if (lr.height >= 44 || lr.width >= 44) return; }
        if (r.width < 44 || r.height < 44) touch.push({ selector: sel(el), w: Math.round(r.width), h: Math.round(r.height), text: (el.innerText || el.value || el.getAttribute('aria-label') || '').trim().slice(0, 40) });
        if (/^(INPUT|SELECT|TEXTAREA)$/.test(el.tagName) && !/^(checkbox|radio|range|color|file|submit|button|image|reset)$/.test(el.type) && parseFloat(cs.fontSize) < 16)
          smallInputs.push({ selector: sel(el), fontSize: cs.fontSize });
      });
    }
    var groupBy = function(arr){ var m = {}; arr.forEach(function(x){ var k = x.selector; if (!m[k]) m[k] = Object.assign({ count: 0 }, x); m[k].count++; }); return Object.keys(m).map(function(k){ return m[k]; }); };
    var tg = groupBy(touch).sort(function(a,b){ return (a.w*a.h) - (b.w*b.h); });
    out.smallTouchTargets = tg.slice(0, 25); out.smallTouchTargetsCount = touch.length;
    out.smallInputs = groupBy(smallInputs).slice(0, 15); out.smallInputsCount = smallInputs.length;

    // 4. Texto < 12 px.
    var tiny = [];
    var tw = document.createTreeWalker(document.body || de, NodeFilter.SHOW_TEXT, null);
    var n, seen = new Set();
    while ((n = tw.nextNode())) {
      if (!n.nodeValue || !n.nodeValue.trim()) continue;
      var p = n.parentElement; if (!p || seen.has(p)) continue; seen.add(p);
      if (/^(SCRIPT|STYLE|NOSCRIPT|OPTION)$/.test(p.tagName)) continue;
      var fs = parseFloat(getComputedStyle(p).fontSize);
      if (fs < 12 && visible(p)) tiny.push({ selector: sel(p), fontSize: fs, text: n.nodeValue.trim().slice(0, 40) });
    }
    out.tinyText = groupBy(tiny).slice(0, 25); out.tinyTextCount = tiny.length;

    // 5. Imágenes deformadas o desbordadas.
    var imgs = [];
    document.querySelectorAll('img').forEach(function(img){
      if (!visible(img) || !img.complete || !img.naturalWidth) return;
      var r = img.getBoundingClientRect(), cs = getComputedStyle(img);
      var nat = img.naturalWidth / img.naturalHeight, ren = r.width / r.height;
      var fit = cs.objectFit;
      var issue = null;
      if (fit === 'fill' && Math.abs(ren - nat) / nat > 0.06 && r.width > 24 && r.height > 24) issue = 'deformada (object-fit:fill, proporción ' + nat.toFixed(2) + ' → ' + ren.toFixed(2) + ')';
      var par = img.parentElement && img.parentElement.getBoundingClientRect();
      if (!issue && par && r.width > par.width + 2 && getComputedStyle(img.parentElement).overflowX === 'visible') issue = 'más ancha que su contenedor (' + Math.round(r.width) + ' > ' + Math.round(par.width) + ')';
      if (!issue && r.right > cw + 1 && !clippedByAncestor(img, cw)) issue = 'se sale del viewport';
      if (issue) imgs.push({ selector: sel(img), issue: issue, src: (img.currentSrc || img.src || '').slice(-80) });
    });
    out.badImages = groupBy(imgs).slice(0, 20); out.badImagesCount = imgs.length;

    // 6. Interactivos tapados (estado inicial): el centro no es suyo.
    var covered = [];
    document.querySelectorAll('a[href],button,input:not([type=hidden]),select,textarea,[role=button]').forEach(function(el){
      if (!visible(el)) return;
      var r = el.getBoundingClientRect();
      var cx = r.left + r.width / 2, cy = r.top + r.height / 2;
      if (cx < 0 || cy < 0 || cx > cw || cy > vh) return;
      if (getComputedStyle(el).pointerEvents === 'none') return;
      var hit = document.elementFromPoint(cx, cy);
      if (!hit || hit === el || el.contains(hit) || hit.contains(el)) return;
      if (hit.closest('label') && hit.closest('label').contains(el)) return;
      covered.push({ selector: sel(el), coveredBy: info(hit), chain: blockerChain(hit) });
    });
    out.coveredInteractive = groupBy(covered).slice(0, 15); out.coveredInteractiveCount = covered.length;

    // 7. Modales / diálogos más anchos que el viewport.
    var dialogs = [];
    document.querySelectorAll('[role=dialog],dialog[open],.el-dialog,.el-drawer,.el-message-box,.modal-dialog,.modal-content,.swal2-popup,[aria-modal=true]').forEach(function(el){
      if (!visible(el)) return;
      var r = el.getBoundingClientRect();
      if (r.width > cw + 1 || r.left < -1 || r.right > cw + 1) dialogs.push({ selector: sel(el), w: Math.round(r.width), left: Math.round(r.left), viewport: cw, inlineWidth: el.style.width || null });
      else if (mobile && cw < 600 && r.width < cw * 0.9 && el.matches('.el-dialog,.modal-dialog')) dialogs.push({ selector: sel(el), w: Math.round(r.width), viewport: cw, note: 'no ocupa la pantalla en móvil' });
    });
    out.wideDialogs = dialogs;
    return out;
  },
  // Para un elemento abierto: ¿se puede pulsar el centro de cada opción?
  probeOptions: function(optionsSel){
    var cw = document.documentElement.clientWidth, vh = window.innerHeight, res = [];
    var list = Array.prototype.slice.call(document.querySelectorAll(optionsSel));
    list.forEach(function(el){
      var r = el.getBoundingClientRect();
      var cs = getComputedStyle(el);
      var vis = r.width > 0 && r.height > 0 && cs.visibility !== 'hidden' && cs.display !== 'none';
      if (!vis) return;
      var cx = r.left + r.width / 2, cy = r.top + r.height / 2;
      if (cx < 0 || cy < 0 || cx > cw || cy > vh) return;
      var hit = document.elementFromPoint(cx, cy);
      var ok = !!hit && (hit === el || el.contains(hit));
      res.push({ selector: sel(el), text: (el.innerText || '').trim().slice(0, 30), center: [Math.round(cx), Math.round(cy)], clickable: ok,
        ownPointerEvents: cs.pointerEvents, hit: hit ? info(hit) : null, hitChain: (!ok && hit) ? blockerChain(hit) : [] });
    });
    return { total: list.length, probed: res.length, blocked: res.filter(function(x){ return !x.clickable; }).length, results: res.slice(0, 40) };
  }};
})();`;

// ───────────────────────── markdown ─────────────────────────
function md(report) {
  const L = [];
  L.push(`# Auditoría responsive — ${report.date}`, '', `Anchos: ${report.widths.join(', ')} px. Chrome headless por CDP (Emulation.setDeviceMetricsOverride).`, '');
  L.push('| Página | Ancho | Scroll-x | Desbordan | Táctiles <44 | Inputs <16px | Texto <12px | Imágenes | Tapados | Modales | Abierto: opciones bloqueadas |');
  L.push('|---|---|---|---|---|---|---|---|---|---|---|');
  for (const p of report.pages) for (const r of p.runs) {
    if (r.error) { L.push(`| ${p.label} | ${r.width} | ERROR: ${r.error} |||||||||`); continue; }
    const a = r.audit;
    L.push(`| ${p.label} | ${r.width}${a.viewport.clientWidth !== r.width ? ' (!' + a.viewport.clientWidth + ')' : ''} | ${a.horizontalScroll ? '**SÍ** (' + a.scrollWidth + ')' : 'no'} | ${a.overflowingCount} | ${a.smallTouchTargetsCount} | ${a.smallInputsCount} | ${a.tinyTextCount} | ${a.badImagesCount} | ${a.coveredInteractiveCount} | ${a.wideDialogs.length} | ${r.open ? (r.open.error ? 'ERR' : r.open.probe.blocked + '/' + r.open.probe.probed) : '—'} |`);
  }
  L.push('');
  for (const p of report.pages) {
    L.push(`## ${p.label}`, '', p.url, '');
    for (const r of p.runs) {
      if (r.error) { L.push(`### ${r.width}px — ERROR ${r.error}`, ''); continue; }
      const a = r.audit;
      L.push(`### ${r.width}px — ${path.basename(r.screenshot)}`);
      if (a.horizontalScroll) L.push(`- **Scroll horizontal**: scrollWidth ${a.scrollWidth} > ${a.viewport.clientWidth}`);
      if (a.clippedByRoot) L.push(`- **Contenido cortado**: no hay scroll porque html/body tienen overflow-x:hidden, pero estos elementos se salen y no se ven enteros:`);
      a.overflowing.slice(0, 8).forEach(o => L.push(`- Se sale ${o.overflowPx}px: \`${o.selector}\``));
      a.smallTouchTargets.slice(0, 10).forEach(t => L.push(`- Táctil ${t.w}×${t.h} (×${t.count}): \`${t.selector}\` «${t.text}»`));
      a.smallInputs.slice(0, 5).forEach(t => L.push(`- Input ${t.fontSize} (zoom iOS) ×${t.count}: \`${t.selector}\``));
      a.tinyText.slice(0, 8).forEach(t => L.push(`- Texto ${t.fontSize}px (×${t.count}): \`${t.selector}\` «${t.text}»`));
      a.badImages.slice(0, 6).forEach(t => L.push(`- Imagen ${t.issue} (×${t.count}): \`${t.selector}\``));
      a.coveredInteractive.slice(0, 6).forEach(t => L.push(`- Tapado \`${t.selector}\` por \`${t.coveredBy.selector}\` (z ${t.coveredBy.zIndex}, ${t.coveredBy.position})`));
      a.wideDialogs.forEach(t => L.push(`- Diálogo \`${t.selector}\` ${t.w}px en viewport ${t.viewport} ${t.note || ''}`));
      if (r.open) {
        if (r.open.error) L.push(`- Abrir \`${opt.open}\`: ERROR ${r.open.error}`);
        else {
          L.push(`- Abierto \`${opt.open}\`: ${r.open.probe.blocked} de ${r.open.probe.probed} opciones visibles bloqueadas`);
          r.open.probe.results.filter(x => !x.clickable).slice(0, 5).forEach(x => L.push(`  - «${x.text}» \`${x.selector}\` → encima \`${x.hit && x.hit.selector}\` (z ${x.hit && x.hit.zIndex}, pe ${x.hit && x.hit.pointerEvents})`));
          if (r.open.clickTest) L.push(`  - Click real en «${r.open.clickTest.text}»: ${r.open.clickTest.effect}`);
        }
      }
      if (r.jsErrors && r.jsErrors.length) L.push(`- Errores JS: ${r.jsErrors.slice(0, 3).map(e => '`' + e.slice(0, 120) + '`').join('; ')}`);
      L.push('');
    }
  }
  return L.join('\n');
}

// ───────────────────────── main ─────────────────────────
async function main() {
  parseArgs(process.argv.slice(2));
  const report = { date: new Date().toISOString(), widths: opt.widths, open: opt.open || null, options: opt.options || null, pages: [] };
  let cdp;
  try {
    cdp = await launch();
    let jsErrors = [];
    cdp.on(m => { if (m.method === 'Runtime.exceptionThrown') jsErrors.push((m.params.exceptionDetails.exception && m.params.exceptionDetails.exception.description) || m.params.exceptionDetails.text); });
    for (const t of targets) {
      const page = { label: t.label, url: t.url, runs: [] };
      report.pages.push(page);
      await setCookies(cdp, t.url);
      for (const w of opt.widths) {
        const run = { width: w };
        page.runs.push(run);
        process.stdout.write(`· ${t.label} @ ${w}px … `);
        try {
          jsErrors = [];
          await emulate(cdp, w);
          const err = await navigate(cdp, t.url);
          if (err) throw new Error(err);
          run.finalUrl = await cdp.evalJS('location.href');
          run.status = await cdp.evalJS('(performance.getEntriesByType("navigation")[0]||{}).responseStatus||null');
          await cdp.evalJS(PAGE_LIB);
          run.audit = await cdp.evalJS('window.__ebResp.audit()');
          run.screenshot = path.join(opt.out, `${t.label}_${w}.png`);
          await screenshot(cdp, run.screenshot, opt.full);
          if (opt.open) {
            run.open = {};
            try {
              await cdp.evalJS('window.scrollTo(0,0)');
              const r = await cdp.evalJS(`(function(){var e=document.querySelector(${JSON.stringify(opt.open)}); if(!e) return null; var r=e.getBoundingClientRect(); return {x:r.left+r.width/2,y:r.top+r.height/2,vis:r.width>0&&r.height>0};})()`);
              if (!r) throw new Error('no existe ' + opt.open);
              if (!r.vis) throw new Error('no visible ' + opt.open);
              await realClick(cdp, r.x, r.y);
              await sleep(800);
              run.open.afterOpen = await cdp.evalJS(`(function(){var e=document.querySelector(${JSON.stringify(opt.open)}); return {ariaExpanded:e&&e.getAttribute('aria-expanded'), bodyOverflow: document.body.style.overflow};})()`);
              if (opt.options) run.open.probe = await cdp.evalJS(`window.__ebResp.probeOptions(${JSON.stringify(opt.options)})`);
              run.open.screenshot = path.join(opt.out, `${t.label}_${w}_open.png`);
              await screenshot(cdp, run.open.screenshot, false);
              if (opt.clickOption && run.open.probe && run.open.probe.results.length) {
                const target = run.open.probe.results.find(x => !x.clickable) || run.open.probe.results[0];
                const before = await cdp.evalJS('location.href + "|" + document.body.innerHTML.length + "|" + (document.querySelector(".is-children-view")?1:0)');
                await realClick(cdp, target.center[0], target.center[1]);
                await sleep(1500);
                let after = '';
                try { after = await cdp.evalJS('location.href + "|" + document.body.innerHTML.length + "|" + (document.querySelector(".is-children-view")?1:0)'); } catch { after = 'navegó'; }
                run.open.clickTest = { text: target.text, selector: target.selector, before, after, effect: before === after ? 'NADA cambió (el click no llega a la opción)' : 'reaccionó (' + after.split('|')[0] + ')' };
              }
            } catch (e) { run.open.error = e.message; }
          }
          run.jsErrors = jsErrors.slice(0, 5);
          const a = run.audit;
          console.log(`cw=${a.viewport.clientWidth} scroll-x=${a.horizontalScroll} táctiles=${a.smallTouchTargetsCount} texto<12=${a.tinyTextCount} img=${a.badImagesCount} tapados=${a.coveredInteractiveCount}` + (run.open && run.open.probe ? ` bloqueadas=${run.open.probe.blocked}/${run.open.probe.probed}` : '') + (run.open && run.open.error ? ' open-err=' + run.open.error : ''));
        } catch (e) { run.error = e.message; console.log('ERROR ' + e.message); }
      }
    }
  } finally {
    try { if (cdp) cdp.ws.close(); } catch { /* */ }
    killChrome();
  }
  fs.writeFileSync(path.join(opt.out, 'report.json'), JSON.stringify(report, null, 2));
  fs.writeFileSync(path.join(opt.out, 'report.md'), md(report));
  console.log('\nInforme: ' + path.join(opt.out, 'report.md'));
}

if (require.main === module) {
  main().catch(e => { console.error(e); killChrome(); process.exit(1); });
} else {
  // Reutilizable desde un guion propio (escenarios de varios pasos):
  //   const R = require('./responsive-audit.js'); const cdp = await R.launch(); ...
  module.exports = { opt, launch, emulate, setCookies, navigate, screenshot, realClick, killChrome, PAGE_LIB, sleep };
}
