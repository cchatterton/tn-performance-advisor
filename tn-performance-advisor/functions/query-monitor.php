<?php
/**
 * Query Monitor capture and sanitisation.
 *
 * @package TNPerformanceAdvisor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'shutdown', 'tnpa_capture_query_monitor_report', 8 );

/**
 * Captures the latest front-end request viewed by an administrator.
 *
 * Query Monitor dispatches at shutdown priority 9. Processing at priority 8
 * makes the same completed collector data available without changing its UI.
 *
 * @return void
 */
function tnpa_capture_query_monitor_report() {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ! is_user_logged_in() ) {
		return;
	}

	$last_error = error_get_last();
	$fatal_types = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR );
	if ( $last_error && in_array( $last_error['type'], $fatal_types, true ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'view_query_monitor' ) ) {
		return;
	}

	if ( ! class_exists( 'QM_Collectors', false ) || ! class_exists( 'QM_Dispatcher', false ) ) {
		return;
	}

	if ( ! QM_Dispatcher::user_can_view() ) {
		return;
	}

	try {
		$collectors = QM_Collectors::init();
		$collectors->process();
		$report = tnpa_build_query_monitor_report();
	} catch ( Throwable $error ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'TN Performance Advisor capture failed: ' . $error->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		return;
	}

	if ( empty( $report ) ) {
		return;
	}

	$user_id = get_current_user_id();
	update_user_meta( $user_id, tnpa_capture_meta_key(), $report );
	delete_user_meta( $user_id, tnpa_result_meta_key() );
}

/**
 * Builds a compact, sanitised report from selected Query Monitor collectors.
 *
 * @return array<string, mixed>
 */
function tnpa_build_query_monitor_report() {
	$request_path = '/';

	if ( isset( $_SERVER['REQUEST_URI'] ) ) {
		$parsed_path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
		if ( is_string( $parsed_path ) && '' !== $parsed_path ) {
			$request_path = $parsed_path;
		}
	}

	$report = array(
		'schema_version' => 1,
		'captured_at'    => gmdate( 'c' ),
		'request'        => array(
			'path' => tnpa_sanitise_request_path( $request_path ),
			'type' => tnpa_get_request_type(),
		),
		'overview'       => tnpa_capture_overview(),
		'database'       => tnpa_capture_database(),
		'http_requests'  => tnpa_capture_http_requests(),
		'php_errors'     => tnpa_capture_php_errors(),
		'assets'         => tnpa_capture_assets(),
		'cache'          => tnpa_capture_cache(),
		'environment'    => tnpa_capture_environment(),
	);

	return $report;
}

/**
 * Returns a non-sensitive description of the current request type.
 *
 * @return string
 */
function tnpa_get_request_type() {
	if ( is_front_page() ) {
		return 'front_page';
	}
	if ( is_home() ) {
		return 'posts_page';
	}
	if ( is_singular() ) {
		return 'singular';
	}
	if ( is_search() ) {
		return 'search';
	}
	if ( is_archive() ) {
		return 'archive';
	}
	if ( is_404() ) {
		return '404';
	}

	return 'front_end';
}

/**
 * Captures request time and memory metrics.
 *
 * @return array<string, int|float|null>
 */
function tnpa_capture_overview() {
	$collector = QM_Collectors::get( 'overview' );
	$data      = $collector ? $collector->get_data() : null;

	return array(
		'time_ms'             => isset( $data->time_taken ) ? round( (float) $data->time_taken * 1000, 2 ) : null,
		'time_limit_seconds'  => isset( $data->time_limit ) ? (int) $data->time_limit : null,
		'memory_bytes'        => isset( $data->memory ) ? (int) $data->memory : null,
		'memory_limit_bytes'  => isset( $data->memory_limit ) ? (int) $data->memory_limit : null,
		'memory_usage_percent' => isset( $data->memory_usage ) ? round( (float) $data->memory_usage, 2 ) : null,
	);
}

/**
 * Captures sanitised database query groups.
 *
 * @return array<string, mixed>
 */
