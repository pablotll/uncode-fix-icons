<?php
/**
 * Plugin Name: FA Provider (solo pruebas)
 * Description: Simula un plugin que carga Font Awesome 6 en todo el sitio, como hace WPVR, e imprime iconos de prueba en el footer. Solo para tests/.
 * Version: 1.0.0
 */

defined( 'ABSPATH' ) || exit;

// Prioridad 5: Font Awesome se imprime ANTES que uncode-icons.css, como en la
// vida real. `UFI_FA_DESPUES` invierte el orden para probar ese escenario.
add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'fa-provider', plugin_dir_url( __FILE__ ) . 'css/all.css', array(), '6.5.1' );
}, defined( 'UFI_FA_DESPUES' ) ? 200 : 5 );

// Consumidores con prefijo explicito (t-*) e iconos propios del tema (u-*).
add_action( 'wp_footer', function () {
	echo '<div id="prueba-iconos">'
		. '<i id="t-clock" class="fas fa-clock"></i>'
		. '<i id="t-sync" class="fas fa-sync"></i>'
		. '<i id="t-times" class="fas fa-times"></i>'
		. '<i id="t-search" class="fas fa-search"></i>'
		. '<i id="t-fbf" class="fab fa-facebook-f"></i>'
		. '<i id="u-tiktok" class="fa fa-tiktok"></i>'
		. '<i id="u-clock" class="fa fa-clock"></i>'
		. '</div>';
}, 99 );
