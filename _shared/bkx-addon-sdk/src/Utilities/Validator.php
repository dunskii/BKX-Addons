<?php
/**
 * Validator Utility
 *
 * Provides input validation methods.
 *
 * @package    BookingX\AddonSDK
 * @subpackage Utilities
 * @since      1.0.0
 */

namespace BookingX\AddonSDK\Utilities;

/**
 * Validator utility class.
 *
 * @since 1.0.0
 */
class Validator {

    /**
     * Validation errors.
     *
     * @var array
     */
    protected array $errors = [];

    /**
     * Data being validated.
     *
     * @var array
     */
    protected array $data = [];

    /**
     * Create a new validator instance.
     *
     * @param array $data Data to validate.
     * @return self
     */
    public static function make( array $data ): self {
        $validator = new self();
        $validator->data = $data;
        return $validator;
    }

    /**
     * Validate data against rules.
     *
     * @since 1.0.0
     * @param array $rules Validation rules [ 'field' => 'rule1|rule2' ].
     * @return bool
     */
    public function validate( array $rules ): bool {
        $this->errors = [];

        foreach ( $rules as $field => $rule_string ) {
            $rules_array = explode( '|', $rule_string );
            $value = $this->data[ $field ] ?? null;

            foreach ( $rules_array as $rule ) {
                $this->apply_rule( $field, $value, $rule );
            }
        }

        return empty( $this->errors );
    }

    /**
     * Apply a validation rule.
     *
     * @since 1.0.0
     * @param string $field Field name.
     * @param mixed  $value Field value.
     * @param string $rule  Rule to apply.
     * @return void
     */
    protected function apply_rule( string $field, $value, string $rule ): void {
        // Parse rule and parameters
        $parts = explode( ':', $rule, 2 );
        $rule_name = $parts[0];
        $params = isset( $parts[1] ) ? explode( ',', $parts[1] ) : [];

        // Skip other rules if empty and not required
        if ( $rule_name !== 'required' && $this->is_empty( $value ) ) {
            return;
        }

        $method = 'rule_' . $rule_name;

        if ( method_exists( $this, $method ) ) {
            $result = $this->$method( $value, $params, $field );

            if ( true !== $result ) {
                $this->add_error( $field, $result );
            }
        }
    }

    /**
     * Check if value is empty.
     *
     * @since 1.0.0
     * @param mixed $value Value to check.
     * @return bool
     */
    protected function is_empty( $value ): bool {
        return null === $value || '' === $value || ( is_array( $value ) && empty( $value ) );
    }

    /**
     * Add a validation error.
     *
     * @since 1.0.0
     * @param string $field   Field name.
     * @param string $message Error message.
     * @return void
     */
    protected function add_error( string $field, string $message ): void {
        if ( ! isset( $this->errors[ $field ] ) ) {
            $this->errors[ $field ] = [];
        }
        $this->errors[ $field ][] = $message;
    }

    /**
     * Get validation errors.
     *
     * @since 1.0.0
     * @return array
     */
    public function errors(): array {
        return $this->errors;
    }

    /**
     * Get first error for a field.
     *
     * @since 1.0.0
     * @param string $field Field name.
     * @return string|null
     */
    public function first( string $field ): ?string {
        return $this->errors[ $field ][0] ?? null;
    }

    /**
     * Check if validation passed.
     *
     * @since 1.0.0
     * @return bool
     */
    public function passes(): bool {
        return empty( $this->errors );
    }

    /**
     * Check if validation failed.
     *
     * @since 1.0.0
     * @return bool
     */
    public function fails(): bool {
        return ! $this->passes();
    }

    // -------------------------------------------------------------------------
    // Validation Rules
    // -------------------------------------------------------------------------

    /**
     * Required rule.
     */
    protected function rule_required( $value ): string|bool {
        if ( $this->is_empty( $value ) ) {
            return __( 'This field is required.', 'bkx-addon-sdk' );
        }
        return true;
    }

    /**
     * Email rule.
     */
    protected function rule_email( $value ): string|bool {
        if ( ! is_email( $value ) ) {
            return __( 'Please enter a valid email address.', 'bkx-addon-sdk' );
        }
        return true;
    }

