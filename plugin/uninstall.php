<?php
/**
 * Uninstall Product Datasheet Autopilot settings only. PDFs remain merchant-owned.
 *
 * @package ProductDatasheetAutopilot
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'pda_settings' );
delete_option( 'pda_incompatible' );
delete_option( 'pda_rewrite_flush_pending' );
delete_option( 'pda_generated_product_ids' );
delete_option( 'pda_telemetry' );
delete_option( 'pda_install_secret' );
delete_option( 'pda_entitlement' );
