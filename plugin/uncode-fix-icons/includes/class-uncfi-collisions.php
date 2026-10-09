<?php
/**
 * Calcula el bloque de overrides comparando las dos hojas reales del sitio.
 *
 * @package UncodeFixIcons
 */

defined( 'ABSPATH' ) || exit;

/**
 * Compara `uncode-icons.css` contra la hoja de Font Awesome que tenga encolada
 * el sitio, y genera una regla por cada clase `.fa-*` que las dos definan con
 * codepoints distintos.
 */
class UNCFI_Collisions {

	/** Opcion donde se cachea el resultado. */
	const OPTION = 'uncfi_cache';

	/** Prefijos que Font Awesome usa y Uncode nunca. */
	const PREFIJOS = '.fas,.far,.fab,.fa-solid,.fa-regular,.fa-brands,.fa-classic';

	/**
	 * Memo por request.
	 *
	 * Es propiedad de la clase y no una variable `static` dentro de `get()`
	 * para que `flush()` la pueda vaciar: con la variable local, un
	 * `get()` → `flush()` → `get()` en el mismo request devolvia el estado
	 * viejo mientras la opcion ya estaba borrada.
	 *
	 * @var array|null
	 */
	private static $memo = null;

	/**
	 * Devuelve el estado completo: css, numero de reglas y de donde salio.
	 *
	 * Se cachea en una opcion y solo se recalcula cuando cambia alguna de las
	 * dos hojas. La llave NO usa el `?ver=` de la URL: ese parametro lleva la
	 * version del *plugin* que encola Font Awesome, no la de Font Awesome —
	 * wpvr 9.1.2 y 9.1.3 traen los dos FA 6.5.1. Usamos tamano y fecha del
	 * archivo, que si cambian cuando cambia el contenido.
	 *
	 * @return array{css:string,count:int,source:string,fa:string,uncode:string}
	 */
	public static function get() {
		// Si `update_option()` falla (permisos de BD, un object cache raro),
		// sin esto se recalcularia en cada llamada dentro del mismo request.
		if ( null !== self::$memo ) {
			return self::$memo;
		}

		$uncode = self::localizar_uncode();
		$fa     = self::localizar_fontawesome();
		$llave  = self::llave( $uncode, $fa );

		$cache = get_option( self::OPTION );
		if ( is_array( $cache ) && isset( $cache['key'] ) && $cache['key'] === $llave ) {
			self::$memo = $cache['data'];
			return self::$memo;
		}

		self::$memo = self::calcular( $uncode, $fa );
		update_option( self::OPTION, array( 'key' => $llave, 'data' => self::$memo ), false );

		return self::$memo;
	}

	/** Lo que haya en cache, sin calcular nada. Lo usa la pantalla de estado. */
	public static function cacheado() {
		$cache = get_option( self::OPTION );
		return ( is_array( $cache ) && isset( $cache['data'] ) ) ? $cache['data'] : false;
	}

	/** Borra la cache (la opcion y el memo) para forzar un recalculo. */
	public static function flush() {
		self::$memo = null;
		delete_option( self::OPTION );
	}

	/**
	 * Ruta de `uncode-icons.css` en el tema padre.
	 *
	 * Se usa `get_template_directory()` a proposito: desde un child theme la
	 * hoja sigue viviendo en el padre.
	 *
	 * @return string|false
	 */
	public static function localizar_uncode() {
		$ruta = get_template_directory() . '/library/css/uncode-icons.css';
		return is_readable( $ruta ) ? $ruta : false;
	}

