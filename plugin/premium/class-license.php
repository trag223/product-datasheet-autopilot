<?php
/**
 * Pro entitlement cache and encrypted gateway install secret.
 *
 * @package ProductDatasheetAutopilot
 */

defined( 'ABSPATH' ) || exit;

class PDA_License {
	/** @var string */ const OPTION = 'pda_entitlement';
	/** @var string */ const SECRET_OPTION = 'pda_install_secret';
	/** @var string */ const INSTALL_OPTION = 'pda_install_id';
	/** @var int */ const GRACE_SECONDS = 604800;
	/** @var PDA_AI_Client */ private $ai;

	/**
	 * @param PDA_AI_Client $ai Gateway client.
	 */
	public function __construct( PDA_AI_Client $ai ) {
		$this->ai = $ai;
	}

	/** @return void */
	public function register() {
		add_filter( 'pda_is_pro', array( $this, 'is_active' ) );
		add_filter( 'pda_ai_available', array( $this, 'ai_available' ) );
		add_filter( 'pda_install_secret', array( $this, 'install_secret' ) );
		add_filter( 'pda_license_id', array( $this, 'license_id' ) );
		add_filter( 'pda_install_id', array( $this, 'install_id' ) );
		add_action( 'admin_init', array( $this, 'maybe_register_installation' ) );
	}

	/**
	 * A verified entitlement is active through its paid-through date. A temporary
	 * Freemius outage uses only the last verified state for a seven-day grace.
	 *
	 * @param bool $current Current filter value.
	 * @return bool
	 */
	public function is_active( $current = false ) {
		if ( $current ) {
			return true;
		}
		$entitlement = get_option( self::OPTION, array() );
		if ( ! is_array( $entitlement ) || empty( $entitlement['active'] ) || empty( $entitlement['license_id'] ) ) {
			return false;
		}
		$now       = time();
		$expires   = (int) ( $entitlement['expires_at'] ?? 0 );
		$verified  = (int) ( $entitlement['verified_at'] ?? 0 );
		return $expires > $now || ( $verified > 0 && $verified + self::GRACE_SECONDS > $now );
	}

	/**
	 * @param bool $current Current filter value.
	 * @return bool
	 */
	public function ai_available( $current = false ) {
		return $current || ( $this->is_active() && '' !== $this->install_secret() );
	}

	/**
	 * @return string
	 */
	public function license_id() {
		$entitlement = get_option( self::OPTION, array() );
		return is_array( $entitlement ) ? (string) ( $entitlement['license_id'] ?? '' ) : '';
	}

	/**
	 * @return string
	 */
	public function install_id() {
		$install = (string) get_option( self::INSTALL_OPTION, '' );
		if ( ! preg_match( '/^[a-f0-9-]{36}$/', $install ) ) {
			$install = wp_generate_uuid4();
			update_option( self::INSTALL_OPTION, $install, false );
		}
		return $install;
	}

	/**
	 * @param string $current Filter default.
	 * @return string
	 */
	public function install_secret( $current = '' ) {
		if ( '' !== $current ) {
			return $current;
		}
		$encrypted = (string) get_option( self::SECRET_OPTION, '' );
		if ( '' === $encrypted || ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}
		$parts = explode( '.', $encrypted );
		if ( 3 !== count( $parts ) ) {
			return '';
		}
		$iv  = base64_decode( $parts[0], true );
		$tag = base64_decode( $parts[1], true );
		$data = base64_decode( $parts[2], true );
		if ( false === $iv || false === $tag || false === $data ) {
			return '';
		}
		$secret = openssl_decrypt( $data, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $iv, $tag );
		return is_string( $secret ) && preg_match( '/^[a-f0-9]{64}$/', $secret ) ? $secret : '';
	}

	/**
	 * @return void
	 */
	public function maybe_register_installation() {
		if ( ! PDA_Settings::ai_is_consented() || ! $this->is_active() || '' !== $this->install_secret() ) {
			return;
		}
		$secret = $this->ai->register( $this->license_id(), $this->install_id(), hash( 'sha256', home_url() ) );
		if ( $secret ) {
			$this->save_install_secret( $secret );
		}
	}

	/**
	 * Called only from a verified Freemius SDK/webhook refresh path. Refunds and
	 * chargebacks set active false and immediately disable AI and automation.
	 *
	 * @param array<string,mixed> $entitlement Verified entitlement.
	 * @return void
	 */
	public function update_entitlement( array $entitlement ) {
		$record = array(
			'license_id'  => sanitize_text_field( (string) ( $entitlement['license_id'] ?? '' ) ),
			'active'      => ! empty( $entitlement['active'] ),
			'expires_at'  => absint( $entitlement['expires_at'] ?? 0 ),
			'verified_at' => time(),
		);
		update_option( self::OPTION, $record, false );
		if ( ! $record['active'] ) {
			delete_option( self::SECRET_OPTION );
		}
	}

	/**
	 * @param string $secret Gateway secret.
	 * @return void
	 */
	private function save_install_secret( $secret ) {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return;
		}
		$iv  = random_bytes( 12 );
		$tag = '';
		$data = openssl_encrypt( $secret, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $iv, $tag );
		if ( false !== $data ) {
			update_option( self::SECRET_OPTION, base64_encode( $iv ) . '.' . base64_encode( $tag ) . '.' . base64_encode( $data ), false );
		}
	}

	/**
	 * @return string
	 */
	private function key() {
		return hash( 'sha256', wp_salt( 'auth' ), true );
	}
}
