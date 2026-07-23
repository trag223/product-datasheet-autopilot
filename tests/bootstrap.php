<?php
declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . DIRECTORY_SEPARATOR );
define( 'PDA_DIR', dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'plugin' . DIRECTORY_SEPARATOR );
define( 'PDA_TEMPLATE_VERSION', '1' );

function __( $text ) { return $text; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $value ) ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, $args ); }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['pda_test_options'] ?? array() ) ? $GLOBALS['pda_test_options'][ $key ] : $default; }
function update_option( $key, $value ) { $GLOBALS['pda_test_options'][ $key ] = $value; return true; }
function apply_filters( $hook, $value ) { return $value; }
function wp_remote_post( $url, $args ) { $GLOBALS['pda_test_http_calls'] = ( $GLOBALS['pda_test_http_calls'] ?? 0 ) + 1; return null; }

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
foreach ( array( 'class-settings.php', 'class-telemetry.php', 'class-ai-client.php', 'class-product-snapshot.php', 'class-content-hash.php', 'class-deterministic-mapper.php', 'class-map-validator.php', 'class-pdf-renderer.php' ) as $file ) {
	require_once PDA_DIR . 'includes/' . $file;
}

function pda_sections() {
	return array(
		'identity' => 'Identity', 'dimensions' => 'Dimensions & Weight', 'materials' => 'Materials', 'performance' => 'Performance',
		'compatibility' => 'Compatibility', 'package' => 'Package Contents', 'compliance' => 'Store-Provided Ratings & Standards', 'other' => 'Other Specifications',
	);
}
