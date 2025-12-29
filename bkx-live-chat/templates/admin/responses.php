<?php
/**
 * Canned Responses Template.
 *
 * @package BookingX\LiveChat
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;
$table     = $wpdb->prefix . 'bkx_livechat_responses';
$responses = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY category, title ASC" ); // phpcs:ignore
?>

<div class="wrap bkx-livechat-responses">
	<h1>
		<?php esc_html_e( 'Canned Responses', 'bkx-live-chat' ); ?>
		<button type="button" class="page-title-action" id="bkx-add-response">
			<?php esc_html_e( 'Add New', 'bkx-live-chat' ); ?>
		</button>
	</h1>

	<p class="description">
		<?php esc_html_e( 'Create pre-written responses to quickly reply to common questions. Type / followed by the shortcut in the chat to use them.', 'bkx-live-chat' ); ?>
	</p>

	<table class="wp-list-table widefat fixed striped" id="bkx-responses-table">
		<thead>
			<tr>
				<th style="width: 100px;"><?php esc_html_e( 'Shortcut', 'bkx-live-chat' ); ?></th>
				<th style="width: 200px;"><?php esc_html_e( 'Title', 'bkx-live-chat' ); ?></th>
				<th><?php esc_html_e( 'Content', 'bkx-live-chat' ); ?></th>
				<th style="width: 100px;"><?php esc_html_e( 'Category', 'bkx-live-chat' ); ?></th>
				<th style="width: 80px;"><?php esc_html_e( 'Uses', 'bkx-live-chat' ); ?></th>
				<th style="width: 120px;"><?php esc_html_e( 'Actions', 'bkx-live-chat' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $responses ) ) : ?>
				<tr>
					<td colspan="6"><?php esc_html_e( 'No canned responses found.', 'bkx-live-chat' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $responses as $response ) : ?>
					<tr data-id="<?php echo esc_attr( $response->id ); ?>">
						<td><code>/<?php echo esc_html( $response->shortcut ); ?></code></td>
						<td><?php echo esc_html( $response->title ); ?></td>
						<td><?php echo esc_html( wp_trim_words( $response->content, 15 ) ); ?></td>
						<td><?php echo esc_html( $response->category ?: '-' ); ?></td>
						<td><?php echo esc_html( $response->use_count ); ?></td>
						<td>
							<button type="button" class="button button-small bkx-edit-response" data-id="<?php echo esc_attr( $response->id ); ?>" data-shortcut="<?php echo esc_attr( $response->shortcut ); ?>" data-title="<?php echo esc_attr( $response->title ); ?>" data-content="<?php echo esc_attr( $response->content ); ?>" data-category="<?php echo esc_attr( $response->category ); ?>">
								<?php esc_html_e( 'Edit', 'bkx-live-chat' ); ?>
							</button>
							<button type="button" class="button button-small bkx-delete-response" data-id="<?php echo esc_attr( $response->id ); ?>">
								<?php esc_html_e( 'Delete', 'bkx-live-chat' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>

<!-- Response Modal -->
<div id="bkx-response-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content">
		<span class="bkx-modal-close">&times;</span>
		<h3 id="bkx-response-modal-title"><?php esc_html_e( 'Add Canned Response', 'bkx-live-chat' ); ?></h3>
		<form id="bkx-response-form">
			<input type="hidden" id="bkx-response-id" name="id">
			<p>
				<label for="bkx-response-shortcut"><?php esc_html_e( 'Shortcut', 'bkx-live-chat' ); ?></label>
				<input type="text" id="bkx-response-shortcut" name="shortcut" class="regular-text" required placeholder="hello">
				<span class="description"><?php esc_html_e( 'Type /shortcut in chat to use', 'bkx-live-chat' ); ?></span>
			</p>
			<p>
				<label for="bkx-response-title"><?php esc_html_e( 'Title', 'bkx-live-chat' ); ?></label>
				<input type="text" id="bkx-response-title" name="title" class="regular-text" required placeholder="Greeting">
			</p>
			<p>
				<label for="bkx-response-content"><?php esc_html_e( 'Content', 'bkx-live-chat' ); ?></label>
				<textarea id="bkx-response-content" name="content" class="large-text" rows="5" required placeholder="Hello! How can I help you today?"></textarea>
			</p>
			<p>
				<label for="bkx-response-category"><?php esc_html_e( 'Category', 'bkx-live-chat' ); ?></label>
				<input type="text" id="bkx-response-category" name="category" class="regular-text" placeholder="General">
			</p>
			<p>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'bkx-live-chat' ); ?></button>
			</p>
		</form>
	</div>
</div>
