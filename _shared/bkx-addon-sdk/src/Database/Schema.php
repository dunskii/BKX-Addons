<?php
/**
 * Schema Builder
 *
 * Fluent interface for building database schemas.
 *
 * @package    BookingX\AddonSDK
 * @subpackage Database
 * @since      1.0.0
 */

namespace BookingX\AddonSDK\Database;

/**
 * Schema builder class.
 *
 * @since 1.0.0
 */
class Schema {

    /**
     * Column definitions.
     *
     * @var array
     */
    protected array $columns = [];

    /**
     * Index definitions.
     *
     * @var array
     */
    protected array $indexes = [];

    /**
     * Primary key columns.
     *
     * @var array
     */
    protected array $primary_key = [];

    /**
     * Create a new schema builder.
     *
     * @return self
     */
    public static function create(): self {
        return new self();
    }

    /**
     * Add auto-incrementing big integer ID.
     *
     * @since 1.0.0
     * @param string $name Column name.
     * @return self
     */
    public function id( string $name = 'id' ): self {
        $this->columns[] = "`{$name}` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT";
        $this->primary_key[] = $name;
        return $this;
    }

    /**
     * Add big integer column.
     *
     * @since 1.0.0
     * @param string $name     Column name.
     * @param bool   $unsigned Whether unsigned.
     * @param bool   $nullable Whether nullable.
     * @return self
     */
    public function bigint( string $name, bool $unsigned = true, bool $nullable = false ): self {
        $unsigned_sql = $unsigned ? ' UNSIGNED' : '';
        $null_sql     = $nullable ? ' DEFAULT NULL' : ' NOT NULL';
        $this->columns[] = "`{$name}` BIGINT(20){$unsigned_sql}{$null_sql}";
        return $this;
    }

    /**
     * Add integer column.
     *
     * @since 1.0.0
     * @param string $name     Column name.
     * @param bool   $unsigned Whether unsigned.
     * @param bool   $nullable Whether nullable.
     * @return self
     */
    public function integer( string $name, bool $unsigned = true, bool $nullable = false ): self {
        $unsigned_sql = $unsigned ? ' UNSIGNED' : '';
        $null_sql     = $nullable ? ' DEFAULT NULL' : ' NOT NULL';
        $this->columns[] = "`{$name}` INT(11){$unsigned_sql}{$null_sql}";
        return $this;
    }

    /**
     * Add tiny integer column.
     *
     * @since 1.0.0
     * @param string $name     Column name.
     * @param bool   $unsigned Whether unsigned.
     * @param bool   $nullable Whether nullable.
     * @return self
     */
    public function tinyint( string $name, bool $unsigned = true, bool $nullable = false ): self {
        $unsigned_sql = $unsigned ? ' UNSIGNED' : '';
        $null_sql     = $nullable ? ' DEFAULT NULL' : ' NOT NULL';
        $this->columns[] = "`{$name}` TINYINT(4){$unsigned_sql}{$null_sql}";
        return $this;
    }

    /**
     * Add decimal column.
     *
     * @since 1.0.0
     * @param string $name      Column name.
     * @param int    $precision Total digits.
     * @param int    $scale     Decimal digits.
     * @param bool   $nullable  Whether nullable.
     * @return self
     */
    public function decimal( string $name, int $precision = 10, int $scale = 2, bool $nullable = false ): self {
        $null_sql = $nullable ? ' DEFAULT NULL' : ' NOT NULL';
        $this->columns[] = "`{$name}` DECIMAL({$precision},{$scale}){$null_sql}";
        return $this;
    }

    /**
     * Add float column.
     *
     * @since 1.0.0
     * @param string $name     Column name.
     * @param bool   $nullable Whether nullable.
     * @return self
     */
    public function float( string $name, bool $nullable = false ): self {
        $null_sql = $nullable ? ' DEFAULT NULL' : ' NOT NULL';
        $this->columns[] = "`{$name}` FLOAT{$null_sql}";
        return $this;
    }

