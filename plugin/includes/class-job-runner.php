<?php
/**
 * Generation pipeline and Action Scheduler entry point.
 *
 * @package ProductDatasheetAutopilot
 */

defined( 'ABSPATH' ) || exit;

class PDA_Generation_Limit_Exception extends RuntimeException {}

class PDA_Job_Runner {
	/** @var PDA_Product_Snapshot */ private $snapshot;
	/** @var PDA_Deterministic_Mapper */ private $mapper;
	/** @var PDA_Map_Validator */ private $validator;
	/** @var PDA_PDF_Renderer */ private $renderer;
	/** @var PDA_File_Store */ private $store;
	/** @var PDA_AI_Client */ private $ai;
	/** @var PDA_Telemetry */ private $telemetry;

	/**
	 * @param PDA_Product_Snapshot     $snapshot Snapshot builder.
	 * @param PDA_Deterministic_Mapper $mapper Local mapper.
	 * @param PDA_Map_Validator        $validator Strict validator.
	 * @param PDA_PDF_Renderer         $renderer Renderer.
	 * @param PDA_File_Store           $store Store.
	 * @param PDA_AI_Client            $ai Optional AI client.
	 * @param PDA_Telemetry            $telemetry Events.
	 */
	public function __construct( PDA_Product_Snapshot $snapshot, PDA_Deterministic_Mapper $mapper, PDA_Map_Validator $validator, PDA_PDF_Renderer $renderer, PDA_File_Store $store, PDA_AI_Client $ai, PDA_Telemetry $telemetry ) {
		$this->snapshot  = $snapshot;
		$this->mapper     = $mapper;
		$this->validator  = $validator;
		$this->renderer   = $renderer;
		$this->store      = $store;
		$this->ai         = $ai;
		$this->telemetry  = $telemetry;
	}

	/** @return void */
	public function register() {
		add_action( 'pda_regenerate_product', array( $this, 'run_scheduled' ), 10, 1 );
	}

	/**
	 * @param int $product_id Product ID.
	 * @return void
	 */
	public function run_scheduled( $product_id ) {
		$this->generate( (int) $product_id, 'scheduled' );
	}

	/**
	 * Generate a PDF. Every recoverable AI failure resolves to local mapping.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $source Trigger source.
	 * @return array<string,mixed>
	 */
	public function generate( $product_id, $source = 'manual' ) {
		try {
			$product = wc_get_product( $product_id );
			if ( ! $product || ! $product->is_visible() ) {
				throw new InvalidArgumentException( 'ineligible_product' );
			}
			$this->assert_free_limit( $product_id );
			$snapshot = $this->snapshot->build( $product );
			$hash     = PDA_Content_Hash::for_snapshot( $snapshot );
			if ( $this->store->is_current( $product_id, $hash ) ) {
				return array( 'success' => true, 'skipped' => true, 'hash' => $hash );
			}
			$map      = $this->mapper->map( $snapshot );
			$fallback = false;
			$ai_map   = $this->ai->map( $snapshot );
			if ( is_array( $ai_map ) ) {
				try {
					$map = $this->validator->assert_valid( $ai_map, $snapshot );
				} catch ( PDA_Map_Validation_Exception $exception ) {
					$fallback = true;
					$this->telemetry->record( 'ai_invalid_map' );
				}
			}
			$pdf = $this->renderer->render( $snapshot, $this->validator->assert_valid( $map, $snapshot ) );
			if ( ! $this->store->publish( $product_id, $hash, $pdf ) ) {
				throw new RuntimeException( 'publish_failed' );
			}
			$this->remember_generated_product( $product_id );
			$this->telemetry->record( 'generation_succeeded' );
			return array( 'success' => true, 'hash' => $hash, 'fallback' => $fallback );
		} catch ( Throwable $exception ) {
			$this->telemetry->record( 'generation_failed' );
			return array( 'success' => false, 'error' => $exception->getMessage() );
		}
	}

	/**
	 * @param int $product_id Product ID.
	 * @return void
	 * @throws PDA_Generation_Limit_Exception On the fourth distinct free product.
	 */
	private function assert_free_limit( $product_id ) {
		if ( apply_filters( 'pda_is_pro', false ) ) {
			return;
		}
		$generated = get_option( 'pda_generated_product_ids', array() );
		$generated = is_array( $generated ) ? array_map( 'absint', $generated ) : array();
		if ( ! in_array( $product_id, $generated, true ) && count( $generated ) >= 3 ) {
			throw new PDA_Generation_Limit_Exception( 'free_product_limit' );
		}
	}

	/**
	 * @param int $product_id Product ID.
	 * @return void
	 */
	private function remember_generated_product( $product_id ) {
		if ( apply_filters( 'pda_is_pro', false ) ) {
			return;
		}
		$generated = get_option( 'pda_generated_product_ids', array() );
		$generated = is_array( $generated ) ? array_map( 'absint', $generated ) : array();
		if ( ! in_array( $product_id, $generated, true ) ) {
			$generated[] = $product_id;
			update_option( 'pda_generated_product_ids', array_slice( $generated, 0, 3 ), false );
		}
	}
}
