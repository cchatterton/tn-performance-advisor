<?php
/**
 * Performance Advisor settings page and actions.
 *
 * @package TNPerformanceAdvisor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'tnpa_register_options_page' );
add_action( 'admin_bar_menu', 'tnpa_add_admin_bar_analyse_link', 1000 );
add_action( 'admin_post_tnpa_analyse', 'tnpa_handle_analyse' );
add_action( 'admin_post_tnpa_next', 'tnpa_handle_next_recommendation' );
add_action( 'admin_post_tnpa_clear', 'tnpa_handle_clear' );

/**
 * Registers Settings > Performance Advisor.
 *
 * @return void
 */
function tnpa_register_options_page() {
	if ( ! tnpa_current_user_is_allowed() ) {
		return;
	}

	add_options_page(
		__( 'Performance Advisor', 'tn-performance-advisor' ),
		__( 'Performance Advisor', 'tn-performance-advisor' ),
		'manage_options',
		'tn-performance-advisor',
		'tnpa_render_options_page'
	);
}

/**
 * Adds the analysis action to the WordPress admin bar.
 *
 * The displayed page has finished loading before the administrator can click
 * this link, so its Query Monitor capture is already available to analyse.
 *
 * @param WP_Admin_Bar $admin_bar WordPress admin bar instance.
 * @return void
 */
