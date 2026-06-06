<?php
/**
 * REST API endpoints.
 *
 * @package SemanticSearchPro
 */

namespace SemanticSearchPro\Http;

use SemanticSearchPro\Search\SearchService;
use SemanticSearchPro\Support\Options;
use SemanticSearchPro\Sync\SyncManager;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RestController {
	private const NAMESPACE = 'semantic-search-pro/v1';

	public function __construct(
		private readonly Options $options,
		private readonly SearchService $search,
		private readonly SyncManager $sync,
		private readonly ApiClient $api_client
	) {}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/search',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( $this, 'search' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'q'        => array(
						'type'     => 'string',
						'required' => true,
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => (int) $this->options->get( 'result_count', 8 ),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'status' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/sync/full',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'full_sync' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/license/validate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'validate_license' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	public function search( WP_REST_Request $request ): WP_REST_Response {
		if ( ! $this->within_public_rate_limit() ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Too many search requests. Please wait and try again.', 'semantic-search-pro' ),
				),
				429
			);
		}

		$result = $this->search->search(
			(string) $request->get_param( 'q' ),
			array(
				'limit'      => absint( $request->get_param( 'per_page' ) ),
				'post_types' => $request->get_param( 'post_type' ),
			)
		);

		return rest_ensure_response( $result );
	}

	public function status(): WP_REST_Response {
		$options = $this->options->all();

		return rest_ensure_response(
			array(
				'license_status'      => $options['license_status'],
				'license_plan'        => $options['license_plan'],
				'queued_items'        => count( (array) $options['sync_queue'] ),
				'indexed_count'       => absint( $options['indexed_count'] ),
				'monthly_query_count' => absint( $options['monthly_query_count'] ),
				'monthly_query_limit' => absint( $options['monthly_query_limit'] ),
				'last_sync_started_at'=> $options['last_sync_started_at'],
				'last_sync_finished_at'=> $options['last_sync_finished_at'],
				'last_sync_error'     => $options['last_sync_error'],
			)
		);
	}

	public function full_sync(): WP_REST_Response {
		return rest_ensure_response(
			array(
				'queued' => $this->sync->enqueue_full_sync(),
			)
		);
	}

	public function validate_license(): WP_REST_Response {
		$response = $this->api_client->validate_license();

		if ( ! $response['success'] ) {
			return new WP_REST_Response(
				array(
					'message' => $response['message'],
				),
				$response['status'] ?: 400
			);
		}

		$data = $response['data'];
		$this->options->update(
			array(
				'license_status'      => sanitize_key( $data['status'] ?? 'active' ),
				'license_plan'        => sanitize_text_field( $data['plan'] ?? '' ),
				'customer_portal_url' => esc_url_raw( $data['customer_portal_url'] ?? '' ),
				'monthly_query_limit' => absint( $data['monthly_query_limit'] ?? 0 ),
			)
		);

		return rest_ensure_response( $data );
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	private function within_public_rate_limit(): bool {
		$ip      = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );
		$key     = 'ssp_rate_' . md5( $ip );
		$current = (int) get_transient( $key );

		if ( $current >= 60 ) {
			return false;
		}

		set_transient( $key, $current + 1, MINUTE_IN_SECONDS );

		return true;
	}
}
