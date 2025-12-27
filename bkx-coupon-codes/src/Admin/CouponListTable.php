<?php
/**
 * Coupon List Table
 *
 * @package BookingX\CouponCodes\Admin
 * @since   1.0.0
 */

namespace BookingX\CouponCodes\Admin;

use BookingX\CouponCodes\CouponCodesAddon;
use WP_List_Table;

// Load WP_List_Table if not already loaded.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Coupon list table class.
 *
 * @since 1.0.0
 */
class CouponListTable extends WP_List_Table {

	/**
	 * Addon instance.
	 *
	 * @var CouponCodesAddon
	 */
	protected CouponCodesAddon $addon;

	/**
	 * Constructor.
	 *
	 * @param CouponCodesAddon $addon Addon instance.
	 */
	public function __construct( CouponCodesAddon $addon ) {
		$this->addon = $addon;

		parent::__construct(
			array(
				'singular' => 'coupon',
				'plural'   => 'coupons',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Get table columns.
	 *
	 * @return array
	 */
	public function get_columns(): array {
		return array(
			'cb'          => '<input type="checkbox">',
			'code'        => __( 'Code', 'bkx-coupon-codes' ),
			'discount'    => __( 'Discount', 'bkx-coupon-codes' ),
			'usage'       => __( 'Usage', 'bkx-coupon-codes' ),
			'expiry'      => __( 'Expiry', 'bkx-coupon-codes' ),
			'status'      => __( 'Status', 'bkx-coupon-codes' ),
			'created'     => __( 'Created', 'bkx-coupon-codes' ),
		);
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns(): array {
		return array(
			'code'    => array( 'code', false ),
			'usage'   => array( 'usage_count', false ),
			'expiry'  => array( 'end_date', false ),
			'created' => array( 'created_at', true ),
		);
	}

	/**
	 * Prepare items for display.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$per_page     = 20;
		$current_page = $this->get_pagenum();

		$args = array(
			'status'   => isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '',
			'search'   => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'orderby'  => isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'created_at',
			'order'    => isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC',
			'per_page' => $per_page,
			'page'     => $current_page,
		);

		$coupon_service = $this->addon->get_coupon_service();

		$this->items = $coupon_service->get_coupons( $args );
		$total_items = $coupon_service->get_count( $args );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array
	 */
	public function get_bulk_actions(): array {
		return array(
			'activate'   => __( 'Activate', 'bkx-coupon-codes' ),
			'deactivate' => __( 'Deactivate', 'bkx-coupon-codes' ),
			'delete'     => __( 'Delete', 'bkx-coupon-codes' ),
		);
	}

	/**
	 * Get views (status filters).
	 *
	 * @return array
	 */
	public function get_views(): array {
		$coupon_service = $this->addon->get_coupon_service();
		$current_status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';

		$total_count    = $coupon_service->get_count();
		$active_count   = $coupon_service->get_count( array( 'status' => 'active' ) );
		$inactive_count = $coupon_service->get_count( array( 'status' => 'inactive' ) );

		$base_url = admin_url( 'edit.php?post_type=bkx_booking&page=bkx-coupons' );

		$views = array(
			'all' => sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
				esc_url( $base_url ),
				'' === $current_status ? 'current' : '',
				__( 'All', 'bkx-coupon-codes' ),
				$total_count
			),
		);

		if ( $active_count > 0 ) {
			$views['active'] = sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
				esc_url( add_query_arg( 'status', 'active', $base_url ) ),
				'active' === $current_status ? 'current' : '',
				__( 'Active', 'bkx-coupon-codes' ),
				$active_count
			);
		}

		if ( $inactive_count > 0 ) {
			$views['inactive'] = sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
				esc_url( add_query_arg( 'status', 'inactive', $base_url ) ),
				'inactive' === $current_status ? 'current' : '',
				__( 'Inactive', 'bkx-coupon-codes' ),
				$inactive_count
			);
		}

		return $views;
	}

	/**
	 * Render checkbox column.
	 *
	 * @param object $item Coupon item.
	 * @return string
	 */
	public function column_cb( $item ): string {
		return sprintf(
			'<input type="checkbox" name="coupon[]" value="%d">',
			absint( $item->id )
		);
	}

	/**
	 * Render code column.
	 *
	 * @param object $item Coupon item.
	 * @return string
	 */
	public function column_code( $item ): string {
		$edit_url   = admin_url( 'edit.php?post_type=bkx_booking&page=bkx-coupons&action=edit&coupon_id=' . $item->id );
		$delete_url = wp_nonce_url(
			admin_url( 'edit.php?post_type=bkx_booking&page=bkx-coupons&action=delete&coupon_id=' . $item->id ),
			'delete_coupon_' . $item->id
		);

		$actions = array(
			'edit'   => sprintf(
				'<a href="%s">%s</a>',
				esc_url( $edit_url ),
				__( 'Edit', 'bkx-coupon-codes' )
			),
			'delete' => sprintf(
				'<a href="%s" onclick="return confirm(\'%s\')">%s</a>',
				esc_url( $delete_url ),
				esc_js( __( 'Are you sure you want to delete this coupon?', 'bkx-coupon-codes' ) ),
				__( 'Delete', 'bkx-coupon-codes' )
			),
		);

		$output = sprintf(
			'<strong><a href="%s" class="row-title">%s</a></strong>',
			esc_url( $edit_url ),
			esc_html( $item->code )
		);

		if ( ! empty( $item->description ) ) {
			$output .= '<br><span class="description">' . esc_html( wp_trim_words( $item->description, 10 ) ) . '</span>';
		}

		return $output . $this->row_actions( $actions );
	}

	/**
	 * Render discount column.
	 *
	 * @param object $item Coupon item.
	 * @return string
	 */
	public function column_discount( $item ): string {
		switch ( $item->discount_type ) {
			case 'percentage':
				return sprintf( '%s%%', number_format( $item->discount_value, 0 ) );

			case 'fixed':
				return wc_price( $item->discount_value );

			case 'free_service':
				return __( 'Free service', 'bkx-coupon-codes' );

			case 'free_extra':
				return __( 'Free add-on', 'bkx-coupon-codes' );

			default:
				return '-';
		}
	}

	/**
	 * Render usage column.
	 *
	 * @param object $item Coupon item.
	 * @return string
	 */
	public function column_usage( $item ): string {
		$usage = $item->usage_count;
		$limit = $item->usage_limit;

		if ( $limit > 0 ) {
			$percentage = ( $usage / $limit ) * 100;
			$color      = $percentage >= 90 ? 'red' : ( $percentage >= 70 ? 'orange' : 'green' );

			return sprintf(
				'<span style="color: %s">%d / %d</span>',
				esc_attr( $color ),
				$usage,
				$limit
			);
		}

		return sprintf( '%d / &infin;', $usage );
	}

	/**
	 * Render expiry column.
	 *
	 * @param object $item Coupon item.
	 * @return string
	 */
	public function column_expiry( $item ): string {
		if ( empty( $item->end_date ) ) {
			return __( 'Never', 'bkx-coupon-codes' );
		}

		$end_timestamp = strtotime( $item->end_date );
		$now           = time();

		if ( $end_timestamp < $now ) {
			return sprintf(
				'<span style="color: red">%s</span>',
				esc_html__( 'Expired', 'bkx-coupon-codes' )
			);
		}

		$days_until = floor( ( $end_timestamp - $now ) / DAY_IN_SECONDS );

		if ( $days_until <= 3 ) {
			return sprintf(
				'<span style="color: orange">%s</span>',
				esc_html( wp_date( get_option( 'date_format' ), $end_timestamp ) )
			);
		}

		return esc_html( wp_date( get_option( 'date_format' ), $end_timestamp ) );
	}

	/**
	 * Render status column.
	 *
	 * @param object $item Coupon item.
	 * @return string
	 */
	public function column_status( $item ): string {
		if ( $item->is_active ) {
			return sprintf(
				'<span class="bkx-status bkx-status-active">%s</span>',
				esc_html__( 'Active', 'bkx-coupon-codes' )
			);
		}

		return sprintf(
			'<span class="bkx-status bkx-status-inactive">%s</span>',
			esc_html__( 'Inactive', 'bkx-coupon-codes' )
		);
	}

	/**
	 * Render created column.
	 *
	 * @param object $item Coupon item.
	 * @return string
	 */
	public function column_created( $item ): string {
		$timestamp = strtotime( $item->created_at );

		return sprintf(
			'%s<br><small>%s</small>',
			esc_html( wp_date( get_option( 'date_format' ), $timestamp ) ),
			esc_html( wp_date( get_option( 'time_format' ), $timestamp ) )
		);
	}

	/**
	 * Default column rendering.
	 *
	 * @param object $item Coupon item.
	 * @param string $column_name Column name.
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		return isset( $item->$column_name ) ? esc_html( $item->$column_name ) : '-';
	}

	/**
	 * Display when no items found.
	 *
	 * @return void
	 */
	public function no_items(): void {
		esc_html_e( 'No coupons found.', 'bkx-coupon-codes' );
	}

	/**
	 * Extra table navigation (search box).
	 *
	 * @param string $which Top or bottom.
	 * @return void
	 */
	public function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		?>
		<div class="alignleft actions">
			<?php $this->search_box( __( 'Search Coupons', 'bkx-coupon-codes' ), 'coupon' ); ?>
		</div>
		<?php
	}

	/**
	 * Process bulk actions.
	 *
	 * @return void
	 */
	public function process_bulk_action(): void {
		$action = $this->current_action();

		if ( ! $action ) {
			return;
		}

		$coupons = isset( $_GET['coupon'] ) ? array_map( 'absint', $_GET['coupon'] ) : array();

		if ( empty( $coupons ) ) {
			return;
		}

		$coupon_service = $this->addon->get_coupon_service();

		switch ( $action ) {
			case 'activate':
				foreach ( $coupons as $coupon_id ) {
					$coupon_service->update_coupon( $coupon_id, array( 'is_active' => 1 ) );
				}
				break;

			case 'deactivate':
				foreach ( $coupons as $coupon_id ) {
					$coupon_service->update_coupon( $coupon_id, array( 'is_active' => 0 ) );
				}
				break;

			case 'delete':
				foreach ( $coupons as $coupon_id ) {
					$coupon_service->delete_coupon( $coupon_id );
				}
				break;
		}
	}
}
