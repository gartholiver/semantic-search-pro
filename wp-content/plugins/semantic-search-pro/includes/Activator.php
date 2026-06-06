<?php
/**
 * Plugin activation routines.
 *
 * @package SemanticSearchPro
 */

namespace SemanticSearchPro;

use SemanticSearchPro\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Activator {
	public static function activate(): void {
		$options = new Options();
		$current = $options->all();

		if ( empty( $current['site_id'] ) ) {
			$current['site_id'] = wp_generate_uuid4();
		}

		if ( empty( $current['site_secret'] ) ) {
			$current['site_secret'] = wp_generate_password( 64, false, false );
		}

		$current['plugin_version'] = SSP_VERSION;
		$options->replace( $current );
	}
}