	/**
	 * Busca la hoja de Font Awesome entre los estilos que se van a imprimir.
	 *
	 * Se identifica por CONTENIDO, no por nombre. Buscar `fontawesome` en la
	 * ruta parece obvio y falla callado: hay plugins que la sirven desde
	 * `assets/css/all.min.css` o con handles como `elementor-icons-fa-solid`, y
	 * ademas los paths se mueven entre versiones — wpvr 9.1.3 movio el suyo a
	 * `legacy/`. Lo unico estable es lo que la hoja trae dentro.
	 *
	 * Solo cuentan las hojas ENCOLADAS (y sus dependencias). Hasta la 1.2.0 se
	 * recorrian todas las registradas, y un Font Awesome 4 local que un plugin
	 * registra pero no imprime le ganaba al FA 6 remoto que si estaba en la
	 * pagina: se calculaba contra una hoja que el navegador nunca ve.
	 *
	 * Gana la que defina mas clases `.fa-*`, por si el sitio tiene varias. Las
	 * remotas compiten igual que las locales: se cuentan por HTTP, con cache.
	 *
	 * @return array{handle:string,src:string,path:string|false}|false
	 */
	public static function localizar_fontawesome() {
		$styles = wp_styles();
		if ( ! $styles instanceof WP_Styles ) {
			return false;
		}

		$mejor      = false;
		$reglas     = 0;
		$por_nombre = false;

		foreach ( self::encoladas( $styles ) as $handle ) {
			$style = $styles->registered[ $handle ];
			if ( ! self::candidata( $handle, $style ) ) {
				continue;
			}

			$src  = (string) $style->src;
			$ruta = self::url_a_ruta( $src );
			$n    = $ruta ? self::contar_reglas_fa( $ruta ) : self::contar_reglas_remoto( $src );

			if ( $n > $reglas ) {
				$reglas = $n;
				$mejor  = array( 'handle' => $handle, 'src' => $src, 'path' => $ruta );
			} elseif ( ! $ruta && ! $n && ! $por_nombre && preg_match( '#font-?awesome#i', $handle . ' ' . $src ) ) {
				// Remota que no se pudo leer: solo queda fiarse del nombre, como
				// ultimo recurso. `calcular()` volvera a intentarlo por HTTP.
				$por_nombre = array( 'handle' => $handle, 'src' => $src, 'path' => false );
			}
		}

		return $mejor ? $mejor : $por_nombre;
	}

	/**
	 * Handles que se van a imprimir: la cola y, recursivamente, sus dependencias.
	 *
	 * @param WP_Styles $styles Registro de estilos.
	 * @return string[]
	 */
	private static function encoladas( $styles ) {
		$pendientes = (array) $styles->queue;
		$vistas     = array();

		while ( $pendientes ) {
			$handle = array_pop( $pendientes );
			if ( isset( $vistas[ $handle ] ) || ! isset( $styles->registered[ $handle ] ) ) {
				continue;
			}
			$vistas[ $handle ] = true;
			foreach ( (array) $styles->registered[ $handle ]->deps as $dep ) {
				$pendientes[] = $dep;
			}
		}

		return array_keys( $vistas );
	}

	/**
	 * Descarta lo que no puede ser la hoja de codepoints de Font Awesome.
	 *
	 * @param string $handle Handle registrado.
	 * @param object $style  Objeto de WP_Styles.
	 * @return bool
	 */
	private static function candidata( $handle, $style ) {
		if ( empty( $style->src ) || ! is_string( $style->src ) ) {
			return false;
		}

		// Lo nuestro y la hoja de Uncode, que es justo la otra mitad del conflicto.
		if ( 0 === strpos( $handle, 'uncfi' ) || false !== strpos( $handle, 'uncode-icons' ) ) {
			return false;
		}

		// Hojas de Font Awesome que NO traen el mapa de codepoints.
		return ! preg_match( '#(v4-shims|v4-font-face|v5-font-face|icons-fix|svg-with-js)#i', $style->src );
	}

	/**
	 * Cuenta las reglas de codepoint `.fa-*` de un archivo.
	 *
	 * Lee solo los primeros 256 KB: de sobra para el mapa de Font Awesome, y
	 * evita cargar en memoria cualquier hoja grande que ande por ahi.
	 *
	 * @param string $ruta Ruta absoluta.
	 * @return int
	 */
	private static function contar_reglas_fa( $ruta ) {
		$tam = @filesize( $ruta ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! $tam || $tam > 2 * MB_IN_BYTES ) {
			return 0;
		}

		$css = @file_get_contents( $ruta, false, null, 0, 256 * KB_IN_BYTES ); // phpcs:ignore
		return is_string( $css ) ? self::contar_en( $css ) : 0;
	}

