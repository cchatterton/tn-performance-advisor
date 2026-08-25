<?php
/**
 * Performance Advisor options page.
 *
 * @package TNPerformanceAdvisor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$notice = tnpa_get_admin_notice( $status );
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Performance Advisor', 'tn-performance-advisor' ); ?></h1>

	<?php if ( ! empty( $notice ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
			<p><?php echo esc_html( $notice['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<p>
		<?php esc_html_e( 'Visit the front-end page you want to test while logged in, then select Analyse Performance in the admin bar. Performance Advisor will analyse that page and return you here.', 'tn-performance-advisor' ); ?>
	</p>

	<?php if ( ! $has_query_monitor ) : ?>
		<div class="notice notice-error inline">
			<p><?php esc_html_e( 'Query Monitor must be active before a performance capture can be created.', 'tn-performance-advisor' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! $has_openai_connector ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<?php esc_html_e( 'The native OpenAI connector is not available.', 'tn-performance-advisor' ); ?>
				<a href="<?php echo esc_url( tnpa_get_connectors_url() ); ?>"><?php esc_html_e( 'Open Connectors', 'tn-performance-advisor' ); ?></a>
			</p>
		</div>
	<?php else : ?>
		<p>
			<strong><?php esc_html_e( 'AI connection:', 'tn-performance-advisor' ); ?></strong>
			<?php esc_html_e( 'OpenAI via the native WordPress AI Client.', 'tn-performance-advisor' ); ?>
			<a href="<?php echo esc_url( tnpa_get_connectors_url() ); ?>"><?php esc_html_e( 'Manage connector', 'tn-performance-advisor' ); ?></a>
		</p>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Latest capture', 'tn-performance-advisor' ); ?></h2>

	<?php if ( empty( $capture ) ) : ?>
		<p><?php esc_html_e( 'No front-end request has been captured yet.', 'tn-performance-advisor' ); ?></p>
	<?php else : ?>
		<table class="widefat striped tnpa-capture-table">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Page', 'tn-performance-advisor' ); ?></th>
					<td><code><?php echo esc_html( $capture['request']['path'] ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Captured', 'tn-performance-advisor' ); ?></th>
					<td><?php echo esc_html( wp_date( 'Y-m-d H:i:s T', strtotime( $capture['captured_at'] ) ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Request time', 'tn-performance-advisor' ); ?></th>
					<td><?php echo esc_html( number_format_i18n( (float) $capture['overview']['time_ms'], 2 ) ); ?> ms</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Database queries', 'tn-performance-advisor' ); ?></th>
					<td><?php echo esc_html( number_format_i18n( (int) $capture['database']['total_queries'] ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Database time', 'tn-performance-advisor' ); ?></th>
					<td><?php echo esc_html( number_format_i18n( (float) $capture['database']['total_query_time_ms'], 2 ) ); ?> ms</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'HTTP requests', 'tn-performance-advisor' ); ?></th>
					<td><?php echo esc_html( number_format_i18n( (int) $capture['http_requests']['count'] ) ); ?></td>
				</tr>
			</tbody>
		</table>

		<div class="tnpa-actions">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tnpa_clear">
				<?php wp_nonce_field( 'tnpa_clear_report' ); ?>
				<?php submit_button( __( 'Clear Report', 'tn-performance-advisor' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $result ) ) : ?>
		<hr>
		<h2><?php esc_html_e( 'Recommendations', 'tn-performance-advisor' ); ?></h2>
		<p><?php echo esc_html( $result['summary'] ); ?></p>

		<?php foreach ( $result['recommendations'] as $recommendation ) : ?>
			<div class="card tnpa-recommendation">
				<h3>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: recommendation priority, 2: recommendation title. */
							__( 'Priority %1$d: %2$s', 'tn-performance-advisor' ),
							(int) $recommendation['priority'],
							$recommendation['title']
						)
					);
					?>
				</h3>
				<p>
					<strong><?php esc_html_e( 'Impact:', 'tn-performance-advisor' ); ?></strong>
					<?php echo esc_html( ucfirst( $recommendation['impact'] ) ); ?>
					&middot;
					<strong><?php esc_html_e( 'Confidence:', 'tn-performance-advisor' ); ?></strong>
					<?php echo esc_html( ucfirst( $recommendation['confidence'] ) ); ?>
				</p>

				<h4><?php esc_html_e( 'Evidence', 'tn-performance-advisor' ); ?></h4>
				<ul>
					<?php foreach ( $recommendation['evidence'] as $item ) : ?>
						<li><?php echo esc_html( $item ); ?></li>
					<?php endforeach; ?>
				</ul>

				<p><?php echo esc_html( $recommendation['why_it_matters'] ); ?></p>

				<h4><?php esc_html_e( 'How to fix it', 'tn-performance-advisor' ); ?></h4>
				<ol>
					<?php foreach ( $recommendation['instructions'] as $item ) : ?>
						<li><?php echo esc_html( $item ); ?></li>
					<?php endforeach; ?>
				</ol>

				<h4><?php esc_html_e( 'Verify the improvement', 'tn-performance-advisor' ); ?></h4>
				<ul>
					<?php foreach ( $recommendation['verification'] as $item ) : ?>
						<li><?php echo esc_html( $item ); ?></li>
					<?php endforeach; ?>
				</ul>

				<?php if ( '' !== $recommendation['caution'] ) : ?>
					<p><strong><?php esc_html_e( 'Caution:', 'tn-performance-advisor' ); ?></strong> <?php echo esc_html( $recommendation['caution'] ); ?></p>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>

		<h3><?php esc_html_e( 'What to capture next', 'tn-performance-advisor' ); ?></h3>
		<p><?php echo esc_html( $result['next_capture_suggestion'] ); ?></p>
	<?php endif; ?>
</div>
