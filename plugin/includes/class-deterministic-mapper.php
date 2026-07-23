<?php
/**
 * Local field sectioning with no outbound dependency.
 *
 * @package ProductDatasheetAutopilot
 */

defined( 'ABSPATH' ) || exit;

class PDA_Deterministic_Mapper {
	/** @var array<string,array<int,string>> */
	private $rules = array(
		'dimensions'    => array( '/dimension/i', '/\blength\b/i', '/\bwidth\b/i', '/\bheight\b/i', '/\bdepth\b/i', '/\bweight\b/i', '/\bsize\b/i' ),
		'materials'     => array( '/material/i', '/finish/i', '/color/i', '/colour/i', '/fabric/i', '/alloy/i' ),
		'performance'   => array( '/voltage/i', '/watt/i', '/power/i', '/capacity/i', '/speed/i', '/pressure/i', '/temperature/i', '/performance/i', '/rating/i' ),
		'compatibility' => array( '/compatib/i', '/fits?/i', '/model/i', '/application/i' ),
		'package'       => array( '/package/i', '/included/i', '/contents?/i', '/in the box/i', '/quantity/i' ),
		'compliance'    => array( '/certif/i', '/compliance/i', '/\bce\b/i', '/\bul\b/i', '/rohs/i', '/standard/i', '/ip[ -]?rating/i' ),
	);

	/**
	 * @param array<string,mixed> $snapshot Normalized snapshot.
	 * @return array<string,mixed>
	 */
	public function map( array $snapshot ) {
		$sections = array();
		foreach ( array_keys( pda_sections() ) as $id ) {
			$sections[ $id ] = array();
		}
		foreach ( $snapshot['fields'] as $field ) {
			$section                       = $this->section_for( (string) $field['label'] );
			$sections[ $section ][]        = (string) $field['id'];
		}
		$output = array();
		foreach ( $sections as $id => $field_ids ) {
			$output[] = array( 'section_id' => $id, 'field_ids' => $field_ids );
		}
		return array(
			'layout_version' => PDA_TEMPLATE_VERSION,
			'sections'       => $output,
			'hero_field_ids' => array_slice( array_column( $snapshot['fields'], 'id' ), 0, 4 ),
			'warnings'       => array(),
		);
	}

	/**
	 * @param string $label Field label.
	 * @return string
	 */
	private function section_for( $label ) {
		if ( preg_match( '/\b(sku|product name|name)\b/i', $label ) ) {
			return 'identity';
		}
		foreach ( $this->rules as $section => $patterns ) {
			foreach ( $patterns as $pattern ) {
				if ( preg_match( $pattern, $label ) ) {
					return $section;
				}
			}
		}
		return 'other';
	}
}
