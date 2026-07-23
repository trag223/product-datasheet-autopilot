<?php
/**
 * Pro feature composition. This directory is excluded from the free ZIP.
 *
 * @package ProductDatasheetAutopilot
 */

defined( 'ABSPATH' ) || exit;

class PDA_Premium_Bootstrap {
	/**
	 * @param PDA_Job_Runner $runner Generator.
	 * @param PDA_File_Store $store Store.
	 * @param PDA_Telemetry  $telemetry Events.
	 * @param PDA_AI_Client  $ai AI client.
	 * @return void
	 */
	public static function init( PDA_Job_Runner $runner, PDA_File_Store $store, PDA_Telemetry $telemetry, PDA_AI_Client $ai ) {
		foreach ( array( 'class-license.php', 'class-auto-regeneration.php', 'class-bulk-backfill.php', 'class-nightly-audit.php' ) as $file ) {
			require_once __DIR__ . '/' . $file;
		}
		$license = new PDA_License( $ai );
		$license->register();
		( new PDA_Auto_Regeneration( $store ) )->register();
		( new PDA_Bulk_Backfill( $runner ) )->register();
		( new PDA_Nightly_Audit( $store, $telemetry ) )->register();
		add_action( 'admin_init', array( __CLASS__, 'refresh_freemius_entitlement' ) );
		add_action( 'admin_post_pda_checkout', array( __CLASS__, 'checkout' ) );
	}

	/**
	 * Initialise the Freemius SDK only after the merchant explicitly consents.
	 * The SDK is optional during development; production builds package it.
	 *
	 * @return object|null
	 */
	public static function freemius() {
		static $sdk = null;
		if ( null !== $sdk || ! PDA_Settings::get( 'consent', false ) ) {
			return $sdk;
		}
		$sdk_file = PDA_DIR . 'vendor/freemius/start.php';
		if ( ! function_exists( 'fs_dynamic_init' ) && file_exists( $sdk_file ) ) {
			require_once $sdk_file;
		}
		$product_id = (int) getenv( 'FREEMIUS_PRODUCT_ID' );
		$public_key = (string) getenv( 'FREEMIUS_API_PUBLIC_KEY' );
		if ( ! function_exists( 'fs_dynamic_init' ) || ! $product_id || '' === $public_key ) {
			return null;
		}
		$sdk = fs_dynamic_init(
			array(
				'id'             => $product_id,
				'slug'           => 'product-datasheet-autopilot',
				'type'           => 'plugin',
				'public_key'     => $public_key,
				'is_premium'     => true,
				'has_paid_plans' => true,
				'menu'           => array( 'slug' => 'product-datasheet-autopilot', 'support' => false ),
			)
		);
		return $sdk;
	}

	/**
	 * Refresh from the local Freemius SDK cache. The gateway webhook remains the
	 * authoritative upstream revocation path; no browser webhook endpoint exists.
	 *
	 * @return void
	 */
	public static function refresh_freemius_entitlement() {
		$sdk = self::freemius();
		if ( ! $sdk || ! method_exists( $sdk, 'is_paying' ) ) {
			return;
		}
		$license = method_exists( $sdk, 'get_license' ) ? $sdk->get_license() : null;
		if ( ! is_object( $license ) || empty( $license->id ) ) {
			return;
		}
		$expires = isset( $license->expiration ) ? strtotime( (string) $license->expiration ) : 0;
		$record  = new PDA_License( new PDA_AI_Client( new PDA_Telemetry() ) );
		$record->update_entitlement( array( 'license_id' => $license->id, 'active' => (bool) $sdk->is_paying(), 'expires_at' => $expires ?: 0 ) );
	}

	/** @return void */
	public static function checkout() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to upgrade.', 'product-datasheet-autopilot' ) );
		}
		check_admin_referer( 'pda_checkout' );
		$sdk = self::freemius();
		if ( $sdk && method_exists( $sdk, 'get_upgrade_url' ) ) {
			wp_safe_redirect( $sdk->get_upgrade_url() );
			exit;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=product-datasheet-autopilot&pda_status=checkout_unavailable' ) );
		exit;
	}
}
