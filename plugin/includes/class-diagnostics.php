<?php
/**
 * Redacted WooCommerce status diagnostics.
 *
 * @package ProductDatasheetAutopilot
 */

defined( 'ABSPATH' ) || exit;

class PDA_Diagnostics {
	/** @var PDA_File_Store */ private $store;

	/**
	 * @param PDA_File_Store $store Store.
	 */
	public function __construct( PDA_File_Store $store ) {
		$this->store = $store;
	}

	/** @return void */
	public function register() {
		add_action( 'woocommerce_system_status_report', array( $this, 'render' ) );
	}

	/** @return void */
	public function render() {
		$data = array(
			'version'           => PDA_VERSION,
			'template_version'  => PDA_TEMPLATE_VERSION,
			'compatible'        => PDA_Compatibility::is_compatible(),
			'ai_consented'      => PDA_Settings::ai_is_consented(),
			'pro_active'        => (bool) apply_filters( 'pda_is_pro', false ),
			'gateway_configured' => 0 === strpos( PDA_GATEWAY_URL, 'https://' ) && false === strpos( PDA_GATEWAY_URL, 'ACCOUNT.workers.dev' ),
			'event_codes'       => PDA_Telemetry::event_codes(),
		);
		echo '<table class="wc_status_table widefat" cellspacing="0"><thead><tr><th colspan="2" data-export-label="Product Datasheet Autopilot"><h2>' . esc_html__( 'Product Datasheet Autopilot', 'product-datasheet-autopilot' ) . '</h2></th></tr></thead><tbody><tr><td colspan="2"><pre>' . esc_html( wp_json_encode( $data, JSON_PRETTY_PRINT ) ) . '</pre></td></tr></tbody></table>';
	}
}
