<?php
/**
 * Abstract Analytics Module Base Class
 *
 * Provides the foundation for analytics and reporting modules.
 *
 * @package    BookingX\AddonSDK
 * @subpackage Abstracts
 * @since      1.0.0
 */

namespace BookingX\AddonSDK\Abstracts;

/**
 * Abstract base class for analytics modules.
 *
 * @since 1.0.0
 */
abstract class AbstractAnalytics {

    /**
     * Module identifier.
     *
     * @var string
     */
    protected string $id;

    /**
     * Module display name.
     *
     * @var string
     */
    protected string $name;

    /**
     * Module description.
     *
     * @var string
     */
    protected string $description;

    /**
     * Module category.
     *
     * @var string
     */
    protected string $category = 'general';

    /**
     * Available metrics.
     *
     * @var array
     */
    protected array $metrics = [];

    /**
     * Available dimensions.
     *
     * @var array
     */
    protected array $dimensions = [];

    /**
     * Cache duration in seconds.
     *
     * @var int
     */
    protected int $cache_duration = 3600; // 1 hour

    /**
     * Constructor.
     */
    public function __construct() {
        $this->register_metrics();
        $this->register_dimensions();
    }

    /**
     * Get the module ID.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_id(): string {
        return $this->id;
    }

    /**
     * Get the module name.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_name(): string {
        return $this->name;
    }

    /**
     * Get the module description.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_description(): string {
        return $this->description;
    }

    /**
     * Get available metrics.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_metrics(): array {
        return $this->metrics;
    }

    /**
     * Get available dimensions.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_dimensions(): array {
        return $this->dimensions;
    }

    /**
     * Register available metrics.
     *
     * @since 1.0.0
     * @return void
     */
    abstract protected function register_metrics(): void;

    /**
     * Register available dimensions.
     *
     * @since 1.0.0
     * @return void
     */
    abstract protected function register_dimensions(): void;

    /**
     * Add a metric definition.
     *
     * @since 1.0.0
     * @param string $id         Metric ID.
     * @param string $name       Metric name.
     * @param string $type       Metric type (count, sum, average, rate).
     * @param array  $options    Additional options.
     * @return void
     */
    protected function add_metric( string $id, string $name, string $type = 'count', array $options = [] ): void {
        $this->metrics[ $id ] = wp_parse_args( $options, [
            'id'          => $id,
            'name'        => $name,
            'type'        => $type,
            'format'      => 'number', // number, currency, percentage, duration
            'description' => '',
            'aggregation' => 'sum', // sum, avg, min, max, count
        ] );
    }

    /**
     * Add a dimension definition.
     *
     * @since 1.0.0
     * @param string $id      Dimension ID.
     * @param string $name    Dimension name.
     * @param array  $options Additional options.
     * @return void
     */
    protected function add_dimension( string $id, string $name, array $options = [] ): void {
        $this->dimensions[ $id ] = wp_parse_args( $options, [
            'id'          => $id,
            'name'        => $name,
            'type'        => 'string', // string, date, number
            'description' => '',
        ] );
    }

    /**
     * Query analytics data.
     *
     * @since 1.0.0
     * @param array $params Query parameters including:
     *                      - metrics: Array of metric IDs
     *                      - dimensions: Array of dimension IDs
     *                      - date_from: Start date (Y-m-d)
     *                      - date_to: End date (Y-m-d)
     *                      - filters: Array of filters
     *                      - sort: Sort configuration
     *                      - limit: Result limit.
     * @return array Query results.
     */
    abstract public function query( array $params ): array;

    /**
     * Get aggregated metrics for a date range.
     *
     * @since 1.0.0
     * @param array  $metrics   Metric IDs to get.
     * @param string $date_from Start date.
     * @param string $date_to   End date.
     * @param array  $filters   Optional filters.
     * @return array Metric values.
     */
    public function get_metrics_data( array $metrics, string $date_from, string $date_to, array $filters = [] ): array {
        $cache_key = $this->get_cache_key( 'metrics', $metrics, $date_from, $date_to, $filters );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        $data = $this->query( [
            'metrics'   => $metrics,
            'date_from' => $date_from,
            'date_to'   => $date_to,
            'filters'   => $filters,
        ] );

        set_transient( $cache_key, $data, $this->cache_duration );

        return $data;
    }

