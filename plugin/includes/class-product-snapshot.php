<?php
/**
 * WooCommerce product data extraction with a deliberately small allow-list.
 *
 * @package ProductDatasheetAutopilot
 */

defined( 'ABSPATH' ) || exit;

class PDA_Snapshot_Limit_Exception extends RuntimeException {}

class PDA_Product_Snapshot {
	/** @var int */
	const MAX_FIELDS = 50;
	/** @var int */
	const MAX_TITLE = 200;
	/** @var int */
	const MAX_LABEL = 100;
	/** @var int */
	const MAX_VALUE = 300;
	/** @var int */
	const MAX_AI_CHARS = 16000;

	/**
	 * Extract only documented product fields. Hidden arbitrary meta is excluded.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return array<string,mixed>
	 * @throws PDA_Snapshot_Limit_Exception If the product exceeds a contract limit.
	 */
	public function build( $product ) {
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
			throw new InvalidArgumentException( 'invalid_product' );
		}
		$fields = array();
		$this->add( $fields, 'core_sku', __( 'SKU', 'product-datasheet-autopilot' ), $product->get_sku() );
		$this->add( $fields, 'core_length', __( 'Length', 'product-datasheet-autopilot' ), $this->with_unit( $product->get_length(), 'woocommerce_dimension_unit' ) );
		$this->add( $fields, 'core_width', __( 'Width', 'product-datasheet-autopilot' ), $this->with_unit( $product->get_width(), 'woocommerce_dimension_unit' ) );
		$this->add( $fields, 'core_height', __( 'Height', 'product-datasheet-autopilot' ), $this->with_unit( $product->get_height(), 'woocommerce_dimension_unit' ) );
		$this->add( $fields, 'core_weight', __( 'Weight', 'product-datasheet-autopilot' ), $this->with_unit( $product->get_weight(), 'woocommerce_weight_unit' ) );

		foreach ( $product->get_attributes() as $attribute ) {
			if ( ! is_object( $attribute ) || ! $attribute->get_visible() ) {
				continue;
			}
			$name  = (string) $attribute->get_name();
			$label = function_exists( 'wc_attribute_label' ) ? wc_attribute_label( $name, $product ) : $name;
			$value = (string) $product->get_attribute( $name );
			$this->add( $fields, 'attr_' . substr( hash( 'sha256', $name ), 0, 16 ), $label, $value );
		}

		foreach ( PDA_Settings::get( 'selected_meta_keys', array() ) as $meta_key ) {
			$value = get_post_meta( $product->get_id(), $meta_key, true );
			if ( is_scalar( $value ) ) {
				$this->add( $fields, 'meta_' . sanitize_key( $meta_key ), $meta_key, (string) $value );
			}
		}

		$image_id = absint( $product->get_image_id() );
		if ( $image_id && 'attachment' !== get_post_type( $image_id ) ) {
			$image_id = 0;
		}
		return self::normalize(
			array(
				'product_id'          => absint( $product->get_id() ),
				'title'               => (string) $product->get_name(),
				'product_url'         => (string) $product->get_permalink(),
				'branding_name'       => (string) get_bloginfo( 'name' ),
				'image_attachment_id' => $image_id,
				'fields'              => $fields,
			)
		);
	}

	/**
	 * Validate a snapshot candidate. Public for unit fixtures and gateway parity.
	 *
	 * @param array<string,mixed> $snapshot Candidate.
	 * @return array<string,mixed>
	 * @throws PDA_Snapshot_Limit_Exception On contract violation.
	 */
	public static function normalize( array $snapshot ) {
		$title = isset( $snapshot['title'] ) ? (string) $snapshot['title'] : '';
		if ( '' === $title || self::length( $title ) > self::MAX_TITLE ) {
			throw new PDA_Snapshot_Limit_Exception( 'title_limit' );
		}
		$fields = isset( $snapshot['fields'] ) && is_array( $snapshot['fields'] ) ? $snapshot['fields'] : array();
		if ( count( $fields ) > self::MAX_FIELDS ) {
			throw new PDA_Snapshot_Limit_Exception( 'field_limit' );
		}
		$ids        = array();
		$normalized = array();
		$ai_chars   = self::length( $title );
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) || ! isset( $field['id'], $field['label'], $field['value'] ) ) {
				throw new PDA_Snapshot_Limit_Exception( 'invalid_field' );
			}
			$id    = (string) $field['id'];
			$label = (string) $field['label'];
			$value = (string) $field['value'];
			if ( ! preg_match( '/^[a-z0-9_]{1,80}$/', $id ) || isset( $ids[ $id ] ) || '' === $label || self::length( $label ) > self::MAX_LABEL || self::length( $value ) > self::MAX_VALUE ) {
				throw new PDA_Snapshot_Limit_Exception( 'field_contract' );
			}
			$ids[ $id ]   = true;
			$ai_chars    += self::length( $label ) + self::length( $value );
			$normalized[] = array( 'id' => $id, 'label' => $label, 'value' => $value );
		}
		if ( $ai_chars > self::MAX_AI_CHARS ) {
			throw new PDA_Snapshot_Limit_Exception( 'ai_input_limit' );
		}
		return array(
			'product_id'          => absint( $snapshot['product_id'] ?? 0 ),
			'title'               => $title,
			'product_url'         => (string) ( $snapshot['product_url'] ?? '' ),
			'branding_name'       => (string) ( $snapshot['branding_name'] ?? '' ),
			'image_attachment_id' => absint( $snapshot['image_attachment_id'] ?? 0 ),
			'fields'              => $normalized,
		);
	}

	/**
	 * @param array<int,array<string,string>> $fields Fields by reference.
	 * @param string                           $id ID.
	 * @param string                           $label Label.
	 * @param mixed                            $value Value.
	 * @return void
	 */
	private function add( array &$fields, $id, $label, $value ) {
		if ( '' !== (string) $value ) {
			$fields[] = array( 'id' => $id, 'label' => (string) $label, 'value' => (string) $value );
		}
	}

	/**
	 * @param mixed  $value Value.
	 * @param string $unit_option WooCommerce unit option.
	 * @return string
	 */
	private function with_unit( $value, $unit_option ) {
		if ( '' === (string) $value ) {
			return '';
		}
		$unit = (string) get_option( $unit_option, '' );
		return (string) $value . ( '' === $unit ? '' : ' ' . $unit );
	}

	/**
	 * @param string $value Text.
	 * @return int
	 */
	private static function length( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
	}
}
