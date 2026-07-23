<?php
/**
 * Atomic, hash-addressed PDF publication.
 *
 * @package ProductDatasheetAutopilot
 */

defined( 'ABSPATH' ) || exit;

class PDA_File_Store {
	/** @var PDA_Telemetry */
	private $telemetry;

	/**
	 * @param PDA_Telemetry $telemetry Events.
	 */
	public function __construct( PDA_Telemetry $telemetry ) {
		$this->telemetry = $telemetry;
	}

	/**
	 * @param int    $product_id Product ID.
	 * @param string $hash SHA-256 content hash.
	 * @return bool
	 */
	public function is_current( $product_id, $hash ) {
		return $hash === (string) get_post_meta( $product_id, '_pda_pdf_hash', true ) && ! (bool) get_post_meta( $product_id, '_pda_pdf_stale', true ) && $this->is_valid_file( $this->path( $product_id, $hash ) );
	}

	/**
	 * Publish a fully rendered PDF only after validating it.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $hash SHA-256 content hash.
	 * @param string $pdf PDF data.
	 * @return bool
	 */
	public function publish( $product_id, $hash, $pdf ) {
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $hash ) || ! is_string( $pdf ) || 0 !== strpos( $pdf, '%PDF' ) || strlen( $pdf ) <= 1024 || $this->page_count( $pdf ) > 2 ) {
			return false;
		}
		$path = $this->path( $product_id, $hash );
		if ( ! wp_mkdir_p( dirname( $path ) ) ) {
			return false;
		}
		$temp = $path . '.' . wp_generate_password( 12, false, false ) . '.tmp';
		if ( false === file_put_contents( $temp, $pdf, LOCK_EX ) ) {
			return false;
		}
		if ( ! $this->is_valid_file( $temp ) || ! @rename( $temp, $path ) ) {
			@unlink( $temp );
			return false;
		}
		update_post_meta( $product_id, '_pda_pdf_hash', $hash );
		update_post_meta( $product_id, '_pda_pdf_generated_at', time() );
		delete_post_meta( $product_id, '_pda_pdf_stale' );
		return true;
	}

	/**
	 * @param int $product_id Product ID.
	 * @return array{hash:string,path:string}|null
	 */
	public function current( $product_id ) {
		$hash = (string) get_post_meta( $product_id, '_pda_pdf_hash', true );
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $hash ) || (bool) get_post_meta( $product_id, '_pda_pdf_stale', true ) || ! $this->is_valid_file( $this->path( $product_id, $hash ) ) ) {
			return null;
		}
		return array( 'hash' => $hash, 'path' => $this->path( $product_id, $hash ) );
	}

	/**
	 * @param int $product_id Product ID.
	 * @return void
	 */
	public function mark_stale( $product_id ) {
		update_post_meta( $product_id, '_pda_pdf_stale', 1 );
	}

	/**
	 * @param int    $product_id Product ID.
	 * @param string $hash Hash.
	 * @return string
	 */
	public function path( $product_id, $hash ) {
		$uploads = wp_upload_dir();
		return trailingslashit( $uploads['basedir'] ) . 'product-datasheet-autopilot/' . absint( $product_id ) . '/' . sanitize_file_name( $hash ) . '.pdf';
	}

	/**
	 * @param string $path Local PDF path.
	 * @return bool
	 */
	private function is_valid_file( $path ) {
		if ( ! is_readable( $path ) || filesize( $path ) <= 1024 ) {
			return false;
		}
		$header = file_get_contents( $path, false, null, 0, 4 );
		return '%PDF' === $header;
	}

	/**
	 * @param string $pdf PDF bytes.
	 * @return int
	 */
	private function page_count( $pdf ) {
		return preg_match_all( '/\\/Type\\s*\\/Page(?!s)\\b/', $pdf, $unused );
	}
}
