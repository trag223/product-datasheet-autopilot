<?php
declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

final class SnapshotTest extends TestCase {
	public function test_accepts_fifty_bounded_fields(): void {
		$snapshot = PDA_Product_Snapshot::normalize( array( 'product_id' => 12, 'title' => 'Pump', 'fields' => $this->fields( 50 ) ) );
		self::assertCount( 50, $snapshot['fields'] );
	}

	public function test_rejects_fifty_first_field(): void {
		$this->expectException( PDA_Snapshot_Limit_Exception::class );
		PDA_Product_Snapshot::normalize( array( 'product_id' => 12, 'title' => 'Pump', 'fields' => $this->fields( 51 ) ) );
	}

	public function test_rejects_oversized_values_instead_of_truncating_them(): void {
		$this->expectException( PDA_Snapshot_Limit_Exception::class );
		PDA_Product_Snapshot::normalize( array( 'product_id' => 12, 'title' => 'Pump', 'fields' => array( array( 'id' => 'attr_long', 'label' => 'Long', 'value' => str_repeat( 'x', 301 ) ) ) ) );
	}

	/** @return array<int,array<string,string>> */
	private function fields( int $count ): array {
		$fields = array();
		for ( $i = 0; $i < $count; ++$i ) {
			$fields[] = array( 'id' => 'attr_' . $i, 'label' => 'Attribute ' . $i, 'value' => 'Value ' . $i );
		}
		return $fields;
	}
}
