<?php
/**
 * Templates Tab Template.
 *
 * @package BookingX\WhatsAppBusiness
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;
$table     = $wpdb->prefix . 'bkx_whatsapp_templates';
$templates = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC" ); // phpcs:ignore
?>

<div class="bkx-templates-container">
	<div class="bkx-templates-header">
		<h2><?php esc_html_e( 'Message Templates', 'bkx-whatsapp-business' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'WhatsApp message templates must be approved by Meta before they can be used.', 'bkx-whatsapp-business' ); ?>
		</p>
		<div class="bkx-templates-actions">
			<button type="button" class="button" id="bkx-sync-templates">
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Sync Templates', 'bkx-whatsapp-business' ); ?>
			</button>
		</div>
	</div>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'bkx-whatsapp-business' ); ?></th>
				<th><?php esc_html_e( 'Category', 'bkx-whatsapp-business' ); ?></th>
				<th><?php esc_html_e( 'Language', 'bkx-whatsapp-business' ); ?></th>
				<th><?php esc_html_e( 'Status', 'bkx-whatsapp-business' ); ?></th>
				<th><?php esc_html_e( 'Last Synced', 'bkx-whatsapp-business' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'bkx-whatsapp-business' ); ?></th>
			</tr>
		</thead>
		<tbody id="bkx-templates-list">
			<?php if ( empty( $templates ) ) : ?>
				<tr>
					<td colspan="6"><?php esc_html_e( 'No templates found. Click "Sync Templates" to fetch from WhatsApp.', 'bkx-whatsapp-business' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $templates as $template ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $template->name ); ?></strong>
							<div class="row-actions">
								<span class="preview">
									<a href="#" class="bkx-preview-template" data-id="<?php echo esc_attr( $template->id ); ?>">
										<?php esc_html_e( 'Preview', 'bkx-whatsapp-business' ); ?>
									</a>
								</span>
							</div>
						</td>
						<td><?php echo esc_html( $template->category ); ?></td>
						<td><?php echo esc_html( strtoupper( $template->language ) ); ?></td>
						<td>
							<span class="bkx-status-badge <?php echo esc_attr( $template->status ); ?>">
								<?php echo esc_html( ucfirst( $template->status ) ); ?>
							</span>
						</td>
						<td>
							<?php
							echo $template->synced_at
								? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $template->synced_at ) ) )
								: '-';
							?>
						</td>
						<td>
							<?php if ( 'approved' === $template->status ) : ?>
								<button type="button" class="button button-small bkx-test-template" data-name="<?php echo esc_attr( $template->name ); ?>">
									<?php esc_html_e( 'Test', 'bkx-whatsapp-business' ); ?>
								</button>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>

<!-- Template Preview Modal -->
<div id="bkx-template-preview-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content">
		<span class="bkx-modal-close">&times;</span>
		<h3><?php esc_html_e( 'Template Preview', 'bkx-whatsapp-business' ); ?></h3>
		<div id="bkx-template-preview-content"></div>
	</div>
</div>

<!-- Test Template Modal -->
<div id="bkx-test-template-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content">
		<span class="bkx-modal-close">&times;</span>
		<h3><?php esc_html_e( 'Send Test Message', 'bkx-whatsapp-business' ); ?></h3>
		<form id="bkx-test-template-form">
			<input type="hidden" id="bkx-test-template-name" name="template_name">
			<p>
				<label for="bkx-test-phone"><?php esc_html_e( 'Phone Number', 'bkx-whatsapp-business' ); ?></label>
				<input type="text" id="bkx-test-phone" name="phone" class="regular-text" placeholder="+1234567890" required>
			</p>
			<div id="bkx-test-variables"></div>
			<p>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Send Test', 'bkx-whatsapp-business' ); ?></button>
			</p>
		</form>
	</div>
</div>
