<?php
/**
 * Custom code settings template.
 *
 * @package BookingX\WhiteLabel
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\WhiteLabel\WhiteLabelAddon::get_instance();
$settings = $addon->get_settings();
?>

<div class="bkx-custom-code-settings">
	<h2><?php esc_html_e( 'Custom Code', 'bkx-white-label' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Add custom CSS and JavaScript to extend functionality or styling.', 'bkx-white-label' ); ?></p>

	<div class="bkx-code-tabs-container">
		<div class="bkx-code-tabs">
			<button type="button" class="bkx-code-tab active" data-tab="admin-css">
				<span class="dashicons dashicons-admin-appearance"></span>
				<?php esc_html_e( 'Admin CSS', 'bkx-white-label' ); ?>
			</button>
			<button type="button" class="bkx-code-tab" data-tab="frontend-css">
				<span class="dashicons dashicons-welcome-widgets-menus"></span>
				<?php esc_html_e( 'Frontend CSS', 'bkx-white-label' ); ?>
			</button>
			<button type="button" class="bkx-code-tab" data-tab="admin-js">
				<span class="dashicons dashicons-editor-code"></span>
				<?php esc_html_e( 'Admin JS', 'bkx-white-label' ); ?>
			</button>
			<button type="button" class="bkx-code-tab" data-tab="frontend-js">
				<span class="dashicons dashicons-media-code"></span>
				<?php esc_html_e( 'Frontend JS', 'bkx-white-label' ); ?>
			</button>
		</div>

		<div class="bkx-code-panels">
			<!-- Admin CSS -->
			<div class="bkx-code-panel active" id="bkx-panel-admin-css">
				<h3><?php esc_html_e( 'Admin Custom CSS', 'bkx-white-label' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Add custom CSS for the WordPress admin area. Applied on all BookingX admin pages.', 'bkx-white-label' ); ?></p>
				<textarea id="custom_css_admin" name="custom_css_admin" rows="20" class="large-text code bkx-code-editor" data-lang="css"><?php echo esc_textarea( $settings['custom_css_admin'] ?? '' ); ?></textarea>

				<div class="bkx-code-examples">
					<h4><?php esc_html_e( 'Examples', 'bkx-white-label' ); ?></h4>
					<pre><code>/* Hide specific elements */
.bkx-welcome-banner {
    display: none;
}

/* Custom button styling */
.bkx-admin-page .button-primary {
    border-radius: 20px;
    text-transform: uppercase;
}

