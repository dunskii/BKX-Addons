<?php
/**
 * HTTP Client Service
 *
 * Provides a wrapper for making HTTP requests to external APIs.
 *
 * @package    BookingX\AddonSDK
 * @subpackage Services
 * @since      1.0.0
 */

namespace BookingX\AddonSDK\Services;

/**
 * HTTP client for API requests.
 *
 * @since 1.0.0
 */
class HttpClient {

    /**
     * Base URL for requests.
     *
     * @var string
     */
    protected string $base_url;

    /**
     * Default headers.
     *
     * @var array
     */
    protected array $headers = [];

    /**
     * Request timeout in seconds.
     *
     * @var int
     */
    protected int $timeout = 30;

    /**
     * Whether to verify SSL.
     *
     * @var bool
     */
    protected bool $ssl_verify = true;

    /**
     * Logger instance.
     *
     * @var LoggerService|null
     */
    protected ?LoggerService $logger = null;

    /**
     * Constructor.
     *
     * @param string $base_url Base URL for requests.
     * @param array  $headers  Default headers.
     */
    public function __construct( string $base_url = '', array $headers = [] ) {
        $this->base_url = rtrim( $base_url, '/' );
        $this->headers  = $headers;
    }

    /**
     * Set the logger.
     *
     * @since 1.0.0
     * @param LoggerService $logger Logger instance.
     * @return self
     */
    public function set_logger( LoggerService $logger ): self {
        $this->logger = $logger;
        return $this;
    }

