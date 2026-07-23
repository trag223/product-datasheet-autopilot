<?php
/**
 * Fixed A4 PDF renderer. It receives original values and never AI-generated text.
 *
 * @package ProductDatasheetAutopilot
 */

defined( 'ABSPATH' ) || exit;

class PDA_Render_Exception extends RuntimeException {}

if ( class_exists( '\\Fpdf\\Fpdf' ) ) {
class PDA_FPDF_Document extends \Fpdf\Fpdf {
	/** @var string */
	private $footer_text;
	/** @var string */
	private $font_family = 'Helvetica';

	/**
	 * @param string $footer_text Mandatory footer.
	 */
	public function __construct( $footer_text ) {
		parent::__construct( 'P', 'mm', 'A4' );
		$this->footer_text = $footer_text;
		$this->SetMargins( 14, 14, 14 );
		$this->SetAutoPageBreak( true, 18 );
	}

	/**
	 * @return void
	 */
	public function Footer() {
		$this->SetY( -14 );
		$this->SetFont( $this->font_family, '', 7 );
		$this->SetTextColor( 80, 80, 80 );
		$this->MultiCell( 0, 3.2, $this->pdf_text( $this->footer_text ), 0, 'C' );
	}

	/**
	 * Load the bundled Noto Sans definition files generated at package time.
	 * Source checkouts retain a core-font fallback until they are built.
	 *
	 * @param string $directory Font directory.
	 * @return void
	 */
	public function enable_noto_fonts( $directory ) {
		if ( ! file_exists( $directory . '/NotoSans-Regular.json' ) || ! file_exists( $directory . '/NotoSans-Bold.json' ) ) {
			return;
		}
		try {
			$this->AddFont( 'Noto', '', 'NotoSans-Regular.json', $directory );
			$this->AddFont( 'Noto', 'B', 'NotoSans-Bold.json', $directory );
			$this->font_family = 'Noto';
		} catch ( Throwable $exception ) {
			$this->font_family = 'Helvetica';
		}
	}

	/**
	 * @return string
	 */
	public function font_family() {
		return $this->font_family;
	}

	/**
	 * The bundled FPDF implementation uses WinAnsi content streams. Control
	 * characters are removed and common typography is already normalized by the
	 * renderer before it reaches this last encoding boundary.
	 *
	 * @param string $text Store text.
	 * @return string
	 */
	public function pdf_text( $text ) {
		$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', (string) $text );
		if ( function_exists( 'mb_convert_encoding' ) ) {
			try {
				$converted = mb_convert_encoding( $text, 'Windows-1252', 'UTF-8' );
			} catch ( Throwable $exception ) {
				$converted = false;
			}
			if ( false !== $converted ) {
				return $converted;
			}
		}
		return $text;
	}
}
}

class PDA_PDF_Renderer {
	/** @var int */
	const MAX_PAGES = 2;
	/** @var int */
	const MAX_OMITTED_FIELDS = 10;

	/**
	 * Render the fixed layout to a PDF string.
	 *
	 * @param array<string,mixed> $snapshot Snapshot.
	 * @param array<string,mixed> $map Validated field map.
	 * @return string
	 * @throws PDA_Render_Exception On unsafe or oversized documents.
	 */
	public function render( array $snapshot, array $map ) {
		try {
			return $this->render_document( $snapshot, $map );
		} catch ( PDA_Render_Exception $exception ) {
			throw $exception;
		} catch ( Throwable $exception ) {
			$message = trim( $exception->getMessage() );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- This exact exception detail is passed to a capability-gated admin notice, not output here.
			throw new PDA_Render_Exception( 'FPDF rendering failed: ' . ( '' !== $message ? $message : get_class( $exception ) ), 0, $exception );
		}
	}

