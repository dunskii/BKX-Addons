<?php
/**
 * Logger Service
 *
 * Provides logging functionality for add-ons.
 *
 * @package    BookingX\AddonSDK
 * @subpackage Services
 * @since      1.0.0
 */

namespace BookingX\AddonSDK\Services;

/**
 * Logging service.
 *
 * @since 1.0.0
 */
class LoggerService {

    /**
     * Log levels.
     */
    public const DEBUG   = 'debug';
    public const INFO    = 'info';
    public const WARNING = 'warning';
    public const ERROR   = 'error';

    /**
     * The add-on ID.
     *
     * @var string
     */
    protected string $addon_id;

    /**
     * Log file path.
     *
     * @var string
     */
    protected string $log_path;

    /**
     * Whether debug mode is enabled.
     *
     * @var bool
     */
    protected bool $debug_mode;

    /**
     * Maximum log file size in bytes.
     *
     * @var int
     */
    protected int $max_file_size = 5242880; // 5MB

    /**
     * Constructor.
     *
     * @param string $addon_id The add-on ID.
     */
    public function __construct( string $addon_id ) {
        $this->addon_id   = $addon_id;
        $this->debug_mode = defined( 'WP_DEBUG' ) && WP_DEBUG;
        $this->log_path   = $this->get_log_directory() . "/{$addon_id}.log";
    }

    /**
     * Get the log directory.
     *
     * @since 1.0.0
     * @return string
     */
    protected function get_log_directory(): string {
        $upload_dir = wp_upload_dir();
        $log_dir    = $upload_dir['basedir'] . '/bkx-logs';

        if ( ! file_exists( $log_dir ) ) {
            wp_mkdir_p( $log_dir );

            // Add index.php and .htaccess for security
            file_put_contents( $log_dir . '/index.php', '<?php // Silence is golden' );
            file_put_contents( $log_dir . '/.htaccess', 'deny from all' );
        }

        return $log_dir;
    }

    /**
     * Log a debug message.
     *
     * @since 1.0.0
     * @param string $message Log message.
     * @param array  $context Additional context.
     * @return void
     */
    public function debug( string $message, array $context = [] ): void {
        if ( $this->debug_mode ) {
            $this->log( self::DEBUG, $message, $context );
        }
    }

    /**
     * Log an info message.
     *
     * @since 1.0.0
     * @param string $message Log message.
     * @param array  $context Additional context.
     * @return void
     */
    public function info( string $message, array $context = [] ): void {
        $this->log( self::INFO, $message, $context );
    }

    /**
     * Log a warning message.
     *
     * @since 1.0.0
     * @param string $message Log message.
     * @param array  $context Additional context.
     * @return void
     */
    public function warning( string $message, array $context = [] ): void {
        $this->log( self::WARNING, $message, $context );
    }

    /**
     * Log an error message.
     *
     * @since 1.0.0
     * @param string $message Log message.
     * @param array  $context Additional context.
     * @return void
     */
    public function error( string $message, array $context = [] ): void {
        $this->log( self::ERROR, $message, $context );
    }

    /**
     * Log a message.
     *
     * @since 1.0.0
     * @param string $level   Log level.
     * @param string $message Log message.
     * @param array  $context Additional context.
     * @return void
     */
    public function log( string $level, string $message, array $context = [] ): void {
        // Rotate log if needed
        $this->maybe_rotate_log();

        // Format the log entry
        $entry = $this->format_entry( $level, $message, $context );

        // Write to file
        $this->write( $entry );

        // Fire action for external logging
        do_action( 'bkx_addon_log', $this->addon_id, $level, $message, $context );
    }

    /**
     * Format a log entry.
     *
     * @since 1.0.0
     * @param string $level   Log level.
     * @param string $message Log message.
     * @param array  $context Additional context.
     * @return string
     */
    protected function format_entry( string $level, string $message, array $context = [] ): string {
        $timestamp = gmdate( 'Y-m-d H:i:s' );
        $level     = strtoupper( $level );

        $entry = "[{$timestamp}] [{$level}] {$message}";

        if ( ! empty( $context ) ) {
            $entry .= ' ' . wp_json_encode( $context );
        }

        return $entry . PHP_EOL;
    }

