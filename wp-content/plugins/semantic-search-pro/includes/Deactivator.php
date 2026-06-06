<?php
/**
 * Plugin deactivation routines.
 *
 * @package SemanticSearchPro
 */

namespace SemanticSearchPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Deactivator {
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'ssp_process_sync_queue' );
	}
}
