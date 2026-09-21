=== Uncode Fix Icons ===
Contributors: pablotll
Tags: uncode, font awesome, icons, fontawesome, geodirectory
Requires at least: 5.6
Tested up to: 7.1
Requires PHP: 7.0
Stable tag: 1.0.1
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

= 1.0.1 =
* El actualizador ya no recurre al zipball automatico de GitHub cuando un release no trae el .zip adjunto. Ese respaldo instalaba el plugin en una carpeta equivocada y lo dejaba desactivado; ahora, sin .zip adjunto, simplemente no se ofrece la actualizacion.

= 1.0.0 =
* Primera version.
