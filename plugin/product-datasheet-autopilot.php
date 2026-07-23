<?php
/**
 * Plugin Name: Product Datasheet Autopilot for WooCommerce
 * Description: Generate truthful, branded, single-product PDF datasheets from existing WooCommerce data.
 * Version: 1.0.0
 * Requires at least: 6.9
 * Tested up to: 7.0
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * Author: Product Datasheet Autopilot
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: product-datasheet-autopilot
 * Domain Path: /languages
 *
 * @package ProductDatasheetAutopilot
 */

defined( 'ABSPATH' ) || exit;

define( 'PDA_VERSION', '1.0.0' );
define( 'PDA_TEMPLATE_VERSION', '1' );
define( 'PDA_GATEWAY_URL', getenv( 'PDA_GATEWAY_URL' ) ?: 'https://pda-ai-gateway.ACCOUNT.workers.dev' );
define( 'PDA_FILE', __FILE__ );
define( 'PDA_DIR', plugin_dir_path( __FILE__ ) );
define( 'PDA_URL', plugin_dir_url( __FILE__ ) );

$pda_autoload = PDA_DIR . 'vendor/autoload.php';

// Packaged builds provide this file; static checks and incomplete installs must not fatal.
if ( file_exists( $pda_autoload ) ) {
	require_once $pda_autoload;
}

foreach ( glob( PDA_DIR . 'includes/class-*.php' ) as $pda_file ) {
	require_once $pda_file;
}

register_activation_hook( __FILE__, array( 'PDA_Bootstrap', 'activate' ) );
register_uninstall_hook( __FILE__, 'pda_uninstall' );

add_action( 'plugins_loaded', array( 'PDA_Bootstrap', 'init' ) );

/**
 * Uninstall callback delegates to the guarded uninstall file.
 *
 * @return void
 */
function pda_uninstall() {
	require PDA_DIR . 'uninstall.php';
}