	/**
	 * Render the fixed layout once the FPDF runtime is available.
	 *
	 * @param array<string,mixed> $snapshot Snapshot.
	 * @param array<string,mixed> $map Validated field map.
	 * @return string
	 * @throws PDA_Render_Exception On unsafe or oversized documents.
	 */
	private function render_document( array $snapshot, array $map ) {
		if ( ! class_exists( '\\Fpdf\\Fpdf' ) || ! class_exists( 'PDA_FPDF_Document' ) ) {
			throw new PDA_Render_Exception( 'FPDF library is missing. Reinstall the plugin package so vendor/autoload.php is present.' );
		}
		$snapshot = $this->normalize_snapshot_text( $snapshot );
		$ordered = $this->ordered_fields( $snapshot, $map );
		$omitted = $this->fields_to_omit( $ordered, ! empty( $snapshot['image_attachment_id'] ) );
		if ( $omitted > self::MAX_OMITTED_FIELDS ) {
			throw new PDA_Render_Exception( 'document_overflow' );
		}
		if ( $omitted ) {
			$ordered = array_slice( $ordered, 0, count( $ordered ) - $omitted );
		}
		$footer = sprintf(
			/* translators: %s: generation date. */
			__( "Generated automatically from this store's product data on %s. Verify specifications with the seller before purchasing or relying on this document.", 'product-datasheet-autopilot' ),
			gmdate( 'Y-m-d' )
		);
		$pdf = new PDA_FPDF_Document( $footer );
		$pdf->enable_noto_fonts( PDA_DIR . 'fonts' );
		$pdf->SetTitle( $snapshot['title'], true );
		$pdf->SetCreator( 'Product Datasheet Autopilot', true );
		$pdf->AddPage();
		$this->header( $pdf, $snapshot );
		$current_section = '';
		foreach ( $ordered as $item ) {
			if ( $item['section_id'] !== $current_section ) {
				$current_section = $item['section_id'];
				$pdf->Ln( 1 );
				$pdf->SetFont( $pdf->font_family(), 'B', 10 );
				$pdf->SetTextColor( 25, 58, 92 );
				$pdf->Cell( 0, 6, $pdf->pdf_text( pda_sections()[ $current_section ] ), 0, 1 );
			}
			$pdf->SetFont( 'Arial', '', 12 );
			$pdf->SetTextColor( 0, 0, 0 );
			$pdf->Cell( 48, 5, $pdf->pdf_text( $item['label'] ), 0, 0 );
			$x = $pdf->GetX();
			$y = $pdf->GetY();
			$pdf->MultiCell( 124, 5, $pdf->pdf_text( $item['value'] ), 0, 'L' );
			if ( $pdf->GetY() <= $y ) {
				$pdf->SetXY( $x, $y + 5 );
			}
		}
		if ( $pdf->PageNo() > self::MAX_PAGES ) {
			throw new PDA_Render_Exception( 'document_overflow' );
		}
		$data = $pdf->Output( 'S' );
		if ( ! is_string( $data ) || 0 !== strpos( $data, '%PDF' ) ) {
			throw new PDA_Render_Exception( 'FPDF produced invalid PDF output.' );
		}
		return $data;
	}

	/**
	 * @param PDA_FPDF_Document $pdf PDF.
	 * @param array<string,mixed> $snapshot Snapshot.
	 * @return void
	 */
	private function header( PDA_FPDF_Document $pdf, array $snapshot ) {
		$pdf->SetFont( $pdf->font_family(), 'B', 10 );
		$pdf->SetTextColor( 25, 58, 92 );
		$pdf->Cell( 0, 5, $pdf->pdf_text( $snapshot['branding_name'] ), 0, 1 );
		$pdf->SetFont( 'Arial', '', 12 );
		$pdf->SetTextColor( 0, 0, 0 );
		$pdf->MultiCell( 0, 8, $pdf->pdf_text( $snapshot['title'] ), 0, 'L' );
		$image = $this->image_path( (int) $snapshot['image_attachment_id'] );
		if ( $image ) {
			try {
				$pdf->Image( $image, 154, 14, 40, 40 );
			} catch ( Throwable $exception ) {
				// A bad image must not prevent the PDF from being generated.
			}
		}
		$pdf->SetDrawColor( 25, 58, 92 );
		$pdf->Line( 14, max( 42, $pdf->GetY() + 2 ), 196, max( 42, $pdf->GetY() + 2 ) );
		$pdf->Ln( 7 );
	}

