<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use BackedEnum;
use Closure;
use Countable;
use Generator;
use JetBrains\PhpStorm\ArrayShape;

use JsonException;
use JsonSerializable;
use PDO;
use Raxos\Collection\ArrayList;
use Raxos\Collection\CursorPage;
use Raxos\Collection\Paginated;
use Raxos\Contract\Collection\ArrayableInterface;
use Raxos\Contract\Collection\ArrayListInterface;
use Raxos\Contract\Database\ConnectionInterface;
use Raxos\Contract\Database\DatabaseExceptionInterface;
use Raxos\Contract\Database\GrammarInterface;
use Raxos\Contract\Database\Orm\OrmExceptionInterface;
use Raxos\Contract\Database\Orm\PrimerInterface;
use Raxos\Contract\Database\Orm\PrimerTiming;
use Raxos\Contract\Database\Query\InternalQueryInterface;
use Raxos\Contract\Database\Query\KeysetQueryInterface;
use Raxos\Contract\Database\Query\QueryExceptionInterface;
use Raxos\Contract\Database\Query\QueryExpressionInterface;
use Raxos\Contract\Database\Query\QueryInterface;
use Raxos\Contract\Database\Query\QueryLiteralInterface;
use Raxos\Contract\Database\Query\QueryValueInterface;
use Raxos\Contract\Database\Query\StatementInterface;
use Raxos\Contract\DebuggableInterface;
use Raxos\Database\Orm\Definition\PropertyDefinition;
use Raxos\Database\Orm\Definition\RelationDefinition;
use Raxos\Database\Orm\Error\InvalidRelationException;
use Raxos\Database\Orm\Model;
use Raxos\Database\Orm\ModelArrayList;
use Raxos\Database\Orm\Structure\StructureGenerator;
use Raxos\Database\Query\Error\ConnectionErrorException;
use Raxos\Database\Query\Error\IncompleteException;
use Raxos\Database\Query\Error\InvalidCursorException;
use Raxos\Database\Query\Error\MissingAliasException;
use Raxos\Database\Query\Error\MissingClauseException;
use Raxos\Database\Query\Error\MissingModelException;
use Raxos\Database\Query\Error\MissingResultException;
use Raxos\Database\Query\Error\StructureErrorException;
use Raxos\Database\Query\Error\TooFewPrimaryKeyValuesException;
use Raxos\Database\Query\Error\TooManyPrimaryKeyValuesException;
use Raxos\Database\Query\Error\UnsupportedException;
use Raxos\Database\Query\Expression\ColumnRef;
use Raxos\Database\Query\Expression\KeysetPredicate;
use Raxos\Database\Query\Literal\Literal;
use stdClass;
use Stringable;
use function array_find_key;
use function array_is_list;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function array_pop;
use function array_shift;
use function array_splice;
use function array_unique;
use function array_unshift;
use function array_values;
use function count;
use function hash;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;
use function iterator_to_array;
use function json_encode;
use function preg_match;
use function preg_replace;
use function str_contains;
use function str_starts_with;
use function strrpos;
use function substr;
use function substr_count;
use function trim;

use const JSON_THROW_ON_ERROR;

/**
 * Class Query
 *
 * Builds bound SQL clauses and selects driver-aware row or ORM hydration.
 *
 * @template TModel
 * @implements QueryInterface<TModel>
 * @implements KeysetQueryInterface<TModel>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query
 * @since 1.0.0
 */
abstract class Query implements DebuggableInterface, InternalQueryInterface, JsonSerializable, KeysetQueryInterface, QueryInterface, Stringable
{
    /**
     * Assigns stable positions to query fragments as the builder evolves.
     *
     * @var int
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private static int $index = 0;

    /**
     * Compiles this builder using the selected database driver's syntax.
     *
     * @var GrammarInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public readonly GrammarInterface $grammar;

    /**
     * Directs new fragments to the clause currently being built.
     *
     * @var string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private string $currentClause = '';

    /**
     * Keeps join predicates separate from the surrounding WHERE clause.
     *
     * @var bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private bool $isDoingJoin = false;

    /**
     * Prevents adding a second initial ON clause to the current join.
     *
     * @var bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private bool $isOnDefined = false;

    /**
     * Selects ORM hydration when results should become models rather than plain row objects.
     *
     * @var class-string<Model>|null
     */
    private ?string $modelClass = null;

    /**
     * Retains clause fragments in builder order until the grammar compiles them.
     *
     * @var Piece[]
     */
    private array $pieces = [];

    /**
     * Records clause boundaries while preserving builder ordering.
     *
     * @var array
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private array $definedClauses = [];

    /**
     * Tracks the current position without rescanning earlier input.
     *
     * @var ?int
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private ?int $position = null;

    /**
     * Controls whether the ORM applies its default soft-delete predicate.
     *
     * @var bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private bool $withDeleted = false;

    /**
     * Collects relations that must be loaded after primary rows have been hydrated.
     *
     * @var array
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private array $eagerLoad = [];

    /**
     * Suppresses default eager relations for this query or statement.
     *
     * @var array
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private array $eagerLoadDisable = [];

    /**
     * Retains bound values independently from generated SQL identifiers.
     *
     * @var array
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private array $params = [];

    /**
     * Counts bound values without recounting the parameter collection.
     *
     * @var int
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private int $paramsCount = 0;

    /**
     * Allocates unique placeholders when fragments add bound values.
     *
     * @var int
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private readonly int $paramsIndex;

    /**
     * Runs deferred preparation before eager relationships are loaded.
     *
     * @var ?Closure
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private ?Closure $beforeRelations = null;

    /**
     * Collects assignments used to initialize models created from this query.
     *
     * @var array<string, list<PrimerInterface|callable>>
     */
    private array $primers = [];

