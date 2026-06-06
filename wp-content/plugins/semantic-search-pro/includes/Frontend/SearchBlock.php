<?php
/**
 * Gutenberg search block.
 *
 * @package SemanticSearchPro
 */

namespace SemanticSearchPro\Frontend;

use SemanticSearchPro\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SearchBlock {
	public function __construct( private readonly Options $options ) {}

	public function register(): void {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	public function register_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		register_block_type(
			'semantic-search-pro/search',
			array(
				'api_version'     => 2,
				'editor_script'   => 'ssp-search-block',
				'render_callback' => array( $this, 'render' ),
				'attributes'      => array(
					'placeholder' => array(
						'type'    => 'string',
						'default' => __( 'Search by meaning...', 'semantic-search-pro' ),
					),
					'perPage'     => array(
						'type'    => 'number',
						'default' => (int) $this->options->get( 'result_count', 8 ),
					),
				),
			)
		);
	}

	public function render( array $attributes ): string {
		$placeholder = sanitize_text_field( $attributes['placeholder'] ?? __( 'Search by meaning...', 'semantic-search-pro' ) );
		$per_page    = absint( $attributes['perPage'] ?? $this->options->get( 'result_count', 8 ) );

		return do_shortcode(
			sprintf(
				'[semantic_search_pro placeholder="%s" per_page="%d"]',
				esc_attr( $placeholder ),
				$per_page
			)
		);
	}
}
