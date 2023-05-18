<?php
/**
 * Created by PhpStorm
 * Date 2023/5/18 11:05
 */

namespace Hyperf\Database\Sqlsrv\Schema\Grammars;

use Hyperf\Database\Connection;
use Hyperf\Database\Query\Expression;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Grammars\Grammar;
use Hyperf\Support\Fluent;
use function Hyperf\Collection\collect;

class SqlServerGrammar extends Grammar
{
    /**
     * If this Grammar supports schema changes wrapped in a transaction.
     *
     * @var bool
     */
    protected bool $transactions = true;

    /**
     * The possible column modifiers.
     *
     * @var string[]
     */
    protected array $modifiers = ['Collate', 'Nullable', 'Default', 'Persisted', 'Increment'];

    /**
     * The columns available as serials.
     *
     * @var string[]
     */
    protected array $serials = ['tinyInteger', 'smallInteger', 'mediumInteger', 'integer', 'bigInteger'];

    /**
     * The commands to be executed outside of create or alter command.
     *
     * @var string[]
     */
    protected array $fluentCommands = ['Default'];

    /**
     * Compile a create database command.
     *
     * @param string $name
     * @param Connection $connection
     * @return string
     */
    public function compileCreateDatabase(string $name, $connection): string
    {
        return sprintf(
            'create database %s',
            $this->wrapValue($name),
        );
    }

    /**
     * Compile a drop database if exists command.
     *
     * @param string $name
     * @return string
     */
    public function compileDropDatabaseIfExists(string $name): string
    {
        return sprintf(
            'drop database if exists %s',
            $this->wrapValue($name)
        );
    }

    /**
     * Compile the query to determine if a table exists.
     *
     * @return string
     */
    public function compileTableExists(): string
    {
        return "select * from sys.sysobjects where id = object_id(?) and xtype in ('U', 'V')";
    }

    /**
     * Compile the query to determine the list of columns.
     *
     * @param string $table
     * @return string
     */
    public function compileColumnListing(string $table): string
    {
        return "select name from sys.columns where object_id = object_id('$table')";
    }

    /**
     * Compile a create table command.
     *
     * @param Blueprint $blueprint
     * @param Fluent $command
     * @return string
     */
    public function compileCreate(Blueprint $blueprint, Fluent $command): string
    {
        $columns = implode(', ', $this->getColumns($blueprint));

        return 'create table ' . $this->wrapTable($blueprint) . " ($columns)";
    }

    /**
     * Compile a column addition table command.
     *
     * @param Blueprint $blueprint
     * @param Fluent $command
     * @return string
     */
    public function compileAdd(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf('alter table %s add %s',
            $this->wrapTable($blueprint),
            implode(', ', $this->getColumns($blueprint))
        );
    }

    /**
     * Compile a primary key command.
     *
     * @param Blueprint $blueprint
     * @param Fluent $command
     * @return string
     */
    public function compilePrimary(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf('alter table %s add constraint %s primary key (%s)',
            $this->wrapTable($blueprint),
            $this->wrap($command->index),
            $this->columnize($command->columns)
        );
    }

    /**
     * Compile a unique key command.
     *
     * @param Blueprint $blueprint
     * @param Fluent $command
     * @return string
     */
    public function compileUnique(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf('create unique index %s on %s (%s)',
            $this->wrap($command->index),
            $this->wrapTable($blueprint),
            $this->columnize($command->columns)
        );
    }

    /**
     * Compile a plain index key command.
     *
     * @param Blueprint $blueprint
     * @param Fluent $command
     * @return string
     */
    public function compileIndex(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf('create index %s on %s (%s)',
            $this->wrap($command->index),
            $this->wrapTable($blueprint),
            $this->columnize($command->columns)
        );
    }

    /**
     * Compile a spatial index key command.
     *
     * @param Blueprint $blueprint
     * @param Fluent $command
     * @return string
     */
    public function compileSpatialIndex(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf('create spatial index %s on %s (%s)',
            $this->wrap($command->index),
            $this->wrapTable($blueprint),
            $this->columnize($command->columns)
        );
    }

    /**
     * Compile a default command.
     *
     * @param Blueprint $blueprint
     * @param Fluent $command
     * @return string|null
     */
    public function compileDefault(Blueprint $blueprint, Fluent $command): ?string
    {
        if ($command->column->change && !is_null($command->column->default)) {
            return sprintf('alter table %s add default %s for %s',
                $this->wrapTable($blueprint),
                $this->getDefaultValue($command->column->default),
                $this->wrap($command->column)
            );
        }
        return null;
    }

