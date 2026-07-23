<?php
/**
 * Plugin composition root.
 *
 * @package ProductDatasheetAutopilot
 */

defined( 'ABSPATH' ) || exit;

class PDA_Bootstrap {
	/** @var PDA_Job_Runner|null */
	private static $runner;

	/**
	 * Activation has no network side effects.
	 *
	 * @return void
	 */
	public static function activate() {
		// The compatibility gate runs on plugins_loaded; WordPress loads language packs on demand.
		update_option( 'pda_incompatible', '', false );
		if ( false === get_option( PDA_Settings::OPTION, false ) ) {
			add_option( PDA_Settings::OPTION, PDA_Settings::defaults(), '', false );
		}
		update_option( 'pda_rewrite_flush_pending', 1, false );
	}

	/**
	 * Register safe hooks only after WordPress is loaded.
	 *
	 * @return void
	 */
	public static function init() {
		$error_code = PDA_Compatibility::error_code();

		if ( (string) get_option( 'pda_incompatible', '' ) !== $error_code ) {
			update_option( 'pda_incompatible', $error_code, false );
		}

		if ( '' !== $error_code ) {
			add_action( 'admin_notices', array( __CLASS__, 'compatibility_notice' ) );
			return;
		}
		$telemetry      = new PDA_Telemetry();
		$store          = new PDA_File_Store( $telemetry );
		$snapshot       = new PDA_Product_Snapshot();
		$mapper         = new PDA_Deterministic_Mapper();
		$validator      = new PDA_Map_Validator();
		$renderer       = new PDA_PDF_Renderer();
		$ai_client      = new PDA_AI_Client( $telemetry );
		self::$runner   = new PDA_Job_Runner( $snapshot, $mapper, $validator, $renderer, $store, $ai_client, $telemetry );
		$endpoint       = new PDA_Download_Endpoint( $store, $telemetry );
		$admin          = new PDA_Admin_Page( self::$runner, $telemetry );
		$diagnostics    = new PDA_Diagnostics( $store );
		$endpoint->register();
		$admin->register();
		$diagnostics->register();
		self::$runner->register();
		add_action( 'woocommerce_single_product_summary', array( $endpoint, 'render_button' ), 35 );
		if ( file_exists( PDA_DIR . 'premium/class-premium-bootstrap.php' ) ) {
			require_once PDA_DIR . 'premium/class-premium-bootstrap.php';
			PDA_Premium_Bootstrap::init( self::$runner, $store, $telemetry, $ai_client );
		}
	}

	/**
	 * @return void
	 */
	public static function compatibility_notice() {
		echo '<div class="notice notice-error"><p>' . esc_html( PDA_Compatibility::reason() ) . '</p></div>';
	}
}

/**
 * Load fixed sections once per request.
 *
 * @return array<string,string>
 */
function pda_sections() {
	static $sections = null;
	if ( null === $sections ) {
		$sections = require PDA_DIR . '../config/sections.php';
	}
	return $sections;
}
