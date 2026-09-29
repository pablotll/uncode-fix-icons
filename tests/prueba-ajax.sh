#!/usr/bin/env bash
# Regresion de la 1.1.0: con el plugin activo, admin-ajax.php debe contestar como
# siempre (400 sin accion o con una accion que nadie registro) y NUNCA 500.
#
# La 1.1.0 llamaba current_user_can() al incluir el plugin, antes de que WordPress
# cargara pluggable.php: cada peticion AJAX terminaba en error fatal. wp-admin y el
# front seguian bien, por eso no se vio en las demas pruebas.
#
# Uso (con el entorno de probar.sh levantado):  ./tests/prueba-ajax.sh
# Lo corre tambien probar.sh despues de instalar el plugin.
set -euo pipefail

cd "$(dirname "$0")"
URL="http://localhost:${UFI_PORT:-8931}"
AJAX="$URL/wp-admin/admin-ajax.php"
JAR="$(mktemp)"
trap 'rm -f "$JAR"' EXIT
fallos=0

# Una peticion: imprime el codigo HTTP y falla si no es el esperado.
revisa() { # etiqueta esperado [args de curl...]
	local etiqueta="$1" esperado="$2"; shift 2
	local codigo
	codigo="$(curl -s -o /dev/null -w '%{http_code}' "$@")"
	if [ "$codigo" = "$esperado" ]; then
		echo "  OK $etiqueta → $codigo"
	else
		echo "  XX $etiqueta → $codigo (esperado $esperado)"
		fallos=$((fallos + 1))
	fi
}

echo "→ admin-ajax.php como anónimo"
revisa "sin acción (GET)"          400 "$AJAX"
revisa "acción cualquiera (POST)"  400 -d 'action=uncfi_no_existe' "$AJAX"

echo "→ admin-ajax.php como admin"
curl -s -o /dev/null -c "$JAR" -b 'wordpress_test_cookie=WP%20Cookie%20check' \
	-d 'log=admin&pwd=admin&testcookie=1&redirect_to=/wp-admin/' "$URL/wp-login.php"
revisa "sesión iniciada (wp-admin)" 200 -b "$JAR" "$URL/wp-admin/"
revisa "sin acción (GET)"          400 -b "$JAR" "$AJAX"
revisa "acción cualquiera (POST)"  400 -b "$JAR" -d 'action=uncfi_no_existe' "$AJAX"

if [ "$fallos" -gt 0 ]; then
	echo "  $fallos fallo(s). Si alguno es 500, revisa el log de PHP: docker compose logs wp | tail" >&2
	exit 1
fi
