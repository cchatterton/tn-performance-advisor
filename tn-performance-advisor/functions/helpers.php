<?php
/**
 * Shared plugin helpers.
 *
 * @package TNPerformanceAdvisor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the user meta key used for a Query Monitor capture.
 *
 * @return string
 */
function tnpa_capture_meta_key() {
	return 'tnpa_latest_capture';
}

/**
 * Returns the user meta key used for AI recommendations.
 *
 * @return string
 */
function tnpa_result_meta_key() {
	return 'tnpa_latest_result';
}

/**
 * Returns whether the current user's email belongs to an approved organisation.
 *
 * @return bool
 */
function tnpa_current_user_is_allowed() {
	if ( ! is_user_logged_in() ) {
		return false;
	}

	$user = wp_get_current_user();
	if ( ! is_object( $user ) || empty( $user->user_email ) || ! is_string( $user->user_email ) ) {
		return false;
	}

	$email_parts = explode( '@', strtolower( trim( $user->user_email ) ) );
	if ( 2 !== count( $email_parts ) ) {
		return false;
	}

	return in_array( $email_parts[1], array( 'alphasys.com.au', 'techn.com.au' ), true );
}

/**
 * Returns the latest capture for a user.
 *
 * @param int $user_id User ID.
 * @return array<string, mixed>
 */
function tnpa_get_capture( $user_id ) {
	$capture = get_user_meta( $user_id, tnpa_capture_meta_key(), true );

	if ( ! is_array( $capture ) || TNPA_CAPTURE_SCHEMA_VERSION !== ( $capture['schema_version'] ?? 0 ) ) {
		return array();
	}

	return $capture;
}

/**
 * Returns the latest result for a user.
 *
 * @param int $user_id User ID.
 * @return array<string, mixed>
 */
function tnpa_get_result( $user_id ) {
	$result = get_user_meta( $user_id, tnpa_result_meta_key(), true );

	if ( ! is_array( $result ) || TNPA_RESULT_SCHEMA_VERSION !== ( $result['_tnpa_schema_version'] ?? 0 ) ) {
		return array();
	}

	return $result;
}

/**
 * Returns the WordPress Connectors settings URL.
 *
 * @return string
 */
function tnpa_get_connectors_url() {
	return admin_url( 'options-connectors.php' );
}

/**
 * Returns whether the required WordPress AI integration is registered.
 *
 * This intentionally does not read or expose the connector credential.
 *
 * @return bool
 */
function tnpa_has_openai_connector() {
	if ( ! function_exists( 'wp_ai_client_prompt' ) || ! function_exists( 'wp_is_connector_registered' ) ) {
		return false;
	}

	return wp_is_connector_registered( 'openai' );
}

/**
 * Sanitises a message before it is included in an AI prompt.
 *
 * @param string $message Raw diagnostic message.
 * @return string
 */
function tnpa_sanitise_diagnostic_message( $message ) {
	$message = wp_strip_all_tags( (string) $message );

	if ( defined( 'ABSPATH' ) ) {
		$message = str_replace( wp_normalize_path( ABSPATH ), '[wordpress]/', wp_normalize_path( $message ) );
	}

	if ( defined( 'WP_CONTENT_DIR' ) ) {
		$message = str_replace( wp_normalize_path( WP_CONTENT_DIR ), '[content]', wp_normalize_path( $message ) );
	}

	$message = preg_replace( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email]', $message );
	$message = preg_replace( '/(https?:\/\/[^\s?#]+)(?:\?[^\s#]*)?(?:#[^\s]*)?/i', '$1', $message );
	$message = preg_replace( '/([?&][A-Za-z0-9_\-]+=)[^&\s]*/', '$1[redacted]', $message );
	$message = preg_replace( '/\b(?:sk|rk|pk)-[A-Za-z0-9_\-]{12,}\b/', '[credential]', $message );
	$message = preg_replace( '/\b[0-9a-f]{32,}\b/i', '[token]', $message );
	$message = preg_replace( '/\s+/', ' ', (string) $message );

	return substr( trim( (string) $message ), 0, 300 );
}

/**
 * Replaces SQL values with placeholders while preserving its useful shape.
 *
 * @param string $sql SQL statement.
 * @return string
 */
function tnpa_sanitise_sql( $sql ) {
	$sql = preg_replace( '#/\*.*?\*/#s', ' ', (string) $sql );
	$sql = preg_replace( "/'(?:''|\\\\.|[^'])*'/s", '?', (string) $sql );
	$sql = preg_replace( '/"(?:""|\\\\.|[^"])*"/s', '?', (string) $sql );
	$sql = preg_replace( '/\b(?:0x[0-9a-f]+|\d+(?:\.\d+)?)\b/i', '?', (string) $sql );
	$sql = preg_replace( '/\s+/', ' ', (string) $sql );

	return substr( trim( (string) $sql ), 0, 800 );
}

/**
 * Removes identifiers and personal data from a request path.
 *
 * @param string $path Request path.
 * @return string
 */
function tnpa_sanitise_request_path( $path ) {
	$path = preg_replace( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email]', (string) $path );
	$path = preg_replace( '/\b[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\b/i', '[id]', (string) $path );
	$path = preg_replace( '#/(?:(?:\d{5,})|(?:[0-9a-f]{24,}))(?=/|$)#i', '/[id]', (string) $path );
	$path = preg_replace( '#/{2,}#', '/', (string) $path );

	return substr( sanitize_text_field( (string) $path ), 0, 300 );
}

/**
 * Sorts report rows by total time, highest first.
 *
 * @param array<string, mixed> $first  First row.
 * @param array<string, mixed> $second Second row.
 * @return int
 */
function tnpa_sort_by_total_time( $first, $second ) {
	return (float) $second['total_time_ms'] <=> (float) $first['total_time_ms'];
}
