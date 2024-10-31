<?php
declare(strict_types=1);

namespace Raxos\Database\Contract;

use BackedEnum;
use Generator;
use PDO;
use Raxos\Database\Error\{ConnectionException, ExecutionException, QueryException};
use Raxos\Database\Orm\{Model, ModelArrayList};
use Raxos\Foundation\Collection\Paginated;
use Raxos\Foundation\Contract\ArrayListInterface;
use Raxos\Database\Orm\Error\{RelationException, StructureException};
use Raxos\Database\Query\Struct\{ColumnLiteral, Literal, Select};
use Raxos\Foundation\Collection\ArrayList;
use stdClass;
use Stringable;

/**
 * Interface QueryInterface
 *
 * @template TModel
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Contract
 * @since 1.0.0
 */
interface QueryInterface
{

    /**
     * Adds an expression to the query.
     *
     * @param string $clause
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $lhs
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $cmp
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $rhs
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function addExpression(
        string $clause,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $lhs,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $cmp,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $rhs
    ): static;

    /**
     * Adds a param and returns its name or when not in prepared mode, returns the
     * value as string or int.
     *
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $value
     *
     * @return string|int
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function addParam(BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $value): string|int;

    /**
     * Adds a query piece.
     *
     * @param string $clause
     * @param ColumnLiteral|array|string|int|null $data
     * @param string|null $separator
     *
     * @return QueryInterface<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function addPiece(string $clause, ColumnLiteral|array|string|int|null $data = null, ?string $separator = null): static;

    /**
     * Executes the given function if the given bool is true.
     *
     * @param bool $is
     * @param callable $fn
     *
     * @return QueryInterface<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function conditional(bool $is, callable $fn): static;

    /**
     * Wraps the given function with parenthesis or does nothing when the given bool is false.
     *
     * @param bool $is
     * @param callable $fn
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function conditionalParenthesis(bool $is, callable $fn): static;

    /**
     * Eager load the given relations when a Model is fetched from the database.
     *
     * @param string|string[] $relations
     *
     * @return QueryInterface<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function eagerLoad(string|array $relations): static;

    /**
     * Disables eager loading for the given relation(s).
     *
     * @param string|string[] $relations
     *
     * @return QueryInterface<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function eagerLoadDisable(string|array $relations): static;

    /**
     * Removes eager loading from the query.
     *
     * @return QueryInterface<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function eagerLoadReset(): static;

    /**
     * Merges the given query with the current one.
     *
     * @param QueryInterface $query
     *
     * @return QueryInterface<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function merge(QueryInterface $query): static;

    /**
     * Wraps the given function in parentheses.
     *
     * @param callable $fn
     * @param bool $patch
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function parenthesis(callable $fn, bool $patch = true): static;

    /**
     * Closes a parenthesis group.
     *
     * @return QueryInterface<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function parenthesisClose(): static;

    /**
     * Opens a parenthesis group.
     *
     * @param string|null $lhs
     * @param string|null $cmp
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $rhs
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function parenthesisOpen(
        ?string $lhs = null,
        ?string $cmp = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $rhs = null
    ): static;

    /**
     * Adds the given raw expression to the query.
     *
     * @param string $expression
     *
     * @return QueryInterface<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function raw(string $expression): static;

    /**
     * Returns TRUE if the given clause is defined in the query.
     *
     * @param string $clause
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function isClauseDefined(string $clause): bool;

    /**
     * Returns TRUE when a model is associated with the query.
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function isModelQuery(): bool;

    /**
     * Removes the given clause from the query.
     *
     * @param string $clause
     *
     * @return QueryInterface<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function removeClause(string $clause): static;

    /**
     * Replaces the given clause using the given function. The function
     * receives the array piece.
     *
     * @param string $clause
     * @param callable $fn
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function replaceClause(string $clause, callable $fn): static;

    /**
     * Associates a model.
     *
     * @param string $class
     *
     * @return QueryInterface<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function withModel(string $class): static;

    /**
     * Removes the associated model.
     *
     * @return QueryInterface<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function withoutModel(): static;

    /**
     * Puts the pieces together and builds the query.
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function toSql(): string;

    /**
     * Returns the result row count found based on the current query. The
     * select part of the query is removed.
     *
     * @return int
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function resultCount(): int;

    /**
     * Returns the total rows found based on the current query. Any limit
     * clause is ignored and the select part is removed. This is useful for
     * queries used for pagination and such.
     *
     * @return int
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function totalCount(): int;

    /**
     * Explain the query.
     *
     * @return array
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.3.0
     */
    public function explain(): array;

