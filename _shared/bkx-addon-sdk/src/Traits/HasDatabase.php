<?php
/**
 * Database Trait
 *
 * Provides database management functionality for add-ons.
 *
 * @package    BookingX\AddonSDK
 * @subpackage Traits
 * @since      1.0.0
 */

namespace BookingX\AddonSDK\Traits;

/**
 * Trait for database management.
 *
 * @since 1.0.0
 */
trait HasDatabase {

    /**
     * Get the installed database version.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_db_version(): string {
        return get_option( "{$this->addon_id}_db_version", '0.0.0' );
    }

    /**
     * Update the database version.
     *
     * @since 1.0.0
     * @param string $version Version to set.
     * @return bool
     */
    protected function update_db_version( string $version ): bool {
        return update_option( "{$this->addon_id}_db_version", $version );
    }

    /**
     * Run pending migrations.
     *
     * @since 1.0.0
     * @return array Results of migrations.
     */
    public function run_pending_migrations(): array {
        $migrations = $this->get_migrations();
        $installed  = $this->get_db_version();
        $results    = [];

        foreach ( $migrations as $version => $migration_classes ) {
            if ( version_compare( $installed, $version, '>=' ) ) {
                continue;
            }

            foreach ( (array) $migration_classes as $migration_class ) {
                $result = $this->run_migration( $migration_class, $version );
                $results[] = $result;

                if ( ! $result['success'] ) {
                    // Stop on first error
                    return $results;
                }
            }

            $this->update_db_version( $version );
        }

        return $results;
    }