	/**
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	private function image_path( $attachment_id ) {
		if ( ! $attachment_id || ! function_exists( 'get_attached_file' ) || 'attachment' !== get_post_type( $attachment_id ) ) {
			return '';
		}
		$path = get_attached_file( $attachment_id );
		if ( ! is_string( $path ) || ! is_readable( $path ) || ! @getimagesize( $path ) ) {
			return '';
		}
		return $path;
	}

	/**
	 * @param array<string,mixed> $snapshot Snapshot.
	 * @param array<string,mixed> $map Mapping.
	 * @return array<int,array<string,string>>
	 */
	private function ordered_fields( array $snapshot, array $map ) {
		$lookup = array();
		foreach ( $snapshot['fields'] as $field ) {
			$lookup[ $field['id'] ] = $field;
		}
		$ordered = array();
		foreach ( $map['sections'] as $section ) {
			foreach ( $section['field_ids'] as $field_id ) {
				if ( isset( $lookup[ $field_id ] ) ) {
					$ordered[] = array_merge( $lookup[ $field_id ], array( 'section_id' => $section['section_id'] ) );
				}
			}
		}
		return $ordered;
	}

	/**
	 * Calculate whether the fixed layout can fit without silently losing data.
	 *
	 * @param array<int,array<string,string>> $fields Fields.
	 * @param bool                             $has_image Whether image reserves header space.
	 * @return int Number of fields to omit from the tail.
	 */
	private function fields_to_omit( array $fields, $has_image ) {
		$capacity = $has_image ? 84 : 91;
		$used     = 9;
		$section  = '';
		foreach ( $fields as $index => $field ) {
			if ( $field['section_id'] !== $section ) {
				$section = $field['section_id'];
				$used   += 2;
			}
			$lines = max( 1, (int) ceil( strlen( $field['value'] ) / 72 ) );
			if ( $used + $lines > $capacity ) {
				return count( $fields ) - $index;
			}
			$used += $lines;
		}
		return 0;
	}

	/**
	 * Normalize commonly auto-formatted WooCommerce and WordPress text before
	 * preparing it for the WinAnsi FPDF output encoding.
	 *
	 * @param array<string,mixed> $snapshot Snapshot.
	 * @return array<string,mixed>
	 */
	private function normalize_snapshot_text( array $snapshot ) {
		$snapshot['title']         = $this->normalize_pdf_text( (string) $snapshot['title'] );
		$snapshot['branding_name'] = $this->normalize_pdf_text( (string) $snapshot['branding_name'] );
		foreach ( $snapshot['fields'] as $index => $field ) {
			$snapshot['fields'][ $index ]['label'] = $this->normalize_pdf_text( (string) $field['label'] );
			$snapshot['fields'][ $index ]['value'] = $this->normalize_pdf_text( (string) $field['value'] );
		}
		return $snapshot;
	}

	/**
	 * Keep ordinary store typography legible before FPDF transliterates it.
	 *
	 * @param string $text Store text.
	 * @return string UTF-8 text normalized for FPDF conversion.
	 */
	private function normalize_pdf_text( $text ) {
		$text = str_replace( array( "\r\n", "\r", "\t" ), array( "\n", "\n", '    ' ), $text );
		$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text );
		$text = strtr(
			$text,
			array(
				"\u{00A0}" => ' ',
				"\u{2018}" => "'",
				"\u{2019}" => "'",
				"\u{201A}" => "'",
				"\u{201B}" => "'",
				"\u{201C}" => '"',
				"\u{201D}" => '"',
				"\u{201E}" => '"',
				"\u{201F}" => '"',
				"\u{2010}" => '-',
				"\u{2011}" => '-',
				"\u{2013}" => '-',
				"\u{2014}" => '-',
				"\u{2212}" => '-',
				"\u{2026}" => '...',
				"\u{20AC}" => 'EUR',
				"\u{00A3}" => 'GBP',
				"\u{00A5}" => 'JPY',
			)
		);
		return $text;
	}
}
