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
	 * Return an unlocalized incompatibility code, or an empty string.
	 *
	 * This is safe to evaluate on plugins_loaded before translations are available.
	 *
	 * @return string
	 */
	public static function error_code() {
		global $wp_version;
		if ( version_compare( PHP_VERSION, self::MIN_PHP, '<' ) ) {
			return 'php_version';
		}
		if ( isset( $wp_version ) && version_compare( (string) $wp_version, self::MIN_WP, '<' ) ) {
			return 'wordpress_version';
		}
		if ( ! defined( 'WC_VERSION' ) ) {
			return 'woocommerce_missing';
		}
		if ( version_compare( WC_VERSION, self::MIN_WOO, '<' ) ) {
			return 'woocommerce_version';
		}
		return '';
	}

	/**
	 * Return a translatable incompatibility reason, or an empty string.
	 *
	 * Call at init or later, once WordPress can safely load translations.
	 *
	 * @return string
	 */
	public static function reason() {
		switch ( self::error_code() ) {
			case 'php_version':
				return sprintf( __( 'Product Datasheet Autopilot requires PHP %s or newer.', 'product-datasheet-autopilot' ), self::MIN_PHP );
			case 'wordpress_version':
				return sprintf( __( 'Product Datasheet Autopilot requires WordPress %s or newer.', 'product-datasheet-autopilot' ), self::MIN_WP );
			case 'woocommerce_missing':
				return __( 'Product Datasheet Autopilot requires WooCommerce to be active.', 'product-datasheet-autopilot' );
			case 'woocommerce_version':
				return sprintf( __( 'Product Datasheet Autopilot requires WooCommerce %s or newer.', 'product-datasheet-autopilot' ), self::MIN_WOO );
			default:
				return '';
		}
	}

	/**
	 * Whether runtime hooks can safely be registered.
	 *
	 * @return bool
	 */
	public static function is_compatible() {
		return '' === self::error_code();
	}
}
