<?php
/**
 * Runtime compatibility gate.
 *
 * @package ProductDatasheetAutopilot
 */

defined( 'ABSPATH' ) || exit;

class PDA_Compatibility {
	/** @var string */
	const MIN_WP = '6.9';
	/** @var string */
	const MIN_WOO = '10.8';
	/** @var string */
	const MIN_PHP = '8.1';

	/**
	 * Return a safe, translatable incompatibility reason, or an empty string.
	 *
	 * @return string
	 */
	public static function reason() {
		global $wp_version;
		if ( version_compare( PHP_VERSION, self::MIN_PHP, '<' ) ) {
			return sprintf( __( 'Product Datasheet Autopilot requires PHP %s or newer.', 'product-datasheet-autopilot' ), self::MIN_PHP );
		}
		if ( isset( $wp_version ) && version_compare( (string) $wp_version, self::MIN_WP, '<' ) ) {
			return sprintf( __( 'Product Datasheet Autopilot requires WordPress %s or newer.', 'product-datasheet-autopilot' ), self::MIN_WP );
		}
		if ( ! defined( 'WC_VERSION' ) ) {
			return __( 'Product Datasheet Autopilot requires WooCommerce to be active.', 'product-datasheet-autopilot' );
		}
		if ( version_compare( WC_VERSION, self::MIN_WOO, '<' ) ) {
			return sprintf( __( 'Product Datasheet Autopilot requires WooCommerce %s or newer.', 'product-datasheet-autopilot' ), self::MIN_WOO );
		}
		return '';
	}

	/**
	 * Whether runtime hooks can safely be registered.
	 *
	 * @return bool
	 */
	public static function is_compatible() {
		return '' === self::reason();
	}
}
