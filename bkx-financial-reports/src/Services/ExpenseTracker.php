<?php
/**
 * Expense Tracker Service.
 *
 * @package BookingX\FinancialReports\Services
 * @since   1.0.0
 */

namespace BookingX\FinancialReports\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ExpenseTracker Class.
 */
class ExpenseTracker {

	/**
	 * Default expense categories.
	 *
	 * @var array
	 */
	private $default_categories = array(
		'Staff',
		'Rent',
		'Utilities',
		'Supplies',
		'Marketing',
		'Equipment',
		'Insurance',
		'Software',
		'Professional Services',
		'Travel',
		'Miscellaneous',
	);

	/**
	 * Get expenses.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @param string $category   Filter by category.
	 * @return array Expenses data.
	 */
	public function get_expenses( $start_date = '', $end_date = '', $category = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_financial_expenses';

		$where = array( '1=1' );
		$args  = array();

		if ( $start_date ) {
			$where[] = 'expense_date >= %s';
			$args[]  = $start_date;
		}

		if ( $end_date ) {
			$where[] = 'expense_date <= %s';
			$args[]  = $end_date;
		}

		if ( $category ) {
			$where[] = 'category = %s';
			$args[]  = $category;
		}

		$where_clause = implode( ' AND ', $where );

		// Build query with table identifier.
		if ( ! empty( $args ) ) {
			// Add table name to args.
			array_unshift( $args, $table );

			$expenses = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM %i WHERE {$where_clause} ORDER BY expense_date DESC",
					...$args
				),
				ARRAY_A
			);
		} else {
			$expenses = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM %i ORDER BY expense_date DESC",
					$table
				),
				ARRAY_A
			);
		}

		// Get category totals.
		if ( ! empty( $args ) ) {
			$category_totals = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT category, SUM(amount) as total, COUNT(*) as count
					FROM %i WHERE {$where_clause}
					GROUP BY category ORDER BY total DESC",
					...$args
				),
				ARRAY_A
			);
		} else {
			$category_totals = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT category, SUM(amount) as total, COUNT(*) as count
					FROM %i GROUP BY category ORDER BY total DESC",
					$table
				),
				ARRAY_A
			);
		}

		return array(
			'expenses'        => $expenses,
			'category_totals' => $category_totals,
			'grand_total'     => array_sum( array_column( $expenses, 'amount' ) ),
			'categories'      => $this->get_categories(),
		);
	}

	/**
	 * Save expense.
	 *
	 * @param array $data Expense data.
	 * @return int|false Expense ID or false.
	 */
	public function save_expense( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_financial_expenses';

		$expense_data = array(
			'expense_date'        => $data['expense_date'],
			'category'            => $data['category'],
			'description'         => $data['description'],
			'amount'              => $data['amount'],
			'payment_method'      => $data['payment_method'] ?? null,
			'vendor'              => $data['vendor'] ?? null,
			'notes'               => $data['notes'] ?? null,
			'is_recurring'        => $data['is_recurring'] ?? 0,
			'recurring_frequency' => $data['recurring_frequency'] ?? null,
			'created_by'          => get_current_user_id(),
		);

		$format = array( '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%d', '%s', '%d' );

		if ( ! empty( $data['id'] ) ) {
			// Update.
			$expense_data['updated_at'] = current_time( 'mysql' );
			$result = $wpdb->update(
				$table,
				$expense_data,
				array( 'id' => $data['id'] ),
				$format,
				array( '%d' )
			);

			return $result !== false ? $data['id'] : false;
		} else {
			// Insert.
			$result = $wpdb->insert( $table, $expense_data, $format );

			return $result ? $wpdb->insert_id : false;
		}
	}

	/**
	 * Delete expense.
	 *
	 * @param int $expense_id Expense ID.
	 * @return bool Success.
	 */
	public function delete_expense( $expense_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_financial_expenses';

		return $wpdb->delete(
			$table,
			array( 'id' => $expense_id ),
			array( '%d' )
		) !== false;
	}

	/**
	 * Get single expense.
	 *
	 * @param int $expense_id Expense ID.
	 * @return array|null Expense data.
	 */
	public function get_expense( $expense_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_financial_expenses';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE id = %d",
				$table,
				$expense_id
			),
			ARRAY_A
		);
	}

	/**
	 * Get expense categories.
	 *
	 * @return array Categories.
	 */
	public function get_categories() {
		$custom = get_option( 'bkx_fin_expense_categories', array() );

		if ( ! empty( $custom ) ) {
			return array_merge( $this->default_categories, $custom );
		}

		return $this->default_categories;
	}

	/**
	 * Get recurring expenses.
	 *
	 * @return array Recurring expenses.
	 */
	public function get_recurring_expenses() {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_financial_expenses';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE is_recurring = 1 ORDER BY expense_date DESC",
				$table
			),
			ARRAY_A
		);
	}

	/**
	 * Process recurring expenses.
	 */
	public function process_recurring() {
		$recurring = $this->get_recurring_expenses();
		$today     = gmdate( 'Y-m-d' );

		foreach ( $recurring as $expense ) {
			$last_date = $expense['expense_date'];
			$frequency = $expense['recurring_frequency'];

			// Calculate next due date.
			$next_date = $this->calculate_next_date( $last_date, $frequency );

			if ( $next_date <= $today ) {
				// Create new expense entry.
				$new_expense = array(
					'expense_date'        => $next_date,
					'category'            => $expense['category'],
					'description'         => $expense['description'] . ' (Recurring)',
					'amount'              => $expense['amount'],
					'payment_method'      => $expense['payment_method'],
					'vendor'              => $expense['vendor'],
					'notes'               => $expense['notes'],
					'is_recurring'        => 0, // Non-recurring copy.
				);

				$this->save_expense( $new_expense );

				// Update original expense date.
				global $wpdb;
				$table = $wpdb->prefix . 'bkx_financial_expenses';
				$wpdb->update(
					$table,
					array( 'expense_date' => $next_date ),
					array( 'id' => $expense['id'] ),
					array( '%s' ),
					array( '%d' )
				);
			}
		}
	}

	/**
	 * Calculate next recurring date.
	 *
	 * @param string $last_date Last date.
	 * @param string $frequency Frequency.
	 * @return string Next date.
	 */
	private function calculate_next_date( $last_date, $frequency ) {
		$date = new \DateTime( $last_date );

		switch ( $frequency ) {
			case 'weekly':
				$date->modify( '+1 week' );
				break;

			case 'bi-weekly':
				$date->modify( '+2 weeks' );
				break;

			case 'monthly':
				$date->modify( '+1 month' );
				break;

			case 'quarterly':
				$date->modify( '+3 months' );
				break;

			case 'yearly':
				$date->modify( '+1 year' );
				break;
		}

		return $date->format( 'Y-m-d' );
	}

	/**
	 * Get expense summary by category.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array Summary.
	 */
	public function get_category_summary( $start_date, $end_date ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_financial_expenses';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					category,
					SUM(amount) as total,
					COUNT(*) as count,
					AVG(amount) as average
				FROM %i
				WHERE expense_date BETWEEN %s AND %s
				GROUP BY category
				ORDER BY total DESC",
				$table,
				$start_date,
				$end_date
			),
			ARRAY_A
		);
	}
}