    /**
     * Get time series data for a metric.
     *
     * @since 1.0.0
     * @param string $metric    Metric ID.
     * @param string $date_from Start date.
     * @param string $date_to   End date.
     * @param string $interval  Interval (day, week, month).
     * @param array  $filters   Optional filters.
     * @return array Time series data.
     */
    public function get_time_series( string $metric, string $date_from, string $date_to, string $interval = 'day', array $filters = [] ): array {
        $cache_key = $this->get_cache_key( 'timeseries', [ $metric ], $date_from, $date_to, $filters, $interval );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        $data = $this->query( [
            'metrics'    => [ $metric ],
            'dimensions' => [ 'date' ],
            'date_from'  => $date_from,
            'date_to'    => $date_to,
            'filters'    => $filters,
            'interval'   => $interval,
        ] );

        set_transient( $cache_key, $data, $this->cache_duration );

        return $data;
    }

    /**
     * Get breakdown by dimension.
     *
     * @since 1.0.0
     * @param string $metric    Metric ID.
     * @param string $dimension Dimension ID.
     * @param string $date_from Start date.
     * @param string $date_to   End date.
     * @param int    $limit     Result limit.
     * @param array  $filters   Optional filters.
     * @return array Breakdown data.
     */
    public function get_breakdown( string $metric, string $dimension, string $date_from, string $date_to, int $limit = 10, array $filters = [] ): array {
        $cache_key = $this->get_cache_key( 'breakdown', [ $metric ], $date_from, $date_to, $filters, $dimension );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        $data = $this->query( [
            'metrics'    => [ $metric ],
            'dimensions' => [ $dimension ],
            'date_from'  => $date_from,
            'date_to'    => $date_to,
            'filters'    => $filters,
            'limit'      => $limit,
            'sort'       => [ 'field' => $metric, 'order' => 'desc' ],
        ] );

        set_transient( $cache_key, $data, $this->cache_duration );

        return $data;
    }

    /**
     * Compare two date ranges.
     *
     * @since 1.0.0
     * @param array  $metrics          Metric IDs.
     * @param string $current_from     Current period start.
     * @param string $current_to       Current period end.
     * @param string $previous_from    Previous period start.
     * @param string $previous_to      Previous period end.
     * @param array  $filters          Optional filters.
     * @return array Comparison data with change percentages.
     */
    public function compare_periods( array $metrics, string $current_from, string $current_to, string $previous_from, string $previous_to, array $filters = [] ): array {
        $current  = $this->get_metrics_data( $metrics, $current_from, $current_to, $filters );
        $previous = $this->get_metrics_data( $metrics, $previous_from, $previous_to, $filters );

        $comparison = [];

        foreach ( $metrics as $metric_id ) {
            $current_value  = $current[ $metric_id ] ?? 0;
            $previous_value = $previous[ $metric_id ] ?? 0;

            $change = 0;
            if ( $previous_value > 0 ) {
                $change = ( ( $current_value - $previous_value ) / $previous_value ) * 100;
            } elseif ( $current_value > 0 ) {
                $change = 100;
            }

            $comparison[ $metric_id ] = [
                'current'         => $current_value,
                'previous'        => $previous_value,
                'change'          => round( $change, 2 ),
                'change_absolute' => $current_value - $previous_value,
                'trend'           => $change > 0 ? 'up' : ( $change < 0 ? 'down' : 'flat' ),
            ];
        }

        return $comparison;
    }

    /**
     * Generate a cache key.
     *
     * @since 1.0.0
     * @param string $type      Query type.
     * @param array  $metrics   Metric IDs.
     * @param string $date_from Start date.
     * @param string $date_to   End date.
     * @param array  $filters   Filters.
     * @param string $extra     Extra key component.
     * @return string Cache key.
     */
    protected function get_cache_key( string $type, array $metrics, string $date_from, string $date_to, array $filters = [], string $extra = '' ): string {
        $key_parts = [
            'bkx_analytics',
            $this->id,
            $type,
            implode( '_', $metrics ),
            $date_from,
            $date_to,
            md5( serialize( $filters ) ),
            $extra,
        ];

        return implode( '_', array_filter( $key_parts ) );
    }

