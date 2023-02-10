<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace Hyperf\Database\Sqlsrv\Query\Grammars;

use Hyperf\Database\Query\Builder;
use Hyperf\Database\Query\Expression;
use Hyperf\Database\Query\Grammars\Grammar;
use Hyperf\Utils\Arr;
use Hyperf\Utils\Str;

class SqlServerGrammar extends Grammar
{
    /**
     * All of the available clause operators.
     *
     * @var string[]
     */
    protected array $operators = [
        '=', '<', '>', '<=', '>=', '!<', '!>', '<>', '!=',
        'like', 'not like', 'ilike',
        '&', '&=', '|', '|=', '^', '^=',
    ];

    /**
     * Compile a select query into SQL.
     */
    public function compileSelect(Builder $query): string
    {
        if (! $query->offset) {
            return parent::compileSelect($query);
        }

        if (is_null($query->columns)) {
            $query->columns = ['*'];
        }

        $components = $this->compileComponents($query);

        if (! empty($components['orders'])) {
            return parent::compileSelect($query) . " offset {$query->offset} rows fetch next {$query->limit} rows only";
        }

        // If an offset is present on the query, we will need to wrap the query in
        // a big "ANSI" offset syntax block. This is very nasty compared to the
        // other database systems but is necessary for implementing features.
        return $this->compileAnsiOffset(
            $query,
            $components
        );
    }

    /**
     * Prepare the binding for a "JSON contains" statement.
     *
     * @param mixed $binding
     */
    public function prepareBindingForJsonContains($binding): string
    {
        return is_bool($binding) ? json_encode($binding) : $binding;
    }

    /**
     * Compile the random statement into SQL.
     *
     * @param string $seed
     */
    public function compileRandom($seed): string
    {
        return 'NEWID()';
    }

    /**
     * Compile an exists statement into SQL.
     */
    public function compileExists(Builder $query): string
    {
        $existsQuery = clone $query;

        $existsQuery->columns = [];

        return $this->compileSelect($existsQuery->selectRaw('1 [exists]')->limit(1));
    }

    /**
     * Compile an "upsert" statement into SQL.
     *
     * @return string
     */
    public function compileUpsert(Builder $query, array $values, array $uniqueBy, array $update)
    {
        $columns = $this->columnize(array_keys(reset($values)));

        $sql = 'merge ' . $this->wrapTable($query->from) . ' ';

        $parameters = collect($values)->map(function ($record) {
            return '(' . $this->parameterize($record) . ')';
        })->implode(', ');

        $sql .= 'using (values ' . $parameters . ') ' . $this->wrapTable('laravel_source') . ' (' . $columns . ') ';

        $on = collect($uniqueBy)->map(function ($column) use ($query) {
            return $this->wrap('laravel_source.' . $column) . ' = ' . $this->wrap($query->from . '.' . $column);
        })->implode(' and ');

        $sql .= 'on ' . $on . ' ';

        if ($update) {
            $update = collect($update)->map(function ($value, $key) {
                return is_numeric($key)
                    ? $this->wrap($value) . ' = ' . $this->wrap('laravel_source.' . $value)
                    : $this->wrap($key) . ' = ' . $this->parameter($value);
            })->implode(', ');

            $sql .= 'when matched then update set ' . $update . ' ';
        }

        $sql .= 'when not matched then insert (' . $columns . ') values (' . $columns . ');';

        return $sql;
    }

    /**
     * Prepare the bindings for an update statement.
     */
    public function prepareBindingsForUpdate(array $bindings, array $values): array
    {
        $cleanBindings = Arr::except($bindings, 'select');

        return array_values(
            array_merge($values, Arr::flatten($cleanBindings))
        );
    }

    /**
     * Compile the SQL statement to define a savepoint.
     *
     * @param string $name
     */
    public function compileSavepoint($name): string
    {
        return 'SAVE TRANSACTION ' . $name;
    }

    /**
     * Compile the SQL statement to execute a savepoint rollback.
     *
     * @param string $name
     */
    public function compileSavepointRollBack($name): string
    {
        return 'ROLLBACK TRANSACTION ' . $name;
    }

