<?php
/**
 * Service Select Elementor Control.
 *
 * @package BookingX\Elementor\Controls
 * @since   1.0.0
 */

namespace BookingX\Elementor\Controls;

use Elementor\Base_Data_Control;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ServiceSelectControl Class.
 */
class ServiceSelectControl extends Base_Data_Control {

	/**
	 * Get control type.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'bkx_service_select';
	}

	/**
	 * Enqueue control scripts and styles.
	 */
	public function enqueue() {
		wp_enqueue_script(
			'bkx-elementor-service-select',
			BKX_ELEMENTOR_URL . 'assets/js/controls/service-select.js',
			array( 'jquery' ),
			BKX_ELEMENTOR_VERSION,
			true
		);

		wp_localize_script(
			'bkx-elementor-service-select',
			'bkxServiceSelectControl',
			array(
				'services' => $this->get_services(),
				'i18n'     => array(
					'selectService' => __( 'Select a service', 'bkx-elementor' ),
					'allServices'   => __( 'All Services', 'bkx-elementor' ),
					'noServices'    => __( 'No services found', 'bkx-elementor' ),
				),
			)
		);
	}

	/**
	 * Get default settings.
	 *
	 * @return array
	 */
	protected function get_default_settings() {
		return array(
			'label_block' => true,
			'multiple'    => false,
			'options'     => array(),
		);
	}

	/**
	 * Render control content.
	 */
	public function content_template() {
		$control_uid = $this->get_control_uid();
		?>
		<div class="elementor-control-field">
			<# if ( data.label ) { #>
				<label for="<?php echo esc_attr( $control_uid ); ?>" class="elementor-control-title">{{{ data.label }}}</label>
			<# } #>
			<div class="elementor-control-input-wrapper elementor-control-unit-5">
				<# if ( data.multiple ) { #>
					<select id="<?php echo esc_attr( $control_uid ); ?>"
							class="elementor-control-bkx-service-select"
							data-setting="{{ data.name }}"
							multiple>
				<# } else { #>
					<select id="<?php echo esc_attr( $control_uid ); ?>"
							class="elementor-control-bkx-service-select"
							data-setting="{{ data.name }}">
						<option value=""><?php esc_html_e( 'All Services', 'bkx-elementor' ); ?></option>
				<# } #>
				<# _.each( data.options, function( option ) { #>
					<option value="{{ option.id }}"
							data-price="{{ option.price }}"
							data-duration="{{ option.duration }}"
							<# if ( data.controlValue == option.id ) { #> selected <# } #>>
						{{{ option.title }}}
						<# if ( option.price ) { #>
							- ${{ option.price }}
						<# } #>
					</option>
				<# }); #>
				</select>
			</div>
			<# if ( data.description ) { #>
				<div class="elementor-control-field-description">{{{ data.description }}}</div>
			<# } #>
		</div>
		<?php
	}

	/**
	 * Get services for control options.
	 *
	 * @return array
	 */
	private function get_services() {
		$services = get_posts(
			array(
				'post_type'      => 'bkx_base',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$options = array();

		foreach ( $services as $service ) {
			$options[] = array(
				'id'       => $service->ID,
				'title'    => $service->post_title,
				'price'    => get_post_meta( $service->ID, 'base_price', true ),
				'duration' => get_post_meta( $service->ID, 'base_time', true ),
			);
		}

		return $options;
	}
}