    /**
     * Runs the query and returns an array containing all the results.
     *
     * @param int $fetchMode
     * @param array $options
     *
     * @return array<array-key, TModel>
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see StatementInterface::array()
     */
    public function array(int $fetchMode = PDO::FETCH_ASSOC, array $options = []): array;

    /**
     * Runs the query and returns an ArrayList containing all the results.
     *
     * @param int $fetchMode
     * @param array $options
     *
     * @return ArrayList<int, TModel>|ModelArrayList<int, TModel>|iterable<int, TModel>
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see StatementInterface::arrayList()
     */
    public function arrayList(int $fetchMode = PDO::FETCH_ASSOC, array $options = []): mixed;

    /**
     * Runs the query and returns a generator containing all results.
     *
     * @param int $fetchMode
     * @param array $options
     *
     * @return Generator<TModel>
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see StatementInterface::cursor()
     */
    public function cursor(int $fetchMode = PDO::FETCH_ASSOC, array $options = []): Generator;

    /**
     * Runs the query and returns a paginated response.
     *
     * @param int $offset
     * @param int $limit
     * @param callable(QueryInterface, int, int):ArrayListInterface|null $itemBuilder
     * @param callable(QueryInterface, int, int):int|null $totalBuilder
     * @param int $fetchMode
     * @param array $options
     *
     * @return Paginated<TModel>
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.3.1
     * @see QueryInterface::arrayList()
     * @see StatementInterface::paginate()
     */
    public function paginate(int $offset, int $limit, ?callable $itemBuilder = null, ?callable $totalBuilder = null, int $fetchMode = PDO::FETCH_ASSOC, array $options = []): Paginated;

    /**
     * Runs the query.
     *
     * @param array $options
     *
     * @throws ExecutionException
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see StatementInterface::run()
     */
    public function run(array $options = []): void;

    /**
     * Returns a query that returns a value. Useful for insert queries.
     *
     * @param Literal|Literal[]|string|string[] $column
     *
     * @return string[]|int[]|string|int
     * @throws ExecutionException
     * @throws QueryException
     * @since 1.0.16
     * @author Bas Milius <bas@mili.us>
     */
    public function runReturning(array|Literal|string $column): array|string|int;