function tnpa_capture_database() {
	$collector = QM_Collectors::get( 'db_queries' );
	$data      = $collector ? $collector->get_data() : null;
	$rows      = ( $data && is_array( $data->rows ) ) ? $data->rows : array();
	$groups    = array();
	$total_ms  = 0.0;

	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) || ! isset( $row['sql'], $row['ltime'] ) ) {
			continue;
		}

		$sql         = tnpa_sanitise_sql( $row['sql'] );
		$time_ms     = round( (float) $row['ltime'] * 1000, 3 );
		$attribution = tnpa_get_row_attribution( $row );
		$key         = md5( $sql . '|' . $attribution['component'] . '|' . $attribution['caller'] );
		$total_ms   += $time_ms;

		if ( ! isset( $groups[ $key ] ) ) {
			$groups[ $key ] = array(
				'query_shape'  => $sql,
				'component'    => $attribution['component'],
				'caller'       => $attribution['caller'],
				'count'        => 0,
				'total_time_ms' => 0.0,
				'max_time_ms'  => 0.0,
			);
		}

		$groups[ $key ]['count']++;
		$groups[ $key ]['total_time_ms'] = round( $groups[ $key ]['total_time_ms'] + $time_ms, 3 );
		$groups[ $key ]['max_time_ms']   = max( $groups[ $key ]['max_time_ms'], $time_ms );
	}

	$groups = array_values( $groups );
	usort( $groups, 'tnpa_sort_by_total_time' );

	return array(
		'total_queries'       => isset( $data->total_qs ) ? (int) $data->total_qs : count( $rows ),
		'total_query_time_ms' => round( $total_ms, 3 ),
		'query_errors'        => ( $data && is_array( $data->errors ) ) ? count( $data->errors ) : 0,
		'top_query_groups'    => array_slice( $groups, 0, 25 ),
	);
}

/**
 * Returns a query's caller and component without exposing file paths.
 *
 * @param array<string, mixed> $row Query Monitor row.
 * @return array{component: string, caller: string}
 */
function tnpa_get_row_attribution( $row ) {
	$component = 'unknown';
	$caller    = 'unknown';

	if ( isset( $row['trace'] ) && is_object( $row['trace'] ) ) {
		if ( method_exists( $row['trace'], 'get_component' ) ) {
			$trace_component = $row['trace']->get_component();
			if ( is_object( $trace_component ) && isset( $trace_component->type, $trace_component->context ) ) {
				$component = sanitize_key( $trace_component->type ) . ':' . sanitize_key( $trace_component->context );
			}
		}

		if ( method_exists( $row['trace'], 'get_caller' ) ) {
			$trace_caller = $row['trace']->get_caller();
			if ( is_object( $trace_caller ) && isset( $trace_caller->id ) ) {
				$caller = tnpa_sanitise_diagnostic_message( $trace_caller->id );
			}
		}
	} elseif ( isset( $row['stack'] ) && is_array( $row['stack'] ) && ! empty( $row['stack'] ) ) {
		$caller = tnpa_sanitise_diagnostic_message( end( $row['stack'] ) );
	}

	return array(
		'component' => $component,
		'caller'    => $caller,
	);
}

/**
 * Captures external HTTP request metrics without URLs, headers, or bodies.
 *
 * @return array<string, mixed>
 */
function tnpa_capture_http_requests() {
	$collector = QM_Collectors::get( 'http' );
	$data      = $collector ? $collector->get_data() : null;
	$requests  = ( $data && is_array( $data->http ) ) ? $data->http : array();
	$items     = array();

	foreach ( $requests as $request ) {
		if ( ! is_object( $request ) ) {
			continue;
		}

		$items[] = array(
			'host'       => isset( $request->host ) ? sanitize_text_field( $request->host ) : '',
			'method'     => isset( $request->args['method'] ) ? sanitize_key( $request->args['method'] ) : '',
			'time_ms'    => isset( $request->ltime ) ? round( (float) $request->ltime * 1000, 2 ) : 0,
			'result'     => isset( $request->type ) ? sanitize_text_field( $request->type ) : 'unknown',
			'is_local'   => ! empty( $request->local ),
			'intercepted' => ! empty( $request->intercepted ),
		);
	}

	usort( $items, 'tnpa_sort_http_requests' );

	return array(
		'count'         => count( $items ),
		'total_time_ms' => isset( $data->ltime ) ? round( (float) $data->ltime * 1000, 2 ) : 0,
		'slowest'       => array_slice( $items, 0, 15 ),
	);
}