    /**
     * Get the format for database stored dates.
     */
    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s.v';
    }

    /**
     * Wrap a table in keyword identifiers.
     *
     * @param array|Expression $table
     */
    public function wrapTable($table): string
    {
        if (! $this->isExpression($table)) {
            return $this->wrapTableValuedFunction(parent::wrapTable($table));
        }

        return $this->getValue($table);
    }

    /**
     * Compile the "select *" portion of the query.
     *
     * @param array $columns
     */
    protected function compileColumns(Builder $query, $columns): string
    {
        if (! is_null($query->aggregate)) {
            return '';
        }

        $select = $query->distinct ? 'select distinct ' : 'select ';

        // If there is a limit on the query, but not an offset, we will add the top
        // clause to the query, which serves as a "limit" type clause within the
        // SQL Server system similar to the limit keywords available in MySQL.
        if (is_numeric($query->limit) && $query->limit > 0 && $query->offset <= 0) {
            $select .= 'top ' . ((int) $query->limit) . ' ';
        }

        return $select . $this->columnize($columns);
    }

    /**
     * Compile the "from" portion of the query.
     *
     * @param string $table
     */
    protected function compileFrom(Builder $query, $table): string
    {
        $from = parent::compileFrom($query, $table);

        if (is_string($query->lock)) {
            return $from . ' ' . $query->lock;
        }

        if (! is_null($query->lock)) {
            return $from . ' with(rowlock,' . ($query->lock ? 'updlock,' : '') . 'holdlock)';
        }

        return $from;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function whereBitwise(Builder $query, array $where)
    {
        $value = $this->parameter($where['value']);

        $operator = str_replace('?', '??', $where['operator']);

        return '(' . $this->wrap($where['column']) . ' ' . $operator . ' ' . $value . ') != 0';
    }

    /**
     * Compile a "where date" clause.
     *
     * @param array $where
     */
    protected function whereDate(Builder $query, $where): string
    {
        $value = $this->parameter($where['value']);

        return 'cast(' . $this->wrap($where['column']) . ' as date) ' . $where['operator'] . ' ' . $value;
    }

    /**
     * Compile a "where time" clause.
     *
     * @param array $where
     */
    protected function whereTime(Builder $query, $where): string
    {
        $value = $this->parameter($where['value']);

        return 'cast(' . $this->wrap($where['column']) . ' as time) ' . $where['operator'] . ' ' . $value;
    }

    /**
     * Compile a "JSON contains" statement into SQL.
     *
     * @param string $column
     * @param string $value
     */
    protected function compileJsonContains($column, $value): string
    {
        [$field, $path] = $this->wrapJsonFieldAndPath($column);

        return $value . ' in (select [value] from openjson(' . $field . $path . '))';
    }

    /**
     * Compile a "JSON length" statement into SQL.
     *
     * @param string $column
     * @param string $operator
     * @param string $value
     */
    protected function compileJsonLength($column, $operator, $value): string
    {
        [$field, $path] = $this->wrapJsonFieldAndPath($column);

        return '(select count(*) from openjson(' . $field . $path . ')) ' . $operator . ' ' . $value;
    }

    /**
     * {@inheritdoc}
     */
    protected function compileHaving(array $having): string
    {
        if ($having['type'] === 'Bitwise') {
            return $this->compileHavingBitwise($having);
        }

        return parent::compileHaving($having);
    }

    /**
     * Compile a having clause involving a bitwise operator.
     */
    protected function compileHavingBitwise(array $having): string
    {
        $column = $this->wrap($having['column']);

        $parameter = $this->parameter($having['value']);

        return $having['boolean'] . ' (' . $column . ' ' . $having['operator'] . ' ' . $parameter . ') != 0';
    }

    /**
     * Create a full ANSI offset clause for the query.
     */
    protected function compileAnsiOffset(Builder $query, array $components): string
    {
        // An ORDER BY clause is required to make this offset query work, so if one does
        // not exist we'll just create a dummy clause to trick the database and so it
        // does not complain about the queries for not having an "order by" clause.
        if (empty($components['orders'])) {
            $components['orders'] = 'order by (select 0)';
        }

        // We need to add the row number to the query so we can compare it to the offset
        // and limit values given for the statements. So we will add an expression to
        // the "select" that will give back the row numbers on each of the records.
        $components['columns'] .= $this->compileOver($components['orders']);

        unset($components['orders']);

        if ($this->queryOrderContainsSubquery($query)) {
            $query->bindings = $this->sortBindingsForSubqueryOrderBy($query);
        }

        // Next we need to calculate the constraints that should be placed on the query
        // to get the right offset and limit from our query but if there is no limit
        // set we will just handle the offset only since that is all that matters.
        $sql = $this->concatenate($components);

        return $this->compileTableExpression($sql, $query);
    }

    /**
     * Compile the over statement for a table expression.
     */
    protected function compileOver(string $orderings): string
    {
        return ", row_number() over ({$orderings}) as row_num";
    }

    /**
     * Determine if the query's order by clauses contain a subquery.
     */
    protected function queryOrderContainsSubquery(Builder $query): bool
    {
        if (! is_array($query->orders)) {
            return false;
        }

        return Arr::first($query->orders, function ($value) {
            return $this->isExpression($value['column'] ?? null);
        }, false) !== false;
    }

    /**
     * Move the order bindings to be after the "select" statement to account for an order by subquery.
     */
    protected function sortBindingsForSubqueryOrderBy(Builder $query): array
    {
        return Arr::sort($query->bindings, function ($bindings, $key) {
            return array_search($key, ['select', 'order', 'from', 'join', 'where', 'groupBy', 'having', 'union', 'unionOrder']);
        });
    }

    /**
     * Compile a common table expression for a query.
     */
    protected function compileTableExpression(string $sql, Builder $query): string
    {
        $constraint = $this->compileRowConstraint($query);

        return "select * from ({$sql}) as temp_table where row_num {$constraint} order by row_num";
    }

    /**
     * Compile the limit / offset row constraint for a query.
     */
    protected function compileRowConstraint(Builder $query): string
    {
        $start = $query->offset + 1;

        if ($query->limit > 0) {
            $finish = $query->offset + $query->limit;

            return "between {$start} and {$finish}";
        }

        return ">= {$start}";
    }

    /**
     * Compile a delete statement without joins into SQL.
     *
     * @return string
     */
    protected function compileDeleteWithoutJoins(Builder $query, string $table, string $where)
    {
        $sql = parent::compileDeleteWithoutJoins($query, $table, $where);

        return ! is_null($query->limit) && $query->limit > 0 && $query->offset <= 0
            ? Str::replaceFirst('delete', 'delete top (' . $query->limit . ')', $sql)
            : $sql;
    }

    /**
     * Compile the "limit" portions of the query.
     *
     * @param int $limit
     */
    protected function compileLimit(Builder $query, $limit): string
    {
        return '';
    }

    /**
     * Compile the "offset" portions of the query.
     *
     * @param int $offset
     */
    protected function compileOffset(Builder $query, $offset): string
    {
        return '';
    }

    /**
     * Compile the lock into SQL.
     *
     * @param bool|string $value
     */
    protected function compileLock(Builder $query, $value): string
    {
        return '';
    }

    /**
     * Wrap a union subquery in parentheses.
     */
    protected function wrapUnion(string $sql): string
    {
        return 'select * from (' . $sql . ') as ' . $this->wrapTable('temp_table');
    }

    /**
     * Compile an update statement with joins into SQL.
     */
    protected function compileUpdateWithJoins(Builder $query, string $table, string $columns, string $where): string
    {
        $alias = last(explode(' as ', $table));

        $joins = $this->compileJoins($query, $query->joins);

        return "update {$alias} set {$columns} from {$table} {$joins} {$where}";
    }

    /**
     * Wrap a single string in keyword identifiers.
     *
     * @param string $value
     */
    protected function wrapValue($value): string
    {
        return $value === '*' ? $value : '[' . str_replace(']', ']]', $value) . ']';
    }

    /**
     * Wrap the given JSON selector.
     *
     * @param string $value
     */
    protected function wrapJsonSelector($value): string
    {
        [$field, $path] = $this->wrapJsonFieldAndPath($value);

        return 'json_value(' . $field . $path . ')';
    }

    /**
     * Wrap the given JSON boolean value.
     */
    protected function wrapJsonBooleanValue(string $value): string
    {
        return "'" . $value . "'";
    }

    /**
     * Wrap a table in keyword identifiers.
     */
    protected function wrapTableValuedFunction(string $table): string
    {
        if (preg_match('/^(.+?)(\(.*?\))]$/', $table, $matches) === 1) {
            $table = $matches[1] . ']' . $matches[2];
        }

        return $table;
    }
}
