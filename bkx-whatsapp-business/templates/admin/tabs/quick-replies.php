<?php
/**
 * Quick Replies Tab Template.
 *
 * @package BookingX\WhatsAppBusiness
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;
$table         = $wpdb->prefix . 'bkx_whatsapp_quick_replies';
$quick_replies = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY title ASC" ); // phpcs:ignore
?>

<div class="bkx-quick-replies-container">
	<div class="bkx-quick-replies-header">
		<h2><?php esc_html_e( 'Quick Replies', 'bkx-whatsapp-business' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Create pre-written responses for common questions. Use shortcuts like /greeting in the chat.', 'bkx-whatsapp-business' ); ?>
		</p>
		<button type="button" class="button button-primary" id="bkx-add-quick-reply">
			<span class="dashicons dashicons-plus-alt"></span>
			<?php esc_html_e( 'Add Quick Reply', 'bkx-whatsapp-business' ); ?>
		</button>
	</div>

	<table class="wp-list-table widefat fixed striped" id="bkx-quick-replies-table">
		<thead>
			<tr>
				<th style="width: 100px;"><?php esc_html_e( 'Shortcut', 'bkx-whatsapp-business' ); ?></th>
				<th style="width: 200px;"><?php esc_html_e( 'Title', 'bkx-whatsapp-business' ); ?></th>
				<th><?php esc_html_e( 'Content', 'bkx-whatsapp-business' ); ?></th>
				<th style="width: 100px;"><?php esc_html_e( 'Category', 'bkx-whatsapp-business' ); ?></th>
				<th style="width: 80px;"><?php esc_html_e( 'Uses', 'bkx-whatsapp-business' ); ?></th>
				<th style="width: 120px;"><?php esc_html_e( 'Actions', 'bkx-whatsapp-business' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $quick_replies ) ) : ?>
				<tr class="bkx-no-items">
					<td colspan="6"><?php esc_html_e( 'No quick replies found. Click "Add Quick Reply" to create one.', 'bkx-whatsapp-business' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $quick_replies as $reply ) : ?>
					<tr data-id="<?php echo esc_attr( $reply->id ); ?>">
						<td><code>/<?php echo esc_html( $reply->shortcut ); ?></code></td>
						<td><?php echo esc_html( $reply->title ); ?></td>
						<td><?php echo esc_html( wp_trim_words( $reply->content, 15 ) ); ?></td>
						<td><?php echo esc_html( $reply->category ?: '-' ); ?></td>
						<td><?php echo esc_html( $reply->use_count ); ?></td>
						<td>
							<button type="button" class="button button-small bkx-edit-quick-reply" data-id="<?php echo esc_attr( $reply->id ); ?>">
								<?php esc_html_e( 'Edit', 'bkx-whatsapp-business' ); ?>
							</button>
							<button type="button" class="button button-small bkx-delete-quick-reply" data-id="<?php echo esc_attr( $reply->id ); ?>">
								<?php esc_html_e( 'Delete', 'bkx-whatsapp-business' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>

<!-- Quick Reply Modal -->
<div id="bkx-quick-reply-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content">
		<span class="bkx-modal-close">&times;</span>
		<h3 id="bkx-quick-reply-modal-title"><?php esc_html_e( 'Add Quick Reply', 'bkx-whatsapp-business' ); ?></h3>
		<form id="bkx-quick-reply-form">
			<input type="hidden" id="bkx-quick-reply-id" name="id">
			<p>
				<label for="bkx-quick-reply-shortcut"><?php esc_html_e( 'Shortcut', 'bkx-whatsapp-business' ); ?></label>
				<input type="text" id="bkx-quick-reply-shortcut" name="shortcut" class="regular-text" required placeholder="greeting">
				<span class="description"><?php esc_html_e( 'Use in chat as /shortcut', 'bkx-whatsapp-business' ); ?></span>
			</p>
			<p>
				<label for="bkx-quick-reply-title"><?php esc_html_e( 'Title', 'bkx-whatsapp-business' ); ?></label>
				<input type="text" id="bkx-quick-reply-title" name="title" class="regular-text" required placeholder="Greeting Message">
			</p>
			<p>
				<label for="bkx-quick-reply-content"><?php esc_html_e( 'Content', 'bkx-whatsapp-business' ); ?></label>
				<textarea id="bkx-quick-reply-content" name="content" class="large-text" rows="5" required placeholder="Hello! How can I help you today?"></textarea>
			</p>
			<p>
				<label for="bkx-quick-reply-category"><?php esc_html_e( 'Category (optional)', 'bkx-whatsapp-business' ); ?></label>
				<input type="text" id="bkx-quick-reply-category" name="category" class="regular-text" placeholder="General">
			</p>
			<p>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'bkx-whatsapp-business' ); ?></button>
			</p>
		</form>
	</div>
</div>