/**
 * Sorts HTTP requests by elapsed time.
 *
 * @param array<string, mixed> $first  First request.
 * @param array<string, mixed> $second Second request.
 * @return int
 */
function tnpa_sort_http_requests( $first, $second ) {
	return (float) $second['time_ms'] <=> (float) $first['time_ms'];
}

/**
 * Captures sanitised PHP errors.
 *
 * @return array<int, array<string, mixed>>
 */
function tnpa_capture_php_errors() {
	$collector = QM_Collectors::get( 'php_errors' );
	$data      = $collector ? $collector->get_data() : null;
	$errors    = ( $data && is_array( $data->errors ) ) ? $data->errors : array();
	$items     = array();

	foreach ( $errors as $error ) {
		if ( ! is_object( $error ) ) {
			continue;
		}

		$component = 'unknown';
		if ( isset( $error->trace ) && is_object( $error->trace ) && method_exists( $error->trace, 'get_component' ) ) {
			$trace_component = $error->trace->get_component();
			if ( is_object( $trace_component ) && isset( $trace_component->type, $trace_component->context ) ) {
				$component = sanitize_key( $trace_component->type ) . ':' . sanitize_key( $trace_component->context );
			}
		}

		$items[] = array(
			'level'      => isset( $error->level ) ? sanitize_key( $error->level ) : 'unknown',
			'component'  => $component,
			'message'    => isset( $error->message ) ? tnpa_sanitise_diagnostic_message( $error->message ) : '',
			'count'      => isset( $error->count ) ? (int) $error->count : 1,
			'suppressed' => ! empty( $error->suppressed ),
		);
	}

	return array_slice( $items, 0, 20 );
}

/**
 * Captures script and stylesheet counts and warnings.
 *
 * @return array<string, mixed>
 */
function tnpa_capture_assets() {
	$result = array();

	foreach ( array( 'scripts' => 'assets_scripts', 'styles' => 'assets_styles' ) as $type => $collector_id ) {
		$collector = QM_Collectors::get( $collector_id );
		$data      = $collector ? $collector->get_data() : null;
		$assets    = ( $data && is_array( $data->assets ) ) ? $data->assets : array();
		$warnings  = array();

		foreach ( $assets as $asset ) {
			if ( ! is_array( $asset ) || empty( $asset['warning'] ) ) {
				continue;
			}

			$warnings[] = array(
				'handle'   => isset( $asset['handle'] ) ? sanitize_key( $asset['handle'] ) : '',
				'position' => isset( $asset['position'] ) ? sanitize_key( $asset['position'] ) : '',
			);
		}

		$result[ $type ] = array(
			'count'    => count( $assets ),
			'warnings' => array_slice( $warnings, 0, 20 ),
		);
	}

	return $result;
}

/**
 * Captures object and opcode cache status.
 *
 * @return array<string, bool|int|null>
 */
function tnpa_capture_cache() {
	$collector = QM_Collectors::get( 'cache' );
	$data      = $collector ? $collector->get_data() : null;

	return array(
		'has_object_cache'     => $data && ! empty( $data->has_object_cache ),
		'has_opcode_cache'     => $data && ! empty( $data->has_opcode_cache ),
		'cache_hit_percentage' => isset( $data->cache_hit_percentage ) ? (int) $data->cache_hit_percentage : null,
	);
}

/**
 * Captures non-sensitive platform version information.
 *
 * @return array<string, string>
 */
function tnpa_capture_environment() {
	$collector = QM_Collectors::get( 'environment' );
	$data      = $collector ? $collector->get_data() : null;

	return array(
		'wordpress_version' => isset( $data->wp['version'] ) ? sanitize_text_field( $data->wp['version'] ) : get_bloginfo( 'version' ),
		'php_version'       => isset( $data->php['version'] ) ? sanitize_text_field( $data->php['version'] ) : PHP_VERSION,
		'database_version'  => isset( $data->db['info']['server-version'] ) ? sanitize_text_field( $data->db['info']['server-version'] ) : '',
		'environment_type'  => isset( $data->wp['environment_type'] ) ? sanitize_key( $data->wp['environment_type'] ) : wp_get_environment_type(),
	);
}
