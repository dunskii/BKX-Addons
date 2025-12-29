<?php
/**
 * Preview Service.
 *
 * @package BookingX\AdvancedEmailTemplates
 */

namespace BookingX\AdvancedEmailTemplates\Services;

defined( 'ABSPATH' ) || exit;

/**
 * PreviewService class.
 */
class PreviewService {

	/**
	 * Variable service.
	 *
	 * @var VariableService
	 */
	private $variable_service;

	/**
	 * Constructor.
	 *
	 * @param VariableService $variable_service Variable service.
	 */
	public function __construct( VariableService $variable_service ) {
		$this->variable_service = $variable_service;
	}

	/**
	 * Render preview.
	 *
	 * @param string $content Content.
	 * @param string $subject Subject.
	 * @return string
	 */
	public function render_preview( $content, $subject = '' ) {
		$sample_data = $this->variable_service->get_sample_data();

		// Replace variables.
		$content = $this->variable_service->replace_variables( $content, $sample_data );
		$subject = $this->variable_service->replace_variables( $subject, $sample_data );

		// Process conditionals.
		$content = $this->process_conditionals( $content, $sample_data );

		// Wrap in preview layout.
		return $this->wrap_in_preview_layout( $content, $subject );
	}

	/**
	 * Process conditionals.
	 *
	 * @param string $content Content.
	 * @param array  $data    Data.
	 * @return string
	 */
	private function process_conditionals( $content, $data ) {
		$pattern = '/\{\{#if\s+(\w+)\}\}(.*?)\{\{\/if\}\}/s';

		return preg_replace_callback(
			$pattern,
			function ( $matches ) use ( $data ) {
				$variable = $matches[1];
				$block    = $matches[2];

				if ( ! empty( $data[ $variable ] ) ) {
					return $block;
				}

				return '';
			},
			$content
		);
	}

	/**
	 * Wrap content in preview layout.
	 *
	 * @param string $content Content.
	 * @param string $subject Subject.
	 * @return string
	 */
	private function wrap_in_preview_layout( $content, $subject ) {
		$settings    = get_option( 'bkx_email_templates_settings', array() );
		$logo        = $settings['logo'] ?? '';
		$footer_text = $settings['footer_text'] ?? get_bloginfo( 'name' );
		$bg_color    = $settings['bg_color'] ?? '#f7f7f7';
		$text_color  = $settings['text_color'] ?? '#333333';
		$link_color  = $settings['link_color'] ?? '#2271b1';

		ob_start();
		?>
		<div class="bkx-email-preview-frame">
			<div class="bkx-email-preview-header">
				<div class="preview-meta">
					<div class="preview-subject">
						<strong><?php esc_html_e( 'Subject:', 'bkx-advanced-email-templates' ); ?></strong>
						<?php echo esc_html( $subject ); ?>
					</div>
					<div class="preview-from">
						<strong><?php esc_html_e( 'From:', 'bkx-advanced-email-templates' ); ?></strong>
						<?php echo esc_html( get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>' ); ?>
					</div>
				</div>
			</div>
			<div class="bkx-email-preview-body" style="background: <?php echo esc_attr( $bg_color ); ?>; color: <?php echo esc_attr( $text_color ); ?>;">
				<div class="email-wrapper" style="max-width: 600px; margin: 0 auto; padding: 20px;">
					<div class="email-header" style="text-align: center; padding: 20px 0;">
						<?php if ( $logo ) : ?>
							<img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" style="max-width: 200px; height: auto;">
						<?php else : ?>
							<h1><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
						<?php endif; ?>
					</div>

					<div class="email-content" style="background: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
						<?php echo wp_kses_post( $content ); ?>
					</div>

					<div class="email-footer" style="text-align: center; padding: 20px; font-size: 12px; color: #999999;">
						<p><?php echo esc_html( $footer_text ); ?></p>
						<p>&copy; <?php echo esc_html( gmdate( 'Y' ) . ' ' . get_bloginfo( 'name' ) ); ?></p>
					</div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render desktop preview.
	 *
	 * @param string $html Email HTML.
	 * @return string
	 */
	public function render_desktop_preview( $html ) {
		ob_start();
		?>
		<div class="bkx-preview-desktop">
			<div class="preview-chrome">
				<div class="chrome-buttons">
					<span class="btn-close"></span>
					<span class="btn-minimize"></span>
					<span class="btn-maximize"></span>
				</div>
				<div class="chrome-title"><?php esc_html_e( 'Email Preview', 'bkx-advanced-email-templates' ); ?></div>
			</div>
			<div class="preview-content">
				<?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render mobile preview.
	 *
	 * @param string $html Email HTML.
	 * @return string
	 */
	public function render_mobile_preview( $html ) {
		ob_start();
		?>
		<div class="bkx-preview-mobile">
			<div class="phone-frame">
				<div class="phone-notch"></div>
				<div class="phone-screen">
					<?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<div class="phone-button"></div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
