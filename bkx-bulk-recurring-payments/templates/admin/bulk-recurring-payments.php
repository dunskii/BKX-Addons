<?php
/**
 * Bulk & Recurring Payments Admin Template.
 *
 * @package BookingX\BulkRecurringPayments
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'packages'; // phpcs:ignore WordPress.Security.NonceVerification
?>

<div class="wrap bkx-payments-wrap">
	<h1><?php esc_html_e( 'Bulk & Recurring Payments', 'bkx-bulk-recurring-payments' ); ?></h1>

	<nav class="nav-tab-wrapper bkx-nav-tabs">
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'packages' ) ); ?>"
		   class="nav-tab <?php echo 'packages' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Packages', 'bkx-bulk-recurring-payments' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'subscriptions' ) ); ?>"
		   class="nav-tab <?php echo 'subscriptions' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Subscriptions', 'bkx-bulk-recurring-payments' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'bulk' ) ); ?>"
		   class="nav-tab <?php echo 'bulk' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Bulk Purchases', 'bkx-bulk-recurring-payments' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'invoices' ) ); ?>"
		   class="nav-tab <?php echo 'invoices' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Invoices', 'bkx-bulk-recurring-payments' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'settings' ) ); ?>"
		   class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Settings', 'bkx-bulk-recurring-payments' ); ?>
		</a>
	</nav>

	<!-- Packages Tab -->
	<div id="packages-tab" class="bkx-tab-content <?php echo 'packages' === $active_tab ? 'active' : ''; ?>">
		<div class="bkx-card">
			<div class="bkx-card-header">
				<h2><?php esc_html_e( 'Payment Packages', 'bkx-bulk-recurring-payments' ); ?></h2>
				<button type="button" class="button button-primary" id="add-package-btn">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e( 'Add Package', 'bkx-bulk-recurring-payments' ); ?>
				</button>
			</div>

			<div class="bkx-package-filters">
				<select id="package-type-filter">
					<option value=""><?php esc_html_e( 'All Types', 'bkx-bulk-recurring-payments' ); ?></option>
					<option value="bulk"><?php esc_html_e( 'Bulk Packages', 'bkx-bulk-recurring-payments' ); ?></option>
					<option value="recurring"><?php esc_html_e( 'Subscriptions', 'bkx-bulk-recurring-payments' ); ?></option>
				</select>
			</div>

			<table class="wp-list-table widefat fixed striped" id="packages-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'bkx-bulk-recurring-payments' ); ?></th>
						<th><?php esc_html_e( 'Type', 'bkx-bulk-recurring-payments' ); ?></th>
						<th><?php esc_html_e( 'Price', 'bkx-bulk-recurring-payments' ); ?></th>
						<th><?php esc_html_e( 'Discount', 'bkx-bulk-recurring-payments' ); ?></th>
						<th><?php esc_html_e( 'Purchases', 'bkx-bulk-recurring-payments' ); ?></th>
						<th><?php esc_html_e( 'Status', 'bkx-bulk-recurring-payments' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'bkx-bulk-recurring-payments' ); ?></th>
					</tr>
				</thead>
				<tbody id="packages-list">
					<tr class="bkx-loading-row">
						<td colspan="7"><?php esc_html_e( 'Loading packages...', 'bkx-bulk-recurring-payments' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>

	<!-- Subscriptions Tab -->
	<div id="subscriptions-tab" class="bkx-tab-content <?php echo 'subscriptions' === $active_tab ? 'active' : ''; ?>">
		<div class="bkx-card">
			<div class="bkx-card-header">
				<h2><?php esc_html_e( 'Active Subscriptions', 'bkx-bulk-recurring-payments' ); ?></h2>
			</div>

			<div class="bkx-subscription-filters">
				<select id="subscription-status-filter">
					<option value=""><?php esc_html_e( 'All Statuses', 'bkx-bulk-recurring-payments' ); ?></option>
					<option value="active"><?php esc_html_e( 'Active', 'bkx-bulk-recurring-payments' ); ?></option>
					<option value="paused"><?php esc_html_e( 'Paused', 'bkx-bulk-recurring-payments' ); ?></option>
					<option value="cancelled"><?php esc_html_e( 'Cancelled', 'bkx-bulk-recurring-payments' ); ?></option>
					<option value="expired"><?php esc_html_e( 'Expired', 'bkx-bulk-recurring-payments' ); ?></option>
				</select>
			</div>

			<table class="wp-list-table widefat fixed striped" id="subscriptions-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'bkx-bulk-recurring-payments' ); ?></th>
						<th><?php esc_html_e( 'Customer', 'bkx-bulk-recurring-payments' ); ?></th>
						<th><?php esc_html_e( 'Package', 'bkx-bulk-recurring-payments' ); ?></th>
						<th><?php esc_html_e( 'Status', 'bkx-bulk-recurring-payments' ); ?></th>
						<th><?php esc_html_e( 'Next Billing', 'bkx-bulk-recurring-payments' ); ?></th>
						<th><?php esc_html_e( 'Total Billed', 'bkx-bulk-recurring-payments' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'bkx-bulk-recurring-payments' ); ?></th>
					</tr>
				</thead>
				<tbody id="subscriptions-list">
					<tr class="bkx-loading-row">
						<td colspan="7"><?php esc_html_e( 'Loading subscriptions...', 'bkx-bulk-recurring-payments' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>

	<!-- Bulk Purchases Tab -->
	<div id="bulk-tab" class="bkx-tab-content <?php echo 'bulk' === $active_tab ? 'active' : ''; ?>">
		<div class="bkx-card">
			<div class="bkx-card-header">
				<h2><?php esc_html_e( 'Bulk Purchases', 'bkx-bulk-recurring-payments' ); ?></h2>
			</div>

			<div class="bkx-bulk-filters">
				<select id="bulk-status-filter">
					<option value=""><?php esc_html_e( 'All Statuses', 'bkx-bulk-recurring-payments' ); ?></option>
					<option value="active"><?php esc_html_e( 'Active', 'bkx-bulk-recurring-payments' ); ?></option>
					<option value="depleted"><?php esc_html_e( 'Depleted', 'bkx-bulk-recurring-payments' ); ?></option>
					<option value="expired"><?php esc_html_e( 'Expired', 'bkx-bulk-recurring-payments' ); ?></option>
				</select>
			</div>

			<table class="wp-list-table widefat fixed striped" id="bulk-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'bkx-bulk-recurring-payments' ); ?></th>
						<th><?php esc_html_e( 'Customer', 'bkx-bulk-recurring-payments' ); ?></th>
						<th><?php esc_html_e( 'Package', 'bkx-bulk-recurring-payments' ); ?></th>
						<th><?php esc_html_e( 'Credits', 'bkx-bulk-recurring-payments' ); ?></th>
						<th><?php esc_html_e( 'Status', 'bkx-bulk-recurring-payments' ); ?></th>
						<th><?php esc_html_e( 'Expires', 'bkx-bulk-recurring-payments' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'bkx-bulk-recurring-payments' ); ?></th>
					</tr>
				</thead>
				<tbody id="bulk-list">
					<tr class="bkx-loading-row">
						<td colspan="7"><?php esc_html_e( 'Loading purchases...', 'bkx-bulk-recurring-payments' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>

	<!-- Invoices Tab -->
	<div id="invoices-tab" class="bkx-tab-content <?php echo 'invoices' === $active_tab ? 'active' : ''; ?>">
		<div class="bkx-card">
			<div class="bkx-card-header">
				<h2><?php esc_html_e( 'Invoice Templates', 'bkx-bulk-recurring-payments' ); ?></h2>
				<button type="button" class="button button-primary" id="add-template-btn">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e( 'Add Template', 'bkx-bulk-recurring-payments' ); ?>
				</button>
			</div>

			<table class="wp-list-table widefat fixed striped" id="templates-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'bkx-bulk-recurring-payments' ); ?></th>
						<th><?php esc_html_e( 'Type', 'bkx-bulk-recurring-payments' ); ?></th>
						<th><?php esc_html_e( 'Default', 'bkx-bulk-recurring-payments' ); ?></th>
						<th><?php esc_html_e( 'Status', 'bkx-bulk-recurring-payments' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'bkx-bulk-recurring-payments' ); ?></th>
					</tr>
				</thead>
				<tbody id="templates-list">
					<tr class="bkx-loading-row">
						<td colspan="5"><?php esc_html_e( 'Loading templates...', 'bkx-bulk-recurring-payments' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>

	<!-- Settings Tab -->
	<div id="settings-tab" class="bkx-tab-content <?php echo 'settings' === $active_tab ? 'active' : ''; ?>">
		<form id="settings-form" class="bkx-settings-form">
			<div class="bkx-card">
				<h2><?php esc_html_e( 'General Settings', 'bkx-bulk-recurring-payments' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Bulk Packages', 'bkx-bulk-recurring-payments' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enable_bulk_packages" value="1"
									<?php checked( ! empty( $this->settings['enable_bulk_packages'] ) ); ?>>
								<?php esc_html_e( 'Allow customers to purchase bulk booking packages', 'bkx-bulk-recurring-payments' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Subscriptions', 'bkx-bulk-recurring-payments' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enable_subscriptions" value="1"
									<?php checked( ! empty( $this->settings['enable_subscriptions'] ) ); ?>>
								<?php esc_html_e( 'Allow customers to subscribe to recurring packages', 'bkx-bulk-recurring-payments' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Bulk Package Expiry', 'bkx-bulk-recurring-payments' ); ?></th>
						<td>
							<input type="number" name="bulk_expiry_days" min="0"
								   value="<?php echo esc_attr( $this->settings['bulk_expiry_days'] ?? 365 ); ?>"
								   class="small-text">
							<?php esc_html_e( 'days (0 = never expires)', 'bkx-bulk-recurring-payments' ); ?>
						</td>
					</tr>
				</table>
			</div>

			<div class="bkx-card">
				<h2><?php esc_html_e( 'Subscription Settings', 'bkx-bulk-recurring-payments' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Auto-Cancel After Failed Payments', 'bkx-bulk-recurring-payments' ); ?></th>
						<td>
							<input type="number" name="auto_cancel_failed_payments" min="1" max="10"
								   value="<?php echo esc_attr( $this->settings['auto_cancel_failed_payments'] ?? 3 ); ?>"
								   class="small-text">
							<?php esc_html_e( 'consecutive failed payments', 'bkx-bulk-recurring-payments' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Allow Package Switching', 'bkx-bulk-recurring-payments' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="allow_package_switching" value="1"
									<?php checked( ! empty( $this->settings['allow_package_switching'] ) ); ?>>
								<?php esc_html_e( 'Allow customers to upgrade/downgrade their subscription', 'bkx-bulk-recurring-payments' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Prorate Upgrades', 'bkx-bulk-recurring-payments' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="prorate_upgrades" value="1"
									<?php checked( ! empty( $this->settings['prorate_upgrades'] ) ); ?>>
								<?php esc_html_e( 'Apply prorated credit when switching packages', 'bkx-bulk-recurring-payments' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Pause Limit', 'bkx-bulk-recurring-payments' ); ?></th>
						<td>
							<input type="number" name="pause_subscription_limit_days" min="0"
								   value="<?php echo esc_attr( $this->settings['pause_subscription_limit_days'] ?? 30 ); ?>"
								   class="small-text">
							<?php esc_html_e( 'days maximum pause duration', 'bkx-bulk-recurring-payments' ); ?>
						</td>
					</tr>
				</table>
			</div>

			<div class="bkx-card">
				<h2><?php esc_html_e( 'Payment Retry Settings', 'bkx-bulk-recurring-payments' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Retry Failed Payments', 'bkx-bulk-recurring-payments' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="retry_failed_payments" value="1"
									<?php checked( ! empty( $this->settings['retry_failed_payments'] ) ); ?>>
								<?php esc_html_e( 'Automatically retry failed subscription payments', 'bkx-bulk-recurring-payments' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Retry Interval', 'bkx-bulk-recurring-payments' ); ?></th>
						<td>
							<input type="number" name="retry_interval_hours" min="1"
								   value="<?php echo esc_attr( $this->settings['retry_interval_hours'] ?? 24 ); ?>"
								   class="small-text">
							<?php esc_html_e( 'hours between retry attempts', 'bkx-bulk-recurring-payments' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Maximum Retry Attempts', 'bkx-bulk-recurring-payments' ); ?></th>
						<td>
							<input type="number" name="max_retry_attempts" min="1" max="10"
								   value="<?php echo esc_attr( $this->settings['max_retry_attempts'] ?? 3 ); ?>"
								   class="small-text">
						</td>
					</tr>
				</table>
			</div>

			<div class="bkx-card">
				<h2><?php esc_html_e( 'Notification Settings', 'bkx-bulk-recurring-payments' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Send Payment Receipts', 'bkx-bulk-recurring-payments' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="send_payment_receipts" value="1"
									<?php checked( ! empty( $this->settings['send_payment_receipts'] ) ); ?>>
								<?php esc_html_e( 'Email customers when payment is processed', 'bkx-bulk-recurring-payments' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Send Renewal Reminders', 'bkx-bulk-recurring-payments' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="send_renewal_reminders" value="1"
									<?php checked( ! empty( $this->settings['send_renewal_reminders'] ) ); ?>>
								<?php esc_html_e( 'Email customers before subscription renews', 'bkx-bulk-recurring-payments' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Reminder days: 7, 3, 1 days before renewal', 'bkx-bulk-recurring-payments' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Send Expiry Warnings', 'bkx-bulk-recurring-payments' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="send_expiry_warnings" value="1"
									<?php checked( ! empty( $this->settings['send_expiry_warnings'] ) ); ?>>
								<?php esc_html_e( 'Email customers before bulk credits expire', 'bkx-bulk-recurring-payments' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Warning days: 30, 7, 1 days before expiry', 'bkx-bulk-recurring-payments' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>

			<div class="bkx-card">
				<h2><?php esc_html_e( 'Invoice Settings', 'bkx-bulk-recurring-payments' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Invoice Prefix', 'bkx-bulk-recurring-payments' ); ?></th>
						<td>
							<input type="text" name="invoice_prefix"
								   value="<?php echo esc_attr( $this->settings['invoice_prefix'] ?? 'INV-' ); ?>"
								   class="regular-text">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Starting Number', 'bkx-bulk-recurring-payments' ); ?></th>
						<td>
							<input type="number" name="invoice_starting_number" min="1"
								   value="<?php echo esc_attr( $this->settings['invoice_starting_number'] ?? 1000 ); ?>"
								   class="regular-text">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Include Tax Breakdown', 'bkx-bulk-recurring-payments' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="invoice_include_tax" value="1"
									<?php checked( ! empty( $this->settings['invoice_include_tax'] ) ); ?>>
								<?php esc_html_e( 'Show tax breakdown on invoices', 'bkx-bulk-recurring-payments' ); ?>
							</label>
						</td>
					</tr>
				</table>
			</div>

			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Save Settings', 'bkx-bulk-recurring-payments' ); ?>
				</button>
			</p>
		</form>
	</div>
</div>

<!-- Package Modal -->
<div id="package-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content bkx-modal-large">
		<span class="bkx-modal-close">&times;</span>
		<h2 id="package-modal-title"><?php esc_html_e( 'Add Package', 'bkx-bulk-recurring-payments' ); ?></h2>

		<form id="package-form">
			<input type="hidden" name="id" id="package-id">

			<div class="bkx-form-row">
				<div class="bkx-form-field">
					<label for="package-name"><?php esc_html_e( 'Package Name', 'bkx-bulk-recurring-payments' ); ?> *</label>
					<input type="text" id="package-name" name="name" required>
				</div>
				<div class="bkx-form-field">
					<label for="package-type"><?php esc_html_e( 'Package Type', 'bkx-bulk-recurring-payments' ); ?> *</label>
					<select id="package-type" name="package_type" required>
						<option value="bulk"><?php esc_html_e( 'Bulk Package', 'bkx-bulk-recurring-payments' ); ?></option>
						<option value="recurring"><?php esc_html_e( 'Recurring Subscription', 'bkx-bulk-recurring-payments' ); ?></option>
					</select>
				</div>
			</div>

			<div class="bkx-form-field">
				<label for="package-description"><?php esc_html_e( 'Description', 'bkx-bulk-recurring-payments' ); ?></label>
				<textarea id="package-description" name="description" rows="3"></textarea>
			</div>

			<div class="bkx-form-row">
				<div class="bkx-form-field">
					<label for="package-price"><?php esc_html_e( 'Price', 'bkx-bulk-recurring-payments' ); ?> *</label>
					<input type="number" id="package-price" name="price" step="0.01" min="0" required>
				</div>
				<div class="bkx-form-field" id="quantity-field">
					<label for="package-quantity"><?php esc_html_e( 'Quantity (Credits)', 'bkx-bulk-recurring-payments' ); ?></label>
					<input type="number" id="package-quantity" name="quantity" min="1" value="1">
				</div>
			</div>

			<div id="recurring-fields" style="display: none;">
				<div class="bkx-form-row">
					<div class="bkx-form-field">
						<label for="interval-count"><?php esc_html_e( 'Billing Interval', 'bkx-bulk-recurring-payments' ); ?></label>
						<input type="number" id="interval-count" name="interval_count" min="1" value="1" class="small-text">
					</div>
					<div class="bkx-form-field">
						<label for="interval-type"><?php esc_html_e( 'Interval Type', 'bkx-bulk-recurring-payments' ); ?></label>
						<select id="interval-type" name="interval_type">
							<option value="day"><?php esc_html_e( 'Day(s)', 'bkx-bulk-recurring-payments' ); ?></option>
							<option value="week"><?php esc_html_e( 'Week(s)', 'bkx-bulk-recurring-payments' ); ?></option>
							<option value="month" selected><?php esc_html_e( 'Month(s)', 'bkx-bulk-recurring-payments' ); ?></option>
							<option value="year"><?php esc_html_e( 'Year(s)', 'bkx-bulk-recurring-payments' ); ?></option>
						</select>
					</div>
				</div>
				<div class="bkx-form-row">
					<div class="bkx-form-field">
						<label for="billing-cycles"><?php esc_html_e( 'Billing Cycles', 'bkx-bulk-recurring-payments' ); ?></label>
						<input type="number" id="billing-cycles" name="billing_cycles" min="0" value="0">
						<p class="description"><?php esc_html_e( '0 = unlimited', 'bkx-bulk-recurring-payments' ); ?></p>
					</div>
					<div class="bkx-form-field">
						<label for="trial-days"><?php esc_html_e( 'Trial Days', 'bkx-bulk-recurring-payments' ); ?></label>
						<input type="number" id="trial-days" name="trial_days" min="0" value="0">
					</div>
					<div class="bkx-form-field">
						<label for="setup-fee"><?php esc_html_e( 'Setup Fee', 'bkx-bulk-recurring-payments' ); ?></label>
						<input type="number" id="setup-fee" name="setup_fee" step="0.01" min="0" value="0">
					</div>
				</div>
			</div>

			<div class="bkx-form-row">
				<div class="bkx-form-field">
					<label for="discount-type"><?php esc_html_e( 'Discount Type', 'bkx-bulk-recurring-payments' ); ?></label>
					<select id="discount-type" name="discount_type">
						<option value="percentage"><?php esc_html_e( 'Percentage', 'bkx-bulk-recurring-payments' ); ?></option>
						<option value="fixed"><?php esc_html_e( 'Fixed Amount', 'bkx-bulk-recurring-payments' ); ?></option>
					</select>
				</div>
				<div class="bkx-form-field">
					<label for="discount-amount"><?php esc_html_e( 'Discount Amount', 'bkx-bulk-recurring-payments' ); ?></label>
					<input type="number" id="discount-amount" name="discount_amount" step="0.01" min="0" value="0">
				</div>
			</div>

			<div class="bkx-form-row">
				<div class="bkx-form-field">
					<label for="valid-from"><?php esc_html_e( 'Valid From', 'bkx-bulk-recurring-payments' ); ?></label>
					<input type="date" id="valid-from" name="valid_from">
				</div>
				<div class="bkx-form-field">
					<label for="valid-until"><?php esc_html_e( 'Valid Until', 'bkx-bulk-recurring-payments' ); ?></label>
					<input type="date" id="valid-until" name="valid_until">
				</div>
			</div>

			<div class="bkx-form-row">
				<div class="bkx-form-field">
					<label for="max-purchases"><?php esc_html_e( 'Maximum Purchases', 'bkx-bulk-recurring-payments' ); ?></label>
					<input type="number" id="max-purchases" name="max_purchases" min="0" value="0">
					<p class="description"><?php esc_html_e( '0 = unlimited', 'bkx-bulk-recurring-payments' ); ?></p>
				</div>
				<div class="bkx-form-field">
					<label for="package-status"><?php esc_html_e( 'Status', 'bkx-bulk-recurring-payments' ); ?></label>
					<select id="package-status" name="status">
						<option value="active"><?php esc_html_e( 'Active', 'bkx-bulk-recurring-payments' ); ?></option>
						<option value="inactive"><?php esc_html_e( 'Inactive', 'bkx-bulk-recurring-payments' ); ?></option>
					</select>
				</div>
			</div>

			<div class="bkx-form-actions">
				<button type="button" class="button" id="cancel-package-btn">
					<?php esc_html_e( 'Cancel', 'bkx-bulk-recurring-payments' ); ?>
				</button>
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Save Package', 'bkx-bulk-recurring-payments' ); ?>
				</button>
			</div>
		</form>
	</div>
</div>
