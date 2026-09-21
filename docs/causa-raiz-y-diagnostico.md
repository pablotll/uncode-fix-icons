# Colisión de iconos: Uncode contra Font Awesome de otro plugin

Consultar cuando un icono salga como un glifo que no tiene nada que ver (un reloj
que se ve como un rollo de papel tachado, una X como cuadro vacío), o antes de
agregar iconos `fa-*` a plantillas de un sitio con el tema Uncode.

## Síntoma

El icono equivocado aparece **aunque el HTML sea correcto**. Ese es el detalle que
despista: inspeccionas el elemento, la clase dice `<i class="fas fa-clock">`, y aun
así se pinta otro glifo. No es un error de plantilla ni de contenido.

El caso que originó este fix: un horario de GeoDirectory ("Abierto ahora: …") que
salía con un **rollo de papel higiénico tachado** en vez de un reloj.

## Causa raíz — dos sets de iconos peleando por el namespace `.fa-*`

1. Un plugin cualquiera encola **Font Awesome 6 completo en todo el sitio**, aunque
   la página no use ninguna de sus funciones. En el caso original fue **WPVR**
   (tours 360), que encola FA 6.5.1 con el handle `wpvrfontawesome-css`.
2. El tema **Uncode** carga **después** `library/css/uncode-icons.css`, que redefine
   **~1570 clases `.fa-*`** con los codepoints de su fuente propia, `uncodeicon`.
3. Uncode fuerza la familia **solo en `.fa`** (`.fa{font-family:'uncodeicon'!important}`).
   **No toca `.fas` / `.far` / `.fab` / `.fa-solid` / `.fa-regular` / `.fa-brands`.**

Para un `<i class="fas fa-clock">` (lo que emite GeoDirectory) el resultado es un
híbrido:

| Propiedad | Quién gana | Por qué |
|---|---|---|
| `font-family` | Font Awesome | la regla `.fas` de `all.css` es la única que aplica |
| `content` | Uncode | misma especificidad (`.fa-clock:before`), pero carga después |

Es decir: **la fuente de Font Awesome pintando el codepoint de Uncode**. Uncode dice
`\e072`, y `\e072` en FA6 Solid es `fa-toilet-paper-slash` — el rollo de papel.

Los iconos propios de Uncode (`<i class="fa fa-tiktok">`) **no** se ven afectados:
llevan `.fa`, así que familia y content salen ambos de Uncode.

**128 clases colisionan** entre FA 6.5.1 y `uncode-icons.css`.

## Por qué NO se arregla cambiando el orden de carga

Es lo primero que uno intenta y no funciona, en ninguna de las dos direcciones:

- Si Font Awesome carga **después**, gana su `content` y se rompen los iconos propios
  del tema.
- Si carga **antes** (la situación actual), gana el `content` de Uncode y se rompen
  los iconos del plugin que emite prefijos explícitos.

No hay un orden correcto porque ambas hojas reclaman el mismo namespace completo. La
única salida limpia es **acotar por prefijo**, que es lo que hace este fix.

## La solución

Una regla por cada clase en colisión, que restaura el codepoint de Font Awesome
**solo cuando el elemento lleva prefijo explícito FA5/6** — prefijos que Uncode nunca
usa:

```css
:is(.fas,.far,.fab,.fa-solid,.fa-regular,.fa-brands,.fa-classic).fa-clock::before{content:"\f017"}
```

Especificidad **0,2,0** (dos clases) contra el **0,1,1** de `.fa-clock:before` de
Uncode. Gana sin `!important` y sin tocar nada del tema.

El bloque completo está en [`dist/uncode-fa-collisions.css`](../dist/uncode-fa-collisions.css).
Se aplica pegándolo **al final** del `style.css` del child theme, que carga después
de `uncode-icons.css`.

## Diagnóstico sin navegador

```bash
# 1. Orden real de las hojas en la página afectada
curl -s https://SITIO/pagina/ | grep -o "href=['\"][^'\"]*\.css[^'\"]*"

# 2. Qué codepoint impone Uncode
grep -o '\.fa-clock[^}]*}' uncode-icons.css        # -> .fa-clock:before{content:"\e072"}

# 3. Qué icono es ese codepoint en Font Awesome
grep -B4 'content: "\\e072"' all.css               # -> .fa-toilet-paper-slash
```

Si el codepoint que impone Uncode corresponde a un icono distinto en Font Awesome,
es esta colisión.

Confirmación en navegador (Playwright / DevTools):

```js
getComputedStyle(document.querySelector('i.fas.fa-clock'),'::before').content
getComputedStyle(document.querySelector('i.fas.fa-clock'),'::before').fontFamily
// content de Uncode + fontFamily de Font Awesome = colisión confirmada
```

