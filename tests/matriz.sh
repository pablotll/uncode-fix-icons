#!/usr/bin/env bash
# Repite la medicion en todas las combinaciones que han roto iconos en la vida real:
# Font Awesome 6.5.1 y 6.7.2 (>= 6.6 trae `.fa::before{content:var(--fa)}`),
# encolado antes de Uncode (como WPVR) o despues (como GeoDirectory, en 5000),
# y servido desde el sitio o desde use.fontawesome.com.
#
# Necesita el entorno ya levantado por probar.sh. Con el plugin desactivado sirve
# para ver los bugs que arregla.
set -euo pipefail
cd "$(dirname "$0")"
URL="http://localhost:${UFI_PORT:-8931}/"
PLUGINS=/var/www/html/wp-content/plugins

wp() { docker compose exec -T wp wp --allow-root --path=/var/www/html "$@"; }

fallos=0
for ver in 6.5.1 6.7.2; do
	docker compose exec -T wp curl -sf \
		"https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@$ver/css/all.css" \
		-o "$PLUGINS/fa-provider/css/all.css"
	for f in fa-solid-900 fa-regular-400 fa-brands-400; do
		docker compose exec -T wp curl -sf \
			"https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@$ver/webfonts/$f.woff2" \
			-o "$PLUGINS/fa-provider/webfonts/$f.woff2"
	done
	for orden in antes despues; do
		for origen in local remoto; do
			wp config set UFI_FA_VER "$ver" >/dev/null
			wp config set UFI_FA_DESPUES "$([ "$orden" = despues ] && echo true || echo false)" --raw >/dev/null
			wp config set UFI_FA_REMOTO "$([ "$origen" = remoto ] && echo true || echo false)" --raw >/dev/null
			wp eval 'class_exists( "UNCFI_Collisions" ) && UNCFI_Collisions::flush();'
			curl -s -o /dev/null "$URL"   # primera carga: el plugin calcula y guarda

			echo; echo "=== FA $ver · $orden de Uncode · $origen"
			wp eval 'if ( ! class_exists( "UNCFI_Collisions" ) ) { echo "  plugin inactivo\n"; exit( 1 ); }
				$e = UNCFI_Collisions::cacheado();
				printf("  motor: %s | %d reglas | hoja FA: %s\n", $e["source"], $e["count"], $e["fa"]);
				exit( "calculado" === $e["source"] && "fa-provider" === $e["fa"] ? 0 : 1 );' \
				|| { echo "  XX el motor no calculó contra la hoja que se imprime"; fallos=$((fallos+1)); }
			node medir.js "$URL" | sed 's/^/  /' || fallos=$((fallos+1))
		done
	done
done

echo
[ "$fallos" -eq 0 ] && echo "Matriz completa: todo bien" || { echo "Matriz: $fallos combinación(es) con fallas"; exit 1; }
