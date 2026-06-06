<?php
/**
 * Admin settings page.
 *
 * @package SemanticSearchPro
 */

namespace SemanticSearchPro\Admin;

use SemanticSearchPro\Http\ApiClient;
use SemanticSearchPro\Support\Options;
use SemanticSearchPro\Sync\SyncManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SettingsPage {
	public function __construct(
		private readonly Options $options,
		private readonly ApiClient $api_client,
		private readonly SyncManager $sync
	) {}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_ssp_validate_license', array( $this, 'validate_license' ) );
		add_action( 'admin_post_ssp_full_sync', array( $this, 'full_sync' ) );
	}

	public function add_menu(): void {
		add_options_page(
			__( 'Semantic Search Pro', 'semantic-search-pro' ),
			__( 'Semantic Search Pro', 'semantic-search-pro' ),
			'manage_options',
			'semantic-search-pro',
			array( $this, 'render' )
		);
	}

	public function register_settings(): void {
		register_setting(
			'ssp_settings',
			Options::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this->options, 'sanitize' ),
			)
		);
	}

	public function validate_license(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Semantic Search Pro.', 'semantic-search-pro' ) );
		}

		check_admin_referer( 'ssp_validate_license' );

		$response = $this->api_client->validate_license();

		if ( $response['success'] ) {
			$data = $response['data'];
			$this->options->update(
				array(
					'license_status'      => sanitize_key( $data['status'] ?? 'active' ),
					'license_plan'        => sanitize_text_field( $data['plan'] ?? '' ),
					'customer_portal_url' => esc_url_raw( $data['customer_portal_url'] ?? '' ),
					'monthly_query_limit' => absint( $data['monthly_query_limit'] ?? 0 ),
					'last_sync_error'     => '',
				)
			);
			$this->redirect( 'license_validated' );
		}

		$this->options->update(
			array(
				'license_status'  => 'error',
				'last_sync_error' => sanitize_text_field( $response['message'] ),
			)
		);
		$this->redirect( 'license_error' );
	}

	public function full_sync(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Semantic Search Pro.', 'semantic-search-pro' ) );
		}

		check_admin_referer( 'ssp_full_sync' );

		$count = $this->sync->enqueue_full_sync();

		$this->redirect( 'sync_enqueued', array( 'queued' => $count ) );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options       = $this->options->all();
		$post_types    = $this->options->indexable_post_types();
		$enabled_types = (array) $options['enabled_post_types'];
		$queue_count   = count( (array) $options['sync_queue'] );
		$notice        = isset( $_GET['ssp_notice'] ) ? sanitize_key( wp_unslash( $_GET['ssp_notice'] ) ) : '';
		?>
		<div class="wrap ssp-admin">
			<h1><?php esc_html_e( 'Semantic Search Pro', 'semantic-search-pro' ); ?></h1>
			<?php $this->render_notice( $notice ); ?>

			<div class="ssp-admin-grid" style="display:grid;grid-template-columns:minmax(0,2fr) minmax(280px,1fr);gap:24px;align-items:start;">
				<form action="options.php" method="post">
					<?php settings_fields( 'ssp_settings' ); ?>
					<h2><?php esc_html_e( 'Hosted service', 'semantic-search-pro' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="ssp_api_base_url"><?php esc_html_e( 'API base URL', 'semantic-search-pro' ); ?></label></th>
							<td><input class="regular-text" id="ssp_api_base_url" name="<?php echo esc_attr( Options::OPTION_NAME ); ?>[api_base_url]" type="url" value="<?php echo esc_attr( $options['api_base_url'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="ssp_license_key"><?php esc_html_e( 'License key', 'semantic-search-pro' ); ?></label></th>
							<td><input class="regular-text" id="ssp_license_key" name="<?php echo esc_attr( Options::OPTION_NAME ); ?>[license_key]" type="password" value="<?php echo esc_attr( $options['license_key'] ); ?>" autocomplete="off"></td>
						</tr>
					</table>

					<h2><?php esc_html_e( 'Indexing', 'semantic-search-pro' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Content types', 'semantic-search-pro' ); ?></th>
							<td>
								<?php foreach ( $post_types as $post_type ) : ?>
									<label style="display:block;margin-bottom:6px;">
										<input type="checkbox" name="<?php echo esc_attr( Options::OPTION_NAME ); ?>[enabled_post_types][]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( in_array( $post_type->name, $enabled_types, true ) ); ?>>
										<?php echo esc_html( $post_type->labels->name ); ?>
									</label>
								<?php endforeach; ?>
								<p class="description"><?php esc_html_e( 'Only published, public, non-password-protected content is indexed.', 'semantic-search-pro' ); ?></p>
							</td>
						</tr>
					</table>

					<h2><?php esc_html_e( 'Search behavior', 'semantic-search-pro' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="ssp_result_count"><?php esc_html_e( 'Default result count', 'semantic-search-pro' ); ?></label></th>
							<td><input id="ssp_result_count" name="<?php echo esc_attr( Options::OPTION_NAME ); ?>[result_count]" type="number" min="1" max="20" value="<?php echo esc_attr( (string) $options['result_count'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="ssp_similarity_threshold"><?php esc_html_e( 'Similarity threshold', 'semantic-search-pro' ); ?></label></th>
							<td><input id="ssp_similarity_threshold" name="<?php echo esc_attr( Options::OPTION_NAME ); ?>[similarity_threshold]" type="number" min="0" max="1" step="0.01" value="<?php echo esc_attr( (string) $options['similarity_threshold'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Fallbacks', 'semantic-search-pro' ); ?></th>
							<td>
								<label><input type="checkbox" name="<?php echo esc_attr( Options::OPTION_NAME ); ?>[fallback_enabled]" value="1" <?php checked( (int) $options['fallback_enabled'], 1 ); ?>> <?php esc_html_e( 'Use native WordPress keyword results when semantic search is unavailable.', 'semantic-search-pro' ); ?></label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Default search', 'semantic-search-pro' ); ?></th>
							<td>
								<label><input type="checkbox" name="<?php echo esc_attr( Options::OPTION_NAME ); ?>[replace_wp_search]" value="1" <?php checked( (int) $options['replace_wp_search'], 1 ); ?>> <?php esc_html_e( 'Redirect standard search forms to the Semantic Search Pro endpoint.', 'semantic-search-pro' ); ?></label>
							</td>
						</tr>
					</table>
					<?php submit_button(); ?>
				</form>

				<aside class="postbox" style="padding:16px;">
					<h2 style="margin-top:0;"><?php esc_html_e( 'Status', 'semantic-search-pro' ); ?></h2>
					<p><strong><?php esc_html_e( 'License:', 'semantic-search-pro' ); ?></strong> <?php echo esc_html( $options['license_status'] ); ?></p>
					<p><strong><?php esc_html_e( 'Plan:', 'semantic-search-pro' ); ?></strong> <?php echo esc_html( $options['license_plan'] ?: __( 'Not connected', 'semantic-search-pro' ) ); ?></p>
					<p><strong><?php esc_html_e( 'Queued items:', 'semantic-search-pro' ); ?></strong> <?php echo esc_html( (string) $queue_count ); ?></p>
					<p><strong><?php esc_html_e( 'Indexed items:', 'semantic-search-pro' ); ?></strong> <?php echo esc_html( (string) absint( $options['indexed_count'] ) ); ?></p>
					<p><strong><?php esc_html_e( 'Monthly queries:', 'semantic-search-pro' ); ?></strong> <?php echo esc_html( absint( $options['monthly_query_count'] ) . ' / ' . absint( $options['monthly_query_limit'] ) ); ?></p>
					<?php if ( ! empty( $options['last_sync_error'] ) ) : ?>
						<p><strong><?php esc_html_e( 'Last error:', 'semantic-search-pro' ); ?></strong> <?php echo esc_html( $options['last_sync_error'] ); ?></p>
					<?php endif; ?>

					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="margin-bottom:12px;">
						<input type="hidden" name="action" value="ssp_validate_license">
						<?php wp_nonce_field( 'ssp_validate_license' ); ?>
						<?php submit_button( __( 'Validate license', 'semantic-search-pro' ), 'secondary', 'submit', false ); ?>
					</form>

					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="margin-bottom:12px;">
						<input type="hidden" name="action" value="ssp_full_sync">
						<?php wp_nonce_field( 'ssp_full_sync' ); ?>
						<?php submit_button( __( 'Queue full sync', 'semantic-search-pro' ), 'primary', 'submit', false ); ?>
					</form>

					<?php if ( ! empty( $options['customer_portal_url'] ) ) : ?>
						<p><a class="button" href="<?php echo esc_url( $options['customer_portal_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Manage subscription', 'semantic-search-pro' ); ?></a></p>
					<?php endif; ?>
				</aside>
			</div>
		</div>
		<?php
	}

	private function render_notice( string $notice ): void {
		$messages = array(
			'license_validated' => __( 'License validated successfully.', 'semantic-search-pro' ),
			'license_error'     => __( 'License validation failed. Check the status panel for details.', 'semantic-search-pro' ),
			'sync_enqueued'     => __( 'Full sync queued.', 'semantic-search-pro' ),
		);

		if ( empty( $messages[ $notice ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-info is-dismissible"><p>%s</p></div>',
			esc_html( $messages[ $notice ] )
		);
	}

	private function redirect( string $notice, array $args = array() ): void {
		wp_safe_redirect(
			add_query_arg(
				array_merge( array( 'ssp_notice' => $notice ), $args ),
				admin_url( 'options-general.php?page=semantic-search-pro' )
			)
		);
		exit;
	}
}