	/**
	 * Lo mismo para una hoja en un CDN u otro host.
	 *
	 * `localizar_fontawesome()` corre en cada pagina, asi que el conteo se
	 * guarda en un transient por URL: una semana si se pudo leer, una hora si
	 * no, para no pedirla en cada visita mientras el CDN falla.
	 *
	 * @param string $url URL de la hoja.
	 * @return int
	 */
	private static function contar_reglas_remoto( $url ) {
		$llave = 'uncfi_remoto_' . md5( $url );
		$n     = get_transient( $llave );

		if ( false === $n ) {
			$css = self::leer_remoto( $url );
			$n   = self::contar_en( substr( $css, 0, 256 * KB_IN_BYTES ) );
			set_transient( $llave, $n, $n ? WEEK_IN_SECONDS : HOUR_IN_SECONDS );
		}

		return (int) $n;
	}

	/**
	 * Cuenta las reglas de codepoint `.fa-*` de una hoja ya leida.
	 *
	 * @param string $css Contenido de la hoja.
	 * @return int
	 */
	private static function contar_en( $css ) {
		if ( false === stripos( $css, '.fa-' ) ) {
			return 0;
		}

		// FA <= 6.5 trae `.fa-x::before{content:"\xxxx"}`; FA >= 6.6, `.fa-x{--fa:"\xxxx"}`.
		return (int) preg_match_all( '#\.fa-[a-z0-9-]+(?:::?before)?\s*\{[^}]*(?:content|--fa\s*:)#i', $css );
	}

	/**
	 * Traduce una URL del propio sitio a una ruta de disco.
	 *
	 * Devuelve false para un CDN o cualquier host ajeno, que es justo el caso
	 * en que hay que caer al respaldo o pedirla por HTTP.
	 *
	 * @param string $url URL del recurso.
	 * @return string|false
	 */
	public static function url_a_ruta( $url ) {
		if ( 0 === strpos( $url, '//' ) ) {
			$url = ( is_ssl() ? 'https:' : 'http:' ) . $url;
		}
		$url = strtok( $url, '?' );

		$mapas = array(
			content_url()  => WP_CONTENT_DIR,
			includes_url() => ABSPATH . WPINC,
			site_url( '/' ) => ABSPATH,
		);

		foreach ( $mapas as $base_url => $base_dir ) {
			$base_url = rtrim( $base_url, '/' );
			if ( '' !== $base_url && 0 === strpos( $url, $base_url ) ) {
				$ruta = self::dentro_de( $base_dir, $base_dir . substr( $url, strlen( $base_url ) ) );
				if ( $ruta ) {
					return $ruta;
				}
			}
		}

		// Ruta relativa al propio WordPress.
		if ( 0 === strpos( $url, '/' ) ) {
			return self::dentro_de( ABSPATH, untrailingslashit( ABSPATH ) . $url );
		}

		return false;
	}

