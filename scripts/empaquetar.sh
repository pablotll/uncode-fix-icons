#!/usr/bin/env bash
# Arma build/uncode-fix-icons.zip, el archivo que se adjunta a cada release de GitHub.
# El actualizador del plugin prefiere un .zip adjunto: trae la carpeta con el nombre
# correcto, a diferencia del zipball automatico de GitHub.
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p build
rm -f build/uncode-fix-icons.zip
(cd plugin && zip -qr ../build/uncode-fix-icons.zip uncode-fix-icons -x '*.DS_Store')
echo "build/uncode-fix-icons.zip ($(du -h build/uncode-fix-icons.zip | cut -f1))"
