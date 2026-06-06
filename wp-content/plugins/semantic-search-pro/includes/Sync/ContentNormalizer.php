<?php
/**
 * Converts WordPress posts into hosted search documents.
 *
 * @package SemanticSearchPro
 */

namespace SemanticSearchPro\Sync;

use SemanticSearchPro\Support\Options;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ContentNormalizer {
	public function __construct( private readonly Options $options ) {}

	public function can_index( int|WP_Post $post ): bool {
		$post = get_post( $post );

		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		if ( 'publish' !== $post->post_status ) {
			return false;
		}

		if ( post_password_required( $post ) || ! empty( $post->post_password ) ) {
			return false;
		}

		$enabled_post_types = (array) $this->options->get( 'enabled_post_types', array( 'post', 'page' ) );

		if ( ! in_array( $post->post_type, $enabled_post_types, true ) ) {
			return false;
		}

		return (bool) apply_filters( 'ssp_can_index_post', true, $post );
	}

	public function normalize( WP_Post $post ): array {
		$taxonomies = get_object_taxonomies( $post->post_type );
		$terms      = array();

		foreach ( $taxonomies as $taxonomy ) {
			$post_terms = get_the_terms( $post, $taxonomy );

			if ( is_wp_error( $post_terms ) || empty( $post_terms ) ) {
				continue;
			}

			$terms[ $taxonomy ] = array_map(
				static fn( $term ): array => array(
					'id'   => (int) $term->term_id,
					'name' => $term->name,
					'slug' => $term->slug,
				),
				$post_terms
			);
		}

		$content = trim( wp_strip_all_tags( do_shortcode( $post->post_content ) ) );
		$excerpt = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( $content, 32, '' );

		return array(
			'site_id'       => $this->options->get( 'site_id' ),
			'site_url'      => home_url(),
			'post_id'       => (int) $post->ID,
			'post_type'     => $post->post_type,
			'post_status'   => $post->post_status,
			'title'         => get_the_title( $post ),
			'excerpt'       => wp_strip_all_tags( $excerpt ),
			'content'       => $content,
			'url'           => get_permalink( $post ),
			'language'      => get_bloginfo( 'language' ),
			'modified_gmt'  => get_post_modified_time( DATE_ATOM, true, $post ),
			'published_gmt' => get_post_time( DATE_ATOM, true, $post ),
			'taxonomies'    => $terms,
			'visibility'    => 'public',
		);
	}
}
