<?php
/**
 * Plugin Name: FA Provider (solo pruebas)
 * Description: Simula un plugin que carga Font Awesome en todo el sitio, como WPVR o GeoDirectory, e imprime iconos de prueba en el footer. Solo para tests/.
 * Version: 1.1.0
 */

defined( 'ABSPATH' ) || exit;

// Por defecto, como WPVR: prioridad 5, Font Awesome se imprime ANTES que
// uncode-icons.css. `UFI_FA_DESPUES` lo encola en 5000, como el
// WP_Font_Awesome_Settings de AyeCode (GeoDirectory): DESPUES de Uncode.
// `UFI_FA_REMOTO` la pide a use.fontawesome.com, tambien como AyeCode.
add_action( 'wp_enqueue_scripts', function () {
	$ver = defined( 'UFI_FA_VER' ) ? UFI_FA_VER : '6.5.1';
	$src = defined( 'UFI_FA_REMOTO' ) && UFI_FA_REMOTO
		? "https://use.fontawesome.com/releases/v$ver/css/all.css"
		: plugin_dir_url( __FILE__ ) . 'css/all.css';
	wp_enqueue_style( 'fa-provider', $src, array(), $ver );
}, defined( 'UFI_FA_DESPUES' ) && UFI_FA_DESPUES ? 5000 : 5 );

// Consumidores con prefijo explicito (t-*) e iconos propios del tema (u-*).
add_action( 'wp_footer', function () {
	echo '<div id="prueba-iconos">'
		. '<i id="t-clock" class="fas fa-clock"></i>'
		. '<i id="t-sync" class="fas fa-sync"></i>'
		. '<i id="t-times" class="fas fa-times"></i>'
		. '<i id="t-search" class="fas fa-search"></i>'
		. '<i id="t-fbf" class="fab fa-facebook-f"></i>'
		. '<i id="t-tiktok" class="fab fa-tiktok"></i>'
		. '<i id="u-tiktok" class="fa fa-tiktok"></i>'
		. '<i id="u-clock" class="fa fa-clock"></i>'
		. '<i id="u-search3" class="fa fa-search3"></i>'
		. '<i id="u-twx" class="fa fa-twitter-x-fa"></i>'
		. '<i id="u-insta" class="fa fa-instagram"></i>'
		. '</div>';
}, 99 );
