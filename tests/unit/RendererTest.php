<?php
declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

final class RendererTest extends TestCase {
	public function test_renders_local_pdf_without_an_image(): void {
		$snapshot = PDA_Product_Snapshot::normalize( array(
			'product_id' => 5, 'title' => 'Pressure Gauge', 'branding_name' => 'Example Store', 'fields' => array(
				array( 'id' => 'core_sku', 'label' => 'SKU', 'value' => 'GAUGE-1' ),
				array( 'id' => 'attr_pressure', 'label' => 'Pressure Rating', 'value' => '12 bar' ),
			),
		) );
		$map = ( new PDA_Deterministic_Mapper() )->map( $snapshot );
		$pdf = ( new PDA_PDF_Renderer() )->render( $snapshot, $map );
		self::assertStringStartsWith( '%PDF', $pdf );
		self::assertStringContainsString( 'NotoSans', $pdf );
		self::assertLessThanOrEqual( 2, preg_match_all( '/\\/Type\\s*\\/Page(?!s)\\b/', $pdf ) );
	}

	public function test_control_characters_are_removed_before_pdf_commands(): void {
		$document = new PDA_FPDF_Document( 'Footer' );
		self::assertSame( 'a b', $document->pdf_text( "a\x00 b" ) );
	}

	public function test_standard_woocommerce_typography_is_transliterated(): void {
		$snapshot = PDA_Product_Snapshot::normalize( array(
			'product_id' => 5, 'title' => "Deluxe \u{2014} \u{201C}European\u{201D} model", 'fields' => array(
				array( 'id' => 'core_sku', 'label' => 'Price', 'value' => "\u{20AC}49.99 / \u{00A3}42.00" ),
			),
		) );
		$pdf      = ( new PDA_PDF_Renderer() )->render( $snapshot, ( new PDA_Deterministic_Mapper() )->map( $snapshot ) );
		self::assertStringStartsWith( '%PDF', $pdf );
	}

	public function test_fifty_short_fields_fit_the_two_page_contract(): void {
		$fields = array();
		for ( $index = 0; $index < 50; ++$index ) {
			$fields[] = array( 'id' => 'attr_' . $index, 'label' => 'Attribute ' . $index, 'value' => 'Value ' . $index );
		}
		$snapshot = PDA_Product_Snapshot::normalize( array( 'product_id' => 5, 'title' => 'Dense Product', 'fields' => $fields ) );
		$pdf = ( new PDA_PDF_Renderer() )->render( $snapshot, ( new PDA_Deterministic_Mapper() )->map( $snapshot ) );
		self::assertLessThanOrEqual( 2, preg_match_all( '/\\/Type\\s*\\/Page(?!s)\\b/', $pdf ) );
	}

	public function test_nonrepresentable_unicode_fails_without_changing_a_value(): void {
		$this->expectException( PDA_Render_Exception::class );
		$snapshot = PDA_Product_Snapshot::normalize( array( 'product_id' => 5, 'title' => '測試', 'fields' => array( array( 'id' => 'core_sku', 'label' => 'SKU', 'value' => 'A-1' ) ) ) );
		( new PDA_PDF_Renderer() )->render( $snapshot, ( new PDA_Deterministic_Mapper() )->map( $snapshot ) );
	}
}
