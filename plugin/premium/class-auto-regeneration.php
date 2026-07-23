<?php
/**
 * Pro product mutation debounce.
 *
 * @package ProductDatasheetAutopilot
 */

defined( 'ABSPATH' ) || exit;

class PDA_Auto_Regeneration {
	/** @var PDA_File_Store */ private $store;

	/**
	 * @param PDA_File_Store $store Store.
	 */
	public function __construct( PDA_File_Store $store ) {
		$this->store = $store;
	}

	/** @return void */
	public function register() {
		add_action( 'woocommerce_update_product', array( $this, 'product_changed' ), 20 );
		add_action( 'woocommerce_product_import_inserted_product_object', array( $this, 'imported' ), 20, 2 );
		add_action( 'woocommerce_product_bulk_edit_save', array( $this, 'product_changed' ), 20 );
	}

	/**
	 * @param int $product_id Product ID.
	 * @return void
	 */
	public function product_changed( $product_id ) {
		if ( ! apply_filters( 'pda_is_pro', false ) ) {
			return;
		}
		$product_id = absint( $product_id );
		if ( ! $product_id ) {
			return;
		}
		$this->store->mark_stale( $product_id );
		if ( function_exists( 'as_next_scheduled_action' ) && false !== as_next_scheduled_action( 'pda_regenerate_product', array( $product_id ), 'pda' ) ) {
			return;
		}
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + 60, 'pda_regenerate_product', array( $product_id ), 'pda' );
		}
	}

	/**
	 * @param WC_Product $product Product.
	 * @return void
	 */
	public function imported( $product ) {
		if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
			$this->product_changed( $product->get_id() );
		}
	}
}
