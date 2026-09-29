<?php
/**
 * Actualizaciones desde los releases del repo publico de GitHub, con Plugin Update
 * Checker (PUC). Sin token y sin mirror.
 *
 * Publicar una version es: git tag vX.Y.Z && git push origin vX.Y.Z. El workflow de
 * release arma uncode-fix-icons.zip (con vendor/) y lo adjunta al release; los sitios
 * lo ven en Escritorio → Actualizaciones.
 *
 * Tres detalles importan:
 *   - Solo el .zip adjunto. El zipball automatico de GitHub es la raiz del repo, donde
 *     el plugin vive en plugin/uncode-fix-icons/: se instalaria una carpeta sin el
 *     encabezado, el plugin quedaria desactivado y la version buena ya reemplazada.
 *     REQUIRE_RELEASE_ASSETS no basta para evitarlo: si el ultimo release no trae el
 *     .zip, PUC pasa a la estrategia "ultimo tag", que ofrece justo ese zipball. Por
 *     eso se deja solo la estrategia del ultimo release (ver solo_releases()).
 *   - El .zip se llama exactamente uncode-fix-icons.zip. El actualizador nativo de la
 *     1.0.x solo acepta ese nombre, y es el que instala la primera version con PUC.
 *   - El "ultimo release" de GitHub se salta los prereleases, asi que un tag como
 *     v1.2.0-rc.1 nunca llega a los sitios.
 *
 * @package UncodeFixIcons
 */

defined( 'ABSPATH' ) || exit;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Actualizador contra los releases de GitHub.
 */
final class UNCFI_Updater {

	const REPO_URL = 'https://github.com/pablotll/uncode-fix-icons/';

	/** El asset que se instala. No cambiarlo: el actualizador nativo de la 1.0.x lo busca por nombre. */
	const ZIP = 'uncode-fix-icons.zip';

	/** Version de WordPress con la que se declara probado. */
	const WP_TESTED = '7.1';

	/**
	 * Api::REQUIRE_RELEASE_ASSETS de PUC. Va como literal porque la clase vive en un
	 * namespace atado a la version menor de PUC (v5p7, v5p8…).
	 */
	const REQUIRE_RELEASE_ASSETS = 2;

	/** Api::STRATEGY_LATEST_RELEASE de PUC, por la misma razon. */
	const STRATEGY_LATEST_RELEASE = 'latest_release';

	/** Engancha el actualizador. */
	public static function init() {
		if ( ! self::should_run() ) {
			return;
		}
		if ( ! class_exists( PucFactory::class ) ) {
			return; // Falta vendor/ (un checkout de git sin composer install).
		}

		$checker = PucFactory::buildUpdateChecker( self::REPO_URL, UNCFI_FILE, UNCFI_SLUG );
		$checker->setBranch( 'main' );
		$checker->getVcsApi()->enableReleaseAssets( '/^' . preg_quote( self::ZIP, '/' ) . '$/', self::REQUIRE_RELEASE_ASSETS );

		add_filter( 'puc_vcs_update_detection_strategies-' . UNCFI_SLUG, array( __CLASS__, 'solo_releases' ) );
		add_filter( 'puc_request_update_result-' . UNCFI_SLUG, array( __CLASS__, 'completar_update' ) );
		add_filter( 'puc_request_info_result-' . UNCFI_SLUG, array( __CLASS__, 'completar_ficha' ) );
	}

	/**
	 * Solo donde se buscan o instalan actualizaciones. admin-ajax cuenta como admin
	 * aun para visitantes anonimos, asi que ahi ademas pide update_plugins.
	 *
	 * @return bool
	 */
	private static function should_run() {
		if ( wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return true;
		}
		if ( ! is_admin() ) {
			return false;
		}
		if ( wp_doing_ajax() ) {
			return current_user_can( 'update_plugins' );
		}
		return true;
	}

	/**
	 * Deja solo la estrategia del ultimo release. Las otras dos (ultimo tag y rama)
	 * descargan el zipball de la raiz del repo, que no es un plugin instalable.
	 *
	 * @param array $estrategias Estrategias de PUC, por nombre.
	 * @return array
	 */
	public static function solo_releases( $estrategias ) {
		return array_intersect_key( $estrategias, array( self::STRATEGY_LATEST_RELEASE => true ) );
	}

	/**
	 * Completa lo que GitHub no da: compatibilidad, para la pantalla de actualizaciones.
	 *
	 * @param object|null $update Update.
	 * @return object|null
	 */
	public static function completar_update( $update ) {
		if ( is_object( $update ) ) {
			$update->tested       = empty( $update->tested ) ? self::WP_TESTED : $update->tested;
			$update->requires_php = empty( $update->requires_php ) ? '7.0' : $update->requires_php;
		}
		return $update;
	}

	/**
	 * Lo mismo para la ventana de "Ver detalles".
	 *
	 * @param object|null $info Ficha del plugin.
	 * @return object|null
	 */
	public static function completar_ficha( $info ) {
		if ( is_object( $info ) ) {
			$info->tested       = empty( $info->tested ) ? self::WP_TESTED : $info->tested;
			$info->requires     = empty( $info->requires ) ? '5.6' : $info->requires;
			$info->requires_php = empty( $info->requires_php ) ? '7.0' : $info->requires_php;
		}
		return $info;
	}
}