    /**
     * Executes the query and returns the first row. When no result is found,
     * null is returned.
     *
     * @param int $fetchMode
     * @param array $options
     *
     * @return TModel|stdClass|array|null
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function single(int $fetchMode = PDO::FETCH_ASSOC, array $options = []): mixed;

    /**
     * Executes the query and returns the first result. When no result is found,
     * a query exception is thrown.
     *
     * @param int $fetchMode
     * @param array $options
     *
     * @return TModel|stdClass|array
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function singleOrFail(int $fetchMode = PDO::FETCH_ASSOC, array $options = []): mixed;

    /**
     * Creates a statement with the current query.
     *
     * @param array $options
     *
     * @return StatementInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function statement(array $options = []): StatementInterface;

    /**
     * Runs the given function using the current query instance.
     *
     * @param callable(static):static $fn
     *
     * @return QueryInterface<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public function withQuery(callable $fn): static;

    /**
     * Adds a `delete $table` expression.
     *
     * @param string $table
     *
     * @return QueryInterface<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function delete(string $table): static;

    /**
     * Adds a `delete from $table` expression.
     *
     * @param string $table
     *
     * @return QueryInterface<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function deleteFrom(string $table): static;

    /**
     * Adds a `from $table` expression.
     *
     * @param QueryInterface|string[]|string $tables
     * @param string|null $alias
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function from(QueryInterface|array|string $tables, ?string $alias = null): static;

    /**
     * Adds a `group by $fields` expression.
     *
     * @param Literal|Literal[]|string[]|string $fields
     *
     * @return QueryInterface<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function groupBy(Literal|array|string $fields): static;

    /**
     * Adds a `having $field $comparator $value` expression.
     *
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $lhs
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $cmp
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $rhs
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function having(
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $lhs = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $cmp = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $rhs = null
    ): static;

    /**
     * Adds a `having exists $query` expression.
     *
     * @param QueryInterface $query
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function havingExists(QueryInterface $query): static;

    /**
     * Adds a `having $field in ($options)` expression.
     *
     * @param Literal|string $field
     * @param iterable $options
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function havingIn(Literal|string $field, iterable $options): static;

    /**
     * Adds a `having not exists $query` expression.
     *
     * @param QueryInterface $query
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.2
     */
    public function havingNotExists(QueryInterface $query): static;

    /**
     * Adds a `having $field is not null` expression.
     *
     * @param Literal|string $field
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function havingNotNull(Literal|string $field): static;

    /**
     * Adds a `having $field not in ($options)` expression.
     *
     * @param Literal|string $field
     * @param iterable $options
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.2
     */
    public function havingNotIn(Literal|string $field, iterable $options): static;

    /**
     * Adds a `having $field is null` expression.
     *
     * @param Literal|string $field
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function havingNull(Literal|string $field): static;

    /**
     * Adds a `limit $limit offset $offset` expression.
     *
     * @param int $limit
     * @param int $offset
     *
     * @return QueryInterface<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function limit(int $limit, int $offset = 0): static;

    /**
     * Adds a `offset $offset` expression.
     *
     * @param int $offset
     *
     * @return QueryInterface<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function offset(int $offset): static;

    /**
     * Adds a `on $left $comparator $right` expression.
     *
     * @param Stringable|QueryValueInterface|string|int|float|bool $lhs
     * @param Stringable|QueryValueInterface|string|int|float|bool $cmp
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $rhs
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function on(
        Stringable|QueryValueInterface|string|int|float|bool $lhs,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $cmp = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $rhs = null
    ): static;

    /**
     * Adds an `on duplicate key update $fields` expression.
     *
     * @param string[]|string $fields
     *
     * @return QueryInterface<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function onDuplicateKeyUpdate(array|string $fields): static;

    /**
     * Adds an `or $field $comparator $value` expression.
     *
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $lhs
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $cmp
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $rhs
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orWhere(
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $lhs = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $cmp = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $rhs = null
    ): static;

    /**
     * Adds a `or exists $query` expression.
     *
     * @param QueryInterface $query
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orWhereExists(QueryInterface $query): static;

    /**
     * Queries the given relation.
     *
     * @param string $relation
     * @param callable|null $fn
     *
     * @return QueryInterface<TModel>
     * @throws ConnectionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orWhereHas(string $relation, ?callable $fn): static;

    /**
     * Adds an `or $field in ($options)` expression.
     *
     * @param Literal|string $field
     * @param iterable $options
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orWhereIn(Literal|string $field, iterable $options): static;

    /**
     * Adds an `or not exists $query` expression.
     *
     * @param QueryInterface $query
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.2
     */
    public function orWhereNotExists(QueryInterface $query): static;

    /**
     * Queries the given relation negated.
     *
     * @param string $relation
     * @param callable $fn
     *
     * @return QueryInterface<TModel>
     * @throws ConnectionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orWhereNotHas(string $relation, callable $fn): static;

    /**
     * Adds an `or where $field not in ($options)` expression.
     *
     * @param Literal|string $field
     * @param iterable $options
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.2
     */
    public function orWhereNotIn(Literal|string $field, iterable $options): static;

