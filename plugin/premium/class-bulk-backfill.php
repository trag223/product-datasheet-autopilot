<?php
/**
 * Pro bulk generation in Action Scheduler-sized batches.
 *
 * @package ProductDatasheetAutopilot
 */

defined( 'ABSPATH' ) || exit;

class PDA_Bulk_Backfill {
	/** @var PDA_Job_Runner */ private $runner;

	/**
	 * @param PDA_Job_Runner $runner Generator.
	 */
	public function __construct( PDA_Job_Runner $runner ) {
		$this->runner = $runner;
	}

	/** @return void */
	public function register() {
		add_action( 'pda_bulk_backfill', array( $this, 'run' ), 10, 1 );
	}

	/**
	 * @param int $page Page number.
	 * @return void
	 */
	public function run( $page = 1 ) {
		if ( ! apply_filters( 'pda_is_pro', false ) ) {
			return;
		}
		$ids = wc_get_products( array( 'status' => 'publish', 'limit' => 50, 'page' => max( 1, absint( $page ) ), 'return' => 'ids' ) );
		foreach ( $ids as $id ) {
			$this->runner->generate( (int) $id, 'bulk_backfill' );
		}
		if ( 50 === count( $ids ) && function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'pda_bulk_backfill', array( $page + 1 ), 'pda' );
		}
	}
}
