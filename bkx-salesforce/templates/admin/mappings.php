<?php
/**
 * Field mappings tab template.
 *
 * @package BookingX\Salesforce
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;
$table = $wpdb->prefix . 'bkx_sf_field_mappings';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$mappings = $wpdb->get_results(
	"SELECT * FROM {$table} ORDER BY object_type, wp_field"
);

$object_types = array(
	'Contact'     => __( 'Contact', 'bkx-salesforce' ),
	'Lead'        => __( 'Lead', 'bkx-salesforce' ),
	'Opportunity' => __( 'Opportunity', 'bkx-salesforce' ),
);

$wp_fields = array(
	'customer_first_name' => __( 'Customer First Name', 'bkx-salesforce' ),
	'customer_last_name'  => __( 'Customer Last Name', 'bkx-salesforce' ),
	'customer_email'      => __( 'Customer Email', 'bkx-salesforce' ),
	'customer_phone'      => __( 'Customer Phone', 'bkx-salesforce' ),
	'billing_address_1'   => __( 'Billing Address', 'bkx-salesforce' ),
	'billing_city'        => __( 'Billing City', 'bkx-salesforce' ),
	'billing_state'       => __( 'Billing State', 'bkx-salesforce' ),
	'billing_postcode'    => __( 'Billing Postcode', 'bkx-salesforce' ),
	'billing_country'     => __( 'Billing Country', 'bkx-salesforce' ),
	'booking_date'        => __( 'Booking Date', 'bkx-salesforce' ),
	'booking_time'        => __( 'Booking Time', 'bkx-salesforce' ),
	'booking_service'     => __( 'Service Name', 'bkx-salesforce' ),
	'booking_total'       => __( 'Booking Total', 'bkx-salesforce' ),
	'booking_notes'       => __( 'Booking Notes', 'bkx-salesforce' ),
);

$sync_directions = array(
	'both'     => __( 'Bi-directional', 'bkx-salesforce' ),
	'wp_to_sf' => __( 'WordPress to Salesforce', 'bkx-salesforce' ),
	'sf_to_wp' => __( 'Salesforce to WordPress', 'bkx-salesforce' ),
);

$transforms = array(
	''           => __( 'None', 'bkx-salesforce' ),
	'uppercase'  => __( 'Uppercase', 'bkx-salesforce' ),
	'lowercase'  => __( 'Lowercase', 'bkx-salesforce' ),
	'ucfirst'    => __( 'Capitalize First', 'bkx-salesforce' ),
	'date_iso'   => __( 'Date (YYYY-MM-DD)', 'bkx-salesforce' ),
	'float'      => __( 'Decimal Number', 'bkx-salesforce' ),
	'int'        => __( 'Integer', 'bkx-salesforce' ),
);
?>

<div class="bkx-sf-mappings">
	<div class="bkx-card">
		<h2><?php esc_html_e( 'Field Mappings', 'bkx-salesforce' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Configure how BookingX fields map to Salesforce object fields. Mappings determine which data is synced between systems.', 'bkx-salesforce' ); ?>
		</p>

		<div class="bkx-mapping-tabs">
			<?php foreach ( $object_types as $type => $label ) : ?>
				<button type="button" class="bkx-mapping-tab <?php echo 'Contact' === $type ? 'active' : ''; ?>"
						data-type="<?php echo esc_attr( $type ); ?>">
					<?php echo esc_html( $label ); ?>
				</button>
			<?php endforeach; ?>
		</div>

		<?php foreach ( $object_types as $type => $label ) : ?>
			<div class="bkx-mapping-panel" id="panel-<?php echo esc_attr( $type ); ?>"
				 style="<?php echo 'Contact' !== $type ? 'display: none;' : ''; ?>">

				<table class="widefat striped bkx-mapping-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'WordPress Field', 'bkx-salesforce' ); ?></th>
							<th><?php esc_html_e( 'Salesforce Field', 'bkx-salesforce' ); ?></th>
							<th><?php esc_html_e( 'Direction', 'bkx-salesforce' ); ?></th>
							<th><?php esc_html_e( 'Transform', 'bkx-salesforce' ); ?></th>
							<th><?php esc_html_e( 'Active', 'bkx-salesforce' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'bkx-salesforce' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$type_mappings = array_filter(
							$mappings,
							function ( $m ) use ( $type ) {
								return $m->object_type === $type;
							}
						);

						if ( empty( $type_mappings ) ) :
							?>
							<tr class="no-items">
								<td colspan="6"><?php esc_html_e( 'No field mappings configured.', 'bkx-salesforce' ); ?></td>
							</tr>
						<?php else : ?>
							<?php foreach ( $type_mappings as $mapping ) : ?>
								<tr data-id="<?php echo esc_attr( $mapping->id ); ?>">
									<td>
										<?php echo esc_html( $wp_fields[ $mapping->wp_field ] ?? $mapping->wp_field ); ?>
										<code class="bkx-field-name"><?php echo esc_html( $mapping->wp_field ); ?></code>
									</td>
									<td>
										<code><?php echo esc_html( $mapping->sf_field ); ?></code>
									</td>
									<td><?php echo esc_html( $sync_directions[ $mapping->sync_direction ] ?? $mapping->sync_direction ); ?></td>
									<td><?php echo esc_html( $transforms[ $mapping->transform ] ?? $mapping->transform ); ?></td>
									<td>
										<?php if ( $mapping->is_active ) : ?>
											<span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
										<?php else : ?>
											<span class="dashicons dashicons-minus" style="color: #999;"></span>
										<?php endif; ?>
									</td>
									<td>
										<button type="button" class="button button-small bkx-edit-mapping"
												data-mapping='<?php echo esc_attr( wp_json_encode( $mapping ) ); ?>'>
											<?php esc_html_e( 'Edit', 'bkx-salesforce' ); ?>
										</button>
										<button type="button" class="button button-small button-link-delete bkx-delete-mapping"
												data-id="<?php echo esc_attr( $mapping->id ); ?>">
											<?php esc_html_e( 'Delete', 'bkx-salesforce' ); ?>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>

				<p>
					<button type="button" class="button bkx-add-mapping" data-type="<?php echo esc_attr( $type ); ?>">
						<?php esc_html_e( 'Add Field Mapping', 'bkx-salesforce' ); ?>
					</button>
					<button type="button" class="button bkx-fetch-sf-fields" data-type="<?php echo esc_attr( $type ); ?>">
						<?php esc_html_e( 'Fetch Salesforce Fields', 'bkx-salesforce' ); ?>
					</button>
				</p>
			</div>
		<?php endforeach; ?>
	</div>
</div>

<!-- Field Mapping Modal -->
<div id="bkx-mapping-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content">
		<span class="bkx-modal-close">&times;</span>
		<h2 id="bkx-modal-title"><?php esc_html_e( 'Add Field Mapping', 'bkx-salesforce' ); ?></h2>

		<form id="bkx-mapping-form">
			<input type="hidden" name="id" id="mapping-id">
			<input type="hidden" name="object_type" id="mapping-object-type">

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="mapping-wp-field"><?php esc_html_e( 'WordPress Field', 'bkx-salesforce' ); ?></label>
					</th>
					<td>
						<select id="mapping-wp-field" name="wp_field" required>
							<option value=""><?php esc_html_e( 'Select field...', 'bkx-salesforce' ); ?></option>
							<?php foreach ( $wp_fields as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
							<option value="custom"><?php esc_html_e( 'Custom field...', 'bkx-salesforce' ); ?></option>
						</select>
						<input type="text" id="mapping-wp-field-custom" name="wp_field_custom" class="regular-text"
							   placeholder="<?php esc_attr_e( 'Enter custom field name', 'bkx-salesforce' ); ?>" style="display: none;">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mapping-sf-field"><?php esc_html_e( 'Salesforce Field', 'bkx-salesforce' ); ?></label>
					</th>
					<td>
						<select id="mapping-sf-field" name="sf_field">
							<option value=""><?php esc_html_e( 'Loading...', 'bkx-salesforce' ); ?></option>
						</select>
						<input type="text" id="mapping-sf-field-custom" name="sf_field_custom" class="regular-text"
							   placeholder="<?php esc_attr_e( 'Or enter API name manually', 'bkx-salesforce' ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mapping-direction"><?php esc_html_e( 'Sync Direction', 'bkx-salesforce' ); ?></label>
					</th>
					<td>
						<select id="mapping-direction" name="sync_direction">
							<?php foreach ( $sync_directions as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mapping-transform"><?php esc_html_e( 'Transform', 'bkx-salesforce' ); ?></label>
					</th>
					<td>
						<select id="mapping-transform" name="transform">
							<?php foreach ( $transforms as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Active', 'bkx-salesforce' ); ?></th>
					<td>
						<label>
							<input type="checkbox" id="mapping-active" name="is_active" value="1" checked>
							<?php esc_html_e( 'Enable this field mapping', 'bkx-salesforce' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Save Mapping', 'bkx-salesforce' ); ?>
				</button>
				<button type="button" class="button bkx-modal-cancel">
					<?php esc_html_e( 'Cancel', 'bkx-salesforce' ); ?>
				</button>
			</p>
		</form>
	</div>
</div>

<!-- Salesforce Fields Cache -->
<script type="text/javascript">
	var bkxSfFieldsCache = {};
</script>
