<?php
// Prueba el actualizador (Plugin Update Checker) por los dos lados, dentro de
// WordPress (wp eval-file) y contra la API real de GitHub.
//
// Uso: UFI_FALSA=0.9.9 para simular un sitio atrasado (debe ofrecer el ultimo
// release, con el .zip adjunto); sin ella, con la version instalada.

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$fallos = 0;
function uncfi_ok( $ok, $texto ) {
	global $fallos;
	$fallos += $ok ? 0 : 1;
	printf( "  %s %s\n", $ok ? 'OK' : 'XX', $texto );
}

echo "Arranque:\n";
uncfi_ok( class_exists( PucFactory::class ), 'vendor/ cargado (PucFactory existe)' );
uncfi_ok( false !== has_filter( 'puc_vcs_update_detection_strategies-' . UNCFI_SLUG ), 'filtro de estrategias enganchado' );

echo "solo_releases():\n";
$todas = array( 'latest_release' => 'a', 'latest_tag' => 'b', 'branch' => 'c' );
uncfi_ok( array( 'latest_release' ) === array_keys( UNCFI_Updater::solo_releases( $todas ) ), 'deja solo latest_release' );

// Un checker nuevo con el mismo slug: recibe el mismo filtro de estrategias.
function uncfi_checker( $regex ) {
	$c = PucFactory::buildUpdateChecker( UNCFI_Updater::REPO_URL, UNCFI_FILE, UNCFI_SLUG );
	$c->setBranch( 'main' );
	$c->getVcsApi()->enableReleaseAssets( $regex, UNCFI_Updater::REQUIRE_RELEASE_ASSETS );
	return $c;
}
$bueno = '/^' . preg_quote( UNCFI_Updater::ZIP, '/' ) . '$/';

echo "Release sin el .zip (regex que no casa con ningun asset):\n";
$u = uncfi_checker( '/^no-existe\.zip$/' )->requestUpdate();
uncfi_ok( null === $u, 'con el filtro: no ofrece nada' . ( $u ? ' (ofrece ' . $u->download_url . ')' : '' ) );

remove_filter( 'puc_vcs_update_detection_strategies-' . UNCFI_SLUG, array( 'UNCFI_Updater', 'solo_releases' ) );
$u = uncfi_checker( '/^no-existe\.zip$/' )->requestUpdate();
printf( "  -- sin el filtro, PUC ofreceria: %s\n", $u ? $u->version . ' desde ' . $u->download_url : 'nada' );
add_filter( 'puc_vcs_update_detection_strategies-' . UNCFI_SLUG, array( 'UNCFI_Updater', 'solo_releases' ) );

echo "Release real con el .zip, version instalada " . UNCFI_VERSION . ":\n";
$u = uncfi_checker( $bueno )->requestUpdate();
if ( $u ) {
	printf( "  -- ultimo release: %s, paquete %s\n", $u->version, $u->download_url );
	uncfi_ok( (bool) preg_match( '#/releases/download/[^/]+/uncode-fix-icons\.zip$#', $u->download_url ), 'el paquete es el .zip adjunto, no un zipball' );
	$ofrece = version_compare( $u->version, UNCFI_VERSION, '>' );
	$espera = (bool) getenv( 'UFI_FALSA' );
	uncfi_ok( $ofrece === $espera, $espera ? 'ofrece actualizar (sitio atrasado)' : 'no ofrece bajar de version' );
} else {
	uncfi_ok( false, 'GitHub no devolvio el ultimo release' );
}

echo $fallos ? "\n$fallos fallo(s)\n" : "\ntodo OK\n";
