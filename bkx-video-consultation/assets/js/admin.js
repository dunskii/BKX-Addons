/**
 * Video Consultation Admin JavaScript.
 *
 * @package BookingX\VideoConsultation
 * @since   1.0.0
 */

(function($) {
	'use strict';

	/**
	 * Initialize on document ready.
	 */
	$(document).ready(function() {
		initRoomsTab();
		initRecordingsTab();
		initSettingsForm();
	});

	/**
	 * Initialize rooms tab.
	 */
	function initRoomsTab() {
		loadRooms();

		$('#bkx-refresh-rooms').on('click', loadRooms);
		$('#bkx-room-status-filter, #bkx-room-provider-filter').on('change', loadRooms);

		// Room actions.
		$(document).on('click', '.bkx-join-room', function() {
			var url = $(this).data('url');
			window.open(url, '_blank');
		});

		$(document).on('click', '.bkx-end-room', function() {
			if (!confirm(bkxVideoData.i18n.confirm_end)) {
				return;
			}

			var roomId = $(this).data('room-id');
			endSession(roomId);
		});
	}

	/**
	 * Load rooms list.
	 */
	function loadRooms() {
		var $tbody = $('#bkx-rooms-table tbody');

		$.ajax({
			url: bkxVideoData.ajax_url,
			type: 'POST',
			data: {
				action: 'bkx_get_video_rooms',
				nonce: bkxVideoData.nonce,
				status: $('#bkx-room-status-filter').val(),
				provider: $('#bkx-room-provider-filter').val()
			},
			beforeSend: function() {
				$tbody.html('<tr><td colspan="7">Loading...</td></tr>');
			},
			success: function(response) {
				if (response.success && response.data) {
					renderRooms(response.data);
				} else {
					$tbody.html('<tr><td colspan="7">No rooms found.</td></tr>');
				}
			},
			error: function() {
				$tbody.html('<tr><td colspan="7">Failed to load rooms.</td></tr>');
			}
		});
	}

	/**
	 * Render rooms table.
	 */
	function renderRooms(rooms) {
		var $tbody = $('#bkx-rooms-table tbody');
		$tbody.empty();

		if (!rooms.length) {
			$tbody.html('<tr><td colspan="7">No video rooms found.</td></tr>');
			return;
		}

		rooms.forEach(function(room) {
			var statusClass = 'bkx-status-badge ' + room.status;
			var providerClass = 'bkx-provider-badge ' + room.provider;

			var actions = '';
			if (room.status === 'scheduled' || room.status === 'active') {
				actions += '<button class="bkx-action-btn join bkx-join-room" data-url="' + room.host_url + '">Join</button> ';
			}
			if (room.status === 'active') {
				actions += '<button class="bkx-action-btn end bkx-end-room" data-room-id="' + room.id + '">End</button>';
			}

			var row = '<tr>';
			row += '<td><code>' + room.room_id + '</code></td>';
			row += '<td><a href="post.php?post=' + room.booking_id + '&action=edit">#' + room.booking_id + '</a></td>';
			row += '<td><span class="' + providerClass + '">' + room.provider.toUpperCase() + '</span></td>';
			row += '<td>' + (room.scheduled_start || '-') + '</td>';
			row += '<td><span class="' + statusClass + '">' + room.status + '</span></td>';
			row += '<td>' + (room.duration_minutes ? room.duration_minutes + ' min' : '-') + '</td>';
			row += '<td>' + actions + '</td>';
			row += '</tr>';

			$tbody.append(row);
		});
	}

	/**
	 * End a video session.
	 */
	function endSession(roomId) {
		$.ajax({
			url: bkxVideoData.ajax_url,
			type: 'POST',
			data: {
				action: 'bkx_end_video_session',
				nonce: bkxVideoData.nonce,
				room_id: roomId
			},
			success: function(response) {
				if (response.success) {
					loadRooms();
				} else {
					alert(response.data || 'Failed to end session.');
				}
			}
		});
	}

	/**
	 * Initialize recordings tab.
	 */
	function initRecordingsTab() {
		if ($('#bkx-recordings-table').length === 0) {
			return;
		}

		loadRecordings();

		$(document).on('click', '.bkx-download-recording', function() {
			var url = $(this).data('url');
			window.location.href = url;
		});

		$(document).on('click', '.bkx-delete-recording', function() {
			if (!confirm(bkxVideoData.i18n.confirm_delete)) {
				return;
			}

			var recordingId = $(this).data('id');
			deleteRecording(recordingId);
		});
	}

	/**
	 * Load recordings list.
	 */
	function loadRecordings() {
		var $tbody = $('#bkx-recordings-table tbody');

		$.ajax({
			url: bkxVideoData.ajax_url,
			type: 'POST',
			data: {
				action: 'bkx_get_video_recordings',
				nonce: bkxVideoData.nonce
			},
			success: function(response) {
				if (response.success && response.data) {
					renderRecordings(response.data.recordings);
					$('#bkx-storage-used').text(response.data.storage_formatted);
				} else {
					$tbody.html('<tr><td colspan="7">No recordings found.</td></tr>');
				}
			}
		});
	}

	/**
	 * Render recordings table.
	 */
	function renderRecordings(recordings) {
		var $tbody = $('#bkx-recordings-table tbody');
		$tbody.empty();

		if (!recordings.length) {
			$tbody.html('<tr><td colspan="7">No recordings found.</td></tr>');
			return;
		}

		recordings.forEach(function(rec) {
			var row = '<tr>';
			row += '<td><code>' + rec.recording_id + '</code></td>';
			row += '<td><a href="post.php?post=' + rec.booking_id + '&action=edit">#' + rec.booking_id + '</a></td>';
			row += '<td>' + formatDuration(rec.duration_seconds) + '</td>';
			row += '<td>' + formatBytes(rec.file_size) + '</td>';
			row += '<td>' + rec.created_at + '</td>';
			row += '<td>' + rec.expires_at + '</td>';
			row += '<td>';
			row += '<button class="bkx-action-btn download bkx-download-recording" data-url="' + rec.download_url + '">Download</button> ';
			row += '<button class="bkx-action-btn delete bkx-delete-recording" data-id="' + rec.id + '">Delete</button>';
			row += '</td>';
			row += '</tr>';

			$tbody.append(row);
		});
	}

	/**
	 * Delete a recording.
	 */
	function deleteRecording(recordingId) {
		$.ajax({
			url: bkxVideoData.ajax_url,
			type: 'POST',
			data: {
				action: 'bkx_delete_video_recording',
				nonce: bkxVideoData.nonce,
				recording_id: recordingId
			},
			success: function(response) {
				if (response.success) {
					loadRecordings();
				} else {
					alert(response.data || 'Failed to delete recording.');
				}
			}
		});
	}

	/**
	 * Initialize settings form.
	 */
	function initSettingsForm() {
		$('#bkx-video-settings-form').on('submit', function(e) {
			e.preventDefault();

			var $form = $(this);
			var $button = $form.find('input[type="submit"]');

			$.ajax({
				url: bkxVideoData.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_save_video_settings',
					nonce: bkxVideoData.nonce,
					settings: $form.serialize()
				},
				beforeSend: function() {
					$button.prop('disabled', true).val('Saving...');
				},
				success: function(response) {
					$button.prop('disabled', false).val('Save Changes');

					if (response.success) {
						// Show success notice.
						var notice = '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
						$form.before(notice);

						setTimeout(function() {
							$('.notice-success').fadeOut();
						}, 3000);
					} else {
						alert(response.data || 'Failed to save settings.');
					}
				},
				error: function() {
					$button.prop('disabled', false).val('Save Changes');
					alert('Failed to save settings.');
				}
			});
		});
	}

	/**
	 * Format duration.
	 */
	function formatDuration(seconds) {
		if (!seconds) return '-';

		var hours = Math.floor(seconds / 3600);
		var minutes = Math.floor((seconds % 3600) / 60);
		var secs = seconds % 60;

		if (hours > 0) {
			return hours + ':' + pad(minutes) + ':' + pad(secs);
		}
		return minutes + ':' + pad(secs);
	}

	/**
	 * Pad number with leading zero.
	 */
	function pad(num) {
		return String(num).padStart(2, '0');
	}

	/**
	 * Format bytes to human readable.
	 */
	function formatBytes(bytes) {
		if (!bytes) return '0 B';

		var sizes = ['B', 'KB', 'MB', 'GB'];
		var i = Math.floor(Math.log(bytes) / Math.log(1024));
		return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + sizes[i];
	}

})(jQuery);
