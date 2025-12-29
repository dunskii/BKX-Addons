<?php
/**
 * Location map shortcode template.
 *
 * @package BookingX\MultiLocation
 */

defined( 'ABSPATH' ) || exit;

$map_id = 'bkx-map-' . wp_rand( 1000, 9999 );
$height = $atts['height'];
$zoom   = absint( $atts['zoom'] );
?>

<div class="bkx-location-map-wrapper">
	<div id="<?php echo esc_attr( $map_id ); ?>" class="bkx-location-map" style="height: <?php echo esc_attr( $height ); ?>;"></div>
</div>

<script>
(function() {
	var locations = <?php echo wp_json_encode( array_map( function( $l ) {
		$addon   = \BookingX\MultiLocation\MultiLocationAddon::get_instance();
		$address = $addon->get_service( 'locations' )->get_formatted_address( $l );

		return array(
			'id'      => $l->id,
			'name'    => $l->name,
			'lat'     => floatval( $l->latitude ),
			'lng'     => floatval( $l->longitude ),
			'address' => $address,
			'phone'   => $l->phone,
		);
	}, $locations ) ); ?>;

	var zoom = <?php echo absint( $zoom ); ?>;
	var mapId = '<?php echo esc_js( $map_id ); ?>';

	function initBkxMap() {
		if (typeof google === 'undefined' || !locations.length) {
			return;
		}

		var bounds = new google.maps.LatLngBounds();
		var map = new google.maps.Map(document.getElementById(mapId), {
			zoom: zoom,
			center: { lat: locations[0].lat, lng: locations[0].lng }
		});

		var infoWindow = new google.maps.InfoWindow();

		locations.forEach(function(location) {
			if (!location.lat || !location.lng) {
				return;
			}

			var marker = new google.maps.Marker({
				position: { lat: location.lat, lng: location.lng },
				map: map,
				title: location.name
			});

			bounds.extend(marker.getPosition());

			var content = '<div class="bkx-map-info">' +
				'<h4>' + location.name + '</h4>' +
				(location.address ? '<p>' + location.address + '</p>' : '') +
				(location.phone ? '<p><a href="tel:' + location.phone.replace(/[^0-9+]/g, '') + '">' + location.phone + '</a></p>' : '') +
				'</div>';

			marker.addListener('click', function() {
				infoWindow.setContent(content);
				infoWindow.open(map, marker);
			});
		});

		if (locations.length > 1) {
			map.fitBounds(bounds);
		}
	}

	if (document.readyState === 'complete') {
		initBkxMap();
	} else {
		window.addEventListener('load', initBkxMap);
	}
})();
</script>
