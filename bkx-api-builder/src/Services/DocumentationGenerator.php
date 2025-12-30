<?php
/**
 * Documentation Generator service.
 *
 * @package BookingX\APIBuilder\Services
 */

namespace BookingX\APIBuilder\Services;

defined( 'ABSPATH' ) || exit;

/**
 * DocumentationGenerator class.
 */
class DocumentationGenerator {

	/**
	 * Endpoint manager.
	 *
	 * @var EndpointManager
	 */
	private $endpoint_manager;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->endpoint_manager = new EndpointManager();
	}

	/**
	 * Generate API documentation.
	 *
	 * @return array OpenAPI-style documentation.
	 */
	public function generate() {
		$settings  = get_option( 'bkx_api_builder_settings', array() );
		$namespace = $settings['api_namespace'] ?? 'bkx-custom/v1';
		$endpoints = $this->endpoint_manager->get_active_endpoints();

		$doc = array(
			'openapi' => '3.0.3',
			'info'    => array(
				'title'       => get_bloginfo( 'name' ) . ' - BookingX API',
				'description' => __( 'Custom REST API endpoints for BookingX booking system', 'bkx-api-builder' ),
				'version'     => '1.0.0',
				'contact'     => array(
					'name'  => get_bloginfo( 'admin_email' ),
					'email' => get_bloginfo( 'admin_email' ),
				),
			),
			'servers' => array(
				array(
					'url'         => rest_url( $namespace ),
					'description' => __( 'Production server', 'bkx-api-builder' ),
				),
			),
			'paths'   => array(),
			'components' => array(
				'securitySchemes' => $this->get_security_schemes(),
				'schemas'         => $this->get_common_schemas(),
			),
		);

		foreach ( $endpoints as $endpoint ) {
			$path   = $endpoint['route'];
			$method = strtolower( $endpoint['method'] );

			if ( ! isset( $doc['paths'][ $path ] ) ) {
				$doc['paths'][ $path ] = array();
			}

			$doc['paths'][ $path ][ $method ] = $this->build_operation( $endpoint );
		}

		return $doc;
	}

	/**
	 * Build operation object for endpoint.
	 *
	 * @param array $endpoint Endpoint data.
	 * @return array
	 */
	private function build_operation( $endpoint ) {
		$operation = array(
			'operationId' => $endpoint['slug'],
			'summary'     => $endpoint['name'],
			'description' => $endpoint['description'],
			'tags'        => array( $this->get_endpoint_tag( $endpoint ) ),
			'responses'   => $this->build_responses( $endpoint ),
		);

		// Add security.
		$security = $this->get_endpoint_security( $endpoint );
		if ( ! empty( $security ) ) {
			$operation['security'] = $security;
		}

		// Add parameters.
		$parameters = $this->build_parameters( $endpoint );
		if ( ! empty( $parameters ) ) {
			$operation['parameters'] = $parameters;
		}

		// Add request body for POST/PUT/PATCH.
		if ( in_array( strtoupper( $endpoint['method'] ), array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$request_body = $this->build_request_body( $endpoint );
			if ( ! empty( $request_body ) ) {
				$operation['requestBody'] = $request_body;
			}
		}

		return $operation;
	}

	/**
	 * Build parameters from request schema.
	 *
	 * @param array $endpoint Endpoint data.
	 * @return array
	 */
	private function build_parameters( $endpoint ) {
		$parameters = array();
		$schema     = json_decode( $endpoint['request_schema'], true ) ?: array();

		// Extract path parameters.
		preg_match_all( '/\{([^}]+)\}/', $endpoint['route'], $matches );
		$path_params = $matches[1] ?? array();

		foreach ( $path_params as $param ) {
			$param_schema = $schema[ $param ] ?? array();
			$parameters[] = array(
				'name'        => $param,
				'in'          => 'path',
				'required'    => true,
				'description' => $param_schema['description'] ?? '',
				'schema'      => array(
					'type' => $param_schema['type'] ?? 'string',
				),
			);
		}

		// Query parameters for GET requests.
		if ( 'GET' === strtoupper( $endpoint['method'] ) ) {
			foreach ( $schema as $name => $config ) {
				if ( in_array( $name, $path_params, true ) ) {
					continue;
				}

				$parameters[] = array(
					'name'        => $name,
					'in'          => 'query',
					'required'    => $config['required'] ?? false,
					'description' => $config['description'] ?? '',
					'schema'      => $this->build_schema( $config ),
				);
			}
		}

		return $parameters;
	}

	/**
	 * Build request body.
	 *
	 * @param array $endpoint Endpoint data.
	 * @return array
	 */
	private function build_request_body( $endpoint ) {
		$schema = json_decode( $endpoint['request_schema'], true ) ?: array();

		if ( empty( $schema ) ) {
			return array();
		}

		$properties = array();
		$required   = array();

		foreach ( $schema as $name => $config ) {
			$properties[ $name ] = $this->build_schema( $config );

			if ( ! empty( $config['required'] ) ) {
				$required[] = $name;
			}
		}

		return array(
			'required'    => true,
			'description' => __( 'Request body', 'bkx-api-builder' ),
			'content'     => array(
				'application/json' => array(
					'schema' => array(
						'type'       => 'object',
						'properties' => $properties,
						'required'   => $required,
					),
				),
			),
		);
	}

	/**
	 * Build responses.
	 *
	 * @param array $endpoint Endpoint data.
	 * @return array
	 */
	private function build_responses( $endpoint ) {
		$responses = array(
			'200' => array(
				'description' => __( 'Successful response', 'bkx-api-builder' ),
			),
			'400' => array(
				'description' => __( 'Bad request', 'bkx-api-builder' ),
				'content'     => array(
					'application/json' => array(
						'schema' => array( '$ref' => '#/components/schemas/Error' ),
					),
				),
			),
			'401' => array(
				'description' => __( 'Unauthorized', 'bkx-api-builder' ),
				'content'     => array(
					'application/json' => array(
						'schema' => array( '$ref' => '#/components/schemas/Error' ),
					),
				),
			),
			'429' => array(
				'description' => __( 'Rate limit exceeded', 'bkx-api-builder' ),
				'content'     => array(
					'application/json' => array(
						'schema' => array( '$ref' => '#/components/schemas/Error' ),
					),
				),
			),
			'500' => array(
				'description' => __( 'Internal server error', 'bkx-api-builder' ),
				'content'     => array(
					'application/json' => array(
						'schema' => array( '$ref' => '#/components/schemas/Error' ),
					),
				),
			),
		);

		// Add response schema if defined.
		$response_schema = json_decode( $endpoint['response_schema'], true );
		if ( ! empty( $response_schema ) ) {
			$responses['200']['content'] = array(
				'application/json' => array(
					'schema' => $response_schema,
				),
			);
		}

		return $responses;
	}

	/**
	 * Build schema from config.
	 *
	 * @param array $config Parameter config.
	 * @return array
	 */
	private function build_schema( $config ) {
		$schema = array(
			'type' => $config['type'] ?? 'string',
		);

		if ( isset( $config['description'] ) ) {
			$schema['description'] = $config['description'];
		}

		if ( isset( $config['default'] ) ) {
			$schema['default'] = $config['default'];
		}

		if ( isset( $config['min'] ) ) {
			$schema['minimum'] = $config['min'];
		}

		if ( isset( $config['max'] ) ) {
			$schema['maximum'] = $config['max'];
		}

		if ( isset( $config['values'] ) ) {
			$schema['enum'] = $config['values'];
		}

		if ( isset( $config['format'] ) ) {
			$schema['format'] = $config['format'];
		}

		return $schema;
	}

	/**
	 * Get security schemes.
	 *
	 * @return array
	 */
	private function get_security_schemes() {
		return array(
			'ApiKeyAuth' => array(
				'type' => 'apiKey',
				'in'   => 'header',
				'name' => 'X-API-Key',
			),
			'BearerAuth' => array(
				'type'   => 'http',
				'scheme' => 'bearer',
				'bearerFormat' => 'JWT',
			),
			'OAuth2'     => array(
				'type'  => 'oauth2',
				'flows' => array(
					'authorizationCode' => array(
						'authorizationUrl' => home_url( '/oauth/authorize' ),
						'tokenUrl'         => home_url( '/oauth/token' ),
						'scopes'           => array(
							'read'  => __( 'Read access', 'bkx-api-builder' ),
							'write' => __( 'Write access', 'bkx-api-builder' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Get common schemas.
	 *
	 * @return array
	 */
	private function get_common_schemas() {
		return array(
			'Error'   => array(
				'type'       => 'object',
				'properties' => array(
					'code'    => array(
						'type'        => 'string',
						'description' => __( 'Error code', 'bkx-api-builder' ),
					),
					'message' => array(
						'type'        => 'string',
						'description' => __( 'Error message', 'bkx-api-builder' ),
					),
					'data'    => array(
						'type'        => 'object',
						'description' => __( 'Additional error data', 'bkx-api-builder' ),
					),
				),
			),
			'Booking' => array(
				'type'       => 'object',
				'properties' => array(
					'id'           => array( 'type' => 'integer' ),
					'title'        => array( 'type' => 'string' ),
					'status'       => array( 'type' => 'string' ),
					'service_id'   => array( 'type' => 'integer' ),
					'staff_id'     => array( 'type' => 'integer' ),
					'booking_date' => array( 'type' => 'string', 'format' => 'date' ),
					'booking_time' => array( 'type' => 'string' ),
					'customer'     => array( '$ref' => '#/components/schemas/Customer' ),
					'created_at'   => array( 'type' => 'string', 'format' => 'date-time' ),
				),
			),
			'Customer' => array(
				'type'       => 'object',
				'properties' => array(
					'name'  => array( 'type' => 'string' ),
					'email' => array( 'type' => 'string', 'format' => 'email' ),
					'phone' => array( 'type' => 'string' ),
				),
			),
			'Service' => array(
				'type'       => 'object',
				'properties' => array(
					'id'          => array( 'type' => 'integer' ),
					'name'        => array( 'type' => 'string' ),
					'description' => array( 'type' => 'string' ),
					'duration'    => array( 'type' => 'integer' ),
					'price'       => array( 'type' => 'number' ),
				),
			),
			'Staff'   => array(
				'type'       => 'object',
				'properties' => array(
					'id'          => array( 'type' => 'integer' ),
					'name'        => array( 'type' => 'string' ),
					'description' => array( 'type' => 'string' ),
					'image'       => array( 'type' => 'string', 'format' => 'uri' ),
				),
			),
			'PaginatedResponse' => array(
				'type'       => 'object',
				'properties' => array(
					'items'        => array( 'type' => 'array' ),
					'total'        => array( 'type' => 'integer' ),
					'pages'        => array( 'type' => 'integer' ),
					'current_page' => array( 'type' => 'integer' ),
				),
			),
		);
	}

	/**
	 * Get endpoint security.
	 *
	 * @param array $endpoint Endpoint data.
	 * @return array
	 */
	private function get_endpoint_security( $endpoint ) {
		switch ( $endpoint['authentication'] ) {
			case 'api_key':
				return array( array( 'ApiKeyAuth' => array() ) );

			case 'jwt':
				return array( array( 'BearerAuth' => array() ) );

			case 'oauth':
				return array( array( 'OAuth2' => array( 'read', 'write' ) ) );

			case 'wordpress':
			case 'capability':
				return array( array( 'BearerAuth' => array() ) );

			default:
				return array();
		}
	}

	/**
	 * Get endpoint tag.
	 *
	 * @param array $endpoint Endpoint data.
	 * @return string
	 */
	private function get_endpoint_tag( $endpoint ) {
		$route = $endpoint['route'];

		// Extract first path segment as tag.
		$parts = explode( '/', trim( $route, '/' ) );

		return ucfirst( $parts[0] ?? 'General' );
	}

	/**
	 * Export documentation in various formats.
	 *
	 * @param string $format Export format (openapi, markdown, html).
	 * @return string|\WP_Error
	 */
	public function export( $format = 'openapi' ) {
		$doc = $this->generate();

		switch ( $format ) {
			case 'openapi':
			case 'json':
				return wp_json_encode( $doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

			case 'yaml':
				return $this->to_yaml( $doc );

			case 'markdown':
				return $this->to_markdown( $doc );

			case 'html':
				return $this->to_html( $doc );

			default:
				return new \WP_Error( 'invalid_format', __( 'Invalid export format', 'bkx-api-builder' ) );
		}
	}

	/**
	 * Convert to YAML format.
	 *
	 * @param array $doc Documentation array.
	 * @return string
	 */
	private function to_yaml( $doc ) {
		// Simple YAML conversion.
		return $this->array_to_yaml( $doc );
	}

	/**
	 * Convert array to YAML string.
	 *
	 * @param mixed $data   Data to convert.
	 * @param int   $indent Indentation level.
	 * @return string
	 */
	private function array_to_yaml( $data, $indent = 0 ) {
		$yaml   = '';
		$prefix = str_repeat( '  ', $indent );

		if ( is_array( $data ) ) {
			$is_indexed = array_keys( $data ) === range( 0, count( $data ) - 1 );

			foreach ( $data as $key => $value ) {
				if ( $is_indexed ) {
					$yaml .= $prefix . '- ';
					if ( is_array( $value ) ) {
						$yaml .= "\n" . $this->array_to_yaml( $value, $indent + 1 );
					} else {
						$yaml .= $this->yaml_value( $value ) . "\n";
					}
				} else {
					$yaml .= $prefix . $key . ':';
					if ( is_array( $value ) ) {
						$yaml .= "\n" . $this->array_to_yaml( $value, $indent + 1 );
					} else {
						$yaml .= ' ' . $this->yaml_value( $value ) . "\n";
					}
				}
			}
		}

		return $yaml;
	}

	/**
	 * Format value for YAML.
	 *
	 * @param mixed $value Value to format.
	 * @return string
	 */
	private function yaml_value( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		if ( is_null( $value ) ) {
			return 'null';
		}

		if ( is_numeric( $value ) ) {
			return (string) $value;
		}

		// Quote strings with special characters.
		if ( preg_match( '/[:#\[\]{}|>]/', $value ) || empty( $value ) ) {
			return '"' . addslashes( $value ) . '"';
		}

		return $value;
	}

	/**
	 * Convert to Markdown format.
	 *
	 * @param array $doc Documentation array.
	 * @return string
	 */
	private function to_markdown( $doc ) {
		$md = "# " . $doc['info']['title'] . "\n\n";
		$md .= $doc['info']['description'] . "\n\n";
		$md .= "**Version:** " . $doc['info']['version'] . "\n\n";
		$md .= "**Base URL:** `" . $doc['servers'][0]['url'] . "`\n\n";

		$md .= "## Authentication\n\n";
		foreach ( $doc['components']['securitySchemes'] as $name => $scheme ) {
			$md .= "### " . $name . "\n";
			$md .= "- Type: " . $scheme['type'] . "\n";
			if ( isset( $scheme['in'] ) ) {
				$md .= "- In: " . $scheme['in'] . "\n";
			}
			if ( isset( $scheme['name'] ) ) {
				$md .= "- Header: `" . $scheme['name'] . "`\n";
			}
			$md .= "\n";
		}

		$md .= "## Endpoints\n\n";

		foreach ( $doc['paths'] as $path => $methods ) {
			foreach ( $methods as $method => $operation ) {
				$md .= "### " . strtoupper( $method ) . " " . $path . "\n\n";
				$md .= "**" . $operation['summary'] . "**\n\n";

				if ( ! empty( $operation['description'] ) ) {
					$md .= $operation['description'] . "\n\n";
				}

				if ( ! empty( $operation['parameters'] ) ) {
					$md .= "#### Parameters\n\n";
					$md .= "| Name | In | Type | Required | Description |\n";
					$md .= "|------|----|----|----------|-------------|\n";

					foreach ( $operation['parameters'] as $param ) {
						$required = ! empty( $param['required'] ) ? 'Yes' : 'No';
						$type     = $param['schema']['type'] ?? 'string';
						$desc     = $param['description'] ?? '';

						$md .= "| `" . $param['name'] . "` | " . $param['in'] . " | " . $type . " | " . $required . " | " . $desc . " |\n";
					}

					$md .= "\n";
				}

				$md .= "---\n\n";
			}
		}

		return $md;
	}

	/**
	 * Convert to HTML format.
	 *
	 * @param array $doc Documentation array.
	 * @return string
	 */
	private function to_html( $doc ) {
		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<title><?php echo esc_html( $doc['info']['title'] ); ?></title>
			<style>
				body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; max-width: 1200px; margin: 0 auto; padding: 20px; }
				h1 { color: #1e3a5f; }
				h2 { color: #2d5986; border-bottom: 1px solid #eee; padding-bottom: 10px; }
				h3 { color: #333; }
				code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
				pre { background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto; }
				table { width: 100%; border-collapse: collapse; margin: 15px 0; }
				th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
				th { background: #f9f9f9; }
				.method { display: inline-block; padding: 3px 10px; border-radius: 3px; color: #fff; font-weight: bold; margin-right: 10px; }
				.get { background: #61affe; }
				.post { background: #49cc90; }
				.put { background: #fca130; }
				.delete { background: #f93e3e; }
				.endpoint { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
			</style>
		</head>
		<body>
			<h1><?php echo esc_html( $doc['info']['title'] ); ?></h1>
			<p><?php echo esc_html( $doc['info']['description'] ); ?></p>
			<p><strong>Base URL:</strong> <code><?php echo esc_html( $doc['servers'][0]['url'] ); ?></code></p>

			<h2>Endpoints</h2>
			<?php foreach ( $doc['paths'] as $path => $methods ) : ?>
				<?php foreach ( $methods as $method => $operation ) : ?>
					<div class="endpoint">
						<h3>
							<span class="method <?php echo esc_attr( $method ); ?>"><?php echo esc_html( strtoupper( $method ) ); ?></span>
							<code><?php echo esc_html( $path ); ?></code>
						</h3>
						<p><strong><?php echo esc_html( $operation['summary'] ); ?></strong></p>
						<?php if ( ! empty( $operation['description'] ) ) : ?>
							<p><?php echo esc_html( $operation['description'] ); ?></p>
						<?php endif; ?>

						<?php if ( ! empty( $operation['parameters'] ) ) : ?>
							<h4>Parameters</h4>
							<table>
								<thead>
									<tr>
										<th>Name</th>
										<th>In</th>
										<th>Type</th>
										<th>Required</th>
										<th>Description</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $operation['parameters'] as $param ) : ?>
										<tr>
											<td><code><?php echo esc_html( $param['name'] ); ?></code></td>
											<td><?php echo esc_html( $param['in'] ); ?></td>
											<td><?php echo esc_html( $param['schema']['type'] ?? 'string' ); ?></td>
											<td><?php echo ! empty( $param['required'] ) ? 'Yes' : 'No'; ?></td>
											<td><?php echo esc_html( $param['description'] ?? '' ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			<?php endforeach; ?>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}
}
