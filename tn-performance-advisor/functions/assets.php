<?php
/**
 * Admin assets.
 *
 * @package TNPerformanceAdvisor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_enqueue_scripts', 'tnpa_enqueue_admin_assets' );

/**
 * Loads the small stylesheet only on the Performance Advisor page.
 *
 * @param string $hook_suffix Current admin page hook.
 * @return void
 */
function tnpa_enqueue_admin_assets( $hook_suffix ) {
	if ( 'settings_page_tn-performance-advisor' !== $hook_suffix ) {
		return;
	}

	wp_enqueue_style(
		'tnpa-admin',
		plugin_dir_url( TNPA_PLUGIN_FILE ) . 'styles/tn-performance-advisor.css',
		array(),
		TNPA_VERSION
	);
}