    /**
     * Query constructor.
     *
     * @param ConnectionInterface $connection
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(
        public readonly ConnectionInterface $connection
    )
    {
        $this->grammar = $connection->grammar;
        $this->paramsIndex = ++self::$index;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function addExpression(
        string $clause,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $lhs = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $cmp = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $rhs = null
    ): static
    {
        if ($rhs === null && $cmp !== null) {
            $rhs = $cmp;

            // note(Bas): a ColumnRef is a bare column reference, not a
            //  self-contained predicate like Expr::in()/between(), so it still
            //  needs an explicit `=` operator between the two operands.
            if (!($rhs instanceof QueryExpressionInterface) || $rhs instanceof ColumnRef) {
                $cmp = '=';
            } else {
                $cmp = null;
            }
        }

        $this->addPiece($clause);

        $lhs !== null && $this->compile($lhs);
        $cmp !== null && $this->addPiece($cmp);
        $rhs !== null && $this->compile($rhs);

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function addParam(BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $value): string|int
    {
        if ($value instanceof QueryLiteralInterface) {
            return (string)$value;
        }

        if ($value instanceof QueryExpressionInterface) {
            return $this->compileColumnField($value);
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if ($value instanceof Stringable) {
            $value = (string)$value;
        }

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        $name = ":p{$this->paramsIndex}_{$this->paramsCount}";
        $this->params[$name] = $value;

        ++$this->paramsCount;

        return $name;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function addPiece(
        string $clause,
        QueryValueInterface|array|string|int|null $data = null,
        ?string $separator = null
    ): static
    {
        if ($data instanceof QueryValueInterface) {
            $data = $this->compileColumnField($data);
        }

        if ($clause === 'select' && $this->isClauseDefined('select')) {
            $index = array_find_key($this->pieces, static fn(Piece $piece) => $piece->clause === 'select');
            $existing = $this->pieces[$index]->data;

            $merged = match (true) {
                is_array($existing) && is_array($data) => [...$existing, ...$data],
                is_array($existing) => [...$existing, $data],
                is_array($data) => [$existing, ...$data],
                default => [$existing, $data]
            };

            $this->pieces[$index] = new Piece($this->pieces[$index]->clause, $merged, $this->pieces[$index]->separator);

            return $this;
        }

        if ($this->position !== null) {
            array_splice($this->pieces, $this->position++, 0, [new Piece($clause, $data, $separator)]);
        } else {
            $this->pieces[] = new Piece($clause, $data, $separator);
        }

        if (!empty($clause)) {
            $this->definedClauses[$clause] = true;
        }

        if ($clause !== '' && $clause !== '(' && $clause !== ')' && $clause !== ',') {
            $this->currentClause = $clause;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function compile(BackedEnum|Stringable|QueryValueInterface|string|int|float|bool $value): void
    {
        match (true) {
            $value instanceof QueryInterface => $this->parenthesis(fn() => $this->merge($value), patch: false),
            $value instanceof BackedEnum => match (true) {
                is_string($value->value) => $this->raw((string)stringLiteral($value->value)),
                default => $this->raw((string)literal($value->value)),
            },
            $value instanceof QueryExpressionInterface => $value->compile($this, $this->connection, $this->grammar),
            $value instanceof QueryLiteralInterface, is_int($value), is_float($value) => $this->raw((string)$value),
            $value instanceof Stringable => $this->raw((string)stringLiteral($value)),
            is_bool($value) => $this->raw($value ? '1' : '0'),
            default => $this->raw((string)$this->addParam($value))
        };
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function compileMultiple(
        iterable $values,
        string $separator = ', '
    ): void
    {
        foreach ($values as $index => $value) {
            if ($index > 0) {
                $this->raw($separator);
            }

            $this->compile($value);
        }
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function conditional(
        bool $is,
        callable $fn
    ): static
    {
        if ($is) {
            $fn($this);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function conditionalParenthesis(
        bool $is,
        callable $fn
    ): static
    {
        if ($is) {
            return $this->parenthesis($fn);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function eagerLoad(string|array $relations): static
    {
        if (is_string($relations)) {
            $this->eagerLoad[] = $relations;

            return $this;
        }

        foreach ($relations as $relation) {
            $this->eagerLoad[] = $relation;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function eagerLoadDisable(string|array $relations): static
    {
        if (is_string($relations)) {
            $this->eagerLoadDisable[] = $relations;

            return $this;
        }

        foreach ($relations as $relation) {
            $this->eagerLoadDisable[] = $relation;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function eagerLoadReset(): static
    {
        $this->eagerLoad = [];
        $this->eagerLoadDisable = [];

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function merge(QueryInterface $query): static
    {
        if ($this->position !== null) {
            array_splice($this->pieces, $this->position, 0, $query->pieces);
            $this->position += count($query->pieces);
        } else {
            array_push($this->pieces, ...$query->pieces);
        }
        $this->mergeParams($query);

        return $this;
    }

    /**
     * Merges the bound parameters of the given query into this one.
     *
     * @param QueryInterface $query
     *
     * @return void
     * @author Bas Milius <bas@mili.us>
     * @since 3.0.0
     * @internal
     */
    private function mergeParams(QueryInterface $query): void
    {
        $this->params = array_merge($this->params, $query->params);
        $this->paramsCount = count($this->params);
    }

