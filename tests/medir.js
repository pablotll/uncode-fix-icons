// Mide el caracter que el navegador dibuja de verdad en cada icono de prueba.
// Uso: node tests/medir.js [url]   (necesita `npm i -g playwright` y
// `npx playwright install chromium`)
//
// Compara contra lo esperado y sale con codigo 1 si algo no cuadra.
const { chromium } = require('playwright');

const ESPERADO = {
  't-clock':  'f017',  // reloj; sin el plugin sale e072 (papel tachado)
  't-sync':   'f021',
  't-times':  'f00d',
  't-search': 'f002',  // no choca: debe quedar igual con y sin plugin
  't-fbf':    'f39e',
  't-tiktok': 'e07b',
  // Iconos del tema. Se rompen si FA carga DESPUES de uncode-icons.css (le gana
  // el codepoint de FA) o con FA >= 6.6, cuya regla `.fa::before{content:var(--fa)}`
  // los pisa todos: los que FA no conoce (search3, twitter-x-fa) desaparecen.
  'u-tiktok': 'e92f',
  'u-clock':  'e072',
  'u-search3': 'e088',
  'u-twx':    'e942',
  'u-insta':  'f16d',
};

(async () => {
  const url = process.argv[2] || 'http://localhost:8931/';
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto(url, { waitUntil: 'networkidle' });

  const medido = await page.evaluate((ids) => ids.map((id) => {
    const el = document.getElementById(id);
    if (!el) return { id, cp: 'ausente' };
    const s = getComputedStyle(el, '::before');
    const txt = (s.content || '').replace(/^["']|["']$/g, '');
    return {
      id,
      clase: el.className,
      cp: txt ? txt.codePointAt(0).toString(16) : 'vacio',
      fuente: (s.fontFamily || '').split(',')[0].replace(/"/g, ''),
      peso: s.fontWeight,
    };
  }), Object.keys(ESPERADO));

  await browser.close();

  let fallos = 0;
  for (const m of medido) {
    // Los del tema, ademas, sin la negrita sintetica que pone `.fa{font-weight:900}` de FA.
    const ok = m.cp === ESPERADO[m.id] && (!m.id.startsWith('u-') || m.peso === '400');
    if (!ok) fallos++;
    console.log(`${ok ? 'OK ' : 'XX '} ${m.id.padEnd(9)} ${(m.clase || '').padEnd(22)} \\${m.cp.padEnd(6)} esperado \\${ESPERADO[m.id].padEnd(5)} ${m.fuente || ''} ${m.peso || ''}`);
  }
  console.log(fallos ? `\n${fallos} icono(s) mal` : `\n${medido.length}/${medido.length} correctos`);
  process.exit(fallos ? 1 : 0);
})();
