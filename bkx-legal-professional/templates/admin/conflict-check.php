<?php
/**
 * Admin Conflict Check Template.
 *
 * @package BookingX\LegalProfessional
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BookingX\LegalProfessional\Services\ConflictCheckService;

$conflict_service = ConflictCheckService::get_instance();

// Check if running a new check.
$run_check = isset( $_GET['run_check'] ) && isset( $_GET['matter_id'] );
$matter_id = isset( $_GET['matter_id'] ) ? absint( $_GET['matter_id'] ) : 0;

// Get matters for dropdown.
$matters = get_posts( array(
	'post_type'      => 'bkx_matter',
	'posts_per_page' => -1,
	'orderby'        => 'date',
	'order'          => 'DESC',
) );
?>
<div class="wrap bkx-conflict-check">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Conflict Check', 'bkx-legal-professional' ); ?></h1>

	<!-- Matter Selection -->
	<div class="bkx-conflict-form">
		<form method="get">
			<input type="hidden" name="post_type" value="bkx_booking">
			<input type="hidden" name="page" value="bkx-conflict-check">

			<select name="matter_id" class="regular-text">
				<option value=""><?php esc_html_e( '— Select Matter —', 'bkx-legal-professional' ); ?></option>
				<?php foreach ( $matters as $matter ) : ?>
					<?php $number = get_post_meta( $matter->ID, '_bkx_matter_number', true ); ?>
					<option value="<?php echo esc_attr( $matter->ID ); ?>" <?php selected( $matter_id, $matter->ID ); ?>>
						<?php echo esc_html( $number . ' - ' . $matter->post_title ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<button type="submit" name="run_check" value="1" class="button button-primary"><?php esc_html_e( 'Run Conflict Check', 'bkx-legal-professional' ); ?></button>
		</form>
	</div>

	<?php if ( $run_check && $matter_id ) : ?>
		<?php
		$matter = get_post( $matter_id );
		if ( $matter ) :
			// Get client info.
			$client_id = get_post_meta( $matter_id, '_bkx_client_id', true );
			$client    = get_user_by( 'id', $client_id );

			// Get parties.
			$parties = $conflict_service->get_parties( $matter_id );

			$opposing = array_filter( $parties, function( $p ) {
				return 'opposing' === $p['relationship'];
			} );

			$related = array_filter( $parties, function( $p ) {
				return 'related' === $p['relationship'];
			} );

			// Run check.
			$results = $conflict_service->run_check( array(
				'client_name'       => $client ? $client->display_name : '',
				'client_email'      => $client ? $client->user_email : '',
				'opposing_parties'  => array_map( function( $p ) {
					return array( 'name' => $p['party_name'], 'relationship' => 'opposing' );
				}, $opposing ),
				'related_parties'   => array_map( function( $p ) {
					return array( 'name' => $p['party_name'], 'relationship' => 'related' );
				}, $related ),
				'description'       => $matter->post_content,
				'exclude_matter_id' => $matter_id,
			) );

			// Save check.
			$conflict_service->save_check( $matter_id, $results );
		?>
		<div class="bkx-conflict-results">
			<h2><?php esc_html_e( 'Conflict Check Results', 'bkx-legal-professional' ); ?></h2>
			<p><strong><?php esc_html_e( 'Matter:', 'bkx-legal-professional' ); ?></strong> <?php echo esc_html( $matter->post_title ); ?></p>
			<p><strong><?php esc_html_e( 'Client:', 'bkx-legal-professional' ); ?></strong> <?php echo esc_html( $client ? $client->display_name : 'N/A' ); ?></p>

			<!-- Status Banner -->
			<?php if ( 'clear' === $results['status'] ) : ?>
				<div class="bkx-conflict-clear">
					<h3><?php esc_html_e( 'No Conflicts Found', 'bkx-legal-professional' ); ?></h3>
					<p><?php esc_html_e( 'The conflict check did not identify any potential conflicts of interest.', 'bkx-legal-professional' ); ?></p>
				</div>
			<?php elseif ( 'potential' === $results['status'] ) : ?>
				<div class="bkx-conflict-potential">
					<h3><?php esc_html_e( 'Potential Conflicts Identified', 'bkx-legal-professional' ); ?></h3>
					<p>
						<?php
						printf(
							/* translators: %d: number of flags */
							esc_html__( 'The conflict check identified %d potential issue(s) that require review.', 'bkx-legal-professional' ),
							$results['total_flags']
						);
						?>
					</p>
				</div>
			<?php else : ?>
				<div class="bkx-conflict-conflict">
					<h3><?php esc_html_e( 'Conflict of Interest Detected', 'bkx-legal-professional' ); ?></h3>
					<p><?php esc_html_e( 'A direct conflict of interest has been identified. Review the matches below before proceeding.', 'bkx-legal-professional' ); ?></p>
				</div>
			<?php endif; ?>

			<!-- Client Matches -->
			<?php if ( ! empty( $results['client_matches'] ) ) : ?>
				<h3><?php esc_html_e( 'Client Matches', 'bkx-legal-professional' ); ?></h3>
				<?php foreach ( $results['client_matches'] as $match ) : ?>
					<div class="bkx-conflict-match severity-<?php echo esc_attr( $match['severity'] ); ?>">
						<strong><?php echo esc_html( $match['user_name'] ); ?></strong>
						(<?php echo esc_html( $match['user_email'] ); ?>)<br>
						<span class="description"><?php echo esc_html( $match['note'] ); ?></span>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>

			<!-- Party Matches -->
			<?php if ( ! empty( $results['party_matches'] ) ) : ?>
				<h3><?php esc_html_e( 'Party Matches', 'bkx-legal-professional' ); ?></h3>
				<?php foreach ( $results['party_matches'] as $match ) : ?>
					<div class="bkx-conflict-match severity-<?php echo esc_attr( $match['severity'] ); ?>">
						<strong><?php echo esc_html( $match['input_party'] ); ?></strong>
						<?php esc_html_e( 'matched with', 'bkx-legal-professional' ); ?>
						<strong><?php echo esc_html( $match['matched_party'] ); ?></strong><br>
						<?php esc_html_e( 'Matter:', 'bkx-legal-professional' ); ?> <?php echo esc_html( $match['matter_name'] ); ?><br>
						<?php esc_html_e( 'Relationship:', 'bkx-legal-professional' ); ?> <?php echo esc_html( ucfirst( $match['relationship'] ) ); ?><br>
						<span class="description"><?php echo esc_html( $match['note'] ); ?></span>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>

			<!-- Keyword Matches -->
			<?php if ( ! empty( $results['matter_matches'] ) ) : ?>
				<h3><?php esc_html_e( 'Similar Matters', 'bkx-legal-professional' ); ?></h3>
				<?php foreach ( $results['matter_matches'] as $match ) : ?>
					<div class="bkx-conflict-match severity-<?php echo esc_attr( $match['severity'] ); ?>">
						<strong><?php echo esc_html( $match['matter_name'] ); ?></strong>
						(<?php echo esc_html( $match['matter_number'] ); ?>)<br>
						<?php esc_html_e( 'Client:', 'bkx-legal-professional' ); ?> <?php echo esc_html( $match['client_name'] ); ?><br>
						<?php esc_html_e( 'Common Keywords:', 'bkx-legal-professional' ); ?> <?php echo esc_html( implode( ', ', $match['common_keywords'] ) ); ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php else : ?>
			<div class="notice notice-error">
				<p><?php esc_html_e( 'Matter not found.', 'bkx-legal-professional' ); ?></p>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<!-- Recent Conflict Checks -->
	<h2><?php esc_html_e( 'Recent Conflict Checks', 'bkx-legal-professional' ); ?></h2>
	<?php
	global $wpdb;
	$table   = $wpdb->prefix . 'bkx_legal_conflict_checks';
	$checks  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY checked_at DESC LIMIT 20", ARRAY_A );
	?>
	<?php if ( ! empty( $checks ) ) : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'bkx-legal-professional' ); ?></th>
					<th><?php esc_html_e( 'Matter', 'bkx-legal-professional' ); ?></th>
					<th><?php esc_html_e( 'Status', 'bkx-legal-professional' ); ?></th>
					<th><?php esc_html_e( 'Flags', 'bkx-legal-professional' ); ?></th>
					<th><?php esc_html_e( 'Checked By', 'bkx-legal-professional' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $checks as $check ) : ?>
					<?php
					$check_matter = get_post( $check['matter_id'] );
					$checker      = get_user_by( 'id', $check['checked_by'] );
					?>
					<tr>
						<td><?php echo esc_html( gmdate( 'M j, Y g:i a', strtotime( $check['checked_at'] ) ) ); ?></td>
						<td>
							<?php if ( $check_matter ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $check['matter_id'] ) ); ?>">
									<?php echo esc_html( $check_matter->post_title ); ?>
								</a>
							<?php else : ?>
								<?php esc_html_e( 'Deleted', 'bkx-legal-professional' ); ?>
							<?php endif; ?>
						</td>
						<td>
							<span class="bkx-status-badge bkx-status-<?php echo esc_attr( $check['status'] ); ?>">
								<?php echo esc_html( ucfirst( $check['status'] ) ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $check['total_flags'] ); ?></td>
						<td><?php echo esc_html( $checker ? $checker->display_name : 'Unknown' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p><?php esc_html_e( 'No conflict checks have been run yet.', 'bkx-legal-professional' ); ?></p>
	<?php endif; ?>
</div>