    /**
     * Add boolean column.
     *
     * @since 1.0.0
     * @param string $name    Column name.
     * @param bool   $default Default value.
     * @return self
     */
    public function boolean( string $name, bool $default = false ): self {
        $default_val = $default ? '1' : '0';
        $this->columns[] = "`{$name}` TINYINT(1) NOT NULL DEFAULT {$default_val}";
        return $this;
    }

    /**
     * Add string/varchar column.
     *
     * @since 1.0.0
     * @param string      $name     Column name.
     * @param int         $length   Max length.
     * @param bool        $nullable Whether nullable.
     * @param string|null $default  Default value.
     * @return self
     */
    public function string( string $name, int $length = 255, bool $nullable = false, ?string $default = null ): self {
        $null_sql = $nullable ? ' DEFAULT NULL' : ' NOT NULL';
        if ( null !== $default && ! $nullable ) {
            $null_sql = " NOT NULL DEFAULT '{$default}'";
        }
        $this->columns[] = "`{$name}` VARCHAR({$length}){$null_sql}";
        return $this;
    }

    /**
     * Add text column.
     *
     * @since 1.0.0
     * @param string $name     Column name.
     * @param bool   $nullable Whether nullable.
     * @return self
     */
    public function text( string $name, bool $nullable = true ): self {
        $null_sql = $nullable ? ' DEFAULT NULL' : ' NOT NULL';
        $this->columns[] = "`{$name}` TEXT{$null_sql}";
        return $this;
    }

    /**
     * Add medium text column.
     *
     * @since 1.0.0
     * @param string $name     Column name.
     * @param bool   $nullable Whether nullable.
     * @return self
     */
    public function mediumtext( string $name, bool $nullable = true ): self {
        $null_sql = $nullable ? ' DEFAULT NULL' : ' NOT NULL';
        $this->columns[] = "`{$name}` MEDIUMTEXT{$null_sql}";
        return $this;
    }

    /**
     * Add long text column.
     *
     * @since 1.0.0
     * @param string $name     Column name.
     * @param bool   $nullable Whether nullable.
     * @return self
     */
    public function longtext( string $name, bool $nullable = true ): self {
        $null_sql = $nullable ? ' DEFAULT NULL' : ' NOT NULL';
        $this->columns[] = "`{$name}` LONGTEXT{$null_sql}";
        return $this;
    }

    /**
     * Add JSON column.
     *
     * @since 1.0.0
     * @param string $name     Column name.
     * @param bool   $nullable Whether nullable.
     * @return self
     */
    public function json( string $name, bool $nullable = true ): self {
        $null_sql = $nullable ? ' DEFAULT NULL' : ' NOT NULL';
        $this->columns[] = "`{$name}` JSON{$null_sql}";
        return $this;
    }

    /**
     * Add date column.
     *
     * @since 1.0.0
     * @param string $name     Column name.
     * @param bool   $nullable Whether nullable.
     * @return self
     */
    public function date( string $name, bool $nullable = false ): self {
        $null_sql = $nullable ? ' DEFAULT NULL' : ' NOT NULL';
        $this->columns[] = "`{$name}` DATE{$null_sql}";
        return $this;
    }

    /**
     * Add time column.
     *
     * @since 1.0.0
     * @param string $name     Column name.
     * @param bool   $nullable Whether nullable.
     * @return self
     */
    public function time( string $name, bool $nullable = false ): self {
        $null_sql = $nullable ? ' DEFAULT NULL' : ' NOT NULL';
        $this->columns[] = "`{$name}` TIME{$null_sql}";
        return $this;
    }

    /**
     * Add datetime column.
     *
     * @since 1.0.0
     * @param string $name     Column name.
     * @param bool   $nullable Whether nullable.
     * @return self
     */
    public function datetime( string $name, bool $nullable = false ): self {
        $null_sql = $nullable ? ' DEFAULT NULL' : ' NOT NULL';
        $this->columns[] = "`{$name}` DATETIME{$null_sql}";
        return $this;
    }

