<?php
/**
 * Settings and explicit consent state.
 *
 * @package ProductDatasheetAutopilot
 */

defined( 'ABSPATH' ) || exit;

class PDA_Settings {
	/** @var string */
	const OPTION = 'pda_settings';

	/**
	 * Defaults intentionally keep every networked capability off.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'consent'            => false,
			'ai_opt_in'          => false,
			'analytics_opt_in'   => false,
			'download_button'    => true,
			'selected_meta_keys' => array(),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	/**
	 * @param string $key Setting key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$settings = self::all();
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * AI needs both a general consent acknowledgement and its separate opt-in.
	 *
	 * @return bool
	 */
	public static function ai_is_consented() {
		return (bool) self::get( 'consent', false ) && (bool) self::get( 'ai_opt_in', false );
	}

	/**
	 * Anonymous operational counters require their own explicit opt-in. They are
	 * never collected merely because the plugin has been activated.
	 *
	 * @return bool
	 */
	public static function telemetry_is_consented() {
		return (bool) self::get( 'analytics_opt_in', false );
	}

	/**
	 * Sanitize the registered option without accepting arbitrary keys.
	 *
	 * @param mixed $value Submitted value.
	 * @return array<string,mixed>
	 */
	public static function sanitize( $value ) {
		$value = is_array( $value ) ? $value : array();
		$meta  = isset( $value['selected_meta_keys'] ) && is_array( $value['selected_meta_keys'] ) ? $value['selected_meta_keys'] : array();
		$meta  = array_values( array_unique( array_filter( array_map( 'sanitize_key', $meta ) ) ) );
		return array(
			'consent'            => ! empty( $value['consent'] ),
			'ai_opt_in'          => ! empty( $value['consent'] ) && ! empty( $value['ai_opt_in'] ),
			'analytics_opt_in'   => ! empty( $value['analytics_opt_in'] ),
			'download_button'    => ! empty( $value['download_button'] ),
			'selected_meta_keys' => array_slice( $meta, 0, 20 ),
		);
	}
}
