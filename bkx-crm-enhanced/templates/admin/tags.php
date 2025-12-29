<?php
/**
 * Tags tab template.
 *
 * @package BookingX\CRM
 */

defined( 'ABSPATH' ) || exit;

$tag_service = new \BookingX\CRM\Services\TagService();
$tags = $tag_service->get_all();
?>

<div class="bkx-crm-tags-page">
	<div class="bkx-two-column">
		<!-- Add Tag Form -->
		<div class="bkx-card">
			<h2><?php esc_html_e( 'Add New Tag', 'bkx-crm' ); ?></h2>

			<form id="bkx-add-tag-form">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="tag-name"><?php esc_html_e( 'Name', 'bkx-crm' ); ?></label>
						</th>
						<td>
							<input type="text" id="tag-name" name="name" class="regular-text" required>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="tag-color"><?php esc_html_e( 'Color', 'bkx-crm' ); ?></label>
						</th>
						<td>
							<input type="color" id="tag-color" name="color" value="#3b82f6">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="tag-description"><?php esc_html_e( 'Description', 'bkx-crm' ); ?></label>
						</th>
						<td>
							<textarea id="tag-description" name="description" rows="3" class="large-text"></textarea>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Add Tag', 'bkx-crm' ); ?>
					</button>
				</p>
			</form>
		</div>

		<!-- Tags List -->
		<div class="bkx-card">
			<h2><?php esc_html_e( 'Tags', 'bkx-crm' ); ?></h2>

			<?php if ( empty( $tags ) ) : ?>
				<p><?php esc_html_e( 'No tags created yet.', 'bkx-crm' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width: 40px;"><?php esc_html_e( 'Color', 'bkx-crm' ); ?></th>
							<th><?php esc_html_e( 'Name', 'bkx-crm' ); ?></th>
							<th style="width: 80px;"><?php esc_html_e( 'Count', 'bkx-crm' ); ?></th>
							<th style="width: 100px;"><?php esc_html_e( 'Actions', 'bkx-crm' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $tags as $tag ) : ?>
							<tr data-tag-id="<?php echo esc_attr( $tag->id ); ?>">
								<td>
									<span class="bkx-color-swatch" style="background-color: <?php echo esc_attr( $tag->color ); ?>;"></span>
								</td>
								<td>
									<strong><?php echo esc_html( $tag->name ); ?></strong>
									<?php if ( $tag->description ) : ?>
										<br><small><?php echo esc_html( $tag->description ); ?></small>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $tag->count ); ?></td>
								<td>
									<button type="button" class="button button-small button-link-delete bkx-delete-tag"
											data-tag-id="<?php echo esc_attr( $tag->id ); ?>">
										<?php esc_html_e( 'Delete', 'bkx-crm' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>
</div>
