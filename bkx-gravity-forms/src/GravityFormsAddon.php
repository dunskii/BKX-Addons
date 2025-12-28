<?php
/**
 * Main Gravity Forms Addon Class.
 *
 * @package BookingX\GravityForms
 * @since   1.0.0
 */

namespace BookingX\GravityForms;

use GFForms;
use GFAddOn;
use GFAPI;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GravityFormsAddon Class.
 */
class GravityFormsAddon extends GFAddOn {

	/**
	 * Instance.
	 *
	 * @var GravityFormsAddon
	 */
	private static $instance = null;

	/**
	 * Addon version.
	 *
	 * @var string
	 */
	protected $_version = BKX_GRAVITY_FORMS_VERSION;

	/**
	 * Minimum Gravity Forms version.
	 *
	 * @var string
	 */
	protected $_min_gravityforms_version = '2.5';

	/**
	 * Addon slug.
	 *
	 * @var string
	 */
	protected $_slug = 'bkx-gravity-forms';

	/**
	 * Full path to plugin.
	 *
	 * @var string
	 */
	protected $_path = 'bkx-gravity-forms/bkx-gravity-forms.php';

	/**
	 * Full path to this file.
	 *
	 * @var string
	 */
	protected $_full_path = __FILE__;

	/**
	 * Title.
	 *
	 * @var string
	 */
	protected $_title = 'BookingX Gravity Forms Integration';

	/**
	 * Short title.
	 *
	 * @var string
	 */
	protected $_short_title = 'BookingX';

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Get instance.
	 *
	 * @return GravityFormsAddon
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();

		$this->settings = get_option( 'bkx_gravity_forms_settings', array() );

		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Register custom field types.
		add_filter( 'gform_add_field_buttons', array( $this, 'add_field_buttons' ) );
		add_filter( 'gform_field_type_title', array( $this, 'field_type_title' ), 10, 2 );

		// Render custom fields.
		add_action( 'gform_field_input', array( $this, 'render_field_input' ), 10, 5 );
		add_action( 'gform_field_content', array( $this, 'field_content' ), 10, 5 );

		// Field settings.
		add_action( 'gform_field_standard_settings', array( $this, 'field_standard_settings' ), 10, 2 );
		add_action( 'gform_editor_js', array( $this, 'editor_script' ) );

		// Validation.
		add_filter( 'gform_field_validation', array( $this, 'validate_field' ), 10, 4 );

		// Submission handling.
		add_action( 'gform_after_submission', array( $this, 'after_submission' ), 10, 2 );

		// Entry display.
		add_filter( 'gform_entry_field_value', array( $this, 'entry_field_value' ), 10, 4 );

		// Merge tags.
		add_filter( 'gform_custom_merge_tags', array( $this, 'add_merge_tags' ), 10, 4 );
		add_filter( 'gform_replace_merge_tags', array( $this, 'replace_merge_tags' ), 10, 7 );

