#!/usr/bin/env python3
"""
Genera el bloque de overrides que resuelve la colision de iconos entre
Font Awesome 5/6 (encolado por un plugin) y uncode-icons.css (tema Uncode).

Las dos hojas definen las mismas clases `.fa-*` con codepoints distintos.
Como Uncode carga despues, gana el `content`; pero la `font-family` la sigue
poniendo FA cuando el elemento lleva prefijo explicito (`.fas`, `.far`, ...),
que Uncode nunca usa. El resultado es la fuente de FA pintando el codepoint
de Uncode: un glifo aleatorio.

Este script compara ambas hojas y emite dos juegos de reglas:

- Lado FA: por cada clase en colision, el codepoint de FA *solo* bajo un
  prefijo explicito FA5/6. (0,2,1) contra el (0,1,1) de Uncode.
- Lado Uncode: el codepoint del tema en `.fa.fa-x` (0,2,1), para cuando FA
  carga DESPUES de Uncode. Con FA >= 6.6 (`.fa::before{content:var(--fa)}`)
  cubre TODAS las clases del tema, no solo las colisiones. Mas una regla que
  deshace el `.fa{font-weight:900}` de FA en los iconos del tema.

Las dos ganan sin `!important` y con cualquier orden de hojas. Es la misma
logica que UNCFI_Collisions del plugin, y debe dar el mismo CSS.

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
# FA >= 6.6 ya no pone `content` por clase: `.fa-x{--fa:"\xxxx"}`.
RE_VAR_FA = re.compile(r'(?:^|;)\s*--fa\s*:\s*["\']\\([0-9a-fA-F]+)["\']', re.I)
# .fa-nombre:before / ::before, sin nada mas pegado al selector.
RE_SELECTOR = re.compile(r"^\.(fa-[a-z0-9-]+)::?before$")
RE_SELECTOR_VAR = re.compile(r"^\.(fa-[a-z0-9-]+)$")


def leer(ruta):
    with open(ruta, encoding="utf-8", errors="replace") as fh:
        return fh.read()


def mapear(css):
    """{clase: codepoint} de `.fa-x::before{content:"\\xxxx"}` y de `.fa-x{--fa:"\\xxxx"}`."""
    css = RE_COMENTARIO.sub("", css)

    mapa = {}
    for selectores, cuerpo in RE_REGLA.findall(css):
        m = RE_CONTENT.search(cuerpo)
        patron = RE_SELECTOR
        if not m:
            m = RE_VAR_FA.search(cuerpo)
            patron = RE_SELECTOR_VAR
        if not m:
            continue
        codepoint = m.group(1).lower()
        for sel in selectores.split(","):
            sm = patron.match(sel.strip())
            if sm:
                mapa[sm.group(1)] = codepoint
    return mapa


def main():
    if len(sys.argv) != 3:
        sys.exit(__doc__.strip())

    css_fa = leer(sys.argv[1])
    fa = mapear(css_fa)
    uncode = mapear(leer(sys.argv[2]))

    colisiones = sorted(
        clase for clase, cp in fa.items()
        if clase in uncode and uncode[clase] != cp
    )

    # FA >= 6.6: su `.fa::before{content:var(--fa)}` pisa todos los iconos del tema.
    propios = sorted(uncode) if "var(--fa)" in css_fa else colisiones

    print("/* {} clases en colision | lado Uncode: {} | FA: {} reglas | Uncode: {} reglas */"
          .format(len(colisiones), len(propios), len(fa), len(uncode)), file=sys.stderr)

    for clase in colisiones:
        print(':is({}).{}::before{{content:"\\{}"}}'
              .format(PREFIJOS, clase, fa[clase]))

    if propios:
        print(".fa:not({}){{font-weight:normal}}".format(PREFIJOS))
    for clase in propios:
        print('.fa.{}::before{{content:"\\{}"}}'.format(clase, uncode[clase]))


if __name__ == "__main__":
    main()