    /**
     * Compile a drop table command.
     *
     * @param Blueprint $blueprint
     * @param Fluent $command
     * @return string
     */
    public function compileDrop(Blueprint $blueprint, Fluent $command): string
    {
        return 'drop table ' . $this->wrapTable($blueprint);
    }

    /**
     * Compile a drop table (if exists) command.
     *
     * @param Blueprint $blueprint
     * @param Fluent $command
     * @return string
     */
    public function compileDropIfExists(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf('if exists (select * from sys.sysobjects where id = object_id(%s, \'U\')) drop table %s',
            "'" . str_replace("'", "''", $this->getTablePrefix() . $blueprint->getTable()) . "'",
            $this->wrapTable($blueprint)
        );
    }

    /**
     * Compile the SQL needed to drop all tables.
     *
     * @return string
     */
    public function compileDropAllTables(): string
    {
        return "EXEC sp_msforeachtable 'DROP TABLE ?'";
    }

    /**
     * Compile a drop column command.
     *
     * @param Blueprint $blueprint
     * @param Fluent $command
     * @return string
     */
    public function compileDropColumn(Blueprint $blueprint, Fluent $command): string
    {
        $columns = $this->wrapArray($command->columns);

        $dropExistingConstraintsSql = $this->compileDropDefaultConstraint($blueprint, $command) . ';';

        return $dropExistingConstraintsSql . 'alter table ' . $this->wrapTable($blueprint) . ' drop column ' . implode(', ', $columns);
    }

    /**
     * Compile a drop default constraint command.
     *
     * @param Blueprint $blueprint
     * @param Fluent $command
     * @return string
     */
    public function compileDropDefaultConstraint(Blueprint $blueprint, Fluent $command): string
    {
        $columns = $command->name === 'change'
            ? "'" . collect($blueprint->getChangedColumns())->pluck('name')->implode("','") . "'"
            : "'" . implode("','", $command->columns) . "'";

        $tableName = $this->getTablePrefix() . $blueprint->getTable();

        $sql = "DECLARE @sql NVARCHAR(MAX) = '';";
        $sql .= "SELECT @sql += 'ALTER TABLE [dbo].[{$tableName}] DROP CONSTRAINT ' + OBJECT_NAME([default_object_id]) + ';' ";
        $sql .= 'FROM sys.columns ';
        $sql .= "WHERE [object_id] = OBJECT_ID('[dbo].[{$tableName}]') AND [name] in ({$columns}) AND [default_object_id] <> 0;";
        $sql .= 'EXEC(@sql)';

        return $sql;
    }

    /**
     * Compile a drop primary key command.
     *
     * @param Blueprint $blueprint
     * @param Fluent $command
     * @return string
     */
    public function compileDropPrimary(Blueprint $blueprint, Fluent $command): string
    {
        $index = $this->wrap($command->index);

        return "alter table {$this->wrapTable($blueprint)} drop constraint {$index}";
    }

    /**
     * Compile a drop unique key command.
     *
     * @param Blueprint $blueprint
     * @param Fluent $command
     * @return string
     */
    public function compileDropUnique(Blueprint $blueprint, Fluent $command): string
    {
        $index = $this->wrap($command->index);

        return "drop index {$index} on {$this->wrapTable($blueprint)}";
    }

    /**
     * Compile a drop index command.
     *
     * @param Blueprint $blueprint
     * @param Fluent $command
     * @return string
     */
    public function compileDropIndex(Blueprint $blueprint, Fluent $command): string
    {
        $index = $this->wrap($command->index);

        return "drop index {$index} on {$this->wrapTable($blueprint)}";
    }

    /**
     * Compile a drop spatial index command.
     *
     * @param Blueprint $blueprint
     * @param Fluent $command
     * @return string
     */
    public function compileDropSpatialIndex(Blueprint $blueprint, Fluent $command): string
    {
        return $this->compileDropIndex($blueprint, $command);
    }

    /**
     * Compile a drop foreign key command.
     *
     * @param Blueprint $blueprint
     * @param Fluent $command
     * @return string
     */
    public function compileDropForeign(Blueprint $blueprint, Fluent $command): string
    {
        $index = $this->wrap($command->index);

        return "alter table {$this->wrapTable($blueprint)} drop constraint {$index}";
    }

    /**
     * Compile a rename table command.
     *
     * @param Blueprint $blueprint
     * @param Fluent $command
     * @return string
     */
    public function compileRename(Blueprint $blueprint, Fluent $command): string
    {
        $from = $this->wrapTable($blueprint);

        return "sp_rename {$from}, " . $this->wrapTable($command->to);
    }

