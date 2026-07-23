<?php
/**
 * Redacted local operational events.
 *
 * @package ProductDatasheetAutopilot
 */

defined( 'ABSPATH' ) || exit;

class PDA_Telemetry {
	/** @var string */
	const OPTION = 'pda_telemetry';

	/**
	 * Store only codes and bounded non-content metadata.
	 *
	 * @param string               $code Event code.
	 * @param array<string,scalar> $context Whitelisted context.
	 * @return void
	 */
	public function record( $code, array $context = array() ) {
		if ( ! PDA_Settings::telemetry_is_consented() ) {
			return;
		}
		$codes = array( 'ai_fallback', 'ai_invalid_map', 'download_served', 'generation_failed', 'generation_succeeded' );
		$code  = sanitize_key( $code );
		if ( ! in_array( $code, $codes, true ) ) {
			return;
		}
		$allowed = array();
		foreach ( array( 'http_status', 'page_count' ) as $key ) {
			if ( isset( $context[ $key ] ) && is_numeric( $context[ $key ] ) ) {
				$allowed[ $key ] = absint( $context[ $key ] );
			}
		}
		$events  = get_option( self::OPTION, array() );
		$events  = is_array( $events ) ? $events : array();
		$events[] = array_merge( array( 'at' => time(), 'code' => $code ), $allowed );
		update_option( self::OPTION, array_slice( $events, -100 ), false );
	}

	/**
	 * Diagnostics expose no historic events unless the merchant opted into their
	 * storage, and then expose event codes only.
	 *
	 * @return array<int,string>
	 */
	public static function event_codes() {
		if ( ! PDA_Settings::telemetry_is_consented() ) {
			return array();
		}
		return array_values( array_unique( array_column( (array) get_option( self::OPTION, array() ), 'code' ) ) );
	}
}