function tnpa_add_admin_bar_analyse_link( $admin_bar ) {
	if ( ! tnpa_current_user_is_allowed() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( ! current_user_can( 'view_query_monitor' ) || ! tnpa_has_openai_connector() ) {
		return;
	}

	$analyse_url = wp_nonce_url(
		add_query_arg(
			array(
				'action' => 'tnpa_analyse',
			),
			admin_url( 'admin-post.php' )
		),
		'tnpa_analyse_capture'
	);

	$admin_bar->add_node(
		array(
			'id'    => 'tnpa-analyse-performance',
			'title' => esc_html__( 'Analyse Performance', 'tn-performance-advisor' ),
			'href'  => $analyse_url,
			'meta'  => array(
				'title' => esc_attr__( 'Analyse the latest captured request with Performance Advisor', 'tn-performance-advisor' ),
			),
		)
	);
}

/**
 * Renders the options page.
 *
 * @return void
 */
function tnpa_render_options_page() {
	if ( ! current_user_can( 'manage_options' ) || ! tnpa_current_user_is_allowed() ) {
		wp_die( esc_html__( 'You are not allowed to access this page.', 'tn-performance-advisor' ) );
	}

	$capture               = tnpa_get_capture( get_current_user_id() );
	$result                = tnpa_get_result( get_current_user_id() );
	if ( empty( $capture ) && ! empty( $result['_tnpa_capture'] ) && is_array( $result['_tnpa_capture'] ) ) {
		$capture = $result['_tnpa_capture'];
	}
	$has_query_monitor     = class_exists( 'QM_Collectors', false );
	$has_openai_connector  = tnpa_has_openai_connector();
	$status                = isset( $_GET['tnpa_status'] ) ? sanitize_key( wp_unslash( $_GET['tnpa_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	require TNPA_PLUGIN_DIR . 'templates/options-page.php';
}

/**
 * Handles a manual AI analysis request.
 *
 * @return void
 */
function tnpa_handle_analyse() {
	if ( ! current_user_can( 'manage_options' ) || ! tnpa_current_user_is_allowed() ) {
		wp_die( esc_html__( 'You are not allowed to perform this action.', 'tn-performance-advisor' ) );
	}

	check_admin_referer( 'tnpa_analyse_capture' );

	$user_id = get_current_user_id();
	$capture = tnpa_get_capture( $user_id );

	if ( empty( $capture ) ) {
		tnpa_redirect_to_options_page( 'no_capture' );
	}

	$result = tnpa_analyse_capture( $capture );
	if ( is_wp_error( $result ) ) {
		tnpa_redirect_to_options_page( $result->get_error_code() );
	}

	$result['_tnpa_schema_version']           = TNPA_RESULT_SCHEMA_VERSION;
	$result['_tnpa_capture']                  = $capture;
	$result['_tnpa_recommendation_number']    = 1;
	$result['_tnpa_excluded_recommendations'] = tnpa_add_result_to_exclusions( array(), $result );
	update_user_meta( $user_id, tnpa_result_meta_key(), $result );
	tnpa_redirect_to_options_page( 'success' );
}

/**
 * Requests the next highest-value recommendation for the saved capture.
 *
 * @return void
 */
function tnpa_handle_next_recommendation() {
	if ( ! current_user_can( 'manage_options' ) || ! tnpa_current_user_is_allowed() ) {
		wp_die( esc_html__( 'You are not allowed to perform this action.', 'tn-performance-advisor' ) );
	}

	check_admin_referer( 'tnpa_next_recommendation' );

	$user_id         = get_current_user_id();
	$previous_result = tnpa_get_result( $user_id );
	$capture         = tnpa_get_capture( $user_id );

	if ( empty( $capture ) && ! empty( $previous_result['_tnpa_capture'] ) && is_array( $previous_result['_tnpa_capture'] ) ) {
		$capture = $previous_result['_tnpa_capture'];
	}

	if ( empty( $capture ) || empty( $previous_result ) ) {
		tnpa_redirect_to_options_page( 'no_capture' );
	}

	$excluded = isset( $previous_result['_tnpa_excluded_recommendations'] ) && is_array( $previous_result['_tnpa_excluded_recommendations'] )
		? $previous_result['_tnpa_excluded_recommendations']
		: array();
	$excluded = tnpa_add_result_to_exclusions( $excluded, $previous_result );
	$result   = tnpa_analyse_capture( $capture, $excluded );

	if ( is_wp_error( $result ) ) {
		tnpa_redirect_to_options_page( $result->get_error_code() );
	}

	$updated_excluded = tnpa_add_result_to_exclusions( $excluded, $result );
	if ( 'improvement_found' === $result['recommendation_status'] && count( $updated_excluded ) === count( $excluded ) ) {
		tnpa_redirect_to_options_page( 'tnpa_invalid_ai_response' );
	}

	$result['_tnpa_schema_version']           = TNPA_RESULT_SCHEMA_VERSION;
	$result['_tnpa_capture']                  = $capture;
	$result['_tnpa_recommendation_number']    = (int) ( $previous_result['_tnpa_recommendation_number'] ?? 1 ) + 1;
	$result['_tnpa_excluded_recommendations'] = $updated_excluded;
	update_user_meta( $user_id, tnpa_result_meta_key(), $result );
	tnpa_redirect_to_options_page( 'improvement_found' === $result['recommendation_status'] ? 'next_success' : 'no_further_recommendation' );
}

/**
 * Adds the current recommendation to the compact exclusion history.
 *
 * @param array<int, array<string, mixed>> $excluded Existing exclusions.
 * @param array<string, mixed>             $result   Analysis result.
 * @return array<int, array<string, mixed>>
 */
function tnpa_add_result_to_exclusions( $excluded, $result ) {
	if ( 'improvement_found' !== ( $result['recommendation_status'] ?? '' ) || empty( $result['recommendations'][0] ) || ! is_array( $result['recommendations'][0] ) ) {
		return $excluded;
	}

	$recommendation = $result['recommendations'][0];
	$entry          = array(
		'title'          => (string) ( $recommendation['title'] ?? '' ),
		'change_to_make' => (string) ( $recommendation['change_to_make'] ?? '' ),
		'evidence'       => isset( $recommendation['evidence'] ) && is_array( $recommendation['evidence'] ) ? array_slice( $recommendation['evidence'], 0, 5 ) : array(),
	);

	if ( ! in_array( $entry, $excluded, true ) ) {
		$excluded[] = $entry;
	}

	return $excluded;
}

/**
 * Clears the current user's capture and recommendations.
 *
 * @return void
 */
function tnpa_handle_clear() {
	if ( ! current_user_can( 'manage_options' ) || ! tnpa_current_user_is_allowed() ) {
		wp_die( esc_html__( 'You are not allowed to perform this action.', 'tn-performance-advisor' ) );
	}

	check_admin_referer( 'tnpa_clear_report' );

	$user_id = get_current_user_id();
	delete_user_meta( $user_id, tnpa_capture_meta_key() );
	delete_user_meta( $user_id, tnpa_result_meta_key() );
	tnpa_redirect_to_options_page( 'cleared' );
}

/**
 * Redirects back to the options page with a known status code.
 *
 * @param string $status Status code.
 * @return void
 */
function tnpa_redirect_to_options_page( $status ) {
	$url = add_query_arg(
		array(
			'page'        => 'tn-performance-advisor',
			'tnpa_status' => sanitize_key( $status ),
		),
		admin_url( 'options-general.php' )
	);

	wp_safe_redirect( $url );
	exit;
}

/**
 * Returns a user-facing notice for a known status code.
 *
 * @param string $status Status code.
 * @return array{type: string, message: string}|array<empty>
 */
function tnpa_get_admin_notice( $status ) {
	$notices = array(
		'success'                  => array( 'type' => 'success', 'message' => __( 'Performance analysis completed.', 'tn-performance-advisor' ) ),
		'next_success'             => array( 'type' => 'success', 'message' => __( 'The next performance recommendation was generated from the same capture.', 'tn-performance-advisor' ) ),
		'no_further_recommendation' => array( 'type' => 'success', 'message' => __( 'No further distinct, evidence-backed performance recommendation remains for this capture.', 'tn-performance-advisor' ) ),
		'cleared'                  => array( 'type' => 'success', 'message' => __( 'The saved capture and recommendations were cleared.', 'tn-performance-advisor' ) ),
		'no_capture'               => array( 'type' => 'warning', 'message' => __( 'No eligible capture is available yet. Visit the front-end page or wp-admin screen you want to test, then select Analyse Performance in the admin bar.', 'tn-performance-advisor' ) ),
		'tnpa_openai_unavailable'  => array( 'type' => 'error', 'message' => __( 'The OpenAI connector is unavailable. Configure it under Settings > Connectors.', 'tn-performance-advisor' ) ),
		'tnpa_ai_disabled'         => array( 'type' => 'error', 'message' => __( 'AI features are disabled in this WordPress environment.', 'tn-performance-advisor' ) ),
		'tnpa_invalid_capture'     => array( 'type' => 'error', 'message' => __( 'The performance capture could not be prepared for analysis.', 'tn-performance-advisor' ) ),
		'tnpa_ai_request_failed'   => array( 'type' => 'error', 'message' => __( 'OpenAI could not analyse the capture. Check the connector and try again.', 'tn-performance-advisor' ) ),
		'tnpa_invalid_ai_response' => array( 'type' => 'error', 'message' => __( 'OpenAI returned an unexpected response. Please try again.', 'tn-performance-advisor' ) ),
	);

	return isset( $notices[ $status ] ) ? $notices[ $status ] : array();
}
