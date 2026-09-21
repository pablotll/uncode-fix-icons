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
	 * Busca la hoja de Font Awesome entre los estilos registrados.
	 *
	 * Se identifica por CONTENIDO, no por nombre. Buscar `fontawesome` en la
	 * ruta parece obvio y falla callado: hay plugins que la sirven desde
	 * `assets/css/all.min.css` o con handles como `elementor-icons-fa-solid`, y
	 * ademas los paths se mueven entre versiones — wpvr 9.1.3 movio el suyo a
	 * `legacy/`. Lo unico estable es lo que la hoja trae dentro.
	 *
	 * Gana la que defina mas clases `.fa-*`, por si el sitio tiene varias.
	 *
	 * @return array{handle:string,src:string,path:string|false}|false
	 */
	public static function localizar_fontawesome() {
		$styles = wp_styles();
		if ( ! $styles instanceof WP_Styles ) {
			return false;
		}

		$mejor  = false;
		$reglas = 0;

		foreach ( $styles->registered as $handle => $style ) {
			if ( ! self::candidata( $handle, $style ) ) {
				continue;
			}

			$ruta = self::url_a_ruta( (string) $style->src );
			if ( ! $ruta ) {
				// Sin archivo local solo podemos fiarnos del nombre; se guarda
				// como ultimo recurso y se resuelve por HTTP mas adelante.
				if ( ! $mejor && preg_match( '#font-?awesome#i', $handle . ' ' . $style->src ) ) {
					$mejor = array( 'handle' => $handle, 'src' => (string) $style->src, 'path' => false );
				}
				continue;
			}

			$n = self::contar_reglas_fa( $ruta );
			if ( $n > $reglas ) {
				$reglas = $n;
				$mejor  = array( 'handle' => $handle, 'src' => (string) $style->src, 'path' => $ruta );
			}
		}

		return $mejor;
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
	 * Cuenta reglas `.fa-x::before{content:"\xxxx"}` en un archivo.
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
		if ( ! is_string( $css ) || false === stripos( $css, '.fa-' ) ) {
			return 0;
		}

		return (int) preg_match_all( '#\.fa-[a-z0-9-]+::?before\s*\{[^}]*content#i', $css );
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

		if ( '' === $css_uncode || '' === $css_fa ) {
			$mapa = require UNCFI_DIR . 'data/fallback-collisions.php';
			return array(
				'css'    => self::render( $mapa ),
				'count'  => count( $mapa ),
				'source' => 'fallback',
				'fa'     => $fa ? $fa['handle'] : '',
				'uncode' => $uncode ? $uncode : '',
			);
		}

		$mapa_uncode = self::parsear( $css_uncode );
		$mapa_fa     = self::parsear( $css_fa );
		$colisiones  = array();

		foreach ( $mapa_fa as $clase => $codepoint ) {
			if ( isset( $mapa_uncode[ $clase ] ) && $mapa_uncode[ $clase ] !== $codepoint ) {
				$colisiones[ $clase ] = $codepoint;
			}
		}

		ksort( $colisiones );

		return array(
			'css'    => self::render( $colisiones ),
			'count'  => count( $colisiones ),
			'source' => 'calculado',
			'fa'     => $fa['handle'],
			'uncode' => $uncode,
		);
	}

	/**
	 * Extrae `clase => codepoint` de las reglas `.fa-x::before{content:"\xxxx"}`.
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
			if ( ! preg_match( '#content\s*:\s*["\']\\\\([0-9a-fA-F]+)["\']#i', $regla[2], $m ) ) {
				continue;
			}
			$codepoint = strtolower( $m[1] );

			foreach ( explode( ',', $regla[1] ) as $selector ) {
				if ( preg_match( '#^\.(fa-[a-z0-9-]+)::?before$#', trim( $selector ), $s ) ) {
					$mapa[ $s[1] ] = $codepoint;
				}
			}
		}

		return $mapa;
	}

	/**
	 * Arma el CSS. La especificidad (0,2,0) le gana al (0,1,1) de Uncode sin
	 * `!important`, y por eso el orden de carga da igual.
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
