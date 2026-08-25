<?php
/**
 * GitHub release updater.
 *
 * @package TNPerformanceAdvisor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides native WordPress updates from public GitHub releases.
 */
final class TNPA_GitHub_Updater {

	private const OWNER             = 'cchatterton';
	private const REPO              = 'tn-performance-advisor';
	private const SLUG              = 'tn-performance-advisor';
	private const ASSET_NAME        = 'tn-performance-advisor.zip';
	private const RELEASE_TRANSIENT = 'tnpa_github_latest_release';
	private const ERROR_TRANSIENT   = 'tnpa_github_latest_release_error';
	private const CHECK_QUERY_ARG   = 'tnpa_check_updates';

	/**
	 * Prevents a forced request from repeatedly clearing a cache rebuilt in the same request.
	 *
	 * @var bool
	 */
	private static $forced_cache_cleared = false;

	/**
	 * Registers updater hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'add_update_data' ) );
		add_filter( 'site_transient_update_plugins', array( __CLASS__, 'add_update_data' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_information' ), 10, 3 );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta' ), 10, 2 );
		add_action( 'admin_init', array( __CLASS__, 'handle_manual_update_check' ) );
		add_action( 'admin_notices', array( __CLASS__, 'update_check_notice' ) );
		add_action( 'network_admin_notices', array( __CLASS__, 'update_check_notice' ) );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_cache_after_update' ), 10, 2 );
	}

	/**
	 * Adds an available release to WordPress plugin update data.
	 *
	 * @param mixed $transient WordPress plugin update transient.
	 * @return object
	 */
	public static function add_update_data( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}

		$release = self::get_latest_release();
		if ( empty( $release ) ) {
			return $transient;
		}

		$plugin_file          = plugin_basename( TNPA_PLUGIN_FILE );
		$transient->response  = isset( $transient->response ) && is_array( $transient->response ) ? $transient->response : array();
		$transient->no_update = isset( $transient->no_update ) && is_array( $transient->no_update ) ? $transient->no_update : array();

		unset( $transient->response[ $plugin_file ] );
		unset( $transient->no_update[ $plugin_file ] );

		if ( ! version_compare( $release['version'], TNPA_VERSION, '>' ) ) {
			return $transient;
		}

		$transient->response[ $plugin_file ] = (object) array(
			'id'           => self::repository_url(),
			'slug'         => self::SLUG,
			'plugin'       => $plugin_file,
			'new_version'  => $release['version'],
			'url'          => $release['release_url'],
			'package'      => $release['package'],
			'requires'     => '7.0',
			'requires_php' => '7.4',
		);

