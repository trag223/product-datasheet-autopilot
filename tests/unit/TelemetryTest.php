<?php
declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

final class TelemetryTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['pda_test_options'] = array();
	}

	public function test_pre_consent_telemetry_does_not_read_or_write_an_event(): void {
		( new PDA_Telemetry() )->record( 'generation_succeeded', array( 'product_id' => 123, 'source' => 'private-title', 'http_status' => 200 ) );
		self::assertArrayNotHasKey( PDA_Telemetry::OPTION, $GLOBALS['pda_test_options'] );
		self::assertSame( array(), PDA_Telemetry::event_codes() );
	}

	public function test_opted_in_telemetry_never_stores_product_or_text_context(): void {
		$GLOBALS['pda_test_options'][ PDA_Settings::OPTION ] = array( 'analytics_opt_in' => true );
		( new PDA_Telemetry() )->record( 'generation_succeeded', array( 'product_id' => 123, 'source' => 'private-title', 'status' => 'private-value', 'http_status' => 200, 'page_count' => 2 ) );
		$event = $GLOBALS['pda_test_options'][ PDA_Telemetry::OPTION ][0];
		self::assertSame( 'generation_succeeded', $event['code'] );
		self::assertSame( 200, $event['http_status'] );
		self::assertSame( 2, $event['page_count'] );
		self::assertArrayNotHasKey( 'product_id', $event );
		self::assertArrayNotHasKey( 'source', $event );
		self::assertArrayNotHasKey( 'status', $event );
	}

	public function test_opted_in_telemetry_rejects_an_arbitrary_event_code(): void {
		$GLOBALS['pda_test_options'][ PDA_Settings::OPTION ] = array( 'analytics_opt_in' => true );
		( new PDA_Telemetry() )->record( 'private-product-title', array( 'http_status' => 200 ) );
		self::assertArrayNotHasKey( PDA_Telemetry::OPTION, $GLOBALS['pda_test_options'] );
	}
}
