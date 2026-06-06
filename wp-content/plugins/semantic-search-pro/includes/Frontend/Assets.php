<?php
/**
 * Frontend asset registration.
 *
 * @package SemanticSearchPro
 */

namespace SemanticSearchPro\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Assets {
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_frontend_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'register_block_assets' ) );
	}

	public function register_frontend_assets(): void {
		wp_register_script(
			'ssp-frontend',
			SSP_PLUGIN_URL . 'assets/frontend.js',
			array(),
			SSP_VERSION,
			true
		);

		wp_register_style(
			'ssp-frontend',
			SSP_PLUGIN_URL . 'assets/frontend.css',
			array(),
			SSP_VERSION
		);
	}

	public function register_block_assets(): void {
		wp_register_script(
			'ssp-search-block',
			SSP_PLUGIN_URL . 'assets/block.js',
			array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n' ),
			SSP_VERSION,
			true
		);
	}
}
