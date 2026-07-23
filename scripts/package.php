<?php
/**
 * Package build output and emit an integrity checksum.
 */

declare( strict_types=1 );

$mode = $argv[1] ?? '';
if ( ! in_array( $mode, array( 'free', 'pro' ), true ) || ! class_exists( 'ZipArchive' ) ) {
	fwrite( STDERR, "Usage: php scripts/package.php <free|pro> (ZipArchive required)\n" );
	exit( 2 );
}
$root = dirname( __DIR__ );
define( 'PDA_BUILD_VERSION', '1.0.0' );
passthru( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . DIRECTORY_SEPARATOR . 'build.php' ) . ' ' . $mode, $status );
if ( 0 !== $status ) {
	exit( $status );
}
$source = $root . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . $mode . DIRECTORY_SEPARATOR . 'product-datasheet-autopilot';
$zip_path = $root . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR . "product-datasheet-autopilot-{$mode}-" . PDA_BUILD_VERSION . '.zip';
$zip = new ZipArchive();
if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
	throw new RuntimeException( 'Unable to create ZIP.' );
}
$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ) );
foreach ( $iterator as $file ) {
	$relative = str_replace( '\\', '/', substr( $file->getPathname(), strlen( dirname( $source ) ) + 1 ) );
	$zip->addFile( $file->getPathname(), $relative );
}
$zip->close();
file_put_contents( $zip_path . '.sha256', hash_file( 'sha256', $zip_path ) . '  ' . basename( $zip_path ) . "\n" );
echo "Packaged {$zip_path}\n";