		// Enqueue scripts.
		add_action( 'gform_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10, 2 );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_gf_get_services', array( $this, 'ajax_get_services' ) );
		add_action( 'wp_ajax_nopriv_bkx_gf_get_services', array( $this, 'ajax_get_services' ) );
		add_action( 'wp_ajax_bkx_gf_get_seats', array( $this, 'ajax_get_seats' ) );
		add_action( 'wp_ajax_nopriv_bkx_gf_get_seats', array( $this, 'ajax_get_seats' ) );
		add_action( 'wp_ajax_bkx_gf_get_time_slots', array( $this, 'ajax_get_time_slots' ) );
		add_action( 'wp_ajax_nopriv_bkx_gf_get_time_slots', array( $this, 'ajax_get_time_slots' ) );
	}

	/**
	 * Get setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get_setting( $key, $default = null ) {
		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
	}

	/**
	 * Add custom field buttons to form editor.
	 *
	 * @param array $field_groups Field groups.
	 * @return array
	 */
	public function add_field_buttons( $field_groups ) {
		$alias_seat = get_option( 'bkx_alias_seat', __( 'Staff', 'bkx-gravity-forms' ) );
		$alias_base = get_option( 'bkx_alias_base', __( 'Service', 'bkx-gravity-forms' ) );

		$field_groups[] = array(
			'name'   => 'bookingx_fields',
			'label'  => __( 'BookingX Fields', 'bkx-gravity-forms' ),
			'fields' => array(
				array(
					'class'   => 'button',
					'value'   => sprintf( __( '%s Selector', 'bkx-gravity-forms' ), $alias_base ),
					'onclick' => "StartAddField('bkx_service');",
				),
				array(
					'class'   => 'button',
					'value'   => sprintf( __( '%s Selector', 'bkx-gravity-forms' ), $alias_seat ),
					'onclick' => "StartAddField('bkx_seat');",
				),
				array(
					'class'   => 'button',
					'value'   => __( 'Date Picker', 'bkx-gravity-forms' ),
					'onclick' => "StartAddField('bkx_date');",
				),
				array(
					'class'   => 'button',
					'value'   => __( 'Time Slots', 'bkx-gravity-forms' ),
					'onclick' => "StartAddField('bkx_time');",
				),
				array(
					'class'   => 'button',
					'value'   => __( 'Extras Selector', 'bkx-gravity-forms' ),
					'onclick' => "StartAddField('bkx_extras');",
				),
				array(
					'class'   => 'button',
					'value'   => __( 'Booking Summary', 'bkx-gravity-forms' ),
					'onclick' => "StartAddField('bkx_summary');",
				),
			),
		);

		return $field_groups;
	}

	/**
	 * Get field type title.
	 *
	 * @param string $type Field type.
	 * @return string
	 */
	public function field_type_title( $type ) {
		$alias_seat = get_option( 'bkx_alias_seat', __( 'Staff', 'bkx-gravity-forms' ) );
		$alias_base = get_option( 'bkx_alias_base', __( 'Service', 'bkx-gravity-forms' ) );

		$titles = array(
			'bkx_service' => sprintf( __( '%s Selector', 'bkx-gravity-forms' ), $alias_base ),
			'bkx_seat'    => sprintf( __( '%s Selector', 'bkx-gravity-forms' ), $alias_seat ),
			'bkx_date'    => __( 'Booking Date Picker', 'bkx-gravity-forms' ),
			'bkx_time'    => __( 'Time Slots', 'bkx-gravity-forms' ),
			'bkx_extras'  => __( 'Extras Selector', 'bkx-gravity-forms' ),
			'bkx_summary' => __( 'Booking Summary', 'bkx-gravity-forms' ),
		);

		return isset( $titles[ $type ] ) ? $titles[ $type ] : $type;
	}

	/**
	 * Render field input.
	 *
	 * @param string $input   Field input HTML.
	 * @param object $field   Field object.
	 * @param string $value   Field value.
	 * @param int    $lead_id Lead ID.
	 * @param int    $form_id Form ID.
	 * @return string
	 */
	public function render_field_input( $input, $field, $value, $lead_id, $form_id ) {
		if ( ! $this->is_bkx_field( $field->type ) ) {
			return $input;
		}

		$field_id    = $field->id;
		$input_name  = 'input_' . $field_id;
		$input_id    = 'input_' . $form_id . '_' . $field_id;
		$class       = 'bkx-gf-field bkx-gf-' . $field->type;
		$placeholder = isset( $field->placeholder ) ? $field->placeholder : '';

		switch ( $field->type ) {
			case 'bkx_service':
				$input = $this->render_service_field( $field, $value, $input_name, $input_id );
				break;

			case 'bkx_seat':
				$input = $this->render_seat_field( $field, $value, $input_name, $input_id );
				break;

			case 'bkx_date':
				$input = $this->render_date_field( $field, $value, $input_name, $input_id );
				break;

			case 'bkx_time':
				$input = $this->render_time_field( $field, $value, $input_name, $input_id );
				break;

			case 'bkx_extras':
				$input = $this->render_extras_field( $field, $value, $input_name, $input_id );
				break;

			case 'bkx_summary':
				$input = $this->render_summary_field( $field, $input_id );
				break;
		}

		return $input;
	}

	/**
	 * Render service selector field.
	 *
	 * @param object $field      Field object.
	 * @param mixed  $value      Field value.
	 * @param string $input_name Input name.
	 * @param string $input_id   Input ID.
	 * @return string
	 */
	private function render_service_field( $field, $value, $input_name, $input_id ) {
		$services = get_posts(
			array(
				'post_type'      => 'bkx_base',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$alias = get_option( 'bkx_alias_base', __( 'Service', 'bkx-gravity-forms' ) );
		$style = isset( $field->bkx_display_style ) ? $field->bkx_display_style : 'dropdown';

		ob_start();
		?>
		<div class="bkx-gf-service-field" data-field-id="<?php echo esc_attr( $field->id ); ?>">
			<?php if ( 'dropdown' === $style ) : ?>
				<select name="<?php echo esc_attr( $input_name ); ?>" id="<?php echo esc_attr( $input_id ); ?>" class="bkx-service-select">
					<option value=""><?php echo esc_html( sprintf( __( 'Select a %s', 'bkx-gravity-forms' ), $alias ) ); ?></option>
					<?php foreach ( $services as $service ) : ?>
						<?php
						$price    = get_post_meta( $service->ID, 'base_price', true );
						$duration = get_post_meta( $service->ID, 'base_time', true );
						$selected = ( $value == $service->ID ) ? 'selected' : '';
						?>
						<option value="<?php echo esc_attr( $service->ID ); ?>"
								data-price="<?php echo esc_attr( $price ); ?>"
								data-duration="<?php echo esc_attr( $duration ); ?>"
								<?php echo esc_attr( $selected ); ?>>
							<?php echo esc_html( $service->post_title ); ?>
							<?php if ( $price ) : ?>
								- $<?php echo esc_html( number_format( (float) $price, 2 ) ); ?>
							<?php endif; ?>
						</option>
					<?php endforeach; ?>
				</select>

			<?php elseif ( 'radio' === $style ) : ?>
				<div class="bkx-service-radio-list">
					<?php foreach ( $services as $service ) : ?>
						<?php
						$price    = get_post_meta( $service->ID, 'base_price', true );
						$duration = get_post_meta( $service->ID, 'base_time', true );
						$checked  = ( $value == $service->ID ) ? 'checked' : '';
						?>
						<label class="bkx-service-radio-item">
							<input type="radio"
								   name="<?php echo esc_attr( $input_name ); ?>"
								   value="<?php echo esc_attr( $service->ID ); ?>"
								   data-price="<?php echo esc_attr( $price ); ?>"
								   data-duration="<?php echo esc_attr( $duration ); ?>"
								   <?php echo esc_attr( $checked ); ?>>
							<span class="service-info">
								<span class="service-name"><?php echo esc_html( $service->post_title ); ?></span>
								<?php if ( $price || $duration ) : ?>
									<span class="service-meta">
										<?php if ( $price ) : ?>
											<span class="service-price">$<?php echo esc_html( number_format( (float) $price, 2 ) ); ?></span>
										<?php endif; ?>
										<?php if ( $duration ) : ?>
											<span class="service-duration"><?php echo esc_html( $duration ); ?> min</span>
										<?php endif; ?>
									</span>
								<?php endif; ?>
							</span>
						</label>
					<?php endforeach; ?>
				</div>

			<?php elseif ( 'cards' === $style ) : ?>
				<div class="bkx-service-cards">
					<input type="hidden" name="<?php echo esc_attr( $input_name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="bkx-service-hidden">
					<?php foreach ( $services as $service ) : ?>
						<?php
						$price    = get_post_meta( $service->ID, 'base_price', true );
						$duration = get_post_meta( $service->ID, 'base_time', true );
						$active   = ( $value == $service->ID ) ? 'active' : '';
						?>
						<div class="bkx-service-card <?php echo esc_attr( $active ); ?>"
							 data-id="<?php echo esc_attr( $service->ID ); ?>"
							 data-price="<?php echo esc_attr( $price ); ?>"
							 data-duration="<?php echo esc_attr( $duration ); ?>">
							<?php if ( has_post_thumbnail( $service->ID ) ) : ?>
								<div class="card-image">
									<?php echo get_the_post_thumbnail( $service->ID, 'thumbnail' ); ?>
								</div>
							<?php endif; ?>
							<div class="card-content">
								<h4 class="card-title"><?php echo esc_html( $service->post_title ); ?></h4>
								<?php if ( $price ) : ?>
									<span class="card-price">$<?php echo esc_html( number_format( (float) $price, 2 ) ); ?></span>
								<?php endif; ?>
								<?php if ( $duration ) : ?>
									<span class="card-duration"><?php echo esc_html( $duration ); ?> min</span>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render seat selector field.
	 *
	 * @param object $field      Field object.
	 * @param mixed  $value      Field value.
	 * @param string $input_name Input name.
	 * @param string $input_id   Input ID.
	 * @return string
	 */
	private function render_seat_field( $field, $value, $input_name, $input_id ) {
		$seats = get_posts(
			array(
				'post_type'      => 'bkx_seat',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$alias = get_option( 'bkx_alias_seat', __( 'Staff', 'bkx-gravity-forms' ) );
		$style = isset( $field->bkx_display_style ) ? $field->bkx_display_style : 'dropdown';

		ob_start();
		?>
		<div class="bkx-gf-seat-field" data-field-id="<?php echo esc_attr( $field->id ); ?>">
			<?php if ( 'dropdown' === $style ) : ?>
				<select name="<?php echo esc_attr( $input_name ); ?>" id="<?php echo esc_attr( $input_id ); ?>" class="bkx-seat-select">
					<option value=""><?php echo esc_html( sprintf( __( 'Select a %s', 'bkx-gravity-forms' ), $alias ) ); ?></option>
					<?php foreach ( $seats as $seat ) : ?>
						<?php $selected = ( $value == $seat->ID ) ? 'selected' : ''; ?>
						<option value="<?php echo esc_attr( $seat->ID ); ?>" <?php echo esc_attr( $selected ); ?>>
							<?php echo esc_html( $seat->post_title ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			<?php else : ?>
				<div class="bkx-seat-cards">
					<input type="hidden" name="<?php echo esc_attr( $input_name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="bkx-seat-hidden">
					<?php foreach ( $seats as $seat ) : ?>
						<?php
						$title  = get_post_meta( $seat->ID, 'seat_title', true );
						$active = ( $value == $seat->ID ) ? 'active' : '';
						?>
						<div class="bkx-seat-card <?php echo esc_attr( $active ); ?>" data-id="<?php echo esc_attr( $seat->ID ); ?>">
							<?php if ( has_post_thumbnail( $seat->ID ) ) : ?>
								<div class="card-image">
									<?php echo get_the_post_thumbnail( $seat->ID, 'thumbnail' ); ?>
								</div>
							<?php endif; ?>
							<div class="card-content">
								<h4 class="card-name"><?php echo esc_html( $seat->post_title ); ?></h4>
								<?php if ( $title ) : ?>
									<span class="card-title"><?php echo esc_html( $title ); ?></span>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render date picker field.
	 *
	 * @param object $field      Field object.
	 * @param mixed  $value      Field value.
	 * @param string $input_name Input name.
	 * @param string $input_id   Input ID.
	 * @return string
	 */
	private function render_date_field( $field, $value, $input_name, $input_id ) {
		$min_date = isset( $field->bkx_min_date ) ? $field->bkx_min_date : 0;
		$max_date = isset( $field->bkx_max_date ) ? $field->bkx_max_date : 90;

		ob_start();
		?>
		<div class="bkx-gf-date-field" data-field-id="<?php echo esc_attr( $field->id ); ?>">
			<input type="text"
				   name="<?php echo esc_attr( $input_name ); ?>"
				   id="<?php echo esc_attr( $input_id ); ?>"
				   class="bkx-date-picker"
				   value="<?php echo esc_attr( $value ); ?>"
				   readonly
				   data-min-date="<?php echo esc_attr( $min_date ); ?>"
				   data-max-date="<?php echo esc_attr( $max_date ); ?>">
			<div class="bkx-calendar-container"></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render time slots field.
	 *
	 * @param object $field      Field object.
	 * @param mixed  $value      Field value.
	 * @param string $input_name Input name.
	 * @param string $input_id   Input ID.
	 * @return string
	 */
	private function render_time_field( $field, $value, $input_name, $input_id ) {
		$style = isset( $field->bkx_display_style ) ? $field->bkx_display_style : 'grid';

		ob_start();
		?>
		<div class="bkx-gf-time-field bkx-time-<?php echo esc_attr( $style ); ?>" data-field-id="<?php echo esc_attr( $field->id ); ?>">
			<input type="hidden" name="<?php echo esc_attr( $input_name ); ?>" id="<?php echo esc_attr( $input_id ); ?>" value="<?php echo esc_attr( $value ); ?>" class="bkx-time-input">
			<div class="bkx-time-slots-container">
				<p class="bkx-select-date-prompt"><?php esc_html_e( 'Please select a date first', 'bkx-gravity-forms' ); ?></p>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render extras selector field.
	 *
	 * @param object $field      Field object.
	 * @param mixed  $value      Field value.
	 * @param string $input_name Input name.
	 * @param string $input_id   Input ID.
	 * @return string
	 */
	private function render_extras_field( $field, $value, $input_name, $input_id ) {
		$extras = get_posts(
			array(
				'post_type'      => 'bkx_addition',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$selected = is_array( $value ) ? $value : array();

		ob_start();
		?>
		<div class="bkx-gf-extras-field" data-field-id="<?php echo esc_attr( $field->id ); ?>">
			<?php if ( empty( $extras ) ) : ?>
				<p class="bkx-no-extras"><?php esc_html_e( 'No extras available', 'bkx-gravity-forms' ); ?></p>
			<?php else : ?>
				<div class="bkx-extras-list">
					<?php foreach ( $extras as $extra ) : ?>
						<?php
						$price   = get_post_meta( $extra->ID, 'addition_price', true );
						$checked = in_array( $extra->ID, $selected, true ) ? 'checked' : '';
						?>
						<label class="bkx-extra-item">
							<input type="checkbox"
								   name="<?php echo esc_attr( $input_name ); ?>[]"
								   value="<?php echo esc_attr( $extra->ID ); ?>"
								   data-price="<?php echo esc_attr( $price ); ?>"
								   <?php echo esc_attr( $checked ); ?>>
							<span class="extra-info">
								<span class="extra-name"><?php echo esc_html( $extra->post_title ); ?></span>
								<?php if ( $price ) : ?>
									<span class="extra-price">+$<?php echo esc_html( number_format( (float) $price, 2 ) ); ?></span>
								<?php endif; ?>
							</span>
						</label>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render booking summary field.
	 *
	 * @param object $field    Field object.
	 * @param string $input_id Input ID.
	 * @return string
	 */
	private function render_summary_field( $field, $input_id ) {
		ob_start();
		?>
		<div class="bkx-gf-summary-field" data-field-id="<?php echo esc_attr( $field->id ); ?>">
			<div class="bkx-summary-content">
				<div class="summary-row summary-service">
					<span class="label"><?php esc_html_e( 'Service:', 'bkx-gravity-forms' ); ?></span>
					<span class="value">-</span>
				</div>
				<div class="summary-row summary-seat">
					<span class="label"><?php echo esc_html( get_option( 'bkx_alias_seat', __( 'Staff', 'bkx-gravity-forms' ) ) ); ?>:</span>
					<span class="value">-</span>
				</div>
				<div class="summary-row summary-datetime">
					<span class="label"><?php esc_html_e( 'Date & Time:', 'bkx-gravity-forms' ); ?></span>
					<span class="value">-</span>
				</div>
				<div class="summary-row summary-extras" style="display: none;">
					<span class="label"><?php esc_html_e( 'Extras:', 'bkx-gravity-forms' ); ?></span>
					<span class="value">-</span>
				</div>
				<div class="summary-row summary-total">
					<span class="label"><?php esc_html_e( 'Total:', 'bkx-gravity-forms' ); ?></span>
					<span class="value">$0.00</span>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Check if field type is a BookingX field.
	 *
	 * @param string $type Field type.
	 * @return bool
	 */
	private function is_bkx_field( $type ) {
		return in_array(
			$type,
			array( 'bkx_service', 'bkx_seat', 'bkx_date', 'bkx_time', 'bkx_extras', 'bkx_summary' ),
			true
		);
	}

	/**
	 * Add field standard settings.
	 *
	 * @param int $position Position.
	 * @param int $form_id  Form ID.
	 */
	public function field_standard_settings( $position, $form_id ) {
		if ( 25 !== $position ) {
			return;
		}
		?>
		<li class="bkx_display_style_setting field_setting">
			<label for="bkx_display_style" class="section_label">
				<?php esc_html_e( 'Display Style', 'bkx-gravity-forms' ); ?>
			</label>
			<select id="bkx_display_style" onchange="SetFieldProperty('bkx_display_style', this.value);">
				<option value="dropdown"><?php esc_html_e( 'Dropdown', 'bkx-gravity-forms' ); ?></option>
				<option value="radio"><?php esc_html_e( 'Radio Buttons', 'bkx-gravity-forms' ); ?></option>
				<option value="cards"><?php esc_html_e( 'Cards', 'bkx-gravity-forms' ); ?></option>
				<option value="grid"><?php esc_html_e( 'Grid', 'bkx-gravity-forms' ); ?></option>
			</select>
		</li>
		<?php
	}

	/**
	 * Editor JavaScript.
	 */
	public function editor_script() {
		?>
		<script type="text/javascript">
			// Add BookingX fields to field settings
			fieldSettings['bkx_service'] = '.bkx_display_style_setting, .label_setting, .description_setting, .rules_setting, .error_message_setting, .css_class_setting, .visibility_setting';
			fieldSettings['bkx_seat'] = '.bkx_display_style_setting, .label_setting, .description_setting, .rules_setting, .error_message_setting, .css_class_setting, .visibility_setting';
			fieldSettings['bkx_date'] = '.label_setting, .description_setting, .rules_setting, .error_message_setting, .css_class_setting, .visibility_setting';
			fieldSettings['bkx_time'] = '.bkx_display_style_setting, .label_setting, .description_setting, .rules_setting, .error_message_setting, .css_class_setting, .visibility_setting';
			fieldSettings['bkx_extras'] = '.label_setting, .description_setting, .css_class_setting, .visibility_setting';
			fieldSettings['bkx_summary'] = '.label_setting, .description_setting, .css_class_setting, .visibility_setting';

			// Bind display style setting
			jQuery(document).on('gform_load_field_settings', function(event, field, form) {
				jQuery('#bkx_display_style').val(field['bkx_display_style'] || 'dropdown');
			});
		</script>
		<?php
	}

	/**
	 * Validate field.
	 *
	 * @param array  $result Validation result.
	 * @param mixed  $value  Field value.
	 * @param array  $form   Form data.
	 * @param object $field  Field object.
	 * @return array
	 */
	public function validate_field( $result, $value, $form, $field ) {
		if ( ! $this->is_bkx_field( $field->type ) ) {
			return $result;
		}

		if ( $field->isRequired && empty( $value ) ) {
			$result['is_valid'] = false;
			$result['message']  = empty( $field->errorMessage )
				? __( 'This field is required.', 'bkx-gravity-forms' )
				: $field->errorMessage;
		}

		// Additional validations.
		switch ( $field->type ) {
			case 'bkx_date':
				if ( ! empty( $value ) ) {
					$date = \DateTime::createFromFormat( 'Y-m-d', $value );
					if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) {
						$result['is_valid'] = false;
						$result['message']  = __( 'Please select a valid date.', 'bkx-gravity-forms' );
					} elseif ( $date < new \DateTime( 'today' ) ) {
						$result['is_valid'] = false;
						$result['message']  = __( 'Please select a future date.', 'bkx-gravity-forms' );
					}
				}
				break;

			case 'bkx_time':
				if ( ! empty( $value ) && ! preg_match( '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $value ) ) {
					$result['is_valid'] = false;
					$result['message']  = __( 'Please select a valid time.', 'bkx-gravity-forms' );
				}
				break;
		}

		return $result;
	}

	/**
	 * After form submission.
	 *
	 * @param array $entry Form entry.
	 * @param array $form  Form data.
	 */
	public function after_submission( $entry, $form ) {
		if ( ! $this->get_setting( 'auto_create_booking', true ) ) {
			return;
		}

		// Check if form has BookingX fields.
		$booking_data = $this->extract_booking_data( $entry, $form );

		if ( empty( $booking_data['service_id'] ) ) {
			return;
		}

		// Create booking.
		$booking_id = $this->create_booking( $booking_data, $entry );

		if ( $booking_id ) {
			// Store booking ID in entry meta.
			gform_update_meta( $entry['id'], 'bkx_booking_id', $booking_id );

			/**
			 * Fires after a booking is created from Gravity Forms.
			 *
			 * @param int   $booking_id   Booking ID.
			 * @param array $booking_data Booking data.
			 * @param array $entry        Form entry.
			 * @param array $form         Form data.
			 */
			do_action( 'bkx_gf_booking_created', $booking_id, $booking_data, $entry, $form );
		}
	}

	/**
	 * Extract booking data from entry.
	 *
	 * @param array $entry Form entry.
	 * @param array $form  Form data.
	 * @return array
	 */
	private function extract_booking_data( $entry, $form ) {
		$data = array(
			'service_id' => 0,
			'seat_id'    => 0,
			'date'       => '',
			'time'       => '',
			'extras'     => array(),
			'name'       => '',
			'email'      => '',
			'phone'      => '',
		);

		foreach ( $form['fields'] as $field ) {
			$value = rgar( $entry, $field->id );

			switch ( $field->type ) {
				case 'bkx_service':
					$data['service_id'] = absint( $value );
					break;

				case 'bkx_seat':
					$data['seat_id'] = absint( $value );
					break;

				case 'bkx_date':
					$data['date'] = sanitize_text_field( $value );
					break;

				case 'bkx_time':
					$data['time'] = sanitize_text_field( $value );
					break;

				case 'bkx_extras':
					$data['extras'] = is_array( $value ) ? array_map( 'absint', $value ) : array();
					break;

				case 'name':
				case 'text':
					if ( stripos( $field->label, 'name' ) !== false && empty( $data['name'] ) ) {
						$data['name'] = sanitize_text_field( $value );
					}
					break;

				case 'email':
					$data['email'] = sanitize_email( $value );
					break;

				case 'phone':
					$data['phone'] = sanitize_text_field( $value );
					break;
			}
		}

		return $data;
	}

	/**
	 * Create booking from form data.
	 *
	 * @param array $data  Booking data.
	 * @param array $entry Form entry.
	 * @return int|false Booking ID or false on failure.
	 */
	private function create_booking( $data, $entry ) {
		// Calculate total price.
		$service_price = (float) get_post_meta( $data['service_id'], 'base_price', true );
		$extras_total  = 0;

		foreach ( $data['extras'] as $extra_id ) {
			$extras_total += (float) get_post_meta( $extra_id, 'addition_price', true );
		}

		$total = $service_price + $extras_total;

		// Create booking post.
		$booking_id = wp_insert_post(
			array(
				'post_type'   => 'bkx_booking',
				'post_status' => 'bkx-pending',
				'post_title'  => sprintf(
					/* translators: 1: Service name, 2: Date */
					__( 'Booking: %1$s on %2$s', 'bkx-gravity-forms' ),
					get_the_title( $data['service_id'] ),
					$data['date']
				),
			)
		);

		if ( is_wp_error( $booking_id ) ) {
			return false;
		}

		// Save booking meta.
		update_post_meta( $booking_id, 'booking_date', $data['date'] );
		update_post_meta( $booking_id, 'booking_time', $data['time'] );
		update_post_meta( $booking_id, 'seat_id', $data['seat_id'] );
		update_post_meta( $booking_id, 'base_id', $data['service_id'] );
		update_post_meta( $booking_id, 'addition_ids', $data['extras'] );
		update_post_meta( $booking_id, 'booking_total', $total );
		update_post_meta( $booking_id, 'customer_name', $data['name'] );
		update_post_meta( $booking_id, 'customer_email', $data['email'] );
		update_post_meta( $booking_id, 'customer_phone', $data['phone'] );
		update_post_meta( $booking_id, 'gf_entry_id', $entry['id'] );
		update_post_meta( $booking_id, 'gf_form_id', $entry['form_id'] );

		// Trigger BookingX hooks.
		do_action( 'bkx_booking_created', $booking_id, array(
			'seat_id'    => $data['seat_id'],
			'base_id'    => $data['service_id'],
			'extras'     => $data['extras'],
			'date'       => $data['date'],
			'time'       => $data['time'],
			'total'      => $total,
			'customer'   => array(
				'name'  => $data['name'],
				'email' => $data['email'],
				'phone' => $data['phone'],
			),
		) );

		return $booking_id;
	}

	/**
	 * Format entry field value for display.
	 *
	 * @param string $value      Field value.
	 * @param object $field      Field object.
	 * @param array  $entry      Entry data.
	 * @param array  $form       Form data.
	 * @return string
	 */
	public function entry_field_value( $value, $field, $entry, $form ) {
		if ( ! $this->is_bkx_field( $field->type ) ) {
			return $value;
		}

		switch ( $field->type ) {
			case 'bkx_service':
				$service = get_post( absint( $value ) );
				return $service ? esc_html( $service->post_title ) : $value;

			case 'bkx_seat':
				$seat = get_post( absint( $value ) );
				return $seat ? esc_html( $seat->post_title ) : $value;

			case 'bkx_extras':
				$extras = is_serialized( $value ) ? maybe_unserialize( $value ) : $value;
				if ( is_array( $extras ) ) {
					$names = array();
					foreach ( $extras as $extra_id ) {
						$extra = get_post( absint( $extra_id ) );
						if ( $extra ) {
							$names[] = esc_html( $extra->post_title );
						}
					}
					return implode( ', ', $names );
				}
				return $value;
		}

		return $value;
	}

	/**
	 * Add custom merge tags.
	 *
	 * @param array $merge_tags Existing merge tags.
	 * @param int   $form_id    Form ID.
	 * @param array $fields     Form fields.
	 * @param int   $element_id Element ID.
	 * @return array
	 */
	public function add_merge_tags( $merge_tags, $form_id, $fields, $element_id ) {
		$merge_tags[] = array(
			'label' => __( 'Booking ID', 'bkx-gravity-forms' ),
			'tag'   => '{bkx_booking_id}',
		);
		$merge_tags[] = array(
			'label' => __( 'Booking Total', 'bkx-gravity-forms' ),
			'tag'   => '{bkx_booking_total}',
		);
		$merge_tags[] = array(
			'label' => __( 'Booking Details', 'bkx-gravity-forms' ),
			'tag'   => '{bkx_booking_details}',
		);

		return $merge_tags;
	}

	/**
	 * Replace merge tags.
	 *
	 * @param string $text       Text with merge tags.
	 * @param array  $form       Form data.
	 * @param array  $entry      Entry data.
	 * @param bool   $url_encode URL encode.
	 * @param bool   $esc_html   Escape HTML.
	 * @param bool   $nl2br      Convert newlines.
	 * @param string $format     Format.
	 * @return string
	 */
	public function replace_merge_tags( $text, $form, $entry, $url_encode, $esc_html, $nl2br, $format ) {
		$booking_id = gform_get_meta( $entry['id'], 'bkx_booking_id' );

		if ( $booking_id ) {
			$text = str_replace( '{bkx_booking_id}', $booking_id, $text );

			$total = get_post_meta( $booking_id, 'booking_total', true );
			$text  = str_replace( '{bkx_booking_total}', '$' . number_format( (float) $total, 2 ), $text );

			// Booking details.
			$details = $this->get_booking_details_html( $booking_id );
			$text    = str_replace( '{bkx_booking_details}', $details, $text );
		} else {
			$text = str_replace( '{bkx_booking_id}', '', $text );
			$text = str_replace( '{bkx_booking_total}', '', $text );
			$text = str_replace( '{bkx_booking_details}', '', $text );
		}

		return $text;
	}

	/**
	 * Get booking details HTML.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string
	 */
	private function get_booking_details_html( $booking_id ) {
		$service_id = get_post_meta( $booking_id, 'base_id', true );
		$seat_id    = get_post_meta( $booking_id, 'seat_id', true );
		$date       = get_post_meta( $booking_id, 'booking_date', true );
		$time       = get_post_meta( $booking_id, 'booking_time', true );
		$total      = get_post_meta( $booking_id, 'booking_total', true );

		$service = get_post( $service_id );
		$seat    = get_post( $seat_id );

		ob_start();
		?>
		<table style="width: 100%; border-collapse: collapse;">
			<tr>
				<td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong><?php esc_html_e( 'Service:', 'bkx-gravity-forms' ); ?></strong></td>
				<td style="padding: 8px; border-bottom: 1px solid #ddd;"><?php echo $service ? esc_html( $service->post_title ) : '-'; ?></td>
			</tr>
			<tr>
				<td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong><?php echo esc_html( get_option( 'bkx_alias_seat', __( 'Staff', 'bkx-gravity-forms' ) ) ); ?>:</strong></td>
				<td style="padding: 8px; border-bottom: 1px solid #ddd;"><?php echo $seat ? esc_html( $seat->post_title ) : '-'; ?></td>
			</tr>
			<tr>
				<td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong><?php esc_html_e( 'Date:', 'bkx-gravity-forms' ); ?></strong></td>
				<td style="padding: 8px; border-bottom: 1px solid #ddd;"><?php echo esc_html( $date ); ?></td>
			</tr>
			<tr>
				<td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong><?php esc_html_e( 'Time:', 'bkx-gravity-forms' ); ?></strong></td>
				<td style="padding: 8px; border-bottom: 1px solid #ddd;"><?php echo esc_html( $time ); ?></td>
			</tr>
			<tr>
				<td style="padding: 8px;"><strong><?php esc_html_e( 'Total:', 'bkx-gravity-forms' ); ?></strong></td>
				<td style="padding: 8px;">$<?php echo esc_html( number_format( (float) $total, 2 ) ); ?></td>
			</tr>
		</table>
		<?php
		return ob_get_clean();
	}

	/**
	 * Enqueue scripts.
	 *
	 * @param array $form  Form data.
	 * @param bool  $ajax  Is AJAX.
	 */
	public function enqueue_scripts( $form, $ajax ) {
		$has_bkx_field = false;

		foreach ( $form['fields'] as $field ) {
			if ( $this->is_bkx_field( $field->type ) ) {
				$has_bkx_field = true;
				break;
			}
		}

		if ( ! $has_bkx_field ) {
			return;
		}

		wp_enqueue_style(
			'bkx-gravity-forms-frontend',
			BKX_GRAVITY_FORMS_URL . 'assets/css/frontend.css',
			array(),
			BKX_GRAVITY_FORMS_VERSION
		);

		wp_enqueue_script(
			'bkx-gravity-forms-frontend',
			BKX_GRAVITY_FORMS_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			BKX_GRAVITY_FORMS_VERSION,
			true
		);

		wp_localize_script(
			'bkx-gravity-forms-frontend',
			'bkxGravityForms',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_gf_nonce' ),
				'formId'  => $form['id'],
				'i18n'    => array(
					'selectDate'  => __( 'Please select a date', 'bkx-gravity-forms' ),
					'selectTime'  => __( 'Please select a time', 'bkx-gravity-forms' ),
					'noSlots'     => __( 'No available time slots', 'bkx-gravity-forms' ),
					'loading'     => __( 'Loading...', 'bkx-gravity-forms' ),
				),
			)
		);
	}

	/**
	 * AJAX: Get services.
	 */
	public function ajax_get_services() {
		check_ajax_referer( 'bkx_gf_nonce', 'nonce' );

		$services = get_posts(
			array(
				'post_type'      => 'bkx_base',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$result = array();

		foreach ( $services as $service ) {
			$result[] = array(
				'id'       => $service->ID,
				'title'    => $service->post_title,
				'price'    => get_post_meta( $service->ID, 'base_price', true ),
				'duration' => get_post_meta( $service->ID, 'base_time', true ),
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Get seats.
	 */
	public function ajax_get_seats() {
		check_ajax_referer( 'bkx_gf_nonce', 'nonce' );

		$service_id = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;

		$args = array(
			'post_type'      => 'bkx_seat',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		// Filter by service if specified.
		if ( $service_id ) {
			$args['meta_query'] = array(
				array(
					'key'     => 'seat_services',
					'value'   => $service_id,
					'compare' => 'LIKE',
				),
			);
		}

		$seats  = get_posts( $args );
		$result = array();

		foreach ( $seats as $seat ) {
			$result[] = array(
				'id'    => $seat->ID,
				'title' => $seat->post_title,
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Get time slots.
	 */
	public function ajax_get_time_slots() {
		check_ajax_referer( 'bkx_gf_nonce', 'nonce' );

		$service_id = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;
		$seat_id    = isset( $_POST['seat_id'] ) ? absint( $_POST['seat_id'] ) : 0;
		$date       = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';

		if ( ! $date ) {
			wp_send_json_error( array( 'message' => __( 'Date is required.', 'bkx-gravity-forms' ) ) );
		}

		$slots = array();

		// Use BookingX function if available.
		if ( function_exists( 'bkx_get_available_slots' ) ) {
			$slots = bkx_get_available_slots( $seat_id, $service_id, $date );
		} else {
			// Fallback: Generate basic time slots.
			$start = 9; // 9 AM.
			$end   = 17; // 5 PM.

			for ( $hour = $start; $hour < $end; $hour++ ) {
				foreach ( array( '00', '30' ) as $minute ) {
					$time = sprintf( '%02d:%s', $hour, $minute );
					$slots[] = array(
						'time'      => $time,
						'display'   => gmdate( 'g:i A', strtotime( $time ) ),
						'available' => true,
					);
				}
			}
		}

		wp_send_json_success( $slots );
	}

	/**
	 * Plugin settings fields.
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {
		return array(
			array(
				'title'  => __( 'BookingX Settings', 'bkx-gravity-forms' ),
				'fields' => array(
					array(
						'name'    => 'auto_create_booking',
						'label'   => __( 'Auto-create Bookings', 'bkx-gravity-forms' ),
						'type'    => 'checkbox',
						'choices' => array(
							array(
								'name'          => 'auto_create_booking',
								'label'         => __( 'Automatically create BookingX bookings from form submissions', 'bkx-gravity-forms' ),
								'default_value' => 1,
							),
						),
					),
					array(
						'name'    => 'send_notifications',
						'label'   => __( 'Send Notifications', 'bkx-gravity-forms' ),
						'type'    => 'checkbox',
						'choices' => array(
							array(
								'name'          => 'send_notifications',
								'label'         => __( 'Send BookingX booking notifications', 'bkx-gravity-forms' ),
								'default_value' => 1,
							),
						),
					),
				),
			),
		);
	}
}

// Register with Gravity Forms.
add_action(
	'gform_loaded',
	function() {
		if ( method_exists( 'GFForms', 'include_addon_framework' ) ) {
			GFForms::include_addon_framework();
			GFAddOn::register( 'BookingX\\GravityForms\\GravityFormsAddon' );
		}
	},
	5
);
