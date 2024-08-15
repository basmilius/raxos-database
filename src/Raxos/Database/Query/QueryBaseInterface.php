<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use BackedEnum;
use Generator;
use PDO;
use Raxos\Database\Error\{ConnectionException, ExecutionException, QueryException};
use Raxos\Database\Orm\{Error\RelationException, Error\StructureException, Model, ModelArrayList};
use Raxos\Database\Query\Struct\{ColumnLiteral, Literal, ValueInterface};
use Raxos\Foundation\Collection\ArrayList;
use stdClass;
use Stringable;

/**
 * Interface QueryInterface
 *
 * @template TModel
 *
 * @author Bas Milius <bas@glybe.nl>
 * @package Raxos\Database\Query
 * @since 1.0.0
 */
interface QueryBaseInterface
{

    /**
     * Adds an expression to the query.
     *
     * @param string $clause
     * @param BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $lhs
     * @param BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $cmp
     * @param BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $rhs
     *
     * @return $this
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function addExpression(
        string $clause,
        BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $lhs,
        BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $cmp,
        BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $rhs
    ): static;

    /**
     * Adds a param and returns its name or when not in prepared mode, returns the
     * value as string or int.
     *
     * @param BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $value
     *
     * @return string|int
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function addParam(BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $value): string|int;

    /**
     * Adds a query piece.
     *
     * @param string $clause
     * @param ColumnLiteral|array|string|int|null $data
     * @param string|null $separator
     *
     * @return $this
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
     * @return $this
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
     * @return $this
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
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function eagerLoad(string|array $relations): static;

    /**
     * Disables eager loading for the given relation(s).
     *
     * @param string|string[] $relations
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function eagerLoadDisable(string|array $relations): static;

    /**
     * Removes eager loading from the query.
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function eagerLoadReset(): static;

    /**
     * Merges the given query with the current one.
     *
     * @param QueryBaseInterface $query
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function merge(QueryBaseInterface $query): static;

    /**
     * Wraps the given function in parentheses.
     *
     * @param callable $fn
     * @param bool $patch
     *
     * @return $this
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function parenthesis(callable $fn, bool $patch = true): static;

    /**
     * Closes a parenthesis group.
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function parenthesisClose(): static;

    /**
     * Opens a parenthesis group.
     *
     * @param string|null $lhs
     * @param string|null $cmp
     * @param BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $rhs
     *
     * @return $this
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function parenthesisOpen(
        ?string $lhs = null,
        ?string $cmp = null,
        BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $rhs = null
    ): static;

    /**
     * Adds the given raw expression to the query.
     *
     * @param string $expression
     *
     * @return $this
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
     * Returns TRUE when a model is associated to the query.
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
     * @return $this
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
     * @return $this
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
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function withModel(string $class): static;

    /**
     * Removes the associated model.
     *
     * @return $this
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
     * @see Statement::array()
     */
    public function array(int $fetchMode = PDO::FETCH_ASSOC, array $options = []): array;

    /**
     * Runs the query and returns an ArrayList containing all the results.
     *
     * @param int $fetchMode
     * @param array $options
     *
     * @return ModelArrayList<int, TModel>|iterable<int, TModel>
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see Statement::arrayList()
     */
    public function arrayList(int $fetchMode = PDO::FETCH_ASSOC, array $options = []): ArrayList|ModelArrayList;

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
     * @see Statement::cursor()
     */
    public function cursor(int $fetchMode = PDO::FETCH_ASSOC, array $options = []): Generator;

    /**
     * Runs the query.
     *
     * @param array $options
     *
     * @throws ExecutionException
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see Statement::run()
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
     * Executes the query and returns the first row. When no result is found
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
    public function single(int $fetchMode = PDO::FETCH_ASSOC, array $options = []): Model|stdClass|array|null;

    /**
     * Executes the query and returns the first result. When no result is found
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
    public function singleOrFail(int $fetchMode = PDO::FETCH_ASSOC, array $options = []): Model|stdClass|array;

    /**
     * Creates a statement with the current query.
     *
     * @param array $options
     *
     * @return Statement
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function statement(array $options = []): Statement;

}
