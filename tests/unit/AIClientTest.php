<?php
declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

final class AIClientTest extends TestCase {
	public function test_no_network_request_is_possible_before_ai_consent(): void {
		$GLOBALS['pda_test_http_calls'] = 0;
		$client = new PDA_AI_Client( new PDA_Telemetry() );
		$result = $client->map( array( 'title' => 'Pump', 'fields' => array() ) );
		self::assertNull( $result );
		self::assertSame( 0, $GLOBALS['pda_test_http_calls'] );
	}
}
