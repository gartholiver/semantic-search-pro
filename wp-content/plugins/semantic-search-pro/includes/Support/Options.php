<?php
/**
 * Plugin option access and sanitization.
 *
 * @package SemanticSearchPro
 */

namespace SemanticSearchPro\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Options {
	public const OPTION_NAME = 'ssp_options';

	public function all(): array {
		$stored = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args( $stored, $this->defaults() );
	}

	public function get( string $key, mixed $default = null ): mixed {
		$options = $this->all();

		return array_key_exists( $key, $options ) ? $options[ $key ] : $default;
	}

	public function replace( array $options ): void {
		update_option( self::OPTION_NAME, wp_parse_args( $options, $this->defaults() ), false );
	}

	public function update( array $changes ): void {
		$this->replace( array_merge( $this->all(), $changes ) );
	}

	public function defaults(): array {
		return array(
			'api_base_url'          => 'https://api.semanticsearchpro.example/v1',
			'license_key'           => '',
			'site_id'               => '',
			'site_secret'           => '',
			'enabled_post_types'    => array( 'post', 'page' ),
			'result_count'          => 8,
			'similarity_threshold'  => 0.72,
			'fallback_enabled'      => 1,
			'replace_wp_search'     => 0,
			'license_status'        => 'not_connected',
			'license_plan'          => '',
			'customer_portal_url'   => '',
			'indexed_count'         => 0,
			'monthly_query_count'   => 0,
			'monthly_query_limit'   => 0,
			'last_sync_started_at'  => '',
			'last_sync_finished_at' => '',
			'last_sync_error'       => '',
			'sync_queue'            => array(),
			'plugin_version'        => SSP_VERSION,
		);
	}

	public function sanitize( mixed $input ): array {
		$current = $this->all();
		$input   = is_array( $input ) ? $input : array();

		$api_base_url = isset( $input['api_base_url'] ) ? esc_url_raw( (string) $input['api_base_url'] ) : $current['api_base_url'];
		$license_key  = isset( $input['license_key'] ) ? sanitize_text_field( (string) $input['license_key'] ) : $current['license_key'];
		$post_types   = isset( $input['enabled_post_types'] ) && is_array( $input['enabled_post_types'] ) ? $input['enabled_post_types'] : array();
		$post_types   = array_values( array_intersect( array_map( 'sanitize_key', $post_types ), array_keys( $this->indexable_post_types() ) ) );

		if ( empty( $post_types ) ) {
			$post_types = array( 'post', 'page' );
		}

		return array_merge(
			$current,
			array(
				'api_base_url'         => untrailingslashit( $api_base_url ),
				'license_key'          => $license_key,
				'enabled_post_types'   => $post_types,
				'result_count'         => max( 1, min( 20, absint( $input['result_count'] ?? $current['result_count'] ) ) ),
				'similarity_threshold' => max( 0, min( 1, (float) ( $input['similarity_threshold'] ?? $current['similarity_threshold'] ) ) ),
				'fallback_enabled'     => empty( $input['fallback_enabled'] ) ? 0 : 1,
				'replace_wp_search'    => empty( $input['replace_wp_search'] ) ? 0 : 1,
			)
		);
	}

	public function indexable_post_types(): array {
		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'objects'
		);

		unset( $post_types['attachment'] );

		return $post_types;
	}
}
