<?php
/**
 * Meta Box Builder
 *
 * Builds custom meta boxes for add-ons.
 *
 * @package    BookingX\AddonSDK
 * @subpackage Admin
 * @since      1.0.0
 */

namespace BookingX\AddonSDK\Admin;

/**
 * Meta box builder class.
 *
 * @since 1.0.0
 */
class MetaBox {

    /**
     * Meta box ID.
     *
     * @var string
     */
    protected string $id;

    /**
     * Meta box title.
     *
     * @var string
     */
    protected string $title;

    /**
     * Post types to display on.
     *
     * @var array
     */
    protected array $post_types = [];

    /**
     * Context (normal, side, advanced).
     *
     * @var string
     */
    protected string $context = 'normal';

    /**
     * Priority (high, core, default, low).
     *
     * @var string
     */
    protected string $priority = 'default';

    /**
     * Meta fields.
     *
     * @var array
     */
    protected array $fields = [];

    /**
     * Nonce action.
     *
     * @var string
     */
    protected string $nonce_action;

    /**
     * Nonce field name.
     *
     * @var string
     */
    protected string $nonce_name;

    /**
     * Constructor.
     *
     * @param string       $id         Meta box ID.
     * @param string       $title      Meta box title.
     * @param string|array $post_types Post types.
     */
    public function __construct( string $id, string $title, $post_types = 'post' ) {
        $this->id          = $id;
        $this->title       = $title;
        $this->post_types  = (array) $post_types;
        $this->nonce_action = $id . '_nonce_action';
        $this->nonce_name  = $id . '_nonce';
    }

    /**
     * Set context.
     *
     * @since 1.0.0
     * @param string $context Context.
     * @return self
     */
    public function set_context( string $context ): self {
        $this->context = $context;
        return $this;
    }

    /**
     * Set priority.
     *
     * @since 1.0.0
     * @param string $priority Priority.
     * @return self
     */
    public function set_priority( string $priority ): self {
        $this->priority = $priority;
        return $this;
    }

    /**
     * Add a field.
     *
     * @since 1.0.0
     * @param string $id    Field ID (meta key).
     * @param string $label Field label.
     * @param string $type  Field type.
     * @param array  $args  Additional arguments.
     * @return self
     */
    public function add_field( string $id, string $label, string $type = 'text', array $args = [] ): self {
        $this->fields[] = array_merge( [
            'id'          => $id,
            'label'       => $label,
            'type'        => $type,
            'description' => '',
            'default'     => '',
            'options'     => [],
            'placeholder' => '',
            'class'       => '',
            'required'    => false,
            'sanitize'    => null,
        ], $args );

        return $this;
    }

    /**
     * Register the meta box.
     *
     * @since 1.0.0
     * @return void
     */
    public function register(): void {
        add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
        add_action( 'save_post', [ $this, 'save_meta_box' ], 10, 2 );
    }

    /**
     * Add the meta box.
     *
     * @since 1.0.0
     * @return void
     */
    public function add_meta_box(): void {
        foreach ( $this->post_types as $post_type ) {
            add_meta_box(
                $this->id,
                $this->title,
                [ $this, 'render' ],
                $post_type,
                $this->context,
                $this->priority
            );
        }
    }

    /**
     * Render the meta box.
     *
     * @since 1.0.0
     * @param \WP_Post $post Current post.
     * @return void
     */
    public function render( \WP_Post $post ): void {
        wp_nonce_field( $this->nonce_action, $this->nonce_name );

        echo '<div class="bkx-meta-box-fields">';

        foreach ( $this->fields as $field ) {
            $this->render_field( $field, $post->ID );
        }

        echo '</div>';
    }

    /**
     * Render a field.
     *
     * @since 1.0.0
     * @param array $field   Field config.
     * @param int   $post_id Post ID.
     * @return void
     */
    protected function render_field( array $field, int $post_id ): void {
        $value = get_post_meta( $post_id, $field['id'], true );

        if ( '' === $value && '' !== $field['default'] ) {
            $value = $field['default'];
        }

        echo '<div class="bkx-meta-field bkx-meta-field-' . esc_attr( $field['type'] ) . '">';
        echo '<label for="' . esc_attr( $field['id'] ) . '"><strong>' . esc_html( $field['label'] ) . '</strong></label>';

        switch ( $field['type'] ) {
            case 'text':
            case 'email':
            case 'url':
            case 'number':
                $this->render_input( $field, $value );
                break;

            case 'textarea':
                $this->render_textarea( $field, $value );
                break;

            case 'select':
                $this->render_select( $field, $value );
                break;

            case 'checkbox':
                $this->render_checkbox( $field, $value );
                break;

            case 'radio':
                $this->render_radio( $field, $value );
                break;

            case 'wysiwyg':
                $this->render_wysiwyg( $field, $value );
                break;

            case 'date':
                $this->render_date( $field, $value );
                break;

            case 'color':
                $this->render_color( $field, $value );
                break;

            default:
                $this->render_input( $field, $value );
        }

        if ( ! empty( $field['description'] ) ) {
            echo '<p class="description">' . esc_html( $field['description'] ) . '</p>';
        }

        echo '</div>';
    }