    /**
     * Compile a rename index command.
     *
     * @param Blueprint $blueprint
     * @param Fluent $command
     * @return string
     */
    public function compileRenameIndex(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf("sp_rename N'%s', %s, N'INDEX'",
            $this->wrap($blueprint->getTable() . '.' . $command->from),
            $this->wrap($command->to)
        );
    }

    /**
     * Compile the command to enable foreign key constraints.
     *
     * @return string
     */
    public function compileEnableForeignKeyConstraints(): string
    {
        return 'EXEC sp_msforeachtable @command1="print \'?\'", @command2="ALTER TABLE ? WITH CHECK CHECK CONSTRAINT all";';
    }

    /**
     * Compile the command to disable foreign key constraints.
     *
     * @return string
     */
    public function compileDisableForeignKeyConstraints(): string
    {
        return 'EXEC sp_msforeachtable "ALTER TABLE ? NOCHECK CONSTRAINT all";';
    }

    /**
     * Compile the command to drop all foreign keys.
     *
     * @return string
     */
    public function compileDropAllForeignKeys(): string
    {
        return "DECLARE @sql NVARCHAR(MAX) = N'';
            SELECT @sql += 'ALTER TABLE '
                + QUOTENAME(OBJECT_SCHEMA_NAME(parent_object_id)) + '.' + + QUOTENAME(OBJECT_NAME(parent_object_id))
                + ' DROP CONSTRAINT ' + QUOTENAME(name) + ';'
            FROM sys.foreign_keys;

            EXEC sp_executesql @sql;";
    }

    /**
     * Compile the command to drop all views.
     *
     * @return string
     */
    public function compileDropAllViews(): string
    {
        return "DECLARE @sql NVARCHAR(MAX) = N'';
            SELECT @sql += 'DROP VIEW ' + QUOTENAME(OBJECT_SCHEMA_NAME(object_id)) + '.' + QUOTENAME(name) + ';'
            FROM sys.views;

            EXEC sp_executesql @sql;";
    }

    /**
     * Compile the SQL needed to retrieve all table names.
     *
     * @return string
     */
    public function compileGetAllTables(): string
    {
        return "select name, type from sys.tables where type = 'U'";
    }

    /**
     * Compile the SQL needed to retrieve all view names.
     *
     * @return string
     */
    public function compileGetAllViews(): string
    {
        return "select name, type from sys.objects where type = 'V'";
    }

    /**
     * Create the column definition for a char type.
     *
     * @param Fluent $column
     * @return string
     */
    protected function typeChar(Fluent $column): string
    {
        return "nchar({$column->length})";
    }

    /**
     * Create the column definition for a string type.
     *
     * @param Fluent $column
     * @return string
     */
    protected function typeString(Fluent $column): string
    {
        return "nvarchar({$column->length})";
    }

    /**
     * Create the column definition for a tiny text type.
     *
     * @param Fluent $column
     * @return string
     */
    protected function typeTinyText(Fluent $column): string
    {
        return 'nvarchar(255)';
    }

    /**
     * Create the column definition for a text type.
     *
     * @param Fluent $column
     * @return string
     */
    protected function typeText(Fluent $column): string
    {
        return 'nvarchar(max)';
    }

    /**
     * Create the column definition for a medium text type.
     *
     * @param Fluent $column
     * @return string
     */
    protected function typeMediumText(Fluent $column): string
    {
        return 'nvarchar(max)';
    }

    /**
     * Create the column definition for a long text type.
     *
     * @param Fluent $column
     * @return string
     */
    protected function typeLongText(Fluent $column): string
    {
        return 'nvarchar(max)';
    }

    /**
     * Create the column definition for an integer type.
     *
     * @param Fluent $column
     * @return string
     */
    protected function typeInteger(Fluent $column): string
    {
        return 'int';
    }

    /**
     * Create the column definition for a big integer type.
     *
     * @param Fluent $column
     * @return string
     */
    protected function typeBigInteger(Fluent $column): string
    {
        return 'bigint';
    }

    /**
     * Create the column definition for a medium integer type.
     *
     * @param Fluent $column
     * @return string
     */
    protected function typeMediumInteger(Fluent $column): string
    {
        return 'int';
    }

    /**
     * Create the column definition for a tiny integer type.
     *
     * @param Fluent $column
     * @return string
     */
    protected function typeTinyInteger(Fluent $column): string
    {
        return 'tinyint';
    }

