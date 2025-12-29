<?php
/**
 * Invoice Generator Service.
 *
 * @package BookingX\BulkRecurringPayments
 * @since   1.0.0
 */

namespace BookingX\BulkRecurringPayments\Services;

/**
 * InvoiceGenerator class.
 *
 * Generates PDF invoices for payments.
 *
 * @since 1.0.0
 */
class InvoiceGenerator {

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Templates table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( $settings ) {
		global $wpdb;
		$this->settings = $settings;
		$this->table    = $wpdb->prefix . 'bkx_invoice_templates';
	}

	/**
	 * Generate an invoice.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type Invoice type (subscription, bulk).
	 * @param int    $id Payment or purchase ID.
	 * @return string|\WP_Error Invoice URL or error.
	 */
	public function generate( $type, $id ) {
		// Get data based on type.
		$data = $this->get_invoice_data( $type, $id );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		// Get template.
		$template = $this->get_template( $type );

		// Generate invoice number.
		$invoice_number = $this->generate_invoice_number();

		// Build invoice HTML.
		$html = $this->build_invoice_html( $data, $template, $invoice_number );

		// Save as PDF (if library available) or HTML.
		$file_path = $this->save_invoice( $invoice_number, $html, $type );

		if ( is_wp_error( $file_path ) ) {
			return $file_path;
		}

		// Store invoice reference.
		$this->store_invoice_reference( $type, $id, $invoice_number, $file_path );

		return $file_path;
	}

	/**
	 * Get invoice data.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type Invoice type.
	 * @param int    $id Payment or purchase ID.
	 * @return array|\WP_Error
	 */
	private function get_invoice_data( $type, $id ) {
		global $wpdb;

		switch ( $type ) {
			case 'subscription':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$payment = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT p.*, s.customer_id, pkg.name as package_name, pkg.description as package_description
						FROM {$wpdb->prefix}bkx_subscription_payments p
						JOIN {$wpdb->prefix}bkx_subscriptions s ON p.subscription_id = s.id
						JOIN {$wpdb->prefix}bkx_payment_packages pkg ON s.package_id = pkg.id
						WHERE p.id = %d",
						$id
					)
				);

				if ( ! $payment ) {
					return new \WP_Error( 'not_found', __( 'Payment not found.', 'bkx-bulk-recurring-payments' ) );
				}

				$customer = get_userdata( $payment->customer_id );

				return array(
					'type'         => 'subscription',
					'amount'       => $payment->amount,
					'currency'     => $payment->currency,
					'paid_at'      => $payment->paid_at,
					'period_start' => $payment->billing_period_start,
					'period_end'   => $payment->billing_period_end,
					'item_name'    => $payment->package_name,
					'description'  => $payment->package_description,
					'customer'     => array(
						'name'  => $customer ? $customer->display_name : __( 'Unknown', 'bkx-bulk-recurring-payments' ),
						'email' => $customer ? $customer->user_email : '',
					),
					'gateway'      => $payment->gateway,
					'gateway_id'   => $payment->gateway_payment_id,
				);

