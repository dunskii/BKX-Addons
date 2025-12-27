<?php
/**
 * Cron Trait
 *
 * Provides cron job management functionality for add-ons.
 *
 * @package    BookingX\AddonSDK
 * @subpackage Traits
 * @since      1.0.0
 */

namespace BookingX\AddonSDK\Traits;

/**
 * Trait for cron job management.
 *
 * @since 1.0.0
 */
trait HasCron {

    /**
     * Registered cron jobs.
     *
     * @var array
     */
    protected array $cron_jobs = [];

    /**
     * Register a cron job.
     *
     * @since 1.0.0
     * @param string   $hook       Hook name.
     * @param string   $recurrence Recurrence (hourly, twicedaily, daily, weekly).
     * @param callable $callback   Callback function.
     * @param array    $args       Arguments to pass to callback.
     * @return void
     */
    protected function register_cron_job( string $hook, string $recurrence, callable $callback, array $args = [] ): void {
        $full_hook = $this->get_cron_hook_name( $hook );

        $this->cron_jobs[ $hook ] = [
            'hook'       => $full_hook,
            'recurrence' => $recurrence,
            'callback'   => $callback,
            'args'       => $args,
        ];

        // Register the action
        add_action( $full_hook, $callback );
    }

    /**
     * Schedule all registered cron jobs.
     *
     * @since 1.0.0
     * @return void
     */
    protected function schedule_cron_jobs(): void {
        foreach ( $this->cron_jobs as $job ) {
            if ( ! wp_next_scheduled( $job['hook'], $job['args'] ) ) {
                wp_schedule_event( time(), $job['recurrence'], $job['hook'], $job['args'] );
            }
        }
    }

