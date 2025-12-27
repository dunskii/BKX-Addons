<?php
/**
 * Settings Page Builder
 *
 * Builds admin settings pages for add-ons.
 *
 * @package    BookingX\AddonSDK
 * @subpackage Admin
 * @since      1.0.0
 */

namespace BookingX\AddonSDK\Admin;

/**
 * Settings page builder class.
 *
 * @since 1.0.0
 */
class SettingsPage {

    /**
     * Page slug.
     *
     * @var string
     */
    protected string $page_slug;

    /**
     * Page title.
     *
     * @var string
     */
    protected string $page_title;

    /**
     * Menu title.
     *
     * @var string
     */
    protected string $menu_title;

    /**
     * Parent menu slug.
     *
     * @var string
     */
    protected string $parent_slug = 'edit.php?post_type=bkx_booking';

    /**
     * Required capability.
     *
     * @var string
     */
    protected string $capability = 'manage_options';

    /**
     * Settings sections.
     *
     * @var array
     */
    protected array $sections = [];

    /**
     * Settings fields.
     *
     * @var array
     */
    protected array $fields = [];

    /**
     * Option group.
     *
     * @var string
     */
    protected string $option_group;

    /**
     * Option name.
     *
     * @var string
     */
    protected string $option_name;

    /**
     * Constructor.
     *
     * @param string $page_slug  Page slug.
     * @param string $page_title Page title.
     * @param string $menu_title Menu title.
     */
    public function __construct( string $page_slug, string $page_title, string $menu_title = '' ) {
        $this->page_slug    = $page_slug;
        $this->page_title   = $page_title;
        $this->menu_title   = $menu_title ?: $page_title;
        $this->option_group = $page_slug . '_options';
        $this->option_name  = $page_slug . '_settings';
    }

    /**
     * Set parent menu.
     *
     * @since 1.0.0
     * @param string $parent_slug Parent menu slug.
     * @return self
     */
    public function set_parent( string $parent_slug ): self {
        $this->parent_slug = $parent_slug;
        return $this;
    }

    /**
     * Set required capability.
     *
     * @since 1.0.0
     * @param string $capability Capability.
     * @return self
     */
    public function set_capability( string $capability ): self {
        $this->capability = $capability;
        return $this;
    }

    /**
     * Set option name.
     *
     * @since 1.0.0
     * @param string $option_name Option name.
     * @return self
     */
    public function set_option_name( string $option_name ): self {
        $this->option_name = $option_name;
        return $this;
    }

    /**
     * Add a section.
     *
     * @since 1.0.0
     * @param string $id          Section ID.
     * @param string $title       Section title.
     * @param string $description Section description.
     * @return self
     */
    public function add_section( string $id, string $title, string $description = '' ): self {
        $this->sections[ $id ] = [
            'id'          => $id,
            'title'       => $title,
            'description' => $description,
        ];
        return $this;
    }

    /**
     * Add a field.
     *
     * @since 1.0.0
     * @param string $section Section ID.
     * @param string $id      Field ID.
     * @param string $title   Field title.
     * @param string $type    Field type.
     * @param array  $args    Additional arguments.
     * @return self
     */
    public function add_field( string $section, string $id, string $title, string $type = 'text', array $args = [] ): self {
        $this->fields[] = array_merge( [
            'id'          => $id,
            'title'       => $title,
            'type'        => $type,
            'section'     => $section,
            'description' => '',
            'default'     => '',
            'options'     => [],
            'placeholder' => '',
            'class'       => '',
            'required'    => false,
        ], $args );

        return $this;
    }

