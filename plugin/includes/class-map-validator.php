<?php
/**
 * Reject, never repair, AI field-ID maps.
 *
 * @package ProductDatasheetAutopilot
 */

defined( 'ABSPATH' ) || exit;

class PDA_Map_Validation_Exception extends RuntimeException {}

class PDA_Map_Validator {
	/**
	 * Validate every strict mapping constraint.
	 *
	 * @param array<string,mixed> $map AI or local map.
	 * @param array<string,mixed> $snapshot Input snapshot.
	 * @return array<string,mixed>
	 * @throws PDA_Map_Validation_Exception When the map cannot be safely rendered.
	 */
	public function assert_valid( array $map, array $snapshot ) {
		if ( PDA_TEMPLATE_VERSION !== (string) ( $map['layout_version'] ?? '' ) || ! isset( $map['sections'] ) || ! is_array( $map['sections'] ) ) {
			throw new PDA_Map_Validation_Exception( 'invalid_layout' );
		}
		$input_ids = array_map( 'strval', array_column( $snapshot['fields'], 'id' ) );
		$seen      = array();
		$allowed   = array_keys( pda_sections() );
		foreach ( $map['sections'] as $section ) {
			if ( ! is_array( $section ) || ! in_array( $section['section_id'] ?? '', $allowed, true ) || ! isset( $section['field_ids'] ) || ! is_array( $section['field_ids'] ) ) {
				throw new PDA_Map_Validation_Exception( 'invalid_section' );
			}
			foreach ( $section['field_ids'] as $field_id ) {
				if ( ! is_string( $field_id ) || ! in_array( $field_id, $input_ids, true ) || isset( $seen[ $field_id ] ) ) {
					throw new PDA_Map_Validation_Exception( 'invalid_field_id' );
				}
				$seen[ $field_id ] = true;
			}
		}
		if ( count( $seen ) !== count( $input_ids ) || array_diff( $input_ids, array_keys( $seen ) ) ) {
			throw new PDA_Map_Validation_Exception( 'missing_field_id' );
		}
		$heroes = $map['hero_field_ids'] ?? array();
		if ( ! is_array( $heroes ) || count( $heroes ) > 4 || count( $heroes ) !== count( array_unique( $heroes ) ) || array_diff( $heroes, $input_ids ) ) {
			throw new PDA_Map_Validation_Exception( 'invalid_hero_ids' );
		}
		foreach ( $map['warnings'] ?? array() as $warning ) {
			if ( ! is_array( $warning ) || 'ambiguous_section' !== ( $warning['code'] ?? '' ) || ! in_array( $warning['field_id'] ?? '', $input_ids, true ) ) {
				throw new PDA_Map_Validation_Exception( 'invalid_warning' );
			}
		}
		return $map;
	}

	/**
	 * @param array<string,mixed> $map Mapping.
	 * @param array<string,mixed> $snapshot Snapshot.
	 * @return bool
	 */
	public function is_valid( array $map, array $snapshot ) {
		try {
			$this->assert_valid( $map, $snapshot );
			return true;
		} catch ( PDA_Map_Validation_Exception $exception ) {
			return false;
		}
	}
}
