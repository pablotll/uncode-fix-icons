# El plugin: arquitectura y cómo se probó

`plugin/uncode-fix-icons/` — plugin de WordPress que aplica el fix sin child theme y
sin FTP. Se instala como `.zip` desde Plugins → Añadir nuevo → Subir plugin.

## Por qué un plugin y no otra cosa

| Opción | ¿Sirve? |
|---|---|
| **Plugin** (`.zip` desde el escritorio) | ✅ Solo hace falta ser admin |
| mu-plugin | ❌ Se instala copiando un archivo a `wp-content/mu-plugins/`: necesita FTP, justo lo que queremos evitar |
| Instalador de extras de Uncode (TGMPA) | ❌ Solo instala los plugins que Uncode declara en su propia lista; un tercero no puede entrar |
| CSS adicional del Customizer | ✅ Como alternativa sin instalar nada — es núcleo de WordPress y se imprime al final |
| Campo "Custom CSS" de Uncode | ⚠️ **Inconsistente**, ver abajo |

### El campo Custom CSS de Uncode no siempre gana

Uncode lo inyecta con `wp_add_inline_style`, y el handle al que lo engancha depende
de si el sitio tiene CSS dinámico generado (`core/inc/main.php`):

```php
if ($dynamic_css_exists) {
    wp_add_inline_style('uncode-custom-style', ...);   // después de uncode-icons.css → gana
} else {
    wp_add_inline_style('uncode-style', ...);          // ANTES de uncode-icons.css → pierde
}
```

Por eso no es una receta que se pueda dar a ciegas: al mismo CSS le va distinto según
la configuración del sitio. El Customizer sí es consistente
(`add_action('wp_head','wp_custom_css_cb',101)`, núcleo de WordPress, siempre después
de las hojas encoladas).

## Cómo funciona

1. **Detecta Font Awesome por contenido, no por nombre.** Recorre los estilos
   registrados y cuenta reglas `.fa-*::before{content:…}` en cada hoja local; gana la
   que más tenga. Buscar `fontawesome` en la ruta parece obvio y falla callado: hay
   plugins que la sirven desde `assets/css/all.min.css`, y los paths se mueven entre
   versiones (wpvr 9.1.3 movió el suyo a `legacy/`). Este defecto **apareció en las
   pruebas**: la primera versión buscaba por nombre y caía al respaldo sin avisar.
2. **Compara las dos hojas** y se queda con las clases que ambas definen con
   codepoints distintos.
3. **Cachea en una opción**, con una llave hecha de tamaño y fecha de los dos
   archivos. No se usa el `?ver=` de la URL: ahí va la versión del *plugin* que
   encola Font Awesome, no la de Font Awesome — wpvr 9.1.2 y 9.1.3 traen ambos FA
   6.5.1. **Excepción:** una hoja servida desde un CDN no tiene tamaño ni fecha que
   mirar, así que a esa llave se le añade un cubo semanal; si no, la caché no se
   invalidaría nunca aunque el CDN empezara a servir otra versión.
4. **Si no puede leer alguna de las dos hojas** (CDN inaccesible, permisos), usa la
   tabla de respaldo de `data/fallback-collisions.php` y **lo dice** en la pantalla de
   estado, en vez de fingir que calculó.
5. **Encola el CSS** en `wp_enqueue_scripts` con prioridad 100. El orden no es
   crítico: la especificidad (0,2,1 contra 0,1,1) hace que gane aunque se imprima
   antes.

La pantalla de estado **solo lee** lo que ya se calculó en el front. Una versión
anterior disparaba ahí `do_action('wp_enqueue_scripts')` para poder encontrar la hoja
de Font Awesome, y eso ejecuta los callbacks de front de todos los demás plugins
dentro de `wp-admin`, en un contexto que no esperan. No vale el riesgo por una
pantalla informativa; ahora, si no hay nada calculado, dice que abras el sitio una vez.

### Entrada no confiable

Las URLs que el plugin recorre las escriben **otros** plugins del sitio, así que se
tratan como entrada no confiable:

- `url_a_ruta()` normaliza con `realpath()` y confirma que la ruta siga dentro del
  directorio permitido. Sin eso, una URL con `../` pasa el filtro por prefijo:
  `…/wp-content/../../../../etc/passwd` empieza por `content_url()`.
- `leer_remoto()` usa `wp_safe_remote_get()`, que activa `reject_unsafe_urls` y
  bloquea direcciones internas.

Las dos las encontró una revisión adversarial del código, la segunda reproducida en
vivo.

## Cómo se probó

WordPress 7.1.1 en Docker, Uncode 2.12.8 real, y un plugin stub que encola Font
Awesome 6.5.1 igual que lo haría WPVR. El orden de hojas del entorno reproduce el de
producción (FA primero, `uncode-icons.css` después).