    /**
     * Register the settings page.
     *
     * @since 1.0.0
     * @return void
     */
    public function register(): void {
        add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    /**
     * Add the menu page.
     *
     * @since 1.0.0
     * @return void
     */
    public function add_menu_page(): void {
        add_submenu_page(
            $this->parent_slug,
            $this->page_title,
            $this->menu_title,
            $this->capability,
            $this->page_slug,
            [ $this, 'render_page' ]
        );
    }

    /**
     * Register settings.
     *
     * @since 1.0.0
     * @return void
     */
    public function register_settings(): void {
        register_setting(
            $this->option_group,
            $this->option_name,
            [
                'type'              => 'array',
                'sanitize_callback' => [ $this, 'sanitize_settings' ],
            ]
        );

        // Register sections
        foreach ( $this->sections as $section ) {
            add_settings_section(
                $section['id'],
                $section['title'],
                function() use ( $section ) {
                    if ( $section['description'] ) {
                        echo '<p>' . esc_html( $section['description'] ) . '</p>';
                    }
                },
                $this->page_slug
            );
        }

        // Register fields
        foreach ( $this->fields as $field ) {
            add_settings_field(
                $field['id'],
                $field['title'],
                [ $this, 'render_field' ],
                $this->page_slug,
                $field['section'],
                $field
            );
        }
    }

    /**
     * Sanitize settings.
     *
     * @since 1.0.0
     * @param array $input Input values.
     * @return array
     */
    public function sanitize_settings( array $input ): array {
        $sanitized = [];

        foreach ( $this->fields as $field ) {
            $id    = $field['id'];
            $type  = $field['type'];
            $value = $input[ $id ] ?? $field['default'];

            $sanitized[ $id ] = $this->sanitize_field( $value, $field );
        }

        return $sanitized;
    }

    /**
     * Sanitize a field value.
     *
     * @since 1.0.0
     * @param mixed $value Field value.
     * @param array $field Field config.
     * @return mixed
     */
    protected function sanitize_field( $value, array $field ) {
        switch ( $field['type'] ) {
            case 'email':
                return sanitize_email( $value );

            case 'url':
                return esc_url_raw( $value );

            case 'number':
                return floatval( $value );

            case 'checkbox':
            case 'toggle':
                return (bool) $value;

            case 'textarea':
                return sanitize_textarea_field( $value );

            case 'html':
            case 'wysiwyg':
                return wp_kses_post( $value );

            case 'select':
            case 'radio':
                $options = $field['options'] ?? [];
                return in_array( $value, array_keys( $options ), true ) ? $value : ( $field['default'] ?? '' );

            case 'multiselect':
            case 'checkbox_group':
                if ( ! is_array( $value ) ) {
                    return [];
                }
                $options = $field['options'] ?? [];
                return array_intersect( $value, array_keys( $options ) );

            case 'password':
            case 'text':
            default:
                return sanitize_text_field( $value );
        }
    }

    /**
     * Render the settings page.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_page(): void {
        if ( ! current_user_can( $this->capability ) ) {
            return;
        }

        // Show save message
        if ( isset( $_GET['settings-updated'] ) ) {
            add_settings_error(
                $this->page_slug . '_messages',
                $this->page_slug . '_message',
                __( 'Settings saved.', 'bkx-addon-sdk' ),
                'updated'
            );
        }

        settings_errors( $this->page_slug . '_messages' );

        ?>
        <div class="wrap bkx-settings-page">
            <h1><?php echo esc_html( $this->page_title ); ?></h1>

            <form action="options.php" method="post">
                <?php
                settings_fields( $this->option_group );
                do_settings_sections( $this->page_slug );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render a field.
     *
     * @since 1.0.0
     * @param array $field Field config.
     * @return void
     */
    public function render_field( array $field ): void {
        $options = get_option( $this->option_name, [] );
        $value   = $options[ $field['id'] ] ?? $field['default'];
        $name    = $this->option_name . '[' . $field['id'] . ']';
        $id      = $this->option_name . '_' . $field['id'];

        echo '<div class="bkx-field bkx-field-' . esc_attr( $field['type'] ) . '">';

        switch ( $field['type'] ) {
            case 'text':
            case 'email':
            case 'url':
            case 'number':
            case 'password':
                $this->render_input( $field, $name, $id, $value );
                break;

            case 'textarea':
                $this->render_textarea( $field, $name, $id, $value );
                break;

            case 'select':
                $this->render_select( $field, $name, $id, $value );
                break;

            case 'multiselect':
                $this->render_multiselect( $field, $name, $id, $value );
                break;

            case 'checkbox':
            case 'toggle':
                $this->render_checkbox( $field, $name, $id, $value );
                break;

            case 'radio':
                $this->render_radio( $field, $name, $id, $value );
                break;

            case 'checkbox_group':
                $this->render_checkbox_group( $field, $name, $id, $value );
                break;

            case 'wysiwyg':
                $this->render_wysiwyg( $field, $name, $id, $value );
                break;

            case 'color':
                $this->render_color( $field, $name, $id, $value );
                break;

            default:
                $this->render_input( $field, $name, $id, $value );
        }

        if ( ! empty( $field['description'] ) ) {
            echo '<p class="description">' . esc_html( $field['description'] ) . '</p>';
        }

        echo '</div>';
    }

    /**
     * Render an input field.
     */
    protected function render_input( array $field, string $name, string $id, $value ): void {
        printf(
            '<input type="%s" id="%s" name="%s" value="%s" class="regular-text %s" placeholder="%s" %s />',
            esc_attr( $field['type'] ),
            esc_attr( $id ),
            esc_attr( $name ),
            esc_attr( $value ),
            esc_attr( $field['class'] ?? '' ),
            esc_attr( $field['placeholder'] ?? '' ),
            $field['required'] ? 'required' : ''
        );
    }

    /**
     * Render a textarea field.
     */
    protected function render_textarea( array $field, string $name, string $id, $value ): void {
        printf(
            '<textarea id="%s" name="%s" class="large-text %s" rows="%d" placeholder="%s">%s</textarea>',
            esc_attr( $id ),
            esc_attr( $name ),
            esc_attr( $field['class'] ?? '' ),
            absint( $field['rows'] ?? 5 ),
            esc_attr( $field['placeholder'] ?? '' ),
            esc_textarea( $value )
        );
    }

    /**
     * Render a select field.
     */
    protected function render_select( array $field, string $name, string $id, $value ): void {
        printf(
            '<select id="%s" name="%s" class="%s">',
            esc_attr( $id ),
            esc_attr( $name ),
            esc_attr( $field['class'] ?? '' )
        );

        foreach ( $field['options'] as $option_value => $option_label ) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr( $option_value ),
                selected( $value, $option_value, false ),
                esc_html( $option_label )
            );
        }

