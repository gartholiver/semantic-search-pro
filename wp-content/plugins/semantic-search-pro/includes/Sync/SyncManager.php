<?php
/**
 * Content sync orchestration.
 *
 * @package SemanticSearchPro
 */

namespace SemanticSearchPro\Sync;

use SemanticSearchPro\Http\ApiClient;
use SemanticSearchPro\Support\Options;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SyncManager {
	private const MAX_BATCH = 10;

	public function __construct(
		private readonly Options $options,
		private readonly ApiClient $api_client,
		private readonly ContentNormalizer $normalizer
	) {}

	public function register(): void {
		add_action( 'save_post', array( $this, 'handle_save_post' ), 20, 3 );
		add_action( 'trashed_post', array( $this, 'handle_delete_post' ) );
		add_action( 'deleted_post', array( $this, 'handle_delete_post' ) );
		add_action( 'transition_post_status', array( $this, 'handle_status_transition' ), 20, 3 );
		add_action( 'ssp_process_sync_queue', array( $this, 'process_queue' ) );
	}

	public function enqueue_full_sync(): int {
		$query = new \WP_Query(
			array(
				'post_type'      => (array) $this->options->get( 'enabled_post_types', array( 'post', 'page' ) ),
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$post_ids = array_map( 'absint', $query->posts );

		$this->options->update(
			array(
				'sync_queue'           => array_values( array_unique( $post_ids ) ),
				'last_sync_started_at' => current_time( 'mysql', true ),
				'last_sync_error'      => '',
			)
		);

		$this->schedule_queue();

		return count( $post_ids );
	}

	public function enqueue_post( int $post_id ): void {
		$queue   = array_map( 'absint', (array) $this->options->get( 'sync_queue', array() ) );
		$queue[] = $post_id;

		$this->options->update(
			array(
				'sync_queue' => array_values( array_unique( array_filter( $queue ) ) ),
			)
		);

		$this->schedule_queue();
	}

	public function process_queue(): void {
		$queue = array_values( array_map( 'absint', (array) $this->options->get( 'sync_queue', array() ) ) );

		if ( empty( $queue ) ) {
			return;
		}

		$batch        = array_splice( $queue, 0, self::MAX_BATCH );
		$indexed      = (int) $this->options->get( 'indexed_count', 0 );
		$last_error   = '';

		foreach ( $batch as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post instanceof WP_Post || ! $this->normalizer->can_index( $post ) ) {
				$response = $this->api_client->delete_content( $post_id );
			} else {
				$response = $this->api_client->upsert_content( $this->normalizer->normalize( $post ) );

				if ( $response['success'] ) {
					++$indexed;
				}
			}

			if ( ! $response['success'] ) {
				$last_error = $response['message'];
				$queue[]    = $post_id;
			}
		}

		$this->options->update(
			array(
				'sync_queue'            => array_values( array_unique( $queue ) ),
				'indexed_count'         => max( 0, $indexed ),
				'last_sync_error'       => $last_error,
				'last_sync_finished_at' => empty( $queue ) ? current_time( 'mysql', true ) : $this->options->get( 'last_sync_finished_at', '' ),
			)
		);

		if ( ! empty( $queue ) ) {
			$this->schedule_queue();
		}
	}

	public function handle_save_post( int $post_id, WP_Post $post, bool $update ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$this->enqueue_post( $post_id );
	}

	public function handle_delete_post( int $post_id ): void {
		$this->api_client->delete_content( absint( $post_id ) );
	}

	public function handle_status_transition( string $new_status, string $old_status, WP_Post $post ): void {
		if ( $new_status === $old_status ) {
			return;
		}

		$this->enqueue_post( (int) $post->ID );
	}

	private function schedule_queue(): void {
		if ( ! wp_next_scheduled( 'ssp_process_sync_queue' ) ) {
			wp_schedule_single_event( time() + 10, 'ssp_process_sync_queue' );
		}
	}
}
