<?php
/**
 * Hosted Semantic Search Pro API client.
 *
 * @package SemanticSearchPro
 */

namespace SemanticSearchPro\Http;

use SemanticSearchPro\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ApiClient {
	public function __construct( private readonly Options $options ) {}

	public function validate_license(): array {
		return $this->request(
			'POST',
			'/license/validate',
			array(
				'site_url'   => home_url(),
				'site_id'    => $this->options->get( 'site_id' ),
				'site_secret'=> $this->options->get( 'site_secret' ),
				'wp_version' => get_bloginfo( 'version' ),
				'plugin'     => SSP_VERSION,
			),
			true
		);
	}

	public function upsert_content( array $document ): array {
		return $this->request( 'POST', '/content/upsert', $document );
	}

	public function delete_content( int $post_id ): array {
		return $this->request(
			'POST',
			'/content/delete',
			array(
				'post_id'  => $post_id,
				'site_id'  => $this->options->get( 'site_id' ),
				'site_url' => home_url(),
			)
		);
	}

	public function search( string $query, array $args = array() ): array {
		return $this->request(
			'POST',
			'/search',
			array_merge(
				array(
					'query'                => $query,
					'site_id'              => $this->options->get( 'site_id' ),
					'site_url'             => home_url(),
					'limit'                => (int) $this->options->get( 'result_count', 8 ),
					'similarity_threshold' => (float) $this->options->get( 'similarity_threshold', 0.72 ),
				),
				$args
			)
		);
	}

	private function request( string $method, string $path, array $payload = array(), bool $allow_without_license = false ): array {
		$base_url    = (string) $this->options->get( 'api_base_url', '' );
		$license_key = (string) $this->options->get( 'license_key', '' );

		if ( empty( $base_url ) ) {
			return $this->error( __( 'The hosted API URL is not configured.', 'semantic-search-pro' ) );
		}

		if ( empty( $license_key ) && ! $allow_without_license ) {
			return $this->error( __( 'A license key is required before using semantic search.', 'semantic-search-pro' ) );
		}

		$body      = wp_json_encode( $payload );
		$body      = false === $body ? '{}' : $body;
		$timestamp = (string) time();
		$signature = $this->signature( $method, $path, $body, $timestamp );
		$request_id = wp_generate_uuid4();

		$response = wp_remote_request(
			untrailingslashit( $base_url ) . $path,
			array(
				'method'  => $method,
				'timeout' => 15,
				'headers' => array(
					'Accept'              => 'application/json',
					'Content-Type'        => 'application/json',
					'X-SSP-License-Key'   => $license_key,
					'X-SSP-Site-ID'       => (string) $this->options->get( 'site_id', '' ),
					'X-SSP-Site-URL'      => home_url(),
					'X-SSP-Timestamp'     => $timestamp,
					'X-SSP-Signature'     => $signature,
					'X-SSP-Plugin-Version'=> SSP_VERSION,
					'X-SSP-Request-ID'    => $request_id,
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->error( $response->get_error_message() );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$data   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) ) {
			$data = array();
		}

		if ( $status < 200 || $status >= 300 ) {
			return $this->error( $data['message'] ?? __( 'The hosted API returned an error.', 'semantic-search-pro' ), $status, $data );
		}

		return array(
			'success' => true,
			'status'  => $status,
			'data'    => $data,
			'message' => '',
		);
	}

	private function signature( string $method, string $path, string $body, string $timestamp ): string {
		$secret = (string) $this->options->get( 'site_secret', '' );

		if ( empty( $secret ) ) {
			return '';
		}

		return hash_hmac( 'sha256', strtoupper( $method ) . "\n" . $path . "\n" . $body . "\n" . $timestamp, $secret );
	}

	private function error( string $message, int $status = 0, array $data = array() ): array {
		return array(
			'success' => false,
			'status'  => $status,
			'data'    => $data,
			'message' => $message,
		);
	}
}