    /**
     * URL rule.
     */
    protected function rule_url( $value ): string|bool {
        if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
            return __( 'Please enter a valid URL.', 'bkx-addon-sdk' );
        }
        return true;
    }

    /**
     * Numeric rule.
     */
    protected function rule_numeric( $value ): string|bool {
        if ( ! is_numeric( $value ) ) {
            return __( 'This field must be a number.', 'bkx-addon-sdk' );
        }
        return true;
    }

    /**
     * Integer rule.
     */
    protected function rule_integer( $value ): string|bool {
        if ( ! filter_var( $value, FILTER_VALIDATE_INT ) ) {
            return __( 'This field must be an integer.', 'bkx-addon-sdk' );
        }
        return true;
    }

    /**
     * Min length rule.
     */
    protected function rule_min( $value, array $params ): string|bool {
        $min = (int) ( $params[0] ?? 0 );

        if ( is_string( $value ) && strlen( $value ) < $min ) {
            return sprintf( __( 'This field must be at least %d characters.', 'bkx-addon-sdk' ), $min );
        }

        if ( is_numeric( $value ) && $value < $min ) {
            return sprintf( __( 'This field must be at least %d.', 'bkx-addon-sdk' ), $min );
        }

        return true;
    }

    /**
     * Max length rule.
     */
    protected function rule_max( $value, array $params ): string|bool {
        $max = (int) ( $params[0] ?? 0 );

        if ( is_string( $value ) && strlen( $value ) > $max ) {
            return sprintf( __( 'This field must be no more than %d characters.', 'bkx-addon-sdk' ), $max );
        }

        if ( is_numeric( $value ) && $value > $max ) {
            return sprintf( __( 'This field must be no more than %d.', 'bkx-addon-sdk' ), $max );
        }

        return true;
    }

    /**
     * Between rule.
     */
    protected function rule_between( $value, array $params ): string|bool {
        $min = (int) ( $params[0] ?? 0 );
        $max = (int) ( $params[1] ?? 0 );

        if ( is_string( $value ) ) {
            $len = strlen( $value );
            if ( $len < $min || $len > $max ) {
                return sprintf( __( 'This field must be between %d and %d characters.', 'bkx-addon-sdk' ), $min, $max );
            }
        }

        if ( is_numeric( $value ) && ( $value < $min || $value > $max ) ) {
            return sprintf( __( 'This field must be between %d and %d.', 'bkx-addon-sdk' ), $min, $max );
        }

        return true;
    }

    /**
     * In list rule.
     */
    protected function rule_in( $value, array $params ): string|bool {
        if ( ! in_array( $value, $params, true ) ) {
            return __( 'The selected value is invalid.', 'bkx-addon-sdk' );
        }
        return true;
    }

    /**
     * Not in list rule.
     */
    protected function rule_not_in( $value, array $params ): string|bool {
        if ( in_array( $value, $params, true ) ) {
            return __( 'The selected value is invalid.', 'bkx-addon-sdk' );
        }
        return true;
    }

    /**
     * Date rule.
     */
    protected function rule_date( $value ): string|bool {
        if ( ! strtotime( $value ) ) {
            return __( 'Please enter a valid date.', 'bkx-addon-sdk' );
        }
        return true;
    }

    /**
     * Date format rule.
     */
    protected function rule_date_format( $value, array $params ): string|bool {
        $format = $params[0] ?? 'Y-m-d';
        $date = \DateTime::createFromFormat( $format, $value );

        if ( ! $date || $date->format( $format ) !== $value ) {
            return sprintf( __( 'Please enter a date in the format: %s', 'bkx-addon-sdk' ), $format );
        }

        return true;
    }

    /**
     * After date rule.
     */
    protected function rule_after( $value, array $params, string $field ): string|bool {
        $compare = $params[0] ?? 'today';

        if ( 'today' === $compare ) {
            $compare_date = strtotime( 'today' );
        } elseif ( isset( $this->data[ $compare ] ) ) {
            $compare_date = strtotime( $this->data[ $compare ] );
        } else {
            $compare_date = strtotime( $compare );
        }

        if ( strtotime( $value ) <= $compare_date ) {
            return __( 'This date must be after the comparison date.', 'bkx-addon-sdk' );
        }

        return true;
    }

    /**
     * Before date rule.
     */
    protected function rule_before( $value, array $params, string $field ): string|bool {
        $compare = $params[0] ?? 'today';

        if ( 'today' === $compare ) {
            $compare_date = strtotime( 'today' );
        } elseif ( isset( $this->data[ $compare ] ) ) {
            $compare_date = strtotime( $this->data[ $compare ] );
        } else {
            $compare_date = strtotime( $compare );
        }

        if ( strtotime( $value ) >= $compare_date ) {
            return __( 'This date must be before the comparison date.', 'bkx-addon-sdk' );
        }

        return true;
    }

    /**
     * Regex rule.
     */
    protected function rule_regex( $value, array $params ): string|bool {
        $pattern = $params[0] ?? '';

        if ( ! preg_match( $pattern, $value ) ) {
            return __( 'The format is invalid.', 'bkx-addon-sdk' );
        }

        return true;
    }

    /**
     * Confirmed rule (field must match field_confirmation).
     */
    protected function rule_confirmed( $value, array $params, string $field ): string|bool {
        $confirmation = $this->data[ $field . '_confirmation' ] ?? null;

        if ( $value !== $confirmation ) {
            return __( 'The confirmation does not match.', 'bkx-addon-sdk' );
        }

        return true;
    }

    /**
     * Alpha rule.
     */
    protected function rule_alpha( $value ): string|bool {
        if ( ! preg_match( '/^[a-zA-Z]+$/', $value ) ) {
            return __( 'This field may only contain letters.', 'bkx-addon-sdk' );
        }
        return true;
    }

    /**
     * Alpha-numeric rule.
     */
    protected function rule_alpha_num( $value ): string|bool {
        if ( ! preg_match( '/^[a-zA-Z0-9]+$/', $value ) ) {
            return __( 'This field may only contain letters and numbers.', 'bkx-addon-sdk' );
        }
        return true;
    }

    /**
     * Alpha-numeric with dashes and underscores rule.
     */
    protected function rule_alpha_dash( $value ): string|bool {
        if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $value ) ) {
            return __( 'This field may only contain letters, numbers, dashes, and underscores.', 'bkx-addon-sdk' );
        }
        return true;
    }

    /**
     * Boolean rule.
     */
    protected function rule_boolean( $value ): string|bool {
        $acceptable = [ true, false, 0, 1, '0', '1', 'true', 'false' ];

        if ( ! in_array( $value, $acceptable, true ) ) {
            return __( 'This field must be true or false.', 'bkx-addon-sdk' );
        }

        return true;
    }

    /**
     * Array rule.
     */
    protected function rule_array( $value ): string|bool {
        if ( ! is_array( $value ) ) {
            return __( 'This field must be an array.', 'bkx-addon-sdk' );
        }
        return true;
    }

    /**
     * JSON rule.
     */
    protected function rule_json( $value ): string|bool {
        if ( ! is_string( $value ) ) {
            return __( 'This field must be valid JSON.', 'bkx-addon-sdk' );
        }

        json_decode( $value );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return __( 'This field must be valid JSON.', 'bkx-addon-sdk' );
        }

        return true;
    }
}
