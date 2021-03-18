<?php
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace Raxos\Database\Query;

use Raxos\Database\Error\DatabaseException;
use Raxos\Database\Error\QueryException;
use Raxos\Database\Query\Struct\AfterExpressionInterface;
use Raxos\Database\Query\Struct\ComparatorAwareLiteral;
use Raxos\Database\Query\Struct\Value;
use Raxos\Foundation\Util\ArrayUtil;
use Stringable;
use function array_keys;
use function array_map;
use function array_values;
use function count;
use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function str_contains;
use function substr;
use function trim;

/**
 * Class Query
 *
 * @template-covariant TModel
 * @extends QueryBase<TModel>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query
 * @since 1.0.0
 */
abstract class Query extends QueryBase
{

    /**
     * Adds an `and $field $comparator $value` expression.
     *
     * @param Stringable|Value|string|int|float|bool|null $field
     * @param Stringable|Value|string|int|float|bool|null $comparator
     * @param Stringable|Value|string|int|float|bool|null $value
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function and(Stringable|Value|string|int|float|bool|null $field = null, Stringable|Value|string|int|float|bool|null $comparator = null, Stringable|Value|string|int|float|bool|null $value = null): static
    {
        return $this->addExpression('and', $field, $comparator, $value);
    }

    /**
     * Adds a `and $field is not null` expression.
     *
     * @param string $field
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function andNotNull(string $field): static
    {
        return $this->and($field, ComparatorAwareLiteral::isNotNull());
    }

    /**
     * Adds a `and $field is null` expression.
     *
     * @param string $field
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function andNull(string $field): static
    {
        return $this->and($field, ComparatorAwareLiteral::isNull());
    }

    /**
     * Adds a `delete $table` expression.
     *
     * @param string $table
     *
     * @return static<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function delete(string $table): static
    {
        return $this->addPiece('delete', $this->dialect->escapeTable($table));
    }

    /**
     * Adds a `delete from $table` expression.
     *
     * @param string $table
     *
     * @return static<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function deleteFrom(string $table): static
    {
        return $this->addPiece('delete from', $this->dialect->escapeTable($table));
    }

    /**
     * Adds a `from $table` expression.
     *
     * @param static|string[]|string $tables
     * @param string|null $alias
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function from(self|array|string $tables, ?string $alias = null): static
    {
        if ($tables instanceof self) {
            $this->addPiece('from');
            $this->parenthesisOpen();
            $this->raw($tables->toSql());
            $this->parenthesisClose();

            if ($alias !== null) {
                $this->addPiece('as', $alias);
            }

            return $this;
        } else {
            if (is_string($tables)) {
                $tables = [$tables];
            }

            $tables = array_map(fn(string $table): string => $this->dialect->escapeTable($table), $tables);

            if ($alias !== null && count($tables) === 1) {
                $tables = array_map(fn(string $table): string => "{$table} as {$alias}", $tables);
            }

            return $this->addPiece('from', $tables, $this->dialect->tableSeparator);
        }
    }

    /**
     * Adds a `group by $fields` expression.
     *
     * @param string[]|string $fields
     *
     * @return static<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function groupBy(array|string $fields): static
    {
        if (is_string($fields)) {
            $fields = [$fields];
        }

        $fields = array_map(fn(string $field): string => $this->dialect->escapeFields($field), $fields);

        return $this->addPiece('group by', $fields, $this->dialect->fieldSeparator);
    }

    /**
     * Adds a `having $field $comparator $value` expression.
     *
     * @param Stringable|Value|string|int|float|bool|null $field
     * @param Stringable|Value|string|int|float|bool|null $comparator
     * @param Stringable|Value|string|int|float|bool|null $value
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function having(Stringable|Value|string|int|float|bool|null $field = null, Stringable|Value|string|int|float|bool|null $comparator = null, Stringable|Value|string|int|float|bool|null $value = null): static
    {
        return $this->addExpression('having', $field, $comparator, $value);
    }

    /**
     * Adds a `limit $limit offset $offset` expression.
     *
     * @param int $limit
     * @param int $offset
     *
     * @return static<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function limit(int $limit, int $offset = 0): static
    {
        $this->addPiece('limit', $limit);

        if ($offset > 0) {
            $this->offset($offset);
        }

        return $this;
    }

    /**
     * Adds a `offset $offset` expression.
     *
     * @param int $offset
     *
     * @return static<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function offset(int $offset): static
    {
        return $this->addPiece('offset', $offset);
    }

    /**
     * Adds a `on $left $comparator $right` expression.
     *
     * @param Value|string|int|float|bool $left
     * @param Value|string|int|float|bool $comparator
     * @param Stringable|Value|string|int|float|bool|null $right
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function on(Value|string|int|float|bool $left, Value|string|int|float|bool $comparator, Stringable|Value|string|int|float|bool|null $right = null): static
    {
        return $this->addExpression('on', $left, $comparator, $right);
    }

    /**
     * Adds a `on duplicate key update $fields` expression.
     *
     * @param string[]|string $fields
     *
     * @return static<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function onDuplicateKeyUpdate(array|string $fields): static
    {
        if (is_string($fields)) {
            $fields = [$fields];
        }

        $fields = array_map(fn(string $field): string => str_contains($field, '=') ? $field : $this->dialect->escapeFields($field) . " = VALUES({$field})", $fields);

        return $this->addPiece('on duplicate key update', $fields, $this->dialect->fieldSeparator);
    }

    /**
     * Adds a `or $field $comparator $value` expression.
     *
     * @param Stringable|Value|string|int|float|bool|null $field
     * @param Stringable|Value|string|int|float|bool|null $comparator
     * @param Stringable|Value|string|int|float|bool|null $value
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function or(Stringable|Value|string|int|float|bool|null $field = null, Stringable|Value|string|int|float|bool|null $comparator = null, Stringable|Value|string|int|float|bool|null $value = null): static
    {
        return $this->addExpression('or', $field, $comparator, $value);
    }

    /**
     * Adds a `or $field is not null` expression.
     *
     * @param string $field
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orNotNull(string $field): static
    {
        return $this->or($field, ComparatorAwareLiteral::isNotNull());
    }

    /**
     * Adds a `or $field is null` expression.
     *
     * @param string $field
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orNull(string $field): static
    {
        return $this->or($field, ComparatorAwareLiteral::isNull());
    }

    /**
     * Adds a `order by $fields` expression.
     *
     * @param Value[]|string[]|Value|string $fields
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orderBy(array|string $fields): static
    {
        if (is_string($fields)) {
            $fields = [$fields];
        }

        $fields = array_map(function (string $field): string {
            if ($field instanceof Value) {
                $field = $field->get($this);
            }

            if (str_contains($field, ' asc') || str_contains($field, ' ASC')) {
                return $this->dialect->escapeFields(substr($field, 0, -4)) . ' asc';
            }

            if (str_contains($field, ' desc') || str_contains($field, ' DESC')) {
                return $this->dialect->escapeFields(substr($field, 0, -5)) . ' desc';
            }

            return $this->dialect->escapeFields($field);
        }, $fields);

        return $this->addPiece('order by', $fields, $this->dialect->fieldSeparator);
    }

    /**
     * Adds a `order by $field asc` expression.
     *
     * @param string $field
     *
     * @return static<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orderByAsc(string $field): static
    {
        $field = $this->dialect->escapeFields($field);
        $clause = $this->currentClause === 'order by' ? trim($this->dialect->fieldSeparator) : 'order by';

        return $this->addPiece($clause, $field . ' asc');
    }

    /**
     * Adds a `order by $field desc` expression.
     *
     * @param string $field
     *
     * @return static<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orderByDesc(string $field): static
    {
        $field = $this->dialect->escapeFields($field);
        $clause = $this->currentClause === 'order by' ? trim($this->dialect->fieldSeparator) : 'order by';

        return $this->addPiece($clause, $field . ' desc');
    }

    /**
     * Adds a `set $field = $value` expression.
     *
     * @param string $field
     * @param Stringable|Value|string|int|float|bool|null $value
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function set(string $field, Stringable|Value|string|int|float|bool|null $value): static
    {
        $value = $this->addParam($value);
        $expression = $this->dialect->escapeFields($field) . ' = ' . $value;

        if ($this->currentClause === 'set') {
            $index = count($this->pieces) - 1;
            $existing = $this->pieces[$index][1];

            if (!is_array($existing)) {
                $existing = [$existing];
            }

            $existing[] = $expression;

            $this->pieces[$index][1] = $existing;
        } else {
            $this->addPiece('set', $expression, $this->dialect->fieldSeparator);
        }

        return $this;
    }

    /**
     * Adds a `union $query` expression.
     *
     * @param Query $query
     *
     * @return static<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function union(self $query): static
    {
        $this->addPiece('union');

        return $this->merge($query);
    }

    /**
     * Adds a `union all $query` expression.
     *
     * @param Query $query
     *
     * @return static<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function unionAll(self $query): static
    {
        $this->addPiece('union all');

        return $this->merge($query);
    }

    /**
     * Adds a `update $table set $pairs` expression.
     *
     * @param string $table
     * @param array|null $pairs
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function update(string $table, ?array $pairs = null): static
    {
        $this->addPiece('update', $this->dialect->escapeTable($table));

        if ($pairs === null) {
            return $this;
        }

        foreach ($pairs as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    /**
     * Adds a `values ($values)` expression.
     *
     * @param array $values
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function values(array $values): static
    {
        $values = array_map(fn(Stringable|Value|string|int|float|bool|null $value) => $this->addParam($value), $values);

        $this->addPiece($this->isClauseDefined('values') ? ', ' : 'values');
        $this->parenthesis(fn() => $this->addPiece('', $values, $this->dialect->fieldSeparator));

        return $this;
    }

    /**
     * Adds an `where $field $comparator $value` expression.
     *
     * @param Stringable|Value|string|int|float|bool|null $field
     * @param Stringable|Value|string|int|float|bool|null $comparator
     * @param Stringable|Value|string|int|float|bool|null $value
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function where(Stringable|Value|string|int|float|bool|null $field = null, Stringable|Value|string|int|float|bool|null $comparator = null, Stringable|Value|string|int|float|bool|null $value = null): static
    {
        return $this->addExpression('where', $field, $comparator, $value);
    }

    /**
     * Adds a `where $field is not null` expression.
     *
     * @param string $field
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function whereNotNull(string $field): static
    {
        return $this->where($field, ComparatorAwareLiteral::isNotNull());
    }

    /**
     * Adds a `where $field is null` expression.
     *
     * @param string $field
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function whereNull(string $field): static
    {
        return $this->where($field, ComparatorAwareLiteral::isNull());
    }

    /**
     * Adds a `insert into $table ($fields)` expression.
     *
     * @param string $table
     * @param string[] $fields
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function insertInto(string $table, array $fields): static
    {
        return $this->baseInsert('insert into', $table, $fields);
    }

    /**
     * Adds a `insert ignore into $table ($fields)` expression.
     *
     * @param string $table
     * @param string[] $fields
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function insertIgnoreInto(string $table, array $fields): static
    {
        return $this->baseInsert('insert ignore into', $table, $fields);
    }

    /**
     * Adds a `insert into $table ($pairs:keys) values ($pairs:values)` expression.
     *
     * @param string $table
     * @param array $pairs
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function insertIntoValues(string $table, array $pairs): static
    {
        $fields = array_keys($pairs);
        $values = array_values($pairs);

        $this->insertInto($table, $fields);
        $this->values($values);

        return $this;
    }

    /**
     * Adds a `insert ignore into $table ($pairs:keys) values ($pairs:values)` expression.
     *
     * @param string $table
     * @param array $pairs
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function insertIgnoreIntoValues(string $table, array $pairs): static
    {
        $fields = array_keys($pairs);
        $values = array_values($pairs);

        $this->insertIgnoreInto($table, $fields);
        $this->values($values);

        return $this;
    }

    /**
     * Adds a `replace into $table ($fields)` expression.
     *
     * @param string $table
     * @param string[] $fields
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function replaceInto(string $table, array $fields): static
    {
        return $this->baseInsert('replace into', $table, $fields);
    }

    /**
     * Adds a `replace into $table ($pairs:keys) values ($pairs:values)` expression.
     *
     * @param string $table
     * @param array $pairs
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function replaceIntoValues(string $table, array $pairs): static
    {
        $fields = array_keys($pairs);
        $values = array_values($pairs);

        $this->replaceInto($table, $fields);
        $this->values($values);

        return $this;
    }

    /**
     * Adds a `select $fields` expression.
     *
     * @param string[]|string|int $fields
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function select(array|string|int $fields = []): static
    {
        return $this->baseSelect('select', $fields);
    }

    /**
     * Adds a `select distinct $fields` expression.
     *
     * @param string[]|string|int $fields
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function selectDistinct(array|string|int $fields = []): static
    {
        return $this->selectSuffix('distinct', $fields);
    }

    /**
     * Adds a `select sql_calc_found_rows $fields` expression.
     *
     * @param string[]|string|int $fields
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function selectFoundRows(array|string|int $fields = []): static
    {
        return $this->selectSuffix('sql_calc_found_rows', $fields);
    }

    /**
     * Adds a `select $suffix $fields` expression.
     *
     * @param string $suffix
     * @param string[]|string|int $fields
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function selectSuffix(string $suffix, array|string|int $fields = []): static
    {
        return $this->baseSelect("select {$suffix}", $fields);
    }

    /**
     * Adds a `full join $table $fn()` expression.
     *
     * @param string $table
     * @param callable|null $fn
     *
     * @return static<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function fullJoin(string $table, ?callable $fn = null): static
    {
        return $this->baseJoin('full join', $table, $fn);
    }

    /**
     * Adds a `inner join $table $fn()` expression.
     *
     * @param string $table
     * @param callable|null $fn
     *
     * @return static<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function innerJoin(string $table, ?callable $fn = null): static
    {
        return $this->baseJoin('inner join', $table, $fn);
    }

    /**
     * Adds a `join $table $fn()` expression.
     *
     * @param string $table
     * @param callable|null $fn
     *
     * @return static<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function join(string $table, ?callable $fn = null): static
    {
        return $this->baseJoin('join', $table, $fn);
    }

    /**
     * Adds a `left join $table $fn()` expression.
     *
     * @param string $table
     * @param callable|null $fn
     *
     * @return static<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function leftJoin(string $table, ?callable $fn = null): static
    {
        return $this->baseJoin('left join', $table, $fn);
    }

    /**
     * Adds a `left outer join $table $fn()` expression.
     *
     * @param string $table
     * @param callable|null $fn
     *
     * @return static<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function leftOuterJoin(string $table, ?callable $fn = null): static
    {
        return $this->baseJoin('left outer join', $table, $fn);
    }

    /**
     * Adds a `right join $table $fn()` expression.
     *
     * @param string $table
     * @param callable|null $fn
     *
     * @return static<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function rightJoin(string $table, ?callable $fn = null): static
    {
        return $this->baseJoin('right join', $table, $fn);
    }

    /**
     * Adds a `with $name as ($query)` expression.
     *
     * @param string $name
     * @param Query $query
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function with(string $name, self $query): static
    {
        return $this->baseWith('with', $name, $query);
    }

    /**
     * Adds a `with recursive $name as ($query)` expression.
     *
     * @param string $name
     * @param Query $query
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function withRecursive(string $name, self $query): static
    {
        return $this->baseWith('with recursive', $name, $query);
    }

    /**
     * Base function to create `insert` expressions.
     *
     * @param string $clause
     * @param string $table
     * @param array $fields
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function baseInsert(string $clause, string $table, array $fields): static
    {
        if (empty($fields)) {
            throw new QueryException('Insert queries require fields.', QueryException::ERR_MISSING_FIELDS);
        }

        $fields = array_map(fn(string $field): string => $this->dialect->escapeFields($field), $fields);

        $this->addPiece($clause, $this->dialect->escapeTable($table));
        $this->parenthesis(fn() => $this->addPiece('', $fields, $this->dialect->fieldSeparator));

        return $this;
    }

    /**
     * Base function to create `join` expressions.
     *
     * @param string $clause
     * @param string $table
     * @param callable|null $fn
     *
     * @return static<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function baseJoin(string $clause, string $table, ?callable $fn): static
    {
        $this->addPiece($clause, $this->dialect->escapeTable($table));

        if ($fn !== null) {
            $fn($this);
        }

        return $this;
    }

    /**
     * Base function to create `select` expressions.
     *
     * @param string $clause
     * @param array|string|int $fields
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function baseSelect(string $clause, array|string|int $fields): static
    {
        if (empty($fields)) {
            return $this->addPiece($clause, '*');
        }

        if (is_int($fields)) {
            return $this->addPiece($clause, $fields);
        }

        if (is_string($fields)) {
            return $this->addPiece($clause, $this->dialect->escapeFields($fields));
        }

        $result = [];

        if (ArrayUtil::isAssociative($fields)) {
            foreach ($fields as $alias => $field) {
                $alias = $this->dialect->escapeFields($alias);

                if ($field === null || $field === true) {
                    $result[] = $alias;
                } else if ($field instanceof QueryBase) {
                    $sql = $field->toSql();

                    $result[] = "({$sql}) as {$alias}";
                } else if ($field instanceof AfterExpressionInterface) {
                    $query = new static($this->connection);
                    $field->after($query);
                    $sql = $query->toSql();

                    $result[] = "({$sql}) as {$alias}";
                } else if ($field instanceof Value) {
                    $field = $field->get($this);

                    $result[] = "{$field} as {$alias}";
                } else {
                    $result[] = $this->dialect->escapeFields($field) . ' as ' . $alias;
                }
            }
        } else {
            foreach ($fields as $field) {
                if (is_array($field) && count($field) === 2) {
                    $result[] = $this->dialect->escapeFields($field[0]) . ' as ' . $this->dialect->escapeFields($field[1]);
                } else if (is_numeric($field)) {
                    $result[] = (string)$field;
                } else if ($field instanceof Value) {
                    $result[] = $field->get($this);
                } else {
                    $result[] = $this->dialect->escapeFields((string)$field);
                }
            }
        }

        return $this->addPiece($clause, $result, $this->dialect->fieldSeparator);
    }

    /**
     * Base function to create `with` expressions.
     *
     * @param string $clause
     * @param string $name
     * @param Query $query
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function baseWith(string $clause, string $name, self $query): static
    {
        $this->addPiece($this->currentClause === $clause ? ',' : $clause, "{$name} as");
        $this->parenthesis(fn() => $this->merge($query), false);

        return $this;
    }

    /**
     * Builds a `optimize table $tables` query.
     *
     * @param string $table
     *
     * @return static<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public abstract function optimizeTable(string $table): static;

    /**
     * Builds a `truncate table $tables` query.
     *
     * @param string $table
     *
     * @return static<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public abstract function truncateTable(string $table): static;

}