        echo '</select>';
    }

    /**
     * Render a multiselect field.
     */
    protected function render_multiselect( array $field, string $name, string $id, $value ): void {
        $value = (array) $value;

        printf(
            '<select id="%s" name="%s[]" class="%s" multiple>',
            esc_attr( $id ),
            esc_attr( $name ),
            esc_attr( $field['class'] ?? '' )
        );

        foreach ( $field['options'] as $option_value => $option_label ) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr( $option_value ),
                in_array( $option_value, $value, true ) ? 'selected' : '',
                esc_html( $option_label )
            );
        }

        echo '</select>';
    }

    /**
     * Render a checkbox field.
     */
    protected function render_checkbox( array $field, string $name, string $id, $value ): void {
        printf(
            '<label><input type="checkbox" id="%s" name="%s" value="1" %s /> %s</label>',
            esc_attr( $id ),
            esc_attr( $name ),
            checked( $value, true, false ),
            esc_html( $field['label'] ?? '' )
        );
    }

    /**
     * Render radio buttons.
     */
    protected function render_radio( array $field, string $name, string $id, $value ): void {
        echo '<fieldset>';

        foreach ( $field['options'] as $option_value => $option_label ) {
            printf(
                '<label><input type="radio" name="%s" value="%s" %s /> %s</label><br>',
                esc_attr( $name ),
                esc_attr( $option_value ),
                checked( $value, $option_value, false ),
                esc_html( $option_label )
            );
        }

        echo '</fieldset>';
    }

    /**
     * Render a checkbox group.
     */
    protected function render_checkbox_group( array $field, string $name, string $id, $value ): void {
        $value = (array) $value;
        echo '<fieldset>';

        foreach ( $field['options'] as $option_value => $option_label ) {
            printf(
                '<label><input type="checkbox" name="%s[]" value="%s" %s /> %s</label><br>',
                esc_attr( $name ),
                esc_attr( $option_value ),
                in_array( $option_value, $value, true ) ? 'checked' : '',
                esc_html( $option_label )
            );
        }

        echo '</fieldset>';
    }

    /**
     * Render a WYSIWYG editor.
     */
    protected function render_wysiwyg( array $field, string $name, string $id, $value ): void {
        wp_editor( $value, $id, [
            'textarea_name' => $name,
            'media_buttons' => $field['media_buttons'] ?? false,
            'textarea_rows' => $field['rows'] ?? 10,
            'teeny'         => $field['teeny'] ?? true,
        ] );
    }

    /**
     * Render a color picker.
     */
    protected function render_color( array $field, string $name, string $id, $value ): void {
        printf(
            '<input type="text" id="%s" name="%s" value="%s" class="bkx-color-picker" data-default-color="%s" />',
            esc_attr( $id ),
            esc_attr( $name ),
            esc_attr( $value ),
            esc_attr( $field['default'] ?? '#000000' )
        );
    }
}