    /**
     * Run a single migration.
     *
     * @since 1.0.0
     * @param string $migration_class Migration class name.
     * @param string $version         Version this migration belongs to.
     * @return array Result with 'success', 'migration', 'message'.
     */
    protected function run_migration( string $migration_class, string $version ): array {
        try {
            if ( ! class_exists( $migration_class ) ) {
                return [
                    'success'   => false,
                    'migration' => $migration_class,
                    'version'   => $version,
                    'message'   => "Migration class not found: {$migration_class}",
                ];
            }

            $migration = new $migration_class();

            if ( ! method_exists( $migration, 'up' ) ) {
                return [
                    'success'   => false,
                    'migration' => $migration_class,
                    'version'   => $version,
                    'message'   => "Migration missing up() method: {$migration_class}",
                ];
            }

            $migration->up();

            return [
                'success'   => true,
                'migration' => $migration_class,
                'version'   => $version,
                'message'   => 'Migration completed successfully.',
            ];

        } catch ( \Exception $e ) {
            return [
                'success'   => false,
                'migration' => $migration_class,
                'version'   => $version,
                'message'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Rollback a migration.
     *
     * @since 1.0.0
     * @param string $migration_class Migration class name.
     * @return array Result.
     */
    protected function rollback_migration( string $migration_class ): array {
        try {
            if ( ! class_exists( $migration_class ) ) {
                return [
                    'success'   => false,
                    'migration' => $migration_class,
                    'message'   => "Migration class not found: {$migration_class}",
                ];
            }

            $migration = new $migration_class();

            if ( ! method_exists( $migration, 'down' ) ) {
                return [
                    'success'   => false,
                    'migration' => $migration_class,
                    'message'   => "Migration missing down() method: {$migration_class}",
                ];
            }

            $migration->down();

            return [
                'success'   => true,
                'migration' => $migration_class,
                'message'   => 'Rollback completed successfully.',
            ];

        } catch ( \Exception $e ) {
            return [
                'success'   => false,
                'migration' => $migration_class,
                'message'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Get table name with prefix.
     *
     * @since 1.0.0
     * @param string $table Table name without prefix.
     * @return string Full table name.
     */
    protected function get_table_name( string $table ): string {
        global $wpdb;
        return $wpdb->prefix . 'bkx_' . $table;
    }

    /**
     * Check if a table exists.
     *
     * @since 1.0.0
     * @param string $table Table name without prefix.
     * @return bool
     */
    protected function table_exists( string $table ): bool {
        global $wpdb;

        $table_name = $this->get_table_name( $table );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
        );

        return $result === $table_name;
    }

    /**
     * Create a table.
     *
     * @since 1.0.0
     * @param string $table  Table name without prefix.
     * @param string $schema Table schema (columns and keys).
     * @return bool
     */
    protected function create_table( string $table, string $schema ): bool {
        global $wpdb;

        $table_name      = $this->get_table_name( $table );
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} ({$schema}) {$charset_collate}";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        return $this->table_exists( $table );
    }

    /**
     * Drop a table.
     *
     * @since 1.0.0
     * @param string $table Table name without prefix.
     * @return bool
     */
    protected function drop_table( string $table ): bool {
        global $wpdb;

        $table_name = $this->get_table_name( $table );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

        return ! $this->table_exists( $table );
    }

    /**
     * Add a column to a table.
     *
     * @since 1.0.0
     * @param string $table      Table name without prefix.
     * @param string $column     Column name.
     * @param string $definition Column definition.
     * @param string $after      Column to add after (optional).
     * @return bool
     */
    protected function add_column( string $table, string $column, string $definition, string $after = '' ): bool {
        global $wpdb;

        if ( $this->column_exists( $table, $column ) ) {
            return true;
        }

        $table_name = $this->get_table_name( $table );
        $after_sql  = $after ? "AFTER `{$after}`" : '';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN `{$column}` {$definition} {$after_sql}" );

        return $this->column_exists( $table, $column );
    }

    /**
     * Drop a column from a table.
     *
     * @since 1.0.0
     * @param string $table  Table name without prefix.
     * @param string $column Column name.
     * @return bool
     */
    protected function drop_column( string $table, string $column ): bool {
        global $wpdb;

        if ( ! $this->column_exists( $table, $column ) ) {
            return true;
        }

        $table_name = $this->get_table_name( $table );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query( "ALTER TABLE {$table_name} DROP COLUMN `{$column}`" );

        return ! $this->column_exists( $table, $column );
    }

    /**
     * Check if a column exists.
     *
     * @since 1.0.0
     * @param string $table  Table name without prefix.
     * @param string $column Column name.
     * @return bool
     */
    protected function column_exists( string $table, string $column ): bool {
        global $wpdb;

        $table_name = $this->get_table_name( $table );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->get_results(
            $wpdb->prepare(
                'SHOW COLUMNS FROM %i LIKE %s',
                $table_name,
                $column
            )
        );

        return ! empty( $result );
    }

    /**
     * Add an index to a table.
     *
     * @since 1.0.0
     * @param string $table   Table name without prefix.
     * @param string $name    Index name.
     * @param array  $columns Columns to index.
     * @param bool   $unique  Whether the index is unique.
     * @return bool
     */
    protected function add_index( string $table, string $name, array $columns, bool $unique = false ): bool {
        global $wpdb;

        if ( $this->index_exists( $table, $name ) ) {
            return true;
        }

        $table_name   = $this->get_table_name( $table );
        $unique_sql   = $unique ? 'UNIQUE' : '';
        $columns_sql  = implode( ', ', array_map( fn( $c ) => "`{$c}`", $columns ) );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query( "ALTER TABLE {$table_name} ADD {$unique_sql} INDEX `{$name}` ({$columns_sql})" );

        return $this->index_exists( $table, $name );
    }

    /**
     * Drop an index from a table.
     *
     * @since 1.0.0
     * @param string $table Table name without prefix.
     * @param string $name  Index name.
     * @return bool
     */
    protected function drop_index( string $table, string $name ): bool {
        global $wpdb;

        if ( ! $this->index_exists( $table, $name ) ) {
            return true;
        }

        $table_name = $this->get_table_name( $table );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query( "ALTER TABLE {$table_name} DROP INDEX `{$name}`" );

        return ! $this->index_exists( $table, $name );
    }

    /**
     * Check if an index exists.
     *
     * @since 1.0.0
     * @param string $table Table name without prefix.
     * @param string $name  Index name.
     * @return bool
     */
    protected function index_exists( string $table, string $name ): bool {
        global $wpdb;

        $table_name = $this->get_table_name( $table );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->get_results(
            $wpdb->prepare(
                'SHOW INDEX FROM %i WHERE Key_name = %s',
                $table_name,
                $name
            )
        );

        return ! empty( $result );
    }

    /**
     * Insert data into a table.
     *
     * @since 1.0.0
     * @param string $table Table name without prefix.
     * @param array  $data  Data to insert.
     * @return int|false Inserted ID or false on failure.
     */
    protected function insert( string $table, array $data ) {
        global $wpdb;

        $table_name = $this->get_table_name( $table );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert( $table_name, $data );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update data in a table.
     *
     * @since 1.0.0
     * @param string $table Table name without prefix.
     * @param array  $data  Data to update.
     * @param array  $where Where conditions.
     * @return int|false Number of rows updated or false on failure.
     */
    protected function update( string $table, array $data, array $where ) {
        global $wpdb;

        $table_name = $this->get_table_name( $table );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->update( $table_name, $data, $where );
    }

    /**
     * Delete data from a table.
     *
     * @since 1.0.0
     * @param string $table Table name without prefix.
     * @param array  $where Where conditions.
     * @return int|false Number of rows deleted or false on failure.
     */
    protected function delete( string $table, array $where ) {
        global $wpdb;

        $table_name = $this->get_table_name( $table );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->delete( $table_name, $where );
    }

    /**
     * Get all add-on tables.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_addon_tables(): array {
        global $wpdb;

        $prefix = $wpdb->prefix . 'bkx_' . str_replace( 'bkx_', '', $this->addon_id );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_col(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $prefix . '%' )
        );
    }

    /**
     * Uninstall database (drop all addon tables).
     *
     * @since 1.0.0
     * @return bool
     */
    public function uninstall_database(): bool {
        $tables = $this->get_addon_tables();

        foreach ( $tables as $table ) {
            // Extract table name without prefix
            global $wpdb;
            $name = str_replace( $wpdb->prefix . 'bkx_', '', $table );
            $this->drop_table( $name );
        }

        delete_option( "{$this->addon_id}_db_version" );

        return true;
    }
}
