<?php
/**
 * Search shortcode.
 *
 * @package SemanticSearchPro
 */

namespace SemanticSearchPro\Frontend;

use SemanticSearchPro\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Shortcode {
	public function __construct( private readonly Options $options ) {}

	public function register(): void {
		add_shortcode( 'semantic_search_pro', array( $this, 'render' ) );
	}

	public function render( array $attributes = array() ): string {
		$attributes = shortcode_atts(
			array(
				'placeholder' => __( 'Search by meaning...', 'semantic-search-pro' ),
				'per_page'    => (string) $this->options->get( 'result_count', 8 ),
			),
			$attributes,
			'semantic_search_pro'
		);

		wp_enqueue_script( 'ssp-frontend' );
		wp_enqueue_style( 'ssp-frontend' );
		wp_localize_script(
			'ssp-frontend',
			'sspSearchPro',
			array(
				'restUrl'    => esc_url_raw( rest_url( 'semantic-search-pro/v1/search' ) ),
				'defaultPerPage' => absint( $attributes['per_page'] ),
				'labels'     => array(
					'searching' => __( 'Searching...', 'semantic-search-pro' ),
					'noResults' => __( 'No matching results found.', 'semantic-search-pro' ),
					'error'     => __( 'Search is temporarily unavailable.', 'semantic-search-pro' ),
				),
			)
		);

		$id = 'ssp-search-' . wp_unique_id();

		ob_start();
		?>
		<div id="<?php echo esc_attr( $id ); ?>" class="ssp-search" data-per-page="<?php echo esc_attr( (string) absint( $attributes['per_page'] ) ); ?>">
			<form class="ssp-search__form" role="search">
				<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>-input"><?php esc_html_e( 'Semantic search query', 'semantic-search-pro' ); ?></label>
				<input id="<?php echo esc_attr( $id ); ?>-input" class="ssp-search__input" type="search" name="q" placeholder="<?php echo esc_attr( $attributes['placeholder'] ); ?>" autocomplete="off">
				<button class="ssp-search__button" type="submit"><?php esc_html_e( 'Search', 'semantic-search-pro' ); ?></button>
			</form>
			<div class="ssp-search__status" aria-live="polite"></div>
			<ul class="ssp-search__results"></ul>
		</div>
		<?php

		return (string) ob_get_clean();
	}
}
