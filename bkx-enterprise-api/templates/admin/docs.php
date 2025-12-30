<?php
/**
 * API Documentation Template.
 *
 * @package BookingX\EnterpriseAPI
 */

defined( 'ABSPATH' ) || exit;

$base_url = rest_url( 'bookingx/v1/' );
?>
<div class="bkx-api-docs">
	<h2><?php esc_html_e( 'API Documentation', 'bkx-enterprise-api' ); ?></h2>

	<div class="bkx-docs-intro">
		<p>
			<?php esc_html_e( 'The BookingX API provides programmatic access to booking data and functionality. The API uses REST principles and supports JSON responses.', 'bkx-enterprise-api' ); ?>
		</p>
		<p>
			<strong><?php esc_html_e( 'Base URL:', 'bkx-enterprise-api' ); ?></strong>
			<code><?php echo esc_url( $base_url ); ?></code>
		</p>
	</div>

	<!-- Authentication -->
	<div class="bkx-docs-section">
		<h3><?php esc_html_e( 'Authentication', 'bkx-enterprise-api' ); ?></h3>

		<h4><?php esc_html_e( 'API Key', 'bkx-enterprise-api' ); ?></h4>
		<p><?php esc_html_e( 'Include your API key in the X-API-Key header:', 'bkx-enterprise-api' ); ?></p>
		<pre><code>curl -H "X-API-Key: your_api_key" <?php echo esc_url( $base_url ); ?>bookings</code></pre>

		<h4><?php esc_html_e( 'OAuth 2.0', 'bkx-enterprise-api' ); ?></h4>
		<p><?php esc_html_e( 'For user-authenticated requests, use OAuth 2.0 Bearer tokens:', 'bkx-enterprise-api' ); ?></p>
		<pre><code>curl -H "Authorization: Bearer your_access_token" <?php echo esc_url( $base_url ); ?>bookings</code></pre>
	</div>

	<!-- Rate Limiting -->
	<div class="bkx-docs-section">
		<h3><?php esc_html_e( 'Rate Limiting', 'bkx-enterprise-api' ); ?></h3>
		<p><?php esc_html_e( 'API requests are rate limited. Check these headers in the response:', 'bkx-enterprise-api' ); ?></p>
		<ul>
			<li><code>X-RateLimit-Limit</code> - <?php esc_html_e( 'Maximum requests per window', 'bkx-enterprise-api' ); ?></li>
			<li><code>X-RateLimit-Remaining</code> - <?php esc_html_e( 'Remaining requests in current window', 'bkx-enterprise-api' ); ?></li>
			<li><code>X-RateLimit-Reset</code> - <?php esc_html_e( 'Unix timestamp when the rate limit resets', 'bkx-enterprise-api' ); ?></li>
		</ul>
	</div>

	<!-- Endpoints -->
	<div class="bkx-docs-section">
		<h3><?php esc_html_e( 'Endpoints', 'bkx-enterprise-api' ); ?></h3>

		<div class="bkx-endpoint">
			<h4>
				<span class="bkx-method-badge bkx-method-get">GET</span>
				<code>/bookings</code>
			</h4>
			<p><?php esc_html_e( 'List all bookings with optional filtering.', 'bkx-enterprise-api' ); ?></p>
			<table class="bkx-params-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Parameter', 'bkx-enterprise-api' ); ?></th>
						<th><?php esc_html_e( 'Type', 'bkx-enterprise-api' ); ?></th>
						<th><?php esc_html_e( 'Description', 'bkx-enterprise-api' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>status</code></td>
						<td>string</td>
						<td><?php esc_html_e( 'Filter by status (pending, confirmed, completed, cancelled)', 'bkx-enterprise-api' ); ?></td>
					</tr>
					<tr>
						<td><code>from</code></td>
						<td>date</td>
						<td><?php esc_html_e( 'Start date (YYYY-MM-DD)', 'bkx-enterprise-api' ); ?></td>
					</tr>
					<tr>
						<td><code>to</code></td>
						<td>date</td>
						<td><?php esc_html_e( 'End date (YYYY-MM-DD)', 'bkx-enterprise-api' ); ?></td>
					</tr>
					<tr>
						<td><code>per_page</code></td>
						<td>integer</td>
						<td><?php esc_html_e( 'Results per page (default: 20, max: 100)', 'bkx-enterprise-api' ); ?></td>
					</tr>
					<tr>
						<td><code>page</code></td>
						<td>integer</td>
						<td><?php esc_html_e( 'Page number', 'bkx-enterprise-api' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="bkx-endpoint">
			<h4>
				<span class="bkx-method-badge bkx-method-get">GET</span>
				<code>/bookings/{id}</code>
			</h4>
			<p><?php esc_html_e( 'Get a single booking by ID.', 'bkx-enterprise-api' ); ?></p>
		</div>

		<div class="bkx-endpoint">
			<h4>
				<span class="bkx-method-badge bkx-method-post">POST</span>
				<code>/bookings</code>
			</h4>
			<p><?php esc_html_e( 'Create a new booking.', 'bkx-enterprise-api' ); ?></p>
			<h5><?php esc_html_e( 'Request Body:', 'bkx-enterprise-api' ); ?></h5>
			<pre><code>{
  "service_id": 123,
  "staff_id": 45,
  "customer_name": "John Doe",
  "customer_email": "john@example.com",
  "customer_phone": "+1234567890",
  "booking_date": "2024-01-20",
  "booking_time": "14:00",
  "notes": "Optional notes"
}</code></pre>
		</div>

		<div class="bkx-endpoint">
			<h4>
				<span class="bkx-method-badge bkx-method-put">PUT</span>
				<code>/bookings/{id}</code>
			</h4>
			<p><?php esc_html_e( 'Update an existing booking.', 'bkx-enterprise-api' ); ?></p>
		</div>

		<div class="bkx-endpoint">
			<h4>
				<span class="bkx-method-badge bkx-method-delete">DELETE</span>
				<code>/bookings/{id}</code>
			</h4>
			<p><?php esc_html_e( 'Cancel/delete a booking.', 'bkx-enterprise-api' ); ?></p>
		</div>

		<div class="bkx-endpoint">
			<h4>
				<span class="bkx-method-badge bkx-method-get">GET</span>
				<code>/services</code>
			</h4>
			<p><?php esc_html_e( 'List all available services.', 'bkx-enterprise-api' ); ?></p>
		</div>

		<div class="bkx-endpoint">
			<h4>
				<span class="bkx-method-badge bkx-method-get">GET</span>
				<code>/staff</code>
			</h4>
			<p><?php esc_html_e( 'List all staff members.', 'bkx-enterprise-api' ); ?></p>
		</div>

		<div class="bkx-endpoint">
			<h4>
				<span class="bkx-method-badge bkx-method-get">GET</span>
				<code>/availability</code>
			</h4>
			<p><?php esc_html_e( 'Get available time slots.', 'bkx-enterprise-api' ); ?></p>
			<table class="bkx-params-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Parameter', 'bkx-enterprise-api' ); ?></th>
						<th><?php esc_html_e( 'Type', 'bkx-enterprise-api' ); ?></th>
						<th><?php esc_html_e( 'Description', 'bkx-enterprise-api' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>service_id</code></td>
						<td>integer</td>
						<td><?php esc_html_e( 'Required. Service ID', 'bkx-enterprise-api' ); ?></td>
					</tr>
					<tr>
						<td><code>staff_id</code></td>
						<td>integer</td>
						<td><?php esc_html_e( 'Optional. Staff ID', 'bkx-enterprise-api' ); ?></td>
					</tr>
					<tr>
						<td><code>date</code></td>
						<td>date</td>
						<td><?php esc_html_e( 'Required. Date (YYYY-MM-DD)', 'bkx-enterprise-api' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>

	<!-- GraphQL -->
	<div class="bkx-docs-section">
		<h3><?php esc_html_e( 'GraphQL', 'bkx-enterprise-api' ); ?></h3>
		<p>
			<?php esc_html_e( 'GraphQL endpoint is available at:', 'bkx-enterprise-api' ); ?>
			<code><?php echo esc_url( home_url( '/graphql' ) ); ?></code>
		</p>
		<h4><?php esc_html_e( 'Example Query:', 'bkx-enterprise-api' ); ?></h4>
		<pre><code>query {
  bookings(first: 10) {
    edges {
      node {
        id
        status
        customerName
        bookingDate
        bookingTime
        service {
          name
          price
        }
      }
    }
    pageInfo {
      hasNextPage
    }
  }
}</code></pre>
	</div>

	<!-- Webhooks -->
	<div class="bkx-docs-section">
		<h3><?php esc_html_e( 'Webhooks', 'bkx-enterprise-api' ); ?></h3>
		<p><?php esc_html_e( 'Webhooks send POST requests when events occur. Verify the signature using the X-BookingX-Signature header:', 'bkx-enterprise-api' ); ?></p>
		<pre><code>$signature = 'sha256=' . hash_hmac('sha256', $payload, $webhook_secret);
if (hash_equals($signature, $_SERVER['HTTP_X_BOOKINGX_SIGNATURE'])) {
    // Valid webhook
}</code></pre>
	</div>

	<!-- Error Codes -->
	<div class="bkx-docs-section">
		<h3><?php esc_html_e( 'Error Codes', 'bkx-enterprise-api' ); ?></h3>
		<table class="bkx-params-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Code', 'bkx-enterprise-api' ); ?></th>
					<th><?php esc_html_e( 'Meaning', 'bkx-enterprise-api' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr><td>400</td><td><?php esc_html_e( 'Bad Request - Invalid parameters', 'bkx-enterprise-api' ); ?></td></tr>
				<tr><td>401</td><td><?php esc_html_e( 'Unauthorized - Invalid or missing authentication', 'bkx-enterprise-api' ); ?></td></tr>
				<tr><td>403</td><td><?php esc_html_e( 'Forbidden - Insufficient permissions', 'bkx-enterprise-api' ); ?></td></tr>
				<tr><td>404</td><td><?php esc_html_e( 'Not Found - Resource does not exist', 'bkx-enterprise-api' ); ?></td></tr>
				<tr><td>429</td><td><?php esc_html_e( 'Too Many Requests - Rate limit exceeded', 'bkx-enterprise-api' ); ?></td></tr>
				<tr><td>500</td><td><?php esc_html_e( 'Internal Server Error', 'bkx-enterprise-api' ); ?></td></tr>
			</tbody>
		</table>
	</div>
</div>

<style>
.bkx-docs-section {
	background: #fff;
	border: 1px solid #e5e7eb;
	border-radius: 8px;
	padding: 20px;
	margin-bottom: 20px;
}
.bkx-docs-section h3 {
	margin-top: 0;
	border-bottom: 1px solid #e5e7eb;
	padding-bottom: 10px;
}
.bkx-endpoint {
	margin: 20px 0;
	padding: 15px;
	background: #f9fafb;
	border-radius: 6px;
}
.bkx-endpoint h4 {
	margin: 0 0 10px;
	display: flex;
	align-items: center;
	gap: 10px;
}
.bkx-params-table {
	width: 100%;
	border-collapse: collapse;
}
.bkx-params-table th,
.bkx-params-table td {
	padding: 8px 12px;
	text-align: left;
	border-bottom: 1px solid #e5e7eb;
}
.bkx-params-table th {
	background: #f3f4f6;
}
.bkx-docs-section pre {
	background: #1f2937;
	color: #f3f4f6;
	padding: 15px;
	border-radius: 6px;
	overflow-x: auto;
}
.bkx-docs-section code {
	background: #f3f4f6;
	padding: 2px 6px;
	border-radius: 3px;
}
.bkx-docs-section pre code {
	background: transparent;
	padding: 0;
}
</style>