    /**
     * Create the column definition for a small integer type.
     *
     * @param Fluent $column
     * @return string
     */
    protected function typeSmallInteger(Fluent $column): string
    {
        return 'smallint';
    }

    /**
     * Create the column definition for a float type.
     *
     * @param Fluent $column
     * @return string
     */
    protected function typeFloat(Fluent $column): string
    {
        return 'float';
    }

    /**
     * Create the column definition for a double type.
     *
     * @param Fluent $column
     * @return string
     */
    protected function typeDouble(Fluent $column): string
    {
        return 'float';
    }

    /**
     * Create the column definition for a decimal type.
     *
     * @param Fluent $column
     * @return string
     */
    protected function typeDecimal(Fluent $column): string
    {
        return "decimal({$column->total}, {$column->places})";
    }

    /**
     * Create the column definition for a boolean type.
     *
     * @param Fluent $column
     * @return string
     */
    protected function typeBoolean(Fluent $column): string
    {
        return 'bit';
    }

    /**
     * Create the column definition for an enumeration type.
     *
     * @param Fluent $column
     * @return string
     */
    protected function typeEnum(Fluent $column): string
    {
        return sprintf(
            'nvarchar(255) check ("%s" in (%s))',
            $column->name,
            $this->quoteString($column->allowed)
        );
    }

    /**
     * Create the column definition for a json type.
     *
     * @param Fluent $column
     * @return string
     */
    protected function typeJson(Fluent $column): string
    {
        return 'nvarchar(max)';
    }

    /**
     * Create the column definition for a jsonb type.
     *
     * @param Fluent $column
     * @return string
     */
    protected function typeJsonb(Fluent $column): string
    {
        return 'nvarchar(max)';
    }

    /**
     * Create the column definition for a date type.
     *
     * @param Fluent $column
     * @return string
     */
    protected function typeDate(Fluent $column): string
    {
        return 'date';
    }

    /**
     * Create the column definition for a date-time type.
     *
     * @param Fluent $column
     * @return string
     */
    protected function typeDateTime(Fluent $column): string
    {
        return $this->typeTimestamp($column);
    }

    /**
     * Create the column definition for a date-time (with time zone) type.
     *
     * @param Fluent $column
     * @return string
     */
    protected function typeDateTimeTz(Fluent $column): string
    {
        return $this->typeTimestampTz($column);
    }

    /**
     * Create the column definition for a time type.
     *
     * @param Fluent $column
     * @return string
     */
    protected function typeTime(Fluent $column): string
    {
        return $column->precision ? "time($column->precision)" : 'time';
    }

    /**
     * Create the column definition for a time (with time zone) type.
     *
     * @param Fluent $column
     * @return string
     */
    protected function typeTimeTz(Fluent $column): string
    {
        return $this->typeTime($column);
    }

    /**
     * Create the column definition for a timestamp type.
     *
     * @param Fluent $column
     * @return string
     */
    protected function typeTimestamp(Fluent $column): string
    {
        if ($column->useCurrent) {
            $column->default(new Expression('CURRENT_TIMESTAMP'));
        }

        return $column->precision ? "datetime2($column->precision)" : 'datetime';
    }

    /**
     * Create the column definition for a timestamp (with time zone) type.
     *
     * @link https://docs.microsoft.com/en-us/sql/t-sql/data-types/datetimeoffset-transact-sql?view=sql-server-ver15
     *
     * @param Fluent $column
     * @return string
     */
    protected function typeTimestampTz(Fluent $column): string
    {
        if ($column->useCurrent) {
            $column->default(new Expression('CURRENT_TIMESTAMP'));
        }

        return $column->precision ? "datetimeoffset($column->precision)" : 'datetimeoffset';
    }

    /**
     * Create the column definition for a year type.
     *
     * @param Fluent $column
     * @return string
     */
    protected function typeYear(Fluent $column): string
    {
        return $this->typeInteger($column);
    }

    /**
     * Create the column definition for a binary type.
     *
     * @param Fluent $column
     * @return string
     */
    protected function typeBinary(Fluent $column): string
    {
        return 'varbinary(max)';
    }

    /**
     * Create the column definition for a uuid type.
     *
     * @param Fluent $column
     * @return string
     */
    protected function typeUuid(Fluent $column): string
    {
        return 'uniqueidentifier';
    }

    /**
     * Create the column definition for an IP address type.
     *
     * @param Fluent $column
     * @return string
     */
    protected function typeIpAddress(Fluent $column): string
    {
        return 'nvarchar(45)';
    }

