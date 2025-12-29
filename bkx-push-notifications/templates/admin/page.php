<?php
/**
 * Push Notifications Admin Page.
 *
 * @package BookingX\PushNotifications
 * @var string $tab Current tab.
 */

defined( 'ABSPATH' ) || exit;

$settings         = get_option( 'bkx_push_settings', array() );
$template_service = new \BookingX\PushNotifications\Services\TemplateService();
$templates        = $template_service->get_all_templates();
$events           = $template_service->get_trigger_events();
$audiences        = $template_service->get_target_audiences();
$variables        = $template_service->get_available_variables();

$subscription_service = new \BookingX\PushNotifications\Services\SubscriptionService();
$stats                = $subscription_service->get_stats();
?>

<div class="wrap bkx-push-notifications">
	<h1><?php esc_html_e( 'Push Notifications', 'bkx-push-notifications' ); ?></h1>

	<nav class="nav-tab-wrapper">
		<a href="?page=bkx-push-notifications&tab=dashboard" class="nav-tab <?php echo 'dashboard' === $tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Dashboard', 'bkx-push-notifications' ); ?>
		</a>
		<a href="?page=bkx-push-notifications&tab=templates" class="nav-tab <?php echo 'templates' === $tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Templates', 'bkx-push-notifications' ); ?>
		</a>
		<a href="?page=bkx-push-notifications&tab=settings" class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Settings', 'bkx-push-notifications' ); ?>
		</a>
	</nav>

	<div class="bkx-push-content">
		<?php if ( 'dashboard' === $tab ) : ?>
			<!-- Dashboard -->
			<div class="bkx-dashboard">
				<div class="bkx-stats-grid">
					<div class="bkx-stat-card">
						<span class="stat-icon dashicons dashicons-smartphone"></span>
						<div class="stat-content">
							<span class="stat-number"><?php echo esc_html( $stats['active'] ); ?></span>
							<span class="stat-label"><?php esc_html_e( 'Active Subscribers', 'bkx-push-notifications' ); ?></span>
						</div>
					</div>
					<div class="bkx-stat-card">
						<span class="stat-icon dashicons dashicons-desktop"></span>
						<div class="stat-content">
							<span class="stat-number"><?php echo esc_html( $stats['desktop'] ); ?></span>
							<span class="stat-label"><?php esc_html_e( 'Desktop', 'bkx-push-notifications' ); ?></span>
						</div>
					</div>
					<div class="bkx-stat-card">
						<span class="stat-icon dashicons dashicons-tablet"></span>
						<div class="stat-content">
							<span class="stat-number"><?php echo esc_html( $stats['mobile'] + $stats['tablet'] ); ?></span>
							<span class="stat-label"><?php esc_html_e( 'Mobile/Tablet', 'bkx-push-notifications' ); ?></span>
						</div>
					</div>
				</div>

				<div class="bkx-test-section">
					<h2><?php esc_html_e( 'Send Test Notification', 'bkx-push-notifications' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Subscribe to push notifications first, then send a test to your browser.', 'bkx-push-notifications' ); ?>
					</p>

					<div class="bkx-test-form">
						<div class="bkx-field">
							<label for="test-title"><?php esc_html_e( 'Title', 'bkx-push-notifications' ); ?></label>
							<input type="text" id="test-title" value="<?php esc_attr_e( 'Test Notification', 'bkx-push-notifications' ); ?>">
						</div>
						<div class="bkx-field">
							<label for="test-body"><?php esc_html_e( 'Message', 'bkx-push-notifications' ); ?></label>
							<textarea id="test-body" rows="2"><?php esc_html_e( 'This is a test push notification from BookingX.', 'bkx-push-notifications' ); ?></textarea>
						</div>
						<button type="button" class="button button-primary" id="bkx-send-test">
							<?php esc_html_e( 'Send Test', 'bkx-push-notifications' ); ?>
						</button>
					</div>
				</div>

				<?php if ( empty( $settings['vapid_public_key'] ) ) : ?>
					<div class="notice notice-warning">
						<p>
							<?php esc_html_e( 'VAPID keys are not configured. Push notifications will not work. Please deactivate and reactivate the plugin to generate keys.', 'bkx-push-notifications' ); ?>
						</p>
					</div>
				<?php endif; ?>
			</div>

		<?php elseif ( 'templates' === $tab ) : ?>
			<!-- Templates -->
			<div class="bkx-templates">
				<p>
					<button type="button" class="button button-primary" id="bkx-add-template">
						<?php esc_html_e( 'Add New Template', 'bkx-push-notifications' ); ?>
					</button>
				</p>

				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'bkx-push-notifications' ); ?></th>
							<th><?php esc_html_e( 'Trigger', 'bkx-push-notifications' ); ?></th>
							<th><?php esc_html_e( 'Audience', 'bkx-push-notifications' ); ?></th>
							<th><?php esc_html_e( 'Status', 'bkx-push-notifications' ); ?></th>
							<th style="width: 150px;"><?php esc_html_e( 'Actions', 'bkx-push-notifications' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $templates ) ) : ?>
							<tr>
								<td colspan="5"><?php esc_html_e( 'No templates found.', 'bkx-push-notifications' ); ?></td>
							</tr>
						<?php else : ?>
							<?php foreach ( $templates as $template ) : ?>
								<tr data-id="<?php echo esc_attr( $template->id ); ?>">
									<td>
										<strong><?php echo esc_html( $template->name ); ?></strong>
										<div class="row-actions">
											<code><?php echo esc_html( $template->slug ); ?></code>
										</div>
									</td>
									<td><?php echo esc_html( $events[ $template->trigger_event ] ?? $template->trigger_event ); ?></td>
									<td><?php echo esc_html( $audiences[ $template->target_audience ] ?? $template->target_audience ); ?></td>
									<td>
										<span class="bkx-status-badge <?php echo esc_attr( $template->status ); ?>">
											<?php echo esc_html( ucfirst( $template->status ) ); ?>
										</span>
									</td>
									<td>
										<button type="button" class="button button-small bkx-edit-template"
											data-id="<?php echo esc_attr( $template->id ); ?>"
											data-name="<?php echo esc_attr( $template->name ); ?>"
											data-slug="<?php echo esc_attr( $template->slug ); ?>"
											data-trigger="<?php echo esc_attr( $template->trigger_event ); ?>"
											data-title="<?php echo esc_attr( $template->title ); ?>"
											data-body="<?php echo esc_attr( $template->body ); ?>"
											data-icon="<?php echo esc_attr( $template->icon ); ?>"
											data-url="<?php echo esc_attr( $template->url ); ?>"
											data-audience="<?php echo esc_attr( $template->target_audience ); ?>"
											data-status="<?php echo esc_attr( $template->status ); ?>">
											<?php esc_html_e( 'Edit', 'bkx-push-notifications' ); ?>
										</button>
										<button type="button" class="button button-small bkx-delete-template" data-id="<?php echo esc_attr( $template->id ); ?>">
											<?php esc_html_e( 'Delete', 'bkx-push-notifications' ); ?>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>

				<h3><?php esc_html_e( 'Available Variables', 'bkx-push-notifications' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Use these variables in your notification templates:', 'bkx-push-notifications' ); ?></p>
				<ul class="bkx-variable-list">
					<?php foreach ( $variables as $var => $label ) : ?>
						<li><code><?php echo esc_html( $var ); ?></code> - <?php echo esc_html( $label ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>

		<?php elseif ( 'settings' === $tab ) : ?>
			<!-- Settings -->
			<form id="bkx-push-settings-form" method="post">
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Push Notifications', 'bkx-push-notifications' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enabled" value="1" <?php checked( $settings['enabled'] ?? 0, 1 ); ?>>
								<?php esc_html_e( 'Show push notification prompt to visitors', 'bkx-push-notifications' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Prompt Delay', 'bkx-push-notifications' ); ?></th>
						<td>
							<input type="number" name="prompt_delay" value="<?php echo esc_attr( $settings['prompt_delay'] ?? 5000 ); ?>" min="0" step="1000">
							<p class="description"><?php esc_html_e( 'Delay in milliseconds before showing the subscription prompt.', 'bkx-push-notifications' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Prompt Message', 'bkx-push-notifications' ); ?></th>
						<td>
							<input type="text" name="prompt_message" value="<?php echo esc_attr( $settings['prompt_message'] ?? '' ); ?>" class="large-text">
							<p class="description"><?php esc_html_e( 'Custom message shown with the subscription prompt.', 'bkx-push-notifications' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Notification Icon', 'bkx-push-notifications' ); ?></th>
						<td>
							<input type="url" name="icon" value="<?php echo esc_url( $settings['icon'] ?? '' ); ?>" class="large-text">
							<p class="description"><?php esc_html_e( 'URL to a 192x192px icon for notifications.', 'bkx-push-notifications' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Badge Icon', 'bkx-push-notifications' ); ?></th>
						<td>
							<input type="url" name="badge" value="<?php echo esc_url( $settings['badge'] ?? '' ); ?>" class="large-text">
							<p class="description"><?php esc_html_e( 'URL to a small monochrome badge icon (96x96px).', 'bkx-push-notifications' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Reminder Hours', 'bkx-push-notifications' ); ?></th>
						<td>
							<input type="number" name="reminder_hours" value="<?php echo esc_attr( $settings['reminder_hours'] ?? 24 ); ?>" min="1" max="168">
							<p class="description"><?php esc_html_e( 'Send booking reminder this many hours before the appointment.', 'bkx-push-notifications' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'VAPID Public Key', 'bkx-push-notifications' ); ?></th>
						<td>
							<code><?php echo esc_html( $settings['vapid_public_key'] ?? 'Not generated' ); ?></code>
							<p class="description"><?php esc_html_e( 'Auto-generated. Used for Web Push authentication.', 'bkx-push-notifications' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'bkx-push-notifications' ); ?></button>
				</p>
			</form>
		<?php endif; ?>
	</div>
</div>

<!-- Template Modal -->
<div id="bkx-template-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content">
		<span class="bkx-modal-close">&times;</span>
		<h3 id="bkx-template-modal-title"><?php esc_html_e( 'Add Template', 'bkx-push-notifications' ); ?></h3>
		<form id="bkx-template-form">
			<input type="hidden" id="template-id" name="template_id">
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Name', 'bkx-push-notifications' ); ?></th>
					<td><input type="text" id="template-name" name="name" class="regular-text" required></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Slug', 'bkx-push-notifications' ); ?></th>
					<td><input type="text" id="template-slug" name="slug" class="regular-text"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Trigger Event', 'bkx-push-notifications' ); ?></th>
					<td>
						<select id="template-trigger" name="trigger_event" required>
							<?php foreach ( $events as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Target Audience', 'bkx-push-notifications' ); ?></th>
					<td>
						<select id="template-audience" name="target_audience" required>
							<?php foreach ( $audiences as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Title', 'bkx-push-notifications' ); ?></th>
					<td><input type="text" id="template-title" name="title" class="large-text" required></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Body', 'bkx-push-notifications' ); ?></th>
					<td><textarea id="template-body" name="body" rows="3" class="large-text" required></textarea></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Icon URL', 'bkx-push-notifications' ); ?></th>
					<td><input type="url" id="template-icon" name="icon" class="large-text"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Click URL', 'bkx-push-notifications' ); ?></th>
					<td><input type="url" id="template-url" name="url" class="large-text"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Status', 'bkx-push-notifications' ); ?></th>
					<td>
						<select id="template-status" name="status">
							<option value="active"><?php esc_html_e( 'Active', 'bkx-push-notifications' ); ?></option>
							<option value="inactive"><?php esc_html_e( 'Inactive', 'bkx-push-notifications' ); ?></option>
						</select>
					</td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Template', 'bkx-push-notifications' ); ?></button>
			</p>
		</form>
	</div>
</div>
