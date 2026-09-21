<?php
/**
 * Actualizaciones desde los releases de GitHub.
 *
 * WordPress solo sabe buscar actualizaciones en wordpress.org. Esto engancha el
 * transient de actualizaciones para que un release nuevo en el repo aparezca en
 * el escritorio como cualquier otra actualizacion, con su boton.
 *
 * @package UncodeFixIcons
 */

defined( 'ABSPATH' ) || exit;

/**
 * Actualizador contra la API publica de GitHub.
 */
class UNCFI_Updater {

	/** Cuanto se cachea la consulta a GitHub. */
	const TTL = 12 * HOUR_IN_SECONDS;

	/** Transient de la ultima respuesta. */
	const TRANSIENT = 'uncfi_release';

	/** Engancha los filtros. */
	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'comprobar' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'ficha' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'renombrar' ), 10, 4 );
	}

	/**
	 * Consulta el ultimo release, con cache.
	 *
	 * Falla en silencio a proposito: si GitHub no contesta, el sitio no debe
	 * romperse ni tardar en cargar por eso.
	 *
	 * @return array|false
	 */
	public static function release() {
		$cache = get_site_transient( self::TRANSIENT );
		if ( is_array( $cache ) ) {
			return empty( $cache ) ? false : $cache;
		}

		$respuesta = wp_remote_get(
			'https://api.github.com/repos/' . UNCFI_REPO . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'uncode-fix-icons/' . UNCFI_VERSION,
				),
			)
		);

		if ( is_wp_error( $respuesta ) || 200 !== wp_remote_retrieve_response_code( $respuesta ) ) {
			set_site_transient( self::TRANSIENT, array(), HOUR_IN_SECONDS );
			return false;
		}

		$datos = json_decode( wp_remote_retrieve_body( $respuesta ), true );
		if ( ! is_array( $datos ) || empty( $datos['tag_name'] ) ) {
			set_site_transient( self::TRANSIENT, array(), HOUR_IN_SECONDS );
			return false;
		}

		$release = array(
			'version' => ltrim( $datos['tag_name'], 'vV' ),
			'zip'     => self::zip( $datos ),
			'notas'   => isset( $datos['body'] ) ? (string) $datos['body'] : '',
			'fecha'   => isset( $datos['published_at'] ) ? (string) $datos['published_at'] : '',
		);

		set_site_transient( self::TRANSIENT, $release, self::TTL );

		return $release;
	}

	/**
	 * Prefiere un .zip adjunto al release; si no hay, usa el zipball.
	 *
	 * El zipball trae el codigo dentro de una carpeta con el hash del commit,
	 * por eso hace falta `renombrar()` mas abajo.
	 *
	 * @param array $datos Respuesta de la API.
	 * @return string
	 */
	private static function zip( $datos ) {
		if ( ! empty( $datos['assets'] ) && is_array( $datos['assets'] ) ) {
			foreach ( $datos['assets'] as $asset ) {
				if ( isset( $asset['browser_download_url'] ) && preg_match( '#\.zip$#i', $asset['browser_download_url'] ) ) {
					return $asset['browser_download_url'];
				}
			}
		}

		return isset( $datos['zipball_url'] ) ? $datos['zipball_url'] : '';
	}

	/**
	 * Mete el update en el transient si hay version nueva.
	 *
	 * @param object $transient Transient de actualizaciones.
	 * @return object
	 */
	public static function comprobar( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$release = self::release();
		if ( ! $release || empty( $release['zip'] ) ) {
			return $transient;
		}

		$basename = plugin_basename( UNCFI_FILE );

		if ( version_compare( $release['version'], UNCFI_VERSION, '<=' ) ) {
			if ( isset( $transient->response[ $basename ] ) ) {
				unset( $transient->response[ $basename ] );
			}
			return $transient;
		}

		$transient->response[ $basename ] = (object) array(
			'id'          => UNCFI_REPO,
			'slug'        => UNCFI_SLUG,
			'plugin'      => $basename,
			'new_version' => $release['version'],
			'url'         => 'https://github.com/' . UNCFI_REPO,
			'package'     => $release['zip'],
		);

		return $transient;
	}

	/**
	 * Ficha del plugin en la ventana de "Ver detalles".
	 *
	 * @param false|object|array $resultado Resultado previo.
	 * @param string             $accion    Accion pedida.
	 * @param object             $args      Argumentos.
	 * @return false|object|array
	 */
	public static function ficha( $resultado, $accion, $args ) {
		if ( 'plugin_information' !== $accion || empty( $args->slug ) || UNCFI_SLUG !== $args->slug ) {
			return $resultado;
		}

		$release = self::release();
		if ( ! $release ) {
			return $resultado;
		}

		return (object) array(
			'name'          => 'Uncode Fix Icons',
			'slug'          => UNCFI_SLUG,
			'version'       => $release['version'],
			'homepage'      => 'https://github.com/' . UNCFI_REPO,
			'download_link' => $release['zip'],
			'last_updated'  => $release['fecha'],
			'sections'      => array(
				'changelog' => wpautop( esc_html( $release['notas'] ) ),
			),
		);
	}

	/**
	 * Renombra la carpeta del zipball al slug del plugin.
	 *
	 * Sin esto, un zipball de GitHub se instalaria como
	 * `pablotll-uncode-fix-icons-a1b2c3d` y WordPress lo tomaria por un plugin
	 * distinto en cada actualizacion.
	 *
	 * @param string      $source      Carpeta de origen.
	 * @param string      $remote      Carpeta remota.
	 * @param WP_Upgrader $upgrader    Instancia del upgrader.
	 * @param array       $extra       Argumentos extra.
	 * @return string|WP_Error
	 */
	public static function renombrar( $source, $remote, $upgrader, $extra = array() ) {
		global $wp_filesystem;

		if ( empty( $extra['plugin'] ) || plugin_basename( UNCFI_FILE ) !== $extra['plugin'] ) {
			return $source;
		}

		if ( ! $wp_filesystem || basename( untrailingslashit( $source ) ) === UNCFI_SLUG ) {
			return $source;
		}

		$destino = trailingslashit( dirname( untrailingslashit( $source ) ) ) . UNCFI_SLUG;

		if ( $wp_filesystem->move( $source, $destino, true ) ) {
			return trailingslashit( $destino );
		}

		return $source;
	}
}
