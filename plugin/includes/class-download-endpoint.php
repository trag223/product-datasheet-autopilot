<?php
/**
 * Stable, noindex public PDF route.
 *
 * @package ProductDatasheetAutopilot
 */

defined( 'ABSPATH' ) || exit;

class PDA_Download_Endpoint {
	/** @var PDA_File_Store */ private $store;
	/** @var PDA_Telemetry */ private $telemetry;

	/**
	 * @param PDA_File_Store $store Store.
	 * @param PDA_Telemetry  $telemetry Events.
	 */
	public function __construct( PDA_File_Store $store, PDA_Telemetry $telemetry ) {
		$this->store     = $store;
		$this->telemetry = $telemetry;
	}

	/** @return void */
	public function register() {
		add_rewrite_rule( '^product-datasheet/([0-9]+)\\.pdf$', 'index.php?pda_product_datasheet=$matches[1]', 'top' );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'serve' ) );
	}

	/**
	 * @param array<int,string> $vars Query vars.
	 * @return array<int,string>
	 */
	public function query_vars( $vars ) {
		$vars[] = 'pda_product_datasheet';
		return $vars;
	}

	/** @return void */
	public function serve() {
		$product_id = absint( get_query_var( 'pda_product_datasheet' ) );
		if ( ! $product_id ) {
			return;
		}
		$current = $this->store->current( $product_id );
		if ( ! $current ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}
		status_header( 200 );
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="product-datasheet-' . $product_id . '.pdf"' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Cache-Control: public, max-age=3600' );
		header( 'Content-Length: ' . filesize( $current['path'] ) );
		$this->telemetry->record( 'download_served' );
		readfile( $current['path'] );
		exit;
	}

	/** @return void */
	public function render_button() {
		global $product;
		if ( ! PDA_Settings::get( 'download_button', true ) || ! $product || ! $this->store->current( $product->get_id() ) ) {
			return;
		}
		$url = home_url( '/product-datasheet/' . $product->get_id() . '.pdf' );
		echo '<p class="pda-download"><a class="button" href="' . esc_url( $url ) . '" rel="nofollow">' . esc_html__( 'Download datasheet (PDF)', 'product-datasheet-autopilot' ) . '</a></p>';
	}
}
