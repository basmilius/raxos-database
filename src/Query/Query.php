<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use BackedEnum;
use Closure;
use Countable;
use Generator;
use JetBrains\PhpStorm\ArrayShape;
use JsonSerializable;
use PDO;
use Raxos\Collection\Paginated;
use Raxos\Contract\Collection\{ArrayableInterface, ArrayListInterface};
use Raxos\Contract\Database\{ConnectionInterface, DatabaseExceptionInterface, GrammarInterface};
use Raxos\Contract\Database\Orm\OrmExceptionInterface;
use Raxos\Contract\Database\Query\{InternalQueryInterface, QueryExceptionInterface, QueryExpressionInterface, QueryInterface, QueryLiteralInterface, QueryValueInterface, StatementInterface};
use Raxos\Contract\DebuggableInterface;
use Raxos\Database\Orm\{Model, ModelArrayList};
use Raxos\Database\Orm\Definition\{PropertyDefinition, RelationDefinition};
use Raxos\Database\Orm\Error\InvalidRelationException;
use Raxos\Database\Orm\Structure\StructureGenerator;
use Raxos\Database\Query\Error\{ConnectionErrorException, IncompleteException, MissingAliasException, MissingClauseException, MissingModelException, MissingResultException, StructureErrorException, TooFewPrimaryKeyValuesException, TooManyPrimaryKeyValuesException};
use Raxos\Database\Query\Expression\ColumnRef;
use Raxos\Database\Query\Literal\Literal;
use stdClass;
use Stringable;
use function array_find_key;
use function array_is_list;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function array_shift;
use function array_splice;
use function array_unique;
use function array_unshift;
use function array_values;
use function count;
use function implode;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;
use function iterator_to_array;
use function str_contains;
use function trim;

/**
 * Class Query
 *
 * @template TModel
 * @implements QueryInterface<TModel>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query
 * @since 1.0.0
 */
abstract class Query implements DebuggableInterface, InternalQueryInterface, JsonSerializable, QueryInterface, Stringable
{

    private static int $index = 0;

    public readonly GrammarInterface $grammar;

    private string $currentClause = '';
    private bool $isDoingJoin = false;
    private bool $isOnDefined = false;
    /** @var class-string<Model>|null */
    private ?string $modelClass = null;
    /** @var Piece[] */
    private array $pieces = [];
    private array $definedClauses = [];
    private ?int $position = null;
    private bool $withDeleted = false;

    private array $eagerLoad = [];
    private array $eagerLoadDisable = [];
    private array $params = [];
    private int $paramsCount = 0;
    private readonly int $paramsIndex;

