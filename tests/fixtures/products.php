<?php
/**
 * Fifty deterministic fixture product snapshots spanning basic, dense, Unicode,
 * long-value, no-image, variable, and malformed inputs.
 *
 * @return array<int,array<string,mixed>>
 */

$fixtures = array();
for ( $index = 1; $index <= 50; ++$index ) {
	$fields = array( array( 'id' => 'core_sku', 'label' => 'SKU', 'value' => 'FIX-' . $index ) );
	for ( $attribute = 1; $attribute <= 20 + ( $index % 31 ); ++$attribute ) {
		$fields[] = array( 'id' => 'attr_' . $index . '_' . $attribute, 'label' => 'Attribute ' . $attribute, 'value' => 'Value ' . $attribute );
	}
	$fixtures[] = array(
		'case'     => 'simple-' . $index,
		'type'     => 0 === $index % 5 ? 'variable' : 'simple',
		'no_image' => 0 === $index % 7,
		'snapshot' => array( 'product_id' => $index, 'title' => 'Fixture product ' . $index, 'fields' => $fields ),
	);
}
$fixtures[0]['case'] = 'unicode';
$fixtures[0]['snapshot']['title'] = 'München 測試 насос';
$fixtures[1]['case'] = 'long-values';
$fixtures[1]['snapshot']['fields'][1]['value'] = str_repeat( 'L', 300 );
$fixtures[2]['case'] = 'malformed-51-fields';
$fixtures[2]['snapshot']['fields'][] = array( 'id' => 'attr_overflow', 'label' => 'Overflow', 'value' => 'x' );

return $fixtures;
