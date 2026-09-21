<?php
/**
 * Pantalla de estado y aviso cuando el tema no es Uncode.
 *
 * @package UncodeFixIcons
 */

defined( 'ABSPATH' ) || exit;

/**
 * Todo lo que el plugin muestra en el escritorio de WordPress.
 *
 * Es deliberadamente una sola pantalla de solo lectura mas un boton: el plugin
 * no tiene nada que configurar, y lo unico que la gente necesita saber es si
 * esta haciendo algo y sobre que archivos.
 */
class UNCFI_Admin {

	/** Engancha todo. */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_notices', array( __CLASS__, 'aviso_tema' ) );
		add_action( 'admin_post_uncfi_regenerar', array( __CLASS__, 'regenerar' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( UNCFI_FILE ), array( __CLASS__, 'enlace' ) );
	}

	/** Entrada en Ajustes. */
	public static function menu() {
		add_options_page(
			__( 'Uncode Fix Icons', 'uncode-fix-icons' ),
			__( 'Uncode Fix Icons', 'uncode-fix-icons' ),
			'manage_options',
			UNCFI_SLUG,
			array( __CLASS__, 'pantalla' )
		);
	}

	/**
	 * Enlace directo desde la lista de plugins.
	 *
	 * @param array $enlaces Enlaces existentes.
	 * @return array
	 */
	public static function enlace( $enlaces ) {
		$url = admin_url( 'options-general.php?page=' . UNCFI_SLUG );
		array_unshift( $enlaces, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Estado', 'uncode-fix-icons' ) . '</a>' );
		return $enlaces;
	}

	/** Si el tema no es Uncode, el plugin no hace nada: hay que decirlo. */
	public static function aviso_tema() {
		if ( uncfi_uncode_activo() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p><strong>Uncode Fix Icons:</strong> '
			. esc_html__( 'el tema activo no es Uncode, asi que el plugin esta inactivo. No estorba, pero tampoco hace nada.', 'uncode-fix-icons' )
			. '</p></div>';
	}

	/** Borra la cache y regresa a la pantalla. */
	public static function regenerar() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sin permisos.', 'uncode-fix-icons' ) );
		}

		check_admin_referer( 'uncfi_regenerar' );
		UNCFI_Collisions::flush();

		wp_safe_redirect( admin_url( 'options-general.php?page=' . UNCFI_SLUG . '&uncfi=ok' ) );
		exit;
	}

	/** La pantalla de estado. */
	public static function pantalla() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Esta pantalla solo LEE lo que ya se calculo en el front. Una version
		// anterior disparaba aqui `do_action('wp_enqueue_scripts')` para poder
		// encontrar la hoja de Font Awesome, y eso ejecuta los callbacks de
		// front de todos los demas plugins dentro de wp-admin, en un contexto
		// que no esperan. No vale el riesgo por una pantalla informativa.
		$estado = UNCFI_Collisions::cacheado();
		$activo = uncfi_uncode_activo();

		$fuentes = array(
			'calculado' => __( 'Calculado de las hojas reales de este sitio', 'uncode-fix-icons' ),
			'fallback'  => __( 'Tabla de respaldo (no se pudieron leer las hojas)', 'uncode-fix-icons' ),
		);

		echo '<div class="wrap"><h1>' . esc_html__( 'Uncode Fix Icons', 'uncode-fix-icons' ) . '</h1>';

