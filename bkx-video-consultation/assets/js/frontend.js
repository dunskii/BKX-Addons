/**
 * Video Consultation Frontend JavaScript.
 *
 * WebRTC-based video calling implementation.
 *
 * @package BookingX\VideoConsultation
 * @since   1.0.0
 */

(function($) {
	'use strict';

	// State.
	var state = {
		roomId: null,
		peerId: null,
		isHost: false,
		localStream: null,
		remoteStream: null,
		peerConnection: null,
		dataChannel: null,
		isConnected: false,
		isCameraOn: true,
		isMicOn: true,
		isScreenSharing: false,
		isRecording: false,
		mediaRecorder: null,
		recordedChunks: [],
		callStartTime: null,
		timerInterval: null,
		pollInterval: null
	};

	// Elements.
	var els = {};

	/**
	 * Initialize on document ready.
	 */
	$(document).ready(function() {
		var $room = $('#bkx-video-room');
		if ($room.length === 0) return;

		state.roomId = $room.data('room-id');
		state.isHost = $room.data('is-host') === true;

		cacheElements();
		bindEvents();
		initLocalPreview();
	});

	/**
	 * Cache DOM elements.
	 */
	function cacheElements() {
		els = {
			precallScreen: $('#bkx-precall-screen'),
			waitingScreen: $('#bkx-waiting-screen'),
			callScreen: $('#bkx-call-screen'),
			endedScreen: $('#bkx-ended-screen'),
			localPreview: document.getElementById('bkx-local-preview'),
			localVideo: document.getElementById('bkx-local-video'),
			remoteVideo: document.getElementById('bkx-remote-video'),
			screenVideo: document.getElementById('bkx-screen-video'),
			joinForm: $('#bkx-join-form'),
			connectionStatus: $('#bkx-connection-status'),
			callTimer: $('#bkx-call-timer'),
			chatMessages: $('#bkx-chat-messages'),
			chatInput: $('#bkx-chat-input'),
			waitingList: $('#bkx-waiting-list'),
			waitingBadge: $('#bkx-waiting-badge')
		};
	}

	/**
	 * Bind event handlers.
	 */
	function bindEvents() {
		// Preview controls.
		$('#bkx-toggle-camera-preview').on('click', togglePreviewCamera);
		$('#bkx-toggle-mic-preview').on('click', togglePreviewMic);

		// Join form.
		els.joinForm.on('submit', handleJoin);

		// Call controls.
		$('#bkx-toggle-camera').on('click', toggleCamera);
		$('#bkx-toggle-mic').on('click', toggleMic);
		$('#bkx-toggle-screen').on('click', toggleScreenShare);
		$('#bkx-toggle-chat').on('click', toggleChatPanel);
		$('#bkx-toggle-record').on('click', toggleRecording);
		$('#bkx-toggle-waiting-room').on('click', toggleWaitingPanel);
		$('#bkx-end-call').on('click', endCall);

		// Chat.
		$('#bkx-close-chat').on('click', function() {
			$('#bkx-chat-panel').hide();
		});
		$('#bkx-chat-form').on('submit', sendChatMessage);

		// Waiting room.
		$('#bkx-close-waiting-panel').on('click', function() {
			$('#bkx-waiting-room-panel').hide();
		});
		$(document).on('click', '.bkx-admit-btn', admitParticipant);
	}

	/**
	 * Initialize local preview.
	 */
	async function initLocalPreview() {
		try {
			state.localStream = await navigator.mediaDevices.getUserMedia({
				video: true,
				audio: true
			});
			els.localPreview.srcObject = state.localStream;
		} catch (err) {
			console.error('Failed to get local stream:', err);
			updateStatus(bkxVideoConfig.i18n.camera_error, 'error');
		}
	}

	/**
	 * Toggle preview camera.
	 */
	function togglePreviewCamera() {
		if (!state.localStream) return;

		var videoTrack = state.localStream.getVideoTracks()[0];
		if (videoTrack) {
			videoTrack.enabled = !videoTrack.enabled;
			state.isCameraOn = videoTrack.enabled;
			$(this).toggleClass('off', !videoTrack.enabled);
		}
	}

	/**
	 * Toggle preview microphone.
	 */
	function togglePreviewMic() {
		if (!state.localStream) return;

		var audioTrack = state.localStream.getAudioTracks()[0];
		if (audioTrack) {
			audioTrack.enabled = !audioTrack.enabled;
			state.isMicOn = audioTrack.enabled;
			$(this).toggleClass('off', !audioTrack.enabled);
		}
	}

	/**
	 * Handle join.
	 */
	function handleJoin(e) {
		e.preventDefault();

		var name = $('#bkx-participant-name').val();
		var email = $('#bkx-participant-email').val();

		$.ajax({
			url: bkxVideoConfig.ajax_url,
			type: 'POST',
			data: {
				action: 'bkx_join_video_room',
				nonce: bkxVideoConfig.nonce,
				room_id: state.roomId,
				name: name,
				email: email,
				is_host: state.isHost
			},
			success: function(response) {
				if (response.success) {
					if (response.data.status === 'waiting') {
						showWaitingScreen();
						startPolling();
					} else {
						startCall();
					}
				} else {
					alert(response.data || 'Failed to join room.');
				}
			}
		});
	}

	/**
	 * Show waiting screen.
	 */
	function showWaitingScreen() {
		els.precallScreen.hide();
		els.waitingScreen.show();
	}

	/**
	 * Start the call.
	 */
	async function startCall() {
		els.precallScreen.hide();
		els.waitingScreen.hide();
		els.callScreen.show();

		// Move local stream to call video.
		if (state.localStream) {
			els.localVideo.srcObject = state.localStream;
		}

		// Generate peer ID.
		state.peerId = 'peer-' + Math.random().toString(36).substr(2, 9);

		// Join signaling.
		await joinSignaling();

		// Start call timer.
		state.callStartTime = Date.now();
		state.timerInterval = setInterval(updateTimer, 1000);

		// Start polling for signals.
		startPolling();

		// Load waiting room if host.
		if (state.isHost) {
			loadWaitingRoom();
		}
	}

	/**
	 * Join signaling server.
	 */
	async function joinSignaling() {
		try {
			var response = await fetch(bkxVideoConfig.rest_url + 'bkx-video/v1/signal', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json'
				},
				body: JSON.stringify({
					room_id: state.roomId,
					peer_id: state.peerId,
					type: 'join'
				})
			});

			var data = await response.json();

			if (data.success && data.peers && data.peers.length > 0) {
				// Connect to existing peers.
				data.peers.forEach(function(remotePeerId) {
					createPeerConnection(remotePeerId, true);
				});
			}

			updateStatus(bkxVideoConfig.i18n.connected, 'connected');
			state.isConnected = true;
		} catch (err) {
			console.error('Signaling error:', err);
			updateStatus(bkxVideoConfig.i18n.connection_lost, 'error');
		}
	}

	/**
	 * Create peer connection.
	 */
	async function createPeerConnection(remotePeerId, isInitiator) {
		var config = {
			iceServers: bkxVideoConfig.stun_servers.map(function(url) {
				return { urls: url };
			})
		};

		if (bkxVideoConfig.turn_server) {
			config.iceServers.push({
				urls: bkxVideoConfig.turn_server,
				username: bkxVideoConfig.turn_username || '',
				credential: bkxVideoConfig.turn_credential || ''
			});
		}

		state.peerConnection = new RTCPeerConnection(config);

		// Add local tracks.
		if (state.localStream) {
			state.localStream.getTracks().forEach(function(track) {
				state.peerConnection.addTrack(track, state.localStream);
			});
		}

		// Handle remote tracks.
		state.peerConnection.ontrack = function(event) {
			state.remoteStream = event.streams[0];
			els.remoteVideo.srcObject = state.remoteStream;
		};

		// Handle ICE candidates.
		state.peerConnection.onicecandidate = function(event) {
			if (event.candidate) {
				sendSignal(remotePeerId, 'ice-candidate', event.candidate);
			}
		};

		// Handle connection state changes.
		state.peerConnection.onconnectionstatechange = function() {
			switch (state.peerConnection.connectionState) {
				case 'connected':
					updateStatus(bkxVideoConfig.i18n.connected, 'connected');
					break;
				case 'disconnected':
				case 'failed':
					updateStatus(bkxVideoConfig.i18n.connection_lost, 'error');
					break;
			}
		};

		// Create data channel for chat.
		if (bkxVideoConfig.enable_chat) {
			if (isInitiator) {
				state.dataChannel = state.peerConnection.createDataChannel('chat');
				setupDataChannel();
			} else {
				state.peerConnection.ondatachannel = function(event) {
					state.dataChannel = event.channel;
					setupDataChannel();
				};
			}
		}

		// Create and send offer if initiator.
		if (isInitiator) {
			var offer = await state.peerConnection.createOffer();
			await state.peerConnection.setLocalDescription(offer);
			sendSignal(remotePeerId, 'offer', offer);
		}
	}

	/**
	 * Setup data channel.
	 */
	function setupDataChannel() {
		state.dataChannel.onopen = function() {
			console.log('Data channel open');
		};

		state.dataChannel.onmessage = function(event) {
			var message = JSON.parse(event.data);
			displayChatMessage(message.text, message.sender, false);
		};
	}

	/**
	 * Send signal.
	 */
	function sendSignal(targetPeer, type, data) {
		fetch(bkxVideoConfig.rest_url + 'bkx-video/v1/signal', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json'
			},
			body: JSON.stringify({
				room_id: state.roomId,
				peer_id: state.peerId,
				target: targetPeer,
				type: type,
				data: data
			})
		});
	}

	/**
	 * Start polling for signals.
	 */
	function startPolling() {
		state.pollInterval = setInterval(pollSignals, 1000);
	}

	/**
	 * Poll for signals.
	 */
	async function pollSignals() {
		try {
			var response = await fetch(bkxVideoConfig.rest_url + 'bkx-video/v1/signal', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json'
				},
				body: JSON.stringify({
					room_id: state.roomId,
					peer_id: state.peerId,
					type: 'poll'
				})
			});

			var data = await response.json();

			if (data.success && data.signals) {
				data.signals.forEach(handleSignal);
			}
		} catch (err) {
			console.error('Poll error:', err);
		}
	}

	/**
	 * Handle incoming signal.
	 */
	async function handleSignal(signal) {
		switch (signal.signal_type) {
			case 'offer':
				await createPeerConnection(signal.from_peer, false);
				await state.peerConnection.setRemoteDescription(new RTCSessionDescription(signal.signal_data));
				var answer = await state.peerConnection.createAnswer();
				await state.peerConnection.setLocalDescription(answer);
				sendSignal(signal.from_peer, 'answer', answer);
				break;

			case 'answer':
				await state.peerConnection.setRemoteDescription(new RTCSessionDescription(signal.signal_data));
				break;

			case 'ice-candidate':
				await state.peerConnection.addIceCandidate(new RTCIceCandidate(signal.signal_data));
				break;

			case 'peer-left':
				handlePeerLeft();
				break;

			case 'admitted':
				// Participant was admitted from waiting room.
				startCall();
				break;
		}
	}

	/**
	 * Handle peer leaving.
	 */
	function handlePeerLeft() {
		if (state.remoteStream) {
			state.remoteStream.getTracks().forEach(function(track) {
				track.stop();
			});
		}
		els.remoteVideo.srcObject = null;
		updateStatus(bkxVideoConfig.i18n.disconnected, 'error');
	}

	/**
	 * Toggle camera.
	 */
	function toggleCamera() {
		if (!state.localStream) return;

		var videoTrack = state.localStream.getVideoTracks()[0];
		if (videoTrack) {
			videoTrack.enabled = !videoTrack.enabled;
			state.isCameraOn = videoTrack.enabled;
			$(this).toggleClass('off', !videoTrack.enabled);
		}
	}

	/**
	 * Toggle microphone.
	 */
	function toggleMic() {
		if (!state.localStream) return;

		var audioTrack = state.localStream.getAudioTracks()[0];
		if (audioTrack) {
			audioTrack.enabled = !audioTrack.enabled;
			state.isMicOn = audioTrack.enabled;
			$(this).toggleClass('off', !audioTrack.enabled);
		}
	}

	/**
	 * Toggle screen sharing.
	 */
	async function toggleScreenShare() {
		if (!bkxVideoConfig.enable_screen_share) return;

		if (state.isScreenSharing) {
			// Stop screen share.
			stopScreenShare();
		} else {
			// Start screen share.
			try {
				var screenStream = await navigator.mediaDevices.getDisplayMedia({
					video: true
				});

				var screenTrack = screenStream.getVideoTracks()[0];

				// Replace video track.
				var sender = state.peerConnection.getSenders().find(function(s) {
					return s.track && s.track.kind === 'video';
				});

				if (sender) {
					sender.replaceTrack(screenTrack);
				}

				// Show screen share view.
				$('#bkx-screen-share').show();
				els.screenVideo.srcObject = screenStream;

				screenTrack.onended = stopScreenShare;

				state.isScreenSharing = true;
				$('#bkx-toggle-screen').addClass('active');
			} catch (err) {
				console.error('Screen share error:', err);
			}
		}
	}

	/**
	 * Stop screen sharing.
	 */
	function stopScreenShare() {
		if (!state.isScreenSharing) return;

		// Replace with camera track.
		var videoTrack = state.localStream.getVideoTracks()[0];
		var sender = state.peerConnection.getSenders().find(function(s) {
			return s.track && s.track.kind === 'video';
		});

		if (sender && videoTrack) {
			sender.replaceTrack(videoTrack);
		}

		$('#bkx-screen-share').hide();
		state.isScreenSharing = false;
		$('#bkx-toggle-screen').removeClass('active');
	}

	/**
	 * Toggle chat panel.
	 */
	function toggleChatPanel() {
		$('#bkx-chat-panel').toggle();
		$(this).toggleClass('active');
	}

	/**
	 * Toggle waiting room panel.
	 */
	function toggleWaitingPanel() {
		$('#bkx-waiting-room-panel').toggle();
		$(this).toggleClass('active');
		loadWaitingRoom();
	}

	/**
	 * Toggle recording.
	 */
	function toggleRecording() {
		if (state.isRecording) {
			stopRecording();
		} else {
			startRecording();
		}
	}

	/**
	 * Start recording.
	 */
	function startRecording() {
		if (!state.localStream) return;

		var options = { mimeType: 'video/webm;codecs=vp9' };
		if (!MediaRecorder.isTypeSupported(options.mimeType)) {
			options = { mimeType: 'video/webm' };
		}

		state.recordedChunks = [];
		state.mediaRecorder = new MediaRecorder(state.localStream, options);

		state.mediaRecorder.ondataavailable = function(event) {
			if (event.data.size > 0) {
				state.recordedChunks.push(event.data);
			}
		};

		state.mediaRecorder.onstop = saveRecording;

		state.mediaRecorder.start(1000);
		state.isRecording = true;
		$('#bkx-toggle-record').addClass('active');
	}

	/**
	 * Stop recording.
	 */
	function stopRecording() {
		if (state.mediaRecorder && state.isRecording) {
			state.mediaRecorder.stop();
			state.isRecording = false;
			$('#bkx-toggle-record').removeClass('active');
		}
	}

	/**
	 * Save recording.
	 */
	function saveRecording() {
		var blob = new Blob(state.recordedChunks, { type: 'video/webm' });
		var formData = new FormData();
		formData.append('action', 'bkx_save_video_recording');
		formData.append('nonce', bkxVideoConfig.nonce);
		formData.append('room_id', state.roomId);
		formData.append('recording', blob, 'recording.webm');

		$.ajax({
			url: bkxVideoConfig.ajax_url,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function(response) {
				console.log('Recording saved:', response);
			}
		});
	}

	/**
	 * Send chat message.
	 */
	function sendChatMessage(e) {
		e.preventDefault();

		var text = els.chatInput.val().trim();
		if (!text || !state.dataChannel) return;

		var message = {
			text: text,
			sender: 'Me',
			timestamp: Date.now()
		};

		state.dataChannel.send(JSON.stringify(message));
		displayChatMessage(text, 'Me', true);
		els.chatInput.val('');
	}

	/**
	 * Display chat message.
	 */
	function displayChatMessage(text, sender, isSent) {
		var time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
		var html = '<div class="bkx-chat-message ' + (isSent ? 'sent' : 'received') + '">';
		html += '<div class="message-bubble">' + escapeHtml(text) + '</div>';
		html += '<div class="message-time">' + sender + ' - ' + time + '</div>';
		html += '</div>';

		els.chatMessages.append(html);
		els.chatMessages.scrollTop(els.chatMessages[0].scrollHeight);
	}

	/**
	 * Load waiting room.
	 */
	function loadWaitingRoom() {
		if (!state.isHost) return;

		$.ajax({
			url: bkxVideoConfig.ajax_url,
			type: 'POST',
			data: {
				action: 'bkx_get_video_room_status',
				nonce: bkxVideoConfig.nonce,
				room_id: state.roomId
			},
			success: function(response) {
				if (response.success && response.data) {
					renderWaitingRoom(response.data.waiting_room);
				}
			}
		});
	}

	/**
	 * Render waiting room list.
	 */
	function renderWaitingRoom(participants) {
		var $list = els.waitingList;
		$list.empty();

		if (!participants || participants.length === 0) {
			$list.html('<p class="bkx-no-waiting">' + bkxVideoConfig.i18n.no_waiting + '</p>');
			els.waitingBadge.hide();
			return;
		}

		els.waitingBadge.text(participants.length).show();

		participants.forEach(function(p) {
			var html = '<div class="bkx-waiting-item">';
			html += '<div>';
			html += '<div class="bkx-waiting-name">' + escapeHtml(p.participant_name) + '</div>';
			html += '<div class="bkx-waiting-time">' + p.created_at + '</div>';
			html += '</div>';
			html += '<button class="bkx-admit-btn" data-id="' + p.id + '">Admit</button>';
			html += '</div>';

			$list.append(html);
		});
	}

	/**
	 * Admit participant.
	 */
	function admitParticipant() {
		var participantId = $(this).data('id');

		$.ajax({
			url: bkxVideoConfig.ajax_url,
			type: 'POST',
			data: {
				action: 'bkx_admit_participant',
				nonce: bkxVideoConfig.nonce,
				participant_id: participantId
			},
			success: function(response) {
				if (response.success) {
					loadWaitingRoom();
				}
			}
		});
	}

	/**
	 * End call.
	 */
	function endCall() {
		if (!confirm('Are you sure you want to end this call?')) {
			return;
		}

		// Stop recording if active.
		if (state.isRecording) {
			stopRecording();
		}

		// Stop polling.
		if (state.pollInterval) {
			clearInterval(state.pollInterval);
		}

		// Stop timer.
		if (state.timerInterval) {
			clearInterval(state.timerInterval);
		}

		// Send leave signal.
		fetch(bkxVideoConfig.rest_url + 'bkx-video/v1/signal', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json'
			},
			body: JSON.stringify({
				room_id: state.roomId,
				peer_id: state.peerId,
				type: 'leave'
			})
		});

		// Close peer connection.
		if (state.peerConnection) {
			state.peerConnection.close();
		}

		// Stop local stream.
		if (state.localStream) {
			state.localStream.getTracks().forEach(function(track) {
				track.stop();
			});
		}

		// Show ended screen.
		els.callScreen.hide();
		els.endedScreen.show();

		// Display call summary.
		var duration = Math.round((Date.now() - state.callStartTime) / 1000);
		$('#bkx-call-summary').html('<p><strong>Duration:</strong> ' + formatTime(duration) + '</p>');
	}

	/**
	 * Update connection status.
	 */
	function updateStatus(text, className) {
		els.connectionStatus.text(text).removeClass('connected error').addClass(className);
	}

	/**
	 * Update call timer.
	 */
	function updateTimer() {
		var elapsed = Math.round((Date.now() - state.callStartTime) / 1000);
		els.callTimer.text(formatTime(elapsed));
	}

	/**
	 * Format time as MM:SS or HH:MM:SS.
	 */
	function formatTime(seconds) {
		var hours = Math.floor(seconds / 3600);
		var minutes = Math.floor((seconds % 3600) / 60);
		var secs = seconds % 60;

		if (hours > 0) {
			return pad(hours) + ':' + pad(minutes) + ':' + pad(secs);
		}
		return pad(minutes) + ':' + pad(secs);
	}

	/**
	 * Pad number with leading zero.
	 */
	function pad(num) {
		return String(num).padStart(2, '0');
	}

	/**
	 * Escape HTML.
	 */
	function escapeHtml(text) {
		var div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

})(jQuery);