    /**
     * Unschedule all registered cron jobs.
     *
     * @since 1.0.0
     * @return void
     */
    protected function unschedule_cron_jobs(): void {
        foreach ( $this->cron_jobs as $job ) {
            $timestamp = wp_next_scheduled( $job['hook'], $job['args'] );
            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, $job['hook'], $job['args'] );
            }
        }
    }

    /**
     * Clear all scheduled events for a hook.
     *
     * @since 1.0.0
     * @param string $hook Hook name (without addon prefix).
     * @return int Number of events cleared.
     */
    protected function clear_cron_hook( string $hook ): int {
        $full_hook = $this->get_cron_hook_name( $hook );
        return wp_unschedule_hook( $full_hook );
    }

    /**
     * Schedule a single event.
     *
     * @since 1.0.0
     * @param string $hook      Hook name.
     * @param int    $timestamp Unix timestamp when to run.
     * @param array  $args      Arguments to pass to callback.
     * @return bool
     */
    protected function schedule_single_event( string $hook, int $timestamp, array $args = [] ): bool {
        $full_hook = $this->get_cron_hook_name( $hook );

        return wp_schedule_single_event( $timestamp, $full_hook, $args );
    }

    /**
     * Schedule a delayed event.
     *
     * @since 1.0.0
     * @param string $hook  Hook name.
     * @param int    $delay Delay in seconds.
     * @param array  $args  Arguments to pass to callback.
     * @return bool
     */
    protected function schedule_delayed_event( string $hook, int $delay, array $args = [] ): bool {
        return $this->schedule_single_event( $hook, time() + $delay, $args );
    }

    /**
     * Check if a cron job is scheduled.
     *
     * @since 1.0.0
     * @param string $hook Hook name (without addon prefix).
     * @param array  $args Arguments (optional).
     * @return int|false Timestamp of next run or false.
     */
    protected function is_cron_scheduled( string $hook, array $args = [] ) {
        $full_hook = $this->get_cron_hook_name( $hook );
        return wp_next_scheduled( $full_hook, $args );
    }

    /**
     * Get the full cron hook name.
     *
     * @since 1.0.0
     * @param string $hook Short hook name.
     * @return string Full hook name with addon prefix.
     */
    protected function get_cron_hook_name( string $hook ): string {
        return "bkx_{$this->addon_id}_{$hook}";
    }

    /**
     * Register custom cron schedules.
     *
     * @since 1.0.0
     * @param array $schedules Existing schedules.
     * @return array Modified schedules.
     */
    public function add_cron_schedules( array $schedules ): array {
        // Every 5 minutes
        $schedules['bkx_five_minutes'] = [
            'interval' => 300,
            'display'  => __( 'Every 5 Minutes', 'bkx-addon-sdk' ),
        ];

        // Every 15 minutes
        $schedules['bkx_fifteen_minutes'] = [
            'interval' => 900,
            'display'  => __( 'Every 15 Minutes', 'bkx-addon-sdk' ),
        ];

        // Every 30 minutes
        $schedules['bkx_thirty_minutes'] = [
            'interval' => 1800,
            'display'  => __( 'Every 30 Minutes', 'bkx-addon-sdk' ),
        ];

        // Weekly
        if ( ! isset( $schedules['weekly'] ) ) {
            $schedules['weekly'] = [
                'interval' => WEEK_IN_SECONDS,
                'display'  => __( 'Once Weekly', 'bkx-addon-sdk' ),
            ];
        }

        // Monthly (approximate - 30 days)
        if ( ! isset( $schedules['monthly'] ) ) {
            $schedules['monthly'] = [
                'interval' => 30 * DAY_IN_SECONDS,
                'display'  => __( 'Once Monthly', 'bkx-addon-sdk' ),
            ];
        }

        return $schedules;
    }

    /**
     * Initialize cron schedules filter.
     *
     * @since 1.0.0
     * @return void
     */
    protected function init_cron_schedules(): void {
        add_filter( 'cron_schedules', [ $this, 'add_cron_schedules' ] );
    }

    /**
     * Get all scheduled events for this addon.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_scheduled_events(): array {
        $crons  = _get_cron_array();
        $events = [];
        $prefix = "bkx_{$this->addon_id}_";

        foreach ( $crons as $timestamp => $hooks ) {
            foreach ( $hooks as $hook => $data ) {
                if ( strpos( $hook, $prefix ) === 0 ) {
                    foreach ( $data as $key => $event ) {
                        $events[] = [
                            'hook'      => $hook,
                            'timestamp' => $timestamp,
                            'schedule'  => $event['schedule'],
                            'args'      => $event['args'],
                            'interval'  => $event['interval'] ?? null,
                        ];
                    }
                }
            }
        }

        return $events;
    }

    /**
     * Run a cron job immediately.
     *
     * @since 1.0.0
     * @param string $hook Hook name (without addon prefix).
     * @param array  $args Arguments to pass.
     * @return mixed Result of callback.
     */
    protected function run_cron_now( string $hook, array $args = [] ) {
        $full_hook = $this->get_cron_hook_name( $hook );

        if ( isset( $this->cron_jobs[ $hook ] ) ) {
            return call_user_func_array( $this->cron_jobs[ $hook ]['callback'], $args );
        }

        return do_action_ref_array( $full_hook, $args );
    }

    /**
     * Use Action Scheduler if available.
     *
     * Action Scheduler is preferred for long-running or high-volume tasks.
     *
     * @since 1.0.0
     * @param string $hook      Hook name.
     * @param array  $args      Arguments.
     * @param int    $timestamp When to run (0 for now).
     * @param string $group     Group name.
     * @return int|bool Action ID or false if Action Scheduler not available.
     */
    protected function schedule_action( string $hook, array $args = [], int $timestamp = 0, string $group = '' ) {
        $full_hook = $this->get_cron_hook_name( $hook );
        $group     = $group ?: $this->addon_id;

        if ( ! function_exists( 'as_schedule_single_action' ) ) {
            // Fall back to WordPress cron
            if ( $timestamp > 0 ) {
                wp_schedule_single_event( $timestamp, $full_hook, $args );
            } else {
                wp_schedule_single_event( time(), $full_hook, $args );
            }
            return false;
        }

        if ( $timestamp > 0 ) {
            return as_schedule_single_action( $timestamp, $full_hook, $args, $group );
        }

        return as_enqueue_async_action( $full_hook, $args, $group );
    }

    /**
     * Schedule a recurring action with Action Scheduler.
     *
     * @since 1.0.0
     * @param string $hook      Hook name.
     * @param int    $interval  Interval in seconds.
     * @param array  $args      Arguments.
     * @param string $group     Group name.
     * @return int|bool Action ID or false.
     */
    protected function schedule_recurring_action( string $hook, int $interval, array $args = [], string $group = '' ) {
        $full_hook = $this->get_cron_hook_name( $hook );
        $group     = $group ?: $this->addon_id;

        if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
            return false;
        }

        // Check if already scheduled
        if ( as_next_scheduled_action( $full_hook, $args, $group ) ) {
            return true;
        }

        return as_schedule_recurring_action( time(), $interval, $full_hook, $args, $group );
    }

    /**
     * Cancel a scheduled action.
     *
     * @since 1.0.0
     * @param string $hook  Hook name.
     * @param array  $args  Arguments.
     * @param string $group Group name.
     * @return int|bool Number cancelled or false.
     */
    protected function cancel_scheduled_action( string $hook, array $args = [], string $group = '' ) {
        $full_hook = $this->get_cron_hook_name( $hook );
        $group     = $group ?: $this->addon_id;

        if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
            return $this->clear_cron_hook( $hook );
        }

        return as_unschedule_all_actions( $full_hook, $args, $group );
    }
}
