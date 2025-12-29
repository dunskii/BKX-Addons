<?php
/**
 * Email Templates List Template.
 *
 * @package BookingX\AdvancedEmailTemplates
 * @var array $templates Available templates.
 */

defined( 'ABSPATH' ) || exit;

$template_service = new \BookingX\AdvancedEmailTemplates\Services\TemplateService();
$types            = $template_service->get_template_types();
$events           = $template_service->get_trigger_events();
?>

<div class="wrap bkx-email-templates">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Email Templates', 'bkx-advanced-email-templates' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-email-templates&action=new' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add New', 'bkx-advanced-email-templates' ); ?>
	</a>

	<hr class="wp-header-end">

	<div class="bkx-template-stats">
		<div class="stat-card">
			<span class="stat-number"><?php echo esc_html( count( $templates ) ); ?></span>
			<span class="stat-label"><?php esc_html_e( 'Templates', 'bkx-advanced-email-templates' ); ?></span>
		</div>
		<div class="stat-card">
			<span class="stat-number"><?php echo esc_html( count( array_filter( $templates, fn( $t ) => 'active' === $t->status ) ) ); ?></span>
			<span class="stat-label"><?php esc_html_e( 'Active', 'bkx-advanced-email-templates' ); ?></span>
		</div>
	</div>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th class="column-name"><?php esc_html_e( 'Template', 'bkx-advanced-email-templates' ); ?></th>
				<th class="column-type"><?php esc_html_e( 'Type', 'bkx-advanced-email-templates' ); ?></th>
				<th class="column-trigger"><?php esc_html_e( 'Trigger Event', 'bkx-advanced-email-templates' ); ?></th>
				<th class="column-status"><?php esc_html_e( 'Status', 'bkx-advanced-email-templates' ); ?></th>
				<th class="column-updated"><?php esc_html_e( 'Last Modified', 'bkx-advanced-email-templates' ); ?></th>
				<th class="column-actions"><?php esc_html_e( 'Actions', 'bkx-advanced-email-templates' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $templates ) ) : ?>
				<tr>
					<td colspan="6">
						<?php esc_html_e( 'No templates found. Create your first template!', 'bkx-advanced-email-templates' ); ?>
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $templates as $template ) : ?>
					<tr data-id="<?php echo esc_attr( $template->id ); ?>">
						<td class="column-name">
							<strong>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-email-templates&action=edit&template_id=' . $template->id ) ); ?>">
									<?php echo esc_html( $template->name ); ?>
								</a>
							</strong>
							<?php if ( $template->is_default ) : ?>
								<span class="bkx-badge default"><?php esc_html_e( 'Default', 'bkx-advanced-email-templates' ); ?></span>
							<?php endif; ?>
							<div class="row-actions">
								<span class="edit">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-email-templates&action=edit&template_id=' . $template->id ) ); ?>">
										<?php esc_html_e( 'Edit', 'bkx-advanced-email-templates' ); ?>
									</a> |
								</span>
								<span class="duplicate">
									<a href="#" class="bkx-duplicate-template" data-id="<?php echo esc_attr( $template->id ); ?>">
										<?php esc_html_e( 'Duplicate', 'bkx-advanced-email-templates' ); ?>
									</a>
									<?php if ( ! $template->is_default ) : ?>
									 |
									<?php endif; ?>
								</span>
								<?php if ( ! $template->is_default ) : ?>
									<span class="delete">
										<a href="#" class="bkx-delete-template" data-id="<?php echo esc_attr( $template->id ); ?>">
											<?php esc_html_e( 'Delete', 'bkx-advanced-email-templates' ); ?>
										</a>
									</span>
								<?php endif; ?>
							</div>
						</td>
						<td class="column-type">
							<?php echo esc_html( $types[ $template->template_type ] ?? $template->template_type ); ?>
						</td>
						<td class="column-trigger">
							<?php echo esc_html( $events[ $template->trigger_event ] ?? '-' ); ?>
						</td>
						<td class="column-status">
							<span class="bkx-status-badge <?php echo esc_attr( $template->status ); ?>">
								<?php echo esc_html( ucfirst( $template->status ) ); ?>
							</span>
						</td>
						<td class="column-updated">
							<?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $template->updated_at ) ) ); ?>
						</td>
						<td class="column-actions">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-email-templates&action=edit&template_id=' . $template->id ) ); ?>" class="button button-small">
								<?php esc_html_e( 'Edit', 'bkx-advanced-email-templates' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<div class="bkx-email-settings-link">
		<h3><?php esc_html_e( 'Global Settings', 'bkx-advanced-email-templates' ); ?></h3>
		<p><?php esc_html_e( 'Configure logo, colors, and footer for all email templates.', 'bkx-advanced-email-templates' ); ?></p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bookingx-settings&tab=email' ) ); ?>" class="button">
			<?php esc_html_e( 'Email Settings', 'bkx-advanced-email-templates' ); ?>
		</a>
	</div>
</div>