    /**
     * Clear analytics cache.
     *
     * @since 1.0.0
     * @return void
     */
    public function clear_cache(): void {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_bkx_analytics_' . $this->id . '%'
            )
        );
    }

    /**
     * Format a metric value for display.
     *
     * @since 1.0.0
     * @param string $metric_id Metric ID.
     * @param mixed  $value     Raw value.
     * @return string Formatted value.
     */
    public function format_metric( string $metric_id, $value ): string {
        $metric = $this->metrics[ $metric_id ] ?? null;

        if ( ! $metric ) {
            return (string) $value;
        }

        switch ( $metric['format'] ) {
            case 'currency':
                return $this->format_currency( $value );

            case 'percentage':
                return $this->format_percentage( $value );

            case 'duration':
                return $this->format_duration( $value );

            case 'number':
            default:
                return $this->format_number( $value );
        }
    }

    /**
     * Format as currency.
     *
     * @since 1.0.0
     * @param float $value Value.
     * @return string Formatted value.
     */
    protected function format_currency( float $value ): string {
        $currency = bkx_crud_option_multisite( 'bkx_currency' ) ?: 'USD';
        return sprintf( '%s%.2f', $this->get_currency_symbol( $currency ), $value );
    }

    /**
     * Format as percentage.
     *
     * @since 1.0.0
     * @param float $value Value.
     * @return string Formatted value.
     */
    protected function format_percentage( float $value ): string {
        return sprintf( '%.1f%%', $value );
    }

    /**
     * Format as duration.
     *
     * @since 1.0.0
     * @param int $minutes Minutes.
     * @return string Formatted value.
     */
    protected function format_duration( int $minutes ): string {
        if ( $minutes < 60 ) {
            return sprintf( __( '%d min', 'bkx-addon-sdk' ), $minutes );
        }

        $hours = floor( $minutes / 60 );
        $mins  = $minutes % 60;

        if ( $mins > 0 ) {
            return sprintf( __( '%dh %dm', 'bkx-addon-sdk' ), $hours, $mins );
        }

        return sprintf( __( '%dh', 'bkx-addon-sdk' ), $hours );
    }

    /**
     * Format as number.
     *
     * @since 1.0.0
     * @param float $value Value.
     * @return string Formatted value.
     */
    protected function format_number( float $value ): string {
        if ( $value >= 1000000 ) {
            return sprintf( '%.1fM', $value / 1000000 );
        }

        if ( $value >= 1000 ) {
            return sprintf( '%.1fK', $value / 1000 );
        }

        return number_format( $value, $value == (int) $value ? 0 : 2 );
    }

    /**
     * Get currency symbol.
     *
     * @since 1.0.0
     * @param string $currency Currency code.
     * @return string Currency symbol.
     */
    protected function get_currency_symbol( string $currency ): string {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'AUD' => 'A$',
            'CAD' => 'C$',
            'CHF' => 'CHF',
            'CNY' => '¥',
            'INR' => '₹',
        ];

        return $symbols[ $currency ] ?? $currency . ' ';
    }

    /**
     * Export data to CSV.
     *
     * @since 1.0.0
     * @param array $params Query parameters.
     * @return string CSV content.
     */
    public function export_csv( array $params ): string {
        $data = $this->query( $params );

        if ( empty( $data['rows'] ) ) {
            return '';
        }

        $output = fopen( 'php://temp', 'r+' );

        // Header row
        $headers = [];
        foreach ( $params['dimensions'] ?? [] as $dim_id ) {
            $headers[] = $this->dimensions[ $dim_id ]['name'] ?? $dim_id;
        }
        foreach ( $params['metrics'] ?? [] as $metric_id ) {
            $headers[] = $this->metrics[ $metric_id ]['name'] ?? $metric_id;
        }
        fputcsv( $output, $headers );

        // Data rows
        foreach ( $data['rows'] as $row ) {
            fputcsv( $output, $row );
        }

        rewind( $output );
        $csv = stream_get_contents( $output );
        fclose( $output );

        return $csv;
    }
}
