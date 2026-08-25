<?php
/**
 * Plugin Name: TN Performance Advisor
 * Description: Turns a sanitised Query Monitor capture into a prioritised performance action plan using the WordPress AI Client.
 * Version: 0.1.5
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Update URI: https://github.com/cchatterton/tn-performance-advisor
 * Author: Techn
 * Author URI: https://techn.com.au
 * Text Domain: tn-performance-advisor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TNPA_VERSION', '0.1.5' );
define( 'TNPA_PLUGIN_FILE', __FILE__ );
define( 'TNPA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TNPA_CAPTURE_SCHEMA_VERSION', 2 );
define( 'TNPA_RESULT_SCHEMA_VERSION', 4 );

require_once TNPA_PLUGIN_DIR . 'functions/github-updater.php';

add_action( 'plugins_loaded', 'tnpa_bootstrap', 20 );

/**
 * Loads the plugin only when its integrations are available.
 *
 * Missing integrations are intentionally silent so this plugin never blocks
 * activation or normal WordPress requests.
 *
 * @return void
 */
function tnpa_bootstrap() {
	$openai_provider_class = '\\WordPress\\OpenAiAiProvider\\Provider\\OpenAiProvider';

	if ( ! class_exists( 'QM_Collectors', false ) || ! class_exists( 'QM_Dispatcher', false ) ) {
		return;
	}

	if ( ! function_exists( 'wp_ai_client_prompt' ) || ! class_exists( $openai_provider_class ) ) {
		return;
	}

	require_once TNPA_PLUGIN_DIR . 'functions/helpers.php';
	require_once TNPA_PLUGIN_DIR . 'functions/query-monitor.php';
	require_once TNPA_PLUGIN_DIR . 'functions/ai.php';
	require_once TNPA_PLUGIN_DIR . 'functions/assets.php';
	require_once TNPA_PLUGIN_DIR . 'functions/admin.php';
}