		if ( isset( $_GET['uncfi'] ) && 'ok' === $_GET['uncfi'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html__( 'Cache borrada. Abre el sitio una vez para que se recalcule.', 'uncode-fix-icons' )
				. '</p></div>';
		}

		if ( ! $estado ) {
			echo '<div class="notice notice-info inline" style="margin-top:1rem;max-width:52rem"><p>'
				. esc_html__( 'Todavia no hay nada calculado. Abre cualquier pagina del sitio una vez y vuelve aqui: el calculo se hace en el front, donde estan encoladas las hojas.', 'uncode-fix-icons' )
				. '</p></div>';

			if ( ! $activo ) {
				echo '<p>' . esc_html__( 'Ademas, el tema activo no es Uncode, asi que el plugin no va a hacer nada.', 'uncode-fix-icons' ) . '</p>';
			}

			echo '</div>';
			return;
		}

		echo '<table class="widefat striped" style="max-width:52rem"><tbody>';

		self::fila(
			__( 'Estado', 'uncode-fix-icons' ),
			$activo
				? sprintf(
					/* translators: %d: numero de reglas. */
					__( 'Activo — corrigiendo %d clases de iconos', 'uncode-fix-icons' ),
					$estado['count']
				)
				: __( 'Inactivo — el tema activo no es Uncode', 'uncode-fix-icons' )
		);

		self::fila(
			__( 'De donde salen las reglas', 'uncode-fix-icons' ),
			isset( $fuentes[ $estado['source'] ] ) ? $fuentes[ $estado['source'] ] : $estado['source']
		);

		self::fila(
			__( 'Hoja de Font Awesome detectada', 'uncode-fix-icons' ),
			$estado['fa'] ? $estado['fa'] : __( 'ninguna — sin ella no hay conflicto que arreglar', 'uncode-fix-icons' )
		);

		self::fila(
			__( 'Hoja de iconos de Uncode', 'uncode-fix-icons' ),
			$estado['uncode'] ? self::acortar( $estado['uncode'] ) : __( 'no encontrada', 'uncode-fix-icons' )
		);

		echo '</tbody></table>';

		if ( 'fallback' === $estado['source'] && $activo ) {
			echo '<div class="notice notice-warning inline" style="margin-top:1rem;max-width:52rem"><p>'
				. esc_html__( 'No se pudieron leer las dos hojas de este sitio, asi que se esta usando la tabla de respaldo (Font Awesome 6.5.1 contra Uncode 2.12.x). Si tu sitio usa otras versiones, algun icono podria seguir saliendo mal.', 'uncode-fix-icons' )
				. '</p></div>';
		}

		echo '<p style="margin-top:1rem">';
		echo '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=uncfi_regenerar' ), 'uncfi_regenerar' ) ) . '">'
			. esc_html__( 'Recalcular ahora', 'uncode-fix-icons' ) . '</a>';
		echo ' <span class="description">'
			. esc_html__( 'Se recalcula solo cuando cambian las hojas; esto es para forzarlo.', 'uncode-fix-icons' )
			. '</span></p>';

		if ( $estado['css'] ) {
			echo '<h2>' . esc_html__( 'CSS que se esta aplicando', 'uncode-fix-icons' ) . '</h2>';
			echo '<textarea readonly rows="12" style="width:100%;max-width:52rem;font-family:monospace;font-size:11px">'
				. esc_textarea( $estado['css'] ) . '</textarea>';
			echo '<p class="description">'
				. esc_html__( 'Si algun dia quitas el plugin, este mismo bloque pegado en Apariencia → Personalizar → CSS adicional hace lo mismo.', 'uncode-fix-icons' )
				. '</p>';
		}

		echo '</div>';
	}

	/**
	 * Una fila de la tabla de estado.
	 *
	 * @param string $etiqueta Etiqueta.
	 * @param string $valor    Valor.
	 */
	private static function fila( $etiqueta, $valor ) {
		echo '<tr><td style="width:16rem"><strong>' . esc_html( $etiqueta ) . '</strong></td>'
			. '<td>' . esc_html( $valor ) . '</td></tr>';
	}

	/**
	 * Recorta una ruta absoluta para no enseñar toda la estructura del servidor.
	 *
	 * @param string $ruta Ruta absoluta.
	 * @return string
	 */
	private static function acortar( $ruta ) {
		$base = defined( 'ABSPATH' ) ? ABSPATH : '';
		return $base && 0 === strpos( $ruta, $base ) ? substr( $ruta, strlen( $base ) ) : basename( $ruta );
	}
}
