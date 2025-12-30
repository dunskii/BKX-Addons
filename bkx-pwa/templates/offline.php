<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php esc_html_e( 'Offline', 'bkx-pwa' ); ?> - <?php bloginfo( 'name' ); ?></title>
	<?php
	$addon       = \BookingX\PWA\PWAAddon::get_instance();
	$theme_color = $addon->get_setting( 'theme_color', '#2563eb' );
	$bg_color    = $addon->get_setting( 'background_color', '#ffffff' );
	?>
	<style>
		* {
			margin: 0;
			padding: 0;
			box-sizing: border-box;
		}

		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			background-color: <?php echo esc_attr( $bg_color ); ?>;
			color: #1f2937;
			min-height: 100vh;
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			padding: 20px;
			text-align: center;
		}

		.offline-container {
			max-width: 400px;
		}

		.offline-icon {
			width: 80px;
			height: 80px;
			margin: 0 auto 24px;
			background: #f3f4f6;
			border-radius: 50%;
			display: flex;
			align-items: center;
			justify-content: center;
		}

		.offline-icon svg {
			width: 40px;
			height: 40px;
			color: #9ca3af;
		}

		h1 {
			font-size: 24px;
			font-weight: 600;
			margin-bottom: 12px;
			color: #1f2937;
		}

		p {
			font-size: 16px;
			color: #6b7280;
			line-height: 1.5;
			margin-bottom: 24px;
		}

		.retry-btn {
			display: inline-flex;
			align-items: center;
			gap: 8px;
			background-color: <?php echo esc_attr( $theme_color ); ?>;
			color: #fff;
			padding: 12px 24px;
			border-radius: 8px;
			font-size: 16px;
			font-weight: 500;
			text-decoration: none;
			border: none;
			cursor: pointer;
			transition: opacity 0.2s;
		}

		.retry-btn:hover {
			opacity: 0.9;
		}

		.retry-btn svg {
			width: 20px;
			height: 20px;
		}

		.offline-booking {
			margin-top: 32px;
			padding: 20px;
			background: #f9fafb;
			border-radius: 12px;
		}

		.offline-booking h2 {
			font-size: 16px;
			font-weight: 600;
			margin-bottom: 8px;
		}

		.offline-booking p {
			font-size: 14px;
			margin-bottom: 16px;
		}

		.offline-booking-btn {
			display: inline-block;
			background: #fff;
			color: <?php echo esc_attr( $theme_color ); ?>;
			border: 2px solid <?php echo esc_attr( $theme_color ); ?>;
			padding: 10px 20px;
			border-radius: 8px;
			font-size: 14px;
			font-weight: 500;
			text-decoration: none;
			cursor: pointer;
		}

		.status-indicator {
			position: fixed;
			top: 20px;
			right: 20px;
			display: flex;
			align-items: center;
			gap: 8px;
			padding: 8px 12px;
			background: #fef2f2;
			border-radius: 20px;
			font-size: 12px;
			color: #991b1b;
		}

		.status-dot {
			width: 8px;
			height: 8px;
			background: #ef4444;
			border-radius: 50%;
		}

		@media (prefers-color-scheme: dark) {
			body {
				background-color: #1f2937;
				color: #f9fafb;
			}

			h1 {
				color: #f9fafb;
			}

			p {
				color: #9ca3af;
			}

			.offline-icon {
				background: #374151;
			}

			.offline-icon svg {
				color: #6b7280;
			}

			.offline-booking {
				background: #374151;
			}
		}
	</style>
</head>
<body>
	<div class="status-indicator">
		<span class="status-dot"></span>
		<?php esc_html_e( 'Offline', 'bkx-pwa' ); ?>
	</div>

	<div class="offline-container">
		<div class="offline-icon">
			<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728m0 0l-2.829-2.829m2.829 2.829L21 21M15.536 8.464a5 5 0 010 7.072m0 0l-2.829-2.829m-4.243 2.829a4.978 4.978 0 01-1.414-2.83m-1.414 5.658a9 9 0 01-2.167-9.238m7.824 2.167a1 1 0 111.414 1.414m-1.414-1.414L3 3m8.293 8.293l1.414 1.414" />
			</svg>
		</div>

		<h1><?php esc_html_e( 'You\'re Offline', 'bkx-pwa' ); ?></h1>
		<p><?php esc_html_e( 'It looks like you\'ve lost your internet connection. Please check your connection and try again.', 'bkx-pwa' ); ?></p>

		<button class="retry-btn" onclick="window.location.reload()">
			<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
			</svg>
			<?php esc_html_e( 'Try Again', 'bkx-pwa' ); ?>
		</button>

		<?php if ( $addon->get_setting( 'offline_bookings', true ) ) : ?>
		<div class="offline-booking">
			<h2><?php esc_html_e( 'Create Offline Booking', 'bkx-pwa' ); ?></h2>
			<p><?php esc_html_e( 'You can still create a booking. It will sync when you\'re back online.', 'bkx-pwa' ); ?></p>
			<button class="offline-booking-btn" id="start-offline-booking">
				<?php esc_html_e( 'Start Booking', 'bkx-pwa' ); ?>
			</button>
		</div>
		<?php endif; ?>
	</div>

	<script>
		// Check if we're back online
		window.addEventListener('online', function() {
			window.location.reload();
		});

		// Offline booking functionality
		document.getElementById('start-offline-booking')?.addEventListener('click', function() {
			// Open offline booking form from IndexedDB cache
			if ('caches' in window) {
				caches.match('/book/').then(function(response) {
					if (response) {
						window.location.href = '/book/?offline=1';
					} else {
						alert('<?php echo esc_js( __( 'Booking page not cached. Please try again when online.', 'bkx-pwa' ) ); ?>');
					}
				});
			}
		});
	</script>
</body>
</html>