Medición del codepoint renderizado con un navegador headless
(`getComputedStyle(el,'::before').content`), no inspección del CSS:

| Elemento | Sin el plugin | Con el plugin | |
|---|---|---|---|
| `fas fa-clock` | `\e072` (papel tachado) | `\f017` (reloj) | corregido |
| `fas fa-sync` | `\e862` | `\f021` | corregido |
| `fas fa-times` | `\e600` | `\f00d` | corregido |
| `fab fa-facebook-f` | `\f09a` (fa-facebook) | `\f39e` | corregido |
| `fas fa-search` | `\f002` | `\f002` | sin colisión, intacto |
| `fa fa-tiktok` | `\e92f` uncodeicon | `\e92f` uncodeicon | icono del tema, intacto |
| `fa fa-clock` | `\e072` uncodeicon | `\e072` uncodeicon | icono del tema, intacto |

Además:

- **El CSS que calcula el plugin coincide byte a byte** con la salida de
  `scripts/generar-colisiones.py` sobre esas mismas dos hojas.
- **Recalcula solo:** al modificar `uncode-icons.css` para que `fa-clock` dejara de
  colisionar, el plugin pasó de 128 a 127 reglas y sacó esa clase, sin intervención.
- **Orden invertido** (Font Awesome cargando *después* de Uncode): las mediciones con
  y sin el plugin son idénticas. Es exactamente neutral ahí — no ayuda, pero tampoco
  estorba. De paso confirma por qué reordenar las hojas no es la solución: en ese
  escenario se rompen los iconos propios del tema.
- **Tema que no es Uncode:** cero reglas emitidas, y un aviso en el escritorio.
- **Instalación desde `.zip`** vía la pantalla de subir plugin, con `WP_DEBUG` y
  `WP_DEBUG_LOG` activos: `debug.log` vacío, sin errores ni warnings.
- **Actualizador contra un repo sin releases:** devuelve `false` y no ofrece nada, en
  vez de romper.
- **El actualizador solo usa el `.zip` adjunto al release** (desde 1.0.1). La 1.0.0
  recurría al zipball automático de GitHub si un release no traía `.zip`, pero el
  zipball es la raíz del repo, donde el plugin vive en `plugin/uncode-fix-icons/`:
  se habría instalado sin el encabezado donde WordPress lo busca y habría quedado
  desactivado. Ahora, sin `.zip` adjunto, no se ofrece actualización.
  `tests/prueba-updater.php` lo prueba por los dos lados. Lo encontró la sesión que
  mantiene las actualizaciones de los sitios, auditando el canal de updates.
- **Actualizador contra el release real `v1.0.0`:** una copia marcada como 0.9.9 vio
  la 1.0.0 disponible, se actualizó sola descargando el `.zip` del release, quedó en
  la carpeta `uncode-fix-icons` y activa, y siguió corrigiendo los 7 íconos.

## Limitaciones conocidas

- **Selectores compuestos y `Content:` en mayúsculas.** El parser tomaba solo
  `content` en minúsculas; ya es insensible a mayúsculas, en el PHP y en el generador
  Python. Lo que sigue sin cubrirse es un selector compuesto
  (`.fas.fa-algo::before`): esa regla se ignora en silencio. Se verificó que Font
  Awesome 6.5.1 no emite esa forma (otras versiones no se revisaron), pero si alguna build lo hiciera, ese icono se
  quedaría sin corregir sin que nada lo avise.
- **La pantalla de estado depende de que alguien haya abierto el sitio.** El cálculo
  vive en el front; en wp-admin solo se lee.
- **Una llamada desde WP-CLI** (`wp eval 'UNCFI_Collisions::get();'`) calcula sin las
  hojas encoladas del front, así que no encuentra Font Awesome y guarda un resultado
  de respaldo. No contamina el sitio: la llave de ese cálculo es distinta, y la
  primera carga normal del front recalcula con las hojas reales. Probado.

## Lo que falta probar

- **Mezclas realistas de plugins.** El entorno de prueba tiene Uncode y un stub. No
  está probado contra WooCommerce, Elementor o constructores de página, que es el
  ecosistema donde esto se va a instalar.
- **`update_option()` fallando** (permisos de BD, object cache raro). Hay un memo por
  request para que en ese caso no se recalcule varias veces dentro del mismo request,
  pero no se ha reproducido el fallo para confirmarlo. El memo es una propiedad de la
  clase que `flush()` vacía: la primera versión lo tenía como variable `static` dentro
  de `get()`, y un `get()` → `flush()` → `get()` en el mismo request devolvía el
  estado viejo con la opción ya borrada. Lo encontraron los dos verificadores por su
  cuenta; está corregido y probado.
