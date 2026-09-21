#!/usr/bin/env bash
# Levanta un WordPress con Uncode + un plugin que carga Font Awesome, instala
# Uncode Fix Icons desde su .zip, y mide los iconos en un navegador.
#
# Uso:
#   UNCODE=/ruta/a/uncode.zip ./tests/probar.sh      # o .tar.gz
#
# Uncode es un tema comercial: este repo no lo incluye. Usa tu propia copia (la
# descargas de ThemeForest). Font Awesome Free 6.5.1 se descarga al momento.
#
# Necesita: docker con compose, curl, unzip, node y playwright
#   (npm i -g playwright && npx playwright install chromium)
set -euo pipefail

cd "$(dirname "$0")"
RAIZ="$(cd .. && pwd)"
PUERTO="${UFI_PORT:-8931}"
URL="http://localhost:$PUERTO"
FA="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1"

if [ -z "${UNCODE:-}" ] || [ ! -f "$UNCODE" ]; then
	echo "Falta la copia de Uncode. Uso: UNCODE=/ruta/a/uncode.zip $0" >&2
	exit 1
fi

wp() { docker compose exec -T wp wp --allow-root --path=/var/www/html "$@"; }

echo "→ Levantando WordPress en $URL"
docker compose up -d
# Apache contesta antes de que la base esté lista, y un `ping` tampoco basta: en
# el primer arranque MariaDB levanta un servidor temporal (sin red y sin el
# usuario de WordPress) mientras se inicializa, y ese servidor ya contesta al
# ping. Lo único que prueba que está lista es entrar como WordPress, por red.
until docker compose exec -T db mariadb -h 127.0.0.1 -uwp -pwp wp -e 'SELECT 1' >/dev/null 2>&1; do sleep 1; done
until [ "$(curl -s -o /dev/null -w '%{http_code}' "$URL/" || true)" != "000" ]; do sleep 1; done
docker compose exec -T wp bash -c 'command -v wp >/dev/null || {
	curl -sL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp
	chmod +x /usr/local/bin/wp; }'
wp core is-installed 2>/dev/null || wp core install --url="$URL" --title=UFI \
	--admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email

echo "→ Instalando Uncode"
docker compose cp "$UNCODE" wp:/tmp/uncode.pkg
docker compose exec -T wp bash -c 'cd /var/www/html/wp-content/themes &&
	(unzip -qo /tmp/uncode.pkg 2>/dev/null || tar xzf /tmp/uncode.pkg) && chown -R www-data: uncode'
wp theme activate uncode

echo "→ Instalando el proveedor de Font Awesome 6.5.1 (descarga oficial)"
TMP="$(mktemp -d)"
trap 'rm -r "$TMP"' EXIT
mkdir -p "$TMP/fa-provider/css" "$TMP/fa-provider/webfonts"
cp fa-provider/fa-provider.php "$TMP/fa-provider/"
curl -sf "$FA/css/all.css" -o "$TMP/fa-provider/css/all.css"
for f in fa-solid-900 fa-regular-400 fa-brands-400; do
	curl -sf "$FA/webfonts/$f.woff2" -o "$TMP/fa-provider/webfonts/$f.woff2"
done
docker compose cp "$TMP/fa-provider" wp:/var/www/html/wp-content/plugins/
docker compose exec -T wp chown -R www-data: /var/www/html/wp-content/plugins/fa-provider
wp plugin activate fa-provider

echo "→ Sin el plugin (aquí debe fallar: es el bug)"
wp plugin deactivate uncode-fix-icons 2>/dev/null || true
node medir.js "$URL/" || true

echo "→ Instalando Uncode Fix Icons desde el .zip"
"$RAIZ/scripts/empaquetar.sh" >/dev/null
docker compose cp "$RAIZ/build/uncode-fix-icons.zip" wp:/tmp/ufi.zip
wp plugin install /tmp/ufi.zip --activate --force
curl -s -o /dev/null "$URL/"   # primera carga: el plugin calcula y guarda

echo "→ Con el plugin"
node medir.js "$URL/"
wp option get uncfi_cache --format=json | node -e '
	let d=""; process.stdin.on("data",c=>d+=c).on("end",()=>{
	const x=JSON.parse(d).data; console.log(`  motor: ${x.source} | ${x.count} reglas | hoja FA: ${x.fa}`);
	if (x.source !== "calculado") { console.log("  se usó la tabla de respaldo: el motor no encontró las hojas"); process.exit(1); } });'

echo
echo "Listo. Para apagarlo:  cd tests && docker compose down --volumes"
