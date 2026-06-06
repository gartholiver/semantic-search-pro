<?php
/**
 * Plugin Name: Semantic Search Pro
 * Description: SaaS-backed semantic search for content-heavy WordPress sites.
 * Version: 0.1.0
 * Author: Semantic Search Pro
 * Text Domain: semantic-search-pro
 * Requires at least: 6.4
 * Requires PHP: 8.1
 *
 * @package SemanticSearchPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SSP_VERSION', '0.1.0' );
define( 'SSP_PLUGIN_FILE', __FILE__ );
define( 'SSP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SSP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'SemanticSearchPro\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative_class = substr( $class_name, strlen( $prefix ) );
		$file           = SSP_PLUGIN_DIR . 'includes/' . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( SemanticSearchPro\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( SemanticSearchPro\Deactivator::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		$plugin = new SemanticSearchPro\Plugin();
		$plugin->register();
	}
);
