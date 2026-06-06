<?php
/**
 * Main plugin composition root.
 *
 * @package SemanticSearchPro
 */

namespace SemanticSearchPro;

use SemanticSearchPro\Admin\SettingsPage;
use SemanticSearchPro\Frontend\Assets;
use SemanticSearchPro\Frontend\SearchBlock;
use SemanticSearchPro\Frontend\Shortcode;
use SemanticSearchPro\Http\ApiClient;
use SemanticSearchPro\Http\RestController;
use SemanticSearchPro\Search\SearchService;
use SemanticSearchPro\Support\Options;
use SemanticSearchPro\Sync\ContentNormalizer;
use SemanticSearchPro\Sync\SyncManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	public function register(): void {
		$options    = new Options();
		$api_client = new ApiClient( $options );
		$normalizer = new ContentNormalizer( $options );
		$sync       = new SyncManager( $options, $api_client, $normalizer );
		$search     = new SearchService( $options, $api_client );

		( new SettingsPage( $options, $api_client, $sync ) )->register();
		( new RestController( $options, $search, $sync, $api_client ) )->register();
		( new Shortcode( $options ) )->register();
		( new SearchBlock( $options ) )->register();
		( new Assets() )->register();
		$search->register();
		$sync->register();

		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
	}

	public function add_privacy_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		wp_add_privacy_policy_content(
			__( 'Semantic Search Pro', 'semantic-search-pro' ),
			wp_kses_post(
				__(
					'Semantic Search Pro sends selected public content, metadata, and search queries to the configured hosted search service to provide semantic search. Drafts, private posts, password-protected content, and excluded post types are not indexed by default. The service may process query text, result metadata, and usage metrics for licensing, quota enforcement, and analytics.',
					'semantic-search-pro'
				)
			)
		);
	}
}
