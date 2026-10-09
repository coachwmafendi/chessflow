#!/usr/bin/env node
// Builds the favicons and the social share image from the ChessFlow mark (rhosgfx knight, CC0).
// Run after `npm run build` (the share image embeds the built Baloo 2 / Lexend fonts):
//   node tools/make-brand-assets.cjs
const { chromium } = require('@playwright/test');
const fs = require('node:fs');
const path = require('node:path');

const root = path.join(__dirname, '..');
const pub = path.join(root, 'public');
const piecesCss = fs.readFileSync(path.join(root, 'resources/css/chessflow/pieces.css'), 'utf8');

const ACCENT = '#0B8577';

function pieceSvg(code) {
    const m = piecesCss.match(new RegExp(`\\.${code}\\{background-image:url\\('data:image/svg\\+xml;base64,([^']+)'\\)`));
    if (!m) throw new Error(`Piece ${code} not found in pieces.css`);
    return Buffer.from(m[1], 'base64').toString('utf8').replace(/<\?xml[^>]*>/, '').trim();
}

/** The header logo mark: white knight on a rounded teal square. radius 0 = full-bleed (iOS rounds it itself). */
function markSvg(radius) {
    const knight = pieceSvg('wN').replace('<svg ', '<svg x="7" y="5" width="50" height="50" ');
    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="${radius}" fill="${ACCENT}"/>${knight}</svg>\n`;
}

function font(name) {
    const file = fs.readdirSync(path.join(pub, 'build/assets')).find((f) => f.startsWith(name) && f.endsWith('.woff2'));
    if (!file) throw new Error(`Font ${name} missing: run npm run build first`);
    return fs.readFileSync(path.join(pub, 'build/assets', file)).toString('base64');
}

/** ICO container holding PNG images (supported by every current browser). */
function ico(pngs) {
    const header = Buffer.alloc(6 + 16 * pngs.length);
    header.writeUInt16LE(0, 0);
    header.writeUInt16LE(1, 2);
    header.writeUInt16LE(pngs.length, 4);
    let offset = header.length;
    pngs.forEach(({ size, data }, i) => {
        const e = 6 + 16 * i;
        header.writeUInt8(size >= 256 ? 0 : size, e);
        header.writeUInt8(size >= 256 ? 0 : size, e + 1);
        header.writeUInt16LE(1, e + 4);
        header.writeUInt16LE(32, e + 6);
        header.writeUInt32LE(data.length, e + 8);
        header.writeUInt32LE(offset, e + 12);
        offset += data.length;
    });
    return Buffer.concat([header, ...pngs.map((p) => p.data)]);
}

function shareHtml() {
    const sq = (x, y) => ((x + y) % 2 ? 'd' : 'l');
    const pieces = [['bR', 0, 0], ['bK', 4, 0], ['bP', 5, 1], ['bP', 6, 1], ['wP', 5, 6], ['wP', 6, 6], ['wK', 6, 7]];
    let board = '';
    for (let y = 0; y < 8; y++) for (let x = 0; x < 8; x++) board += `<span class="${sq(x, y)}"></span>`;
    const pcs = pieces
        .map(([c, x, y]) => `<i class="pc ${c}" style="left:${x * 12.5}%;top:${y * 12.5}%"></i>`)
        .join('');
    return `<!doctype html><html><head><meta charset="utf-8"><style>
@font-face{font-family:B;src:url(data:font/woff2;base64,${font('baloo-2-800')}) format('woff2');font-weight:800}
@font-face{font-family:L;src:url(data:font/woff2;base64,${font('lexend-400')}) format('woff2');font-weight:400}
${piecesCss}
.pc{background-size:100% 100%;background-repeat:no-repeat;position:absolute;width:12.5%;height:12.5%}
*{box-sizing:border-box;margin:0}
body{width:1200px;height:630px;overflow:hidden;background:#EEF3F7;font-family:L;color:#18263A;position:relative}
.glow{position:absolute;inset:0;background:radial-gradient(circle at 82% 22%,#FFF1D2,transparent 45%),radial-gradient(circle at 70% 95%,#D6EFEB,transparent 50%)}
.copy{position:absolute;left:72px;top:70px;width:600px}
.logo{display:flex;align-items:center;gap:16px;font-family:B;font-size:44px}
.logo .m{width:68px;height:68px}
.logo b{font-weight:800}.logo b span{color:${ACCENT}}
h1{font-family:B;font-weight:800;font-size:78px;line-height:.98;letter-spacing:-.02em;margin-top:56px}
h1 em{font-style:normal;color:${ACCENT}}
p{font-size:26px;color:#58677D;margin-top:26px;line-height:1.35}
.tags{display:flex;gap:10px;margin-top:30px}
.tags b{font-weight:400;font-size:20px;background:#fff;border:1px solid #D6E0E8;border-radius:99px;padding:8px 18px}
.board{position:absolute;right:70px;top:95px;width:440px;height:440px;display:grid;grid-template-columns:repeat(8,1fr);border:12px solid #28463F;border-radius:22px;overflow:hidden;transform:rotate(-3deg);box-shadow:0 40px 70px -24px rgba(40,70,63,.6)}
.board .l{background:#F2E7D0}.board .d{background:#7FA89F}
.ring{position:absolute;width:12.5%;height:12.5%;border-radius:50%;box-shadow:inset 0 0 0 5px #E5484D;background:rgba(229,72,77,.2)}
.knight{left:25%;top:12.5%;filter:drop-shadow(0 8px 5px rgba(0,0,0,.3))}
</style></head><body><div class="glow"></div>
<div class="copy">
<div class="logo"><span class="m">${markSvg(16).replace('<svg ', '<svg width="68" height="68" ')}</span><b>Chess<span>Flow</span></b></div>
<h1>Belajar catur dalam <em>Bahasa Melayu</em></h1>
<p>Langkah demi langkah untuk kanak-kanak dan pemula, dibimbing Pak Kuda.</p>
<div class="tags"><b>Papan interaktif</b><b>Teka-teki harian</b><b>Sijil</b></div>
</div>
<div class="board">${board}${pcs}<b class="ring" style="left:0;top:0"></b><b class="ring" style="left:50%;top:0"></b><i class="pc wN knight"></i></div>
</body></html>`;
}

(async () => {
    fs.writeFileSync(path.join(pub, 'favicon.svg'), markSvg(14));

    const browser = await chromium.launch({ channel: process.env.PW_CHANNEL ?? 'chrome' });
    const page = await browser.newPage();

    async function png(svg, size) {
        await page.setViewportSize({ width: size, height: size });
        await page.setContent(`<style>*{margin:0}svg{display:block;width:${size}px;height:${size}px}</style>${svg}`);
        return page.screenshot({ omitBackground: true, type: 'png' });
    }

    const icoPngs = [];
    for (const size of [16, 32, 48]) icoPngs.push({ size, data: await png(markSvg(14), size) });
    fs.writeFileSync(path.join(pub, 'favicon.ico'), ico(icoPngs));
    fs.writeFileSync(path.join(pub, 'apple-touch-icon.png'), await png(markSvg(0), 180));

    await page.setViewportSize({ width: 1200, height: 630 });
    await page.setContent(shareHtml());
    await page.evaluate(() => document.fonts.ready);
    fs.mkdirSync(path.join(pub, 'images'), { recursive: true });
    await page.screenshot({ path: path.join(pub, 'images/og-chessflow.png'), type: 'png' });

    await browser.close();
    console.log('Wrote favicon.svg, favicon.ico, apple-touch-icon.png, images/og-chessflow.png');
})().catch((e) => {
    console.error(e);
    process.exit(1);
});