			case 'bulk':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$purchase = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT b.*, pkg.name as package_name, pkg.description as package_description
						FROM {$wpdb->prefix}bkx_bulk_purchases b
						JOIN {$wpdb->prefix}bkx_payment_packages pkg ON b.package_id = pkg.id
						WHERE b.id = %d",
						$id
					)
				);

				if ( ! $purchase ) {
					return new \WP_Error( 'not_found', __( 'Purchase not found.', 'bkx-bulk-recurring-payments' ) );
				}

				$customer = get_userdata( $purchase->customer_id );

				return array(
					'type'        => 'bulk',
					'amount'      => $purchase->total_price,
					'currency'    => 'USD', // Default, should be stored.
					'paid_at'     => $purchase->activated_at ?? $purchase->created_at,
					'quantity'    => $purchase->quantity_purchased,
					'unit_price'  => $purchase->unit_price,
					'discount'    => $purchase->discount_applied,
					'item_name'   => $purchase->package_name,
					'description' => $purchase->package_description,
					'customer'    => array(
						'name'  => $customer ? $customer->display_name : __( 'Unknown', 'bkx-bulk-recurring-payments' ),
						'email' => $customer ? $customer->user_email : '',
					),
					'gateway'     => $purchase->gateway,
					'gateway_id'  => $purchase->gateway_payment_id,
				);

			default:
				return new \WP_Error( 'invalid_type', __( 'Invalid invoice type.', 'bkx-bulk-recurring-payments' ) );
		}
	}

	/**
	 * Get invoice template.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type Template type.
	 * @return object|null
	 */
	private function get_template( $type ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$template = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE template_type = %s AND is_default = 1 AND status = 'active'",
				$this->table,
				$type
			)
		);

		if ( ! $template ) {
			// Return default values.
			return (object) array(
				'company_name'    => get_bloginfo( 'name' ),
				'company_address' => '',
				'company_tax_id'  => '',
				'logo_url'        => '',
				'header_text'     => '',
				'footer_text'     => __( 'Thank you for your business!', 'bkx-bulk-recurring-payments' ),
				'terms_text'      => '',
				'custom_css'      => '',
			);
		}

		return $template;
	}

	/**
	 * Generate invoice number.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private function generate_invoice_number() {
		$prefix  = $this->settings['invoice_prefix'] ?? 'INV-';
		$number  = get_option( 'bkx_next_invoice_number', $this->settings['invoice_starting_number'] ?? 1000 );

		// Increment for next invoice.
		update_option( 'bkx_next_invoice_number', $number + 1 );

		return $prefix . str_pad( $number, 6, '0', STR_PAD_LEFT );
	}

	/**
	 * Build invoice HTML.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $data Invoice data.
	 * @param object $template Template settings.
	 * @param string $invoice_number Invoice number.
	 * @return string
	 */
	private function build_invoice_html( $data, $template, $invoice_number ) {
		$currency_symbol = $this->get_currency_symbol( $data['currency'] ?? 'USD' );

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="utf-8">
			<title><?php echo esc_html( sprintf( __( 'Invoice %s', 'bkx-bulk-recurring-payments' ), $invoice_number ) ); ?></title>
			<style>
				body {
					font-family: 'Helvetica Neue', Arial, sans-serif;
					font-size: 14px;
					line-height: 1.6;
					color: #333;
					margin: 0;
					padding: 40px;
				}
				.invoice-header {
					display: flex;
					justify-content: space-between;
					margin-bottom: 40px;
					border-bottom: 2px solid #0073aa;
					padding-bottom: 20px;
				}
				.company-info h1 {
					margin: 0 0 10px;
					color: #0073aa;
				}
				.invoice-details {
					text-align: right;
				}
				.invoice-details h2 {
					margin: 0 0 10px;
					color: #666;
				}
				.invoice-number {
					font-size: 24px;
					font-weight: bold;
					color: #0073aa;
				}
				.customer-info {
					margin-bottom: 30px;
				}
				.customer-info h3 {
					margin: 0 0 10px;
					color: #666;
				}
				table {
					width: 100%;
					border-collapse: collapse;
					margin-bottom: 30px;
				}
				th, td {
					padding: 12px;
					text-align: left;
					border-bottom: 1px solid #ddd;
				}
				th {
					background: #f5f5f5;
					font-weight: 600;
				}
				.amount {
					text-align: right;
				}
				.total-row {
					font-size: 18px;
					font-weight: bold;
				}
				.total-row td {
					border-top: 2px solid #333;
				}
				.footer {
					margin-top: 40px;
					padding-top: 20px;
					border-top: 1px solid #ddd;
					text-align: center;
					color: #666;
				}
				.paid-stamp {
					position: absolute;
					top: 50%;
					left: 50%;
					transform: translate(-50%, -50%) rotate(-15deg);
					font-size: 72px;
					font-weight: bold;
					color: rgba(0, 128, 0, 0.2);
					pointer-events: none;
				}
				<?php echo esc_html( $template->custom_css ?? '' ); ?>
			</style>
		</head>
		<body>
			<?php if ( ! empty( $data['paid_at'] ) ) : ?>
				<div class="paid-stamp"><?php esc_html_e( 'PAID', 'bkx-bulk-recurring-payments' ); ?></div>
			<?php endif; ?>

			<div class="invoice-header">
				<div class="company-info">
					<?php if ( ! empty( $template->logo_url ) ) : ?>
						<img src="<?php echo esc_url( $template->logo_url ); ?>" alt="Logo" style="max-height: 60px; margin-bottom: 10px;">
					<?php endif; ?>
					<h1><?php echo esc_html( $template->company_name ); ?></h1>
					<?php if ( ! empty( $template->company_address ) ) : ?>
						<p><?php echo nl2br( esc_html( $template->company_address ) ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $template->company_tax_id ) ) : ?>
						<p><?php esc_html_e( 'Tax ID:', 'bkx-bulk-recurring-payments' ); ?> <?php echo esc_html( $template->company_tax_id ); ?></p>
					<?php endif; ?>
				</div>
				<div class="invoice-details">
					<h2><?php esc_html_e( 'INVOICE', 'bkx-bulk-recurring-payments' ); ?></h2>
					<p class="invoice-number"><?php echo esc_html( $invoice_number ); ?></p>
					<p>
						<?php esc_html_e( 'Date:', 'bkx-bulk-recurring-payments' ); ?>
						<?php echo esc_html( gmdate( 'F j, Y', strtotime( $data['paid_at'] ?? current_time( 'mysql' ) ) ) ); ?>
					</p>
				</div>
			</div>

			<?php if ( ! empty( $template->header_text ) ) : ?>
				<div class="header-text">
					<?php echo wp_kses_post( $template->header_text ); ?>
				</div>
			<?php endif; ?>

			<div class="customer-info">
				<h3><?php esc_html_e( 'Bill To:', 'bkx-bulk-recurring-payments' ); ?></h3>
				<p>
					<strong><?php echo esc_html( $data['customer']['name'] ); ?></strong><br>
					<?php echo esc_html( $data['customer']['email'] ); ?>
				</p>
			</div>

			<table>
				<thead>
					<tr>
						<th><?php esc_html_e( 'Description', 'bkx-bulk-recurring-payments' ); ?></th>
						<?php if ( 'bulk' === $data['type'] ) : ?>
							<th><?php esc_html_e( 'Quantity', 'bkx-bulk-recurring-payments' ); ?></th>
							<th class="amount"><?php esc_html_e( 'Unit Price', 'bkx-bulk-recurring-payments' ); ?></th>
						<?php endif; ?>
						<th class="amount"><?php esc_html_e( 'Amount', 'bkx-bulk-recurring-payments' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>
							<strong><?php echo esc_html( $data['item_name'] ); ?></strong>
							<?php if ( ! empty( $data['description'] ) ) : ?>
								<br><small><?php echo esc_html( $data['description'] ); ?></small>
							<?php endif; ?>
							<?php if ( 'subscription' === $data['type'] && ! empty( $data['period_start'] ) ) : ?>
								<br><small>
									<?php
									echo esc_html(
										sprintf(
											/* translators: 1: Start date, 2: End date */
											__( 'Period: %1$s - %2$s', 'bkx-bulk-recurring-payments' ),
											gmdate( 'M j, Y', strtotime( $data['period_start'] ) ),
											gmdate( 'M j, Y', strtotime( $data['period_end'] ) )
										)
									);
									?>
								</small>
							<?php endif; ?>
						</td>
						<?php if ( 'bulk' === $data['type'] ) : ?>
							<td><?php echo esc_html( $data['quantity'] ); ?></td>
							<td class="amount"><?php echo esc_html( $currency_symbol . number_format( $data['unit_price'], 2 ) ); ?></td>
						<?php endif; ?>
						<td class="amount">
							<?php
							$subtotal = 'bulk' === $data['type']
								? $data['quantity'] * $data['unit_price']
								: $data['amount'];
							echo esc_html( $currency_symbol . number_format( $subtotal, 2 ) );
							?>
						</td>
					</tr>

					<?php if ( ! empty( $data['discount'] ) && $data['discount'] > 0 ) : ?>
						<tr>
							<td colspan="<?php echo 'bulk' === $data['type'] ? 3 : 1; ?>">
								<?php esc_html_e( 'Discount', 'bkx-bulk-recurring-payments' ); ?>
							</td>
							<td class="amount">-<?php echo esc_html( $currency_symbol . number_format( $data['discount'], 2 ) ); ?></td>
						</tr>
					<?php endif; ?>

					<tr class="total-row">
						<td colspan="<?php echo 'bulk' === $data['type'] ? 3 : 1; ?>">
							<strong><?php esc_html_e( 'Total', 'bkx-bulk-recurring-payments' ); ?></strong>
						</td>
						<td class="amount">
							<strong><?php echo esc_html( $currency_symbol . number_format( $data['amount'], 2 ) ); ?></strong>
						</td>
					</tr>
				</tbody>
			</table>

			<div class="payment-info">
				<p>
					<strong><?php esc_html_e( 'Payment Method:', 'bkx-bulk-recurring-payments' ); ?></strong>
					<?php echo esc_html( ucfirst( $data['gateway'] ) ); ?>
				</p>
				<?php if ( ! empty( $data['gateway_id'] ) ) : ?>
					<p>
						<strong><?php esc_html_e( 'Transaction ID:', 'bkx-bulk-recurring-payments' ); ?></strong>
						<?php echo esc_html( $data['gateway_id'] ); ?>
					</p>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $template->terms_text ) ) : ?>
				<div class="terms">
					<h4><?php esc_html_e( 'Terms & Conditions', 'bkx-bulk-recurring-payments' ); ?></h4>
					<?php echo wp_kses_post( $template->terms_text ); ?>
				</div>
			<?php endif; ?>

			<div class="footer">
				<?php echo wp_kses_post( $template->footer_text ); ?>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Save invoice to file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $invoice_number Invoice number.
	 * @param string $html Invoice HTML.
	 * @param string $type Invoice type.
	 * @return string|\WP_Error File URL or error.
	 */
	private function save_invoice( $invoice_number, $html, $type ) {
		$upload_dir = wp_upload_dir();
		$invoice_dir = $upload_dir['basedir'] . '/bkx-invoices/' . gmdate( 'Y/m' );

		// Create directory if needed.
		if ( ! file_exists( $invoice_dir ) ) {
			wp_mkdir_p( $invoice_dir );

			// Add .htaccess for security.
			file_put_contents( $invoice_dir . '/.htaccess', 'deny from all' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		$filename = sanitize_file_name( $invoice_number . '.html' );
		$filepath = $invoice_dir . '/' . $filename;

		// Save HTML file.
		$result = file_put_contents( $filepath, $html ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		if ( false === $result ) {
			return new \WP_Error( 'save_failed', __( 'Failed to save invoice.', 'bkx-bulk-recurring-payments' ) );
		}

		// Return URL (via a secure endpoint).
		return add_query_arg(
			array(
				'bkx_invoice' => $invoice_number,
				'nonce'       => wp_create_nonce( 'bkx_view_invoice_' . $invoice_number ),
			),
			home_url( '/bkx-invoice/' )
		);
	}

	/**
	 * Store invoice reference.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type Invoice type.
	 * @param int    $id Payment/purchase ID.
	 * @param string $invoice_number Invoice number.
	 * @param string $file_path File path.
	 */
	private function store_invoice_reference( $type, $id, $invoice_number, $file_path ) {
		global $wpdb;

		$table = 'subscription' === $type
			? $wpdb->prefix . 'bkx_subscription_payments'
			: $wpdb->prefix . 'bkx_bulk_purchases';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array(
				'meta_data' => wp_json_encode(
					array(
						'invoice_number' => $invoice_number,
						'invoice_url'    => $file_path,
					)
				),
			),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Get currency symbol.
	 *
	 * @since 1.0.0
	 *
	 * @param string $currency Currency code.
	 * @return string
	 */
	private function get_currency_symbol( $currency ) {
		$symbols = array(
			'USD' => '$',
			'EUR' => '€',
			'GBP' => '£',
			'JPY' => '¥',
			'AUD' => 'A$',
			'CAD' => 'C$',
			'CHF' => 'CHF',
			'CNY' => '¥',
			'INR' => '₹',
			'MXN' => 'MX$',
			'BRL' => 'R$',
		);

		return $symbols[ $currency ] ?? $currency . ' ';
	}

	/**
	 * Save invoice template.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Template data.
	 * @return int|\WP_Error Template ID or error.
	 */
	public function save_template( $data ) {
		global $wpdb;

		$db_data = array(
			'name'              => sanitize_text_field( $data['name'] ),
			'template_type'     => sanitize_text_field( $data['template_type'] ),
			'logo_url'          => esc_url_raw( $data['logo_url'] ?? '' ),
			'company_name'      => sanitize_text_field( $data['company_name'] ?? '' ),
			'company_address'   => sanitize_textarea_field( $data['company_address'] ?? '' ),
			'company_tax_id'    => sanitize_text_field( $data['company_tax_id'] ?? '' ),
			'header_text'       => wp_kses_post( $data['header_text'] ?? '' ),
			'footer_text'       => wp_kses_post( $data['footer_text'] ?? '' ),
			'terms_text'        => wp_kses_post( $data['terms_text'] ?? '' ),
			'show_tax_breakdown' => ! empty( $data['show_tax_breakdown'] ) ? 1 : 0,
			'show_discount_line' => ! empty( $data['show_discount_line'] ) ? 1 : 0,
			'custom_css'        => sanitize_textarea_field( $data['custom_css'] ?? '' ),
			'is_default'        => ! empty( $data['is_default'] ) ? 1 : 0,
			'status'            => sanitize_text_field( $data['status'] ?? 'active' ),
		);

		if ( ! empty( $data['id'] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( $this->table, $db_data, array( 'id' => absint( $data['id'] ) ) );
			return absint( $data['id'] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $this->table, $db_data );
		return $wpdb->insert_id;
	}
}
