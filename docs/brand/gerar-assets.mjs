import { chromium } from 'playwright';
import { readFileSync } from 'fs';
const root = '/home/scabello/projetos/ebd';
const read = (p) => readFileSync(`${root}/${p}`, 'utf8');
const lora = `${root}/node_modules/@fontsource-variable/lora/files/lora-latin-wght-normal.woff2`;
const inter = `${root}/node_modules/@fontsource-variable/inter/files/inter-latin-wght-normal.woff2`;
const b = await chromium.launch();
async function shot(html, w, h, out, transparent = false) {
    const p = await b.newPage({ viewport: { width: w, height: h } });
    await p.setContent(
        `<style>@font-face{font-family:Lora;src:url(data:font/woff2;base64,${readFileSync(lora).toString('base64')})}@font-face{font-family:Inter;src:url(data:font/woff2;base64,${readFileSync(inter).toString('base64')})}html,body{margin:0;width:${w}px;height:${h}px;overflow:hidden;${transparent ? 'background:transparent' : ''}}svg{display:block}</style>${html}`,
        { waitUntil: 'load' },
    );
    await p.evaluate(() => document.fonts.ready);
    await p.screenshot({ path: `${root}/${out}`, omitBackground: transparent });
    await p.close();
}
const sized = (svg, w, h) =>
    svg.replace('<svg ', `<svg width="${w}" height="${h}" `);
const appIconSquare = read('public/icons/icon.svg').replace(
    'rx="230"',
    'rx="0"',
);
await shot(
    sized(read('public/icons/icon.svg'), 192, 192),
    192,
    192,
    'public/icons/icon-192.png',
    true,
);
await shot(
    sized(read('public/icons/icon.svg'), 512, 512),
    512,
    512,
    'public/icons/icon-512.png',
    true,
);
await shot(
    sized(read('public/brand/icone-maskable.svg'), 512, 512),
    512,
    512,
    'public/icons/maskable-512.png',
);
await shot(
    sized(appIconSquare, 180, 180),
    180,
    180,
    'public/apple-touch-icon.png',
);
await shot(
    sized(read('public/favicon.svg'), 32, 32),
    32,
    32,
    'public/brand/favicon-32.png',
    true,
);
await shot(
    sized(read('public/favicon.svg'), 16, 16),
    16,
    16,
    'public/brand/favicon-16.png',
    true,
);
await shot(
    `<div style="color:#fff">${sized(read('public/brand/simbolo-mono.svg'), 96, 96)}</div>`,
    96,
    96,
    'public/icons/badge-96.png',
    true,
);
// Open Graph 1200x630: papel, símbolo, nome em Lora e assinatura.
await shot(
    `<div style="width:1200px;height:630px;background:#F3EEE5;display:flex;align-items:center;justify-content:center;gap:56px;font-family:Lora">
  ${sized(read('public/brand/simbolo.svg'), 300, 237)}
  <div><div style="font-size:132px;color:#1F2B23;line-height:1;letter-spacing:-1px">Didaché</div>
  <div style="font-family:Inter;font-size:26px;letter-spacing:7px;color:#8F6C48;margin-top:22px;text-transform:uppercase">Ensino bíblico em comunidade</div></div></div>`,
    1200,
    630,
    'public/brand/og-didache.png',
);
await b.close();
console.log('ok');
