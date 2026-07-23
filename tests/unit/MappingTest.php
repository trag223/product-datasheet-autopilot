<?php
declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

final class MappingTest extends TestCase {
	/** @var array<string,mixed> */ private array $snapshot;

	protected function setUp(): void {
		$this->snapshot = PDA_Product_Snapshot::normalize( array(
			'product_id' => 5, 'title' => 'Bench PSU',
			'fields' => array(
				array( 'id' => 'core_sku', 'label' => 'SKU', 'value' => 'PSU-42' ),
				array( 'id' => 'attr_voltage', 'label' => 'Input Voltage', 'value' => '230 V' ),
				array( 'id' => 'attr_finish', 'label' => 'Material Finish', 'value' => 'Powder coat' ),
			),
		) );
	}

	public function test_deterministic_map_covers_each_field_once(): void {
		$map = ( new PDA_Deterministic_Mapper() )->map( $this->snapshot );
		self::assertTrue( ( new PDA_Map_Validator() )->is_valid( $map, $this->snapshot ) );
	}

	public function test_duplicate_ai_ids_are_rejected_without_repair(): void {
		$map = ( new PDA_Deterministic_Mapper() )->map( $this->snapshot );
		$map['sections'][0]['field_ids'][] = 'core_sku';
		self::assertFalse( ( new PDA_Map_Validator() )->is_valid( $map, $this->snapshot ) );
	}

	public function test_missing_and_unknown_ai_ids_are_rejected(): void {
		$map = ( new PDA_Deterministic_Mapper() )->map( $this->snapshot );
		$map['sections'][0]['field_ids'] = array( 'unknown_id' );
		self::assertFalse( ( new PDA_Map_Validator() )->is_valid( $map, $this->snapshot ) );
	}

	public function test_hash_ignores_product_identity(): void {
		$other = $this->snapshot;
		$other['product_id'] = 999;
		self::assertSame( PDA_Content_Hash::for_snapshot( $this->snapshot ), PDA_Content_Hash::for_snapshot( $other ) );
	}
}