    /**
     * Adds an `or $field is not null` expression.
     *
     * @param Literal|string $field
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orWhereNotNull(Literal|string $field): static;

    /**
     * Adds an `or $field is null` expression.
     *
     * @param Literal|string $field
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orWhereNull(Literal|string $field): static;

    /**
     * Queries the given relation based on one condition.
     *
     * @param string $relation
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $lhs
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $cmp
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $rhs
     *
     * @return QueryInterface<TModel>
     * @throws ConnectionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orWhereRelation(
        string $relation,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $lhs = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $cmp = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $rhs = null
    ): static;

    /**
     * Adds an `order by $fields` expression.
     *
     * @param Literal[]|string[]|Literal|string $fields
     *
     * @return QueryInterface<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orderBy(Literal|array|string $fields): static;

    /**
     * Adds an `order by $field asc` expression.
     *
     * @param Literal|string $field
     *
     * @return QueryInterface<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orderByAsc(Literal|string $field): static;

    /**
     * Adds an `order by $field desc` expression.
     *
     * @param Literal|string $field
     *
     * @return QueryInterface<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orderByDesc(Literal|string $field): static;

    /**
     * Adds a `set $field = $value` expression.
     *
     * @param Stringable|QueryValueInterface|string $field
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $value
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function set(
        Stringable|QueryValueInterface|string $field,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $value
    ): static;

    /**
     * Adds an `union $query` expression.
     *
     * @param QueryInterface $query
     *
     * @return QueryInterface<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function union(QueryInterface $query): static;

    /**
     * Adds an `union all $query` expression.
     *
     * @param QueryInterface $query
     *
     * @return QueryInterface<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function unionAll(QueryInterface $query): static;

    /**
     * Adds an `update $table set $pairs` expression.
     *
     * @param string $table
     * @param array|null $pairs
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function update(string $table, ?array $pairs = null): static;

    /**
     * Adds a `values ($values)` expression.
     *
     * @param array $values
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function values(array $values): static;

    /**
     * Adds an `where $field $comparator $value` expression.
     *
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $lhs
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $cmp
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $rhs
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function where(
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $lhs = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $cmp = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $rhs = null
    ): static;

    /**
     * Adds a `where exists $query` expression.
     *
     * @param QueryInterface $query
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function whereExists(QueryInterface $query): static;

    /**
     * Queries the given relation.
     *
     * @param string $relation
     * @param callable|null $fn
     *
     * @return QueryInterface<TModel>
     * @throws ConnectionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function whereHas(string $relation, ?callable $fn): static;

    /**
     * Adds a `where $field in ($options)` expression.
     *
     * @param Literal|string $field
     * @param iterable $options
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function whereIn(Literal|string $field, iterable $options): static;

    /**
     * Adds a `where not exists $query` expression.
     *
     * @param QueryInterface $query
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.2
     */
    public function whereNotExists(QueryInterface $query): static;

    /**
     * Queries the given relation.
     *
     * @param string $relation
     * @param callable $fn
     *
     * @return QueryInterface<TModel>
     * @throws ConnectionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function whereNotHas(string $relation, callable $fn): static;

    /**
     * Adds a `where $field not in ($options)` expression.
     *
     * @param Literal|string $field
     * @param iterable $options
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.2
     */
    public function whereNotIn(Literal|string $field, iterable $options): static;