    private ?Closure $beforeRelations = null;

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
            $value->compile($this, $this->connection, $this->grammar);
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
    public function addPiece(string $clause, QueryValueInterface|array|string|int|null $data = null, ?string $separator = null): static
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
    public function compileMultiple(iterable $values, string $separator = ', '): void
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
    public function conditional(bool $is, callable $fn): static
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
    public function conditionalParenthesis(bool $is, callable $fn): static
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
        array_push($this->pieces, ...$query->pieces);
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
     * Adopts the clause-tracking state of the given query, so that subsequent
     * clause calls continue correctly after wrapping an existing query. Unlike
     * {@see self::merge()} this does not copy any pieces; it only carries over
     * the defined clauses and the current clause.
     *
     * @param QueryInterface $query
     *
     * @return void
     * @author Bas Milius <bas@mili.us>
     * @since 2.3.0
     * @internal
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
    public function parenthesis(callable $fn, bool $patch = true): static
    {
        $index = count($this->pieces);

        $this->parenthesisOpen();
        $fn($this);
        $this->parenthesisClose();

        if ($patch) {
            $clausePlusOne = $this->pieces[$index + 1]->clause;
            $open = $this->pieces[$index];
            $inner = $this->pieces[$index + 1];

            $this->pieces[$index] = new Piece(
                (!empty($clausePlusOne) ? $clausePlusOne . ' ' : '') . $open->clause,
                $open->data,
                $open->separator
            );

            $this->pieces[$index + 1] = new Piece('', $inner->data, $inner->separator);
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
    public function replaceClause(string $clause, callable $fn): static
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

        foreach ($this->pieces as $piece) {
            $data = $piece->data;

            if (is_array($data)) {
                $data = implode($piece->separator ?? $this->grammar->columnSeparator, $data);
            }

            $pieces[] = $piece->clause;

            if (!empty($data)) {
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
        $clone = clone $this;

        return (int)$clone
            ->replaceClause('select', static fn(Piece $piece) => new Piece('select', 'count(*)', null))
            ->withoutModel()
            ->statement()
            ->fetchColumn();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function totalCount(): int
    {
        $original = clone $this;

        if (!$original->isClauseDefined('having')) {
            $original->replaceClause('select', static fn(Piece $piece) => new Piece($piece->clause, '*', $piece->separator));
        }

        $original->removeClause('limit');
        $original->removeClause('offset');
        $original->removeClause('order by');

        return (int)$original
            ->replaceClause('select', static fn(Piece $piece) => new Piece('select', 'count(*)', null))
            ->withoutModel()
            ->statement()
            ->fetchColumn();
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
    public function array(int $fetchMode = PDO::FETCH_ASSOC, array $options = []): array
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
    public function arrayList(int $fetchMode = PDO::FETCH_ASSOC, array $options = []): ArrayListInterface|ModelArrayList
    {
        return $this
            ->statement($options)
            ->arrayList($fetchMode);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function cursor(int $fetchMode = PDO::FETCH_ASSOC, array $options = []): Generator
    {
        return $this
            ->statement($options)
            ->cursor($fetchMode);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.3.1
     */
    public function paginate(int $offset, int $limit, ?callable $itemBuilder = null, ?callable $totalBuilder = null, int $fetchMode = PDO::FETCH_ASSOC, array $options = []): Paginated
    {
        return $this
            ->statement($options)
            ->paginate($offset, $limit, $itemBuilder, $totalBuilder, $fetchMode);
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
    public function single(int $fetchMode = PDO::FETCH_ASSOC, array $options = []): Model|stdClass|array|null
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
    public function singleOrFail(int $fetchMode = PDO::FETCH_ASSOC, array $options = []): Model|stdClass|array
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

                if (!$this->withDeleted && $structure->softDeleteColumn !== null && $this->isClauseDefined('select')) {
                    if ($this->isClauseDefined('where')) {
                        $softDeleteColumn = $this->compileColumnField($structure->getColumn($structure->softDeleteColumn));

                        $this->replaceClause('where', static function (Piece $piece) use ($softDeleteColumn): Piece {
                            return new Piece('where', "{$softDeleteColumn} is null and", $piece->separator);
                        });
                    } else {
                        $this->whereNull($structure->getColumn($structure->softDeleteColumn));
                    }
                }

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
    public function from(QueryInterface|array|string $tables, ?string $alias = null): static
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
    public function groupBy(QueryValueInterface|QueryLiteralInterface|array|string $fields, bool $withRollup = false): static
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
    public function havingIn(QueryValueInterface|QueryLiteralInterface|string $field, ArrayableInterface|array $options): static
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
    public function havingNotNull(QueryValueInterface|QueryLiteralInterface|string $field): static
    {
        return $this->having($field, Expr::isNotNull());
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.2
     */
    public function havingNotIn(QueryValueInterface|QueryLiteralInterface|string $field, ArrayableInterface|array $options): static
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
    public function havingNull(QueryValueInterface|QueryLiteralInterface|string $field): static
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
    public function orHavingIn(QueryValueInterface|QueryLiteralInterface|string $field, ArrayableInterface|array $options): static
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
    public function orHavingNotIn(QueryValueInterface|QueryLiteralInterface|string $field, ArrayableInterface|array $options): static
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
    public function orHavingNotNull(QueryValueInterface|QueryLiteralInterface|string $field): static
    {
        return $this->orHaving($field, Expr::isNotNull());
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.1.0
     */
    public function orHavingNull(QueryValueInterface|QueryLiteralInterface|string $field): static
    {
        return $this->orHaving($field, Expr::isNull());
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function limit(int $limit, int $offset = 0): static
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
                $fields[$key] = $value->column;
            } elseif (str_contains($value, '=')) {
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
        return $this->addExpression('or', $lhs, $cmp, $rhs);
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
    public function orWhereHas(string $relation, ?callable $fn = null): static
    {
        return $this->baseWhereHas($relation, $fn, $this->orWhere(...));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orWhereIn(QueryValueInterface|QueryLiteralInterface|string $field, ArrayableInterface|array $options): static
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
    public function orWhereNotHas(string $relation, ?callable $fn = null): static
    {
        return $this->baseWhereHas($relation, $fn, $this->orWhere(...), true);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.2
     */
    public function orWhereNotIn(QueryValueInterface|QueryLiteralInterface|string $field, ArrayableInterface|array $options): static
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
    public function orWhereNotNull(QueryValueInterface|QueryLiteralInterface|string $field): static
    {
        return $this->orWhere($field, Expr::isNotNull());
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orWhereNull(QueryValueInterface|QueryLiteralInterface|string $field): static
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
    public function orderBy(QueryValueInterface|QueryLiteralInterface|array|string $fields): static
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
    public function orderByAsc(QueryValueInterface|QueryLiteralInterface|string $field): static
    {
        $clause = $this->currentClause === 'order by' ? trim($this->grammar->columnSeparator) : 'order by';

        return $this->addPiece($clause, $this->compileColumnField($field) . ' ' . SortDirection::ASC->value);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orderByDesc(QueryValueInterface|QueryLiteralInterface|string $field): static
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
    public function update(string $table, ?array $pairs = null): static
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
        return $this->addExpression($this->isClauseDefined('where') || $this->isDoingJoin ? 'and' : 'where', $lhs, $cmp, $rhs);
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
    public function whereHas(string $relation, ?callable $fn = null): static
    {
        return $this->baseWhereHas($relation, $fn, $this->where(...));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function whereIn(QueryValueInterface|QueryLiteralInterface|string $field, ArrayableInterface|array $options): static
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
    public function whereNotHas(string $relation, ?callable $fn = null): static
    {
        return $this->baseWhereHas($relation, $fn, $this->where(...), true);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.2
     */
    public function whereNotIn(QueryValueInterface|QueryLiteralInterface|string $field, ArrayableInterface|array $options): static
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
    public function whereNotNull(QueryValueInterface|QueryLiteralInterface|string $field): static
    {
        return $this->where($field, Expr::isNotNull());
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function whereNull(QueryValueInterface|QueryLiteralInterface|string $field): static
    {
        return $this->where($field, Expr::isNull());
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function wherePrimaryKey(string $modelClass, array|int|string $primaryKey): static
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
    public function wherePrimaryKeyIn(string $modelClass, array $primaryKeys): static
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
            $colTuple = '(' . implode(', ', $columns) . ')';
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
    public function insertInto(string $table, array $fields): static
    {
        return $this->baseInsert('insert into', $table, $fields);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function insertIgnoreInto(string $table, array $fields): static
    {
        return $this->baseInsert('insert ignore into', $table, $fields);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function insertIntoValues(string $table, array $pairs): static
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
    public function insertIgnoreIntoValues(string $table, array $pairs): static
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
    public function replaceInto(string $table, array $fields): static
    {
        return $this->baseInsert('replace into', $table, $fields);
    }

    /**
     * {@inheritdoc}
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
    public function selectSuffix(string $suffix, QueryInterface|QueryExpressionInterface|QueryLiteralInterface|Stringable|array|string|int|float|bool ...$fields): static
    {
        return $this->baseSelect("select {$suffix}", $this->normalizeSelect($fields));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function fullJoin(string $table, ?callable $fn = null): static
    {
        return $this->baseJoin('full join', $table, $fn);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function innerJoin(string $table, ?callable $fn = null): static
    {
        return $this->baseJoin('inner join', $table, $fn);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function join(string $table, ?callable $fn = null): static
    {
        return $this->baseJoin('join', $table, $fn);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function leftJoin(string $table, ?callable $fn = null): static
    {
        return $this->baseJoin('left join', $table, $fn);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function leftOuterJoin(string $table, ?callable $fn = null): static
    {
        return $this->baseJoin('left outer join', $table, $fn);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function rightJoin(string $table, ?callable $fn = null): static
    {
        return $this->baseJoin('right join', $table, $fn);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 3.0.0
     */
    public function fullJoinSub(QueryInterface $query, string $alias, ?callable $on = null): static
    {
        return $this->baseJoinSub('full join', $query, $alias, $on);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 3.0.0
     */
    public function innerJoinSub(QueryInterface $query, string $alias, ?callable $on = null): static
    {
        return $this->baseJoinSub('inner join', $query, $alias, $on);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 3.0.0
     */
    public function joinSub(QueryInterface $query, string $alias, ?callable $on = null): static
    {
        return $this->baseJoinSub('join', $query, $alias, $on);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 3.0.0
     */
    public function leftJoinSub(QueryInterface $query, string $alias, ?callable $on = null): static
    {
        return $this->baseJoinSub('left join', $query, $alias, $on);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 3.0.0
     */
    public function leftOuterJoinSub(QueryInterface $query, string $alias, ?callable $on = null): static
    {
        return $this->baseJoinSub('left outer join', $query, $alias, $on);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 3.0.0
     */
    public function rightJoinSub(QueryInterface $query, string $alias, ?callable $on = null): static
    {
        return $this->baseJoinSub('right join', $query, $alias, $on);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function with(string $name, QueryInterface $query): static
    {
        return $this->baseWith('with', $name, $query);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function withRecursive(string $name, QueryInterface $query): static
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
    protected function baseInsert(string $clause, string $table, array $fields): static
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
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function baseJoin(string $clause, string $table, ?callable $fn): static
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
     * expressions. Bewust `raw(toSql())` + `mergeParams()` en niet `merge()`,
     * zodat de splice-volgorde van joins vóór `where` intact blijft.
     *
     * @param string $clause
     * @param QueryInterface $query
     * @param string $alias
     * @param callable|null $on
     *
     * @return static<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 3.0.0
     */
    protected function baseJoinSub(string $clause, QueryInterface $query, string $alias, ?callable $on): static
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
    protected function baseSelect(string $clause, array $fields): static
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
    protected function baseWhereHas(string $relation, ?callable $fn, callable $clause, bool $negate = false): static
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
    protected function baseWith(string $clause, string $name, QueryInterface $query): static
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
     * Sub-queries and expressions merge their bound parameters into the host.
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
            } else {
                $escaped = $this->grammar->escape((string)$value);

                yield $alias !== null ? "{$escaped} as {$alias}" : $escaped;
            }
        }
    }

    /**
     * Renders a column-referencing field to its SQL form. An expression (e.g.
     * a {@see Expression\ColumnRef}) is compiled and its bound parameters merged
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

}
