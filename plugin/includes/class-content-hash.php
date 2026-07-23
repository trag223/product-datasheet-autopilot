<?php
/**
 * Idempotent content hashes.
 *
 * @package ProductDatasheetAutopilot
 */

defined( 'ABSPATH' ) || exit;

class PDA_Content_Hash {
	/**
	 * Hash layout-relevant snapshot content while excluding the product ID.
	 *
	 * @param array<string,mixed> $snapshot Snapshot.
	 * @return string
	 */
	public static function for_snapshot( array $snapshot ) {
		unset( $snapshot['product_id'] );
		return hash( 'sha256', PDA_TEMPLATE_VERSION . "\n" . wp_json_encode( self::sort( $snapshot ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	}

	/**
	 * Keep associative data stable without changing ordered field lists.
	 *
	 * @param mixed $value Input.
	 * @return mixed
	 */
	private static function sort( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::sort( $item );
		}
		if ( array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
			ksort( $value, SORT_STRING );
		}
		return $value;
	}
}
