<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use BackedEnum;
use Closure;
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
use Raxos\Database\Query\Error\{ConnectionErrorException, IncompleteException, MissingAliasException, MissingClauseException, MissingModelException, MissingResultException, StructureErrorException, TooFewPrimaryKeyValuesExceptions, TooManyPrimaryKeyValuesExceptions};
use Raxos\Database\Query\Literal\{ColumnLiteral, Literal};
use stdClass;
use Stringable;
use function array_any;
use function array_find_key;
use function array_is_list;
use function array_keys;
use function array_map;
use function array_merge;
use function array_shift;
use function array_splice;
use function array_unique;
use function array_unshift;
use function array_values;
use function assert;
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
use function substr;
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
    private array $pieces = [];
    private ?int $position = null;
    private bool $withDeleted = false;

    private array $eagerLoad = [];
    private array $eagerLoadDisable = [];
    private array $params = [];
    private readonly int $paramsIndex;

    private ?Closure $beforeRelations = null;

    /**
     * Query constructor.
     *
     * @param ConnectionInterface $connection
     * @param bool $prepared
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(
        public readonly ConnectionInterface $connection,
        public readonly bool $prepared = true
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

            if (!($rhs instanceof QueryExpressionInterface)) {
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

        if (!$this->prepared) {
            if (is_int($value)) {
                return $value;
            }

            try {
                return $this->connection->quote((string)$value);
            } catch (DatabaseExceptionInterface $err) {
                throw new ConnectionErrorException($err);
            }
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if ($value instanceof Stringable) {
            $value = (string)$value;
        }

        $paramsCount = count($this->params);
        $name = ":{$this->paramsIndex}{$paramsCount}";

        $this->params[$name] = $value;

        return $name;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function addPiece(string $clause, ColumnLiteral|array|string|int|null $data = null, ?string $separator = null): static
    {
        if ($data instanceof ColumnLiteral) {
            $data = (string)$data;
        }

        if ($clause === 'select' && $this->isClauseDefined('select')) {
            $index = array_find_key($this->pieces, static fn(array $piece) => $piece[0] === 'select');

            if (is_array($this->pieces[$index][1]) && is_array($data)) {
                $this->pieces[$index][1] = [
                    ...$this->pieces[$index][1],
                    ...$data
                ];
            } elseif (is_array($this->pieces[$index][1])) {
                $this->pieces[$index][1][] = $data;
            } elseif (is_array($data)) {
                $this->pieces[$index][1] = [
                    $this->pieces[$index][1],
                    ...$data
                ];
            } else {
                $this->pieces[$index][1] = [$this->pieces[$index][1], $data];
            }

            return $this;
        }

        if ($this->position !== null) {
            array_splice($this->pieces, $this->position++, 0, [[$clause, $data, $separator]]);
        } else {
            $this->pieces[] = [$clause, $data, $separator];
        }

        if (isset($clause[2])) {
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
        foreach ($query->pieces as [$clause, $data, $separator]) {
            $this->pieces[] = [$clause, $data, $separator];
        }

        $this->params = array_merge($this->params, $query->params);

        return $this;
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
            $clause = $this->pieces[$index + 1][0];
            $this->pieces[$index][0] = (!empty($clause) ? $clause . ' ' : '') . $this->pieces[$index][0];
            $this->pieces[$index + 1][0] = '';
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
            array_splice($this->pieces, $this->position++, 0, [[$expression, null, null]]);
        } else {
            $this->pieces[] = [$expression, null, null];
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
        return array_any($this->pieces, static fn(array $piece) => $piece[0] === $clause);
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
        $index = array_find_key($this->pieces, static fn(array $piece) => $piece[0] === $clause);

        if ($index !== null) {
            array_splice($this->pieces, $index, 1);

            while (isset($this->pieces[$index]) && $this->pieces[$index][0] === ',') {
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
        $index = array_find_key($this->pieces, static fn(array $piece) => $piece[0] === $clause);

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

        foreach ($this->pieces as [$clause, $data, $separator]) {
            if (is_array($data)) {
                $data = implode($separator ?? $this->grammar->columnSeparator, $data);
            }

            $pieces[] = $clause;

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
        return (int)$this
            ->replaceClause('select', static fn(array $piece) => ['select', 'count(*)', null])
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
            $original->replaceClause('select', static function (array $piece): array {
                $piece[1] = '*';

                return $piece;
            });
        }

        $original->removeClause('limit');
        $original->removeClause('offset');
        $original->removeClause('order by');

        return (int)$original
            ->replaceClause('select', static fn(array $piece) => ['select', 'count(*)', null])
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

        array_unshift($explain->pieces, ['explain', null, null]);

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
    public function run(array $options = []): void
    {
        $this
            ->statement($options)
            ->run();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function runReturning(QueryLiteralInterface|array|string $column): array|string|int
    {
        $sqlColumn = match (true) {
            is_array($column) => array_map($this->grammar->escape(...), $column),
            is_string($column) => $this->grammar->escape($column),
            default => $column
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
            if ($col instanceof ColumnLiteral) {
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
                        $this->replaceClause('where', static function (array $piece) use ($structure): array {
                            $softDeleteColumn = $structure->getColumn($structure->softDeleteColumn);

                            $piece[0] = "where";
                            $piece[1] = "{$softDeleteColumn} is null and";

                            return $piece;
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
    public function _internal_beforeRelations(callable $fn): QueryInterface
    {
        $this->beforeRelations = $fn;

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public function _internal_invokeBeforeRelations(ArrayListInterface $instances): void
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
        $this->modelClass = null;
        $this->params = [];
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
     * @since 1.0.0
     */
    public function groupBy(QueryLiteralInterface|array|string $fields, bool $withRollup = false): static
    {
        if (!is_array($fields)) {
            $fields = [$fields];
        }

        $fields = array_map(\strval(...), $fields);
        $fields = array_map($this->grammar->escape(...), $fields);

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
        return $this->having(expr->exists(expr->subQuery($query)));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function havingIn(QueryLiteralInterface|string $field, ArrayableInterface|array $options): static
    {
        return $this->having($field, expr->in(...$options));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.2
     */
    public function havingNotExists(QueryInterface $query): static
    {
        return $this->having(expr->not(expr->exists(expr->subQuery($query))));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function havingNotNull(QueryLiteralInterface|string $field): static
    {
        return $this->having($field, expr->isNotNull());
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.2
     */
    public function havingNotIn(QueryLiteralInterface|string $field, ArrayableInterface|array $options): static
    {
        return $this->having($field, expr->not(expr->in(...$options)));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function havingNull(QueryLiteralInterface|string $field): static
    {
        return $this->having($field, expr->isNull());
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function offset(int $offset): static
    {
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
            if ($value instanceof ColumnLiteral) {
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
        return $this->orWhere(expr->exists(expr->subQuery($query)));
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
    public function orWhereIn(QueryLiteralInterface|string $field, ArrayableInterface|array $options): static
    {
        return $this->orWhere($field, expr->in(...$options));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.2
     */
    public function orWhereNotExists(QueryInterface $query): static
    {
        return $this->orWhere(expr->not(expr->exists(expr->subQuery($query))));
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
    public function orWhereNotIn(QueryLiteralInterface|string $field, ArrayableInterface|array $options): static
    {
        return $this->orWhere($field, expr->not(expr->in(...$options)));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orWhereNotNull(QueryLiteralInterface|string $field): static
    {
        return $this->orWhere($field, expr->isNotNull());
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orWhereNull(QueryLiteralInterface|string $field): static
    {
        return $this->orWhere($field, expr->isNull());
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
    public function orderBy(QueryLiteralInterface|array|string $fields): static
    {
        if ($fields instanceof QueryLiteralInterface) {
            $fields = [(string)$fields];
        } else if (is_string($fields)) {
            $fields = [$fields];
        }

        $fields = array_map(function (QueryValueInterface|string $field): string {
            if ($field instanceof QueryLiteralInterface) {
                $field = (string)$field;
            }

            if (str_contains($field, ' asc') || str_contains($field, ' ASC')) {
                return $this->grammar->escape(substr($field, 0, -4)) . ' asc';
            }

            if (str_contains($field, ' desc') || str_contains($field, ' DESC')) {
                return $this->grammar->escape(substr($field, 0, -5)) . ' desc';
            }

            return $this->grammar->escape($field);
        }, $fields);

        return $this->addPiece('order by', $fields, $this->grammar->columnSeparator);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orderByAsc(QueryLiteralInterface|string $field): static
    {
        if (is_string($field)) {
            $field = $this->grammar->escape($field);
        }

        $clause = $this->currentClause === 'order by' ? trim($this->grammar->columnSeparator) : 'order by';

        return $this->addPiece($clause, $field . ' asc');
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function orderByDesc(QueryLiteralInterface|string $field): static
    {
        if (is_string($field)) {
            $field = $this->grammar->escape($field);
        }

        $clause = $this->currentClause === 'order by' ? trim($this->grammar->columnSeparator) : 'order by';

        return $this->addPiece($clause, $field . ' desc');
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
        $expression = $this->grammar->escape((string)$field) . ' = ' . $value;

        if ($this->currentClause === 'set') {
            $index = count($this->pieces) - 1;
            $existing = $this->pieces[$index][1];

            if (!is_array($existing)) {
                $existing = [$existing];
            }

            $existing[] = $expression;

            $this->pieces[$index][1] = $existing;
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
        return $this->where(expr->exists(expr->subQuery($query)));
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
    public function whereIn(QueryLiteralInterface|string $field, ArrayableInterface|array $options): static
    {
        return $this->where($field, expr->in(...$options));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.2
     */
    public function whereNotExists(QueryInterface $query): static
    {
        return $this->where(expr->not(expr->exists(expr->subQuery($query))));
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
    public function whereNotIn(QueryLiteralInterface|string $field, ArrayableInterface|array $options): static
    {
        return $this->where($field, expr->not(expr->in(...$options)));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function whereNotNull(QueryLiteralInterface|string $field): static
    {
        return $this->where($field, expr->isNotNull());
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function whereNull(QueryLiteralInterface|string $field): static
    {
        return $this->where($field, expr->isNull());
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
                throw new TooFewPrimaryKeyValuesExceptions($modelClass);
            }

            $value = array_shift($primaryKey);

            if (is_int($value) || is_float($value)) {
                $value = literal($value);
            }

            $this->where($structure->getColumn($property->name), $value);
        }

        if (!empty($primaryKey)) {
            throw new TooManyPrimaryKeyValuesExceptions($modelClass);
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
        $structure = StructureGenerator::for($modelClass);
        $properties = $structure->primaryKey;

        if (count($properties) === 1) {
            return $this->where($structure->getColumn($properties[0]->key), expr->in(...$primaryKeys));
        }

        $columns = array_map(static fn(PropertyDefinition $property) => $structure->getColumn($property->name), $properties);

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
    public function select(Select|Stringable|array|string|int $fields = []): static
    {
        return $this->baseSelect('select', $fields);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function selectDistinct(Select|Stringable|array|string|int $fields = []): static
    {
        return $this->selectSuffix('distinct', $fields);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function selectFoundRows(Select|Stringable|array|string|int $fields = []): static
    {
        return $this->selectSuffix('sql_calc_found_rows', $fields);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function selectSuffix(string $suffix, Select|Stringable|array|string|int $fields = []): static
    {
        return $this->baseSelect("select {$suffix}", $fields);
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

        foreach ($this->pieces as $index => [$existingClause, $existingTable]) {
            if ($existingClause === 'where') {
                $this->position = $index;
            }

            // note(Bas): this filters out double joins, but we need to figure out
            //  if we still need to execute the given function.
            if (str_contains($existingClause, 'join') && $existingTable === $table) {
                return $this;
            }
        }

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
     * Base function to create `select` expressions.
     *
     * @param string $clause
     * @param ColumnLiteral|Literal|Select|array|string|int $fields
     *
     * @return static<TModel>
     * @throws OrmExceptionInterface
     * @throws QueryExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function baseSelect(string $clause, ColumnLiteral|Literal|Select|array|string|int $fields): static
    {
        if (empty($fields) || ($fields instanceof Select && $fields->isEmpty)) {
            if ($this->modelClass !== null) {
                return $this->addPiece($clause, $this->modelClass::col('*'));
            }

            return $this->addPiece($clause, '*');
        }

        if (is_int($fields) || $fields instanceof ColumnLiteral) {
            return $this->addPiece($clause, $fields);
        }

        if ($fields instanceof Literal) {
            return $this->addPiece($clause, (string)$fields);
        }

        if (is_string($fields)) {
            return $this->addPiece($clause, $this->grammar->escape($fields));
        }

        $result = match (true) {
            $fields instanceof Select => array_map(fn(SelectEntry $entry) => $entry->unwrap(
                $this,
                $this->connection,
                $this->grammar
            ), $fields->entries),

            array_is_list($fields) => iterator_to_array($this->unwrapSelectList($fields)),

            default => iterator_to_array($this->unwrapSelectMap($fields)),
        };

        return $this->addPiece($clause, array_unique($result), $this->grammar->columnSeparator);
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
                $clause(expr->not(expr->exists(expr->subQuery($query))));
            } else {
                $clause(expr->exists(expr->subQuery($query)));
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
     * Unwraps a select list.
     *
     * @param array $fields
     *
     * @return Generator
     * @throws QueryExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     * @see self::baseSelect()
     */
    private function unwrapSelectList(array $fields): Generator
    {
        foreach ($fields as $field) {
            if (is_array($field) && count($field) === 2) {
                yield $this->grammar->escape($field[0]) . ' as ' . $this->grammar->escape($field[1]);
                continue;
            }

            if (is_numeric($field)) {
                yield (string)$field;
                continue;
            }

            if ($field instanceof QueryExpressionInterface) {
                throw new MissingAliasException();
            }

            if ($field instanceof QueryLiteralInterface) {
                yield (string)$field;
                continue;
            }

            yield $this->grammar->escape((string)$field);
        }
    }

    /**
     * Unwrap a select map.
     *
     * @param array $fields
     *
     * @return Generator
     * @throws QueryExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     * @see self::baseSelect()
     */
    private function unwrapSelectMap(array $fields): Generator
    {
        foreach ($fields as $alias => $field) {
            $alias = $this->grammar->escape($alias);

            if ($field === null || $field === true) {
                yield $alias;
                continue;
            }

            if ($field instanceof QueryInterface) {
                $sql = $field->toSql();

                yield "({$sql}) as {$alias}";
                continue;
            }

            if ($field instanceof QueryExpressionInterface) {
                assert($alias !== null);

                $query = new static($this->connection);
                $field->compile($query, $this->connection, $this->grammar);

                return "({$query}) as {$alias}";
            }

            if ($field instanceof QueryLiteralInterface) {
                if ($alias !== null) {
                    yield "{$field} as {$alias}";
                } else {
                    yield (string)$field;
                }

                continue;
            }

            yield $this->grammar->escape($field) . ' as ' . $alias;
        }
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
            'type' => $this->prepared ? 'PREPARED QUERY' : 'RAW QUERY',
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
