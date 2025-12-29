<?php
/**
 * Discord API Service.
 *
 * @package BookingX\Discord
 */

namespace BookingX\Discord\Services;

defined( 'ABSPATH' ) || exit;

/**
 * DiscordApi class.
 */
class DiscordApi {

	/**
	 * Discord API base URL.
	 *
	 * @var string
	 */
	private const API_BASE = 'https://discord.com/api/v10';

	/**
	 * Send webhook message.
	 *
	 * @param string $webhook_url Webhook URL.
	 * @param array  $payload     Message payload.
	 * @return array|WP_Error
	 */
	public function send_webhook( $webhook_url, $payload ) {
		$response = wp_remote_post(
			$webhook_url . '?wait=true',
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code >= 400 ) {
			$message = $data['message'] ?? 'Unknown error';
			return new \WP_Error( 'discord_api_error', $message, array( 'code' => $code ) );
		}

		return $data;
	}

	/**
	 * Get webhook info.
	 *
	 * @param string $webhook_url Webhook URL.
	 * @return array|WP_Error
	 */
	public function get_webhook_info( $webhook_url ) {
		$response = wp_remote_get(
			$webhook_url,
			array(
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( 200 !== $code ) {
			return new \WP_Error( 'discord_api_error', $data['message'] ?? 'Invalid webhook' );
		}

		return $data;
	}

	/**
	 * Verify interaction signature.
	 *
	 * @param string $public_key Public key.
	 * @param string $signature  X-Signature-Ed25519 header.
	 * @param string $timestamp  X-Signature-Timestamp header.
	 * @param string $body       Request body.
	 * @return bool
	 */
	public function verify_interaction( $public_key, $signature, $timestamp, $body ) {
		if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
			return false;
		}

		try {
			$message = $timestamp . $body;
			return sodium_crypto_sign_verify_detached(
				sodium_hex2bin( $signature ),
				$message,
				sodium_hex2bin( $public_key )
			);
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Create interaction response.
	 *
	 * @param int   $type Response type.
	 * @param array $data Response data.
	 * @return array
	 */
	public function create_interaction_response( $type, $data = array() ) {
		$response = array(
			'type' => $type,
		);

		if ( ! empty( $data ) ) {
			$response['data'] = $data;
		}

		return $response;
	}

	/**
	 * Build embed.
	 *
	 * @param array $options Embed options.
	 * @return array
	 */
	public function build_embed( $options ) {
		$embed = array(
			'title'       => $options['title'] ?? '',
			'description' => $options['description'] ?? '',
			'color'       => $this->hex_to_int( $options['color'] ?? '#5865F2' ),
			'timestamp'   => gmdate( 'c' ),
		);

		if ( ! empty( $options['fields'] ) ) {
			$embed['fields'] = $options['fields'];
		}

		if ( ! empty( $options['footer'] ) ) {
			$embed['footer'] = array(
				'text' => $options['footer'],
			);
		}

		if ( ! empty( $options['thumbnail'] ) ) {
			$embed['thumbnail'] = array(
				'url' => $options['thumbnail'],
			);
		}

		if ( ! empty( $options['url'] ) ) {
			$embed['url'] = $options['url'];
		}

		if ( ! empty( $options['author'] ) ) {
			$embed['author'] = $options['author'];
		}

		return $embed;
	}

	/**
	 * Convert hex color to integer.
	 *
	 * @param string $hex Hex color.
	 * @return int
	 */
	private function hex_to_int( $hex ) {
		$hex = ltrim( $hex, '#' );
		return hexdec( $hex );
	}

	/**
	 * Build message components (buttons).
	 *
	 * @param array $buttons Buttons configuration.
	 * @return array
	 */
	public function build_components( $buttons ) {
		$components = array();

		foreach ( $buttons as $button ) {
			$components[] = array(
				'type'      => 2, // Button.
				'style'     => $button['style'] ?? 1,
				'label'     => $button['label'],
				'custom_id' => $button['custom_id'] ?? null,
				'url'       => $button['url'] ?? null,
				'emoji'     => $button['emoji'] ?? null,
				'disabled'  => $button['disabled'] ?? false,
			);
		}

		return array(
			array(
				'type'       => 1, // Action Row.
				'components' => $components,
			),
		);
	}

	/**
	 * Send typing indicator.
	 *
	 * @param string $channel_id Channel ID.
	 * @param string $bot_token  Bot token.
	 * @return bool
	 */
	public function send_typing( $channel_id, $bot_token ) {
		$response = wp_remote_post(
			self::API_BASE . '/channels/' . $channel_id . '/typing',
			array(
				'headers' => array(
					'Authorization' => 'Bot ' . $bot_token,
				),
				'timeout' => 10,
			)
		);

		return ! is_wp_error( $response );
	}

	/**
	 * Create message in channel (bot only).
	 *
	 * @param string $channel_id Channel ID.
	 * @param array  $payload    Message payload.
	 * @param string $bot_token  Bot token.
	 * @return array|WP_Error
	 */
	public function create_message( $channel_id, $payload, $bot_token ) {
		$response = wp_remote_post(
			self::API_BASE . '/channels/' . $channel_id . '/messages',
			array(
				'headers' => array(
					'Authorization' => 'Bot ' . $bot_token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code >= 400 ) {
			return new \WP_Error( 'discord_api_error', $data['message'] ?? 'Unknown error' );
		}

		return $data;
	}

	/**
	 * Get guild channels.
	 *
	 * @param string $guild_id  Guild ID.
	 * @param string $bot_token Bot token.
	 * @return array|WP_Error
	 */
	public function get_guild_channels( $guild_id, $bot_token ) {
		$response = wp_remote_get(
			self::API_BASE . '/guilds/' . $guild_id . '/channels',
			array(
				'headers' => array(
					'Authorization' => 'Bot ' . $bot_token,
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		return json_decode( $body, true );
	}

	/**
	 * Get guild roles.
	 *
	 * @param string $guild_id  Guild ID.
	 * @param string $bot_token Bot token.
	 * @return array|WP_Error
	 */
	public function get_guild_roles( $guild_id, $bot_token ) {
		$response = wp_remote_get(
			self::API_BASE . '/guilds/' . $guild_id . '/roles',
			array(
				'headers' => array(
					'Authorization' => 'Bot ' . $bot_token,
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		return json_decode( $body, true );
	}
}
