# Uncode Fix Icons

**Un plugin de WordPress que arregla los íconos que salen equivocados cuando el tema
Uncode convive con cualquier plugin que carga Font Awesome.** Se instala desde el
escritorio: sin child theme, sin FTP y sin tocar una línea de CSS.

---

## Por qué existe esto

Amo Uncode. Es el tema con el que hago casi todos mis sitios, y de verdad hay muy
pocas cosas que me hayan hecho sufrir con él. Esta es una de esas pocas.

Un día, en un directorio de restaurantes hecho con Uncode y GeoDirectory, el ícono
del horario —un relojito junto a "Abierto ahora"— empezó a salir como **un rollo de
papel higiénico tachado**. En serio. Revisé el HTML y estaba perfecto:
`<i class="fas fa-clock">`. La clase era correcta, el plugin era correcto, el tema
era correcto… y aun así, papel de baño.

Cuando por fin entendí por qué pasaba, vi que no era un error de ese sitio: le puede
pasar a **cualquier** sitio con Uncode que tenga un plugin que traiga Font Awesome.
Como no parece que vaya a arreglarse en el tema, hice este plugin para que nadie más
tenga que volverse loco con esto. Es público y gratis para quien lo necesite.

---

## El síntoma

Un ícono se ve como un glifo que no tiene nada que ver con lo que debería ser —un
reloj que sale como un rollo de papel tachado, una X que sale como un cuadro vacío—
**aunque el HTML sea correcto**:

```html
<i class="fas fa-clock"></i>   <!-- debería ser un reloj; sale un rollo de papel tachado -->
```

Eso es lo que despista: inspeccionas el elemento, la clase está bien, y no hay nada
que corregir en la plantilla ni en el contenido. El problema está en el CSS, entre
dos hojas que no se conocen.

Le pasa a los íconos que ponen **otros plugins** —GeoDirectory y el resto de AyeCode
son el caso más común, pero puede ser cualquiera que use los prefijos `fas`, `far` o
`fab`—. Los íconos propios de Uncode se ven bien.

---

## Por qué pasa

Dos juegos de íconos se pelean por los mismos nombres de clase, `.fa-*`.

1. **Uncode** trae su propia fuente de íconos, `uncodeicon`, y en
   `library/css/uncode-icons.css` redefine unas **1570 clases `.fa-*`** con sus propios
   codepoints. Para que el tema pueda escribir `<i class="fa fa-clock">` y le salga
   *su* reloj.
2. Pero Uncode solo fuerza su fuente en la clase `.fa`:
   ```css
   .fa { font-family: 'uncodeicon' !important; }
   ```
   No toca `.fas`, `.far`, `.fab`, `.fa-solid` ni `.fa-brands`, que son los prefijos
   de Font Awesome 5 y 6.
3. Mientras tanto, algún plugin carga **Font Awesome** en todo el sitio. En mi caso
   fue WPVR (tours 360), que lo carga en todas las páginas aunque no tengan ningún
   tour.

Para un `<i class="fas fa-clock">` el resultado es un híbrido:

| Propiedad | ¿Quién gana? | ¿Por qué? |
|---|---|---|
| La **fuente** | Font Awesome | la regla `.fas` es la única que aplica |
| El **carácter** (`content`) | Uncode | misma especificidad que la de Font Awesome, pero carga después |

O sea: **la fuente de Font Awesome dibujando el carácter de Uncode.** Uncode dice
`\e072`, y en Font Awesome 6 el `\e072` es… `fa-toilet-paper-slash`. Ahí está el
papel de baño.

Entre Font Awesome 6.5.1 y Uncode 2.12 hay **128 clases** que chocan así.

### Quién tiene la culpa

Es fácil culpar al plugin equivocado, así que conviene separar las piezas:

| Pieza | Qué hace | En mi caso |
|---|---|---|
| **Uncode** | **rompe** — reclama todos los nombres `.fa-*` sin acotarse a su prefijo | el tema |
| El proveedor | mete Font Awesome al sitio | WPVR |
| El consumidor | usa `fas`/`far`/`fab` y es donde se ve el daño | GeoDirectory |

La raíz es **Uncode**. El plugin que trae Font Awesome no es el culpable: si lo
cambias por otro, o si GeoDirectory cargara su propio Font Awesome, el choque sería
exactamente igual.

### Lo que no funciona

- **Cambiar el orden de las hojas.** Si Font Awesome carga después, se rompen los
  íconos propios de Uncode. Si carga antes (lo normal), se rompen los de los plugins.
  Las dos hojas definen los mismos nombres con la misma especificidad, así que la que
  carga al final le gana a la otra en *todos* ellos: con cualquier orden, uno de los
  dos lados pierde. Medí los dos órdenes y pasa exactamente eso.
