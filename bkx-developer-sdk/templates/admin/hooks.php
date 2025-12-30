<?php
/**
 * Hook Inspector template.
 *
 * @package BookingX\DeveloperSDK
 */

defined( 'ABSPATH' ) || exit;

$addon     = \BookingX\DeveloperSDK\DeveloperSDKAddon::get_instance();
$inspector = $addon->get_service( 'hook_inspector' );

$all_hooks  = $inspector->get_all_hooks();
$categories = array_keys( $all_hooks );

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$current_category = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '';
$search           = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
// phpcs:enable

$filtered_hooks = $inspector->get_hooks( $current_category, $search );
?>

<div class="bkx-hook-inspector">
	<div class="bkx-hooks-header">
		<div class="bkx-hooks-filters">
			<form method="get">
				<input type="hidden" name="post_type" value="bkx_booking">
				<input type="hidden" name="page" value="bkx-developer-sdk">
				<input type="hidden" name="tab" value="hooks">

				<select name="category">
					<option value=""><?php esc_html_e( 'All Categories', 'bkx-developer-sdk' ); ?></option>
					<?php foreach ( $categories as $category ) : ?>
						<option value="<?php echo esc_attr( $category ); ?>" <?php selected( $current_category, $category ); ?>>
							<?php echo esc_html( ucfirst( $category ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<input type="search" name="search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search hooks...', 'bkx-developer-sdk' ); ?>">

				<button type="submit" class="button"><?php esc_html_e( 'Filter', 'bkx-developer-sdk' ); ?></button>
			</form>
		</div>

		<div class="bkx-hooks-stats">
			<?php
			$total_hooks = 0;
			foreach ( $all_hooks as $hooks ) {
				$total_hooks += count( $hooks );
			}
			?>
			<span class="bkx-stat">
				<?php
				printf(
					/* translators: %d: number of hooks */
					esc_html__( '%d hooks available', 'bkx-developer-sdk' ),
					$total_hooks
				);
				?>
			</span>
		</div>
	</div>

	<?php foreach ( $filtered_hooks as $category => $hooks ) : ?>
		<?php if ( empty( $hooks ) ) continue; ?>
		<div class="bkx-hook-category">
			<h3>
				<span class="dashicons dashicons-tag"></span>
				<?php echo esc_html( ucfirst( $category ) ); ?>
				<span class="bkx-hook-count">(<?php echo esc_html( count( $hooks ) ); ?>)</span>
			</h3>

			<div class="bkx-hooks-list">
				<?php foreach ( $hooks as $hook ) : ?>
					<div class="bkx-hook-item">
						<div class="bkx-hook-header">
							<code class="bkx-hook-name"><?php echo esc_html( $hook['name'] ); ?></code>
							<span class="bkx-hook-type bkx-hook-type-<?php echo esc_attr( $hook['type'] ); ?>">
								<?php echo esc_html( $hook['type'] ); ?>
							</span>
							<?php if ( ! empty( $hook['since'] ) ) : ?>
								<span class="bkx-hook-since">
									<?php
									printf(
										/* translators: %s: version number */
										esc_html__( 'Since %s', 'bkx-developer-sdk' ),
										esc_html( $hook['since'] )
									);
									?>
								</span>
							<?php endif; ?>
						</div>

						<p class="bkx-hook-description"><?php echo esc_html( $hook['description'] ); ?></p>

						<?php if ( ! empty( $hook['params'] ) ) : ?>
							<div class="bkx-hook-params">
								<h4><?php esc_html_e( 'Parameters', 'bkx-developer-sdk' ); ?></h4>
								<ul>
									<?php foreach ( $hook['params'] as $param ) : ?>
										<li>
											<code><?php echo esc_html( $param['name'] ); ?></code>
											<span class="bkx-param-type">(<?php echo esc_html( $param['type'] ); ?>)</span>
											- <?php echo esc_html( $param['desc'] ); ?>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $hook['return'] ) ) : ?>
							<div class="bkx-hook-return">
								<h4><?php esc_html_e( 'Returns', 'bkx-developer-sdk' ); ?></h4>
								<code><?php echo esc_html( $hook['return'] ); ?></code>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $hook['example'] ) ) : ?>
							<div class="bkx-hook-example">
								<h4><?php esc_html_e( 'Example', 'bkx-developer-sdk' ); ?></h4>
								<pre><code class="language-php"><?php echo esc_html( $hook['example'] ); ?></code></pre>
							</div>
						<?php endif; ?>

						<div class="bkx-hook-actions">
							<button type="button" class="button button-small bkx-copy-hook" data-hook="<?php echo esc_attr( $hook['name'] ); ?>" data-type="<?php echo esc_attr( $hook['type'] ); ?>">
								<span class="dashicons dashicons-clipboard"></span>
								<?php esc_html_e( 'Copy Usage', 'bkx-developer-sdk' ); ?>
							</button>
							<button type="button" class="button button-small bkx-generate-listener" data-hook="<?php echo esc_attr( $hook['name'] ); ?>">
								<span class="dashicons dashicons-editor-code"></span>
								<?php esc_html_e( 'Generate Listener', 'bkx-developer-sdk' ); ?>
							</button>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endforeach; ?>
</div>