    /**
     * Add timestamp column.
     *
     * @since 1.0.0
     * @param string $name     Column name.
     * @param bool   $nullable Whether nullable.
     * @return self
     */
    public function timestamp( string $name, bool $nullable = false ): self {
        $null_sql = $nullable ? ' DEFAULT NULL' : ' NOT NULL';
        $this->columns[] = "`{$name}` TIMESTAMP{$null_sql}";
        return $this;
    }

    /**
     * Add created_at and updated_at columns.
     *
     * @since 1.0.0
     * @return self
     */
    public function timestamps(): self {
        $this->columns[] = '`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP';
        $this->columns[] = '`updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP';
        return $this;
    }

    /**
     * Add soft delete column.
     *
     * @since 1.0.0
     * @return self
     */
    public function soft_deletes(): self {
        $this->columns[] = '`deleted_at` DATETIME DEFAULT NULL';
        return $this;
    }

    /**
     * Add enum column.
     *
     * @since 1.0.0
     * @param string      $name    Column name.
     * @param array       $values  Enum values.
     * @param string|null $default Default value.
     * @return self
     */
    public function enum( string $name, array $values, ?string $default = null ): self {
        $values_sql = implode( ', ', array_map( fn( $v ) => "'{$v}'", $values ) );
        $default_sql = null !== $default ? " DEFAULT '{$default}'" : '';
        $this->columns[] = "`{$name}` ENUM({$values_sql}) NOT NULL{$default_sql}";
        return $this;
    }

    /**
     * Add foreign key reference column.
     *
     * @since 1.0.0
     * @param string $name     Column name.
     * @param bool   $nullable Whether nullable.
     * @return self
     */
    public function foreign_id( string $name, bool $nullable = true ): self {
        return $this->bigint( $name, true, $nullable );
    }

    /**
     * Add status column with common values.
     *
     * @since 1.0.0
     * @param string $default Default status.
     * @return self
     */
    public function status( string $default = 'pending' ): self {
        return $this->string( 'status', 50, false, $default );
    }

    /**
     * Add an index.
     *
     * @since 1.0.0
     * @param string       $name    Index name.
     * @param array|string $columns Column(s) to index.
     * @return self
     */
    public function index( string $name, $columns ): self {
        $columns = (array) $columns;
        $columns_sql = implode( ', ', array_map( fn( $c ) => "`{$c}`", $columns ) );
        $this->indexes[] = "KEY `{$name}` ({$columns_sql})";
        return $this;
    }

    /**
     * Add a unique index.
     *
     * @since 1.0.0
     * @param string       $name    Index name.
     * @param array|string $columns Column(s) to index.
     * @return self
     */
    public function unique( string $name, $columns ): self {
        $columns = (array) $columns;
        $columns_sql = implode( ', ', array_map( fn( $c ) => "`{$c}`", $columns ) );
        $this->indexes[] = "UNIQUE KEY `{$name}` ({$columns_sql})";
        return $this;
    }

    /**
     * Add a fulltext index.
     *
     * @since 1.0.0
     * @param string       $name    Index name.
     * @param array|string $columns Column(s) to index.
     * @return self
     */
    public function fulltext( string $name, $columns ): self {
        $columns = (array) $columns;
        $columns_sql = implode( ', ', array_map( fn( $c ) => "`{$c}`", $columns ) );
        $this->indexes[] = "FULLTEXT KEY `{$name}` ({$columns_sql})";
        return $this;
    }

    /**
     * Build the schema SQL.
     *
     * @since 1.0.0
     * @return string
     */
    public function build(): string {
        $parts = $this->columns;

        // Add primary key
        if ( ! empty( $this->primary_key ) ) {
            $pk_cols = implode( ', ', array_map( fn( $c ) => "`{$c}`", $this->primary_key ) );
            $parts[] = "PRIMARY KEY ({$pk_cols})";
        }

        // Add indexes
        $parts = array_merge( $parts, $this->indexes );

        return implode( ",\n", $parts );
    }

    /**
     * Convert to string.
     *
     * @since 1.0.0
     * @return string
     */
    public function __toString(): string {
        return $this->build();
    }
}