- **Desactivar el plugin que trae Font Awesome.** Cambias un ícono equivocado por un
  ícono roto: los plugins que usaban esa fuente se quedan sin ella.

---

## La solución

Devolverle a Font Awesome su carácter **solo cuando el elemento lleva un prefijo
explícito de Font Awesome**, que es justo lo que Uncode nunca usa:

```css
:is(.fas,.far,.fab,.fa-solid,.fa-regular,.fa-brands,.fa-classic).fa-clock::before { content: "\f017"; }
```

Esa regla tiene más especificidad que la de Uncode (dos clases contra una), así que
gana sin `!important` y sin importar en qué orden se carguen las hojas. Y como exige
el prefijo, los íconos propios del tema (`<i class="fa fa-tiktok">`) ni se enteran.

Hace falta una regla así por cada una de las clases que chocan. Aquí es donde entra
el plugin.

---

## Instalación

### Opción 1 — el plugin (la recomendada)

1. Descarga **`uncode-fix-icons.zip`** de la última versión en
   [Releases](https://github.com/pablotll/uncode-fix-icons/releases).
2. En tu WordPress ve a **Plugins → Añadir nuevo → Subir plugin**, elige el `.zip` e
   instálalo.
3. Actívalo. Listo.

Si tienes caché de página (Varnish, Cloudflare, un plugin de caché), bórrala para
ver el cambio.

### Opción 2 — copiar y pegar, sin instalar nada

Si no puedes instalar plugins, copia el contenido de
[`dist/uncode-fa-collisions.css`](dist/uncode-fa-collisions.css) y pégalo en
**Apariencia → Personalizar → CSS adicional**.

> ⚠️ **No lo pegues en el campo "Custom CSS" de Uncode.** Parece el lugar obvio, pero
> Uncode lo mete *antes* o *después* de su hoja de íconos según si tu sitio tiene CSS
> dinámico generado, así que a unos les funciona y a otros no, sin explicación
> aparente. El CSS adicional del Personalizador es de WordPress, se imprime siempre al
> final y siempre gana.

La diferencia con el plugin es que este bloque está fijo: se calculó con Font Awesome
6.5.1 y Uncode 2.12. Si tu sitio usa otras versiones, puede que algún ícono siga
saliendo mal.

### Opción 3 — child theme

El mismo bloque, al final del `style.css` de tu child theme. Necesitas acceso a los
archivos y se pierde si cambias de child theme.

---

## Cómo funciona el plugin

La parte importante: **no trae una lista fija de las 128 clases**. Lee las hojas de
*tu* sitio y calcula qué choca ahí.

1. **Busca la hoja de Font Awesome** entre los estilos que tiene cargados tu sitio.
   La reconoce por lo que tiene adentro, no por su nombre: cada plugin la llama y la
   guarda distinto, y las rutas cambian entre versiones.
2. **La compara contra `uncode-icons.css`** (la del tema padre, así que funciona
   igual con un child theme) y se queda con las clases que las dos definen con
   caracteres distintos.
3. **Guarda el resultado** y solo lo vuelve a calcular cuando cambia alguna de las
   dos hojas. Si actualizas Uncode o el plugin que trae Font Awesome, se recalcula
   solo.
4. **Si no puede leer alguna de las hojas** —por ejemplo, si Font Awesome viene de un
   CDN que no responde—, usa una tabla de respaldo y te lo dice.
5. **Añade el CSS** a la página. No modifica ningún archivo del tema ni de otro
   plugin. Si lo desactivas, no deja nada.

### La pantalla de estado

En **Ajustes → Uncode Fix Icons** ves lo que está haciendo:

- si está activo y cuántas clases está corrigiendo,
- si las reglas salieron de tus hojas o de la tabla de respaldo,
- qué hoja de Font Awesome encontró,
- el CSS exacto que está aplicando (por si algún día lo quieres pegar a mano),
- y un botón para forzar el recálculo.

Si el tema activo no es Uncode, el plugin no hace nada y te avisa.

### Actualizaciones

El plugin revisa los releases de este repositorio. Cuando salga una versión nueva,
te aparecerá en el escritorio de WordPress como cualquier otra actualización, con su
botón de "Actualizar ahora".

---

## ¿Mi sitio tiene este problema?

Hacen falta tres cosas. Las tres se pueden ver desde fuera, con `curl`:

```bash
# 1 y 2: ¿hay Font Awesome y está la hoja de íconos de Uncode?
curl -s https://TUSITIO/ | grep -o "href=['\"][^'\"]*\(fontawesome\|uncode-icons\)[^'\"]*"

# 3: ¿hay íconos con prefijo explícito de Font Awesome?
curl -s https://TUSITIO/ | grep -o '\(fas\|far\|fab\) fa-[a-z0-9-]*' | sort -u
```

Si el último comando no devuelve nada, hoy no tienes el problema.

Si devuelve clases, **todavía no es seguro**: además tienen que ser de las que
chocan. Crúzalas contra el bloque:

```bash
comm -12 \
  <(curl -s https://TUSITIO/ | grep -o '\(fas\|far\|fab\) fa-[a-z0-9-]*' | awk '{print $2}' | sort -u) \
  <(grep -o '\.fa-[a-z0-9-]*::before' dist/uncode-fa-collisions.css | sed 's/^\.//;s/::before//' | sort -u)
```

Lo que salga ahí es lo que se está viendo mal. Casi siempre son pocas: en el sitio
donde lo encontré, de 21 clases con prefijo solo 3 chocaban. Por eso el problema
puede pasar meses sin que nadie lo note, hasta que un cambio de diseño deja a la
vista justo el ícono equivocado.

---

## Cómo se probó

En un WordPress 7.1.1 con Uncode 2.12.8 y Font Awesome 6.5.1, midiendo en un
navegador el carácter que de verdad se dibuja en pantalla:

| Elemento | Sin el plugin | Con el plugin | |
|---|---|---|---|
| `fas fa-clock` | `\e072` (papel tachado) | `\f017` (reloj) | ✅ corregido |
| `fas fa-sync` | `\e862` | `\f021` | ✅ corregido |
| `fas fa-times` | `\e600` | `\f00d` | ✅ corregido |
| `fab fa-facebook-f` | `\f09a` | `\f39e` | ✅ corregido |
| `fas fa-search` | `\f002` | `\f002` | no chocaba, intacto |
| `fa fa-tiktok` (del tema) | `\e92f` | `\e92f` | intacto |
| `fa fa-clock` (del tema) | `\e072` | `\e072` | intacto |

Además:

- el CSS que calcula el plugin es idéntico al del script de referencia de este repo;
- al cambiar la hoja de Uncode, el plugin se recalculó solo;
- con las hojas en el orden inverso, el plugin no cambia nada, ni para bien ni para
  mal;
- con un tema que no es Uncode, no aplica nada;
- se instala desde el `.zip` sin un solo error ni aviso de PHP.

Puedes repetir todo esto tú: [`tests/`](tests/) levanta un WordPress desechable con
tu copia de Uncode, mide los íconos sin el plugin y con él, y confirma que las reglas
salieron de calcular con las hojas y no de la tabla de respaldo.

El código lo revisaron por separado dos modelos de IA de proveedores distintos, que
intentaron romperlo. Encontraron problemas reales —dos de seguridad incluidos— y
están corregidos.

---

## Limitaciones conocidas

- **Probado con Font Awesome 6.5.1 y Uncode 2.12.** El plugin calcula contra las
  hojas de tu sitio, así que debería funcionar con otras versiones, pero no lo he
  probado.
- **No se ha probado con una mezcla grande de plugins** (WooCommerce, Elementor,
  constructores de páginas). Si en tu sitio no funciona, abre un
  [issue](https://github.com/pablotll/uncode-fix-icons/issues) con lo que dice la
  pantalla de estado.
- **Si Font Awesome escribe una regla con un selector compuesto**
  (`.fas.fa-algo::before`), el plugin la ignora. Font Awesome 6.5.1 no escribe reglas
  así; otras versiones no las he revisado.
- **La pantalla de estado se llena la primera vez que alguien abre el sitio.** El
  cálculo se hace en las páginas públicas, no en el escritorio.

---

## Para desarrolladores

```
plugin/uncode-fix-icons/        el plugin
dist/uncode-fa-collisions.css   el bloque fijo, para copiar y pegar
scripts/generar-colisiones.py   genera el bloque comparando dos hojas
scripts/empaquetar.sh           arma el .zip del release
tests/                          entorno de prueba reproducible (Docker + navegador)
docs/causa-raiz-y-diagnostico.md   el análisis completo
docs/plugin.md                  la arquitectura del plugin y cómo se probó
```

Regenerar el bloque fijo contra otras versiones:

```bash
python3 scripts/generar-colisiones.py ruta/a/all.css ruta/a/uncode-icons.css > dist/uncode-fa-collisions.css
```

Armar el `.zip`:

```bash
./scripts/empaquetar.sh    # deja build/uncode-fix-icons.zip
```

---

## Licencia y créditos

GPL v2 o posterior, como WordPress.

Este proyecto no está afiliado a Undsgn, los creadores de Uncode, ni a Fonticons, los
de Font Awesome. No incluye ningún archivo de ninguno de los dos: solo los números de
los caracteres que hay que corregir.

Si te sirvió, una ⭐ en el repo me ayuda a saber que le está sirviendo a alguien más.
