<?php

namespace Automattic\VIP\Files;

/**
 * Class ImageSizes
 *
 * Manages image resizing via VIP Go File Service.
 *
 * @package Automattic\VIP\Files
 */
class ImageSizes {

	/** @var array $data Attachment metadata. */
	public $data;

	/** @var Image Image to be resized. */
	public $image;
	
	/** @var int Attachment ID. */
	public $attachment_id;

	/** @var null|array $sizes Intermediate sizes. */
	public static $sizes = null;

	/**
	 * Construct new sizes meta
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $data          Attachment metadata.
	 */
	public function __construct( $attachment_id, $data ) {
		$this->data          = $data;
		$this->attachment_id = $attachment_id;
		$this->image         = new Image( $data, get_post_mime_type( $attachment_id ) );
		$this->generate_sizes();
	}

	/**
	 * Generate sizes for attachment.
	 *
	 * @return array Array of sizes; empty array as failure fallback.
	 */
	protected function generate_sizes() {

		// There is no need to generate the sizes a new for every single image.
		if ( null !== static::$sizes ) {
			return static::$sizes;
		}

		/*
		 * The following logic is copied over from wp_generate_attachment_metadata
		 */
		$_wp_additional_image_sizes = wp_get_additional_image_sizes();

		$sizes = [];

		/*
		 * Remove filter preventing WordPress from reading the sizes, it's meant
		 * to prevent creation of intermediate files, which are not really being used.
		 */
		remove_filter( 'intermediate_image_sizes', 'wpcom_intermediate_sizes' );
		$intermediate_image_sizes = get_intermediate_image_sizes();             // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_intermediate_image_sizes_get_intermediate_image_sizes
		add_filter( 'intermediate_image_sizes', 'wpcom_intermediate_sizes' );   // Re-add the filter.

		foreach ( $intermediate_image_sizes as $s ) {
			$sizes[ $s ] = [
				'width'  => '',
				'height' => '',
				'crop'   => false,
			];
			if ( isset( $_wp_additional_image_sizes[ $s ]['width'] ) ) {
				// For theme-added sizes.
				$sizes[ $s ]['width'] = intval( $_wp_additional_image_sizes[ $s ]['width'] );
			} else {
				// For default sizes set in options.
				$sizes[ $s ]['width'] = get_option( "{$s}_size_w" );
			}

			if ( isset( $_wp_additional_image_sizes[ $s ]['height'] ) ) {
				// For theme-added sizes.
				$sizes[ $s ]['height'] = intval( $_wp_additional_image_sizes[ $s ]['height'] );
			} else {
				// For default sizes set in options.
				$sizes[ $s ]['height'] = get_option( "{$s}_size_h" );
			}

			if ( isset( $_wp_additional_image_sizes[ $s ]['crop'] ) ) {
				// For theme-added sizes.
				$sizes[ $s ]['crop'] = $_wp_additional_image_sizes[ $s ]['crop'];
			} else {
				// For default sizes set in options.
				$sizes[ $s ]['crop'] = get_option( "{$s}_crop" );
			}
		}

		static::$sizes = $sizes;

		return $sizes;
	}

	/**
	 * @return array
	 */
	public function filtered_sizes() {
		// Remove filter preventing the creation of advanced sizes.
		remove_filter( 'intermediate_image_sizes_advanced', 'wpcom_intermediate_sizes' );

		/** This filter is documented in wp-admin/includes/image.php */
		$sizes = apply_filters( 'intermediate_image_sizes_advanced', static::$sizes, $this->data, $this->attachment_id );

		// Re-add the filter removed above.
		add_filter( 'intermediate_image_sizes_advanced', 'wpcom_intermediate_sizes' );

		return (array) $sizes;
	}

	/**
	 * Standardises and validates the size_data array.
	 *
	 * @param array $size_data Size data array - at least containing height or width key. Can contain crop as well.
	 *
	 * @return array Array with populated width, height and crop keys; empty array if no width and height are provided.
	 */
	public function standardize_size_data( $size_data ) {
		$has_at_least_width_or_height = ( isset( $size_data['width'] ) || isset( $size_data['height'] ) );
		if ( ! $has_at_least_width_or_height ) {
			return [];
		}

		$defaults = [
			'width'  => null,
			'height' => null,
			'crop'   => false,
		];

		return array_merge( $defaults, $size_data );
	}

	/**
	 * Get sizes for attachment post meta.
	 *
	 * @return array ImageSizes for attachment postmeta.
	 */
	public function generate_sizes_meta() {

		$metadata = [];

		foreach ( $this->filtered_sizes() as $size => $size_data ) {

			$size_data = $this->standardize_size_data( $size_data );

			if ( true === empty( $size_data ) ) {
				continue;
			}

			$resized_image = $this->resize( $size_data );

			if ( true === is_array( $resized_image ) ) {
				$metadata[ $size ] = $resized_image;
			}
		}

		return $metadata;
	}

	/**
	 * @param array
	 *
	 * @return array|\WP_Error Array for usage in $metadata['sizes']; WP_Error on failure.
	 */
	protected function resize( $size_data ) {

		return $this->image->get_size( $size_data );

	}
}
