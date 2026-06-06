<?php
/**
 * Semantic search and fallback orchestration.
 *
 * @package SemanticSearchPro
 */

namespace SemanticSearchPro\Search;

use SemanticSearchPro\Http\ApiClient;
use SemanticSearchPro\Support\Options;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SearchService {
	public function __construct(
		private readonly Options $options,
		private readonly ApiClient $api_client
	) {}

	public function register(): void {
		add_action( 'pre_get_posts', array( $this, 'replace_native_search' ) );
	}

	public function search( string $query, array $args = array() ): array {
		$query = trim( wp_strip_all_tags( $query ) );

		if ( '' === $query ) {
			return array(
				'source'  => 'empty',
				'results' => array(),
				'message' => __( 'Enter a search query.', 'semantic-search-pro' ),
			);
		}

		$limit    = max( 1, min( 20, absint( $args['limit'] ?? $this->options->get( 'result_count', 8 ) ) ) );
		$response = $this->api_client->search(
			$query,
			array(
				'limit'      => $limit,
				'post_types' => $this->sanitize_post_types( $args['post_types'] ?? $this->options->get( 'enabled_post_types', array( 'post', 'page' ) ) ),
			)
		);

		if ( $response['success'] ) {
			$results = $this->normalize_remote_results( $response['data']['results'] ?? array() );

			$this->options->update(
				array(
					'monthly_query_count' => absint( $response['data']['usage']['monthly_query_count'] ?? $this->options->get( 'monthly_query_count', 0 ) ),
					'monthly_query_limit' => absint( $response['data']['usage']['monthly_query_limit'] ?? $this->options->get( 'monthly_query_limit', 0 ) ),
				)
			);

			if ( ! empty( $results ) ) {
				return array(
					'source'  => 'semantic',
					'results' => $results,
					'message' => '',
				);
			}
		}

		if ( (int) $this->options->get( 'fallback_enabled', 1 ) !== 1 ) {
			return array(
				'source'  => 'semantic',
				'results' => array(),
				'message' => $response['message'] ?? __( 'No semantic results were found.', 'semantic-search-pro' ),
			);
		}

		return array(
			'source'  => 'fallback',
			'results' => $this->fallback_results( $query, $limit, $args ),
			'message' => $response['message'] ?? '',
		);
	}

	public function replace_native_search( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
			return;
		}

		if ( (int) $this->options->get( 'replace_wp_search', 0 ) !== 1 ) {
			return;
		}

		$search_query = (string) $query->get( 's' );
		$results      = $this->search(
			$search_query,
			array(
				'limit' => (int) $query->get( 'posts_per_page' ) ?: (int) $this->options->get( 'result_count', 8 ),
			)
		);
		$post_ids     = array_values(
			array_filter(
				array_map(
					static fn( array $result ): int => absint( $result['post_id'] ?? 0 ),
					$results['results']
				)
			)
		);

		if ( empty( $post_ids ) || 'fallback' === $results['source'] ) {
			return;
		}

		$query->set( 's', '' );
		$query->set( 'post__in', $post_ids );
		$query->set( 'orderby', 'post__in' );
	}

	private function fallback_results( string $query, int $limit, array $args = array() ): array {
		$wp_query = new WP_Query(
			array(
				's'              => $query,
				'post_type'      => $this->sanitize_post_types( $args['post_types'] ?? $this->options->get( 'enabled_post_types', array( 'post', 'page' ) ) ),
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'no_found_rows'  => true,
			)
		);

		return array_map(
			static function ( \WP_Post $post ): array {
				$content = trim( wp_strip_all_tags( $post->post_content ) );

				return array(
					'post_id'   => (int) $post->ID,
					'title'     => get_the_title( $post ),
					'url'       => get_permalink( $post ),
					'excerpt'   => has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( $content, 28 ),
					'post_type' => $post->post_type,
					'score'     => null,
				);
			},
			$wp_query->posts
		);
	}

	private function normalize_remote_results( mixed $results ): array {
		if ( ! is_array( $results ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map(
					static function ( mixed $result ): ?array {
						if ( ! is_array( $result ) ) {
							return null;
						}

						return array(
							'post_id'   => absint( $result['post_id'] ?? 0 ),
							'title'     => sanitize_text_field( $result['title'] ?? '' ),
							'url'       => esc_url_raw( $result['url'] ?? '' ),
							'excerpt'   => wp_kses_post( $result['excerpt'] ?? '' ),
							'post_type' => sanitize_key( $result['post_type'] ?? '' ),
							'score'     => isset( $result['score'] ) ? (float) $result['score'] : null,
						);
					},
					$results
				)
			)
		);
	}

	private function sanitize_post_types( mixed $post_types ): array {
		$post_types = is_array( $post_types ) ? $post_types : array( $post_types );
		$allowed    = array_keys( $this->options->indexable_post_types() );
		$sanitized  = array_values( array_intersect( array_map( 'sanitize_key', $post_types ), $allowed ) );

		return empty( $sanitized ) ? (array) $this->options->get( 'enabled_post_types', array( 'post', 'page' ) ) : $sanitized;
	}
}