		return $transient;
	}

	/**
	 * Supplies data for the native plugin details modal.
	 *
	 * @param mixed  $result Existing result.
	 * @param string $action API action.
	 * @param object $args   API arguments.
	 * @return mixed
	 */
	public static function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}

		$release = self::get_latest_release();
		if ( empty( $release ) ) {
			return $result;
		}

		return (object) array(
			'name'          => 'TN Performance Advisor',
			'slug'          => self::SLUG,
			'version'       => $release['version'],
			'author'        => 'Techn',
			'homepage'      => self::repository_url(),
			'download_link' => $release['package'],
			'requires'      => '7.0',
			'requires_php'  => '7.4',
			'sections'      => array(
				'description' => esc_html__( 'Turns a sanitised Query Monitor capture into a prioritised performance action plan using the native WordPress AI Client.', 'tn-performance-advisor' ),
				'changelog'   => wpautop( esc_html( $release['body'] ) ),
			),
		);
	}

	/**
	 * Adds GitHub and manual update links to the plugin row.
	 *
	 * @param array<int, string> $links Existing row metadata.
	 * @param string             $file  Plugin basename.
	 * @return array<int, string>
	 */
	public static function plugin_row_meta( $links, $file ) {
		if ( plugin_basename( TNPA_PLUGIN_FILE ) !== $file ) {
			return $links;
		}

		$links[] = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( self::repository_url() ),
			esc_html__( 'GitHub', 'tn-performance-advisor' )
		);

		if ( current_user_can( 'update_plugins' ) ) {
			$check_url = wp_nonce_url(
				add_query_arg( self::CHECK_QUERY_ARG, '1', self::plugins_page_url() ),
				self::CHECK_QUERY_ARG
			);

			$links[] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $check_url ),
				esc_html__( 'Check for updates', 'tn-performance-advisor' )
			);
		}

		return $links;
	}

	/**
	 * Processes the nonce-protected plugin-row update check.
	 *
	 * @return void
	 */
	public static function handle_manual_update_check() {
		if ( '1' !== self::request_value( self::CHECK_QUERY_ARG ) ) {
			return;
		}

		check_admin_referer( self::CHECK_QUERY_ARG );

		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'You do not have permission to check for plugin updates.', 'tn-performance-advisor' ) );
		}

		self::clear_release_cache();
		self::$forced_cache_cleared = true;
		delete_site_transient( 'update_plugins' );

		if ( ! function_exists( 'wp_update_plugins' ) ) {
			require_once ABSPATH . 'wp-includes/update.php';
		}

		wp_update_plugins();
		$transient = self::add_update_data( get_site_transient( 'update_plugins' ) );
		set_site_transient( 'update_plugins', $transient );

		if ( get_site_transient( self::ERROR_TRANSIENT ) ) {
			$result = 'failed';
		} elseif ( isset( $transient->response[ plugin_basename( TNPA_PLUGIN_FILE ) ] ) ) {
			$result = 'available';
		} else {
			$result = 'current';
		}

		wp_safe_redirect( add_query_arg( 'tnpa_update_check', $result, self::plugins_page_url() ) );
		exit;
	}

	/**
	 * Displays a result from a manual update check.
	 *
	 * @return void
	 */
	public static function update_check_notice() {
		$result = self::request_value( 'tnpa_update_check' );
		$messages = array(
			'available' => array( 'success', __( 'A newer TN Performance Advisor release is available below.', 'tn-performance-advisor' ) ),
			'current'   => array( 'info', __( 'TN Performance Advisor is current.', 'tn-performance-advisor' ) ),
			'failed'    => array( 'error', __( 'TN Performance Advisor could not check GitHub. Try again later.', 'tn-performance-advisor' ) ),
		);

		if ( ! isset( $messages[ $result ] ) || ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $messages[ $result ][0] ),
			esc_html( $messages[ $result ][1] )
		);
	}

	/**
	 * Clears updater caches after this plugin is upgraded.
	 *
	 * @param WP_Upgrader $upgrader Upgrader instance.
	 * @param array       $options  Upgrade context.
	 * @return void
	 */
	public static function clear_cache_after_update( $upgrader, $options ) {
		unset( $upgrader );

		if ( 'plugin' !== ( $options['type'] ?? '' ) || 'update' !== ( $options['action'] ?? '' ) ) {
			return;
		}

		$plugins = isset( $options['plugins'] ) ? (array) $options['plugins'] : array();
		if ( ! empty( $options['plugin'] ) ) {
			$plugins[] = $options['plugin'];
		}

		if ( in_array( plugin_basename( TNPA_PLUGIN_FILE ), $plugins, true ) ) {
			self::clear_release_cache();
		}
	}

	/**
	 * Returns cached or freshly discovered release metadata.
	 *
	 * @return array<string, string>
	 */
	private static function get_latest_release() {
		$forced_check = self::is_forced_update_check();

		if ( $forced_check && ! self::$forced_cache_cleared ) {
			self::clear_release_cache();
			self::$forced_cache_cleared = true;
		}

		$cached = get_site_transient( self::RELEASE_TRANSIENT );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		if ( ! $forced_check && get_site_transient( self::ERROR_TRANSIENT ) ) {
			return array();
		}

		$release = self::release_from_manifest();
		if ( ! empty( $release ) ) {
			return self::cache_release( $release );
		}

		$release = self::release_from_redirect();
		if ( ! empty( $release ) ) {
			return self::cache_release( $release );
		}

		if ( self::is_rate_limited() ) {
			return array();
		}

		$release = self::release_from_api();
		if ( ! empty( $release ) ) {
			return self::cache_release( $release );
		}

		return array();
	}

	/**
	 * Fetches stable metadata from the repository-controlled manifest.
	 *
	 * @return array<string, string>
	 */
	private static function release_from_manifest() {
		$response = wp_remote_get(
			'https://raw.githubusercontent.com/' . self::OWNER . '/' . self::REPO . '/main/update.json',
			self::request_args()
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			self::record_rate_limit_if_needed( $response );
			return array();
		}

		$data    = json_decode( wp_remote_retrieve_body( $response ), true );
		$version = is_array( $data ) ? self::validate_version( $data['version'] ?? '' ) : '';

		if ( '' === $version ) {
			return array();
		}

		return self::normalise_release( $version, (string) ( $data['body'] ?? '' ) );
	}

	/**
	 * Reads the public latest-release redirect without using the GitHub API.
	 *
	 * @return array<string, string>
	 */
	private static function release_from_redirect() {
		$args                = self::request_args();
		$args['redirection'] = 0;
		$response            = wp_remote_get( self::repository_url() . '/releases/latest', $args );

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 429 === $code ) {
			self::record_error( array( 'type' => 'rate_limit', 'code' => $code ) );
			return array();
		}

		$location = wp_remote_retrieve_header( $response, 'location' );
		if ( ! is_string( $location ) || ! preg_match( '#/releases/tag/v?([^/?#]+)#i', $location, $matches ) ) {
			return array();
		}

		$version = self::validate_version( rawurldecode( $matches[1] ) );
		return '' !== $version ? self::normalise_release( $version, '' ) : array();
	}

	/**
	 * Uses the GitHub API only when the manifest and redirect are unavailable.
	 *
	 * @return array<string, string>
	 */
	private static function release_from_api() {
		$args = self::request_args();
		$args['headers']['Accept'] = 'application/vnd.github+json';
		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::OWNER . '/' . self::REPO . '/releases/latest',
			$args
		);

		if ( is_wp_error( $response ) ) {
			self::record_error( array( 'type' => 'wp_error', 'message' => $response->get_error_message() ) );
			return array();
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			self::record_error(
				array(
					'type'    => 429 === $code ? 'rate_limit' : 'http_error',
					'code'    => $code,
					'message' => wp_remote_retrieve_response_message( $response ),
					'body'    => substr( wp_strip_all_tags( wp_remote_retrieve_body( $response ) ), 0, 500 ),
				)
			);
			return array();
		}

		$data    = json_decode( wp_remote_retrieve_body( $response ), true );
		$version = is_array( $data ) ? self::validate_version( ltrim( (string) ( $data['tag_name'] ?? '' ), 'vV' ) ) : '';
		$package = '';

		foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
			if ( self::ASSET_NAME === ( $asset['name'] ?? '' ) && ! empty( $asset['browser_download_url'] ) ) {
				$package = esc_url_raw( (string) $asset['browser_download_url'] );
				break;
			}
		}

		if ( '' === $version || '' === $package ) {
			self::record_error( array( 'type' => 'json_error', 'message' => 'Incomplete release metadata.' ) );
			return array();
		}

		return array(
			'version'     => $version,
			'body'        => sanitize_textarea_field( (string) ( $data['body'] ?? '' ) ),
			'release_url' => esc_url_raw( (string) ( $data['html_url'] ?? self::repository_url() ) ),
			'package'     => $package,
		);
	}

	/**
	 * Constructs repository-controlled release URLs from a validated version.
	 *
	 * @param string $version Validated version.
	 * @param string $body    Release summary.
	 * @return array<string, string>
	 */
	private static function normalise_release( $version, $body ) {
		$tag = 'v' . $version;

		return array(
			'version'     => $version,
			'body'        => sanitize_textarea_field( $body ),
			'release_url' => self::repository_url() . '/releases/tag/' . rawurlencode( $tag ),
			'package'     => self::repository_url() . '/releases/download/' . rawurlencode( $tag ) . '/' . self::ASSET_NAME,
		);
	}

	/**
	 * Caches successful release data using hotfix-friendly durations.
	 *
	 * @param array<string, string> $release Release data.
	 * @return array<string, string>
	 */
	private static function cache_release( $release ) {
		$duration = version_compare( $release['version'], TNPA_VERSION, '>' ) ? 6 * HOUR_IN_SECONDS : 5 * MINUTE_IN_SECONDS;
		set_site_transient( self::RELEASE_TRANSIENT, $release, $duration );
		delete_site_transient( self::ERROR_TRANSIENT );

		return $release;
	}

	/**
	 * Returns common GitHub request arguments.
	 *
	 * @return array<string, mixed>
	 */
	private static function request_args() {
		return array(
			'timeout' => 10,
			'headers' => array(
				'User-Agent' => 'TN-Performance-Advisor/' . TNPA_VERSION,
			),
		);
	}

	/**
	 * Records a rate limit response encountered before the API fallback.
	 *
	 * @param array|WP_Error $response HTTP response or error.
	 * @return void
	 */
	private static function record_rate_limit_if_needed( $response ) {
		if ( ! is_wp_error( $response ) && 429 === (int) wp_remote_retrieve_response_code( $response ) ) {
			self::record_error( array( 'type' => 'rate_limit', 'code' => 429 ) );
		}
	}

	/**
	 * Returns whether a prior endpoint activated the short failure backoff.
	 *
	 * @return bool
	 */
	private static function is_rate_limited() {
		$error = get_site_transient( self::ERROR_TRANSIENT );

		return is_array( $error ) && 'rate_limit' === ( $error['type'] ?? '' );
	}

	/**
	 * Stores diagnostics separately from successful release state.
	 *
	 * @param array<string, mixed> $details Diagnostic details.
	 * @return void
	 */
	private static function record_error( $details ) {
		$details['checked_at'] = time();
		delete_site_transient( self::RELEASE_TRANSIENT );
		set_site_transient( self::ERROR_TRANSIENT, $details, 10 * MINUTE_IN_SECONDS );
	}

	/**
	 * Clears all updater-specific state.
	 *
	 * @return void
	 */
	private static function clear_release_cache() {
		delete_site_transient( self::RELEASE_TRANSIENT );
		delete_site_transient( self::ERROR_TRANSIENT );
	}

	/**
	 * Detects native and plugin-specific forced update requests.
	 *
	 * @return bool
	 */
	private static function is_forced_update_check() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return false;
		}

		$force_check = self::request_value( 'force-check' );
		$action      = self::request_value( 'action' );
		$action_two  = self::request_value( 'action2' );
		$actions     = array( 'update-selected', 'upgrade-plugin', 'do-plugin-upgrade' );

		return '1' === self::request_value( self::CHECK_QUERY_ARG )
			|| ( '' !== $force_check && '0' !== $force_check )
			|| in_array( $action, $actions, true )
			|| in_array( $action_two, $actions, true );
	}

	/**
	 * Reads a scalar request value from POST or GET.
	 *
	 * @param string $key Request key.
	 * @return string
	 */
	private static function request_value( $key ) {
		if ( isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ) {
			return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
		}

		if ( isset( $_GET[ $key ] ) && is_scalar( $_GET[ $key ] ) ) {
			return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
		}

		return '';
	}

	/**
	 * Validates a semantic release version.
	 *
	 * @param mixed $version Candidate version.
	 * @return string
	 */
	private static function validate_version( $version ) {
		$version = ltrim( (string) $version, 'vV' );

		return preg_match( '/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][0-9A-Za-z.-]+)?$/', $version ) ? $version : '';
	}

	/**
	 * Returns the public repository URL.
	 *
	 * @return string
	 */
	private static function repository_url() {
		return 'https://github.com/' . self::OWNER . '/' . self::REPO;
	}

	/**
	 * Returns the correct Plugins screen for the current site.
	 *
	 * @return string
	 */
	private static function plugins_page_url() {
		return is_multisite() ? network_admin_url( 'plugins.php' ) : admin_url( 'plugins.php' );
	}
}

TNPA_GitHub_Updater::init();
