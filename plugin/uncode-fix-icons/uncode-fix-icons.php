<?php
/**
 * Plugin Name:       Uncode Fix Icons
 * Plugin URI:        https://github.com/pablotll/uncode-fix-icons
 * Description:       Arregla los iconos que salen equivocados cuando el tema Uncode convive con un plugin que carga Font Awesome. Sin child theme y sin FTP.
 * Version:           1.0.1
 * Requires at least: 5.6
 * Requires PHP:      7.0
 * Author:            Pablo Torres
 * Author URI:        https://github.com/pablotll
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       uncode-fix-icons
 * Domain Path:       /languages
 *
 * @package UncodeFixIcons
 */

defined( 'ABSPATH' ) || exit;

define( 'UNCFI_VERSION', '1.0.1' );
define( 'UNCFI_FILE', __FILE__ );
define( 'UNCFI_DIR', plugin_dir_path( __FILE__ ) );
define( 'UNCFI_SLUG', 'uncode-fix-icons' );
define( 'UNCFI_REPO', 'pablotll/uncode-fix-icons' );

require_once UNCFI_DIR . 'includes/class-uncfi-collisions.php';
require_once UNCFI_DIR . 'includes/class-uncfi-admin.php';
require_once UNCFI_DIR . 'includes/class-uncfi-updater.php';

/**
 * ¿Esta activo el tema Uncode (o un child suyo)?
 *
 * Se mira el tema padre, que es donde vive `uncode-icons.css`, para que un
 * child theme con cualquier nombre siga contando.
 *
 * @return bool
 */
function uncfi_uncode_activo() {
	$tema = wp_get_theme( get_template() );

	if ( 'uncode' === strtolower( get_template() ) ) {
		return true;
	}

	return false !== stripos( (string) $tema->get( 'Name' ), 'uncode' );
}

/**
 * Inyecta el bloque de overrides.
 *
 * Prioridad 100 para que corra despues de que los demas plugins hayan
 * registrado sus hojas — necesitamos ver cual es la de Font Awesome.
 *
 * El orden de impresion no es critico: las reglas tienen especificidad 0,2,0
 * contra el 0,1,1 de Uncode, asi que ganan aunque se impriman antes. La
 * dependencia de `uncode-icons` es un cinturon de seguridad, y se agrega solo
 * si ese handle existe: declarar una dependencia inexistente haria que
 * WordPress no imprimiera nada.
 */
function uncfi_enqueue() {
	if ( ! uncfi_uncode_activo() ) {
		return;
	}

	$estado = UNCFI_Collisions::get();

	if ( '' === $estado['css'] ) {
		return;
	}

	$deps = wp_style_is( 'uncode-icons', 'registered' ) ? array( 'uncode-icons' ) : array();

	wp_register_style( 'uncfi-overrides', false, $deps, UNCFI_VERSION );
	wp_enqueue_style( 'uncfi-overrides' );
	wp_add_inline_style( 'uncfi-overrides', $estado['css'] );
}
add_action( 'wp_enqueue_scripts', 'uncfi_enqueue', 100 );

/**
 * Recalcula al activar, y cada vez que cambie el tema o se actualice algo:
 * es justo cuando pueden haberse movido los codepoints.
 */
function uncfi_flush() {
	UNCFI_Collisions::flush();
}
register_activation_hook( __FILE__, 'uncfi_flush' );
add_action( 'switch_theme', 'uncfi_flush' );
add_action( 'upgrader_process_complete', 'uncfi_flush' );

/** Al desactivar no queda nada: el plugin no escribe en el tema ni en el CSS del sitio. */
function uncfi_desactivar() {
	UNCFI_Collisions::flush();
}
register_deactivation_hook( __FILE__, 'uncfi_desactivar' );

UNCFI_Admin::init();
UNCFI_Updater::init();