    /**
     * Render input field.
     */
    protected function render_input( array $field, $value ): void {
        printf(
            '<input type="%s" id="%s" name="%s" value="%s" class="widefat %s" placeholder="%s" %s />',
            esc_attr( $field['type'] ),
            esc_attr( $field['id'] ),
            esc_attr( $field['id'] ),
            esc_attr( $value ),
            esc_attr( $field['class'] ),
            esc_attr( $field['placeholder'] ),
            $field['required'] ? 'required' : ''
        );
    }

    /**
     * Render textarea field.
     */
    protected function render_textarea( array $field, $value ): void {
        printf(
            '<textarea id="%s" name="%s" class="widefat %s" rows="%d" placeholder="%s">%s</textarea>',
            esc_attr( $field['id'] ),
            esc_attr( $field['id'] ),
            esc_attr( $field['class'] ),
            absint( $field['rows'] ?? 5 ),
            esc_attr( $field['placeholder'] ),
            esc_textarea( $value )
        );
    }

    /**
     * Render select field.
     */
    protected function render_select( array $field, $value ): void {
        printf(
            '<select id="%s" name="%s" class="widefat %s">',
            esc_attr( $field['id'] ),
            esc_attr( $field['id'] ),
            esc_attr( $field['class'] )
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
     * Render checkbox field.
     */
    protected function render_checkbox( array $field, $value ): void {
        printf(
            '<input type="checkbox" id="%s" name="%s" value="1" %s />',
            esc_attr( $field['id'] ),
            esc_attr( $field['id'] ),
            checked( $value, '1', false )
        );

        if ( ! empty( $field['checkbox_label'] ) ) {
            echo ' <span>' . esc_html( $field['checkbox_label'] ) . '</span>';
        }
    }

    /**
     * Render radio field.
     */
    protected function render_radio( array $field, $value ): void {
        foreach ( $field['options'] as $option_value => $option_label ) {
            printf(
                '<label><input type="radio" name="%s" value="%s" %s /> %s</label><br>',
                esc_attr( $field['id'] ),
                esc_attr( $option_value ),
                checked( $value, $option_value, false ),
                esc_html( $option_label )
            );
        }
    }

    /**
     * Render WYSIWYG field.
     */
    protected function render_wysiwyg( array $field, $value ): void {
        wp_editor( $value, $field['id'], [
            'textarea_name' => $field['id'],
            'media_buttons' => $field['media_buttons'] ?? false,
            'textarea_rows' => $field['rows'] ?? 10,
            'teeny'         => $field['teeny'] ?? true,
        ] );
    }

    /**
     * Render date field.
     */
    protected function render_date( array $field, $value ): void {
        printf(
            '<input type="date" id="%s" name="%s" value="%s" class="widefat %s" />',
            esc_attr( $field['id'] ),
            esc_attr( $field['id'] ),
            esc_attr( $value ),
            esc_attr( $field['class'] )
        );
    }

    /**
     * Render color field.
     */
    protected function render_color( array $field, $value ): void {
        printf(
            '<input type="text" id="%s" name="%s" value="%s" class="bkx-color-picker %s" data-default-color="%s" />',
            esc_attr( $field['id'] ),
            esc_attr( $field['id'] ),
            esc_attr( $value ),
            esc_attr( $field['class'] ),
            esc_attr( $field['default'] )
        );
    }

    /**
     * Save meta box data.
     *
     * @since 1.0.0
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     * @return void
     */
    public function save_meta_box( int $post_id, \WP_Post $post ): void {
        // Verify nonce
        if ( ! isset( $_POST[ $this->nonce_name ] ) ||
             ! wp_verify_nonce( $_POST[ $this->nonce_name ], $this->nonce_action ) ) {
            return;
        }

        // Check autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Check post type
        if ( ! in_array( $post->post_type, $this->post_types, true ) ) {
            return;
        }

        // Check permissions
        $post_type_obj = get_post_type_object( $post->post_type );
        if ( ! current_user_can( $post_type_obj->cap->edit_post, $post_id ) ) {
            return;
        }

        // Save fields
        foreach ( $this->fields as $field ) {
            $value = $_POST[ $field['id'] ] ?? '';

            // Apply custom sanitization
            if ( is_callable( $field['sanitize'] ) ) {
                $value = call_user_func( $field['sanitize'], $value );
            } else {
                $value = $this->sanitize_value( $value, $field['type'] );
            }

            // Update or delete
            if ( '' !== $value ) {
                update_post_meta( $post_id, $field['id'], $value );
            } else {
                delete_post_meta( $post_id, $field['id'] );
            }
        }
    }

    /**
     * Sanitize a value based on type.
     *
     * @since 1.0.0
     * @param mixed  $value Field value.
     * @param string $type  Field type.
     * @return mixed
     */
    protected function sanitize_value( $value, string $type ) {
        switch ( $type ) {
            case 'email':
                return sanitize_email( $value );

            case 'url':
                return esc_url_raw( $value );

            case 'number':
                return floatval( $value );

            case 'checkbox':
                return $value ? '1' : '';

            case 'textarea':
                return sanitize_textarea_field( $value );

            case 'wysiwyg':
                return wp_kses_post( $value );

            case 'color':
                return sanitize_hex_color( $value );

            case 'date':
                return sanitize_text_field( $value );

            case 'text':
            default:
                return sanitize_text_field( $value );
        }
    }
}