    /**
     * Write to log file.
     *
     * @since 1.0.0
     * @param string $entry Log entry.
     * @return void
     */
    protected function write( string $entry ): void {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        file_put_contents( $this->log_path, $entry, FILE_APPEND | LOCK_EX );
    }

    /**
     * Maybe rotate log file.
     *
     * @since 1.0.0
     * @return void
     */
    protected function maybe_rotate_log(): void {
        if ( ! file_exists( $this->log_path ) ) {
            return;
        }

        if ( filesize( $this->log_path ) < $this->max_file_size ) {
            return;
        }

        // Rotate log
        $backup_path = str_replace( '.log', '-' . gmdate( 'Y-m-d-His' ) . '.log', $this->log_path );
        rename( $this->log_path, $backup_path );

        // Clean old logs (keep last 5)
        $this->cleanup_old_logs();
    }

    /**
     * Cleanup old log files.
     *
     * @since 1.0.0
     * @param int $keep Number of old logs to keep.
     * @return void
     */
    protected function cleanup_old_logs( int $keep = 5 ): void {
        $log_dir = $this->get_log_directory();
        $pattern = $log_dir . "/{$this->addon_id}-*.log";
        $files   = glob( $pattern );

        if ( count( $files ) <= $keep ) {
            return;
        }

        // Sort by modification time (oldest first)
        usort( $files, function( $a, $b ) {
            return filemtime( $a ) - filemtime( $b );
        } );

        // Delete oldest files
        $to_delete = array_slice( $files, 0, count( $files ) - $keep );
        foreach ( $to_delete as $file ) {
            unlink( $file );
        }
    }

    /**
     * Get log contents.
     *
     * @since 1.0.0
     * @param int $lines Number of lines to get (from end).
     * @return array
     */
    public function get_logs( int $lines = 100 ): array {
        if ( ! file_exists( $this->log_path ) ) {
            return [];
        }

        $content = file_get_contents( $this->log_path );
        $all_lines = explode( PHP_EOL, trim( $content ) );

        // Get last N lines
        $log_lines = array_slice( $all_lines, -$lines );

        // Parse into structured format
        $logs = [];
        foreach ( $log_lines as $line ) {
            $parsed = $this->parse_log_line( $line );
            if ( $parsed ) {
                $logs[] = $parsed;
            }
        }

        return array_reverse( $logs ); // Newest first
    }

    /**
     * Parse a log line.
     *
     * @since 1.0.0
     * @param string $line Log line.
     * @return array|null
     */
    protected function parse_log_line( string $line ): ?array {
        $pattern = '/^\[([^\]]+)\] \[([^\]]+)\] (.+)$/';

        if ( ! preg_match( $pattern, $line, $matches ) ) {
            return null;
        }

        $message = $matches[3];
        $context = [];

        // Try to extract JSON context
        $json_pos = strpos( $message, ' {' );
        if ( $json_pos !== false ) {
            $json_str = substr( $message, $json_pos + 1 );
            $decoded  = json_decode( $json_str, true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                $context = $decoded;
                $message = substr( $message, 0, $json_pos );
            }
        }

        return [
            'timestamp' => $matches[1],
            'level'     => strtolower( $matches[2] ),
            'message'   => $message,
            'context'   => $context,
        ];
    }

    /**
     * Clear the log file.
     *
     * @since 1.0.0
     * @return bool
     */
    public function clear(): bool {
        if ( file_exists( $this->log_path ) ) {
            return unlink( $this->log_path );
        }

        return true;
    }

    /**
     * Get log file path.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_path(): string {
        return $this->log_path;
    }

    /**
     * Get log file size.
     *
     * @since 1.0.0
     * @return int
     */
    public function get_size(): int {
        if ( ! file_exists( $this->log_path ) ) {
            return 0;
        }

        return filesize( $this->log_path );
    }

    /**
     * Log an exception.
     *
     * @since 1.0.0
     * @param \Throwable $exception The exception.
     * @param array      $context   Additional context.
     * @return void
     */
    public function exception( \Throwable $exception, array $context = [] ): void {
        $context = array_merge( $context, [
            'exception' => get_class( $exception ),
            'file'      => $exception->getFile(),
            'line'      => $exception->getLine(),
            'trace'     => $exception->getTraceAsString(),
        ] );

        $this->error( $exception->getMessage(), $context );
    }
}
