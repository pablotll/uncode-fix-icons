#!/usr/bin/env bash
# Arma build/uncode-fix-icons.zip, el archivo que se adjunta a cada release de GitHub.
# Lo usa tambien el workflow de release (.github/workflows/release.yml).
#
#   ./scripts/empaquetar.sh            version del encabezado del repo
#   ./scripts/empaquetar.sh 1.2.0-rc.1 inyecta esa version en el encabezado y en UNCFI_VERSION
#
# El .zip trae la carpeta uncode-fix-icons/ en la raiz y el vendor/ de produccion
# (plugin-update-checker). El nombre del .zip no se cambia: el actualizador nativo de
# la 1.0.x solo acepta uncode-fix-icons.zip.
set -euo pipefail
cd "$(dirname "$0")/.."
RAIZ="$PWD"
SLUG=uncode-fix-icons
VERSION="${1:-}"

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
WORKDIR="$TMP/$SLUG"
mkdir -p "$WORKDIR"

# vendor/ se instala limpio en la copia, no se toma el del repo (puede traer dev).
rsync -a "plugin/$SLUG/" "$WORKDIR/" --exclude vendor --exclude '.DS_Store'
composer install --working-dir="$WORKDIR" --no-dev --optimize-autoloader --no-interaction --prefer-dist --quiet
rm -f "$WORKDIR/composer.json" "$WORKDIR/composer.lock"

# La version la manda el TAG. Un tag con sufijo (v1.2.0-rc.1) deja el sufijo tambien
# en el encabezado, para que version_compare lo ordene debajo del estable y no
# "queme" el numero limpio.
if [ -n "$VERSION" ]; then
	MAIN="$WORKDIR/$SLUG.php"
	sed -i -E "s/^( \* Version:[[:space:]]*).*/\1${VERSION}/" "$MAIN"
	sed -i -E "s/(define\( 'UNCFI_VERSION', ')[^']*(' \);)/\1${VERSION}\2/" "$MAIN"
	grep -q "^ \* Version: *${VERSION}\$" "$MAIN" && grep -q "'UNCFI_VERSION', '${VERSION}'" "$MAIN" \
		|| { echo "no se pudo inyectar la version ${VERSION}" >&2; exit 1; }
fi

mkdir -p build
rm -f "build/$SLUG.zip"
(cd "$TMP" && zip -qr "$RAIZ/build/$SLUG.zip" "$SLUG")
echo "build/$SLUG.zip ($(du -h "build/$SLUG.zip" | cut -f1))"
