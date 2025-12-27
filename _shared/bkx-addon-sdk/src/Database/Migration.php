<?php
/**
 * Migration Base Class
 *
 * Base class for database migrations.
 *
 * @package    BookingX\AddonSDK
 * @subpackage Database
 * @since      1.0.0
 */

namespace BookingX\AddonSDK\Database;

/**
 * Abstract base class for migrations.
 *
 * @since 1.0.0
 */
abstract class Migration {

    /**
     * WordPress database instance.
     *
     * @var \wpdb
     */
    protected $wpdb;

    /**
     * Table prefix.
     *
     * @var string
     */
    protected string $prefix;

    /**
     * Charset collation.
     *
     * @var string
     */
    protected string $charset_collate;

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;

        $this->wpdb            = $wpdb;
        $this->prefix          = $wpdb->prefix . 'bkx_';
        $this->charset_collate = $wpdb->get_charset_collate();
    }

    /**
     * Run the migration.
     *
     * @since 1.0.0
     * @return void
     */
    abstract public function up(): void;

    /**
     * Reverse the migration.
     *
     * @since 1.0.0
     * @return void
     */
    abstract public function down(): void;

    /**
     * Get the full table name.
     *
     * @since 1.0.0
     * @param string $name Table name without prefix.
     * @return string
     */
    protected function table( string $name ): string {
        return $this->prefix . $name;
    }

    /**
     * Create a table.
     *
     * @since 1.0.0
     * @param string $name    Table name without prefix.
     * @param string $columns Column definitions.
     * @return void
     */
    protected function create_table( string $name, string $columns ): void {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_name = $this->table( $name );
        $sql        = "CREATE TABLE {$table_name} ({$columns}) {$this->charset_collate}";

        dbDelta( $sql );
    }

    /**
     * Drop a table.
     *
     * @since 1.0.0
     * @param string $name Table name without prefix.
     * @return void
     */
    protected function drop_table( string $name ): void {
        $table_name = $this->table( $name );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $this->wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
    }

    /**
     * Check if table exists.
     *
     * @since 1.0.0
     * @param string $name Table name without prefix.
     * @return bool
     */
    protected function table_exists( string $name ): bool {
        $table_name = $this->table( $name );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $this->wpdb->get_var(
            $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
        ) === $table_name;
    }

    /**
     * Add a column.
     *
     * @since 1.0.0
     * @param string $table      Table name without prefix.
     * @param string $column     Column name.
     * @param string $definition Column definition.
     * @param string $after      Column to add after (optional).
     * @return void
     */
    protected function add_column( string $table, string $column, string $definition, string $after = '' ): void {
        if ( $this->column_exists( $table, $column ) ) {
            return;
        }

        $table_name = $this->table( $table );
        $after_sql  = $after ? "AFTER `{$after}`" : '';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $this->wpdb->query(
            "ALTER TABLE {$table_name} ADD COLUMN `{$column}` {$definition} {$after_sql}"
        );
    }

    /**
     * Drop a column.
     *
     * @since 1.0.0
     * @param string $table  Table name without prefix.
     * @param string $column Column name.
     * @return void
     */
    protected function drop_column( string $table, string $column ): void {
        if ( ! $this->column_exists( $table, $column ) ) {
            return;
        }

        $table_name = $this->table( $table );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $this->wpdb->query( "ALTER TABLE {$table_name} DROP COLUMN `{$column}`" );
    }

    /**
     * Modify a column.
     *
     * @since 1.0.0
     * @param string $table      Table name without prefix.
     * @param string $column     Column name.
     * @param string $definition New column definition.
     * @return void
     */
    protected function modify_column( string $table, string $column, string $definition ): void {
        if ( ! $this->column_exists( $table, $column ) ) {
            return;
        }

        $table_name = $this->table( $table );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $this->wpdb->query(
            "ALTER TABLE {$table_name} MODIFY COLUMN `{$column}` {$definition}"
        );
    }

    /**
     * Rename a column.
     *
     * @since 1.0.0
     * @param string $table       Table name without prefix.
     * @param string $old_name    Old column name.
     * @param string $new_name    New column name.
     * @param string $definition  Column definition.
     * @return void
     */
    protected function rename_column( string $table, string $old_name, string $new_name, string $definition ): void {
        if ( ! $this->column_exists( $table, $old_name ) ) {
            return;
        }

        $table_name = $this->table( $table );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $this->wpdb->query(
            "ALTER TABLE {$table_name} CHANGE COLUMN `{$old_name}` `{$new_name}` {$definition}"
        );
    }

    /**
     * Check if column exists.
     *
     * @since 1.0.0
     * @param string $table  Table name without prefix.
     * @param string $column Column name.
     * @return bool
     */
    protected function column_exists( string $table, string $column ): bool {
        $table_name = $this->table( $table );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SHOW COLUMNS FROM {$table_name} LIKE %s",
                $column
            )
        );

        return ! empty( $result );
    }

    /**
     * Add an index.
     *
     * @since 1.0.0
     * @param string $table   Table name without prefix.
     * @param string $name    Index name.
     * @param array  $columns Columns to index.
     * @param bool   $unique  Whether unique.
     * @return void
     */
    protected function add_index( string $table, string $name, array $columns, bool $unique = false ): void {
        if ( $this->index_exists( $table, $name ) ) {
            return;
        }

        $table_name  = $this->table( $table );
        $unique_sql  = $unique ? 'UNIQUE' : '';
        $columns_sql = implode( ', ', array_map( fn( $c ) => "`{$c}`", $columns ) );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $this->wpdb->query(
            "ALTER TABLE {$table_name} ADD {$unique_sql} INDEX `{$name}` ({$columns_sql})"
        );
    }

    /**
     * Drop an index.
     *
     * @since 1.0.0
     * @param string $table Table name without prefix.
     * @param string $name  Index name.
     * @return void
     */
    protected function drop_index( string $table, string $name ): void {
        if ( ! $this->index_exists( $table, $name ) ) {
            return;
        }

        $table_name = $this->table( $table );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $this->wpdb->query( "ALTER TABLE {$table_name} DROP INDEX `{$name}`" );
    }

    /**
     * Check if index exists.
     *
     * @since 1.0.0
     * @param string $table Table name without prefix.
     * @param string $name  Index name.
     * @return bool
     */
    protected function index_exists( string $table, string $name ): bool {
        $table_name = $this->table( $table );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SHOW INDEX FROM {$table_name} WHERE Key_name = %s",
                $name
            )
        );

        return ! empty( $result );
    }

    /**
     * Add a foreign key.
     *
     * @since 1.0.0
     * @param string $table           Table name without prefix.
     * @param string $name            Constraint name.
     * @param string $column          Column name.
     * @param string $reference_table Reference table name.
     * @param string $reference_col   Reference column name.
     * @param string $on_delete       On delete action.
     * @param string $on_update       On update action.
     * @return void
     */
    protected function add_foreign_key(
        string $table,
        string $name,
        string $column,
        string $reference_table,
        string $reference_col,
        string $on_delete = 'CASCADE',
        string $on_update = 'CASCADE'
    ): void {
        $table_name = $this->table( $table );
        $ref_table  = $this->table( $reference_table );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $this->wpdb->query(
            "ALTER TABLE {$table_name}
            ADD CONSTRAINT `{$name}` FOREIGN KEY (`{$column}`)
            REFERENCES {$ref_table} (`{$reference_col}`)
            ON DELETE {$on_delete}
            ON UPDATE {$on_update}"
        );
    }

    /**
     * Drop a foreign key.
     *
     * @since 1.0.0
     * @param string $table Table name without prefix.
     * @param string $name  Constraint name.
     * @return void
     */
    protected function drop_foreign_key( string $table, string $name ): void {
        $table_name = $this->table( $table );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $this->wpdb->query( "ALTER TABLE {$table_name} DROP FOREIGN KEY `{$name}`" );
    }

    /**
     * Execute raw SQL.
     *
     * @since 1.0.0
     * @param string $sql SQL to execute.
     * @return void
     */
    protected function raw( string $sql ): void {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $this->wpdb->query( $sql );
    }

    /**
     * Insert data.
     *
     * @since 1.0.0
     * @param string $table Table name without prefix.
     * @param array  $data  Data to insert.
     * @return int|false Insert ID or false.
     */
    protected function insert( string $table, array $data ) {
        $table_name = $this->table( $table );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $this->wpdb->insert( $table_name, $data );

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Seed data.
     *
     * @since 1.0.0
     * @param string $table Table name without prefix.
     * @param array  $rows  Array of rows to insert.
     * @return int Number of rows inserted.
     */
    protected function seed( string $table, array $rows ): int {
        $count = 0;

        foreach ( $rows as $row ) {
            if ( $this->insert( $table, $row ) ) {
                $count++;
            }
        }

        return $count;
    }
}
