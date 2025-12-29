<?php
/**
 * Notification Service.
 *
 * @package BookingX\BulkRecurringPayments
 * @since   1.0.0
 */

namespace BookingX\BulkRecurringPayments\Services;

/**
 * NotificationService class.
 *
 * Handles email notifications for subscriptions and bulk purchases.
 *
 * @since 1.0.0
 */
class NotificationService {

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;

		// Register hooks.
		add_action( 'bkx_subscription_created', array( $this, 'send_subscription_welcome' ), 10, 3 );
		add_action( 'bkx_subscription_renewed', array( $this, 'send_renewal_confirmation' ), 10, 2 );
		add_action( 'bkx_subscription_cancelled', array( $this, 'send_cancellation_confirmation' ), 10, 3 );
		add_action( 'bkx_subscription_payment_failed', array( $this, 'send_payment_failed' ), 10, 3 );
		add_action( 'bkx_bulk_purchase_created', array( $this, 'send_bulk_purchase_confirmation' ), 10, 3 );
		add_action( 'bkx_bulk_purchase_expired', array( $this, 'send_bulk_expiry_notice' ), 10, 1 );
	}

	/**
	 * Send subscription welcome email.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $subscription_id Subscription ID.
	 * @param int    $customer_id Customer ID.
	 * @param object $package Package object.
	 */
	public function send_subscription_welcome( $subscription_id, $customer_id, $package ) {
		$customer = get_userdata( $customer_id );

		if ( ! $customer ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: Package name */
			__( 'Welcome to %s!', 'bkx-bulk-recurring-payments' ),
			$package->name
		);

		$message = $this->get_email_template(
			'subscription_welcome',
			array(
				'customer_name'   => $customer->display_name,
				'package_name'    => $package->name,
				'package_price'   => $this->format_price( $package->price ),
				'interval'        => $package->interval_label ?? '',
				'subscription_id' => $subscription_id,
				'manage_url'      => $this->get_manage_url(),
			)
		);

		$this->send_email( $customer->user_email, $subject, $message );
	}

	/**
	 * Send renewal confirmation email.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $subscription_id Subscription ID.
	 * @param object $subscription Subscription object.
	 */
	public function send_renewal_confirmation( $subscription_id, $subscription ) {
		if ( empty( $this->settings['send_payment_receipts'] ) ) {
			return;
		}

		$customer = get_userdata( $subscription->customer_id );

		if ( ! $customer ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: Package name */
			__( 'Your %s subscription has been renewed', 'bkx-bulk-recurring-payments' ),
			$subscription->package->name ?? __( 'subscription', 'bkx-bulk-recurring-payments' )
		);

		$message = $this->get_email_template(
			'subscription_renewed',
			array(
				'customer_name'      => $customer->display_name,
				'package_name'       => $subscription->package->name ?? '',
				'amount'             => $this->format_price( $subscription->package->price ?? 0 ),
				'next_billing_date'  => date_i18n( get_option( 'date_format' ), strtotime( $subscription->next_billing_date ) ),
				'period_start'       => date_i18n( get_option( 'date_format' ), strtotime( $subscription->current_period_start ) ),
				'period_end'         => date_i18n( get_option( 'date_format' ), strtotime( $subscription->current_period_end ) ),
				'manage_url'         => $this->get_manage_url(),
			)
		);

		$this->send_email( $customer->user_email, $subject, $message );
	}

	/**
	 * Send cancellation confirmation email.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $subscription_id Subscription ID.
	 * @param object $subscription Subscription object.
	 * @param bool   $immediate Whether cancelled immediately.
	 */
	public function send_cancellation_confirmation( $subscription_id, $subscription, $immediate ) {
		$customer = get_userdata( $subscription->customer_id );

		if ( ! $customer ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: Package name */
			__( 'Your %s subscription has been cancelled', 'bkx-bulk-recurring-payments' ),
			$subscription->package->name ?? __( 'subscription', 'bkx-bulk-recurring-payments' )
		);

		$message = $this->get_email_template(
			'subscription_cancelled',
			array(
				'customer_name' => $customer->display_name,
				'package_name'  => $subscription->package->name ?? '',
				'end_date'      => $immediate
					? __( 'immediately', 'bkx-bulk-recurring-payments' )
					: date_i18n( get_option( 'date_format' ), strtotime( $subscription->current_period_end ) ),
				'reason'        => $subscription->cancel_reason ?? '',
				'resubscribe_url' => $this->get_packages_url(),
			)
		);

		$this->send_email( $customer->user_email, $subject, $message );
	}

	/**
	 * Send payment failed email.
	 *
	 * @since 1.0.0
	 *
	 * @param int $subscription_id Subscription ID.
	 * @param int $payment_id Payment ID.
	 * @param int $failed_count Number of failed attempts.
	 */
	public function send_payment_failed( $subscription_id, $payment_id, $failed_count ) {
		$subscription_manager = new SubscriptionManager( $this->settings );
		$subscription         = $subscription_manager->get( $subscription_id );

		if ( ! $subscription ) {
			return;
		}

		$customer = get_userdata( $subscription->customer_id );

		if ( ! $customer ) {
			return;
		}

		$max_retries = $this->settings['max_retry_attempts'] ?? 3;

		$subject = sprintf(
			/* translators: %s: Package name */
			__( 'Payment failed for your %s subscription', 'bkx-bulk-recurring-payments' ),
			$subscription->package->name ?? __( 'subscription', 'bkx-bulk-recurring-payments' )
		);

		$message = $this->get_email_template(
			'payment_failed',
			array(
				'customer_name'    => $customer->display_name,
				'package_name'     => $subscription->package->name ?? '',
				'amount'           => $this->format_price( $subscription->package->price ?? 0 ),
				'failed_count'     => $failed_count,
				'max_retries'      => $max_retries,
				'update_payment_url' => $this->get_manage_url(),
			)
		);

		$this->send_email( $customer->user_email, $subject, $message );
	}

	/**
	 * Send bulk purchase confirmation.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $purchase_id Purchase ID.
	 * @param int    $customer_id Customer ID.
	 * @param object $package Package object.
	 */
	public function send_bulk_purchase_confirmation( $purchase_id, $customer_id, $package ) {
		$customer = get_userdata( $customer_id );

		if ( ! $customer ) {
			return;
		}

		$bulk_manager = new BulkPurchaseManager( $this->settings );
		$purchase     = $bulk_manager->get( $purchase_id );

		$subject = sprintf(
			/* translators: %s: Package name */
			__( 'Your %s purchase confirmation', 'bkx-bulk-recurring-payments' ),
			$package->name
		);

		$message = $this->get_email_template(
			'bulk_purchase',
			array(
				'customer_name'   => $customer->display_name,
				'package_name'    => $package->name,
				'quantity'        => $purchase->quantity_purchased ?? $package->quantity,
				'total_price'     => $this->format_price( $purchase->total_price ?? $package->price ),
				'expires_at'      => $purchase->expires_at
					? date_i18n( get_option( 'date_format' ), strtotime( $purchase->expires_at ) )
					: __( 'Never', 'bkx-bulk-recurring-payments' ),
				'manage_url'      => $this->get_manage_url(),
			)
		);

		$this->send_email( $customer->user_email, $subject, $message );
	}

	/**
	 * Send bulk expiry notice.
	 *
	 * @since 1.0.0
	 *
	 * @param int $purchase_id Purchase ID.
	 */
	public function send_bulk_expiry_notice( $purchase_id ) {
		$bulk_manager = new BulkPurchaseManager( $this->settings );
		$purchase     = $bulk_manager->get( $purchase_id );

		if ( ! $purchase ) {
			return;
		}

		$customer = get_userdata( $purchase->customer_id );

		if ( ! $customer ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: Package name */
			__( 'Your %s credits have expired', 'bkx-bulk-recurring-payments' ),
			$purchase->package->name ?? __( 'bulk', 'bkx-bulk-recurring-payments' )
		);

		$message = $this->get_email_template(
			'bulk_expired',
			array(
				'customer_name'     => $customer->display_name,
				'package_name'      => $purchase->package->name ?? '',
				'remaining_credits' => $purchase->quantity_remaining,
				'repurchase_url'    => $this->get_packages_url(),
			)
		);

		$this->send_email( $customer->user_email, $subject, $message );
	}

	/**
	 * Send renewal reminders.
	 *
	 * @since 1.0.0
	 */
	public function send_renewal_reminders() {
		if ( empty( $this->settings['send_renewal_reminders'] ) ) {
			return;
		}

		$reminder_days = $this->settings['renewal_reminder_days'] ?? array( 7, 3, 1 );

		$subscription_manager = new SubscriptionManager( $this->settings );

		foreach ( $reminder_days as $days ) {
			$target_date = gmdate( 'Y-m-d', strtotime( "+{$days} days" ) );

			$subscriptions = $subscription_manager->get_all(
				array(
					'status' => 'active',
				)
			);

			foreach ( $subscriptions as $subscription ) {
				if ( $subscription->next_billing_date === $target_date ) {
					$this->send_renewal_reminder( $subscription, $days );
				}
			}
		}

		// Also send expiry warnings for bulk purchases.
		if ( ! empty( $this->settings['send_expiry_warnings'] ) ) {
			$this->send_expiry_warnings();
		}
	}

	/**
	 * Send a renewal reminder.
	 *
	 * @since 1.0.0
	 *
	 * @param object $subscription Subscription object.
	 * @param int    $days_until Days until renewal.
	 */
	private function send_renewal_reminder( $subscription, $days_until ) {
		$customer = get_userdata( $subscription->customer_id );

		if ( ! $customer ) {
			return;
		}

		$subject = sprintf(
			/* translators: 1: Package name, 2: Number of days */
			__( 'Your %1$s subscription renews in %2$d days', 'bkx-bulk-recurring-payments' ),
			$subscription->package->name ?? __( 'subscription', 'bkx-bulk-recurring-payments' ),
			$days_until
		);

		$message = $this->get_email_template(
			'renewal_reminder',
			array(
				'customer_name'     => $customer->display_name,
				'package_name'      => $subscription->package->name ?? '',
				'days_until'        => $days_until,
				'renewal_date'      => date_i18n( get_option( 'date_format' ), strtotime( $subscription->next_billing_date ) ),
				'amount'            => $this->format_price( $subscription->package->price ?? 0 ),
				'manage_url'        => $this->get_manage_url(),
			)
		);

		$this->send_email( $customer->user_email, $subject, $message );
	}

	/**
	 * Send expiry warnings for bulk purchases.
	 *
	 * @since 1.0.0
	 */
	private function send_expiry_warnings() {
		$warning_days = $this->settings['expiry_warning_days'] ?? array( 30, 7, 1 );

		$bulk_manager = new BulkPurchaseManager( $this->settings );

		foreach ( $warning_days as $days ) {
			$target_date = gmdate( 'Y-m-d', strtotime( "+{$days} days" ) );

			$purchases = $bulk_manager->get_all( array( 'status' => 'active' ) );

			foreach ( $purchases as $purchase ) {
				if ( $purchase->expires_at === $target_date && $purchase->quantity_remaining > 0 ) {
					$this->send_expiry_warning( $purchase, $days );
				}
			}
		}
	}

	/**
	 * Send expiry warning for bulk purchase.
	 *
	 * @since 1.0.0
	 *
	 * @param object $purchase Purchase object.
	 * @param int    $days_until Days until expiry.
	 */
	private function send_expiry_warning( $purchase, $days_until ) {
		$customer = get_userdata( $purchase->customer_id );

		if ( ! $customer ) {
			return;
		}

		$subject = sprintf(
			/* translators: 1: Number of credits, 2: Number of days */
			__( 'Your %1$d credits expire in %2$d days', 'bkx-bulk-recurring-payments' ),
			$purchase->quantity_remaining,
			$days_until
		);

		$message = $this->get_email_template(
			'expiry_warning',
			array(
				'customer_name'     => $customer->display_name,
				'package_name'      => $purchase->package->name ?? '',
				'remaining_credits' => $purchase->quantity_remaining,
				'days_until'        => $days_until,
				'expiry_date'       => date_i18n( get_option( 'date_format' ), strtotime( $purchase->expires_at ) ),
				'book_now_url'      => home_url( '/book/' ),
			)
		);

		$this->send_email( $customer->user_email, $subject, $message );
	}

	/**
	 * Get email template.
	 *
	 * @since 1.0.0
	 *
	 * @param string $template_name Template name.
	 * @param array  $variables Template variables.
	 * @return string
	 */
	private function get_email_template( $template_name, $variables ) {
		$templates = array(
			'subscription_welcome' => __(
				"Hello {customer_name},\n\n" .
				"Thank you for subscribing to {package_name}!\n\n" .
				"Your subscription details:\n" .
				"- Package: {package_name}\n" .
				"- Price: {package_price} / {interval}\n" .
				"- Subscription ID: #{subscription_id}\n\n" .
				"You can manage your subscription at any time: {manage_url}\n\n" .
				"Thank you for your business!",
				'bkx-bulk-recurring-payments'
			),

			'subscription_renewed' => __(
				"Hello {customer_name},\n\n" .
				"Your {package_name} subscription has been successfully renewed.\n\n" .
				"Renewal details:\n" .
				"- Amount charged: {amount}\n" .
				"- Billing period: {period_start} - {period_end}\n" .
				"- Next billing date: {next_billing_date}\n\n" .
				"Manage your subscription: {manage_url}\n\n" .
				"Thank you for your continued business!",
				'bkx-bulk-recurring-payments'
			),

			'subscription_cancelled' => __(
				"Hello {customer_name},\n\n" .
				"Your {package_name} subscription has been cancelled.\n\n" .
				"Your access will end: {end_date}\n\n" .
				"We're sorry to see you go. If you'd like to resubscribe in the future, you can do so here: {resubscribe_url}\n\n" .
				"Thank you for being a customer!",
				'bkx-bulk-recurring-payments'
			),

			'payment_failed' => __(
				"Hello {customer_name},\n\n" .
				"We were unable to process your payment of {amount} for {package_name}.\n\n" .
				"This was attempt {failed_count} of {max_retries}.\n\n" .
				"Please update your payment method to avoid service interruption: {update_payment_url}\n\n" .
				"If you have any questions, please contact us.",
				'bkx-bulk-recurring-payments'
			),

			'bulk_purchase' => __(
				"Hello {customer_name},\n\n" .
				"Thank you for your purchase of {package_name}!\n\n" .
				"Purchase details:\n" .
				"- Credits purchased: {quantity}\n" .
				"- Total paid: {total_price}\n" .
				"- Valid until: {expires_at}\n\n" .
				"You can use your credits when booking. View your credits: {manage_url}\n\n" .
				"Thank you for your business!",
				'bkx-bulk-recurring-payments'
			),

			'bulk_expired' => __(
				"Hello {customer_name},\n\n" .
				"Your {package_name} credits have expired.\n\n" .
				"Unused credits: {remaining_credits}\n\n" .
				"Unfortunately, expired credits cannot be recovered. " .
				"To continue enjoying our services, please purchase a new package: {repurchase_url}\n\n" .
				"Thank you!",
				'bkx-bulk-recurring-payments'
			),

			'renewal_reminder' => __(
				"Hello {customer_name},\n\n" .
				"This is a reminder that your {package_name} subscription will renew in {days_until} days.\n\n" .
				"Renewal details:\n" .
				"- Renewal date: {renewal_date}\n" .
				"- Amount: {amount}\n\n" .
				"If you wish to make any changes, you can manage your subscription here: {manage_url}\n\n" .
				"Thank you for your business!",
				'bkx-bulk-recurring-payments'
			),

			'expiry_warning' => __(
				"Hello {customer_name},\n\n" .
				"Your {package_name} credits will expire in {days_until} days!\n\n" .
				"You still have {remaining_credits} unused credits that will be lost on {expiry_date}.\n\n" .
				"Don't let them go to waste! Book now: {book_now_url}\n\n" .
				"Thank you!",
				'bkx-bulk-recurring-payments'
			),
		);

		/**
		 * Filter email templates.
		 *
		 * @since 1.0.0
		 *
		 * @param array $templates Email templates.
		 */
		$templates = apply_filters( 'bkx_bulk_recurring_email_templates', $templates );

		$template = $templates[ $template_name ] ?? '';

		// Replace variables.
		foreach ( $variables as $key => $value ) {
			$template = str_replace( '{' . $key . '}', $value, $template );
		}

		return $template;
	}

	/**
	 * Send an email.
	 *
	 * @since 1.0.0
	 *
	 * @param string $to Recipient email.
	 * @param string $subject Email subject.
	 * @param string $message Email message.
	 * @return bool
	 */
	private function send_email( $to, $subject, $message ) {
		$headers = array(
			'Content-Type: text/plain; charset=UTF-8',
		);

		/**
		 * Filter email before sending.
		 *
		 * @since 1.0.0
		 *
		 * @param array $email Email data.
		 */
		$email = apply_filters(
			'bkx_bulk_recurring_email',
			array(
				'to'      => $to,
				'subject' => $subject,
				'message' => $message,
				'headers' => $headers,
			)
		);

		return wp_mail(
			$email['to'],
			$email['subject'],
			$email['message'],
			$email['headers']
		);
	}

	/**
	 * Format price for display.
	 *
	 * @since 1.0.0
	 *
	 * @param float $price Price value.
	 * @return string
	 */
	private function format_price( $price ) {
		return '$' . number_format( (float) $price, 2 );
	}

	/**
	 * Get manage subscription URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private function get_manage_url() {
		/**
		 * Filter the manage subscriptions URL.
		 *
		 * @since 1.0.0
		 *
		 * @param string $url The URL.
		 */
		return apply_filters( 'bkx_manage_subscriptions_url', home_url( '/my-account/subscriptions/' ) );
	}

	/**
	 * Get packages URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private function get_packages_url() {
		/**
		 * Filter the packages URL.
		 *
		 * @since 1.0.0
		 *
		 * @param string $url The URL.
		 */
		return apply_filters( 'bkx_packages_url', home_url( '/packages/' ) );
	}
}
