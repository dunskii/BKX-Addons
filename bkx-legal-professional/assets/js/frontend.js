/**
 * Legal & Professional Services - Frontend JavaScript
 *
 * @package BookingX\LegalProfessional
 * @since   1.0.0
 */

(function($) {
	'use strict';

	/**
	 * Portal tabs.
	 */
	function initPortalTabs() {
		const $tabs = $('.bkx-legal-tab');
		const $contents = $('.bkx-legal-tab-content');

		if (!$tabs.length) return;

		$tabs.on('click', function() {
			const target = $(this).data('tab');

			$tabs.removeClass('active');
			$(this).addClass('active');

			$contents.removeClass('active');
			$('#' + target).addClass('active');

			// Update URL hash
			window.location.hash = target;
		});

		// Check for hash on load
		if (window.location.hash) {
			const hash = window.location.hash.substring(1);
			const $tab = $tabs.filter('[data-tab="' + hash + '"]');
			if ($tab.length) {
				$tab.trigger('click');
			}
		}
	}

	/**
	 * Client intake form.
	 */
	function initIntakeForm() {
		const $form = $('.bkx-intake-form');
		if (!$form.length) return;

		// Form validation
		$form.on('submit', function(e) {
			let isValid = true;
			const $required = $form.find('[required]');

			$required.each(function() {
				const $field = $(this);
				const value = $field.val().trim();

				$field.removeClass('error');

				if (!value) {
					$field.addClass('error');
					isValid = false;
				}

				// Email validation
				if ($field.attr('type') === 'email' && value) {
					const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
					if (!emailRegex.test(value)) {
						$field.addClass('error');
						isValid = false;
					}
				}

				// Phone validation
				if ($field.attr('type') === 'tel' && value) {
					const phoneRegex = /^[\d\s\-\+\(\)]+$/;
					if (!phoneRegex.test(value)) {
						$field.addClass('error');
						isValid = false;
					}
				}
			});

			if (!isValid) {
				e.preventDefault();

				// Scroll to first error
				const $firstError = $form.find('.error').first();
				if ($firstError.length) {
					$('html, body').animate({
						scrollTop: $firstError.offset().top - 100
					}, 300);
				}

				// Show error message
				if (!$form.find('.bkx-form-error').length) {
					$form.prepend('<div class="bkx-form-error">Please fill in all required fields correctly.</div>');
				}
			}
		});

		// Clear error on input
		$form.on('input change', '.error', function() {
			$(this).removeClass('error');
		});
	}

	/**
	 * Signature pad for retainer signing.
	 */
	function initSignaturePad() {
		const $canvas = $('#bkx-signature-canvas');
		if (!$canvas.length) return;

		const canvas = $canvas[0];
		const ctx = canvas.getContext('2d');
		let isDrawing = false;
		let lastX = 0;
		let lastY = 0;

		// Set canvas size
		function resizeCanvas() {
			const container = $canvas.parent();
			canvas.width = container.width();
			canvas.height = 200;

			// Reset context properties after resize
			ctx.strokeStyle = '#000';
			ctx.lineWidth = 2;
			ctx.lineCap = 'round';
			ctx.lineJoin = 'round';
		}

		resizeCanvas();
		$(window).on('resize', resizeCanvas);

		// Drawing functions
		function getPos(e) {
			const rect = canvas.getBoundingClientRect();
			const x = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left;
			const y = (e.touches ? e.touches[0].clientY : e.clientY) - rect.top;
			return { x, y };
		}

		function startDrawing(e) {
			isDrawing = true;
			const pos = getPos(e);
			lastX = pos.x;
			lastY = pos.y;
		}

		function draw(e) {
			if (!isDrawing) return;
			e.preventDefault();

			const pos = getPos(e);

			ctx.beginPath();
			ctx.moveTo(lastX, lastY);
			ctx.lineTo(pos.x, pos.y);
			ctx.stroke();

			lastX = pos.x;
			lastY = pos.y;
		}

		function stopDrawing() {
			isDrawing = false;
		}

		// Mouse events
		canvas.addEventListener('mousedown', startDrawing);
		canvas.addEventListener('mousemove', draw);
		canvas.addEventListener('mouseup', stopDrawing);
		canvas.addEventListener('mouseout', stopDrawing);

		// Touch events
		canvas.addEventListener('touchstart', startDrawing);
		canvas.addEventListener('touchmove', draw);
		canvas.addEventListener('touchend', stopDrawing);

		// Clear button
		$('#bkx-clear-signature').on('click', function() {
			ctx.clearRect(0, 0, canvas.width, canvas.height);
		});

		// Submit signature
		$('#bkx-submit-signature').on('click', function() {
			// Check if canvas is empty
			const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
			const data = imageData.data;
			let isEmpty = true;

			for (let i = 0; i < data.length; i += 4) {
				if (data[i + 3] !== 0) {
					isEmpty = false;
					break;
				}
			}

			if (isEmpty) {
				alert('Please sign before submitting.');
				return;
			}

			const signatureData = canvas.toDataURL('image/png');
			const $btn = $(this);
			const retainerId = $btn.data('retainer-id');
			const token = $btn.data('token');

			$btn.prop('disabled', true).text('Submitting...');

			$.ajax({
				url: bkxLegalFrontend.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_legal_sign_retainer',
					nonce: bkxLegalFrontend.nonce,
					retainer_id: retainerId,
					token: token,
					signature: signatureData
				},
				success: function(response) {
					if (response.success) {
						$('.bkx-signature-area').html('<div class="bkx-success-message"><h3>Thank you!</h3><p>Your signature has been recorded.</p></div>');
					} else {
						alert(response.data.message || 'An error occurred.');
						$btn.prop('disabled', false).text('Submit Signature');
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
					$btn.prop('disabled', false).text('Submit Signature');
				}
			});
		});
	}

	/**
	 * Document upload from client portal.
	 */
	function initClientDocUpload() {
		const $form = $('#bkx-client-doc-upload');
		if (!$form.length) return;

		$form.on('submit', function(e) {
			e.preventDefault();

			const formData = new FormData(this);
			formData.append('action', 'bkx_legal_client_upload_document');
			formData.append('nonce', bkxLegalFrontend.nonce);

			const $submit = $form.find('button[type="submit"]');
			const $progress = $form.find('.upload-progress');

			$submit.prop('disabled', true).text('Uploading...');
			$progress.show();

			$.ajax({
				url: bkxLegalFrontend.ajaxUrl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				xhr: function() {
					const xhr = new window.XMLHttpRequest();
					xhr.upload.addEventListener('progress', function(e) {
						if (e.lengthComputable) {
							const percent = Math.round((e.loaded / e.total) * 100);
							$progress.find('.progress-bar').css('width', percent + '%');
						}
					}, false);
					return xhr;
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || 'Upload failed.');
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
				},
				complete: function() {
					$submit.prop('disabled', false).text('Upload');
					$progress.hide();
				}
			});
		});
	}

	/**
	 * Client messaging.
	 */
	function initMessaging() {
		const $container = $('.bkx-messages-container');
		if (!$container.length) return;

		// Load message
		$container.on('click', '.bkx-message-preview', function() {
			const messageId = $(this).data('message-id');
			const $detail = $('.bkx-message-detail');

			$('.bkx-message-preview').removeClass('active');
			$(this).addClass('active').removeClass('unread');

			$.ajax({
				url: bkxLegalFrontend.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_legal_get_message',
					nonce: bkxLegalFrontend.nonce,
					message_id: messageId
				},
				success: function(response) {
					if (response.success) {
						$detail.html(response.data.html);
					}
				}
			});
		});

		// Send message
		$container.on('submit', '#bkx-message-reply-form', function(e) {
			e.preventDefault();

			const $form = $(this);
			const $submit = $form.find('button[type="submit"]');

			$submit.prop('disabled', true).text('Sending...');

			$.ajax({
				url: bkxLegalFrontend.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_legal_send_message',
					nonce: bkxLegalFrontend.nonce,
					parent_id: $form.find('[name="parent_id"]').val(),
					message: $form.find('[name="message"]').val()
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || 'Failed to send message.');
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
				},
				complete: function() {
					$submit.prop('disabled', false).text('Send');
				}
			});
		});

		// New message modal
		$('#bkx-new-message-btn').on('click', function() {
			$('#bkx-new-message-modal').show();
		});

		$('.bkx-modal-close').on('click', function() {
			$(this).closest('.bkx-modal-overlay').hide();
		});

		$('#bkx-new-message-form').on('submit', function(e) {
			e.preventDefault();

			const $form = $(this);
			const $submit = $form.find('button[type="submit"]');

			$submit.prop('disabled', true).text('Sending...');

			$.ajax({
				url: bkxLegalFrontend.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_legal_send_message',
					nonce: bkxLegalFrontend.nonce,
					matter_id: $form.find('[name="matter_id"]').val(),
					subject: $form.find('[name="subject"]').val(),
					message: $form.find('[name="message"]').val()
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || 'Failed to send message.');
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
				},
				complete: function() {
					$submit.prop('disabled', false).text('Send Message');
				}
			});
		});
	}

	/**
	 * Matter filter.
	 */
	function initMatterFilter() {
		const $filter = $('#bkx-matter-status-filter');
		if (!$filter.length) return;

		$filter.on('change', function() {
			const status = $(this).val();
			const $matters = $('.bkx-matter-card');

			if (!status) {
				$matters.show();
			} else {
				$matters.hide();
				$matters.filter('[data-status="' + status + '"]').show();
			}
		});
	}

	/**
	 * Invoice download.
	 */
	function initInvoiceDownload() {
		$(document).on('click', '.bkx-download-invoice', function(e) {
			// Allow default link behavior for PDF download
		});
	}

	/**
	 * Appointment cancellation.
	 */
	function initAppointmentCancel() {
		$(document).on('click', '.bkx-cancel-appointment', function(e) {
			e.preventDefault();

			if (!confirm('Are you sure you want to cancel this appointment?')) {
				return;
			}

			const bookingId = $(this).data('booking-id');
			const $btn = $(this);

			$btn.prop('disabled', true).text('Cancelling...');

			$.ajax({
				url: bkxLegalFrontend.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_legal_cancel_appointment',
					nonce: bkxLegalFrontend.nonce,
					booking_id: bookingId
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || 'Failed to cancel appointment.');
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
				},
				complete: function() {
					$btn.prop('disabled', false).text('Cancel');
				}
			});
		});
	}

	/**
	 * Initialize on document ready.
	 */
	$(document).ready(function() {
		initPortalTabs();
		initIntakeForm();
		initSignaturePad();
		initClientDocUpload();
		initMessaging();
		initMatterFilter();
		initInvoiceDownload();
		initAppointmentCancel();
	});

})(jQuery);
