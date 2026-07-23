<?php
/**
 * Concise WooCommerce admin surface.
 *
 * @package ProductDatasheetAutopilot
 */

defined( 'ABSPATH' ) || exit;

class PDA_Admin_Page {
	/** @var PDA_Job_Runner */ private $runner;
	/** @var PDA_Telemetry */ private $telemetry;

	/**
	 * @param PDA_Job_Runner $runner Generator.
	 * @param PDA_Telemetry  $telemetry Events.
	 */
	public function __construct( PDA_Job_Runner $runner, PDA_Telemetry $telemetry ) {
		$this->runner    = $runner;
		$this->telemetry = $telemetry;
	}

	/** @return void */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'settings' ) );
		add_action( 'admin_post_pda_generate_preview', array( $this, 'generate_preview' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/** @return void */
	public function menu() {
		add_submenu_page( 'woocommerce', __( 'Datasheet Autopilot', 'product-datasheet-autopilot' ), __( 'Datasheets', 'product-datasheet-autopilot' ), 'manage_woocommerce', 'product-datasheet-autopilot', array( $this, 'page' ) );
	}

	/** @return void */
	public function settings() {
		register_setting( 'pda_settings', PDA_Settings::OPTION, array( 'sanitize_callback' => array( 'PDA_Settings', 'sanitize' ) ) );
	}

	/** @return void */
	public function assets() {
		$screen = get_current_screen();
		if ( ! $screen || 'woocommerce_page_product-datasheet-autopilot' !== $screen->id ) {
			return;
		}
		wp_enqueue_style( 'pda-admin', PDA_URL . 'assets/admin.css', array(), PDA_VERSION );
		wp_enqueue_script( 'pda-admin', PDA_URL . 'assets/admin.js', array(), PDA_VERSION, true );
	}

	/** @return void */
	public function page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage datasheets.', 'product-datasheet-autopilot' ) );
		}
		$settings = PDA_Settings::all();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a sanitized fixed status code set by the nonce-protected POST redirect below.
		$status   = isset( $_GET['pda_status'] ) ? sanitize_key( wp_unslash( $_GET['pda_status'] ) ) : '';
		?>
		<div class="wrap pda-admin">
			<h1><?php esc_html_e( 'Product Datasheet Autopilot', 'product-datasheet-autopilot' ); ?></h1>
			<?php if ( $status ) : ?>
				<div class="notice <?php echo 'success' === $status ? 'notice-success' : 'notice-error'; ?>"><p><?php echo esc_html( $this->status_message( $status ) ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'Generate a truthful, fixed-layout PDF from existing WooCommerce product data.', 'product-datasheet-autopilot' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'pda_generate_preview' ); ?>
				<input type="hidden" name="action" value="pda_generate_preview" />
				<?php submit_button( __( 'Generate preview for 3 recent products', 'product-datasheet-autopilot' ), 'primary', 'submit', false ); ?>
			</form>
			<hr />
			<form method="post" action="options.php">
				<?php settings_fields( 'pda_settings' ); ?>
				<h2><?php esc_html_e( 'Privacy and downloads', 'product-datasheet-autopilot' ); ?></h2>
				<label><input type="checkbox" name="pda_settings[download_button]" value="1" <?php checked( $settings['download_button'] ); ?> /> <?php esc_html_e( 'Show a datasheet download button on current product pages.', 'product-datasheet-autopilot' ); ?></label>
				<p><label><input type="checkbox" name="pda_settings[consent]" value="1" <?php checked( $settings['consent'] ); ?> /> <?php esc_html_e( 'I understand that enabling optional AI sends this product snapshot to the configured AI gateway for field organization only.', 'product-datasheet-autopilot' ); ?></label></p>
				<p><label><input type="checkbox" name="pda_settings[ai_opt_in]" value="1" <?php checked( $settings['ai_opt_in'] ); ?> /> <?php esc_html_e( 'Enable AI-assisted section organization (Pro only, off by default). AI never writes product values.', 'product-datasheet-autopilot' ); ?></label></p>
				<p><label><input type="checkbox" name="pda_settings[analytics_opt_in]" value="1" <?php checked( $settings['analytics_opt_in'] ); ?> /> <?php esc_html_e( 'Allow anonymous local generation counters.', 'product-datasheet-autopilot' ); ?></label></p>
				<?php submit_button(); ?>
			</form>
			<?php if ( ! apply_filters( 'pda_is_pro', false ) ) : ?>
				<div class="pda-upgrade"><h2><?php esc_html_e( 'Product Datasheet Autopilot Pro — $59/year', 'product-datasheet-autopilot' ); ?></h2><p><?php esc_html_e( 'One production site, unlimited products, automatic regeneration, nightly stale audit, bulk backfill, and 1,000 optional AI remaps per license per month.', 'product-datasheet-autopilot' ); ?></p><p><?php esc_html_e( 'Free stays permanent: three manually generated products, a fixed local layout, and no external API.', 'product-datasheet-autopilot' ); ?></p></div>
			<?php endif; ?>
		</div>
		<?php
	}

	/** @return void */
	public function generate_preview() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to generate datasheets.', 'product-datasheet-autopilot' ) );
		}
		check_admin_referer( 'pda_generate_preview' );
		$products = wc_get_products( array( 'status' => 'publish', 'limit' => 3, 'orderby' => 'modified', 'order' => 'DESC', 'return' => 'objects' ) );
		$status   = 'success';
		foreach ( $products as $product ) {
			$result = $this->runner->generate( $product->get_id(), 'admin_preview' );
			if ( empty( $result['success'] ) ) {
				$status = sanitize_key( (string) $result['error'] );
				break;
			}
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'product-datasheet-autopilot', 'pda_status' => $status ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * @param string $status Status key.
	 * @return string
	 */
	private function status_message( $status ) {
		$messages = array(
			'success'            => __( 'Datasheet preview generated.', 'product-datasheet-autopilot' ),
			'free_product_limit' => __( 'The free edition can generate up to three distinct products. Upgrade to Pro for unlimited products.', 'product-datasheet-autopilot' ),
			'field_limit'        => __( 'This product has more than 50 eligible fields. Reduce visible attributes or selected custom fields.', 'product-datasheet-autopilot' ),
			'document_overflow'  => __( 'This product cannot fit in the two-page fixed layout without omitting too many fields.', 'product-datasheet-autopilot' ),
		);
		return $messages[ $status ] ?? __( 'The datasheet could not be generated. Check WooCommerce → Status → Datasheet Autopilot for a redacted diagnostic code.', 'product-datasheet-autopilot' );
	}
}
