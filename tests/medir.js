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
  'u-tiktok': 'e92f',  // icono del tema: el plugin no debe tocarlo
  'u-clock':  'e072',  // icono del tema: el plugin no debe tocarlo
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
    };
  }), Object.keys(ESPERADO));

  await browser.close();

  let fallos = 0;
  for (const m of medido) {
    const ok = m.cp === ESPERADO[m.id];
    if (!ok) fallos++;
    console.log(`${ok ? 'OK ' : 'XX '} ${m.id.padEnd(9)} ${(m.clase || '').padEnd(20)} \\${m.cp.padEnd(6)} esperado \\${ESPERADO[m.id]}  ${m.fuente || ''}`);
  }
  console.log(fallos ? `\n${fallos} icono(s) mal` : `\n${medido.length}/${medido.length} correctos`);
  process.exit(fallos ? 1 : 0);
})();