	/**
	 * Resuelve una ruta y confirma que siga dentro del directorio permitido.
	 *
	 * Sin esto, una URL con `../` pasa el filtro por prefijo y termina leyendo
	 * cualquier archivo del servidor: `…/wp-content/../../../../etc/passwd`
	 * empieza por `content_url()` y el prefijo solo no lo detecta. Las URLs que
	 * recorremos las escriben otros plugins, asi que son entrada no confiable.
	 *
	 * @param string $base Directorio permitido.
	 * @param string $ruta Ruta candidata, todavia sin normalizar.
	 * @return string|false
	 */
	private static function dentro_de( $base, $ruta ) {
		$real_base = realpath( $base );
		$real_ruta = realpath( $ruta );

		if ( ! $real_base || ! $real_ruta || ! is_file( $real_ruta ) ) {
			return false;
		}

		$real_base = rtrim( $real_base, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;

		if ( 0 !== strpos( $real_ruta, $real_base ) ) {
			return false;
		}

		return is_readable( $real_ruta ) ? $real_ruta : false;
	}

	/**
	 * Llave de cache: cambia si cambia el contenido de cualquiera de las hojas.
	 *
	 * @param string|false $uncode Ruta de uncode-icons.css.
	 * @param array|false  $fa     Datos de la hoja de Font Awesome.
	 * @return string
	 */
	private static function llave( $uncode, $fa ) {
		$partes = array( UNCFI_VERSION );

		foreach ( array( $uncode, isset( $fa['path'] ) ? $fa['path'] : false ) as $ruta ) {
			$partes[] = $ruta && is_readable( $ruta )
				? $ruta . ':' . filesize( $ruta ) . ':' . filemtime( $ruta )
				: 'n/a';
		}

		$partes[] = isset( $fa['src'] ) ? $fa['src'] : 'n/a';

		// Una hoja remota (CDN) no tiene tamano ni fecha que mirar, asi que la
		// llave nunca cambiaria y la cache no se invalidaria jamas aunque el CDN
		// sirviera otra version. Se le anade un cubo semanal para que al menos
		// se revise de vez en cuando.
		if ( $fa && empty( $fa['path'] ) ) {
			$partes[] = 'remoto:' . (int) floor( time() / WEEK_IN_SECONDS );
		}

		return md5( implode( '|', $partes ) );
	}

	/**
	 * Hace el calculo real, o cae al respaldo si no puede leer las hojas.
	 *
	 * @param string|false $uncode Ruta de uncode-icons.css.
	 * @param array|false  $fa     Datos de la hoja de Font Awesome.
	 * @return array
	 */
	private static function calcular( $uncode, $fa ) {
		$css_uncode = $uncode ? self::leer( $uncode ) : '';
		$css_fa     = '';

		if ( $fa ) {
			if ( ! empty( $fa['path'] ) ) {
				$css_fa = self::leer( $fa['path'] );
			} else {
				$css_fa = self::leer_remoto( $fa['src'] );
			}
		}

		$mapa_uncode = '' === $css_uncode ? array() : self::parsear( $css_uncode );

		if ( '' === $css_uncode || '' === $css_fa ) {
			$mapa = require UNCFI_DIR . 'data/fallback-collisions.php';

			// Lado Uncode: si hay una hoja de FA que no pudimos leer no sabemos su
			// version, y con FA >= 6.6 cualquier icono del tema puede romperse; se
			// cubren todos. Sin hoja de FA, basta con las colisiones conocidas.
			$propios = $fa ? $mapa_uncode : array_intersect_key( $mapa_uncode, $mapa );

			return array(
				'css'    => self::render( $mapa ) . self::render_uncode( $propios ),
				'count'  => count( $mapa ) + count( $propios ),
				'source' => 'fallback',
				'fa'     => $fa ? $fa['handle'] : '',
				'uncode' => $uncode ? $uncode : '',
			);
		}

		$mapa_fa    = self::parsear( $css_fa );
		$colisiones = array();

		foreach ( $mapa_fa as $clase => $codepoint ) {
			if ( isset( $mapa_uncode[ $clase ] ) && $mapa_uncode[ $clase ] !== $codepoint ) {
				$colisiones[ $clase ] = $codepoint;
			}
		}

		ksort( $colisiones );

		// FA >= 6.6 trae `.fa::before{content:var(--fa)}`: pisa TODOS los iconos
		// `.fa` del tema, choquen de nombre o no (los que FA no conoce se quedan
		// sin `content` y desaparecen). Con FA anterior solo estorban las colisiones.
		$propios = false !== strpos( $css_fa, 'var(--fa)' )
			? $mapa_uncode
			: array_intersect_key( $mapa_uncode, $colisiones );

		return array(
			'css'    => self::render( $colisiones ) . self::render_uncode( $propios ),
			'count'  => count( $colisiones ) + count( $propios ),
			'source' => 'calculado',
			'fa'     => $fa['handle'],
			'uncode' => $uncode,
		);
	}

	/**
	 * Extrae `clase => codepoint` de las reglas `.fa-x::before{content:"\xxxx"}`
	 * y, para FA >= 6.6, de `.fa-x{--fa:"\xxxx"}`.
	 *
	 * @param string $css Hoja completa.
	 * @return array<string,string>
	 */
	public static function parsear( $css ) {
		$css   = preg_replace( '#/\*.*?\*/#s', '', $css );
		$mapa  = array();
		$total = preg_match_all( '#([^{}]+)\{([^{}]*)\}#s', $css, $reglas, PREG_SET_ORDER );

		if ( ! $total ) {
			return $mapa;
		}

		foreach ( $reglas as $regla ) {
			if ( preg_match( '#content\s*:\s*["\']\\\\([0-9a-fA-F]+)["\']#i', $regla[2], $m ) ) {
				$patron = '#^\.(fa-[a-z0-9-]+)::?before$#';
			} elseif ( preg_match( '#(?:^|;)\s*--fa\s*:\s*["\']\\\\([0-9a-fA-F]+)["\']#i', $regla[2], $m ) ) {
				$patron = '#^\.(fa-[a-z0-9-]+)$#';
			} else {
				continue;
			}
			$codepoint = strtolower( $m[1] );

			foreach ( explode( ',', $regla[1] ) as $selector ) {
				if ( preg_match( $patron, trim( $selector ), $s ) ) {
					$mapa[ $s[1] ] = $codepoint;
				}
			}
		}

		return $mapa;
	}

	/**
	 * Lado Font Awesome: restaura el codepoint de FA en los iconos con prefijo
	 * explicito, que Uncode nunca usa. La especificidad (0,2,1) le gana al
	 * (0,1,1) de Uncode sin `!important`, y por eso el orden de carga da igual.
	 *
	 * @param array<string,string> $colisiones Mapa clase => codepoint.
	 * @return string
	 */
	public static function render( $colisiones ) {
		$lineas = array();

		foreach ( $colisiones as $clase => $codepoint ) {
			$lineas[] = sprintf(
				':is(%s).%s::before{content:"\\%s"}',
				self::PREFIJOS,
				$clase,
				$codepoint
			);
		}

		return implode( "\n", $lineas );
	}

	/**
	 * Lado Uncode: restaura el codepoint del tema en sus propios iconos
	 * (`<i class="fa fa-tiktok">`), por si Font Awesome carga DESPUES de
	 * `uncode-icons.css` o trae la regla generica `.fa::before` de FA >= 6.6.
	 *
	 * `.fa.fa-x::before` es (0,2,1) contra el (0,1,1) de `.fa-x::before` y de
	 * `.fa::before` de FA, asi que tampoco depende del orden. No hace falta
	 * excluir los prefijos de FA: un elemento con `.fa` se dibuja siempre con
	 * la fuente de Uncode (`font-family:'uncodeicon' !important`), lleve o no
	 * `.fas`, asi que nunca es un icono de FA que funcione.
	 *
	 * La regla de `font-weight` deshace el `.fa{font-weight:900}` de FA, que
	 * cargado despues engrosa los iconos del tema con negrita sintetica.
	 *
	 * @param array<string,string> $propios Mapa clase => codepoint de Uncode.
	 * @return string
	 */
	public static function render_uncode( $propios ) {
		if ( ! $propios ) {
			return '';
		}

		ksort( $propios );
		$lineas = array( sprintf( '.fa:not(%s){font-weight:normal}', self::PREFIJOS ) );

		foreach ( $propios as $clase => $codepoint ) {
			$lineas[] = sprintf( '.fa.%s::before{content:"\\%s"}', $clase, $codepoint );
		}

		return "\n" . implode( "\n", $lineas );
	}

	/**
	 * Lee un archivo local.
	 *
	 * @param string $ruta Ruta absoluta.
	 * @return string
	 */
	private static function leer( $ruta ) {
		if ( ! $ruta || ! is_readable( $ruta ) ) {
			return '';
		}
		$contenido = file_get_contents( $ruta ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return is_string( $contenido ) ? $contenido : '';
	}

	/**
	 * Ultimo recurso para una hoja en un CDN u otro host.
	 *
	 * @param string $url URL de la hoja.
	 * @return string
	 */
	private static function leer_remoto( $url ) {
		// `wp_safe_remote_get` y no `wp_remote_get`: esta URL la escribio OTRO
		// plugin del sitio, no nosotros. La variante segura activa
		// `reject_unsafe_urls`, que pasa por `wp_http_validate_url()` y bloquea
		// direcciones internas — justo el caso para el que existe.
		$respuesta = wp_safe_remote_get( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $respuesta ) || 200 !== wp_remote_retrieve_response_code( $respuesta ) ) {
			return '';
		}

		return wp_remote_retrieve_body( $respuesta );
	}
}
