#!/usr/bin/env python3
"""
Genera el bloque de overrides que resuelve la colision de iconos entre
Font Awesome 5/6 (encolado por un plugin) y uncode-icons.css (tema Uncode).

Las dos hojas definen las mismas clases `.fa-*` con codepoints distintos.
Como Uncode carga despues, gana el `content`; pero la `font-family` la sigue
poniendo FA cuando el elemento lleva prefijo explicito (`.fas`, `.far`, ...),
que Uncode nunca usa. El resultado es la fuente de FA pintando el codepoint
de Uncode: un glifo aleatorio.

Este script compara ambas hojas y emite una regla por cada clase en colision,
restaurando el codepoint de FA *solo* bajo un prefijo explicito FA5/6.
Especificidad 0,2,0 contra el 0,1,1 de Uncode: gana sin `!important` y sin
tocar los iconos propios del tema (`<i class="fa fa-...">`).

Uso:
    python3 generar-colisiones.py FA_ALL.CSS UNCODE_ICONS.CSS > salida.css
"""

import re
import sys

PREFIJOS = ".fas,.far,.fab,.fa-solid,.fa-regular,.fa-brands,.fa-classic"

# Un bloque CSS: selectores { cuerpo }. Ignora at-rules (@font-face, @media).
RE_REGLA = re.compile(r"([^{}]+)\{([^{}]*)\}")
RE_COMENTARIO = re.compile(r"/\*.*?\*/", re.S)
# `re.I` por el nombre de la propiedad: CSS no distingue mayusculas ahi, y sin
# esto una hoja que escriba `Content:` pierde esa regla en silencio.
RE_CONTENT = re.compile(r'content\s*:\s*["\']\\([0-9a-fA-F]+)["\']', re.I)
# .fa-nombre:before / ::before, sin nada mas pegado al selector.
RE_SELECTOR = re.compile(r"^\.(fa-[a-z0-9-]+)::?before$")


def mapear(ruta):
    """Devuelve {clase: codepoint} para las reglas `.fa-x::before{content:"\\xxxx"}`."""
    with open(ruta, encoding="utf-8", errors="replace") as fh:
        css = RE_COMENTARIO.sub("", fh.read())

    mapa = {}
    for selectores, cuerpo in RE_REGLA.findall(css):
        m = RE_CONTENT.search(cuerpo)
        if not m:
            continue
        codepoint = m.group(1).lower()
        for sel in selectores.split(","):
            sm = RE_SELECTOR.match(sel.strip())
            if sm:
                mapa[sm.group(1)] = codepoint
    return mapa


def main():
    if len(sys.argv) != 3:
        sys.exit(__doc__.strip())

    fa = mapear(sys.argv[1])
    uncode = mapear(sys.argv[2])

    colisiones = sorted(
        clase for clase, cp in fa.items()
        if clase in uncode and uncode[clase] != cp
    )

    print("/* {} clases en colision | FA: {} reglas | Uncode: {} reglas */"
          .format(len(colisiones), len(fa), len(uncode)), file=sys.stderr)

    for clase in colisiones:
        print(':is({}).{}::before{{content:"\\{}"}}'
              .format(PREFIJOS, clase, fa[clase]))


if __name__ == "__main__":
    main()
