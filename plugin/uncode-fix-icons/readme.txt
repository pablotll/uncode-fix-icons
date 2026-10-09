=== Uncode Fix Icons ===
Contributors: pablotll
Tags: uncode, font awesome, icons, fontawesome, geodirectory
Requires at least: 5.6
Tested up to: 7.1
Requires PHP: 7.0
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Arregla los iconos que salen equivocados cuando el tema Uncode convive con un plugin que carga Font Awesome.

== Description ==

Si tu sitio usa el tema Uncode y algun icono se ve como un glifo que no tiene nada
que ver — un reloj que sale como un rollo de papel higienico tachado, una X como un
cuadro vacio — aunque el HTML sea correcto, este plugin lo arregla.

**La causa.** Uncode redefine unas 1570 clases `.fa-*` con los codepoints de su
fuente propia, pero solo fuerza la familia tipografica en `.fa`, no en `.fas`,
`.far` ni `.fab`. Cuando otro plugin carga Font Awesome, un `<i class="fas fa-clock">`
acaba pintandose con la FUENTE de Font Awesome y el CONTENT de Uncode. Salen dos
iconos distintos mezclados, y el resultado es un glifo al azar.

**La solucion.** El plugin compara las dos hojas de tu sitio, detecta que clases
chocan, y restaura el codepoint de Font Awesome solo cuando el elemento lleva un
prefijo explicito — prefijos que Uncode nunca usa. Los iconos propios del tema no
se tocan.

No necesitas child theme ni acceso FTP. No modifica ningun archivo del tema ni de
otro plugin: solo añade CSS. Al desactivarlo no queda nada.

**Se adapta a tu sitio.** En vez de traer una lista fija, lee las hojas reales que
tienes instaladas y calcula las colisiones. Si actualizas Uncode o el plugin que
trae Font Awesome, lo recalcula solo.

== Frequently Asked Questions ==

= No uso Uncode, ¿me sirve? =
No. El plugin se desactiva solo si el tema activo no es Uncode.

= ¿Funciona con un child theme? =
Si. Busca la hoja de iconos en el tema padre.

= ¿Que plugin causa el problema? =
Cualquiera que cargue Font Awesome: WPVR, Elementor, constructores de formularios,
plugins de redes sociales. El que se ve mal suele ser otro — GeoDirectory y el resto
de AyeCode son el caso mas comun — pero el conflicto de fondo es entre Uncode y
Font Awesome, no con ningun plugin en particular.

= ¿Y si desactivo el plugin que trae Font Awesome? =
No lo arregla: los iconos que dependian de esa fuente se quedan sin ella. Cambias un
icono equivocado por un icono roto.

== Changelog ==

= 1.2.1 =
* Busca la hoja de Font Awesome solo entre las que de verdad se imprimen (encoladas y sus dependencias). La 1.2.0 podia elegir un Font Awesome 4 que otro plugin registra pero no carga, y calcular contra una hoja que el navegador nunca ve.
* Una hoja de Font Awesome servida desde un CDN ya compite igual que una local: se cuentan sus reglas por HTTP (con cache de una semana).

= 1.2.0 =
* Ahora tambien protege los iconos propios del tema (`<i class="fa fa-tiktok">`). Si un plugin encola Font Awesome DESPUES de Uncode (GeoDirectory y los demas de AyeCode lo hacen), le ganaba el codepoint de Font Awesome y el icono del tema salia como otro glifo.
* Soporta Font Awesome 6.6 y posteriores. Su regla `.fa::before{content:var(--fa)}` pisaba todos los iconos del tema, y los que Font Awesome no conoce (como `fa-search3`) desaparecian. El plugin lo detecta y cubre todas las clases de Uncode.
* Lee el nuevo formato de Font Awesome 6.6+ (`.fa-x{--fa:"..."}`); antes no encontraba ninguna regla en esas hojas.
* Calcula al final de `wp_enqueue_scripts`. Con la prioridad 100 de antes no veia la hoja de Font Awesome que AyeCode registra en 5000, y usaba la tabla de respaldo.
* Deshace la negrita sintetica (`font-weight:900` de Font Awesome) en los iconos del tema.

= 1.1.1 =
* Corrige un error fatal de la 1.1.0: cualquier peticion a admin-ajax.php (busquedas, formularios, traducciones, optimizadores de imagen...) respondia 500. El actualizador se enganchaba antes de que WordPress cargara las funciones de usuarios; ahora arranca en plugins_loaded. Si tienes la 1.1.0, actualiza desde Escritorio > Actualizaciones.

= 1.1.0 =
* El actualizador ahora usa Plugin Update Checker. Sigue instalando solo el .zip adjunto al release, nunca el zipball del codigo fuente.
* Las versiones se publican con un tag de git: el workflow de GitHub arma el .zip y crea el release. Los tags con sufijo (v1.2.0-rc.1) salen como prerelease y no llegan a los sitios.

= 1.0.1 =
* El actualizador ya no recurre al zipball automatico de GitHub cuando un release no trae el .zip adjunto. Ese respaldo instalaba el plugin en una carpeta equivocada y lo dejaba desactivado; ahora, sin .zip adjunto, simplemente no se ofrece la actualizacion.

= 1.0.0 =
* Primera version.
