<?php
// Prueba el actualizador por los dos lados, dentro de WordPress (wp eval-file).
// Simula respuestas de la API de GitHub poniendolas en el transient que el
// plugin consulta primero, asi no hace falta publicar releases de prueba.

$zip = new ReflectionMethod( 'UNCFI_Updater', 'zip' );
$zip->setAccessible( true );

$bueno = 'https://github.com/pablotll/uncode-fix-icons/releases/download/v9.9.9/uncode-fix-icons.zip';
$casos = array(
	'sin assets, solo zipball'         => array( array( 'zipball_url' => 'https://api.github.com/repos/x/y/zipball/v9.9.9' ), '' ),
	'assets vacio'                     => array( array( 'assets' => array(), 'zipball_url' => 'https://z' ), '' ),
	'un .zip con otro nombre'          => array( array( 'assets' => array( array( 'browser_download_url' => 'https://x/otro-plugin.zip' ) ) ), '' ),
	'solo el .tar.gz'                  => array( array( 'assets' => array( array( 'browser_download_url' => 'https://x/uncode-fix-icons.tar.gz' ) ) ), '' ),
	'el .zip correcto'                 => array( array( 'assets' => array( array( 'browser_download_url' => $bueno ) ) ), $bueno ),
);

$fallos = 0;
echo "zip():\n";
foreach ( $casos as $nombre => $caso ) {
	$r  = $zip->invoke( null, $caso[0] );
	$ok = $r === $caso[1];
	$fallos += $ok ? 0 : 1;
	printf( "  %s %-28s -> %s\n", $ok ? 'OK' : 'XX', $nombre, '' === $r ? "'' (no ofrece)" : $r );
}

// comprobar(): lo que de verdad ve WordPress.
function uncfi_probar_comprobar( $release ) {
	set_site_transient( UNCFI_Updater::TRANSIENT, $release, HOUR_IN_SECONDS );
	$t           = new stdClass();
	$t->response = array();
	$t           = UNCFI_Updater::comprobar( $t );
	return isset( $t->response[ plugin_basename( UNCFI_FILE ) ] ) ? $t->response[ plugin_basename( UNCFI_FILE ) ] : null;
}

echo "comprobar(), version instalada " . UNCFI_VERSION . ":\n";
$escenarios = array(
	'9.9.9 SIN .zip adjunto'  => array( array( 'version' => '9.9.9', 'zip' => '', 'notas' => '', 'fecha' => '' ), false ),
	'9.9.9 CON .zip adjunto'  => array( array( 'version' => '9.9.9', 'zip' => $bueno, 'notas' => '', 'fecha' => '' ), true ),
	'1.0.0 (mas vieja)'       => array( array( 'version' => '1.0.0', 'zip' => $bueno, 'notas' => '', 'fecha' => '' ), false ),
	'misma version'           => array( array( 'version' => UNCFI_VERSION, 'zip' => $bueno, 'notas' => '', 'fecha' => '' ), false ),
);
foreach ( $escenarios as $nombre => $e ) {
	$u      = uncfi_probar_comprobar( $e[0] );
	$ofrece = null !== $u;
	$ok     = $ofrece === $e[1];
	$fallos += $ok ? 0 : 1;
	printf( "  %s %-24s -> %s\n", $ok ? 'OK' : 'XX', $nombre, $ofrece ? 'ofrece ' . $u->new_version . ' (' . basename( $u->package ) . ')' : 'no ofrece nada' );
}

delete_site_transient( UNCFI_Updater::TRANSIENT );

// Contra la API real: hoy el ultimo release es 1.0.0 y lo instalado es 1.0.1.
$real = UNCFI_Updater::release();
echo "API real de GitHub: ultimo release " . ( $real ? $real['version'] . ', zip: ' . basename( $real['zip'] ) : 'no disponible' ) . "\n";
$t = new stdClass(); $t->response = array();
$t = UNCFI_Updater::comprobar( $t );
$ok = empty( $t->response );
$fallos += $ok ? 0 : 1;
printf( "  %s con 1.0.1 instalada no ofrece bajar a la 1.0.0\n", $ok ? 'OK' : 'XX' );
delete_site_transient( UNCFI_Updater::TRANSIENT );

echo $fallos ? "\n$fallos fallo(s)\n" : "\ntodo OK\n";
