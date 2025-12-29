<?php
/**
 * Email Template Editor.
 *
 * @package BookingX\AdvancedEmailTemplates
 * @var object|null $template Template object or null for new.
 */

defined( 'ABSPATH' ) || exit;

$template_service = new \BookingX\AdvancedEmailTemplates\Services\TemplateService();
$variable_service = new \BookingX\AdvancedEmailTemplates\Services\VariableService();
$types            = $template_service->get_template_types();
$events           = $template_service->get_trigger_events();
$variables        = $variable_service->get_all_variables();

$is_new = ! $template;
$title  = $is_new ? __( 'Add New Email Template', 'bkx-advanced-email-templates' ) : __( 'Edit Email Template', 'bkx-advanced-email-templates' );
?>

<div class="wrap bkx-email-editor">
	<h1 class="wp-heading-inline"><?php echo esc_html( $title ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-email-templates' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Back to List', 'bkx-advanced-email-templates' ); ?>
	</a>
	<hr class="wp-header-end">

	<form id="bkx-email-template-form" method="post">
		<input type="hidden" name="template_id" value="<?php echo esc_attr( $template->id ?? 0 ); ?>">

		<div class="bkx-editor-layout">
			<!-- Main Content -->
			<div class="bkx-editor-main">
				<div class="bkx-editor-section">
					<label for="template-name"><?php esc_html_e( 'Template Name', 'bkx-advanced-email-templates' ); ?></label>
					<input type="text" id="template-name" name="name" class="large-text" value="<?php echo esc_attr( $template->name ?? '' ); ?>" required>
				</div>

				<div class="bkx-editor-section">
					<label for="template-subject"><?php esc_html_e( 'Email Subject', 'bkx-advanced-email-templates' ); ?></label>
					<input type="text" id="template-subject" name="subject" class="large-text" value="<?php echo esc_attr( $template->subject ?? '' ); ?>" placeholder="<?php esc_attr_e( 'e.g., Booking Confirmed - {{booking_id}}', 'bkx-advanced-email-templates' ); ?>" required>
					<p class="description"><?php esc_html_e( 'Use {{variables}} for dynamic content.', 'bkx-advanced-email-templates' ); ?></p>
				</div>

				<div class="bkx-editor-section">
					<label for="template-preheader"><?php esc_html_e( 'Preheader Text', 'bkx-advanced-email-templates' ); ?></label>
					<input type="text" id="template-preheader" name="preheader" class="large-text" value="<?php echo esc_attr( $template->preheader ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Preview text shown in email clients', 'bkx-advanced-email-templates' ); ?>">
					<p class="description"><?php esc_html_e( 'This text appears in email client previews.', 'bkx-advanced-email-templates' ); ?></p>
				</div>

				<div class="bkx-editor-section">
					<label for="template-content"><?php esc_html_e( 'Email Content', 'bkx-advanced-email-templates' ); ?></label>
					<div class="bkx-editor-toolbar">
						<button type="button" class="button bkx-insert-variable">
							<span class="dashicons dashicons-shortcode"></span>
							<?php esc_html_e( 'Insert Variable', 'bkx-advanced-email-templates' ); ?>
						</button>
						<button type="button" class="button bkx-insert-block">
							<span class="dashicons dashicons-screenoptions"></span>
							<?php esc_html_e( 'Insert Block', 'bkx-advanced-email-templates' ); ?>
						</button>
					</div>
					<?php
					wp_editor(
						$template->content ?? '',
						'template-content',
						array(
							'textarea_name' => 'content',
							'textarea_rows' => 20,
							'media_buttons' => true,
							'tinymce'       => array(
								'content_css' => BKX_EMAIL_TEMPLATES_URL . 'assets/css/editor-content.css',
							),
						)
					);
					?>
				</div>
			</div>

			<!-- Sidebar -->
			<div class="bkx-editor-sidebar">
				<!-- Publish Box -->
				<div class="bkx-sidebar-box">
					<h3><?php esc_html_e( 'Publish', 'bkx-advanced-email-templates' ); ?></h3>
					<div class="bkx-sidebar-content">
						<div class="bkx-field">
							<label for="template-status"><?php esc_html_e( 'Status', 'bkx-advanced-email-templates' ); ?></label>
							<select id="template-status" name="status">
								<option value="active" <?php selected( $template->status ?? '', 'active' ); ?>>
									<?php esc_html_e( 'Active', 'bkx-advanced-email-templates' ); ?>
								</option>
								<option value="draft" <?php selected( $template->status ?? '', 'draft' ); ?>>
									<?php esc_html_e( 'Draft', 'bkx-advanced-email-templates' ); ?>
								</option>
								<option value="disabled" <?php selected( $template->status ?? '', 'disabled' ); ?>>
									<?php esc_html_e( 'Disabled', 'bkx-advanced-email-templates' ); ?>
								</option>
							</select>
						</div>

						<div class="bkx-publish-actions">
							<button type="submit" class="button button-primary button-large">
								<?php echo $is_new ? esc_html__( 'Create Template', 'bkx-advanced-email-templates' ) : esc_html__( 'Save Template', 'bkx-advanced-email-templates' ); ?>
							</button>
						</div>
					</div>
				</div>

				<!-- Template Settings -->
				<div class="bkx-sidebar-box">
					<h3><?php esc_html_e( 'Template Settings', 'bkx-advanced-email-templates' ); ?></h3>
					<div class="bkx-sidebar-content">
						<div class="bkx-field">
							<label for="template-type"><?php esc_html_e( 'Type', 'bkx-advanced-email-templates' ); ?></label>
							<select id="template-type" name="template_type">
								<?php foreach ( $types as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $template->template_type ?? 'custom', $value ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="bkx-field">
							<label for="template-trigger"><?php esc_html_e( 'Trigger Event', 'bkx-advanced-email-templates' ); ?></label>
							<select id="template-trigger" name="trigger_event">
								<option value=""><?php esc_html_e( '— None —', 'bkx-advanced-email-templates' ); ?></option>
								<?php foreach ( $events as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $template->trigger_event ?? '', $value ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Automatically send when this event occurs.', 'bkx-advanced-email-templates' ); ?></p>
						</div>

						<div class="bkx-field">
							<label for="template-slug"><?php esc_html_e( 'Slug', 'bkx-advanced-email-templates' ); ?></label>
							<input type="text" id="template-slug" name="slug" value="<?php echo esc_attr( $template->slug ?? '' ); ?>" placeholder="<?php esc_attr_e( 'auto-generated', 'bkx-advanced-email-templates' ); ?>">
						</div>
					</div>
				</div>

				<!-- Preview & Test -->
				<div class="bkx-sidebar-box">
					<h3><?php esc_html_e( 'Preview & Test', 'bkx-advanced-email-templates' ); ?></h3>
					<div class="bkx-sidebar-content">
						<button type="button" class="button bkx-preview-btn" id="bkx-preview-template">
							<span class="dashicons dashicons-visibility"></span>
							<?php esc_html_e( 'Preview', 'bkx-advanced-email-templates' ); ?>
						</button>

						<div class="bkx-test-email">
							<input type="email" id="bkx-test-email" placeholder="<?php esc_attr_e( 'email@example.com', 'bkx-advanced-email-templates' ); ?>" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>">
							<button type="button" class="button" id="bkx-send-test">
								<?php esc_html_e( 'Send Test', 'bkx-advanced-email-templates' ); ?>
							</button>
						</div>
					</div>
				</div>

				<!-- Variables Reference -->
				<div class="bkx-sidebar-box bkx-variables-box">
					<h3><?php esc_html_e( 'Available Variables', 'bkx-advanced-email-templates' ); ?></h3>
					<div class="bkx-sidebar-content">
						<div class="bkx-variables-accordion">
							<?php foreach ( $variables as $group_key => $group ) : ?>
								<div class="bkx-variable-group">
									<button type="button" class="bkx-group-toggle">
										<?php echo esc_html( $group['label'] ); ?>
										<span class="dashicons dashicons-arrow-down-alt2"></span>
									</button>
									<div class="bkx-group-variables">
										<?php foreach ( $group['variables'] as $var_key => $var_label ) : ?>
											<div class="bkx-variable-item" data-variable="{{<?php echo esc_attr( $var_key ); ?>}}">
												<code>{{<?php echo esc_html( $var_key ); ?>}}</code>
												<span><?php echo esc_html( $var_label ); ?></span>
											</div>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</form>
</div>

<!-- Preview Modal -->
<div id="bkx-preview-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content bkx-modal-large">
		<span class="bkx-modal-close">&times;</span>
		<div class="bkx-preview-header">
			<h3><?php esc_html_e( 'Email Preview', 'bkx-advanced-email-templates' ); ?></h3>
			<div class="bkx-preview-device-toggle">
				<button type="button" class="active" data-device="desktop">
					<span class="dashicons dashicons-desktop"></span>
				</button>
				<button type="button" data-device="mobile">
					<span class="dashicons dashicons-smartphone"></span>
				</button>
			</div>
		</div>
		<div class="bkx-preview-container">
			<div id="bkx-preview-content"></div>
		</div>
	</div>
</div>

<!-- Variable Picker Modal -->
<div id="bkx-variable-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content">
		<span class="bkx-modal-close">&times;</span>
		<h3><?php esc_html_e( 'Insert Variable', 'bkx-advanced-email-templates' ); ?></h3>
		<div class="bkx-variable-picker">
			<input type="text" id="bkx-variable-search" placeholder="<?php esc_attr_e( 'Search variables...', 'bkx-advanced-email-templates' ); ?>">
			<div class="bkx-variable-list">
				<?php foreach ( $variables as $group_key => $group ) : ?>
					<div class="bkx-variable-section">
						<h4><?php echo esc_html( $group['label'] ); ?></h4>
						<?php foreach ( $group['variables'] as $var_key => $var_label ) : ?>
							<button type="button" class="bkx-pick-variable" data-variable="{{<?php echo esc_attr( $var_key ); ?>}}">
								<code>{{<?php echo esc_html( $var_key ); ?>}}</code>
								<span><?php echo esc_html( $var_label ); ?></span>
							</button>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</div>