    /**
     * Adopts the clause-tracking state of the given query so that later
     * clause calls continue correctly after wrapping an existing query. Unlike
     * {@see self::merge()} this does not copy any pieces; it only carries over
     * the defined clauses and the current clause.
     *
     * @param QueryInterface $query
     *
     * @return void
     * @author Bas Milius <bas@mili.us>
     * @since 2.3.0
     */
    protected function adoptClauseState(QueryInterface $query): void
    {
        $this->definedClauses = $query->definedClauses;
        $this->currentClause = $query->currentClause;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function parenthesis(
        callable $fn,
        bool $patch = true
    ): static
    {
        $originalPosition = $this->position;
        $originalClause = $this->currentClause;
        $index = $originalPosition ?? count($this->pieces);
        $this->position = $index;

        try {
            $this->parenthesisOpen();
            $fn($this);
            $this->parenthesisClose();
        } finally {
            if ($originalPosition === null) {
                $this->position = null;
            }
        }

        if ($patch && $this->pieces[$index + 1]->clause !== ')') {
            $clausePlusOne = $this->pieces[$index + 1]->clause;
            $open = $this->pieces[$index];
            $inner = $this->pieces[$index + 1];

            $this->pieces[$index] = new Piece(
                (!empty($clausePlusOne) ? $clausePlusOne . ' ' : '') . $open->clause,
                $open->data,
                $open->separator
            );

            $this->pieces[$index + 1] = new Piece('', $inner->data, $inner->separator);

            if ($originalPosition === null && !$this->isDoingJoin && in_array($clausePlusOne, ['where', 'and', 'or'], true)) {
                $depth = 0;

                for ($tail = 0; $tail < $index; ++$tail) {
                    $clause = $this->pieces[$tail]->clause;

                    if ($depth === 0 && in_array($clause, ['group by', 'having', 'order by', 'limit', 'offset', 'union', 'union all', 'returning', 'for update'], true)) {
                        $group = array_splice($this->pieces, $index);
                        array_splice($this->pieces, $tail, 0, $group);
                        $this->currentClause = $originalClause;

                        break;
                    }

                    $depth += self::parenthesisBalance($clause);
                }
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function parenthesisClose(): static
    {
        return $this->addPiece(')');
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function parenthesisOpen(
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $lhs = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $cmp = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $rhs = null
    ): static
    {
        return $this->addExpression('(', $lhs, $cmp, $rhs);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function raw(string $expression): static
    {
        if ($this->position !== null) {
            array_splice($this->pieces, $this->position++, 0, [new Piece($expression)]);
        } else {
            $this->pieces[] = new Piece($expression);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function isClauseDefined(string $clause): bool
    {
        return isset($this->definedClauses[$clause]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function isModelQuery(): bool
    {
        return $this->modelClass !== null;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function removeClause(string $clause): static
    {
        $index = array_find_key($this->pieces, static fn(Piece $piece) => $piece->clause === $clause);

        if ($index !== null) {
            array_splice($this->pieces, $index, 1);
            unset($this->definedClauses[$clause]);

            while (isset($this->pieces[$index]) && $this->pieces[$index]->clause === ',') {
                array_splice($this->pieces, $index, 1);
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function replaceClause(
        string $clause,
        callable $fn
    ): static
    {
        $index = array_find_key($this->pieces, static fn(Piece $piece) => $piece->clause === $clause);

        if ($index === null) {
            throw new MissingClauseException($clause);
        }

        $this->pieces[$index] = $fn($this->pieces[$index]);

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.6.1
     */
    public function withDeleted(): static
    {
        $this->withDeleted = true;

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function withModel(string $class): static
    {
        $this->modelClass = $class;

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function withoutModel(): static
    {
        $this->modelClass = null;
        $this->eagerLoad = [];

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function toSql(): string
    {
        $pieces = [];

        foreach ($this->filteredPieces() as $piece) {
            $data = $piece->data;

            if (is_array($data)) {
                $data = implode($piece->separator ?? $this->grammar->columnSeparator, $data);
            }

            $pieces[] = $piece->clause;

            if ($data !== null && $data !== '') {
                $pieces[] = $data;
            }
        }

        return implode(' ', $pieces);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function resultCount(): int
    {
        return $this->countResults(clone $this);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function totalCount(): int
    {
        $original = clone $this;
        $original->removeClause('limit');
        $original->removeClause('offset');
        $original->removeClause('order by');

        return $this->countResults($original);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.3.0
     */
    public function explain(): array
    {
        $explain = clone $this;

        array_unshift($explain->pieces, new Piece('explain'));

        $result = $explain
            ->withoutModel()
            ->single();

        $result['original_sql'] = $this->toSql();

        return $result;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function array(
        int $fetchMode = PDO::FETCH_ASSOC,
        array $options = []
    ): array
    {
        return $this
            ->statement($options)
            ->array($fetchMode);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function arrayList(
        int $fetchMode = PDO::FETCH_ASSOC,
        array $options = []
    ): ArrayListInterface|ModelArrayList
    {
        return $this
            ->statement($options)
            ->arrayList($fetchMode);
    }

    /**
     * {@inheritdoc}
     * @param int $fetchMode
     * @param array $options
     * @param int $batchSize
     * @param bool $retainCache
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function cursor(
        int $fetchMode = PDO::FETCH_ASSOC,
        array $options = [],
        int $batchSize = 100,
        bool $retainCache = false
    ): Generator
    {
        return $this
            ->statement($options)
            ->cursor($fetchMode, $batchSize, $retainCache);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.3.1
     */
    public function paginate(
        int $offset,
        int $limit,
        ?callable $itemBuilder = null,
        ?callable $totalBuilder = null,
        int $fetchMode = PDO::FETCH_ASSOC,
        array $options = []
    ): Paginated
    {
        return $this
            ->statement($options)
            ->paginate($offset, $limit, $itemBuilder, $totalBuilder, $fetchMode);
    }

    /**
     * Uses a non-null sort tuple ending in a unique key; replaces order and limits on a clone, without COUNT.
     *
     * @param int $size
     * @param string|null $cursor
     * @param list<string> $columns
     * @param bool $descending
     * @param array $options
     *
     * @return CursorPage<TModel|array>
     * @throws DatabaseExceptionInterface|QueryExceptionInterface|JsonException
     * @author Bas Milius <bas@mili.us>
     * @since 3.3.0
     */
    public function cursorPaginate(
        int $size = 25,
        ?string $cursor = null,
        array $columns = ['id'],
        bool $descending = false,
        array $options = []
    ): CursorPage
    {
        if ($size < 1 || $size > 10000 || $columns === [] || !array_is_list($columns)) {
            throw new InvalidCursorException('A page requires 1–10000 items and distinct ordered columns.');
        }

        foreach ($columns as $column) {
            if (!is_string($column) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)?$/D', $column)) {
                throw new InvalidCursorException('Cursor columns must be identifiers.');
            }
        }

        foreach (['group by', 'having', 'union', 'union all'] as $clause) {
            if ($this->isClauseDefined($clause)) {
                throw new InvalidCursorException('Grouped and union queries require an explicit outer keyset query.');
            }
        }

        if (count(array_unique($columns)) !== count($columns)) {
            throw new InvalidCursorException('Cursor columns must be distinct.');
        }

        $query = clone $this;
        $query->removeClause('order by')->removeClause('limit')->removeClause('offset');
        $query->groupWhereForKeyset();
        $signature = hash('sha256', json_encode([
            preg_replace('/:p[0-9]+_[0-9]+/', '?', $query->toSql()),
            array_values($query->params),
            $columns,
            $descending,
            $query->modelClass
        ], JSON_THROW_ON_ERROR));

        if ($cursor !== null) {
            $query->where(new KeysetPredicate(
                $columns,
                KeysetCursor::decode($cursor, $signature, count($columns)),
                $descending
            ));
        }

        foreach ($columns as $column) {
            $descending ? $query->orderByDesc($column) : $query->orderByAsc($column);
        }

        $rows = $query->limit($size + 1)->array(options: $options);
        $hasMore = count($rows) > $size;

        if ($hasMore) {
            array_pop($rows);
        }

        $position = [];

        foreach ($rows as $row) {
            $values = [];

            foreach ($columns as $column) {
                $key = str_contains($column, '.') ? substr($column, strrpos($column, '.') + 1) : $column;
                $values[] = KeysetCursor::value(is_array($row) ? ($row[$key] ?? null) : ($row->{$key} ?? null));
            }

            $position = $values;
        }

        return new CursorPage(
            new ArrayList($rows),
            $hasMore ? KeysetCursor::encode($signature, $position) : null,
            $hasMore
        );
    }

    /**
     * Closes each bounded result before eager loading and releases identities created by each batch. Sort keys must remain stable.
     *
     * @param int $batchSize
     * @param string|list<string> $column
     * @param bool $descending
     * @param bool $retainCache
     *
     * @return Generator<int, TModel|array>
     * @throws DatabaseExceptionInterface|QueryExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 3.3.0
     */
    public function lazyById(
        int $batchSize = 100,
        string|array $column = 'id',
        bool $descending = false,
        bool $retainCache = false
    ): Generator
    {
        $cursor = null;
        $columns = is_array($column) ? $column : [$column];

        do {
            $fetch = fn(): CursorPage => $this->cursorPaginate($batchSize, $cursor, $columns, $descending);
            $page = $retainCache ? $fetch() : $this->connection->cache->scope($fetch);

            foreach ($page->items as $item) {
                yield $item;
            }

            $cursor = $page->nextCursor;
        } while ($cursor !== null);
    }

    /**
     * A false callback result stops processing after the current batch.
     *
     * @param callable(ArrayList<int, TModel|array>):bool|void $fn
     * @param int $batchSize
     * @param string|list<string> $column
     * @param bool $descending
     * @param bool $retainCache
     *
     * @return void
     * @throws DatabaseExceptionInterface|QueryExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 3.3.0
     */
    public function chunkById(
        callable $fn,
        int $batchSize = 100,
        string|array $column = 'id',
        bool $descending = false,
        bool $retainCache = false
    ): void
    {
        $batch = [];

        foreach ($this->lazyById($batchSize, $column, $descending, $retainCache) as $row) {
            $batch[] = $row;

            if (count($batch) === $batchSize) {
                if ($fn(new ArrayList($batch)) === false) {
                    return;
                }

                $batch = [];
            }
        }

        if ($batch !== []) {
            $fn(new ArrayList($batch));
        }
    }

    /**
     * Keeps an existing OR filter grouped before appending the seek predicate.
     *
     * @return void
     * @author Bas Milius <bas@mili.us>
     * @since 3.3.0
     */
    private function groupWhereForKeyset(): void
    {
        $start = null;
        $end = count($this->pieces);
        $depth = 0;

        foreach ($this->pieces as $index => $piece) {
            if ($depth === 0 && ($piece->clause === 'where' || str_starts_with($piece->clause, 'where ('))) {
                $start = $index;
            } elseif ($start !== null && $depth === 0 && in_array($piece->clause, ['order by', 'limit', 'offset', 'for update', 'returning'], true)) {
                $end = $index;

                break;
            }

            $depth += self::parenthesisBalance($piece->clause);
        }

        if ($start !== null) {
            $piece = $this->pieces[$start];
            $this->pieces[$start] = new Piece($piece->clause . ' (', $piece->data, $piece->separator);
            array_splice($this->pieces, $end, 0, [new Piece(')')]);
        }
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function run(array $options = []): int
    {
        return $this
            ->statement($options)
            ->run();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function runReturning(QueryValueInterface|array|string $column): array|string|int
    {
        $sqlColumn = match (true) {
            is_array($column) => array_map($this->compileColumnField(...), $column),
            is_string($column) => $this->grammar->escape($column),
            default => $this->compileColumnField($column)
        };

        $statement = $this
            ->addPiece('returning', $sqlColumn, $this->grammar->columnSeparator)
            ->statement();

        $statement->run();

        if (!is_array($column)) {
            return $statement->pdoStatement->fetchColumn();
        }

        $data = $statement->pdoStatement->fetch(PDO::FETCH_ASSOC);
        $result = [];

        foreach ($column as $col) {
            if ($col instanceof ColumnRef) {
                $result[$col->column] = $data[$col->column];
            } else {
                $result[$col] = $data[$col];
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.1.0
     */
    public function runReturningRow(): array
    {
        $statement = $this
            ->addPiece('returning', '*')
            ->statement();

        $statement->run();

        return $statement->pdoStatement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function single(
        int $fetchMode = PDO::FETCH_ASSOC,
        array $options = []
    ): Model|stdClass|array|null
    {
        return $this
            ->statement($options)
            ->single($fetchMode);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function singleOrFail(
        int $fetchMode = PDO::FETCH_ASSOC,
        array $options = []
    ): Model|stdClass|array
    {
        $result = $this->single($fetchMode, $options);

        if ($result === null) {
            throw new MissingResultException();
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function statement(array $options = []): StatementInterface
    {
        if ($this->modelClass !== null) {
            try {
                $structure = StructureGenerator::for($this->modelClass);

                $statement = new Statement($this->connection, $this, $options);
                $statement->withModel($this->modelClass);
            } catch (OrmExceptionInterface $err) {
                throw new StructureErrorException($this->modelClass, $err);
            }
        } else {
            $statement = new Statement($this->connection, $this, $options);
        }

        $statement->eagerLoad($this->eagerLoad);
        $statement->eagerLoadDisable($this->eagerLoadDisable);

        foreach ($this->params as $name => $value) {
            $statement->bind($name, $value);
        }

        return $statement;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public function withQuery(callable $fn): static
    {
        return $fn($this);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public function setBeforeRelationsHook(callable $fn): QueryInterface
    {
        $this->beforeRelations = $fn;

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public function invokeBeforeRelationsHook(ArrayListInterface $instances): void
    {
        if ($this->beforeRelations === null) {
            return;
        }

        $beforeRelations = $this->beforeRelations;
        $beforeRelations($instances);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 3.0.0
     */
    public function prime(
        PrimerInterface|callable $primer,
        PrimerTiming $timing = PrimerTiming::AfterRelations
    ): static
    {
        $this->primers[$timing->name][] = $primer;

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 3.0.0
     */
    public function invokePrimers(
        ArrayListInterface $instances,
        PrimerTiming $timing,
        ConnectionInterface $connection
    ): void
    {
        foreach ($this->primers[$timing->name] ?? [] as $primer) {
            if ($primer instanceof PrimerInterface) {
                $primer->prime($instances, $connection);
            } else {
                $primer($instances, $connection);
            }
        }
    }

    /**
     * Resets the builder.
     *
     * @return static<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected final function reset(): static
    {
        $this->currentClause = '';
        $this->definedClauses = [];
        $this->modelClass = null;
        $this->params = [];
        $this->paramsCount = 0;
        $this->pieces = [];
        $this->primers = [];

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function delete(string $table): static
    {
        return $this->addPiece('delete', $this->grammar->escape($table));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function deleteFrom(string $table): static
    {
        return $this->addPiece('delete from', $this->grammar->escape($table));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function from(
        QueryInterface|array|string $tables,
        ?string $alias = null
    ): static
    {
        if ($tables instanceof self) {
            $this->addPiece('from');
            $this->parenthesisOpen();
            $this->raw($tables->toSql());
            $this->parenthesisClose();

            $this->mergeParams($tables);

            if ($alias !== null) {
                $this->addPiece('as', $alias);
            }

            return $this;
        }

        if (is_string($tables)) {
            $tables = [$tables];
        }

        $tables = array_map($this->grammar->escape(...), $tables);

        if ($alias !== null && count($tables) === 1) {
            $tables = array_map(static fn(string $table): string => "{$table} as {$alias}", $tables);
        }

        return $this->addPiece('from', $tables, $this->grammar->tableSeparator);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.2.0
     */
    public function forShare(): static
    {
        return $this->addPiece($this->grammar->compileForShare());
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.2.0
     */
    public function forUpdate(): static
    {
        return $this->addPiece($this->grammar->compileForUpdate());
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.2.0
     */
    public function nowait(): static
    {
        return $this->addPiece($this->grammar->compileLockNowait());
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.2.0
     */
    public function skipLocked(): static
    {
        return $this->addPiece($this->grammar->compileLockSkipLocked());
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function groupBy(
        QueryValueInterface|array|string $fields,
        bool $withRollup = false
    ): static
    {
        if (!is_array($fields)) {
            $fields = [$fields];
        }

        $fields = array_map($this->compileColumnField(...), $fields);

        $this->addPiece('group by', $fields, $this->grammar->columnSeparator);

        if ($withRollup) {
            $this->addPiece('with rollup');
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function having(
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $lhs = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $cmp = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $rhs = null
    ): static
    {
        return $this->addExpression($this->isClauseDefined('having') ? 'and' : 'having', $lhs, $cmp, $rhs);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function havingExists(QueryInterface $query): static
    {
        return $this->having(Expr::exists(Expr::subQuery($query)));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function havingIn(
        QueryValueInterface|string $field,
        ArrayableInterface|array $options
    ): static
    {
        if (self::isEmptyOptions($options)) {
            return $this->having(Literal::of('1 = 0'));
        }

        return $this->having($field, Expr::in(...$options));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.2
     */
    public function havingNotExists(QueryInterface $query): static
    {
        return $this->having(Expr::not(Expr::exists(Expr::subQuery($query))));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function havingNotNull(QueryValueInterface|string $field): static
    {
        return $this->having($field, Expr::isNotNull());
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.2
     */
    public function havingNotIn(
        QueryValueInterface|string $field,
        ArrayableInterface|array $options
    ): static
    {
        if (self::isEmptyOptions($options)) {
            return $this->having(Literal::of('1 = 1'));
        }

        return $this->having($field, Expr::not(Expr::in(...$options)));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function havingNull(QueryValueInterface|string $field): static
    {
        return $this->having($field, Expr::isNull());
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.1.0
     */
    public function orHaving(
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $lhs = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $cmp = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $rhs = null
    ): static
    {
        return $this->addExpression('or', $lhs, $cmp, $rhs);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.1.0
     */
    public function orHavingExists(QueryInterface $query): static
    {
        return $this->orHaving(Expr::exists(Expr::subQuery($query)));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.1.0
     */
    public function orHavingIn(
        QueryValueInterface|string $field,
        ArrayableInterface|array $options
    ): static
    {
        if (self::isEmptyOptions($options)) {
            return $this->orHaving(Literal::of('1 = 0'));
        }

        return $this->orHaving($field, Expr::in(...$options));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.1.0
     */
    public function orHavingNotExists(QueryInterface $query): static
    {
        return $this->orHaving(Expr::not(Expr::exists(Expr::subQuery($query))));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.1.0
     */
    public function orHavingNotIn(
        QueryValueInterface|string $field,
        ArrayableInterface|array $options
    ): static
    {
        if (self::isEmptyOptions($options)) {
            return $this->orHaving(Literal::of('1 = 1'));
        }

        return $this->orHaving($field, Expr::not(Expr::in(...$options)));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.1.0
     */
    public function orHavingNotNull(QueryValueInterface|string $field): static
    {
        return $this->orHaving($field, Expr::isNotNull());
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.1.0
     */
    public function orHavingNull(QueryValueInterface|string $field): static
    {
        return $this->orHaving($field, Expr::isNull());
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function limit(
        int $limit,
        int $offset = 0
    ): static
    {
        if ($limit < 0) {
            throw new IncompleteException("limit() expects a non-negative integer, got {$limit}.");
        }

        if ($offset < 0) {
            throw new IncompleteException("limit() offset must be non-negative, got {$offset}.");
        }

        $this->addPiece('limit', $limit);

        if ($offset > 0) {
            $this->offset($offset);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function offset(int $offset): static
    {
        if ($offset < 0) {
            throw new IncompleteException("offset() expects a non-negative integer, got {$offset}.");
        }

        return $this->addPiece('offset', $offset);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function on(
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $lhs,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $cmp = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $rhs = null
    ): static
    {
        $didOn = $this->isOnDefined;
        $this->isOnDefined = true;

        return $this->addExpression($didOn ? 'and' : 'on', $lhs, $cmp, $rhs);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function onDuplicateKeyUpdate(array|string $fields): static
    {
        if (is_string($fields)) {
            $fields = [$fields];
        }

        foreach ($fields as $key => $value) {
            if ($value instanceof ColumnRef) {
                $value = $value->column;
            }

            if (str_contains($value, '=')) {
                $fields[$key] = $value;
            } else {
                $value = $this->grammar->escape($value);
                $fields[$key] = "{$value} = VALUES({$value})";
            }
        }

        return $this->addPiece('on duplicate key update', $fields, $this->grammar->columnSeparator);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orWhere(
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $lhs = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $cmp = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $rhs = null
    ): static
    {
        return $this->addWhereExpression('or', $lhs, $cmp, $rhs);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orWhereExists(QueryInterface $query): static
    {
        return $this->orWhere(Expr::exists(Expr::subQuery($query)));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orWhereHas(
        string $relation,
        ?callable $fn = null
    ): static
    {
        return $this->baseWhereHas($relation, $fn, $this->orWhere(...));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orWhereIn(
        QueryValueInterface|string $field,
        ArrayableInterface|array $options
    ): static
    {
        if (self::isEmptyOptions($options)) {
            return $this->orWhere(Literal::of('1 = 0'));
        }

        return $this->orWhere($field, Expr::in(...$options));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.2
     */
    public function orWhereNotExists(QueryInterface $query): static
    {
        return $this->orWhere(Expr::not(Expr::exists(Expr::subQuery($query))));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orWhereNotHas(
        string $relation,
        ?callable $fn = null
    ): static
    {
        return $this->baseWhereHas($relation, $fn, $this->orWhere(...), true);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.2
     */
    public function orWhereNotIn(
        QueryValueInterface|string $field,
        ArrayableInterface|array $options
    ): static
    {
        if (self::isEmptyOptions($options)) {
            return $this->orWhere(Literal::of('1 = 1'));
        }

        return $this->orWhere($field, Expr::not(Expr::in(...$options)));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orWhereNotNull(QueryValueInterface|string $field): static
    {
        return $this->orWhere($field, Expr::isNotNull());
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orWhereNull(QueryValueInterface|string $field): static
    {
        return $this->orWhere($field, Expr::isNull());
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orWhereRelation(
        string $relation,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $lhs = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $cmp = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $rhs = null
    ): static
    {
        return $this->orWhereHas($relation, $lhs !== null ? static fn(self $query) => $query->where($lhs, $cmp, $rhs) : null);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orderBy(QueryValueInterface|array|string $fields): static
    {
        if (!is_array($fields)) {
            $fields = [$fields];
        }

        $fields = array_map(function (QueryValueInterface|string $field): string {
            if (is_string($field) && preg_match('/^(.+)\s+(asc|desc)$/i', $field, $matches) === 1) {
                return $this->grammar->escape(trim($matches[1])) . ' ' . strtolower($matches[2]);
            }

            return $this->compileColumnField($field);
        }, $fields);

        return $this->addPiece('order by', $fields, $this->grammar->columnSeparator);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orderByAsc(QueryValueInterface|string $field): static
    {
        $clause = $this->currentClause === 'order by' ? trim($this->grammar->columnSeparator) : 'order by';

        return $this->addPiece($clause, $this->compileColumnField($field) . ' ' . SortDirection::ASC->value);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orderByDesc(QueryValueInterface|string $field): static
    {
        $clause = $this->currentClause === 'order by' ? trim($this->grammar->columnSeparator) : 'order by';

        return $this->addPiece($clause, $this->compileColumnField($field) . ' ' . SortDirection::DESC->value);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function set(
        Stringable|QueryValueInterface|string $field,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $value
    ): static
    {
        $value = $this->addParam($value);
        $expression = $this->compileColumnField($field) . ' = ' . $value;

        if ($this->currentClause === 'set') {
            $index = count($this->pieces) - 1;
            $existing = $this->pieces[$index]->data;

            if (!is_array($existing)) {
                $existing = [$existing];
            }

            $existing[] = $expression;

            $this->pieces[$index] = new Piece($this->pieces[$index]->clause, $existing, $this->pieces[$index]->separator);
        } else {
            $this->addPiece('set', $expression, $this->grammar->columnSeparator);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function union(QueryInterface $query): static
    {
        $this->addPiece('union');

        return $this->merge($query);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function unionAll(QueryInterface $query): static
    {
        $this->addPiece('union all');

        return $this->merge($query);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function update(
        string $table,
        ?array $pairs = null
    ): static
    {
        $this->addPiece('update', $this->grammar->escape($table));

        if ($pairs === null) {
            return $this;
        }

        foreach ($pairs as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function values(array $values): static
    {
        $values = array_map($this->addParam(...), $values);

        $this->addPiece($this->isClauseDefined('values') ? ', ' : 'values');
        $this->parenthesis(fn() => $this->addPiece('', $values, $this->grammar->columnSeparator));

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function where(
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $lhs = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $cmp = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $rhs = null
    ): static
    {
        return $this->addWhereExpression($this->isClauseDefined('where') || $this->isDoingJoin ? 'and' : 'where', $lhs, $cmp, $rhs);
    }

    /**
     * Treats the left operand as a column identifier.
     *
     * @param string $field
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $cmp
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $rhs
     *
     * @return static
     * @author Bas Milius <bas@mili.us>
     * @since 3.3.0
     */
    public function whereField(
        string $field,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $cmp = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $rhs = null
    ): static
    {
        return $this->where(new ColumnRef($field), $cmp, $rhs);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function whereExists(QueryInterface $query): static
    {
        return $this->where(Expr::exists(Expr::subQuery($query)));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function whereHas(
        string $relation,
        ?callable $fn = null
    ): static
    {
        return $this->baseWhereHas($relation, $fn, $this->where(...));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function whereIn(
        QueryValueInterface|string $field,
        ArrayableInterface|array $options
    ): static
    {
        if (self::isEmptyOptions($options)) {
            return $this->where(Literal::of('1 = 0'));
        }

        return $this->where($field, Expr::in(...$options));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.2
     */
    public function whereNotExists(QueryInterface $query): static
    {
        return $this->where(Expr::not(Expr::exists(Expr::subQuery($query))));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function whereNotHas(
        string $relation,
        ?callable $fn = null
    ): static
    {
        return $this->baseWhereHas($relation, $fn, $this->where(...), true);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.2
     */
    public function whereNotIn(
        QueryValueInterface|string $field,
        ArrayableInterface|array $options
    ): static
    {
        if (self::isEmptyOptions($options)) {
            return $this->where(Literal::of('1 = 1'));
        }

        return $this->where($field, Expr::not(Expr::in(...$options)));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function whereNotNull(QueryValueInterface|string $field): static
    {
        return $this->where($field, Expr::isNotNull());
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function whereNull(QueryValueInterface|string $field): static
    {
        return $this->where($field, Expr::isNull());
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function wherePrimaryKey(
        string $modelClass,
        array|int|string $primaryKey
    ): static
    {
        if (!is_array($primaryKey)) {
            $primaryKey = [$primaryKey];
        }

        $structure = StructureGenerator::for($modelClass);

        foreach ($structure->primaryKey as $property) {
            if (empty($primaryKey)) {
                throw new TooFewPrimaryKeyValuesException($modelClass);
            }

            $value = array_shift($primaryKey);

            if (is_int($value) || is_float($value)) {
                $value = literal($value);
            }

            $this->where($structure->getColumn($property->name), $value);
        }

        if (!empty($primaryKey)) {
            throw new TooManyPrimaryKeyValuesException($modelClass);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function wherePrimaryKeyIn(
        string $modelClass,
        array $primaryKeys
    ): static
    {
        if (empty($primaryKeys)) {
            return $this->where(Literal::of('1 = 0'));
        }

        $structure = StructureGenerator::for($modelClass);
        $properties = $structure->primaryKey;

        if (count($properties) === 1) {
            return $this->where($structure->getColumn($properties[0]->key), Expr::in(...$primaryKeys));
        }

        $columns = array_map(static fn(PropertyDefinition $property) => $structure->getColumn($property->name), $properties);

        if ($this->grammar->supportsRowValueConstructors) {
            // Generate: (`col1`, `col2`) IN ((v1, v2), (v3, v4))
            $colTuple = '(' . implode(', ', array_map($this->compileColumnField(...), $columns)) . ')';
            $rowTuples = [];

            foreach ($primaryKeys as $primaryKey) {
                $values = is_array($primaryKey) ? $primaryKey : [$primaryKey];
                $params = array_map(fn(mixed $value) => (string)$this->addParam(
                    is_int($value) || is_float($value) ? literal($value) : $value
                ), $values);
                $rowTuples[] = '(' . implode(', ', $params) . ')';
            }

            return $this->where(Literal::of($colTuple . ' in (' . implode(', ', $rowTuples) . ')'));
        }

        // Fallback for databases without row value constructor support
        $this->where('1 = 1');

        foreach ($primaryKeys as $index => $primaryKey) {
            $this->parenthesis(function () use ($index, $primaryKey, $columns): void {
                foreach ($primaryKey as $columnIndex => $value) {
                    if ($columnIndex === 0 && $index > 0) {
                        $this->orWhere($columns[$columnIndex], $value);
                    } else {
                        $this->where($columns[$columnIndex], $value);
                    }
                }
            });
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function whereRelation(
        string $relation,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $lhs = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $cmp = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $rhs = null
    ): static
    {
        return $this->whereHas($relation, $lhs !== null ? static fn(self $query) => $query->where($lhs, $cmp, $rhs) : null);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function insertInto(
        string $table,
        array $fields
    ): static
    {
        return $this->baseInsert('insert into', $table, $fields);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function insertIgnoreInto(
        string $table,
        array $fields
    ): static
    {
        return $this->baseInsert('insert ignore into', $table, $fields);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function insertIntoValues(
        string $table,
        array $pairs
    ): static
    {
        if (empty($pairs)) {
            throw new IncompleteException('There must be at least one column.');
        }

        if (array_is_list($pairs)) {
            $this->insertInto($table, array_keys($pairs[0]));

            foreach ($pairs as $pair) {
                $this->values(array_values($pair));
            }
        } else {
            $this->insertInto($table, array_keys($pairs));
            $this->values(array_values($pairs));
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function insertIgnoreIntoValues(
        string $table,
        array $pairs
    ): static
    {
        if (empty($pairs)) {
            throw new IncompleteException('There must be at least one column.');
        }

        if (array_is_list($pairs)) {
            $this->insertIgnoreInto($table, array_keys($pairs[0]));

            foreach ($pairs as $pair) {
                $this->values(array_values($pair));
            }
        } else {
            $this->insertIgnoreInto($table, array_keys($pairs));
            $this->values(array_values($pairs));
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function replaceInto(
        string $table,
        array $fields
    ): static
    {
        return $this->baseInsert('replace into', $table, $fields);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function replaceIntoValues(
        string $table,
        array $pairs
    ): static
    {
        $fields = array_keys($pairs);
        $values = array_values($pairs);

        $this->replaceInto($table, $fields);
        $this->values($values);

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function select(QueryInterface|QueryExpressionInterface|QueryLiteralInterface|Stringable|array|string|int|float|bool ...$fields): static
    {
        return $this->baseSelect('select', $this->normalizeSelect($fields));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function selectDistinct(QueryInterface|QueryExpressionInterface|QueryLiteralInterface|Stringable|array|string|int|float|bool ...$fields): static
    {
        return $this->selectSuffix('distinct', ...$fields);
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated 2.3.0 `SQL_CALC_FOUND_ROWS` is deprecated in MySQL 8.0.17
     *   and removed in MySQL 8.4. Use {@see self::totalCount()} (a separate
     *   `COUNT(*)` query) instead.
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function selectFoundRows(QueryInterface|QueryExpressionInterface|QueryLiteralInterface|Stringable|array|string|int|float|bool ...$fields): static
    {
        return $this->selectSuffix('sql_calc_found_rows', ...$fields);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function selectSuffix(
        string $suffix,
        QueryInterface|QueryExpressionInterface|QueryLiteralInterface|Stringable|array|string|int|float|bool ...$fields
    ): static
    {
        return $this->baseSelect("select {$suffix}", $this->normalizeSelect($fields));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function fullJoin(
        string $table,
        ?callable $fn = null
    ): static
    {
        return $this->baseJoin('full join', $table, $fn);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function innerJoin(
        string $table,
        ?callable $fn = null
    ): static
    {
        return $this->baseJoin('inner join', $table, $fn);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function join(
        string $table,
        ?callable $fn = null
    ): static
    {
        return $this->baseJoin('join', $table, $fn);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function leftJoin(
        string $table,
        ?callable $fn = null
    ): static
    {
        return $this->baseJoin('left join', $table, $fn);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function leftOuterJoin(
        string $table,
        ?callable $fn = null
    ): static
    {
        return $this->baseJoin('left outer join', $table, $fn);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function rightJoin(
        string $table,
        ?callable $fn = null
    ): static
    {
        return $this->baseJoin('right join', $table, $fn);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 3.0.0
     */
    public function fullJoinSub(
        QueryInterface $query,
        string $alias,
        ?callable $on = null
    ): static
    {
        return $this->baseJoinSub('full join', $query, $alias, $on);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 3.0.0
     */
    public function innerJoinSub(
        QueryInterface $query,
        string $alias,
        ?callable $on = null
    ): static
    {
        return $this->baseJoinSub('inner join', $query, $alias, $on);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 3.0.0
     */
    public function joinSub(
        QueryInterface $query,
        string $alias,
        ?callable $on = null
    ): static
    {
        return $this->baseJoinSub('join', $query, $alias, $on);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 3.0.0
     */
    public function leftJoinSub(
        QueryInterface $query,
        string $alias,
        ?callable $on = null
    ): static
    {
        return $this->baseJoinSub('left join', $query, $alias, $on);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 3.0.0
     */
    public function leftOuterJoinSub(
        QueryInterface $query,
        string $alias,
        ?callable $on = null
    ): static
    {
        return $this->baseJoinSub('left outer join', $query, $alias, $on);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 3.0.0
     */
    public function rightJoinSub(
        QueryInterface $query,
        string $alias,
        ?callable $on = null
    ): static
    {
        return $this->baseJoinSub('right join', $query, $alias, $on);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function with(
        string $name,
        QueryInterface $query
    ): static
    {
        return $this->baseWith('with', $name, $query);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function withRecursive(
        string $name,
        QueryInterface $query
    ): static
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
     * @throws QueryExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function baseInsert(
        string $clause,
        string $table,
        array $fields
    ): static
    {
        if (empty($fields)) {
            throw new IncompleteException('There must be at least one column.');
        }

        $fields = array_map($this->grammar->escape(...), $fields);

        $this->addPiece($clause, $this->grammar->escape($table));
        $this->parenthesis(fn() => $this->addPiece('', $fields, $this->grammar->columnSeparator));

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
     * @throws QueryExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function baseJoin(
        string $clause,
        string $table,
        ?callable $fn
    ): static
    {
        $table = $this->grammar->escape($table);
        $wherePosition = null;

        foreach ($this->pieces as $index => $piece) {
            if ($piece->clause === 'where') {
                $wherePosition = $index;
            }

            // note(Bas): joins are idempotent on (table, alias). A second
            //  call with the same identifier is a no-op (callback included)
            //  to avoid duplicating ON / AND clauses. To join the same
            //  physical table multiple times, give each occurrence a unique
            //  alias (e.g. `users as u1`, `users as u2`).
            if (str_contains($piece->clause, 'join') && $piece->data === $table) {
                return $this;
            }
        }

        $this->position = $wherePosition;
        $this->addPiece($clause, $table);
        $this->isOnDefined = false;

        if ($fn !== null) {
            $this->isDoingJoin = true;
            $fn($this);
            $this->isDoingJoin = false;
        }

        $this->isOnDefined = false;
        $this->position = null;

        return $this;
    }

    /**
     * Base function to create joinable derived table (`join (subquery) as alias`)
     * expressions. Intentionally uses `raw(toSql())` + `mergeParams()` instead of
     * `merge()`, so the splice order of joins before `where` stays intact.
     *
     * @param string $clause
     * @param QueryInterface $query
     * @param string $alias
     * @param callable|null $on
     *
     * @return static<TModel>
     * @throws QueryExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 3.0.0
     */
    protected function baseJoinSub(
        string $clause,
        QueryInterface $query,
        string $alias,
        ?callable $on
    ): static
    {
        $aliasEscaped = $this->grammar->escape($alias);
        $wherePosition = null;

        foreach ($this->pieces as $index => $piece) {
            if ($piece->clause === 'where') {
                $wherePosition = $index;
            }

            // note(Bas): derived-table joins are idempotent on their alias. A
            //  second call with the same alias is a no-op (callback included)
            //  to avoid duplicating the subquery and its ON clauses.
            if ($piece->clause === 'as' && $piece->data === $aliasEscaped) {
                return $this;
            }
        }

        $this->position = $wherePosition;
        $this->addPiece($clause);
        $this->parenthesisOpen();
        $this->raw($query->toSql());
        $this->parenthesisClose();
        $this->addPiece('as', $aliasEscaped);
        $this->mergeParams($query);

        $this->isOnDefined = false;

        if ($on !== null) {
            $this->isDoingJoin = true;
            $on($this);
            $this->isDoingJoin = false;
        }

        $this->isOnDefined = false;
        $this->position = null;

        return $this;
    }

    /**
     * Base function to create `select` expressions.
     *
     * @param string $clause
     * @param array $fields
     *
     * @return static<TModel>
     * @throws OrmExceptionInterface
     * @throws QueryExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function baseSelect(
        string $clause,
        array $fields
    ): static
    {
        if (empty($fields)) {
            return $this->addPiece($clause, $this->modelClass !== null
                ? $this->compileColumnField($this->modelClass::col('*'))
                : '*');
        }

        return $this->addPiece(
            $clause,
            array_unique(iterator_to_array($this->unwrapSelect($fields))),
            $this->grammar->columnSeparator
        );
    }

    /**
     * Base function to create `[where|and|or] (not) exists (query)` expressions.
     *
     * @param string $relation
     * @param callable|null $fn
     * @param callable $clause
     * @param bool $negate
     *
     * @return static<TModel>
     * @throws OrmExceptionInterface
     * @throws QueryExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function baseWhereHas(
        string $relation,
        ?callable $fn,
        callable $clause,
        bool $negate = false
    ): static
    {
        if ($this->modelClass === null) {
            throw new MissingModelException();
        }

        $structure = StructureGenerator::for($this->modelClass);
        $property = $structure->getProperty($relation);

        if (!($property instanceof RelationDefinition)) {
            throw new InvalidRelationException($structure->class, $property->name);
        }

        try {
            $relation = $structure->getRelation($property);
            $query = $relation->rawQuery();

            if ($fn !== null) {
                $fn($query);
            }

            if ($negate) {
                $clause(Expr::not(Expr::exists(Expr::subQuery($query))));
            } else {
                $clause(Expr::exists(Expr::subQuery($query)));
            }

            return $this;
        } catch (DatabaseExceptionInterface $err) {
            throw new ConnectionErrorException($err);
        }
    }

    /**
     * Adds a grouped predicate without changing the connector used by the surrounding query.
     *
     * @param string $clause
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $lhs
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $cmp
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $rhs
     *
     * @return static
     * @author Bas Milius <bas@mili.us>
     * @since 3.2.0
     */
    private function addWhereExpression(
        string $clause,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $lhs,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $cmp,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $rhs
    ): static
    {
        if ($this->position !== null || $this->isDoingJoin) {
            return $this->addExpression($clause, $lhs, $cmp, $rhs);
        }

        $depth = 0;

        foreach ($this->pieces as $index => $piece) {
            if ($depth === 0 && in_array($piece->clause, ['group by', 'having', 'order by', 'limit', 'offset', 'union', 'union all', 'returning', 'for update'], true)) {
                $currentClause = $this->currentClause;
                $this->position = $index;

                try {
                    return $this->addExpression($clause, $lhs, $cmp, $rhs);
                } finally {
                    $this->position = null;
                    $this->currentClause = $currentClause;
                }
            }

            $depth += self::parenthesisBalance($piece->clause);
        }

        return $this->addExpression($clause, $lhs, $cmp, $rhs);
    }

    /**
     * Base function to create `with` expressions.
     *
     * @param string $clause
     * @param string $name
     * @param QueryInterface $query
     *
     * @return static<TModel>
     * @throws QueryExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function baseWith(
        string $clause,
        string $name,
        QueryInterface $query
    ): static
    {
        $this->addPiece($this->currentClause === $clause ? ',' : $clause, "{$name} as");
        $this->parenthesis(fn() => $this->merge($query), false);

        return $this;
    }

    /**
     * Normalizes the variadic select fields. A single pre-built array passed
     * as the only argument is unwrapped, so both `select($a, $b)` and
     * `select([$a, $b])` yield the same field set.
     *
     * @param array $fields
     *
     * @return array
     * @author Bas Milius <bas@mili.us>
     * @since 3.0.0
     * @see self::select()
     */
    private function normalizeSelect(array $fields): array
    {
        if (count($fields) === 1 && array_key_exists(0, $fields) && is_array($fields[0])) {
            return $fields[0];
        }

        return $fields;
    }

    /**
     * Unwraps the select fields into their rendered SQL form. An `int` key
     * yields no alias, a `string` key yields an identifier-escaped alias.
     * Sub-queries and expressions merge their bound parameters into the host;
     * an aliasless expression is rendered bare. Scalar `int`/`float`/`bool`
     * values are emitted as literals; array values are not supported.
     *
     * @param array $fields
     *
     * @return Generator
     * @throws QueryExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 3.0.0
     * @see self::baseSelect()
     */
    private function unwrapSelect(array $fields): Generator
    {
        foreach ($fields as $key => $value) {
            $alias = !is_numeric($key) ? $this->grammar->escape((string)$key) : null;

            if ($value instanceof QueryInterface) {
                if ($alias === null) {
                    throw new MissingAliasException();
                }

                $this->mergeParams($value);

                yield "({$value}) as {$alias}";
            } elseif ($value instanceof QueryExpressionInterface) {
                $sub = new static($this->connection);
                $value->compile($sub, $this->connection, $this->grammar);
                $this->mergeParams($sub);

                yield $alias !== null ? "({$sub}) as {$alias}" : (string)$sub;
            } elseif ($value instanceof QueryLiteralInterface) {
                yield $alias !== null ? "{$value} as {$alias}" : (string)$value;
            } elseif (is_int($value) || is_float($value) || is_bool($value)) {
                $literal = is_bool($value) ? (string)(int)$value : (string)$value;

                yield $alias !== null ? "{$literal} as {$alias}" : $literal;
            } elseif (is_array($value)) {
                throw new UnsupportedException('A select field value cannot be an array; alias a column with a string key instead.');
            } else {
                $escaped = $this->grammar->escape((string)$value);

                yield $alias !== null ? "{$escaped} as {$alias}" : $escaped;
            }
        }
    }

    /**
     * Renders a column-referencing field to its SQL form. An expression (e.g.,
     * a {@see ColumnRef}) is compiled and its bound parameters merged
     * into the host; a literal is stringified as-is; a plain string is escaped
     * as a column identifier.
     *
     * @param QueryValueInterface|Stringable|string $field
     *
     * @return string
     * @throws QueryExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 3.0.0
     */
    private function compileColumnField(QueryValueInterface|Stringable|string $field): string
    {
        if ($field instanceof QueryExpressionInterface) {
            $sub = new static($this->connection);
            $field->compile($sub, $this->connection, $this->grammar);
            $this->mergeParams($sub);

            return (string)$sub;
        }

        if ($field instanceof QueryLiteralInterface) {
            return (string)$field;
        }

        return $this->grammar->escape((string)$field);
    }

    /**
     * Distinguishes an absent option list from an explicit collection of query options.
     *
     * @param ArrayableInterface|array $options
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 2.3.0
     */
    private static function isEmptyOptions(ArrayableInterface|array $options): bool
    {
        if (is_array($options)) {
            return empty($options);
        }

        if ($options instanceof Countable) {
            return count($options) === 0;
        }

        return empty($options->toArray());
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function jsonSerialize(): string
    {
        return $this->toSql();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[ArrayShape([
        'sql' => 'string',
        'type' => 'string',
        'params' => 'array'
    ])]
    public function __debugInfo(): array
    {
        return [
            'sql' => $this->toSql(),
            'type' => 'PREPARED QUERY',
            'params' => $this->params
        ];
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __toString(): string
    {
        return $this->toSql();
    }

    /**
     * Counts the result rows, preserving DISTINCT, grouping, HAVING and pagination.
     *
     * @param self $query
     *
     * @return int
     * @author Bas Milius <bas@mili.us>
     * @since 3.2.0
     */
    protected function countResults(self $query): int
    {
        $counter = $this->connection->query();

        if ($counter instanceof self && $counter::class !== static::class) {
            return $counter->countResults($query);
        }

        // Nested builders merge stored pieces, so materialize implicit model filters first.
        $query->pieces = $query->filteredPieces();
        $query->withDeleted = true;

        return (int)$counter
            ->select(Literal::of('count(*)'))
            ->from($query, '__raxos_count')
            ->statement()
            ->fetchColumn();
    }

    /**
     * Removes excluded clauses when deriving count and continuation queries.
     *
     * @return Piece[]
     * @author Bas Milius <bas@mili.us>
     * @since 3.2.0
     */
    private function filteredPieces(): array
    {
        if ($this->modelClass === null || $this->withDeleted || !$this->isClauseDefined('select')) {
            return $this->pieces;
        }

        $structure = StructureGenerator::for($this->modelClass);

        if ($structure->softDeleteColumn === null) {
            return $this->pieces;
        }

        $column = $this->compileColumnField($structure->getColumn($structure->softDeleteColumn));
        $pieces = $this->pieces;
        $where = null;
        $end = count($pieces);
        $depth = 0;

        foreach ($pieces as $index => $piece) {
            if ($depth === 0 && in_array($piece->clause, ['where', 'where ('], true)) {
                $where = $index;
            } elseif ($depth === 0 && in_array($piece->clause, ['group by', 'having', 'order by', 'limit', 'offset', 'union', 'union all', 'returning', 'for update'], true)) {
                $end = $index;

                break;
            }

            $depth += self::parenthesisBalance($piece->clause);
        }

        if ($where === null) {
            array_splice($pieces, $end, 0, [new Piece('where', "{$column} is null")]);
        } else {
            $predicate = $pieces[$where]->data;

            if (is_array($predicate)) {
                $predicate = implode($pieces[$where]->separator ?? $this->grammar->columnSeparator, $predicate);
            }

            if ($pieces[$where]->clause === 'where (') {
                $predicate = '( ' . $predicate;
            }

            $pieces[$where] = new Piece('where', "{$column} is null and ( {$predicate}", $pieces[$where]->separator);
            array_splice($pieces, $end, 0, [new Piece(')')]);
        }

        return $pieces;
    }

    /**
     * Counts structural parentheses, excluding quoted literals, identifiers and comments.
     *
     * @param string $clause
     *
     * @return int
     * @author Bas Milius <bas@mili.us>
     * @since 3.2.0
     */
    private static function parenthesisBalance(string $clause): int
    {
        if (!str_contains($clause, '(') && !str_contains($clause, ')')) {
            return 0;
        }

        $syntax = preg_replace(
            <<<'REGEX'
~'(?:''|\\.|[^'\\])*'|"(?:""|\\.|[^"\\])*"|\x60(?:\x60\x60|[^\x60])*\x60|/\*.*?\*/|--(?=\s|$)[^\r\n]*|#[^\r\n]*~s
REGEX,
            '',
            $clause
        );

        return substr_count($syntax, '(') - substr_count($syntax, ')');
    }
}
