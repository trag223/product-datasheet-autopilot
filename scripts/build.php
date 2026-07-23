<?php
/**
 * Build an installable free or Pro plugin tree. The free tree never contains
 * premium PHP, even if the source checkout does.
 */

declare( strict_types=1 );

$mode = $argv[1] ?? '';
if ( ! in_array( $mode, array( 'free', 'pro' ), true ) ) {
	fwrite( STDERR, "Usage: php scripts/build.php <free|pro>\n" );
	exit( 2 );
}

$root = dirname( __DIR__ );
$stage_root = $root . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . $mode;
$plugin_stage = $stage_root . DIRECTORY_SEPARATOR . 'product-datasheet-autopilot';
if ( str_replace( '\\', '/', realpath( dirname( $stage_root ) ) ?: dirname( $stage_root ) ) !== str_replace( '\\', '/', $root . '/dist/build' ) ) {
	throw new RuntimeException( 'Unsafe build path.' );
}
delete_tree( $stage_root );
copy_tree( $root . DIRECTORY_SEPARATOR . 'plugin', $plugin_stage, array( '/vendor/', '/languages/' ) );
copy_tree( $root . DIRECTORY_SEPARATOR . 'config', $plugin_stage . DIRECTORY_SEPARATOR . 'config' );
if ( 'free' === $mode ) {
	delete_tree( $plugin_stage . DIRECTORY_SEPARATOR . 'premium' );
}
copy( $root . DIRECTORY_SEPARATOR . 'composer.json', $plugin_stage . DIRECTORY_SEPARATOR . 'composer.json' );
copy( $root . DIRECTORY_SEPARATOR . 'composer.lock', $plugin_stage . DIRECTORY_SEPARATOR . 'composer.lock' );
$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'composer' );
if ( ! file_exists( dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'composer' ) ) {
	$command = 'composer';
}
$command .= ' install --no-dev --prefer-dist --no-interaction --working-dir=' . escapeshellarg( $plugin_stage );
passthru( $command, $exit_code );
if ( 0 !== $exit_code ) {
	throw new RuntimeException( 'Composer production install failed.' );
}
generate_fonts( $plugin_stage );
echo "Built {$mode}: {$plugin_stage}\n";

/** @param array<int,string> $excluded */
function copy_tree( string $source, string $destination, array $excluded = array() ): void {
	if ( ! is_dir( $source ) ) {
		throw new RuntimeException( "Source is missing: {$source}" );
	}
	mkdir( $destination, 0775, true );
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
	foreach ( $iterator as $item ) {
		$relative = str_replace( '\\', '/', substr( $item->getPathname(), strlen( $source ) ) );
		if ( array_reduce( $excluded, static fn( bool $skip, string $needle ): bool => $skip || str_starts_with( $relative . '/', $needle ), false ) ) {
			continue;
		}
		$target = $destination . str_replace( '/', DIRECTORY_SEPARATOR, $relative );
		if ( $item->isDir() ) {
			is_dir( $target ) || mkdir( $target, 0775, true );
		} else {
			copy( $item->getPathname(), $target );
		}
	}
}

function delete_tree( string $path ): void {
	if ( ! file_exists( $path ) ) {
		return;
	}
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $iterator as $item ) {
		$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
	}
	rmdir( $path );
}

function generate_fonts( string $plugin_stage ): void {
	$font_dir = $plugin_stage . DIRECTORY_SEPARATOR . 'fonts';
	$maker    = $plugin_stage . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'fpdf' . DIRECTORY_SEPARATOR . 'fpdf' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Fpdf' . DIRECTORY_SEPARATOR . 'makefont' . DIRECTORY_SEPARATOR . 'makefont.php';
	if ( ! file_exists( $maker ) || ! file_exists( $font_dir . DIRECTORY_SEPARATOR . 'NotoSans-Regular.ttf' ) || ! file_exists( $font_dir . DIRECTORY_SEPARATOR . 'NotoSans-Bold.ttf' ) ) {
		throw new RuntimeException( 'Bundled Noto Sans or FPDF font maker is missing.' );
	}
	$current = getcwd();
	chdir( $font_dir );
	foreach ( array( 'NotoSans-Regular.ttf', 'NotoSans-Bold.ttf' ) as $font ) {
		$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $maker ) . ' ' . escapeshellarg( $font ) . ' cp1252 true true';
		passthru( $command, $status );
		if ( 0 !== $status ) {
			throw new RuntimeException( 'Unable to generate FPDF font metrics.' );
		}
	}
	chdir( $current ?: $plugin_stage );
}
