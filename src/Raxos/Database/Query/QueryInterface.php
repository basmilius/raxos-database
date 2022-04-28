<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use Raxos\Database\Error\{DatabaseException, QueryException};
use Raxos\Database\Query\Struct\Value;
use Stringable;

/**
 * Interface QueryInterface
 *
 * @template TModel
 * @extends QueryBaseInterface<TModel>
 *
 * @author Bas Milius <bas@glybe.nl>
 * @package Raxos\Database\Query
 * @since 1.0.0
 */
interface QueryInterface extends QueryBaseInterface
{

    /**
     * Adds a `delete $table` expression.
     *
     * @param string $table
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function delete(string $table): static;

    /**
     * Adds a `delete from $table` expression.
     *
     * @param string $table
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function deleteFrom(string $table): static;

    /**
     * Adds a `from $table` expression.
     *
     * @param static|string[]|string $tables
     * @param string|null $alias
     *
     * @return $this
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function from(Query|array|string $tables, ?string $alias = null): static;

    /**
     * Adds a `group by $fields` expression.
     *
     * @param string[]|string $fields
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function groupBy(array|string $fields): static;

    /**
     * Adds a `having $field $comparator $value` expression.
     *
     * @param Stringable|Value|string|int|float|bool|null $lhs
     * @param Stringable|Value|string|int|float|bool|null $cmp
     * @param Stringable|Value|string|int|float|bool|null $rhs
     *
     * @return $this
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function having(Stringable|Value|string|int|float|bool|null $lhs = null, Stringable|Value|string|int|float|bool|null $cmp = null, Stringable|Value|string|int|float|bool|null $rhs = null): static;

    /**
     * Adds a `having exists $query` expression.
     *
     * @param Query $query
     *
     * @return $this
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function havingExists(Query $query): static;

    /**
     * Adds a `having $field in ($options)` expression.
     *
     * @param string $field
     * @param array $options
     *
     * @return $this
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function havingIn(string $field, array $options): static;

    /**
     * Adds a `having $field is not null` expression.
     *
     * @param string $field
     *
     * @return $this
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function havingNotNull(string $field): static;

    /**
     * Adds a `having $field is null` expression.
     *
     * @param string $field
     *
     * @return $this
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function havingNull(string $field): static;

    /**
     * Adds a `limit $limit offset $offset` expression.
     *
     * @param int $limit
     * @param int $offset
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function limit(int $limit, int $offset = 0): static;

    /**
     * Adds a `offset $offset` expression.
     *
     * @param int $offset
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function offset(int $offset): static;

    /**
     * Adds a `on $left $comparator $right` expression.
     *
     * @param Stringable|Value|string|int|float|bool $lhs
     * @param Stringable|Value|string|int|float|bool $cmp
     * @param Stringable|Value|string|int|float|bool|null $rhs
     *
     * @return $this
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function on(Stringable|Value|string|int|float|bool $lhs, Stringable|Value|string|int|float|bool|null $cmp = null, Stringable|Value|string|int|float|bool|null $rhs = null): static;

    /**
     * Adds a `on duplicate key update $fields` expression.
     *
     * @param string[]|string $fields
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function onDuplicateKeyUpdate(array|string $fields): static;

    /**
     * Adds a `or $field $comparator $value` expression.
     *
     * @param Stringable|Value|string|int|float|bool|null $lhs
     * @param Stringable|Value|string|int|float|bool|null $cmp
     * @param Stringable|Value|string|int|float|bool|null $rhs
     *
     * @return $this
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orWhere(Stringable|Value|string|int|float|bool|null $lhs = null, Stringable|Value|string|int|float|bool|null $cmp = null, Stringable|Value|string|int|float|bool|null $rhs = null): static;

    /**
     * Adds a `or exists $query` expression.
     *
     * @param Query $query
     *
     * @return $this
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orWhereExists(Query $query): static;

    /**
     * Queries the given relation.
     *
     * @param string $relation
     * @param callable $fn
     *
     * @return $this
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orWhereHas(string $relation, callable $fn): static;

    /**
     * Adds a `or $field in ($options)` expression.
     *
     * @param string $field
     * @param array $options
     *
     * @return $this
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orWhereIn(string $field, array $options): static;

    /**
     * Queries the given relation negated.
     *
     * @param string $relation
     * @param callable $fn
     *
     * @return $this
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orWhereNotHas(string $relation, callable $fn): static;

    /**
     * Adds a `or $field is not null` expression.
     *
     * @param string $field
     *
     * @return $this
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orWhereNotNull(string $field): static;

    /**
     * Adds a `or $field is null` expression.
     *
     * @param string $field
     *
     * @return $this
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orWhereNull(string $field): static;

    /**
     * Queries the given relation based on one condition.
     *
     * @param string $relation
     * @param Stringable|Value|string|int|float|bool|null $lhs
     * @param Stringable|Value|string|int|float|bool|null $cmp
     * @param Stringable|Value|string|int|float|bool|null $rhs
     *
     * @return $this
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orWhereRelation(string $relation, Stringable|Value|string|int|float|bool|null $lhs = null, Stringable|Value|string|int|float|bool|null $cmp = null, Stringable|Value|string|int|float|bool|null $rhs = null): static;

    /**
     * Adds a `order by $fields` expression.
     *
     * @param Value[]|string[]|Value|string $fields
     *
     * @return $this
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orderBy(array|string $fields): static;

    /**
     * Adds a `order by $field asc` expression.
     *
     * @param string $field
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orderByAsc(string $field): static;

    /**
     * Adds a `order by $field desc` expression.
     *
     * @param string $field
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orderByDesc(string $field): static;

    /**
     * Adds a `set $field = $value` expression.
     *
     * @param string $field
     * @param Stringable|Value|string|int|float|bool|null $value
     *
     * @return $this
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function set(string $field, Stringable|Value|string|int|float|bool|null $value): static;

    /**
     * Adds a `union $query` expression.
     *
     * @param Query $query
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function union(Query $query): static;

    /**
     * Adds a `union all $query` expression.
     *
     * @param Query $query
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function unionAll(Query $query): static;

    /**
     * Adds a `update $table set $pairs` expression.
     *
     * @param string $table
     * @param array|null $pairs
     *
     * @return $this
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function update(string $table, ?array $pairs = null): static;

    /**
     * Adds a `values ($values)` expression.
     *
     * @param array $values
     *
     * @return $this
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function values(array $values): static;

    /**
     * Adds an `where $field $comparator $value` expression.
     *
     * @param Stringable|Value|string|int|float|bool|null $lhs
     * @param Stringable|Value|string|int|float|bool|null $cmp
     * @param Stringable|Value|string|int|float|bool|null $rhs
     *
     * @return $this
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function where(Stringable|Value|string|int|float|bool|null $lhs = null, Stringable|Value|string|int|float|bool|null $cmp = null, Stringable|Value|string|int|float|bool|null $rhs = null): static;

    /**
     * Adds a `where exists $query` expression.
     *
     * @param Query $query
     *
     * @return $this
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function whereExists(Query $query): static;

    /**
     * Queries the given relation.
     *
     * @param string $relation
     * @param callable $fn
     *
     * @return $this
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function whereHas(string $relation, callable $fn): static;

    /**
     * Adds a `where $field in ($options)` expression.
     *
     * @param string $field
     * @param array $options
     *
     * @return $this
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function whereIn(string $field, array $options): static;

    /**
     * Queries the given relation.
     *
     * @param string $relation
     * @param callable $fn
     *
     * @return $this
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function whereNotHas(string $relation, callable $fn): static;

    /**
     * Adds a `where $field is not null` expression.
     *
     * @param string $field
     *
     * @return $this
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function whereNotNull(string $field): static;

    /**
     * Adds a `where $field is null` expression.
     *
     * @param string $field
     *
     * @return $this
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function whereNull(string $field): static;

    /**
     * Queries the given relation based on one condition.
     *
     * @param string $relation
     * @param Stringable|Value|string|int|float|bool|null $lhs
     * @param Stringable|Value|string|int|float|bool|null $cmp
     * @param Stringable|Value|string|int|float|bool|null $rhs
     *
     * @return $this
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function whereRelation(string $relation, Stringable|Value|string|int|float|bool|null $lhs = null, Stringable|Value|string|int|float|bool|null $cmp = null, Stringable|Value|string|int|float|bool|null $rhs = null): static;

    /**
     * Adds a `insert into $table ($fields)` expression.
     *
     * @param string $table
     * @param string[] $fields
     *
     * @return $this
     * @throws DatabaseException
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function insertInto(string $table, array $fields): static;

    /**
     * Adds a `insert ignore into $table ($fields)` expression.
     *
     * @param string $table
     * @param string[] $fields
     *
     * @return $this
     * @throws DatabaseException
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function insertIgnoreInto(string $table, array $fields): static;

    /**
     * Adds a `insert into $table ($pairs:keys) values ($pairs:values)` expression.
     *
     * @param string $table
     * @param array $pairs
     *
     * @return $this
     * @throws DatabaseException
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function insertIntoValues(string $table, array $pairs): static;

    /**
     * Adds a `insert ignore into $table ($pairs:keys) values ($pairs:values)` expression.
     *
     * @param string $table
     * @param array $pairs
     *
     * @return $this
     * @throws DatabaseException
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function insertIgnoreIntoValues(string $table, array $pairs): static;

    /**
     * Adds a `replace into $table ($fields)` expression.
     *
     * @param string $table
     * @param string[] $fields
     *
     * @return $this
     * @throws DatabaseException
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function replaceInto(string $table, array $fields): static;

    /**
     * Adds a `replace into $table ($pairs:keys) values ($pairs:values)` expression.
     *
     * @param string $table
     * @param array $pairs
     *
     * @return $this
     * @throws DatabaseException
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function replaceIntoValues(string $table, array $pairs): static;

    /**
     * Adds a `select $fields` expression.
     *
     * @param array<static|string|int|bool>|string|int $fields
     *
     * @return $this
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function select(array|string|int $fields = []): static;

    /**
     * Adds a `select distinct $fields` expression.
     *
     * @param string[]|string|int $fields
     *
     * @return $this
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function selectDistinct(array|string|int $fields = []): static;

    /**
     * Adds a `select sql_calc_found_rows $fields` expression.
     *
     * @param string[]|string|int $fields
     *
     * @return $this
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function selectFoundRows(array|string|int $fields = []): static;

    /**
     * Adds a `select $suffix $fields` expression.
     *
     * @param string $suffix
     * @param string[]|string|int $fields
     *
     * @return $this
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function selectSuffix(string $suffix, array|string|int $fields = []): static;

    /**
     * Adds a `full join $table $fn()` expression.
     *
     * @param string $table
     * @param callable|null $fn
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function fullJoin(string $table, ?callable $fn = null): static;

    /**
     * Adds a `inner join $table $fn()` expression.
     *
     * @param string $table
     * @param callable|null $fn
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function innerJoin(string $table, ?callable $fn = null): static;

    /**
     * Adds a `join $table $fn()` expression.
     *
     * @param string $table
     * @param callable|null $fn
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function join(string $table, ?callable $fn = null): static;

    /**
     * Adds a `left join $table $fn()` expression.
     *
     * @param string $table
     * @param callable|null $fn
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function leftJoin(string $table, ?callable $fn = null): static;

    /**
     * Adds a `left outer join $table $fn()` expression.
     *
     * @param string $table
     * @param callable|null $fn
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function leftOuterJoin(string $table, ?callable $fn = null): static;

    /**
     * Adds a `right join $table $fn()` expression.
     *
     * @param string $table
     * @param callable|null $fn
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function rightJoin(string $table, ?callable $fn = null): static;

    /**
     * Adds a `with $name as ($query)` expression.
     *
     * @param string $name
     * @param Query $query
     *
     * @return $this
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function with(string $name, Query $query): static;

    /**
     * Adds a `with recursive $name as ($query)` expression.
     *
     * @param string $name
     * @param Query $query
     *
     * @return $this
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function withRecursive(string $name, Query $query): static;

    /**
     * Builds a `optimize table $tables` query.
     *
     * @param string $table
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function optimizeTable(string $table): static;

    /**
     * Builds a `truncate table $tables` query.
     *
     * @param string $table
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function truncateTable(string $table): static;

}