/* Custom card shadows */
.bkx-stat-card {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}</code></pre>
				</div>
			</div>

			<!-- Frontend CSS -->
			<div class="bkx-code-panel" id="bkx-panel-frontend-css">
				<h3><?php esc_html_e( 'Frontend Custom CSS', 'bkx-white-label' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Add custom CSS for the public-facing booking forms and pages.', 'bkx-white-label' ); ?></p>
				<textarea id="custom_css_frontend" name="custom_css_frontend" rows="20" class="large-text code bkx-code-editor" data-lang="css"><?php echo esc_textarea( $settings['custom_css_frontend'] ?? '' ); ?></textarea>

				<div class="bkx-code-examples">
					<h4><?php esc_html_e( 'Examples', 'bkx-white-label' ); ?></h4>
					<pre><code>/* Custom booking form styling */
.bkx-booking-form {
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
}

/* Service card hover effect */
.bkx-service-card:hover {
    transform: translateY(-5px);
    transition: transform 0.3s ease;
}

/* Custom time slot styling */
.bkx-time-slot {
    border-radius: 25px;
}</code></pre>
				</div>
			</div>

			<!-- Admin JS -->
			<div class="bkx-code-panel" id="bkx-panel-admin-js">
				<h3><?php esc_html_e( 'Admin Custom JavaScript', 'bkx-white-label' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Add custom JavaScript for the WordPress admin area. Use with caution.', 'bkx-white-label' ); ?></p>

				<div class="bkx-notice bkx-notice-warning">
					<p><strong><?php esc_html_e( 'Warning:', 'bkx-white-label' ); ?></strong> <?php esc_html_e( 'Invalid JavaScript can break admin functionality. Test thoroughly.', 'bkx-white-label' ); ?></p>
				</div>

				<textarea id="custom_js_admin" name="custom_js_admin" rows="20" class="large-text code bkx-code-editor" data-lang="javascript"><?php echo esc_textarea( $settings['custom_js_admin'] ?? '' ); ?></textarea>

				<div class="bkx-code-examples">
					<h4><?php esc_html_e( 'Examples', 'bkx-white-label' ); ?></h4>
					<pre><code>// jQuery is available
jQuery(document).ready(function($) {
    // Add confirmation to delete buttons
    $('.bkx-delete-booking').on('click', function(e) {
        if (!confirm('Are you sure?')) {
            e.preventDefault();
        }
    });

    // Custom initialization
    console.log('BookingX Admin loaded');
});</code></pre>
				</div>
			</div>

			<!-- Frontend JS -->
			<div class="bkx-code-panel" id="bkx-panel-frontend-js">
				<h3><?php esc_html_e( 'Frontend Custom JavaScript', 'bkx-white-label' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Add custom JavaScript for the public-facing pages.', 'bkx-white-label' ); ?></p>

				<div class="bkx-notice bkx-notice-warning">
					<p><strong><?php esc_html_e( 'Warning:', 'bkx-white-label' ); ?></strong> <?php esc_html_e( 'Invalid JavaScript can break booking forms. Test thoroughly.', 'bkx-white-label' ); ?></p>
				</div>

				<textarea id="custom_js_frontend" name="custom_js_frontend" rows="20" class="large-text code bkx-code-editor" data-lang="javascript"><?php echo esc_textarea( $settings['custom_js_frontend'] ?? '' ); ?></textarea>

				<div class="bkx-code-examples">
					<h4><?php esc_html_e( 'Examples', 'bkx-white-label' ); ?></h4>
					<pre><code>// jQuery is available
jQuery(document).ready(function($) {
    // Track booking form submissions
    $('.bkx-booking-form').on('submit', function() {
        // Google Analytics event
        if (typeof gtag !== 'undefined') {
            gtag('event', 'booking_submit', {
                'event_category': 'Bookings'
            });
        }
    });

    // Add custom animations
    $('.bkx-service-card').hover(
        function() { $(this).addClass('animated'); },
        function() { $(this).removeClass('animated'); }
    );
});</code></pre>
				</div>
			</div>
		</div>
	</div>
</div>

<style>
.bkx-code-tabs {
	display: flex;
	gap: 5px;
	margin-bottom: 0;
	border-bottom: 1px solid #ddd;
	padding-bottom: 0;
}
.bkx-code-tab {
	background: #f5f5f5;
	border: 1px solid #ddd;
	border-bottom: none;
	padding: 10px 20px;
	cursor: pointer;
	border-radius: 4px 4px 0 0;
	display: flex;
	align-items: center;
	gap: 8px;
}
.bkx-code-tab:hover {
	background: #fff;
}
.bkx-code-tab.active {
	background: #fff;
	border-bottom-color: #fff;
	margin-bottom: -1px;
}
.bkx-code-panels {
	background: #fff;
	border: 1px solid #ddd;
	border-top: none;
	padding: 20px;
	border-radius: 0 0 4px 4px;
}
.bkx-code-panel {
	display: none;
}
.bkx-code-panel.active {
	display: block;
}
.bkx-code-editor {
	font-family: monospace;
	font-size: 13px;
	background: #23282d;
	color: #f1f1f1;
	padding: 15px;
	border-radius: 4px;
}
.bkx-code-examples {
	margin-top: 20px;
	background: #f9f9f9;
	padding: 15px;
	border-radius: 4px;
}
.bkx-code-examples h4 {
	margin: 0 0 10px;
}
.bkx-code-examples pre {
	background: #23282d;
	color: #f1f1f1;
	padding: 15px;
	border-radius: 4px;
	margin: 0;
	overflow-x: auto;
}
.bkx-notice-warning {
	background: #fff3cd;
	border-left: 4px solid #dba617;
	padding: 10px 15px;
	margin: 15px 0;
}
</style>
