<?php
/**
 * Atomic, hash-addressed PDF publication.
 *
 * @package ProductDatasheetAutopilot
 */

defined( 'ABSPATH' ) || exit;

class PDA_File_Store_Exception extends RuntimeException {}

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
	 * @throws PDA_File_Store_Exception When a PDF cannot be safely published.
	 */
	public function publish( $product_id, $hash, $pdf ) {
		try {
			if ( ! preg_match( '/^[a-f0-9]{64}$/', $hash ) || ! is_string( $pdf ) || 0 !== strpos( $pdf, '%PDF' ) || strlen( $pdf ) <= 1024 || $this->page_count( $pdf ) > 2 ) {
				throw new PDA_File_Store_Exception( 'The rendered PDF failed validation before it could be saved.' );
			}
			$path      = $this->path( $product_id, $hash );
			$directory = dirname( $path );
			$mkdir     = $this->run_file_operation(
				static function() use ( $directory ) {
					return wp_mkdir_p( $directory );
				},
				$mkdir_error
			);
			if ( ! $mkdir ) {
				throw new PDA_File_Store_Exception( $this->operation_error( 'Unable to create PDF directory ' . $directory . '.', $mkdir_error ) );
			}
			$temp    = $path . '.' . wp_generate_password( 12, false, false ) . '.tmp';
			$written = $this->run_file_operation(
				static function() use ( $temp, $pdf ) {
					return file_put_contents( $temp, $pdf, LOCK_EX );
				},
				$write_error
			);
			if ( false === $written ) {
				throw new PDA_File_Store_Exception( $this->operation_error( 'Unable to write temporary PDF file ' . $temp . '.', $write_error ) );
			}
			if ( ! $this->is_valid_file( $temp ) ) {
				throw new PDA_File_Store_Exception( 'Temporary PDF file failed validation: ' . $temp . '.' );
			}
			$published = $this->run_file_operation(
				static function() use ( $temp, $path ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
					return rename( $temp, $path );
				},
				$rename_error
			);
			if ( ! $published ) {
				throw new PDA_File_Store_Exception( $this->operation_error( 'Unable to publish PDF file ' . $path . '.', $rename_error ) );
			}
			update_post_meta( $product_id, '_pda_pdf_hash', $hash );
			update_post_meta( $product_id, '_pda_pdf_generated_at', time() );
			delete_post_meta( $product_id, '_pda_pdf_stale' );
			return true;
		} catch ( PDA_File_Store_Exception $exception ) {
			$this->cleanup_temp_file( isset( $temp ) ? $temp : '' );
			throw $exception;
		} catch ( Throwable $exception ) {
			$this->cleanup_temp_file( isset( $temp ) ? $temp : '' );
			$message = trim( $exception->getMessage() );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- This exact exception detail is passed to a capability-gated admin notice, not output here.
			throw new PDA_File_Store_Exception( 'PDF publication failed: ' . ( '' !== $message ? $message : get_class( $exception ) ), 0, $exception );
		}
	}

	/**
	 * Execute an operation that may otherwise emit an unhelpful PHP warning.
	 *
	 * @param callable $operation Filesystem operation.
	 * @param string   $error_message Captured PHP warning, if any.
	 * @return mixed
	 */
	private function run_file_operation( $operation, &$error_message ) {
		$error_message = '';
		set_error_handler(
			static function( $severity, $message ) use ( &$error_message ) {
				$error_message = (string) $message;
				return true;
			}
		);
		try {
			return call_user_func( $operation );
		} finally {
			restore_error_handler();
		}
	}

	/**
	 * @param string $fallback Error fallback.
	 * @param string $captured Captured PHP warning.
	 * @return string
	 */
	private function operation_error( $fallback, $captured ) {
		return '' !== $captured ? $captured : $fallback . ' Check filesystem permissions and available disk space.';
	}

	/**
	 * @param string $temp Temporary path.
	 * @return void
	 */
	private function cleanup_temp_file( $temp ) {
		if ( '' === $temp || ! file_exists( $temp ) ) {
			return;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		unlink( $temp );
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
