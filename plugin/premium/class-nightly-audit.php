<?php
/**
 * Pro nightly stale and missing PDF audit.
 *
 * @package ProductDatasheetAutopilot
 */

defined( 'ABSPATH' ) || exit;

class PDA_Nightly_Audit {
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
		add_action( 'init', array( $this, 'schedule' ) );
		add_action( 'pda_nightly_audit', array( $this, 'run' ), 10, 1 );
	}

	/** @return void */
	public function schedule() {
		if ( ! apply_filters( 'pda_is_pro', false ) || ! function_exists( 'as_next_scheduled_action' ) || false !== as_next_scheduled_action( 'pda_nightly_audit', array( 0 ), 'pda' ) ) {
			return;
		}
		as_schedule_single_action( $this->next_0215(), 'pda_nightly_audit', array( 0 ), 'pda' );
	}

	/**
	 * @param int $offset Product offset.
	 * @return void
	 */
	public function run( $offset = 0 ) {
		if ( ! apply_filters( 'pda_is_pro', false ) ) {
			return;
		}
		$offset = min( 500, max( 0, absint( $offset ) ) );
		$ids    = wc_get_products( array( 'status' => 'publish', 'limit' => 50, 'offset' => $offset, 'orderby' => 'ID', 'order' => 'ASC', 'return' => 'ids' ) );
		foreach ( $ids as $id ) {
			if ( ! $this->store->current( (int) $id ) && function_exists( 'as_enqueue_async_action' ) ) {
				as_enqueue_async_action( 'pda_regenerate_product', array( (int) $id ), 'pda' );
			}
		}
		$this->purge_temps();
		if ( 50 === count( $ids ) && $offset + 50 < 500 && function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'pda_nightly_audit', array( $offset + 50 ), 'pda' );
			return;
		}
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( $this->next_0215(), 'pda_nightly_audit', array( 0 ), 'pda' );
		}
	}

	/**
	 * @return int UTC timestamp for 02:15 in the site's configured timezone.
	 */
	private function next_0215() {
		$now  = new DateTimeImmutable( 'now', wp_timezone() );
		$next = $now->setTime( 2, 15 );
		if ( $next <= $now ) {
			$next = $next->modify( '+1 day' );
		}
		return $next->getTimestamp();
	}

	/**
	 * Delete only old temp files beneath this plugin's own resolved upload root.
	 *
	 * @return void
	 */
	private function purge_temps() {
		$uploads = wp_upload_dir();
		$root    = trailingslashit( $uploads['basedir'] ) . 'product-datasheet-autopilot';
		if ( ! is_dir( $root ) ) {
			return;
		}
		foreach ( glob( $root . '/*/*.tmp' ) ?: array() as $temp ) {
			if ( 0 === strpos( realpath( dirname( $temp ) ) ?: '', realpath( $root ) ?: '' ) && filemtime( $temp ) < time() - DAY_IN_SECONDS ) {
				@unlink( $temp );
			}
		}
	}
}
