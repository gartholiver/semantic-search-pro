<?php
/**
 * Semantic Search Pro uninstall cleanup.
 *
 * @package SemanticSearchPro
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'ssp_options' );
wp_clear_scheduled_hook( 'ssp_process_sync_queue' );