## ¿Este sitio está afectado? (10 segundos, sin SSH)

Hacen falta **tres** ingredientes. Los tres se ven desde fuera:

```bash
# 1 y 2: ¿está el proveedor (Font Awesome) y está Uncode reclamando el namespace?
curl -s https://SITIO/ | grep -o "href=['\"][^'\"]*\(fontawesome\|uncode-icons\)[^'\"]*"

# 3: ¿hay alguien emitiendo prefijos explícitos? (el consumidor)
curl -s https://SITIO/ | grep -o '\(fas\|far\|fab\) fa-[a-z0-9-]*' | sort -u
```

⚠️ **El prefijo por sí solo no basta.** Que aparezcan clases `fas`/`far`/`fab` no
quiere decir que el sitio esté roto: además tienen que ser **de las 128 que
colisionan**. En el sitio donde se detectó, de 21 clases en uso solo 3 colisionan; las otras 18
se pintan bien. Dar por roto un sitio por ver un `fas` es una falsa alarma.

- **Sin los dos primeros** → no es este problema, busca en otro lado.
- **Los dos primeros, sin el tercero** → **riesgo latente**: la pólvora está puesta y
  nadie dispara. Nada que aplicar ni síntoma que ver, pero vale la pena anotarlo.
- **Los tres** → sigue al paso que decide, abajo.

### El paso que decide: cruzar contra el bloque

```bash
comm -12 \
  <(curl -s https://SITIO/ | grep -o '\(fas\|far\|fab\) fa-[a-z0-9-]*' | awk '{print $2}' | sort -u) \
  <(grep -o '\.fa-[a-z0-9-]*::before' dist/uncode-fa-collisions.css | sed 's/^\.//;s/::before//' | sort -u)
```

Si sale **vacío**, el sitio tiene los tres ingredientes pero ninguna clase en
colisión: no hay nada que arreglar hoy. Si sale **con clases**, esas son exactamente
las que se están pintando mal.

Normalmente son pocas. Y hay una cuarta capa, que no es técnica: **que además se
vea**. En el caso original la clase en colisión llevaba meses en el sitio, pero el
icono quedaba tapado por la descripción de las tarjetas; el día que quitaron la
descripción apareció el reloj equivocado, y pareció un bug nuevo. No lo era.

## Regenerar el bloque

`scripts/generar-colisiones.py` compara las dos hojas y emite las reglas:

```bash
python3 scripts/generar-colisiones.py all.css uncode-icons.css > dist/uncode-fa-collisions.css
```

Hay que regenerarlo si **Font Awesome o Uncode suben de versión**, porque los
codepoints se mueven. Conviene correrlo y hacer `diff` contra el bloque aplicado:
si no hay diferencias, no hay nada que hacer.

## Advertencias

- **La fuente de Font Awesome puede desaparecer.** En un sitio así, FA existe *solo*
  porque un plugin la encola. Si ese plugin se desactiva o deja de encolarla, los
  iconos que dependían de ella se quedan sin fuente. Hay que encolar FA desde el
  child theme o activar la carga de FA del propio plugin que emite los iconos (en
  GeoDirectory, en los ajustes de AyeCode UI).
- **WPVR está empezando a acotar su Font Awesome.** Desde 9.1.3 trae un
  `icons-fix.css` que define familias propias (`"WPVR Font Awesome 6 Free"`) acotadas
  a `.wpvr-cardboard`. Por ahora `all.css` sigue siendo global y el fix sigue siendo
  necesario, pero si algún día acotan `all.css` también, los iconos del sitio que
  dependían de esa fuente se quedan sin ella. Ver la advertencia anterior.
- **No confundir al proveedor con la raíz.** Hay tres piezas y conviene no
  mezclarlas:

  | Pieza | Papel | En el caso original |
  |---|---|---|
  | **Uncode** | **quien rompe** — redefine ~1570 clases `.fa-*` sin acotar el prefijo | el tema |
  | Proveedor | quien mete Font Awesome al sitio | WPVR |
  | Consumidor | quien emite `fas`/`far`/`fab` y se ve mal | GeoDirectory / AyeCode |

  La raíz es **Uncode**, no el proveedor. Desactivar el plugin que encola Font
  Awesome no arregla nada: cambia un icono equivocado por un icono roto, porque los
  consumidores se quedan sin fuente. Y si el consumidor carga su propia Font Awesome
  —AyeCode UI tiene esa opción en los ajustes de GeoDirectory— **el choque con Uncode
  persiste igual**. El conflicto de fondo es Uncode contra cualquier Font Awesome
  real, venga de donde venga.
- **Si hay caché de página (Varnish, Cloudflare), hay que invalidarla** después de
  editar el `style.css` del child. El cache-busting por `filemtime()` solo sirve si
  el HTML se regenera.
