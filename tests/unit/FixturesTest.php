<?php
declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

final class FixturesTest extends TestCase {
	public function test_fixture_corpus_has_fifty_product_cases_and_required_edges(): void {
		$fixtures = require dirname( __DIR__ ) . '/fixtures/products.php';
		self::assertCount( 50, $fixtures );
		self::assertContains( 'unicode', array_column( $fixtures, 'case' ) );
		self::assertContains( 'malformed-51-fields', array_column( $fixtures, 'case' ) );
		self::assertContains( 'variable', array_column( $fixtures, 'type' ) );
		self::assertTrue( (bool) array_filter( $fixtures, static fn( array $fixture ): bool => $fixture['no_image'] ) );
	}
}