    /**
     * Set the timeout.
     *
     * @since 1.0.0
     * @param int $timeout Timeout in seconds.
     * @return self
     */
    public function set_timeout( int $timeout ): self {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * Set SSL verification.
     *
     * @since 1.0.0
     * @param bool $verify Whether to verify SSL.
     * @return self
     */
    public function set_ssl_verify( bool $verify ): self {
        $this->ssl_verify = $verify;
        return $this;
    }

    /**
     * Set a header.
     *
     * @since 1.0.0
     * @param string $name  Header name.
     * @param string $value Header value.
     * @return self
     */
    public function set_header( string $name, string $value ): self {
        $this->headers[ $name ] = $value;
        return $this;
    }

    /**
     * Set bearer token authorization.
     *
     * @since 1.0.0
     * @param string $token Bearer token.
     * @return self
     */
    public function set_bearer_token( string $token ): self {
        $this->headers['Authorization'] = 'Bearer ' . $token;
        return $this;
    }

    /**
     * Set basic auth.
     *
     * @since 1.0.0
     * @param string $username Username.
     * @param string $password Password.
     * @return self
     */
    public function set_basic_auth( string $username, string $password ): self {
        $this->headers['Authorization'] = 'Basic ' . base64_encode( "{$username}:{$password}" );
        return $this;
    }

    /**
     * Make a GET request.
     *
     * @since 1.0.0
     * @param string $endpoint Endpoint URL.
     * @param array  $params   Query parameters.
     * @param array  $headers  Additional headers.
     * @return array|\WP_Error
     */
    public function get( string $endpoint, array $params = [], array $headers = [] ) {
        $url = $this->build_url( $endpoint, $params );
        return $this->request( 'GET', $url, [], $headers );
    }

    /**
     * Make a POST request.
     *
     * @since 1.0.0
     * @param string $endpoint Endpoint URL.
     * @param array  $data     Request body.
     * @param array  $headers  Additional headers.
     * @return array|\WP_Error
     */
    public function post( string $endpoint, array $data = [], array $headers = [] ) {
        $url = $this->build_url( $endpoint );
        return $this->request( 'POST', $url, $data, $headers );
    }

    /**
     * Make a PUT request.
     *
     * @since 1.0.0
     * @param string $endpoint Endpoint URL.
     * @param array  $data     Request body.
     * @param array  $headers  Additional headers.
     * @return array|\WP_Error
     */
    public function put( string $endpoint, array $data = [], array $headers = [] ) {
        $url = $this->build_url( $endpoint );
        return $this->request( 'PUT', $url, $data, $headers );
    }

    /**
     * Make a PATCH request.
     *
     * @since 1.0.0
     * @param string $endpoint Endpoint URL.
     * @param array  $data     Request body.
     * @param array  $headers  Additional headers.
     * @return array|\WP_Error
     */
    public function patch( string $endpoint, array $data = [], array $headers = [] ) {
        $url = $this->build_url( $endpoint );
        return $this->request( 'PATCH', $url, $data, $headers );
    }

    /**
     * Make a DELETE request.
     *
     * @since 1.0.0
     * @param string $endpoint Endpoint URL.
     * @param array  $headers  Additional headers.
     * @return array|\WP_Error
     */
    public function delete( string $endpoint, array $headers = [] ) {
        $url = $this->build_url( $endpoint );
        return $this->request( 'DELETE', $url, [], $headers );
    }

    /**
     * Make an HTTP request.
     *
     * @since 1.0.0
     * @param string $method  HTTP method.
     * @param string $url     Full URL.
     * @param array  $data    Request body.
     * @param array  $headers Additional headers.
     * @return array|\WP_Error
     */
    protected function request( string $method, string $url, array $data = [], array $headers = [] ) {
        $start_time = microtime( true );

        $args = [
            'method'    => $method,
            'timeout'   => $this->timeout,
            'sslverify' => $this->ssl_verify,
            'headers'   => array_merge( $this->headers, $headers ),
        ];

        // Add body for POST/PUT/PATCH
        if ( in_array( $method, [ 'POST', 'PUT', 'PATCH' ], true ) && ! empty( $data ) ) {
            // Check if we should send as JSON
            $content_type = $args['headers']['Content-Type'] ?? '';
            if ( strpos( $content_type, 'application/json' ) !== false ) {
                $args['body'] = wp_json_encode( $data );
            } else {
                $args['body'] = $data;
            }
        }

        // Log request
        if ( $this->logger ) {
            $this->logger->debug( "HTTP {$method} {$url}", [
                'headers' => $this->sanitize_headers_for_log( $args['headers'] ),
                'body'    => $data,
            ] );
        }

        // Make request
        $response = wp_remote_request( $url, $args );

        $duration = round( ( microtime( true ) - $start_time ) * 1000, 2 );

        // Handle error
        if ( is_wp_error( $response ) ) {
            if ( $this->logger ) {
                $this->logger->error( "HTTP {$method} {$url} failed", [
                    'error'    => $response->get_error_message(),
                    'duration' => $duration . 'ms',
                ] );
            }
            return $response;
        }

        // Parse response
        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = wp_remote_retrieve_body( $response );
        $headers     = wp_remote_retrieve_headers( $response );

        // Try to decode JSON
        $decoded = json_decode( $body, true );
        if ( json_last_error() === JSON_ERROR_NONE ) {
            $body = $decoded;
        }

        // Log response
        if ( $this->logger ) {
            $log_level = $status_code >= 400 ? 'warning' : 'debug';
            $this->logger->log( $log_level, "HTTP {$method} {$url} responded {$status_code}", [
                'status'   => $status_code,
                'duration' => $duration . 'ms',
            ] );
        }

        return [
            'status'  => $status_code,
            'headers' => $headers->getAll(),
            'body'    => $body,
            'success' => $status_code >= 200 && $status_code < 300,
        ];
    }

    /**
     * Build the full URL.
     *
     * @since 1.0.0
     * @param string $endpoint Endpoint.
     * @param array  $params   Query parameters.
     * @return string
     */
    protected function build_url( string $endpoint, array $params = [] ): string {
        // If endpoint is a full URL, use it directly
        if ( strpos( $endpoint, 'http' ) === 0 ) {
            $url = $endpoint;
        } else {
            $url = $this->base_url . '/' . ltrim( $endpoint, '/' );
        }

        if ( ! empty( $params ) ) {
            $url .= '?' . http_build_query( $params );
        }

        return $url;
    }

    /**
     * Sanitize headers for logging (hide sensitive data).
     *
     * @since 1.0.0
     * @param array $headers Headers.
     * @return array
     */
    protected function sanitize_headers_for_log( array $headers ): array {
        $sensitive = [ 'Authorization', 'X-Api-Key', 'X-Secret-Key' ];

        foreach ( $sensitive as $header ) {
            if ( isset( $headers[ $header ] ) ) {
                $headers[ $header ] = '***REDACTED***';
            }
        }

        return $headers;
    }

    /**
     * Post JSON data.
     *
     * @since 1.0.0
     * @param string $endpoint Endpoint URL.
     * @param array  $data     Request body.
     * @param array  $headers  Additional headers.
     * @return array|\WP_Error
     */
    public function post_json( string $endpoint, array $data = [], array $headers = [] ) {
        $headers['Content-Type'] = 'application/json';
        return $this->post( $endpoint, $data, $headers );
    }

    /**
     * Put JSON data.
     *
     * @since 1.0.0
     * @param string $endpoint Endpoint URL.
     * @param array  $data     Request body.
     * @param array  $headers  Additional headers.
     * @return array|\WP_Error
     */
    public function put_json( string $endpoint, array $data = [], array $headers = [] ) {
        $headers['Content-Type'] = 'application/json';
        return $this->put( $endpoint, $data, $headers );
    }

    /**
     * Upload a file.
     *
     * @since 1.0.0
     * @param string $endpoint  Endpoint URL.
     * @param string $file_path File path.
     * @param string $field     Field name.
     * @param array  $data      Additional form data.
     * @param array  $headers   Additional headers.
     * @return array|\WP_Error
     */
    public function upload( string $endpoint, string $file_path, string $field = 'file', array $data = [], array $headers = [] ) {
        if ( ! file_exists( $file_path ) ) {
            return new \WP_Error( 'file_not_found', __( 'File not found.', 'bkx-addon-sdk' ) );
        }

        $boundary = wp_generate_password( 24, false );
        $headers['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;

        $payload = '';

        // Add file
        $file_content = file_get_contents( $file_path );
        $file_name    = basename( $file_path );
        $mime_type    = mime_content_type( $file_path ) ?: 'application/octet-stream';

        $payload .= "--{$boundary}\r\n";
        $payload .= "Content-Disposition: form-data; name=\"{$field}\"; filename=\"{$file_name}\"\r\n";
        $payload .= "Content-Type: {$mime_type}\r\n\r\n";
        $payload .= $file_content . "\r\n";

        // Add additional fields
        foreach ( $data as $name => $value ) {
            $payload .= "--{$boundary}\r\n";
            $payload .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
            $payload .= $value . "\r\n";
        }

        $payload .= "--{$boundary}--\r\n";

        $url = $this->build_url( $endpoint );

        $args = [
            'method'    => 'POST',
            'timeout'   => $this->timeout * 2, // Double timeout for uploads
            'sslverify' => $this->ssl_verify,
            'headers'   => array_merge( $this->headers, $headers ),
            'body'      => $payload,
        ];

        return wp_remote_request( $url, $args );
    }
}