    /**
     * Create the column definition for a MAC address type.
     *
     * @param Fluent $column
     * @return string
     */
    protected function typeMacAddress(Fluent $column): string
    {
        return 'nvarchar(17)';
    }

    /**
     * Create the column definition for a spatial Geometry type.
     *
     * @param Fluent $column
     * @return string
     */
    public function typeGeometry(Fluent $column): string
    {
        return 'geography';
    }

    /**
     * Create the column definition for a spatial Point type.
     *
     * @param Fluent $column
     * @return string
     */
    public function typePoint(Fluent $column): string
    {
        return 'geography';
    }

    /**
     * Create the column definition for a spatial LineString type.
     *
     * @param Fluent $column
     * @return string
     */
    public function typeLineString(Fluent $column): string
    {
        return 'geography';
    }

    /**
     * Create the column definition for a spatial Polygon type.
     *
     * @param Fluent $column
     * @return string
     */
    public function typePolygon(Fluent $column): string
    {
        return 'geography';
    }

    /**
     * Create the column definition for a spatial GeometryCollection type.
     *
     * @param Fluent $column
     * @return string
     */
    public function typeGeometryCollection(Fluent $column): string
    {
        return 'geography';
    }

    /**
     * Create the column definition for a spatial MultiPoint type.
     *
     * @param Fluent $column
     * @return string
     */
    public function typeMultiPoint(Fluent $column): string
    {
        return 'geography';
    }

    /**
     * Create the column definition for a spatial MultiLineString type.
     *
     * @param Fluent $column
     * @return string
     */
    public function typeMultiLineString(Fluent $column): string
    {
        return 'geography';
    }

    /**
     * Create the column definition for a spatial MultiPolygon type.
     *
     * @param Fluent $column
     * @return string
     */
    public function typeMultiPolygon(Fluent $column): string
    {
        return 'geography';
    }

    /**
     * Create the column definition for a generated, computed column type.
     *
     * @param Fluent $column
     * @return string|null
     */
    protected function typeComputed(Fluent $column): ?string
    {
        return "as ({$column->expression})";
    }

    /**
     * Get the SQL for a collation column modifier.
     *
     * @param Blueprint $blueprint
     * @param Fluent $column
     * @return string|null
     */
    protected function modifyCollate(Blueprint $blueprint, Fluent $column): ?string
    {
        if (!is_null($column->collation)) {
            return ' collate ' . $column->collation;
        }
        return null;
    }

    /**
     * Get the SQL for a nullable column modifier.
     *
     * @param Blueprint $blueprint
     * @param Fluent $column
     * @return string|null
     */
    protected function modifyNullable(Blueprint $blueprint, Fluent $column): ?string
    {
        if ($column->type !== 'computed') {
            return $column->nullable ? ' null' : ' not null';
        }
        return null;
    }

    /**
     * Get the SQL for a default column modifier.
     *
     * @param Blueprint $blueprint
     * @param Fluent $column
     * @return string|null
     */
    protected function modifyDefault(Blueprint $blueprint, Fluent $column): ?string
    {
        if (!$column->change && !is_null($column->default)) {
            return ' default ' . $this->getDefaultValue($column->default);
        }
        return null;
    }

    /**
     * Get the SQL for an auto-increment column modifier.
     *
     * @param Blueprint $blueprint
     * @param Fluent $column
     * @return string|null
     */
    protected function modifyIncrement(Blueprint $blueprint, Fluent $column): ?string
    {
        if (!$column->change && in_array($column->type, $this->serials) && $column->autoIncrement) {
            return ' identity primary key';
        }
        return null;
    }

    /**
     * Get the SQL for a generated stored column modifier.
     *
     * @param Blueprint $blueprint
     * @param Fluent $column
     * @return string|null
     */
    protected function modifyPersisted(Blueprint $blueprint, Fluent $column): ?string
    {
        if ($column->change) {
            if ($column->type === 'computed') {
                return $column->persisted ? ' add persisted' : ' drop persisted';
            }

            return null;
        }

        if ($column->persisted) {
            return ' persisted';
        }
        return null;
    }

    /**
     * Wrap a table in keyword identifiers.
     *
     * @param Blueprint|Expression|string $table
     * @return string
     */
    public function wrapTable($table): string
    {
        if ($table instanceof Blueprint && $table->temporary) {
            $this->setTablePrefix('#');
        }

        return parent::wrapTable($table);
    }

    /**
     * Quote the given string literal.
     *
     * @param string|array $value
     * @return string
     */
    public function quoteString($value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map([$this, __FUNCTION__], $value));
        }

        return "N'$value'";
    }
}