    /**
     * Adds a `where $field is not null` expression.
     *
     * @param Literal|string $field
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function whereNotNull(Literal|string $field): static;

    /**
     * Adds a `where $field is null` expression.
     *
     * @param Literal|string $field
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function whereNull(Literal|string $field): static;

    /**
     * Adds a set of where expressions for the primary key of the given
     * model class. If multiple primary keys exist for the model, all
     * of them are added using `and`.
     *
     * Example: `where table.pk1 = 1 and table.pk2 = 'test'`
     *
     * @template TQueryModel of Model
     *
     * @param class-string<TQueryModel>|class-string<Model> $modelClass
     * @param array|string|int $primaryKey
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function wherePrimaryKey(string $modelClass, array|string|int $primaryKey): static;

    /**
     * Adds a set of where expressions for the primary key of the given
     * model class. The difference between this method and {@see self::wherePrimaryKey()}
     * is that this method can look up multiple values.
     *
     * Example: `where (table.pk1 = 1 and table.pk2 = 'test') or (table.pk1 = 2 and table.pk2 = 'hello')`
     *
     * @template TQueryModel of Model
     *
     * @param class-string<TQueryModel>|class-string<Model> $modelClass
     * @param array $primaryKeys
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function wherePrimaryKeyIn(string $modelClass, array $primaryKeys): static;

    /**
     * Queries the given relation based on one condition.
     *
     * @param string $relation
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $lhs
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $cmp
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $rhs
     *
     * @return QueryInterface<TModel>
     * @throws ConnectionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function whereRelation(
        string $relation,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $lhs = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $cmp = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $rhs = null
    ): static;

    /**
     * Adds an `insert into $table ($fields)` expression.
     *
     * @param string $table
     * @param string[] $fields
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function insertInto(string $table, array $fields): static;

    /**
     * Adds an `insert ignore into $table ($fields)` expression.
     *
     * @param string $table
     * @param string[] $fields
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function insertIgnoreInto(string $table, array $fields): static;

    /**
     * Adds an `insert into $table ($pairs:keys) values ($pairs:values)` expression.
     *
     * @param string $table
     * @param array $pairs
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function insertIntoValues(string $table, array $pairs): static;

    /**
     * Adds an `insert ignore into $table ($pairs:keys) values ($pairs:values)` expression.
     *
     * @param string $table
     * @param array $pairs
     *
     * @return QueryInterface<TModel>
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
     * @return QueryInterface<TModel>
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
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function replaceIntoValues(string $table, array $pairs): static;

    /**
     * Adds a `select $fields` expression.
     *
     * @param Select|Stringable|array<static|string|int|bool>|string|int $fields
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function select(Select|Stringable|array|string|int $fields = []): static;

    /**
     * Adds a `select distinct $fields` expression.
     *
     * @param Select|Stringable|string[]|string|int $fields
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function selectDistinct(Select|Stringable|array|string|int $fields = []): static;

    /**
     * Adds a `select sql_calc_found_rows $fields` expression.
     *
     * @param Select|Stringable|string[]|string|int $fields
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function selectFoundRows(Select|Stringable|array|string|int $fields = []): static;

    /**
     * Adds a `select $suffix $fields` expression.
     *
     * @param string $suffix
     * @param Select|Stringable|string[]|string|int $fields
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function selectSuffix(string $suffix, Select|Stringable|array|string|int $fields = []): static;

    /**
     * Adds a `full join $table $fn()` expression.
     *
     * @param string $table
     * @param callable|null $fn
     *
     * @return QueryInterface<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function fullJoin(string $table, ?callable $fn = null): static;

    /**
     * Adds an `inner join $table $fn()` expression.
     *
     * @param string $table
     * @param callable|null $fn
     *
     * @return QueryInterface<TModel>
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
     * @return QueryInterface<TModel>
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
     * @return QueryInterface<TModel>
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
     * @return QueryInterface<TModel>
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
     * @return QueryInterface<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function rightJoin(string $table, ?callable $fn = null): static;

    /**
     * Adds a `with $name as ($query)` expression.
     *
     * @param string $name
     * @param QueryInterface $query
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function with(string $name, QueryInterface $query): static;

    /**
     * Adds a `with recursive $name as ($query)` expression.
     *
     * @param string $name
     * @param QueryInterface $query
     *
     * @return QueryInterface<TModel>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function withRecursive(string $name, QueryInterface $query): static;

}
