<?php
/**
 * Property mappings tab template.
 *
 * @package BookingX\HubSpot
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;
$table = $wpdb->prefix . 'bkx_hs_property_mappings';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$mappings = $wpdb->get_results(
	"SELECT * FROM {$table} ORDER BY object_type, wp_field"
);

$object_types = array(
	'contact' => __( 'Contact', 'bkx-hubspot' ),
	'deal'    => __( 'Deal', 'bkx-hubspot' ),
);

$wp_fields = array(
	'customer_first_name' => __( 'Customer First Name', 'bkx-hubspot' ),
	'customer_last_name'  => __( 'Customer Last Name', 'bkx-hubspot' ),
	'customer_email'      => __( 'Customer Email', 'bkx-hubspot' ),
	'customer_phone'      => __( 'Customer Phone', 'bkx-hubspot' ),
	'billing_address_1'   => __( 'Billing Address', 'bkx-hubspot' ),
	'billing_city'        => __( 'Billing City', 'bkx-hubspot' ),
	'billing_state'       => __( 'Billing State', 'bkx-hubspot' ),
	'billing_postcode'    => __( 'Billing Postcode', 'bkx-hubspot' ),
	'billing_country'     => __( 'Billing Country', 'bkx-hubspot' ),
	'booking_date'        => __( 'Booking Date', 'bkx-hubspot' ),
	'booking_time'        => __( 'Booking Time', 'bkx-hubspot' ),
	'booking_service'     => __( 'Service Name', 'bkx-hubspot' ),
	'booking_total'       => __( 'Booking Total', 'bkx-hubspot' ),
	'booking_notes'       => __( 'Booking Notes', 'bkx-hubspot' ),
);

$sync_directions = array(
	'both'     => __( 'Bi-directional', 'bkx-hubspot' ),
	'wp_to_hs' => __( 'WordPress to HubSpot', 'bkx-hubspot' ),
	'hs_to_wp' => __( 'HubSpot to WordPress', 'bkx-hubspot' ),
);
?>

<div class="bkx-hs-mappings">
	<div class="bkx-card">
		<h2><?php esc_html_e( 'Property Mappings', 'bkx-hubspot' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Configure how BookingX fields map to HubSpot properties.', 'bkx-hubspot' ); ?>
		</p>

		<div class="bkx-mapping-tabs">
			<?php foreach ( $object_types as $type => $label ) : ?>
				<button type="button" class="bkx-mapping-tab <?php echo 'contact' === $type ? 'active' : ''; ?>"
						data-type="<?php echo esc_attr( $type ); ?>">
					<?php echo esc_html( $label ); ?>
				</button>
			<?php endforeach; ?>
		</div>

		<?php foreach ( $object_types as $type => $label ) : ?>
			<div class="bkx-mapping-panel" id="panel-<?php echo esc_attr( $type ); ?>"
				 style="<?php echo 'contact' !== $type ? 'display: none;' : ''; ?>">

				<table class="widefat striped bkx-mapping-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'WordPress Field', 'bkx-hubspot' ); ?></th>
							<th><?php esc_html_e( 'HubSpot Property', 'bkx-hubspot' ); ?></th>
							<th><?php esc_html_e( 'Direction', 'bkx-hubspot' ); ?></th>
							<th><?php esc_html_e( 'Active', 'bkx-hubspot' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'bkx-hubspot' ); ?></th>
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
								<td colspan="5"><?php esc_html_e( 'No property mappings configured.', 'bkx-hubspot' ); ?></td>
							</tr>
						<?php else : ?>
							<?php foreach ( $type_mappings as $mapping ) : ?>
								<tr data-id="<?php echo esc_attr( $mapping->id ); ?>">
									<td>
										<?php echo esc_html( $wp_fields[ $mapping->wp_field ] ?? $mapping->wp_field ); ?>
										<code class="bkx-field-name"><?php echo esc_html( $mapping->wp_field ); ?></code>
									</td>
									<td>
										<code><?php echo esc_html( $mapping->hs_property ); ?></code>
									</td>
									<td><?php echo esc_html( $sync_directions[ $mapping->sync_direction ] ?? $mapping->sync_direction ); ?></td>
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
											<?php esc_html_e( 'Edit', 'bkx-hubspot' ); ?>
										</button>
										<button type="button" class="button button-small button-link-delete bkx-delete-mapping"
												data-id="<?php echo esc_attr( $mapping->id ); ?>">
											<?php esc_html_e( 'Delete', 'bkx-hubspot' ); ?>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>

				<p>
					<button type="button" class="button bkx-add-mapping" data-type="<?php echo esc_attr( $type ); ?>">
						<?php esc_html_e( 'Add Property Mapping', 'bkx-hubspot' ); ?>
					</button>
					<button type="button" class="button bkx-fetch-hs-props" data-type="<?php echo esc_attr( $type ); ?>">
						<?php esc_html_e( 'Fetch HubSpot Properties', 'bkx-hubspot' ); ?>
					</button>
				</p>
			</div>
		<?php endforeach; ?>
	</div>
</div>

<!-- Property Mapping Modal -->
<div id="bkx-mapping-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content">
		<span class="bkx-modal-close">&times;</span>
		<h2 id="bkx-modal-title"><?php esc_html_e( 'Add Property Mapping', 'bkx-hubspot' ); ?></h2>

		<form id="bkx-mapping-form">
			<input type="hidden" name="id" id="mapping-id">
			<input type="hidden" name="object_type" id="mapping-object-type">

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="mapping-wp-field"><?php esc_html_e( 'WordPress Field', 'bkx-hubspot' ); ?></label>
					</th>
					<td>
						<select id="mapping-wp-field" name="wp_field" required>
							<option value=""><?php esc_html_e( 'Select field...', 'bkx-hubspot' ); ?></option>
							<?php foreach ( $wp_fields as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
							<option value="custom"><?php esc_html_e( 'Custom field...', 'bkx-hubspot' ); ?></option>
						</select>
						<input type="text" id="mapping-wp-field-custom" name="wp_field_custom" class="regular-text"
							   placeholder="<?php esc_attr_e( 'Enter custom field name', 'bkx-hubspot' ); ?>" style="display: none;">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mapping-hs-prop"><?php esc_html_e( 'HubSpot Property', 'bkx-hubspot' ); ?></label>
					</th>
					<td>
						<select id="mapping-hs-prop" name="hs_property">
							<option value=""><?php esc_html_e( 'Loading...', 'bkx-hubspot' ); ?></option>
						</select>
						<input type="text" id="mapping-hs-prop-custom" name="hs_property_custom" class="regular-text"
							   placeholder="<?php esc_attr_e( 'Or enter internal name manually', 'bkx-hubspot' ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mapping-direction"><?php esc_html_e( 'Sync Direction', 'bkx-hubspot' ); ?></label>
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
					<th scope="row"><?php esc_html_e( 'Active', 'bkx-hubspot' ); ?></th>
					<td>
						<label>
							<input type="checkbox" id="mapping-active" name="is_active" value="1" checked>
							<?php esc_html_e( 'Enable this property mapping', 'bkx-hubspot' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Save Mapping', 'bkx-hubspot' ); ?>
				</button>
				<button type="button" class="button bkx-modal-cancel">
					<?php esc_html_e( 'Cancel', 'bkx-hubspot' ); ?>
				</button>
			</p>
		</form>
	</div>
</div>

<script type="text/javascript">
	var bkxHsPropsCache = {};
</script>
