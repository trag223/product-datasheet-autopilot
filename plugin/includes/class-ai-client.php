<?php
/**
 * Optional, consent-gated gateway client.
 *
 * @package ProductDatasheetAutopilot
 */

defined( 'ABSPATH' ) || exit;

class PDA_AI_Client {
	/** @var PDA_Telemetry */
	private $telemetry;

	/**
	 * @param PDA_Telemetry $telemetry Redacted events.
	 */
	public function __construct( PDA_Telemetry $telemetry ) {
		$this->telemetry = $telemetry;
	}

	/**
	 * Ask the gateway for a field-ID-only map. Null means local fallback.
	 *
	 * @param array<string,mixed> $snapshot Snapshot.
	 * @return array<string,mixed>|null
	 */
	public function map( array $snapshot ) {
		if ( ! PDA_Settings::ai_is_consented() || ! apply_filters( 'pda_ai_available', false ) ) {
			return null;
		}
		$secret  = (string) apply_filters( 'pda_install_secret', '' );
		$license = (string) apply_filters( 'pda_license_id', '' );
		$install = (string) apply_filters( 'pda_install_id', '' );
		if ( '' === $secret || '' === $license || '' === $install || 0 !== strpos( PDA_GATEWAY_URL, 'https://' ) ) {
			return null;
		}
		$body      = wp_json_encode( array( 'layout_version' => PDA_TEMPLATE_VERSION, 'snapshot' => $snapshot ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$timestamp = (string) time();
		$nonce     = wp_generate_uuid4();
		$signature = hash_hmac( 'sha256', $timestamp . "\n" . $nonce . "\n" . hash( 'sha256', $body ), $secret );
		$response  = wp_remote_post(
			trailingslashit( PDA_GATEWAY_URL ) . 'v1/map',
			array(
				'timeout'     => 10,
				'redirection'  => 0,
				'headers'     => array(
					'Content-Type'    => 'application/json',
					'X-PDA-License'   => $license,
					'X-PDA-Install'   => $install,
					'X-PDA-Timestamp' => $timestamp,
					'X-PDA-Nonce'     => $nonce,
					'X-PDA-Signature' => $signature,
				),
				'body'        => $body,
				'data_format' => 'body',
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			$http_status = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
			$this->telemetry->record( 'ai_fallback', array( 'http_status' => $http_status ) );
			return null;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $data ) && isset( $data['map'] ) && is_array( $data['map'] ) ? $data['map'] : null;
	}

	/**
	 * Register a paid installation only after explicit AI consent.
	 *
	 * @param string $license License ID.
	 * @param string $install Install ID.
	 * @param string $site_hash One-way site hash.
	 * @return string|null Install secret.
	 */
	public function register( $license, $install, $site_hash ) {
		if ( ! PDA_Settings::ai_is_consented() || 0 !== strpos( PDA_GATEWAY_URL, 'https://' ) ) {
			return null;
		}
		$response = wp_remote_post(
			trailingslashit( PDA_GATEWAY_URL ) . 'v1/install/register',
			array(
				'timeout'    => 10,
				'redirection' => 0,
				'headers'    => array( 'Content-Type' => 'application/json' ),
				'body'       => wp_json_encode( array( 'license_id' => $license, 'install_id' => $install, 'site_hash' => $site_hash ) ),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $data ) && isset( $data['install_secret'] ) && preg_match( '/^[a-f0-9]{64}$/', $data['install_secret'] ) ? $data['install_secret'] : null;
	}
}
